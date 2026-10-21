'use strict';

const { contextBridge, ipcRenderer } = require('electron');

/**
 * The only bridge between the pages and the agent.
 *
 * contextIsolation is on and nodeIntegration is off, so the renderer has no
 * access to the filesystem, the network or the token. It can call exactly
 * the handlers listed here and nothing else.
 */
contextBridge.exposeInMainWorld('agent', {
  signIn: (payload) => ipcRenderer.invoke('agent:sign-in', payload),
  signOut: () => ipcRenderer.invoke('agent:sign-out'),
  pause: () => ipcRenderer.invoke('agent:pause'),
  resume: () => ipcRenderer.invoke('agent:resume'),
  requestState: () => ipcRenderer.invoke('agent:state'),
  syncNow: () => ipcRenderer.invoke('agent:sync-now'),
  openHrms: () => ipcRenderer.invoke('agent:open-hrms'),
  setAutoStart: (enabled) => ipcRenderer.invoke('agent:set-autostart', enabled),

  onState: (handler) => {
    const listener = (_event, state) => handler(state);

    ipcRenderer.on('agent:state', listener);

    return () => ipcRenderer.removeListener('agent:state', listener);
  },

  onIndicator: (handler) => {
    const listener = (_event, state) => handler(state);

    ipcRenderer.on('indicator:state', listener);

    return () => ipcRenderer.removeListener('indicator:state', listener);
  },
});
