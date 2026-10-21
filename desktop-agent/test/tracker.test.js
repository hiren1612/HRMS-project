'use strict';

const test = require('node:test');
const assert = require('node:assert');

const { Tracker, STATUS } = require('../src/main/tracker');

/**
 * The tracker takes its clock and its idle reading as injected functions,
 * so a whole working day can be played out in milliseconds and the awkward
 * cases — suspend, back-dating, midnight, pause abuse — can be checked
 * properly rather than hoped about.
 */

const SETTINGS = {
  tick_seconds: 5,
  idle_threshold_seconds: 300,
  activity_bucket_seconds: 60,
  allow_pause: true,
  max_pause_minutes_per_day: 60,
  working_hours: { enforce: false },
};

/**
 * A controllable world: time only moves when the test says so.
 */
function world(settings = {}) {
  const state = {
    nowMs: new Date('2026-11-03T09:00:00').getTime(), // a Tuesday
    idleSeconds: 0,
    buckets: [],
    idlePeriods: [],
    statuses: [],
  };

  const tracker = new Tracker({
    now: () => state.nowMs,
    getIdleSeconds: () => state.idleSeconds,
    settings: { ...SETTINGS, ...settings },
    onBucket: (b) => state.buckets.push(b),
    onIdleEnd: (p) => state.idlePeriods.push(p),
    onStatusChange: (s) => state.statuses.push(s),
  });

  /**
   * Advance by `seconds`, ticking at the configured interval so the tracker
   * sees the same cadence it would in real life.
   */
  state.advance = (seconds, { idle = false } = {}) => {
    const step = SETTINGS.tick_seconds;

    for (let i = 0; i < seconds / step; i++) {
      state.nowMs += step * 1000;
      state.idleSeconds = idle ? state.idleSeconds + step : 0;
      tracker.tick();
    }
  };

  state.tracker = tracker;

  return state;
}

// ------------------------------------------------------------ basic timing

test('an hour of work banks an hour of active time', () => {
  const w = world();

  w.tracker.start();
  w.advance(3600);

  assert.strictEqual(w.tracker.snapshot().activeSeconds, 3600);
  assert.strictEqual(w.tracker.snapshot().idleSeconds, 0);
  assert.strictEqual(w.tracker.status, STATUS.ACTIVE);
});

test('activity is emitted in one-minute buckets', () => {
  const w = world();

  w.tracker.start();
  w.advance(300); // five minutes

  assert.strictEqual(w.buckets.length, 5);
  assert.ok(w.buckets.every((b) => b.active_seconds === 60));
});

test('every bucket carries a unique idempotency key', () => {
  const w = world();

  w.tracker.start();
  w.advance(600);

  const ids = w.buckets.map((b) => b.client_uuid);

  assert.strictEqual(new Set(ids).size, ids.length);
});

// --------------------------------------------------- the brief's own example

test('the worked example from the brief produces the right split', () => {
  // 09:00 start. Active until 10:30. No input 10:30-10:50. Back at 10:50.
  const w = world();

  w.tracker.start();
  w.advance(90 * 60);                  // 09:00 -> 10:30 working
  assert.strictEqual(w.tracker.status, STATUS.ACTIVE);

  w.advance(5 * 60, { idle: true });   // 10:30 -> 10:35 no input
  assert.strictEqual(w.tracker.status, STATUS.IDLE, 'idle at the 5-minute threshold');

  w.advance(15 * 60, { idle: true });  // 10:35 -> 10:50 still away
  w.advance(60);                       // 10:50 back at the keyboard

  assert.strictEqual(w.tracker.status, STATUS.ACTIVE, 'resumes on input');

  const snap = w.tracker.snapshot();

  // 110 minutes elapsed; 20 of them were away from the desk.
  assert.strictEqual(snap.idleSeconds, 20 * 60, 'the whole 20 minutes counts as idle');
  assert.strictEqual(snap.activeSeconds, 91 * 60, 'and none of it as work');
  assert.strictEqual(snap.trackedSeconds, 111 * 60);
});

test('the five minutes before idle is noticed are not credited as work', () => {
  // Without back-dating, every idle period would quietly gift one
  // threshold's worth of productive time.
  const w = world();

  w.tracker.start();
  w.advance(600);                       // 10 minutes working
  w.advance(300, { idle: true });       // 5 minutes away -> flips to idle

  assert.strictEqual(w.tracker.snapshot().activeSeconds, 600);
  assert.strictEqual(w.tracker.snapshot().idleSeconds, 300);
});

test('an idle period is reported with its real start time', () => {
  const w = world();

  w.tracker.start();
  w.advance(600);
  w.advance(600, { idle: true });
  w.advance(60);

  assert.strictEqual(w.idlePeriods.length, 1);
  assert.strictEqual(w.idlePeriods[0].seconds, 600);
});

test('brief interruptions do not trip the idle threshold', () => {
  const w = world();

  w.tracker.start();

  for (let i = 0; i < 10; i++) {
    w.advance(120, { idle: true }); // two minutes, under the five-minute threshold
    w.advance(60);                  // back at it
  }

  assert.strictEqual(w.tracker.snapshot().idleSeconds, 0);
  assert.strictEqual(w.tracker.status, STATUS.ACTIVE);
});

// ------------------------------------------------------- suspend and resume

