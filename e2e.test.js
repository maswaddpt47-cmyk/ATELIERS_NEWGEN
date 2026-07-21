#!/usr/bin/env node
/**
 * e2e.test.js — Playwright end-to-end : charge index.html + admin.html,
 * clique chaque onglet, détecte les erreurs JS console.
 * Lance un serveur HTTP local pour servir les fichiers statiques.
 * Intercepte les appels GAS (script.google.com) avec des réponses mock.
 */

'use strict';

const { chromium } = require('playwright');
const http = require('http');
const fs   = require('fs');
const path = require('path');

const ROOT = __dirname;
const PORT = 17341;

// ── Réponse mock GAS ──────────────────────────────────────────────────────
const MOCK_RESPONSE = JSON.stringify({
  ok: true,
  entries: [],
  lists: {
    statuts:     ['Planifié','Réalisé','Annulé','Reporté','Non réalisé'],
    conseillers: ['Alice Martin'],
    publics:     ['Séniors'],
    materiels:   ['Ordinateur'],
  },
  config: {},
  role: 'admin',
  comptes: [],
  visibility: {
    saisie:true, historique:true, dashboard:true,
    carte:true,  bingo:true,      calendrier:true,
    agenda:true, roadmap:true,
  },
  colors: {},
});

// ── MIME types ────────────────────────────────────────────────────────────
const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js':   'application/javascript; charset=utf-8',
  '.css':  'text/css; charset=utf-8',
  '.json': 'application/json',
  '.png':  'image/png',
  '.svg':  'image/svg+xml',
};

// ── Serveur HTTP local ────────────────────────────────────────────────────
function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      let filePath = path.join(ROOT, req.url === '/' ? '/index.html' : req.url);
      // Ignorer la query string
      filePath = filePath.split('?')[0];
      const ext = path.extname(filePath);
      if (!fs.existsSync(filePath)) {
        res.writeHead(404); res.end('Not found'); return;
      }
      res.writeHead(200, { 'Content-Type': MIME[ext] || 'application/octet-stream' });
      fs.createReadStream(filePath).pipe(res);
    });
    server.listen(PORT, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

// ── Onglets à tester ──────────────────────────────────────────────────────
// index.html : vue par défaut = 'accueil', puis sélection d'un conseiller
// admin.html : vue par défaut = 'historique'

const INDEX_VIEWS = [
  // Après le choix du conseiller on atterrit sur 'historique'
  // On teste chaque bouton de la barre de navigation
  { label: 'Historique', ariaLabel: 'Historique' },
  { label: 'Nouveau',    ariaLabel: 'Nouveau'    },
  { label: 'Stats',      ariaLabel: 'Stats'      },
  { label: 'Agenda',     ariaLabel: 'Agenda'     },
  { label: 'Calendrier', ariaLabel: 'Calendrier' },
  { label: 'Carte',      ariaLabel: 'Carte'      },
  { label: 'Roadmap',    ariaLabel: 'Roadmap'    },
  { label: 'Bingo',      ariaLabel: 'Bingo'      },
];

const ADMIN_VIEWS = [
  { label: 'Historique', ariaLabel: 'Historique' },
  { label: 'Nouveau',    ariaLabel: 'Nouveau'    },
  { label: 'Dashboard',  ariaLabel: 'Dashboard'  },
  { label: 'Agenda',     ariaLabel: 'Agenda'     },
  { label: 'Calendrier', ariaLabel: 'Calendrier' },
  { label: 'Carte',      ariaLabel: 'Carte'      },
  { label: 'Roadmap',    ariaLabel: 'Roadmap'    },
  { label: 'Bingo',      ariaLabel: 'Bingo'      },
  { label: 'Anomalies',  ariaLabel: 'Anomalies'  },
  { label: 'Admin',      ariaLabel: 'Admin'      },
];

// ── Collecte les erreurs et intercepte les requêtes GAS ──────────────────
async function testPage(browser, url, views, skipAccueil) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const errors = [];

  page.on('pageerror', err => {
    errors.push({ type: 'pageerror', msg: err.message });
  });
  page.on('console', msg => {
    if (msg.type() === 'error') {
      const text = msg.text();
      // Ignorer les erreurs réseau attendues (GAS intercepté, Leaflet tiles, etc.)
      if (
        text.includes('favicon') ||
        text.includes('Failed to load resource') ||
        text.includes('net::ERR_') ||
        text.includes('404') ||
        text.includes('tile') ||
        text.includes('openstreetmap')
      ) return;
      errors.push({ type: 'console.error', msg: text });
    }
  });

  // Intercepter CDN React/ReactDOM → servir les fichiers locaux npm
  const REACT_PATH     = path.join(ROOT, 'node_modules/react/umd/react.production.min.js');
  const REACT_DOM_PATH = path.join(ROOT, 'node_modules/react-dom/umd/react-dom.production.min.js');
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react/**', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: fs.readFileSync(REACT_PATH) });
  });
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react-dom/**', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: fs.readFileSync(REACT_DOM_PATH) });
  });
  // Stub Leaflet minimal pour que VueCarte ne crashe pas
  const LEAFLET_STUB = `
window.L = {
  map: (id) => {
    const el = document.getElementById(id);
    if(el) el.innerHTML = '<div style="background:#e2e8f0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#888">Carte désactivée (test)</div>';
    const m = { setView:()=>m, remove:()=>{}, invalidateSize:()=>{}, off:()=>{} };
    return m;
  },
  tileLayer: () => ({ addTo:()=>{} }),
  circleMarker: () => ({ addTo:()=>({ bindPopup:()=>({}) }), bindPopup:()=>({}) }),
  popup: () => ({ setLatLng:()=>({setContent:()=>({openOn:()=>{}})})}),
};
`;
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/leaflet/**/*.js', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: LEAFLET_STUB });
  });
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/leaflet/**/*.css', route => {
    route.fulfill({ status:200, contentType:'text/css; charset=utf-8', body: '' });
  });
  // Stub echarts minimal
  const ECHARTS_STUB = `window.echarts = { init:()=>({ setOption:()=>{}, resize:()=>{}, dispose:()=>{}, off:()=>{} }), registerMap:()=>{} };`;
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/echarts/**', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: ECHARTS_STUB });
  });
  // Intercepter les appels GAS → réponse mock
  await ctx.route('**/script.google.com/**', route => {
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: MOCK_RESPONSE,
    });
  });
  // Intercepter les tiles Leaflet et autres ressources externes silencieusement
  await ctx.route('**/tile.openstreetmap.org/**', route => route.abort());
  await ctx.route('**/tiles.stadiamaps.com/**', route => route.abort());

  await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });

  // Sur index.html, passer l'écran d'accueil
  if (!skipAccueil) {
    try {
      const skipBtn = page.getByText('Voir tous les ateliers');
      if (await skipBtn.isVisible({ timeout: 3000 })) {
        await skipBtn.click();
        await page.waitForTimeout(600);
      }
    } catch (_) { /* pas d'écran accueil */ }
  }

  // Sur admin.html, passer l'écran de login (formulaire mot de passe)
  if (skipAccueil) {
    try {
      const pwdInput = page.locator('input[type="password"],input[type="text"][placeholder="Mot de passe"]').first();
      if (await pwdInput.isVisible({ timeout: 3000 })) {
        await pwdInput.fill('test');
        await page.getByRole('button', { name: 'Connexion' }).click();
        // Attendre que le formulaire disparaisse et la nav apparaisse
        await page.waitForTimeout(1500);
      }
    } catch (_) { /* pas de login visible */ }
  }

  // Attendre que React ait fini de rendre
  await page.waitForTimeout(800);

  const results = [];

  for (const v of views) {
    // Chercher le bouton par aria-label ou par texte
    let btn = null;
    try {
      btn = page.getByRole('button', { name: v.ariaLabel, exact: true });
      if (!(await btn.isVisible({ timeout: 1500 }))) btn = null;
    } catch (_) { btn = null; }

    if (!btn) {
      // Bouton potentiellement absent (onglet désactivé) — pas une erreur
      results.push({ view: v.label, status: 'skip', reason: 'bouton absent' });
      continue;
    }

    const errsBefore = errors.length;
    try {
      await btn.click();
      await page.waitForTimeout(500);
    } catch (e) {
      results.push({ view: v.label, status: 'fail', reason: `click échoué: ${e.message}` });
      continue;
    }
    const newErrs = errors.slice(errsBefore);
    if (newErrs.length > 0) {
      results.push({ view: v.label, status: 'fail', reason: newErrs.map(e=>e.msg).join(' | ') });
    } else {
      results.push({ view: v.label, status: 'ok' });
    }
  }

  await ctx.close();
  return { errors, results };
}

