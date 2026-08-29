import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const pagesRoot = path.join(root, 'pages');
const required = [
  'index.html',
  'guide.html',
  'demo.html',
  'support.html',
  '404.html',
  '.nojekyll',
  'assets/site.css',
  'assets/site.js',
  'assets/demo.js'
];
const failures = [];

for (const relative of required) {
  if (!fs.existsSync(path.join(pagesRoot, relative))) {
    failures.push(`Missing Pages asset: ${relative}`);
  }
}

const htmlFiles = fs.readdirSync(pagesRoot)
  .filter((name) => name.endsWith('.html'))
  .map((name) => path.join(pagesRoot, name));

for (const htmlFile of htmlFiles) {
  const html = fs.readFileSync(htmlFile, 'utf8');
  for (const needle of ['<html lang="en">', '<meta name="viewport"', '<main', 'Skip to content']) {
    if (!html.includes(needle)) failures.push(`${path.basename(htmlFile)} is missing required markup: ${needle}`);
  }

  for (const match of html.matchAll(/(?:href|src)="([^"]+)"/g)) {
    const target = match[1];
    if (!target || target.startsWith('#') || /^(?:https?:|mailto:)/.test(target)) continue;
    const pathOnly = target.split(/[?#]/, 1)[0];
    const resolved = path.resolve(path.dirname(htmlFile), pathOnly);
    if (!resolved.startsWith(pagesRoot + path.sep) || !fs.existsSync(resolved)) {
      failures.push(`${path.basename(htmlFile)} has a broken local reference: ${target}`);
    }
  }
}

const demoScript = fs.readFileSync(path.join(pagesRoot, 'assets', 'demo.js'), 'utf8');
for (const primitive of ['fetch(', 'XMLHttpRequest', 'WebSocket', 'navigator.sendBeacon', 'localStorage', 'sessionStorage']) {
  if (demoScript.includes(primitive)) failures.push(`Demo must remain offline and ephemeral; found ${primitive}`);
}

const siteCss = fs.readFileSync(path.join(pagesRoot, 'assets', 'site.css'), 'utf8');
for (const demoContrastRule of [
  '.demo-table {\n  width: 100%;\n  border-collapse: collapse;\n  background: var(--demo-card);',
  '.demo-card .table-wrap {',
  '.demo-topbar .demo-status {'
]) {
  if (!siteCss.includes(demoContrastRule)) failures.push(`Missing demo contrast rule: ${demoContrastRule}`);
}

const pagesText = required
  .filter((relative) => fs.existsSync(path.join(pagesRoot, relative)) && fs.statSync(path.join(pagesRoot, relative)).isFile())
  .map((relative) => fs.readFileSync(path.join(pagesRoot, relative), 'utf8'))
  .join('\n');
for (const privateMarker of ['IT Done Right', 'Tyler Gifol', 'TylerGifol']) {
  if (pagesText.toLowerCase().includes(privateMarker.toLowerCase())) failures.push(`Pages content includes private marker: ${privateMarker}`);
}

if (failures.length) {
  console.error(failures.join('\n'));
  process.exit(1);
}

console.log(`Pages portal validation passed (${htmlFiles.length} HTML pages).`);
