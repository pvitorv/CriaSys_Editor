import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('criasys', {
    platform: process.platform,
    isDesktop: true,
    getProjectRoot: () => ipcRenderer.invoke('criasys:getProjectRoot'),
    openProjectFolder: (projectId) => ipcRenderer.invoke('criasys:openProjectFolder', projectId),
    openExportsFolder: () => ipcRenderer.invoke('criasys:openExportsFolder'),
    openDataFolder: () => ipcRenderer.invoke('criasys:openDataFolder'),
    getPortableInfo: () => ipcRenderer.invoke('criasys:getPortableInfo'),
    getRenderStatus: () => ipcRenderer.invoke('criasys:getRenderStatus'),
    restartLaravel: () => ipcRenderer.invoke('criasys:restartLaravel'),
});