// ── Entrée principale ─────────────────────────────────────────────────────
(async () => {
  let server, browser;
  let exitCode = 0;

  try {
    server  = await startServer();
    browser = await chromium.launch({
      executablePath: '/opt/pw-browsers/chromium',
      args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    const BASE = `http://127.0.0.1:${PORT}`;

    // ── index.html ────────────────────────────────────────────
    console.log('\n── index.html ─────────────────────────────────────────────');
    const idx = await testPage(browser, `${BASE}/index.html`, INDEX_VIEWS, false);
    let idxFail = 0;
    for (const r of idx.results) {
      if (r.status === 'ok')   console.log(`  ✅ ${r.view}`);
      else if (r.status==='skip') console.log(`  ⏭  ${r.view} — ${r.reason}`);
      else { console.log(`  ❌ ${r.view} — ${r.reason}`); idxFail++; }
    }
    // Erreurs pageerror non liées à un onglet spécifique
    const idxPageErrs = idx.errors.filter(e=>e.type==='pageerror');
    if (idxPageErrs.length) {
      console.log(`  ⚠ pageerror hors-onglet (${idxPageErrs.length}) :`);
      idxPageErrs.forEach(e => console.log(`     ${e.msg}`));
      exitCode = 1;
    }
    if (idxFail > 0) exitCode = 1;

    // ── admin.html ────────────────────────────────────────────
    console.log('\n── admin.html ─────────────────────────────────────────────');
    const adm = await testPage(browser, `${BASE}/admin.html`, ADMIN_VIEWS, true);
    let admFail = 0;
    for (const r of adm.results) {
      if (r.status === 'ok')   console.log(`  ✅ ${r.view}`);
      else if (r.status==='skip') console.log(`  ⏭  ${r.view} — ${r.reason}`);
      else { console.log(`  ❌ ${r.view} — ${r.reason}`); admFail++; }
    }
    const admPageErrs = adm.errors.filter(e=>e.type==='pageerror');
    if (admPageErrs.length) {
      console.log(`  ⚠ pageerror hors-onglet (${admPageErrs.length}) :`);
      admPageErrs.forEach(e => console.log(`     ${e.msg}`));
      exitCode = 1;
    }
    if (admFail > 0) exitCode = 1;

    console.log('');
    if (exitCode === 0) console.log('✅ e2e : aucune erreur JS détectée');
    else                console.log('❌ e2e : des erreurs ont été détectées (voir ci-dessus)');

  } catch (err) {
    console.error('❌ e2e : erreur fatale —', err.message);
    exitCode = 1;
  } finally {
    if (browser) await browser.close().catch(()=>{});
    if (server)  server.close();
  }

  process.exit(exitCode);
})();
