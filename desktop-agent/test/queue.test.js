'use strict';

const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const { Queue } = require('../src/main/queue');

function tempFile() {
  return path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'agent-queue-')), 'outbox.json');
}

test('items survive the process being restarted', () => {
  const file = tempFile();

  const first = new Queue(file);

  first.push('activity', { active_seconds: 60 });
  first.push('activity', { active_seconds: 45 });

  // A new instance reads the same file, as it would after a crash.
  const second = new Queue(file);

  assert.strictEqual(second.length, 2);
  assert.strictEqual(second.peek()[0].payload.active_seconds, 60);
});

test('acknowledged items are removed and the rest stay', () => {
  const queue = new Queue(tempFile());

  queue.push('activity', { n: 1 });
  queue.push('activity', { n: 2 });
  queue.push('activity', { n: 3 });

  const [first, second] = queue.peek();

  queue.ack([first.id, second.id]);

  assert.strictEqual(queue.length, 1);
  assert.strictEqual(queue.peek()[0].payload.n, 3);
});

test('a failed item is kept for another attempt', () => {
  const queue = new Queue(tempFile());

  queue.push('activity', { n: 1 });
  queue.fail([queue.peek()[0].id]);

  assert.strictEqual(queue.length, 1, 'still queued');
  assert.strictEqual(queue.peek()[0].attempts, 1);
});

test('an item that keeps failing is eventually abandoned', () => {
  const queue = new Queue(tempFile());

  queue.push('activity', { poisoned: true });
  queue.push('activity', { fine: true });

  for (let i = 0; i < 10; i++) {
    const stuck = queue.peek().find((item) => item.payload.poisoned);

    if (stuck) queue.fail([stuck.id], { maxAttempts: 10 });
  }

  assert.strictEqual(queue.length, 1, 'one bad payload must not block the queue forever');
  assert.strictEqual(queue.peek()[0].payload.fine, true);
});

test('the oldest are dropped rather than filling the disk', () => {
  const queue = new Queue(tempFile(), { maxItems: 5 });

  for (let i = 0; i < 20; i++) queue.push('activity', { n: i });

  assert.strictEqual(queue.length, 5);
  assert.strictEqual(queue.peek()[0].payload.n, 15, 'kept the most recent');
  assert.strictEqual(queue.dropped, 15);
});

test('a corrupt queue file costs the backlog, not the agent', () => {
  const file = tempFile();

  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, '{ this is not json');

  const queue = new Queue(file);

  assert.strictEqual(queue.length, 0, 'starts empty instead of throwing');

  queue.push('activity', { n: 1 });
  assert.strictEqual(queue.length, 1, 'and still works afterwards');
});

test('every item carries an id so acknowledgements cannot be ambiguous', () => {
  const queue = new Queue(tempFile());

  for (let i = 0; i < 50; i++) queue.push('activity', { n: i });

  const ids = queue.peek(50).map((item) => item.id);

  assert.strictEqual(new Set(ids).size, 50);
});

test('a batch is returned oldest first', () => {
  const queue = new Queue(tempFile());

  queue.push('activity', { n: 1 });
  queue.push('screenshot', { n: 2 });
  queue.push('idle', { n: 3 });

  assert.deepStrictEqual(queue.peek().map((i) => i.kind), ['activity', 'screenshot', 'idle']);
});
