'use strict';

const { spawnSync } = require('child_process');
const path = require('path');

const worker = path.join(__dirname, 'generate-tts.cjs');
const args = process.argv.slice(2);

const result = spawnSync(process.execPath, [worker, ...args], {
    cwd: __dirname,
    stdio: ['ignore', 'pipe', 'pipe'],
    timeout: 120000,
    windowsHide: true,
    env: process.env,
});

if (result.stdout?.length) {
    process.stdout.write(result.stdout);
}

if (result.stderr?.length) {
    process.stderr.write(result.stderr);
}

if (result.error) {
    console.error(result.error.message);
    process.exit(1);
}

process.exit(typeof result.status === 'number' ? result.status : 1);
