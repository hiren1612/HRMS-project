'use strict';

const fs = require('node:fs');
const path = require('node:path');

/**
 * A small file logger.
 *
 * What it must never contain: anything the employee typed, any window
 * contents, any credentials. It records what the agent did, not what the
 * person did. One file per day, and anything older than two weeks is
 * removed on startup so the agent cannot slowly fill a disk.
 */
function createLogger(directory) {
  try {
    fs.mkdirSync(directory, { recursive: true });
    prune(directory, 14);
  } catch {
    return console;
  }

  const write = (level, parts) => {
    const line = `[${new Date().toISOString()}] ${level} ${parts.map(stringify).join(' ')}\n`;

    try {
      fs.appendFileSync(path.join(directory, `agent-${today()}.log`), line, 'utf8');
    } catch {
      // Never let logging be the thing that breaks tracking.
    }

    if (process.env.AGENT_DEV) process.stdout.write(line);
  };

  return {
    info: (...parts) => write('INFO ', parts),
    warn: (...parts) => write('WARN ', parts),
    error: (...parts) => write('ERROR', parts),
    directory,
  };
}

function stringify(value) {
  if (value instanceof Error) return value.stack || value.message;
  if (typeof value === 'object') {
    try { return JSON.stringify(value); } catch { return '[object]'; }
  }

  return String(value);
}

function today() {
  return new Date().toISOString().slice(0, 10);
}

function prune(directory, keepDays) {
  const cutoff = Date.now() - keepDays * 86400000;

  for (const name of fs.readdirSync(directory)) {
    if (!name.startsWith('agent-') || !name.endsWith('.log')) continue;

    const file = path.join(directory, name);

    try {
      if (fs.statSync(file).mtimeMs < cutoff) fs.unlinkSync(file);
    } catch {
      // Leave it.
    }
  }
}

module.exports = { createLogger };
