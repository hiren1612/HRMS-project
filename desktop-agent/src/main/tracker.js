'use strict';

/**
 * The time-tracking state machine.
 *
 * Deliberately free of any Electron import so it can be unit tested headless:
 * the clock and the idle-time reading are both injected. src/main/main.js
 * wires the real ones in (Date.now and powerMonitor.getSystemIdleTime).
 *
 * How idle is detected
 * --------------------
 * Windows is asked how many seconds it has been since ANY input. No keyboard
 * hook is installed and no key codes are read, so nothing the employee types
 * is ever visible to this process. That is both a privacy property and a
 * practical one: global input hooks are routinely flagged as keyloggers by
 * antivirus software, and this avoids the problem entirely.
 *
 * Back-dating an idle period
 * --------------------------
 * The brief's own example: input stops at 10:30, the threshold is 5 minutes,
 * so the status flips to Idle at 10:35. Those five minutes were not work, so
 * when the flip happens they are moved out of active and into idle rather
 * than left banked. Without this every idle period would silently credit one
 * threshold's worth of productive time.
 */

const STATUS = {
  ACTIVE: 'active',
  IDLE: 'idle',
  PAUSED: 'paused',
  OFF: 'offline',
};

class Tracker {
  /**
   * @param {object}   deps
   * @param {function} deps.getIdleSeconds  seconds since last system input
   * @param {function} [deps.now]           ms epoch, injectable for tests
   * @param {object}   deps.settings        resolved server settings
   * @param {function} [deps.onBucket]      called with each completed bucket
   * @param {function} [deps.onIdleStart]
   * @param {function} [deps.onIdleEnd]
   * @param {function} [deps.onStatusChange]
   */
  constructor({
    getIdleSeconds,
    now = () => Date.now(),
    settings,
    onBucket = () => {},
    onIdleStart = () => {},
    onIdleEnd = () => {},
    onStatusChange = () => {},
  }) {
    this.getIdleSeconds = getIdleSeconds;
    this.now = now;
    this.settings = settings;
    this.onBucket = onBucket;
    this.onIdleStart = onIdleStart;
    this.onIdleEnd = onIdleEnd;
    this.onStatusChange = onStatusChange;

    this.running = false;
    this.paused = false;
    this.status = STATUS.OFF;

    this.sessionActiveSeconds = 0;
    this.sessionIdleSeconds = 0;
    this.pausedSecondsToday = 0;

    this.bucket = null;
    this.openIdleStartedAt = null;
    this.lastTickAt = null;
  }

  // ---------------------------------------------------------------- control

  start(resumeState = null) {
    this.running = true;
    this.lastTickAt = this.now();

    if (resumeState) {
      // Survives the app being closed and reopened, or crashing.
      this.sessionActiveSeconds = resumeState.activeSeconds || 0;
      this.sessionIdleSeconds = resumeState.idleSeconds || 0;
      this.pausedSecondsToday = resumeState.pausedSecondsToday || 0;
    }

    this.#openBucket();
    this.#setStatus(STATUS.ACTIVE);
  }

  stop() {
    if (!this.running) return;

    this.#closeOpenIdlePeriod();
    this.#flushBucket();
    this.running = false;
    this.#setStatus(STATUS.OFF);
  }

  /**
   * Employee-initiated pause. Only honoured when the server allows it and
   * the daily allowance has not been used up.
   */
  pause() {
    if (!this.running || this.paused) return false;
    if (!this.settings.allow_pause) return false;
    if (this.pausedSecondsToday >= (this.settings.max_pause_minutes_per_day || 0) * 60) return false;

    this.#closeOpenIdlePeriod();
    this.#flushBucket();
    this.paused = true;
    this.#openBucket();
    this.#setStatus(STATUS.PAUSED);

    return true;
  }

  resume() {
    if (!this.paused) return false;

    this.#flushBucket();
    this.paused = false;
    this.#openBucket();
    this.#setStatus(STATUS.ACTIVE);

    return true;
  }

  // ------------------------------------------------------------------- tick

  /**
   * Called on a short interval by the main process. Everything is derived
   * from how much real time has passed, not from how many times this ran,
   * so a missed interval does not lose time and a flood of them cannot
   * invent any.
   */
  tick() {
    if (!this.running) return;

    const nowMs = this.now();
    const tickMs = (this.settings.tick_seconds || 5) * 1000;

    let elapsed = Math.floor((nowMs - this.lastTickAt) / 1000);
    this.lastTickAt = nowMs;

    if (elapsed <= 0) return;

    // A suspended or hibernated PC can produce an enormous gap. That time
    // was not spent working, so it is discarded rather than banked.
    const ceiling = Math.max(Math.ceil((tickMs / 1000) * 3), 10);
    if (elapsed > ceiling) elapsed = 0;

    // Outside configured working hours nothing is recorded at all.
    if (!this.withinWorkingHours(new Date(nowMs))) {
      this.#closeOpenIdlePeriod();
      this.#flushBucket();
      this.#setStatus(STATUS.OFF);
      this.#openBucket();
      return;
    }

    if (this.paused) {
      this.pausedSecondsToday += elapsed;
      this.#setStatus(STATUS.PAUSED);
      this.#rollBucketIfDue(nowMs);

      // Auto-resume once the daily pause allowance is spent.
      if (this.pausedSecondsToday >= (this.settings.max_pause_minutes_per_day || 0) * 60) {
        this.resume();
      }
      return;
    }

    const idleSeconds = this.getIdleSeconds();
    const threshold = this.settings.idle_threshold_seconds || 300;
    const shouldBeIdle = idleSeconds >= threshold;

    if (shouldBeIdle && this.status !== STATUS.IDLE) {
      this.#beginIdle(nowMs, threshold, elapsed);
    } else if (!shouldBeIdle && this.status === STATUS.IDLE) {
      this.#endIdle(nowMs, elapsed);
    }

    if (this.status === STATUS.IDLE) {
      this.sessionIdleSeconds += elapsed;
      this.bucket.idleSeconds += elapsed;
    } else {
      this.sessionActiveSeconds += elapsed;
      this.bucket.activeSeconds += elapsed;
    }

    this.#rollBucketIfDue(nowMs);
  }

