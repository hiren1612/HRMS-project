# HRMS Tracking Agent

Windows desktop agent for the HRMS Employee Productivity module. Records
active and idle time, takes periodic screenshots during working hours, and
supports admin live screen viewing.

**It does not record keystrokes.** No input hook is installed. Idle is
detected by asking Windows how long it has been since any input, so nothing
typed is ever visible to this process. See *Privacy* below.

This is a self-contained Electron project. It has **no dependency on the
Laravel application** beyond the HTTP API, and nothing in it can affect the
HRMS web app.

---

## Requirements

| | |
|---|---|
| Node | 20 or newer (built against 24) |
| OS to build on | Windows (for `signtool` and the NSIS target) |
| OS to run on | Windows 10 / 11, x64 |

---

## Quick start (development)

```bash
cd desktop-agent
npm install

# Terminal 1 — a stand-in for the API, which does not exist yet (Phase 4)
npm run mock-server

# Terminal 2 — the agent
npm run dev
```

Sign in with:

- **HRMS address**: `http://127.0.0.1:8787`
- **Email / password**: anything — the mock accepts all credentials

The mock server prints every bucket, screenshot and heartbeat as it arrives,
and exposes three URLs for driving the agent by hand:

| URL | What it does |
|---|---|
| `/dev/stats` | What has been received so far |
| `/dev/request-live` | Asks for a live view; the agent picks it up on its next heartbeat |
| `/dev/revoke-device` | Revokes the device; the agent should stop tracking within five minutes |

---

## Tests

```bash
npm test
```

28 tests, no Electron required — the tracker takes its clock and its idle
reading as injected functions, so a full working day runs in milliseconds.

Covered: the worked example from the brief (09:00 start, idle 10:30–10:50),
idle back-dating, suspend and resume, pause and the daily pause allowance,
working-hours enforcement, screenshot eligibility, restart recovery, and the
durable outbox including corruption and poison-payload handling.

---

## Building the installer

```bash
npm run dist
```

Produces `dist/HRMS-Tracking-Agent-Setup-1.0.0.exe` (NSIS, x64, per-user),
roughly 78 MB.

### Two things that will bite you on Windows

**`ELECTRON_RUN_AS_NODE` must not be set.** With it set, Electron runs the
main script as plain Node, `require('electron')` returns a path string
instead of the API object, and the agent dies on the first line that touches
`app` — with no window and no log, which makes it look like a crash in the
agent rather than an environment problem. Some editors and task runners
export it. Check with `echo %ELECTRON_RUN_AS_NODE%` before blaming the code.

**electron-builder's `winCodeSign` package needs symlink privileges.** It
contains macOS `.dylib` symlinks, and extracting them on Windows fails with
`Cannot create symbolic link : A required privilege is not held by the
client` unless Developer Mode is on or the build runs elevated. The build
retries four times and then stops before it ever reaches NSIS.

Either turn on Developer Mode (Settings → Privacy & security → For
developers), or pre-extract the package without the macOS folder, which
needs no special privileges:

```
set CACHE=%LOCALAPPDATA%\electron-builder\Cache\winCodeSign
7za x -bd "%CACHE%\<id>.7z" -o"%CACHE%\winCodeSign-2.6.0" -xr!darwin
```

`<id>` is the numeric name of the `.7z` the failed run already downloaded
into that folder. Delete the partial numeric directories beside it first,
or electron-builder will keep retrying them.

The Windows tools that are actually used — `rcedit` for the icon and version
metadata, and `signtool` — are all outside that `darwin` folder.

### Code signing — do not skip this

Unsigned, Windows SmartScreen shows a full-screen warning on first run and
most employees will not click past it. IT will also struggle to deploy it.
Treat the certificate as part of the deliverable:

```bash
set CSC_LINK=C:\path\to\certificate.pfx
set CSC_KEY_PASSWORD=...
npm run dist
```

An OV certificate still accumulates SmartScreen reputation over the first few
hundred installs. An EV certificate is trusted immediately. For a rollout
across a company in one week, EV is worth the difference.

### What the installer does

- Installs per-user (no admin rights needed, nothing machine-wide)
- Creates a Start Menu shortcut, no desktop shortcut
- Registers the agent to start with Windows (`HKCU\...\Run`, hidden, `--autostart`)
- Prints what the agent monitors during installation, so nobody finds out afterwards
- On uninstall, removes the Run entry so it cannot come back at next logon

It does **not** install a service or a driver, and does not write outside the
installing user's profile.

---

## Employee installation

1. Run `HRMS-Tracking-Agent-Setup-1.0.0.exe`.
2. At first launch, enter the HRMS address and sign in with normal HRMS
   credentials. The password is used once and never written to disk.
3. The device appears in the HRMS as **pending**. Nothing is tracked until an
   administrator approves it.
