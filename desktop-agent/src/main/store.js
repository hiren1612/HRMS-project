'use strict';

const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const crypto = require('node:crypto');

/**
 * Local state: the device identity, the auth token, and enough of the
 * current session to survive a crash.
 *
 * The token is encrypted with Electron's safeStorage, which on Windows is
 * DPAPI — the ciphertext is bound to the Windows user account, so copying
 * the file to another machine or another user yields nothing. If the OS
 * refuses to provide encryption the token is NOT written in the clear; the
 * employee is asked to sign in again instead.
 *
 * safeStorage is injected rather than imported so this file can be unit
 * tested without Electron.
 */
class Store {
  /**
   * @param {string} directory     Electron's userData path
   * @param {object} [safeStorage] Electron safeStorage, or null
   */
  constructor(directory, safeStorage = null) {
    this.directory = directory;
    this.safeStorage = safeStorage;
    this.file = path.join(directory, 'agent-state.json');
    this.data = this.#read();
  }

  // ----------------------------------------------------------- device identity

  /**
   * A stable id for this installation on this PC.
   *
   * Generated once and kept, so reinstalling re-registers the same device
   * instead of creating a duplicate row — which is what the server's unique
   * constraint on device_uuid expects.
   */
  deviceUuid() {
    if (!this.data.device_uuid) {
      this.data.device_uuid = crypto.randomUUID();
      this.#write();
    }

    return this.data.device_uuid;
  }

  deviceInfo() {
    return {
      uuid: this.deviceUuid(),
      name: os.hostname(),
      platform: `${os.type()} ${os.release()}`,
    };
  }

  // -------------------------------------------------------------------- auth

  /**
   * @returns {boolean} false when the OS could not encrypt, in which case
   *                    nothing was written.
   */
  setToken(token) {
    if (!token) return false;

    if (!this.safeStorage?.isEncryptionAvailable?.()) {
      // Refuse to persist a bearer token as plain text on disk.
      this.data.token = null;
      this.data.token_plaintext_refused = true;
      this.#write();

      return false;
    }

    this.data.token = this.safeStorage.encryptString(token).toString('base64');
    this.data.token_plaintext_refused = false;
    this.#write();

    return true;
  }

  getToken() {
    if (!this.data.token) return null;

    try {
      return this.safeStorage.decryptString(Buffer.from(this.data.token, 'base64'));
    } catch {
      // Key rotated, profile moved, different Windows account.
      return null;
    }
  }

  clearToken() {
    this.data.token = null;
    this.data.user = null;
    this.#write();
  }

  setUser(user) {
    this.data.user = user;
    this.#write();
  }

  getUser() {
    return this.data.user || null;
  }

  // ---------------------------------------------------------------- session

  /**
   * Written on every tick so a crash or a power cut loses seconds, not
   * hours. main.js hands this back to Tracker.start() on next launch.
   */
  saveSession(state) {
    this.data.session = { ...state, savedAt: new Date().toISOString() };
    this.#write();
  }

  /**
   * The saved session, but only if it belongs to today. Yesterday's totals
   * must not be resumed into this morning.
   */
  loadSession() {
    const session = this.data.session;

    if (!session?.savedAt) return null;

    const savedOn = new Date(session.savedAt).toDateString();

    return savedOn === new Date().toDateString() ? session : null;
  }

  clearSession() {
    this.data.session = null;
    this.#write();
  }

  // --------------------------------------------------------------- settings

  cacheSettings(settings) {
    this.data.settings = settings;
    this.#write();
  }

  /**
   * The last settings the server sent, so the agent still behaves sensibly
   * while offline rather than falling back to something permissive.
   */
  cachedSettings() {
    return this.data.settings || null;
  }

  get serverUrl() {
    return this.data.server_url || null;
  }

  set serverUrl(url) {
    this.data.server_url = url;
    this.#write();
  }

  // -------------------------------------------------------------- internals

  #read() {
    try {
      if (fs.existsSync(this.file)) {
        return JSON.parse(fs.readFileSync(this.file, 'utf8'));
      }
    } catch {
      // Corrupt state should cost a re-login, not a broken install.
    }

    return {};
  }

  #write() {
    try {
      fs.mkdirSync(this.directory, { recursive: true });

      const tmp = this.file + '.tmp';

      fs.writeFileSync(tmp, JSON.stringify(this.data, null, 2), 'utf8');
      fs.renameSync(tmp, this.file);
    } catch {
      // Carry on in memory.
    }
  }
}

module.exports = { Store };
