'use strict';

const form = document.getElementById('form');
const errorBox = document.getElementById('error');
const submit = document.getElementById('submit');

form.addEventListener('submit', async (event) => {
    event.preventDefault();

    errorBox.hidden = true;
    submit.disabled = true;
    submit.textContent = 'Signing in…';

    try {
        await window.agent.signIn({
            serverUrl: document.getElementById('server').value.trim(),
            email: document.getElementById('email').value.trim(),
            password: document.getElementById('password').value,
        });
        // The main process closes this window on success.
    } catch (error) {
        // Electron prefixes IPC errors; show only the useful part.
        errorBox.textContent = String(error?.message || error).replace(/^Error invoking remote method '[^']+':\s*/, '');
        errorBox.hidden = false;
        submit.disabled = false;
        submit.textContent = 'Sign in';
    }
});
