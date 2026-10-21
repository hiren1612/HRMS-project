'use strict';

/**
 * HTTP client for the HRMS tracking API.
 *
 * Deliberately knows nothing about how the server issues tokens. It sends
 * `Authorization: Bearer <token>` and reads a token back from the login
 * response, which is true whether the server side ends up using Laravel
 * Sanctum or a hand-rolled device-token table. That decision can be made
 * without touching this file.
 *
 * Uses Node's built-in fetch (Node 18+, and Electron 33 ships newer), so
 * the agent has no runtime dependencies at all.
 */

const DEFAULT_TIMEOUT_MS = 20000;

class ApiError extends Error {
  constructor(message, { status = 0, body = null, retryable = false } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.body = body;
    this.retryable = retryable;
  }
}

class Api {
  /**
   * @param {object}   options
   * @param {string}   options.baseUrl    e.g. https://hrms.example.com
   * @param {function} [options.getToken]
   * @param {string}   [options.agentVersion]
   */
  constructor({ baseUrl, getToken = () => null, agentVersion = '1.0.0' }) {
    this.baseUrl = String(baseUrl || '').replace(/\/+$/, '');
    this.getToken = getToken;
    this.agentVersion = agentVersion;
  }

  // ------------------------------------------------------------- endpoints

  /**
   * Exchange HRMS credentials for a device token.
   *
   * The password is used once, here, and never written to disk. What is
   * stored is the token the server returns.
   */
  login({ email, password, device }) {
    return this.#request('POST', '/api/agent/login', {
      email,
      password,
      device_uuid: device.uuid,
      device_name: device.name,
      platform: device.platform,
      agent_version: this.agentVersion,
    }, { auth: false });
  }

  logout() {
    return this.#request('POST', '/api/agent/logout');
  }

  /**
   * Who am I, is this device approved, and what are my settings?
   * Called at launch and periodically, so a revoked device or a changed
   * setting takes effect without the employee restarting anything.
   */
  session() {
    return this.#request('GET', '/api/agent/session');
  }

  startSession() {
    return this.#request('POST', '/api/agent/sessions/start', {
      started_at: nowSql(),
    });
  }

  /**
   * Keep-alive. The response carries any pending instruction from the
   * server — a live-screen request, a revocation, a settings change.
   */
  heartbeat({ sessionId, status, activeSeconds, idleSeconds }) {
    return this.#request('POST', '/api/agent/heartbeat', {
      session_id: sessionId,
      status,
      active_seconds: activeSeconds,
      idle_seconds: idleSeconds,
      at: nowSql(),
    });
  }

  endSession({ sessionId, reason }) {
    return this.#request('POST', '/api/agent/sessions/end', {
      session_id: sessionId,
      ended_at: nowSql(),
      reason,
    });
  }

  /**
   * Upload a batch of activity buckets and idle periods together.
   * Each bucket carries its own client_uuid, so re-sending after a dropped
   * connection cannot double-count.
   */
  syncActivity({ sessionId, buckets, idlePeriods }) {
    return this.#request('POST', '/api/agent/activity', {
      session_id: sessionId,
      buckets,
      idle_periods: idlePeriods,
    });
  }

  /**
   * @param {object} meta
   * @param {Buffer} jpeg
   */
  uploadScreenshot(meta, jpeg) {
    const form = new FormData();

    form.append('session_id', String(meta.sessionId ?? ''));
    form.append('captured_at', meta.capturedAt);
    form.append('status_at_capture', meta.status);
    form.append('width', String(meta.width));
    form.append('height', String(meta.height));
    form.append('screenshot', new Blob([jpeg], { type: 'image/jpeg' }), 'screen.jpg');

    return this.#request('POST', '/api/agent/screenshots', form);
  }

  /**
   * Tell the server the on-screen indicator is now showing. Until this
   * lands the server must refuse to serve any frame.
   */
  confirmLiveNotification(liveSessionId) {
    return this.#request('POST', `/api/agent/live/${liveSessionId}/notified`);
  }

  pushLiveFrame(liveSessionId, jpeg) {
    const form = new FormData();

    form.append('frame', new Blob([jpeg], { type: 'image/jpeg' }), 'frame.jpg');

    return this.#request('POST', `/api/agent/live/${liveSessionId}/frame`, form);
  }

  endLive(liveSessionId, reason) {
    return this.#request('POST', `/api/agent/live/${liveSessionId}/end`, { reason });
  }

  // -------------------------------------------------------------- internals

  async #request(method, pathname, body = null, { auth = true } = {}) {
    const url = this.baseUrl + pathname;
    const headers = {
      Accept: 'application/json',
      'X-Agent-Version': this.agentVersion,
    };

    if (auth) {
      const token = this.getToken();

      if (!token) {
        throw new ApiError('Not signed in.', { status: 401 });
      }

      headers.Authorization = 'Bearer ' + token;
    }

    let payload;

    if (body instanceof FormData) {
      payload = body; // fetch sets the multipart boundary itself
    } else if (body !== null) {
      headers['Content-Type'] = 'application/json';
      payload = JSON.stringify(body);
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), DEFAULT_TIMEOUT_MS);

    let response;

    try {
      response = await fetch(url, {
        method,
        headers,
        body: payload,
        signal: controller.signal,
        redirect: 'error',
      });
    } catch (error) {
      // No network, DNS failure, timeout, server down. All worth retrying,
      // which is why the caller leaves the item on the queue.
      throw new ApiError(`Cannot reach the HRMS (${error.message}).`, { retryable: true });
    } finally {
      clearTimeout(timer);
    }

    const text = await response.text();
    let parsed = null;

    try {
      parsed = text ? JSON.parse(text) : null;
    } catch {
      parsed = { raw: text };
    }

    if (response.ok) return parsed;

    // 401/403 mean the token is dead or the device was revoked; retrying
    // will not help and must not be done in a loop. 5xx and 429 will pass.
    const retryable = response.status >= 500 || response.status === 429;

    throw new ApiError(
      parsed?.message || `Request failed with status ${response.status}.`,
      { status: response.status, body: parsed, retryable },
    );
  }
}

function nowSql() {
  const d = new Date();
  const p = (n) => String(n).padStart(2, '0');

  return (
    d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
    ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds())
  );
}

module.exports = { Api, ApiError, nowSql };
