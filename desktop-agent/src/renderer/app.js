'use strict';

const el = (id) => document.getElementById(id);

const LABELS = {
    active: 'Working',
    idle: 'Idle',
    paused: 'Paused',
    offline: 'Not tracking',
};

function render(state) {
    const user = state.user || {};

    el('name').textContent = user.name || 'Not signed in';
    el('role').textContent = user.employee_code
        ? `${user.designation || 'Employee'} · ${user.employee_code}`
        : (state.serverUrl || '—');
    el('avatar').textContent = (user.name || '?').trim().charAt(0).toUpperCase();

    // Status pill
    const status = state.status || 'offline';
    const pill = el('status');

    pill.className = 'status ' + status;
    el('statusText').textContent = LABELS[status] || 'Not tracking';
    el('dot').className = status === 'active' ? 'dot pulse' : 'dot';

    // Times
    el('active').textContent = state.activeLabel || '0h 00m';
    el('idle').textContent = state.idleLabel || '0h 00m';
    el('tracked').textContent = state.trackedLabel || '0h 00m';

    // Monitoring
    const shots = el('shots');

    shots.textContent = state.screenshotsEnabled ? 'On' : 'Off';
    shots.className = 'v ' + (state.screenshotsEnabled ? 'on' : 'off');

    const live = el('live');

    live.textContent = state.liveActive ? 'Being viewed now' : 'Not active';
    live.className = 'v ' + (state.liveActive ? 'warn' : 'off');

    el('queued').textContent = `${state.queued || 0} item${state.queued === 1 ? '' : 's'}`;
    el('autostart').textContent = state.autoStart ? 'Yes' : 'No';

    // Pause button
    const pause = el('pause');

    pause.textContent = state.paused ? 'Resume' : 'Pause';
    pause.disabled = !state.running || !state.allowPause;

    // Messages
    el('blocked').hidden = !state.blocked;
    if (state.blocked) el('blocked').textContent = state.blocked;
    el('offline').hidden = !state.offline;
}

el('pause').addEventListener('click', async () => {
    const resuming = el('pause').textContent === 'Resume';

    if (resuming) await window.agent.resume();
    else await window.agent.pause();

    window.agent.requestState();
});

el('open').addEventListener('click', () => window.agent.openHrms());
el('sync').addEventListener('click', () => window.agent.syncNow());
el('signout').addEventListener('click', () => window.agent.signOut());

window.agent.onState(render);
window.agent.requestState();
