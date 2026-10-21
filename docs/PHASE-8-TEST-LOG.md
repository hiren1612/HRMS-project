# Phase 8 — end-to-end test log

The brief lists **sixteen** end-to-end scenarios. Only six of them are named
in the material available in this repository; the other ten are recorded in
the brief itself and have not been reached. This file records what was
actually run, what passed, and — where it matters — what could not be
reached and why.

A scenario is only marked **Pass** if it was exercised against the real
installer, the real agent code and the real server. Behaviour covered by
unit tests with an injected clock is recorded separately, because the
distinction is the whole reason phase 8 exists.

**Run:** 21 October 2026 · Windows 10 · local Laravel server · agent 1.0.0

---

## Summary

| # | Scenario | Result |
|---|---|---|
| 1 | Fresh installation | **Pass**, with findings |
| 2 | Startup after restart | **Partial** — autostart path verified, signed-in restore not reachable |
| 3 | Idle detection | **Not run end-to-end** — covered by unit tests only |
| 4 | Reconnection | **Pass** |
| 5 | Crash recovery | **Pass** |
| 6 | Duplicate registration | **Pass** |
| 7–16 | Not known | **Blocked** — the list is in the brief, which is not in this repository |

One real defect was found and fixed. See *Findings*.

---

## 1. Fresh installation — Pass

Installed `HRMS-Tracking-Agent-Setup-1.0.0.exe` on a machine with no prior
installation, no autostart entry and no agent data.

| Check | Result |
|---|---|
| Installer exit code | 0, in 11.6s |
| Needed administrator rights | No — installed per-user |
| Install location | `%LOCALAPPDATA%\Programs\HRMS Tracking Agent`, 268 MB, 74 files |
| Nothing written to Program Files | Correct — per-user as configured |
| Start Menu shortcut | Present, target resolves |
| Desktop shortcut | Absent — correct, the config asks for none |
| Apps & Features entry | Registered as "HRMS Tracking Agent 1.0.0", publisher "Hike Branding" |
| Executable metadata | Product name, version and company all correct |

**Not covered.** The installer was run with `/S`, so the interactive wizard
pages — including the transparency notice in `customInstall` — were not
seen. Someone should click through it once before rollout.

**Known gap, not a test failure.** The installer is unsigned, so
`Get-AuthenticodeSignature` reports `NotSigned`. The build had no
mark-of-the-web because it was produced locally; a copy downloaded by an
employee will carry one, and SmartScreen will show a full-screen warning
that this test did not reproduce. **Scenario 1 cannot be honestly signed
off until there is a code-signing certificate**, because the flow tested is
not the flow an employee will meet.

---

## 2. Startup after restart — Partial

The machine was not rebooted. Instead the exact command Windows runs at
logon was taken from the registry and executed:

```
...\HRMS Tracking Agent.exe --autostart
```

| Check | Result |
|---|---|
| Autostart registered on first run | Yes, pointing at the installed exe |
| Agent starts under `--autostart` | Yes, 4 processes |
| Window shown | **None** — started hidden to the tray, which is the required behaviour |

**Not covered.** Restoring a *signed-in* session across a restart. Signing
in needs text typed into the agent's own window, which could not be
automated here. What was verified is that the agent launches hidden by the
same command Windows will use; what was not verified is that it resumes an
existing session and does not double-count the time the machine was off.

---

## 3. Idle detection — not run end-to-end

Covered by nine agent unit tests, including the worked example from the
brief (09:00 start, idle from 10:30, returns 10:50 → 91 minutes active, 20
minutes idle, the whole idle stretch back-dated).

Those tests inject the clock and the idle reading. A real run needs the
agent signed in and a real person not touching the machine, which this
session could not arrange.

---

## 4. Reconnection — Pass

Run against the real server, which was genuinely stopped mid-test.

1. Signed in, opened session 3, device approved
2. **Stopped the Laravel server.** Confirmed unreachable (curl returned `000`)
3. Queued three activity buckets while offline; the queue file was written to disk
4. A heartbeat was attempted and failed with "Cannot reach the HRMS" — no crash, nothing lost
5. The process was run twice while offline, so six items accumulated across two process lifetimes
6. **Restarted the server**
7. All six items were read back off disk and accepted
8. The identical batch was then re-sent, as a retry after a dropped acknowledgement would

**Database afterwards:** 3 rows, 3 distinct client uuids, session total 180
seconds active. Six queued items carrying three unique buckets, sent twice,
produced three rows and 180 seconds — not 360, not 720. Idempotency holds
through a real outage.

---

## 5. Crash recovery — Pass

A process queued five items and was then killed outright with
`Stop-Process -Force` (TerminateProcess — no signal handler, no flush, no
cleanup).

| Check | Result |
|---|---|
| Queue file survives the kill | Yes |
| A new process recovers the items | Yes — all five |
| Identifiers intact and unique | Yes |
| Acknowledging drains the queue | Yes, 0 left |

---

## 6. Duplicate registration — Pass

Two employees, both with real employee records, signing in on one machine.

| Step | Result |
|---|---|
| Employee A registers device | Accepted, status pending |
| Employee B, same device UUID | **409 — "This device is already registered to another employee."** |
| Employee A signs in again | Accepted — same person on the same machine is normal |

**A note on how this was first tested wrongly.** The first attempt used
`hr@hrms.test` as the second employee and got a 403. That looked like a
pass but was not: the refusal was "this account is not linked to an employee
record", so the duplicate-device check was never reached. The three demo
accounts all have a null `employee_id`, which is QA item M15. The test was
redone with two accounts that do have employee records.

---

## Findings

### F1 — Uninstalling left an autostart entry pointing at a deleted file (fixed)

**Severity: medium.** Found by uninstalling and looking, which is the only
way it surfaces.

`build/installer.nsh` did try to clean up the Run key, but deleted the wrong
value name. Electron's `app.setLoginItemSettings()` registers under
`electron.app.` plus the product name; the uninstaller deleted the bare
product name. The real entry therefore survived, and Windows would try to
launch a deleted executable at every logon for the life of the account.

On a managed fleet this is the kind of leftover IT notices and objects to.

Fixed: the uninstaller now removes both value names. The installer was
rebuilt and **the fix was then verified by a full cycle** — install, launch
once so the agent registers autostart (confirmed present as
`electron.app.HRMS Tracking Agent`), uninstall, check the registry. The
entry is now removed. The machine used for testing was left clean: no
install directory, no user data, no registry entries, no Start Menu
shortcut.

### F2 — An empty install folder is left behind (cosmetic)

After uninstalling, `%LOCALAPPDATA%\Programs\HRMS Tracking Agent` remained
as an empty directory: 0 files, 0 bytes. The application itself was fully
removed. Not worth fixing on its own.

---

## What is still blocked

| Blocker | Consequence |
|---|---|
| **No code-signing certificate** | Scenario 1 cannot be signed off; the tested flow is not the one employees meet |
| **No scheduler cron on the server** | A retention test would pass locally and be wrong in production |
| **The other ten scenarios** | Not in this repository; needed from the brief |
| **No GUI automation here** | Anything requiring typing into the agent's own window — signing in, and therefore the signed-in restore across a reboot |

## What should be run next

1. Click through the interactive wizard once and read the transparency notice
2. A real reboot with the agent signed in, to finish scenario 2
3. A full working day on one machine, reconciled against the dashboard
4. Scenarios 7–16, once the list is to hand
