'use strict';

const {
  app, BrowserWindow, Tray, Menu, nativeImage, ipcMain,
  powerMonitor, desktopCapturer, screen, safeStorage, shell, dialog,
} = require('electron');
const path = require('node:path');

const { Tracker, STATUS } = require('./tracker');
const { Queue } = require('./queue');
const { Api, ApiError, nowSql } = require('./api');
const { Store } = require('./store');
const { Capture } = require('./capture');
const { createLogger } = require('./logger');
const { setAutoStart, isAutoStartEnabled } = require('./autostart');

const DEFAULT_SETTINGS = {
  tracking_enabled: false,
  idle_threshold_seconds: 300,
  activity_bucket_seconds: 60,
  tick_seconds: 5,
  screenshots_enabled: false,
  screenshot_interval_seconds: 300,
  screenshot_max_dimension: 1600,
  screenshot_jpeg_quality: 60,
  live_enabled: false,
  excluded_windows: [],
  allow_pause: true,
  max_pause_minutes_per_day: 60,
  working_hours: { enforce: true, start: '09:00', end: '19:00', days: [1, 2, 3, 4, 5] },
};

// Only one copy may run. A second launch (from the Start Menu, say) should
// reveal the window that is already there, not start tracking twice.
//
// app.exit rather than app.quit: quit schedules a shutdown but lets the rest
// of this module run, so the second copy would still build a Store and a
// Queue over the running copy's files and could lose queued activity. The
// primary instance has already been handed the 'second-instance' event by
// the time requestSingleInstanceLock returns, so leaving immediately costs
// nothing.
if (!app.requestSingleInstanceLock()) {
  app.exit(0);
}

class Agent {
  constructor() {
    this.logger = createLogger(path.join(app.getPath('userData'), 'logs'));
    this.store = new Store(app.getPath('userData'), safeStorage);
    this.queue = new Queue(path.join(app.getPath('userData'), 'outbox.json'));
    this.capture = new Capture({ desktopCapturer, screen, nativeImage, logger: this.logger });

    this.settings = { ...DEFAULT_SETTINGS, ...(this.store.cachedSettings() || {}) };
    this.api = new Api({
      baseUrl: this.store.serverUrl || process.env.HRMS_URL || '',
      getToken: () => this.store.getToken(),
      agentVersion: app.getVersion(),
    });

    this.tracker = null;
    this.sessionId = null;
    this.liveSession = null;

    this.mainWindow = null;
    this.loginWindow = null;
    this.indicatorWindow = null;
    this.tray = null;

    this.timers = {};
    this.lastScreenshotAt = 0;
  }

  // ------------------------------------------------------------- lifecycle

  async boot() {
    this.#createTray();

    if (!this.store.serverUrl || !this.store.getToken()) {
      this.showLogin();
      return;
    }

    await this.refreshSession({ startTracking: true });
    this.#startTimers();
  }

  /**
   * Ask the server who we are, whether this device is still approved, and
   * what the current settings are.
   *
   * Called at launch and every few minutes, so revoking a device or
   * changing a setting takes effect without anyone restarting anything.
   */
  async refreshSession({ startTracking = false } = {}) {
    try {
      const data = await this.api.session();

      this.store.setUser(data.user);
      this.settings = { ...DEFAULT_SETTINGS, ...(data.settings || {}) };
      this.store.cacheSettings(this.settings);

      if (data.device?.status !== 'approved' && data.device?.requires_approval !== false) {
        this.#stopTracking('device_not_approved');
        this.#pushState({ blocked: 'This device is waiting for an administrator to approve it.' });
        this.#updateTray();
        return;
      }

      if (!this.settings.tracking_enabled) {
        this.#stopTracking('tracking_disabled');
        this.#pushState({ blocked: 'Tracking is switched off by your administrator.' });
        this.#updateTray();
        return;
      }

      if (startTracking && !this.tracker?.running) {
        await this.#startTracking();
      }

      this.#pushState({ blocked: null });
      this.#updateTray();
    } catch (error) {
      if (error instanceof ApiError && (error.status === 401 || error.status === 403)) {
        this.logger.warn('session: rejected by the server, signing out');
        this.#stopTracking('unauthorised');
        this.store.clearToken();
        this.showLogin();
        return;
      }

      // Offline. Carry on with the cached settings rather than stopping:
      // the queue will catch up when the network returns.
      this.logger.warn('session: ' + error.message);
      this.#pushState({ offline: true });
    }
  }

  async #startTracking() {
    let resume = this.store.loadSession();

