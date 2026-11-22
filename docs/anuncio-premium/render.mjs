// Renderiza ad.html quadro a quadro (seek determinístico) e manda para o ffmpeg
import { chromium } from 'playwright-core';
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const dir = path.dirname(fileURLToPath(import.meta.url));
const exe = process.env.LOCALAPPDATA + '/ms-playwright/chromium-1243/chrome-win64/chrome.exe';
const browser = await chromium.launch({ executablePath: exe });
const page = await browser.newPage({ viewport: { width: 1080, height: 1920 }, deviceScaleFactor: 1 });
page.on('pageerror', (e) => console.error('PAGE ERROR', e.message));
await page.goto(pathToFileURL(path.join(dir, 'ad.html')).href);
await page.evaluate(async () => { await document.fonts.ready; });
await page.evaluate((av) => window.init(av), JSON.parse(fs.readFileSync(path.join(dir, 'avatars.json'), 'utf8')));
const settle = () => page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));

const mode = process.argv[2] || 'stills';
if (mode === 'stills') {
    const times = (process.argv[3] || '1,4.5,7,8.4,11.8,14,17.6,19.9,23').split(',').map(Number);
    fs.mkdirSync(path.join(dir, 'stills'), { recursive: true });
    for (const t of times) {
        await page.evaluate((t) => window.seek(t), t); await settle();
        await page.screenshot({ path: path.join(dir, 'stills', `t${t.toFixed(2)}.png`) });
    }
} else {
    const fps = 30;
    const dur = await page.evaluate(() => window.DURATION);
    const n = Math.round(dur * fps);
    const ff = spawn('ffmpeg', ['-y', '-v', 'error', '-f', 'image2pipe', '-framerate', String(fps), '-i', '-', '-c:v', 'libx264', '-preset', 'medium', '-crf', '18', '-pix_fmt', 'yuv420p', path.join(dir, 'video-noaudio.mp4')], { stdio: ['pipe', 'inherit', 'inherit'] });
    for (let i = 0; i < n; i++) {
        await page.evaluate((t) => window.seek(t), i / fps); await settle();
        const buf = await page.screenshot({ type: 'png' });
        if (!ff.stdin.write(buf)) await new Promise((r) => ff.stdin.once('drain', r));
        if (i % 60 === 0) console.log('frame', i, '/', n);
    }
    ff.stdin.end();
    await new Promise((r) => ff.on('close', r));
}
await browser.close();
