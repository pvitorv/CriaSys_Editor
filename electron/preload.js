import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('criasys', {
    platform: process.platform,
    isDesktop: true,
    getProjectRoot: () => ipcRenderer.invoke('criasys:getProjectRoot'),
    openProjectFolder: (projectId) => ipcRenderer.invoke('criasys:openProjectFolder', projectId),
    openExportsFolder: () => ipcRenderer.invoke('criasys:openExportsFolder'),
    getRenderStatus: () => ipcRenderer.invoke('criasys:getRenderStatus'),
    restartLaravel: () => ipcRenderer.invoke('criasys:restartLaravel'),
});