test('a suspended PC does not bank the hours it was asleep', () => {
  const w = world();

  w.tracker.start();
  w.advance(600);

  const before = w.tracker.snapshot().trackedSeconds;

  // Lid closed for three hours, then one tick on waking.
  w.nowMs += 3 * 3600 * 1000;
  w.tracker.tick();

  assert.strictEqual(w.tracker.snapshot().trackedSeconds, before,
    'the gap is discarded, not counted');
});

// -------------------------------------------------------------------- pause

test('pausing stops both clocks', () => {
  const w = world();

  w.tracker.start();
  w.advance(600);

  assert.strictEqual(w.tracker.pause(), true);

  const snap = w.tracker.snapshot();

  w.advance(600);

  assert.strictEqual(w.tracker.snapshot().activeSeconds, snap.activeSeconds);
  assert.strictEqual(w.tracker.snapshot().idleSeconds, snap.idleSeconds);
  assert.strictEqual(w.tracker.status, STATUS.PAUSED);
});

test('pause is refused when the administrator has not allowed it', () => {
  const w = world({ allow_pause: false });

  w.tracker.start();

  assert.strictEqual(w.tracker.pause(), false);
  assert.strictEqual(w.tracker.status, STATUS.ACTIVE);
});

test('tracking resumes by itself once the daily pause allowance runs out', () => {
  const w = world({ max_pause_minutes_per_day: 10 });

  w.tracker.start();
  w.tracker.pause();
  w.advance(11 * 60);

  assert.strictEqual(w.tracker.paused, false, 'auto-resumed');
  assert.strictEqual(w.tracker.status, STATUS.ACTIVE);
});

test('the pause allowance cannot be reset by pausing again', () => {
  const w = world({ max_pause_minutes_per_day: 10 });

  w.tracker.start();
  w.tracker.pause();
  w.advance(11 * 60);          // allowance spent, auto-resumed

  assert.strictEqual(w.tracker.pause(), false, 'no further pause today');
});

// ------------------------------------------------------------ working hours

test('nothing is recorded outside working hours', () => {
  const w = world({ working_hours: { enforce: true, start: '09:00', end: '17:00', days: [1, 2, 3, 4, 5] } });

  w.nowMs = new Date('2026-11-03T22:00:00').getTime(); // Tuesday, late
  w.tracker.start();
  w.advance(1800);

  assert.strictEqual(w.tracker.snapshot().trackedSeconds, 0);
  assert.strictEqual(w.tracker.status, STATUS.OFF);
});

test('nothing is recorded at the weekend', () => {
  const w = world({ working_hours: { enforce: true, start: '09:00', end: '17:00', days: [1, 2, 3, 4, 5] } });

  w.nowMs = new Date('2026-11-08T11:00:00').getTime(); // Sunday
  w.tracker.start();
  w.advance(1800);

  assert.strictEqual(w.tracker.snapshot().trackedSeconds, 0);
});

test('tracking works normally inside working hours', () => {
  const w = world({ working_hours: { enforce: true, start: '09:00', end: '17:00', days: [1, 2, 3, 4, 5] } });

  w.nowMs = new Date('2026-11-03T10:00:00').getTime();
  w.tracker.start();
  w.advance(1800);

  assert.strictEqual(w.tracker.snapshot().activeSeconds, 1800);
});

// ----------------------------------------------------------- capture gating

test('screenshots are only eligible while genuinely working', () => {
  const w = world();

  w.tracker.start();
  w.advance(60);
  assert.strictEqual(w.tracker.isCaptureEligible(), true);

  w.advance(300, { idle: true });
  assert.strictEqual(w.tracker.isCaptureEligible(), false, 'not while idle');

  w.advance(60);
  w.tracker.pause();
  assert.strictEqual(w.tracker.isCaptureEligible(), false, 'not while paused');

  w.tracker.resume();
  w.tracker.stop();
  assert.strictEqual(w.tracker.isCaptureEligible(), false, 'not when stopped');
});

// ------------------------------------------------------------- restart

test('totals survive the agent being closed and reopened', () => {
  const first = world();

  first.tracker.start();
  first.advance(3600);

  const saved = first.tracker.snapshot();

  first.tracker.stop();

  const second = world();

  second.tracker.start(saved);
  second.advance(600);

  assert.strictEqual(second.tracker.snapshot().activeSeconds, 4200,
    'the hour before the restart is still there');
});

test('stopping flushes whatever is in the open bucket', () => {
  const w = world();

  w.tracker.start();
  w.advance(90); // one full bucket plus 30 seconds

  const before = w.buckets.length;

  w.tracker.stop();

  assert.strictEqual(w.buckets.length, before + 1, 'the part bucket is not lost');
});

test('an empty bucket is not sent', () => {
  const w = world({ working_hours: { enforce: true, start: '09:00', end: '17:00', days: [1] } });

  w.nowMs = new Date('2026-11-08T03:00:00').getTime(); // Sunday night
  w.tracker.start();
  w.advance(600);
  w.tracker.stop();

  assert.strictEqual(w.buckets.length, 0, 'no rows of zeroes');
});

// --------------------------------------------------------------- privacy

test('no bucket can carry anything that was typed', () => {
  const w = world();

  w.tracker.start();
  w.advance(120);

  for (const bucket of w.buckets) {
    assert.deepStrictEqual(
      Object.keys(bucket).sort(),
      [
        'active_seconds', 'bucket_ended_at', 'bucket_started_at', 'client_uuid',
        'idle_seconds', 'key_events', 'mouse_events', 'status',
      ],
      'the bucket shape is fixed and contains only counts and times',
    );
    assert.strictEqual(bucket.key_events, 0, 'no input hook is installed');
  }
});
