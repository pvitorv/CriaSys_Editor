import { contextBridge, ipcRenderer } from 'electron';

contextBridge.exposeInMainWorld('criasys', {
    platform: process.platform,
    isDesktop: true,
    getProjectRoot: () => ipcRenderer.invoke('criasys:getProjectRoot'),
    openProjectFolder: (projectId) => ipcRenderer.invoke('criasys:openProjectFolder', projectId),
    openExportsFolder: () => ipcRenderer.invoke('criasys:openExportsFolder'),
    openDataFolder: () => ipcRenderer.invoke('criasys:openDataFolder'),
    pickWatchFolder: () => ipcRenderer.invoke('criasys:pickWatchFolder'),
    watchFolder: (folderPath) => ipcRenderer.invoke('criasys:watchFolder', folderPath),
    readLocalFile: (filePath) => ipcRenderer.invoke('criasys:readLocalFile', filePath),
    onFolderChanged: (callback) => {
        const handler = (_event, data) => callback(data);
        ipcRenderer.on('criasys:folderChanged', handler);
        return () => ipcRenderer.removeListener('criasys:folderChanged', handler);
    },
    getPortableInfo: () => ipcRenderer.invoke('criasys:getPortableInfo'),
    getRenderStatus: () => ipcRenderer.invoke('criasys:getRenderStatus'),
    restartLaravel: () => ipcRenderer.invoke('criasys:restartLaravel'),
});
