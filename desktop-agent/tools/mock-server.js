'use strict';

/**
 * A stand-in for the HRMS tracking API, for developing the agent before
 * Phase 4 exists.
 *
 * This is a development tool and nothing more. It is excluded from the
 * installer by electron-builder, it accepts any password, and it keeps
 * everything in memory. It must never be pointed at by a real install.
 *
 *   npm run mock-server
 *   then sign in at http://127.0.0.1:8787 with any email and password
 *
 * It also serves as the written specification for Phase 4: whatever the
 * Laravel side ends up doing, it has to match the shapes below.
 */

const http = require('node:http');
const crypto = require('node:crypto');

const PORT = Number(process.env.MOCK_PORT || 8787);

const state = {
  token: null,
  sessionId: null,
  buckets: [],
  idlePeriods: [],
  screenshots: [],
  liveRequest: null,
  liveFrames: 0,
  // Flip these while the agent is running to exercise its behaviour.
  deviceApproved: true,
  trackingEnabled: true,
};

const settings = {
  tracking_enabled: true,
  idle_threshold_seconds: 300,
  activity_bucket_seconds: 60,
  tick_seconds: 5,
  screenshots_enabled: true,
  screenshot_interval_seconds: 60,
  screenshot_max_dimension: 1600,
  screenshot_jpeg_quality: 60,
  live_enabled: true,
  excluded_windows: ['KeePass', 'Bitwarden', '1Password'],
  allow_pause: true,
  max_pause_minutes_per_day: 60,
  working_hours: { enforce: false, start: '09:00', end: '19:00', days: [1, 2, 3, 4, 5] },
};

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  const route = `${req.method} ${url.pathname}`;

  const send = (status, body) => {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(body));
    console.log(`${status} ${route}`);
  };

  const authorised = () => {
    const header = req.headers.authorization || '';

    return state.token && header === 'Bearer ' + state.token;
  };

  // Everything except login needs the bearer token. The /dev/ helpers are
  // exempt so they can be used to inspect and drive the agent before anyone
  // has signed in.
  const exempt = route === 'POST /api/agent/login' || url.pathname.startsWith('/dev/');

  if (!exempt && !authorised()) {
    return send(401, { message: 'Unauthenticated.' });
  }

  switch (route) {
    case 'POST /api/agent/login': {
      const body = await readJson(req);

      state.token = crypto.randomUUID();
      console.log(`  login: ${body.email} on ${body.device_name} (${body.device_uuid})`);

      return send(200, {
        token: state.token,
        user: {
          id: 1,
          name: 'Test Employee',
          email: body.email,
          employee_code: 'EMP-0001',
          designation: 'Software Developer',
        },
        device: { status: 'approved', requires_approval: true },
      });
    }

    case 'GET /api/agent/session':
      return send(200, {
        user: { id: 1, name: 'Test Employee', employee_code: 'EMP-0001', designation: 'Software Developer' },
        device: { status: state.deviceApproved ? 'approved' : 'pending', requires_approval: true },
        settings: { ...settings, tracking_enabled: state.trackingEnabled },
      });

    case 'POST /api/agent/sessions/start':
      state.sessionId = Math.floor(Math.random() * 100000);
      return send(201, { session_id: state.sessionId });

    case 'POST /api/agent/sessions/end':
      return send(200, { ok: true });

    case 'POST /api/agent/heartbeat': {
      const body = await readJson(req);

      console.log(`  heartbeat: ${body.status}, active ${body.active_seconds}s, idle ${body.idle_seconds}s`);

      // Hand back a pending live request, if one was asked for.
      const instruction = state.liveRequest;

      state.liveRequest = null;

      return send(200, { ok: true, live: instruction });
    }

    case 'POST /api/agent/activity': {
      const body = await readJson(req);

      state.buckets.push(...(body.buckets || []));
      state.idlePeriods.push(...(body.idle_periods || []));

      console.log(`  activity: +${(body.buckets || []).length} buckets, ` +
        `+${(body.idle_periods || []).length} idle (total ${state.buckets.length})`);

      return send(200, { accepted: (body.buckets || []).length + (body.idle_periods || []).length });
    }

    case 'POST /api/agent/screenshots': {
      const size = await drain(req);

      state.screenshots.push({ at: new Date().toISOString(), bytes: size });
      console.log(`  screenshot: ${Math.round(size / 1024)} KB (total ${state.screenshots.length})`);

      return send(201, { id: state.screenshots.length });
    }

    case 'POST /api/agent/logout':
      state.token = null;
      return send(200, { ok: true });

    // ---- helpers for driving the agent by hand during development ----
    case 'GET /dev/request-live':
      state.liveRequest = { live_session_id: 99, initiated_by_name: 'Test Admin' };
      return send(200, { ok: true, note: 'the agent will pick this up on its next heartbeat' });

    case 'GET /dev/stats':
      return send(200, {
        buckets: state.buckets.length,
        idlePeriods: state.idlePeriods.length,
        screenshots: state.screenshots.length,
        liveFrames: state.liveFrames,
        activeSeconds: state.buckets.reduce((n, b) => n + (b.active_seconds || 0), 0),
        idleSeconds: state.buckets.reduce((n, b) => n + (b.idle_seconds || 0), 0),
      });

    case 'GET /dev/revoke-device':
      state.deviceApproved = false;
      return send(200, { ok: true, note: 'the agent should stop tracking within five minutes' });

    default:
      break;
  }

  // Live endpoints carry an id in the path.
  if (/^POST \/api\/agent\/live\/\d+\/notified$/.test(route)) {
    console.log('  live: the agent confirmed the indicator is showing');
    return send(200, { ok: true });
  }

  if (/^POST \/api\/agent\/live\/\d+\/frame$/.test(route)) {
    const size = await drain(req);

    state.liveFrames += 1;

    if (state.liveFrames % 10 === 0) {
      console.log(`  live: ${state.liveFrames} frames, latest ${Math.round(size / 1024)} KB`);
    }

    return send(200, { ok: true });
  }

  if (/^POST \/api\/agent\/live\/\d+\/end$/.test(route)) {
    console.log('  live: ended');
    return send(200, { ok: true });
  }

  return send(404, { message: 'No such endpoint: ' + route });
});

function readJson(req) {
  return new Promise((resolve) => {
    let body = '';

    req.on('data', (chunk) => { body += chunk; });
    req.on('end', () => {
      try { resolve(JSON.parse(body || '{}')); } catch { resolve({}); }
    });
  });
}

function drain(req) {
  return new Promise((resolve) => {
    let bytes = 0;

    req.on('data', (chunk) => { bytes += chunk.length; });
    req.on('end', () => resolve(bytes));
  });
}

server.listen(PORT, '127.0.0.1', () => {
  console.log(`Mock HRMS tracking API on http://127.0.0.1:${PORT}`);
  console.log('Sign in from the agent with any email and password.');
  console.log('');
  console.log('  http://127.0.0.1:' + PORT + '/dev/stats           what has arrived');
  console.log('  http://127.0.0.1:' + PORT + '/dev/request-live    ask for a live view');
  console.log('  http://127.0.0.1:' + PORT + '/dev/revoke-device   test revocation');
});
