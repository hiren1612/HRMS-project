'use strict';

/**
 * Launch with Windows.
 *
 * Uses Electron's own login-item API, which writes the standard
 * Run registry entry under HKCU. Nothing is installed as a service and
 * nothing is written outside the signed-in user's own profile, so the
 * agent cannot start for anybody who did not install it.
 *
 * --autostart is passed so the agent can tell a Windows-triggered launch
 * from someone opening it from the Start Menu, and stay in the tray rather
 * than popping a window in front of whatever they are doing.
 */
function setAutoStart(app, enabled) {
  try {
    app.setLoginItemSettings({
      openAtLogin: !!enabled,
      openAsHidden: true,
      args: ['--autostart'],
    });

    return true;
  } catch {
    return false;
  }
}

function isAutoStartEnabled(app) {
  try {
    return app.getLoginItemSettings({ args: ['--autostart'] }).openAtLogin;
  } catch {
    return false;
  }
}

function launchedByWindows() {
  return process.argv.includes('--autostart');
}

module.exports = { setAutoStart, isAutoStartEnabled, launchedByWindows };