    try {
      const started = await this.api.startSession();

      this.sessionId = started.session_id;
    } catch (error) {
      this.logger.warn('could not open a server session: ' + error.message);
      // Track anyway and attach the buckets once the session exists; the
      // alternative is losing the morning because the Wi-Fi was slow.
      this.sessionId = resume?.sessionId ?? null;
    }

    if (resume?.sessionId && !this.sessionId) this.sessionId = resume.sessionId;

    this.tracker = new Tracker({
      getIdleSeconds: () => powerMonitor.getSystemIdleTime(),
      settings: this.settings,
      onBucket: (bucket) => this.queue.push('activity', bucket),
      onIdleStart: (period) => this.logger.info('idle from ' + period.startedAt),
      onIdleEnd: (period) => this.queue.push('idle', period),
      onStatusChange: (status) => {
        this.#updateTray();
        this.#pushState();
        this.logger.info('status: ' + status);
      },
    });

    this.tracker.start(resume);
    this.logger.info('tracking started, session ' + this.sessionId);
  }

  #stopTracking(reason) {
    if (!this.tracker?.running) return;

    this.tracker.stop();
    this.#flushQueueSoon();

    if (this.sessionId) {
      this.api.endSession({ sessionId: this.sessionId, reason })
        .catch((error) => this.logger.warn('endSession: ' + error.message));
    }

    this.store.clearSession();
    this.sessionId = null;
    this.logger.info('tracking stopped: ' + reason);
  }

  // ---------------------------------------------------------------- timers

  #startTimers() {
    clearInterval(this.timers.tick);
    clearInterval(this.timers.sync);
    clearInterval(this.timers.session);

    this.timers.tick = setInterval(() => this.#onTick(), (this.settings.tick_seconds || 5) * 1000);
    this.timers.sync = setInterval(() => this.#flushQueue(), 30 * 1000);
    this.timers.session = setInterval(() => this.refreshSession(), 5 * 60 * 1000);
  }

  async #onTick() {
    if (!this.tracker?.running) return;

    this.tracker.tick();
    this.store.saveSession({ ...this.tracker.snapshot(), sessionId: this.sessionId });

    this.#pushState();
    this.#updateTray();

    await this.#maybeScreenshot();
  }

  async #maybeScreenshot() {
    if (!this.settings.screenshots_enabled) return;
    if (!this.tracker.isCaptureEligible()) return;

    const due = Date.now() - this.lastScreenshotAt >= (this.settings.screenshot_interval_seconds || 300) * 1000;

    if (!due) return;

    this.lastScreenshotAt = Date.now();

    try {
      if (await this.capture.isExcluded(this.settings)) {
        this.logger.info('screenshot skipped: excluded window in the foreground');
        return;
      }

      const shot = await this.capture.captureAll(this.settings);

      if (!shot) return;

      if (shot.displays > 1) {
        this.logger.info(`screenshot: ${shot.displays} displays stitched into one frame`);
      }

      // Queued as base64 so it survives a restart like everything else.
      this.queue.push('screenshot', {
        capturedAt: nowSql(),
        status: this.tracker.status,
        width: shot.width,
        height: shot.height,
        jpegBase64: shot.buffer.toString('base64'),
      });
    } catch (error) {
      this.logger.warn('screenshot failed: ' + error.message);
    }
  }

  // ----------------------------------------------------------------- queue

  #flushQueueSoon() {
    setTimeout(() => this.#flushQueue(), 500);
  }

  /**
   * Drain the outbox. Items only leave the queue once the server has
   * acknowledged them, so nothing is lost to a dropped connection.
   */
  async #flushQueue() {
    if (this.flushing || this.queue.length === 0) return;
    if (!this.store.getToken()) return;

    this.flushing = true;

    try {
      const batch = this.queue.peek(50);
      const buckets = batch.filter((i) => i.kind === 'activity');
      const idles = batch.filter((i) => i.kind === 'idle');
      const shots = batch.filter((i) => i.kind === 'screenshot');

      if (buckets.length || idles.length) {
        try {
          await this.api.syncActivity({
            sessionId: this.sessionId,
            buckets: buckets.map((i) => i.payload),
            idlePeriods: idles.map((i) => i.payload),
          });

          this.queue.ack([...buckets, ...idles].map((i) => i.id));
        } catch (error) {
          this.#handleSyncError(error, [...buckets, ...idles].map((i) => i.id));
        }
      }

      // One at a time: an image is far larger than a bucket, and a failure
      // should cost one upload rather than the whole batch.
      for (const shot of shots.slice(0, 5)) {
        try {
          await this.api.uploadScreenshot(
            { ...shot.payload, sessionId: this.sessionId },
            Buffer.from(shot.payload.jpegBase64, 'base64'),
          );

          this.queue.ack([shot.id]);
        } catch (error) {
          this.#handleSyncError(error, [shot.id]);
          break;
        }
      }
    } finally {
      this.flushing = false;
      this.#pushState();
    }
  }

  #handleSyncError(error, ids) {
    if (error instanceof ApiError && !error.retryable && error.status >= 400 && error.status < 500) {
      // The server rejected it outright — a duplicate it already has, or a
      // payload it will never accept. Retrying forever would wedge the
      // queue, so drop it and say so in the log.
      this.logger.warn(`sync rejected (${error.status}): ${error.message}`);
      this.queue.ack(ids);
      return;
    }

    this.logger.warn('sync will retry: ' + error.message);
    this.queue.fail(ids);
  }

  // ----------------------------------------------------------- live screen

  /**
   * The server asks for a live view through the heartbeat response. The
   * indicator goes up first and the server is told it is showing; only
   * then do frames start. No indicator, no frames.
   */
  async startLive(request) {
    if (!this.settings.live_enabled || this.liveSession) return;

    this.liveSession = { id: request.live_session_id, startedAt: Date.now() };

    this.#showIndicator({
      live: true,
      watcher: request.initiated_by_name || 'An administrator',
    });

    try {
      await this.api.confirmLiveNotification(this.liveSession.id);
    } catch (error) {
      this.logger.warn('live: could not confirm the notification, refusing to stream');
      this.stopLive('notify_failed');
      return;
    }

    this.timers.live = setInterval(() => this.#pushLiveFrame(), 500);
    this.logger.info('live monitoring started by ' + (request.initiated_by_name || 'admin'));
  }

  async #pushLiveFrame() {
    if (!this.liveSession) return;

    try {
      const frame = await this.capture.captureLiveFrame();

      if (frame) await this.api.pushLiveFrame(this.liveSession.id, frame.buffer);
    } catch (error) {
      this.logger.warn('live frame failed: ' + error.message);
      this.stopLive('disconnected');
    }
  }

  stopLive(reason = 'stopped_by_admin') {
    if (!this.liveSession) return;

    clearInterval(this.timers.live);

    const id = this.liveSession.id;

    this.liveSession = null;
    this.#hideIndicator();

    this.api.endLive(id, reason).catch(() => {});
    this.logger.info('live monitoring ended: ' + reason);
  }

  // --------------------------------------------------------------- windows

  showLogin() {
    if (this.loginWindow) return this.loginWindow.focus();

    this.loginWindow = new BrowserWindow({
      width: 420,
      height: 580,
      resizable: false,
      title: 'Sign in — HRMS Tracking',
      autoHideMenuBar: true,
      webPreferences: {
        preload: path.join(__dirname, '..', 'preload', 'preload.js'),
        contextIsolation: true,
        nodeIntegration: false,
      },
    });

    this.loginWindow.loadFile(path.join(__dirname, '..', 'renderer', 'login.html'));
    this.loginWindow.on('closed', () => { this.loginWindow = null; });
  }

  showMain() {
    if (this.mainWindow) {
      this.mainWindow.show();
      return this.mainWindow.focus();
    }

    this.mainWindow = new BrowserWindow({
      width: 460,
      height: 640,
      resizable: false,
      title: 'HRMS Tracking',
      autoHideMenuBar: true,
      webPreferences: {
        preload: path.join(__dirname, '..', 'preload', 'preload.js'),
        contextIsolation: true,
        nodeIntegration: false,
      },
    });

    this.mainWindow.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));
    this.mainWindow.on('ready-to-show', () => this.#pushState());

    // Closing hides to the tray; Quit is an explicit choice in the menu.
    this.mainWindow.on('close', (event) => {
      if (!app.isQuitting) {
        event.preventDefault();
        this.mainWindow.hide();
      }
    });

    this.mainWindow.on('closed', () => { this.mainWindow = null; });
  }

  /**
   * The always-visible proof that monitoring is on. Small, frameless,
   * always on top, and present on every workspace. There is deliberately
   * no way for the employee to dismiss it while monitoring is active —
   * an indicator that can be hidden is not an indicator.
   */
  #showIndicator({ live = false, watcher = null } = {}) {
    if (!this.indicatorWindow) {
      const { width } = screen.getPrimaryDisplay().workAreaSize;

      this.indicatorWindow = new BrowserWindow({
        width: 290,
        height: 54,
        x: width - 310,
        y: 16,
        frame: false,
        resizable: false,
        movable: false,
        skipTaskbar: true,
        alwaysOnTop: true,
        focusable: false,
        transparent: true,
        webPreferences: {
          preload: path.join(__dirname, '..', 'preload', 'preload.js'),
          contextIsolation: true,
          nodeIntegration: false,
        },
      });

      this.indicatorWindow.setAlwaysOnTop(true, 'screen-saver');
      this.indicatorWindow.setVisibleOnAllWorkspaces(true);
      this.indicatorWindow.loadFile(path.join(__dirname, '..', 'renderer', 'indicator.html'));
      this.indicatorWindow.on('closed', () => { this.indicatorWindow = null; });
    }

    const send = () => this.indicatorWindow?.webContents.send('indicator:state', { live, watcher });

    if (this.indicatorWindow.webContents.isLoading()) {
      this.indicatorWindow.webContents.once('did-finish-load', send);
    } else {
      send();
    }

    this.indicatorWindow.showInactive();
  }

  #hideIndicator() {
    if (this.settings.screenshots_enabled && this.tracker?.running) {
      // Screenshots are still being taken, so the indicator stays up; only
      // the live-viewing part of the message goes away.
      this.#showIndicator({ live: false });
      return;
    }

    this.indicatorWindow?.hide();
  }

  #syncIndicator() {
    const monitoring = this.tracker?.running && this.settings.screenshots_enabled;

    if (monitoring || this.liveSession) {
      this.#showIndicator({ live: !!this.liveSession });
    } else {
      this.indicatorWindow?.hide();
    }
  }

  // ------------------------------------------------------------------ tray

  #createTray() {
    this.tray = new Tray(trayIcon('idle'));
    this.tray.setToolTip('HRMS Tracking');
    this.#updateTray();
    this.tray.on('double-click', () => this.showMain());
  }

  #updateTray() {
    if (!this.tray) return;

    const snap = this.tracker?.snapshot() || { status: STATUS.OFF, activeSeconds: 0, idleSeconds: 0 };
    const user = this.store.getUser();

    const label = {
      [STATUS.ACTIVE]: 'Active',
      [STATUS.IDLE]: 'Idle',
      [STATUS.PAUSED]: 'Paused',
      [STATUS.OFF]: 'Not tracking',
    }[snap.status] || 'Not tracking';

    this.tray.setImage(trayIcon(snap.status));
    this.tray.setToolTip(`HRMS Tracking — ${label} — ${hhmm(snap.activeSeconds)} active`);

    const menu = Menu.buildFromTemplate([
      { label: user ? `${user.name}` : 'Not signed in', enabled: false },
      { label: `Status: ${label}`, enabled: false },
      { label: `Active today: ${hhmm(snap.activeSeconds)}`, enabled: false },
      { label: `Idle today: ${hhmm(snap.idleSeconds)}`, enabled: false },
      { type: 'separator' },
      {
        label: this.settings.screenshots_enabled ? 'Screenshots: on' : 'Screenshots: off',
        enabled: false,
      },
      {
        label: this.liveSession ? 'Live screen: BEING VIEWED' : 'Live screen: not active',
        enabled: false,
      },
      { type: 'separator' },
      {
        label: snap.paused ? 'Resume tracking' : 'Pause tracking',
        enabled: !!this.tracker?.running && !!this.settings.allow_pause,
        click: () => {
          if (snap.paused) this.tracker.resume();
          else this.tracker.pause();
          this.#updateTray();
          this.#pushState();
        },
      },
      { label: 'Open HRMS Tracking', click: () => this.showMain() },
      {
        label: `Queued items: ${this.queue.length}`,
        enabled: this.queue.length > 0,
        click: () => this.#flushQueue(),
      },
      { type: 'separator' },
      { label: 'Sign out', click: () => this.signOut() },
      { label: 'Quit', click: () => this.quit() },
    ]);

    this.tray.setContextMenu(menu);
    this.#syncIndicator();
  }

  // ------------------------------------------------------------------- IPC

  /**
   * Public entry point for the renderer asking "what is happening now?".
   */
  requestState() {
    this.#pushState();

    return true;
  }

  #pushState(extra = {}) {
    const snap = this.tracker?.snapshot() || {
      running: false, paused: false, status: STATUS.OFF,
      activeSeconds: 0, idleSeconds: 0, trackedSeconds: 0,
    };

    const state = {
      ...snap,
      user: this.store.getUser(),
      serverUrl: this.store.serverUrl,
      screenshotsEnabled: !!this.settings.screenshots_enabled,
      liveActive: !!this.liveSession,
      allowPause: !!this.settings.allow_pause,
      queued: this.queue.length,
      activeLabel: hhmm(snap.activeSeconds),
      idleLabel: hhmm(snap.idleSeconds),
      trackedLabel: hhmm(snap.trackedSeconds),
      autoStart: isAutoStartEnabled(app),
      ...extra,
    };

    this.mainWindow?.webContents.send('agent:state', state);
  }

  async signIn({ serverUrl, email, password }) {
    this.store.serverUrl = String(serverUrl || '').replace(/\/+$/, '');
    this.api.baseUrl = this.store.serverUrl;

    const result = await this.api.login({
      email,
      password,
      device: this.store.deviceInfo(),
    });

    const stored = this.store.setToken(result.token);

    if (!stored) {
      throw new Error(
        'Windows would not provide secure storage, so the sign-in could not be saved. ' +
        'Please contact IT rather than continuing.',
      );
    }

    this.store.setUser(result.user);

    this.loginWindow?.close();
    this.showMain();

    await this.refreshSession({ startTracking: true });
    this.#startTimers();

    return result;
  }

  signOut() {
    this.#stopTracking('logout');
    this.api.logout().catch(() => {});
    this.store.clearToken();
    this.stopLive('stopped_by_employee');
    this.mainWindow?.hide();
    this.#updateTray();
    this.showLogin();
  }

  quit() {
    app.isQuitting = true;
    this.#stopTracking('shutdown');
    this.stopLive('stopped_by_employee');

    // Give the last flush a moment before the process goes away.
    setTimeout(() => app.quit(), 1200);
  }
}

