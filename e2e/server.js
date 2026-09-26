// Serveur statique des tests Playwright : sert la racine du dépôt, plus une
// page nue /test-reseau.html qui ne charge que shared.js (reseau.spec.js).
'use strict';
const http = require('http');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PORT = Number(process.env.PORT_TESTS || 7475);
const MIME = {
  '.html': 'text/html; charset=utf-8', '.js': 'application/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8', '.json': 'application/json', '.png': 'image/png',
  '.svg': 'image/svg+xml', '.ico': 'image/x-icon', '.webmanifest': 'application/manifest+json',
};
const PAGE_RESEAU = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/>
<script src="/node_modules/react/umd/react.production.min.js"></script>
<script src="/node_modules/react-dom/umd/react-dom.production.min.js"></script>
<script src="/utils.js"></script>
<script src="/shared.js"></script>
</head><body><div id="root"></div></body></html>`;

http.createServer((req, res) => {
  const url = decodeURIComponent(req.url.split('?')[0]);
  if (url === '/test-reseau.html') {
    res.writeHead(200, { 'Content-Type': MIME['.html'] });
    return res.end(PAGE_RESEAU);
  }
  const f = path.join(ROOT, url === '/' ? '/index.html' : url);
  if (!f.startsWith(ROOT) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) {
    res.writeHead(404); return res.end('Not found');
  }
  res.writeHead(200, { 'Content-Type': MIME[path.extname(f)] || 'application/octet-stream' });
  fs.createReadStream(f).pipe(res);
}).listen(PORT, '127.0.0.1');
