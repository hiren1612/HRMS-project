'use strict';

const badge = document.getElementById('badge');
const title = document.getElementById('title');
const by = document.getElementById('by');

window.agent.onIndicator((state) => {
    if (state.live) {
        badge.classList.add('live');
        title.textContent = 'Your screen is being viewed';
        by.textContent = state.watcher ? 'by ' + state.watcher : 'by an administrator';
        return;
    }

    badge.classList.remove('live');
    title.textContent = 'Screenshots are on';
    by.textContent = 'Working hours only';
});