  // ------------------------------------------------------------- idle edges

  #beginIdle(nowMs, threshold, elapsed) {
    // The idle actually began one threshold ago, when input stopped. Those
    // seconds were counted as active on the way here, so move them across.
    //
    // All but the current tick: this tick has not been attributed yet and
    // is about to be added to idle by the caller. Moving the full threshold
    // would count the same seconds twice and leave the split one tick out.
    const backdated = Math.min(threshold - elapsed, this.sessionActiveSeconds);

    this.sessionActiveSeconds -= backdated;
    this.sessionIdleSeconds += backdated;

    const fromBucket = Math.min(backdated, this.bucket.activeSeconds);
    this.bucket.activeSeconds -= fromBucket;
    this.bucket.idleSeconds += fromBucket;

    this.openIdleStartedAt = nowMs - threshold * 1000;
    this.#setStatus(STATUS.IDLE);
    this.onIdleStart({ startedAt: new Date(this.openIdleStartedAt).toISOString() });
  }

  #endIdle(nowMs, elapsed) {
    // The idle ended at the START of this tick, because this tick's seconds
    // are credited as active. Closing it at nowMs would report a period one
    // tick longer than the idle total actually recorded.
    this.#closeOpenIdlePeriod(nowMs - elapsed * 1000);
    this.#setStatus(STATUS.ACTIVE);
  }

  #closeOpenIdlePeriod(nowMs = null) {
    if (this.openIdleStartedAt === null) return;

    const endedAt = nowMs ?? this.now();
    const seconds = Math.max(0, Math.floor((endedAt - this.openIdleStartedAt) / 1000));

    this.onIdleEnd({
      startedAt: new Date(this.openIdleStartedAt).toISOString(),
      endedAt: new Date(endedAt).toISOString(),
      seconds,
    });

    this.openIdleStartedAt = null;
  }

  // ---------------------------------------------------------------- buckets

  #openBucket() {
    this.bucket = {
      startedAt: this.now(),
      activeSeconds: 0,
      idleSeconds: 0,
    };
  }

  #rollBucketIfDue(nowMs) {
    const size = (this.settings.activity_bucket_seconds || 60) * 1000;

    if (nowMs - this.bucket.startedAt >= size) {
      this.#flushBucket(nowMs);
      this.#openBucket();
    }
  }

  #flushBucket(nowMs = null) {
    if (!this.bucket) return;

    const endedAt = nowMs ?? this.now();

    // Nothing happened in it; there is no value in a row of zeroes.
    if (this.bucket.activeSeconds === 0 && this.bucket.idleSeconds === 0) {
      this.bucket = null;
      return;
    }

    this.onBucket({
      client_uuid: randomUuid(),
      bucket_started_at: toSqlDateTime(new Date(this.bucket.startedAt)),
      bucket_ended_at: toSqlDateTime(new Date(endedAt)),
      active_seconds: this.bucket.activeSeconds,
      idle_seconds: this.bucket.idleSeconds,
      // Always zero without a global input hook, which is deliberately not
      // installed. The column exists so counts can be added later without a
      // schema change if the antivirus trade-off is ever accepted.
      mouse_events: 0,
      key_events: 0,
      status: this.status === STATUS.OFF ? STATUS.IDLE : this.status,
    });

    this.bucket = null;
  }

  // ----------------------------------------------------------------- status

  #setStatus(next) {
    if (this.status === next) return;

    this.status = next;
    this.onStatusChange(next);
  }

  /**
   * Is the given moment inside the configured working window?
   */
  withinWorkingHours(date = new Date()) {
    const wh = this.settings.working_hours;

    if (!wh || !wh.enforce) return true;

    // 1 = Monday … 7 = Sunday, matching the server.
    const isoDay = date.getDay() === 0 ? 7 : date.getDay();
    if (Array.isArray(wh.days) && !wh.days.includes(isoDay)) return false;

    const hhmm = String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');

    return hhmm >= (wh.start || '00:00') && hhmm <= (wh.end || '23:59');
  }

  /**
   * Everything the tray, the window and the crash-recovery file need.
   */
  snapshot() {
    return {
      running: this.running,
      paused: this.paused,
      status: this.status,
      activeSeconds: this.sessionActiveSeconds,
      idleSeconds: this.sessionIdleSeconds,
      pausedSecondsToday: this.pausedSecondsToday,
      trackedSeconds: this.sessionActiveSeconds + this.sessionIdleSeconds,
    };
  }

  /**
   * May a screenshot be taken right now? Captures are skipped while idle,
   * while paused and outside working hours, as the brief requires.
   */
  isCaptureEligible() {
    return this.running && !this.paused && this.status === STATUS.ACTIVE && this.withinWorkingHours();
  }
}

function toSqlDateTime(date) {
  const p = (n) => String(n).padStart(2, '0');

  return (
    date.getFullYear() + '-' + p(date.getMonth() + 1) + '-' + p(date.getDate()) +
    ' ' + p(date.getHours()) + ':' + p(date.getMinutes()) + ':' + p(date.getSeconds())
  );
}

function randomUuid() {
  return require('node:crypto').randomUUID();
}

module.exports = { Tracker, STATUS, toSqlDateTime };
