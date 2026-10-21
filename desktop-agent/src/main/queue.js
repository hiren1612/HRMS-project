'use strict';

const fs = require('node:fs');
const path = require('node:path');

/**
 * A durable outbox.
 *
 * Everything bound for the server goes in here first and is only removed
 * once the server has acknowledged it. The file is rewritten after every
 * change, so pulling the power out loses at most the item being written —
 * and because each item carries an idempotency key the server rejects a
 * duplicate anyway, so a replay after recovery is harmless.
 *
 * Writes go to a temporary file and are then renamed over the real one.
 * Rename is atomic, so a crash mid-write cannot leave a half-written queue
 * that fails to parse on next launch.
 */
class Queue {
  /**
   * @param {string} filePath  where to persist
   * @param {object} [options]
   * @param {number} [options.maxItems]  oldest are dropped beyond this
   */
  constructor(filePath, { maxItems = 5000 } = {}) {
    this.filePath = filePath;
    this.maxItems = maxItems;
    this.items = [];
    this.dropped = 0;

    this.#load();
  }

  get length() {
    return this.items.length;
  }

  /**
   * @param {string} kind     'activity' | 'idle' | 'screenshot' | 'event'
   * @param {object} payload
   */
  push(kind, payload) {
    this.items.push({
      id: require('node:crypto').randomUUID(),
      kind,
      payload,
      attempts: 0,
      queuedAt: new Date().toISOString(),
    });

    // A machine offline for a very long time must not fill its own disk.
    // The oldest go first: recent activity is the more useful record.
    while (this.items.length > this.maxItems) {
      this.items.shift();
      this.dropped += 1;
    }

    this.#persist();
  }

  /**
   * The next batch to try, oldest first.
   */
  peek(limit = 25) {
    return this.items.slice(0, limit);
  }

  /**
   * Remove items the server accepted.
   */
  ack(ids) {
    const done = new Set(ids);

    this.items = this.items.filter((item) => !done.has(item.id));
    this.#persist();
  }

  /**
   * Record a failed attempt. An item that keeps failing is eventually
   * abandoned, so one poisoned payload cannot block the queue behind it
   * forever.
   */
  fail(ids, { maxAttempts = 10 } = {}) {
    const failed = new Set(ids);

    this.items = this.items.filter((item) => {
      if (!failed.has(item.id)) return true;

      item.attempts += 1;

      if (item.attempts >= maxAttempts) {
        this.dropped += 1;
        return false;
      }

      return true;
    });

    this.#persist();
  }

  clear() {
    this.items = [];
    this.#persist();
  }

  // ------------------------------------------------------------- internals

  #load() {
    try {
      if (!fs.existsSync(this.filePath)) return;

      const parsed = JSON.parse(fs.readFileSync(this.filePath, 'utf8'));

      if (Array.isArray(parsed.items)) {
        this.items = parsed.items;
        this.dropped = parsed.dropped || 0;
      }
    } catch {
      // A corrupt queue is not worth crashing over, and not worth keeping.
      // Losing unsent activity is bad; refusing to start is worse.
      this.items = [];
    }
  }

  #persist() {
    try {
      fs.mkdirSync(path.dirname(this.filePath), { recursive: true });

      const tmp = this.filePath + '.tmp';

      fs.writeFileSync(tmp, JSON.stringify({ items: this.items, dropped: this.dropped }), 'utf8');
      fs.renameSync(tmp, this.filePath);
    } catch {
      // Keep running in memory rather than taking the agent down.
    }
  }
}

module.exports = { Queue };
