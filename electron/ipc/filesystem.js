import { ipcMain, shell } from 'electron';
import path from 'node:path';
import fs from 'node:fs';

let projectRoot = '';
let dataPath = '';

export function registerFilesystemIpc(root, portableDataPath = '') {
    projectRoot = root;
    dataPath = portableDataPath;

    ipcMain.handle('criasys:getProjectRoot', () => projectRoot);

    ipcMain.handle('criasys:openProjectFolder', async (_event, projectId) => {
        const base = dataPath
            ? path.join(dataPath, 'storage', 'app', 'criasys', 'projetos', String(projectId))
            : path.join(projectRoot, 'storage', 'app', 'criasys', 'projetos', String(projectId));
        ensureDir(base);
        await shell.openPath(base);
        return base;
    });

    ipcMain.handle('criasys:openExportsFolder', async () => {
        const base = dataPath
            ? path.join(dataPath, 'storage', 'app', 'criasys', 'exports')
            : path.join(projectRoot, 'storage', 'app', 'criasys', 'exports');
        ensureDir(base);
        await shell.openPath(base);
        return base;
    });

    ipcMain.handle('criasys:openDataFolder', async () => {
        if (!dataPath) return null;
        ensureDir(dataPath);
        await shell.openPath(dataPath);
        return dataPath;
    });
}

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}
