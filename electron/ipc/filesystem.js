import { ipcMain, shell, dialog, BrowserWindow } from 'electron';
import path from 'node:path';
import fs from 'node:fs';

let projectRoot = '';
let dataPath = '';
let folderWatcher = null;

const MIME_BY_EXT = {
    png: 'image/png',
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    webp: 'image/webp',
    gif: 'image/gif',
};

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

    ipcMain.handle('criasys:pickWatchFolder', async () => {
        const result = await dialog.showOpenDialog({
            properties: ['openDirectory'],
            title: 'Pasta para monitorar (Image Studio)',
        });
        return result.canceled ? null : result.filePaths[0];
    });

    ipcMain.handle('criasys:watchFolder', async (_event, folderPath) => {
        if (folderWatcher) {
            folderWatcher.close();
            folderWatcher = null;
        }
        if (!folderPath || !fs.existsSync(folderPath)) {
            return null;
        }
        folderWatcher = fs.watch(folderPath, (_eventType, filename) => {
            if (!filename) {
                return;
            }
            const filePath = path.join(folderPath, filename.toString());
            if (!fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
                return;
            }
            BrowserWindow.getAllWindows().forEach((win) => {
                win.webContents.send('criasys:folderChanged', { filePath, folderPath });
            });
        });
        return folderPath;
    });

    ipcMain.handle('criasys:readLocalFile', async (_event, filePath) => {
        if (!filePath || !fs.existsSync(filePath)) {
            return null;
        }
        const ext = path.extname(filePath).slice(1).toLowerCase();
        const mime = MIME_BY_EXT[ext] || 'application/octet-stream';
        const buf = fs.readFileSync(filePath);
        return {
            dataUrl: `data:${mime};base64,${buf.toString('base64')}`,
            filename: path.basename(filePath),
        };
    });
}

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}