// --------------------------------------------------------------- helpers

function hhmm(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);

  return `${h}h ${String(m).padStart(2, '0')}m`;
}

/**
 * A plain coloured dot, drawn as a data URI so the build needs no icon
 * assets for the tray and cannot fail for want of a missing .ico.
 */
function trayIcon(status) {
  const colour = {
    [STATUS.ACTIVE]: '#16a34a',
    [STATUS.IDLE]: '#d97706',
    [STATUS.PAUSED]: '#64748b',
    [STATUS.OFF]: '#94a3b8',
  }[status] || '#94a3b8';

  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16"><circle cx="8" cy="8" r="6" fill="${colour}"/></svg>`;

  return nativeImage.createFromDataURL('data:image/svg+xml;base64,' + Buffer.from(svg).toString('base64'));
}

// ------------------------------------------------------------------ boot

const agent = new Agent();

app.whenReady().then(async () => {
  // Start with Windows, unless an administrator has turned that off.
  setAutoStart(app, true);

  ipcMain.handle('agent:sign-in', (_event, payload) => agent.signIn(payload));
  ipcMain.handle('agent:sign-out', () => agent.signOut());
  ipcMain.handle('agent:pause', () => agent.tracker?.pause() ?? false);
  ipcMain.handle('agent:resume', () => agent.tracker?.resume() ?? false);
  ipcMain.handle('agent:state', () => agent.requestState());
  ipcMain.handle('agent:sync-now', () => agent.refreshSession());
  ipcMain.handle('agent:open-hrms', () => {
    if (agent.store.serverUrl) shell.openExternal(agent.store.serverUrl);
  });
  ipcMain.handle('agent:set-autostart', (_event, enabled) => setAutoStart(app, enabled));

  await agent.boot();
});

app.on('second-instance', () => {
  if (agent.store.getToken()) agent.showMain();
  else agent.showLogin();
});

// Tracking lives in the tray, so closing the last window must not end the
// day. Registering this handler and NOT calling app.quit() is what keeps the
// process alive on Windows.
app.on('window-all-closed', () => {});

app.on('before-quit', () => { app.isQuitting = true; });

// Windows shutting down, or the user logging off.
powerMonitor.on('shutdown', () => agent.quit());
powerMonitor.on('lock-screen', () => agent.logger.info('screen locked'));
powerMonitor.on('suspend', () => agent.logger.info('system suspended'));
powerMonitor.on('resume', () => {
  agent.logger.info('system resumed');
  agent.refreshSession();
});

process.on('uncaughtException', (error) => {
  agent.logger?.error('uncaught: ' + (error?.stack || error));
});

module.exports = { Agent, hhmm };
