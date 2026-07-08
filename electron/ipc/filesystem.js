import { ipcMain, shell } from 'electron';
import path from 'node:path';
import fs from 'node:fs';

let projectRoot = '';

export function registerFilesystemIpc(root) {
    projectRoot = root;

    ipcMain.handle('criasys:getProjectRoot', () => projectRoot);

    ipcMain.handle('criasys:openProjectFolder', async (_event, projectId) => {
        const base = path.join(projectRoot, 'storage', 'app', 'criasys', 'projetos', String(projectId));
        ensureDir(base);
        await shell.openPath(base);
        return base;
    });

    ipcMain.handle('criasys:openExportsFolder', async () => {
        const base = path.join(projectRoot, 'storage', 'app', 'criasys', 'exports');
        ensureDir(base);
        await shell.openPath(base);
        return base;
    });
}

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}
