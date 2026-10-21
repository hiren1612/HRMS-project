# Deploying the HRMS

Written for whoever has shell access to the production server. Every command
here is run **on the server**, in the application root, unless it says
otherwise.

Items marked 🔴 are outstanding and have been raised in several QA rounds.

---

## 1. 🔴 Turn off debug mode (QA item C3)

**This is the most urgent item in this file.** With `APP_DEBUG=true`, any
unhandled error renders Laravel's debug page, which shows source code, the
database host and username, and the contents of the session — to whoever
triggered the error, including an anonymous visitor.

In `.env`:

```ini
APP_DEBUG=false
APP_ENV=production
```

Then clear the cached config, or the old value stays live:

```bash
php artisan config:clear
php artisan config:cache
```

Verify by visiting a URL that does not exist. You should get a plain 404
page with no stack trace.

---

## 2. 🔴 Run the pending migrations (QA item C4)

Forgot Password currently returns a 500 on production because the table it
needs was never created. The same command also creates the ten tables the
monitoring module needs.

```bash
php artisan migrate --force
```

`--force` is required: Laravel refuses to migrate in production without it.

**Take a database backup first.** These migrations only add tables and
columns, but a backup before any migration is the habit worth keeping.

Expected: three QA migrations, nine monitoring tables, and
`personal_access_tokens` for the desktop agent.

---

## 3. 🔴 Install the scheduler cron entry

Laravel's scheduler is driven by a single cron entry. Without it **nothing
scheduled ever runs**, and two features silently do nothing:

| Scheduled task | What breaks without it |
|---|---|
| `attendance:close-stale` (00:15) | Sessions nobody clocked out of stay open forever |
| `monitoring:prune` (03:30) | Screenshots are never deleted, whatever the retention setting says |

The second one matters beyond tidiness: the settings screen tells an
administrator that screenshots are deleted after 30 days. Without this cron
entry that statement is false.

```bash
crontab -e
```

Add, replacing the path:

```cron
* * * * * cd /path/to/hrms && php artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
php artisan schedule:list      # should list both tasks
php artisan monitoring:prune --dry-run   # safe, deletes nothing
```

---

## 4. Build front-end assets

`public/build` is **not** in the repository, so a deploy that only pulls
code leaves the site running whatever CSS and JS were built last time.

```bash
npm ci
npm run build
```

If this is missing from your deploy script, add it. A recent symptom: the
site served CSS built in June for four months, which predated every QA fix
to the interface.

---

## 5. Other outstanding server items

| | |
|---|---|
| 🔴 **H3** | Mail credentials are not set, so password-reset email never arrives |
| 🔴 | Security headers (`X-Frame-Options`, `X-Content-Type-Options`, HSTS) |
| 🔴 | `server_tokens off` in nginx, to stop advertising the version |
| 🔴 | `robots.txt` — the HRMS should not be indexed |

---

## 6. First run of the monitoring module

The module ships **switched off and closed to everyone**, including
administrators. Running the migrations monitors nobody. To turn it on:

1. **Grant yourself access.** Sign in as an administrator → **Monitoring
   Access** in the sidebar → choose yourself → tick the capabilities you
   need → *Grant access*.

   Being an administrator is not itself clearance to see monitoring data,
   which is why this step exists and why the other monitoring links do not
   appear until you complete it. Granting to yourself is allowed and is
   recorded in the audit log as a self-grant.

2. **Turn tracking on.** With the *Change monitoring settings* capability:
   **Monitoring Settings** → tick **Tracking enabled**. Nothing is recorded
   until this is on.

3. **Install the agent** on a machine, sign in with ordinary HRMS
   credentials, then approve the device under **Devices**. A device that is
   not approved can read its settings and nothing else.

### Before rolling the agent out to staff

- **Tell them first, in writing.** The agent shows an indicator and never
  records keystrokes, but finding tracking software on your machine
  unannounced is its own harm.
- Decide the retention period before switching screenshots on, not after.
- Check the working hours in settings match the contracted hours. Activity
  outside them is discarded, not stored.

---

## 7. 🔴 Code-signing certificate for the desktop agent

The installer is currently **unsigned**. On first run Windows SmartScreen
shows a full-screen warning, and most employees will not click past it. IT
deployment tools will also object.

This needs buying before any rollout.

| | OV certificate | EV certificate |
|---|---|---|
| Trusted immediately | No — builds reputation over a few hundred installs | Yes |
| Typical cost | Lower | Higher |
| Delivery | Often a file | Usually a hardware token or cloud HSM |

For a rollout inside a week, **EV is worth the difference** — an OV
certificate will still trigger warnings for the first wave of installs.

Once you have one, build with:

```bash
set CSC_LINK=C:\path\to\certificate.pfx
set CSC_KEY_PASSWORD=...
npm run dist
```

Never put those in `electron-builder.yml` or commit them.

### Two things that will bite you building on Windows

**`ELECTRON_RUN_AS_NODE` must not be set.** With it set, Electron runs the
main script as plain Node, `require('electron')` returns a string, and the
agent exits instantly with no window and no log — it looks like a crash in
the agent rather than an environment problem.

**electron-builder's `winCodeSign` package contains macOS symlinks**, which
Windows refuses to create without Developer Mode or elevation. The build
retries four times and stops before reaching NSIS, while still exiting 0.
Either turn on Developer Mode, or pre-extract the package without its
`darwin` folder. Full detail in `desktop-agent/README.md`.

---

## 8. Health check after deploying

```bash
php artisan about                 # environment, debug flag, cache state
php artisan migrate:status        # nothing pending
php artisan schedule:list         # both scheduled tasks present
php artisan route:list --path=api # eleven agent endpoints
```

Then, in a browser:

- A nonexistent URL returns a plain 404, **no stack trace**
- Forgot Password sends an email rather than erroring
- The dashboard loads with current styling