4. Once approved, tracking starts automatically and the agent sits in the
   system tray.

Double-click the tray icon for today's totals. Right-click for status, pause
(if permitted), sync, sign out and quit.

The tray icon is a coloured dot: green active, amber idle, grey paused or
stopped.

---

## Privacy

What the agent is built *not* to do is as deliberate as what it does.

| | |
|---|---|
| **No keystroke capture** | No input hook is installed. `powerMonitor.getSystemIdleTime()` returns seconds since last input and nothing else. `key_events` is always `0` |
| **Visible indicator** | Whenever screenshots or live viewing are active, an always-on-top badge is shown. It has no close button — an indicator that can be dismissed is not one |
| **No stealth live viewing** | The agent must confirm to the server that the indicator is showing before any frame is sent. If that confirmation fails, it refuses to stream |
| **Excluded windows** | Captures are skipped when a password manager is in the foreground. If the foreground check itself fails, the capture is skipped — a missed screenshot is a smaller problem than a captured vault |
| **Working hours** | Nothing is recorded outside the configured window |
| **Not while idle or paused** | No captures in either state |
| **Token at rest** | Encrypted with Windows DPAPI via Electron `safeStorage`. If the OS will not provide encryption, the token is **not** written in plain text — the employee is asked to sign in again |
| **Logs** | Record what the agent did, never what the person did. Rotated daily, pruned after 14 days |

---

## Resilience

| Situation | Behaviour |
|---|---|
| Network drops | Everything queues to disk and sends when the connection returns |
| Agent crashes | Session totals are written every tick; the next launch resumes them |
| PC restarts | Same, provided it is the same calendar day |
| PC suspends | The gap is discarded, not banked as work |
| Duplicate submission | Every bucket carries a `client_uuid`; the server rejects a replay |
| Server rejects a payload | Dropped after one rejection, so one bad item cannot wedge the queue |
| Offline for days | Oldest items are dropped past 5,000 so the agent cannot fill the disk |
| Opened twice | The second launch reveals the existing window rather than tracking twice |

---

## API contract (what Phase 4 must implement)

`tools/mock-server.js` is the executable specification. All endpoints take
`Authorization: Bearer <token>`, except login.

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/agent/login` | Credentials + device info → `{ token, user, device }` |
| POST | `/api/agent/logout` | Revoke this device's token |
| GET | `/api/agent/session` | `{ user, device: { status }, settings }` |
| POST | `/api/agent/sessions/start` | → `{ session_id }` |
| POST | `/api/agent/sessions/end` | Close the session |
| POST | `/api/agent/heartbeat` | Keep-alive; response may carry a live request |
| POST | `/api/agent/activity` | Batch of buckets + idle periods |
| POST | `/api/agent/screenshots` | Multipart JPEG upload |
| POST | `/api/agent/live/{id}/notified` | Indicator is showing — gate for streaming |
| POST | `/api/agent/live/{id}/frame` | One JPEG frame |
| POST | `/api/agent/live/{id}/end` | Close the live session |

The agent sends a bearer token and reads one back from login. It does **not**
care whether the server uses Laravel Sanctum or a custom device-token table —
that decision can be made without touching agent code.

---

## Known limitations

1. **Mouse and keyboard counts are always zero.** Counting individual events
   needs a global input hook, which antivirus routinely flags as a keylogger.
   The columns exist so counts can be added later if that trade-off is
   accepted. Active/idle detection — what actually drives the feature — is
   unaffected.

2. **Live viewing is JPEG frame-push over HTTPS at ~2 fps, not WebRTC.** The
   brief says "a suitable secure real-time streaming architecture such as
   WebRTC". True WebRTC needs a signalling server and, for remote workers, a
   TURN server — neither of which the HRMS has. Frame-push needs no new
   infrastructure and works today. `src/main/main.js` isolates this in
   `startLive`/`#pushLiveFrame`, so WebRTC can replace it without touching
   anything else.

3. **Primary monitor only.** Multi-monitor capture is not implemented.

4. **Windows only.** The foreground-window check uses PowerShell.

5. **No auto-update.** An agent that can silently replace its own binary on
   employee machines is a bigger decision than a build setting. Turn it on
   deliberately once there is a certificate and somewhere to host the feed.

6. **Untested against the real API**, which does not exist yet (Phase 4). The
   logic is covered by 28 tests and the agent runs end-to-end against the mock
   server, but the first run against Laravel will need a pass for mismatches.

---

## Layout

```
desktop-agent/
  src/main/        tracker, queue, api, store, capture, autostart, logger, main
  src/preload/     the only bridge to the renderer (contextIsolation on)
  src/renderer/    login, main window, monitoring indicator
  test/            28 tests, no Electron needed
  tools/           mock server (development only, excluded from the build)
  build/           NSIS customisation
```

`src/main/tracker.js` holds the time accounting and is worth reading first.
