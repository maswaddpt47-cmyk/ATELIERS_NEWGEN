#!/usr/bin/env node
/**
 * appels.test.js — Playwright : vérifie ce sur quoi repose le diagnostic de
 * latence — le nombre d'appels GAS réellement émis, et l'intégrité du journal
 * Admin qui sert à les lire.
 *
 * Lance avec : node appels.test.js   (exige `npm ci` + Chromium)
 *
 * Pourquoi : sur ce déploiement Apps Script, une partie des réponses n'est
 * jamais livrée (404 au bout de 27 s, appels abandonnés — voir la note en
 * tête de shared.js). Chaque appel émis est donc un tirage au sort, et le
 * journal du 18/09/2026 montre que la panne frappe par fenêtres de temps :
 * trois appels lancés ensemble à 22:10:09 sont morts ensemble. Le nombre
 * d'appels n'est pas une question de quota, c'est une question de latence
 * ressentie — d'où ce garde-fou, qui échoue si un appel réapparaît.
 */

'use strict';

const { chromium } = require('playwright');
const http = require('http');
const fs   = require('fs');
const path = require('path');

const ROOT = __dirname;
const PORT = 17343;
const CHROMIUM_PREINSTALLED = '/opt/pw-browsers/chromium';

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js':   'application/javascript; charset=utf-8',
  '.css':  'text/css; charset=utf-8',
};

const ENTREE = {
  _id: 'entry_test_1', _n: 1, statut: 'Planifié', date: '2026-09-21',
  horaire: '09:00', ampm: 'AM', orienteur: 'CCAS', commune: 'AGEN (47000)',
  lieu: 'Mairie', thematique: 'Démarches en ligne', inscrits: 8, presents: 0,
  public: 'Séniors', conseiller: 'Alice Martin', co_animateur: '',
  residence: '', remarques: '', materiel: [], nb_ordinateurs: '',
};

const MOCK = JSON.stringify({
  ok: true,
  entries: [ENTREE],
  lists: {
    statuts: ['Planifié','Réalisé','Annulé','Reporté','Non réalisé'],
    conseillers: ['Alice Martin'],
    publics: ['Séniors'],
    materiels: ['Ordinateur'],
  },
  config: {}, role: 'admin', comptes: [], colors: {},
  visibility: {
    saisie:true, historique:true, dashboard:true, carte:true,
    bingo:true, calendrier:true, agenda:true, roadmap:true, gestion_ordi:true,
  },
});

function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const filePath = path.join(ROOT, (req.url === '/' ? '/index.html' : req.url).split('?')[0]);
      if (!filePath.startsWith(ROOT) || !fs.existsSync(filePath)) { res.writeHead(404); res.end('nope'); return; }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream' });
      fs.createReadStream(filePath).pipe(res);
    });
    server.listen(PORT, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

const STUBS = {
  leaflet: `window.L={map:id=>{const m={setView:()=>m,remove:()=>{},invalidateSize:()=>{},off:()=>{}};return m;},tileLayer:()=>({addTo:()=>{}}),circleMarker:()=>({addTo:()=>({bindPopup:()=>({})}),bindPopup:()=>({})}),popup:()=>({setLatLng:()=>({setContent:()=>({openOn:()=>{}})})})};`,
  echarts: `window.echarts={init:()=>({setOption:()=>{},resize:()=>{},dispose:()=>{},off:()=>{}}),registerMap:()=>{}};`,
};

async function preparer(browser) {
  const ctx = await browser.newContext();
  const appels = [];
  const react    = path.join(ROOT, 'node_modules/react/umd/react.production.min.js');
  const reactDom = path.join(ROOT, 'node_modules/react-dom/umd/react-dom.production.min.js');

  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react/**',     r => r.fulfill({ status:200, contentType:MIME['.js'], body:fs.readFileSync(react) }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react-dom/**', r => r.fulfill({ status:200, contentType:MIME['.js'], body:fs.readFileSync(reactDom) }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/leaflet/**/*.js',  r => r.fulfill({ status:200, contentType:MIME['.js'], body:STUBS.leaflet }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/leaflet/**/*.css', r => r.fulfill({ status:200, contentType:MIME['.css'], body:'' }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/echarts/**',       r => r.fulfill({ status:200, contentType:MIME['.js'], body:STUBS.echarts }));
  await ctx.route('**/tile.openstreetmap.org/**', r => r.abort());
  await ctx.route('**/tiles.stadiamaps.com/**',   r => r.abort());

  await ctx.route('**/script.google.com/**', route => {
    const action = new URL(route.request().url()).searchParams.get('action') || '?';
    appels.push(action);
    route.fulfill({ status:200, contentType:'application/json', body:MOCK });
  });

  const page = await ctx.newPage();
  return { ctx, page, appels };
}

async function connecter(page) {
  const pwd = page.locator('input[type="password"]').first();
  if (await pwd.isVisible({ timeout: 4000 }).catch(() => false)) {
    await pwd.fill('test');
    await page.getByRole('button', { name: /Connexion/ }).click();
    await page.waitForTimeout(1200);
  }
  const acces = page.getByText('Accéder à mes ateliers');
  if (await acces.isVisible({ timeout: 2000 }).catch(() => false)) {
    await page.locator('select.accueil-select').selectOption({ index: 1 });
    await acces.click();
    await page.waitForTimeout(600);
  }
}

const resultats = [];
function verifier(nom, condition, detail) {
  resultats.push({ nom, ok: !!condition, detail });
  console.log(`  ${condition ? '✓' : '✗'} ${nom}${detail ? `  (${detail})` : ''}`);
}

(async () => {
  const server  = await startServer();
  const browser = await chromium.launch({
    executablePath: fs.existsSync(CHROMIUM_PREINSTALLED) ? CHROMIUM_PREINSTALLED : undefined,
    args: ['--no-sandbox'],
  });

  console.log('\n── Appels GAS émis ──');

  // 1. Ouverture d'index.html : getConfig ne doit plus partir (le drapeau
  //    maintenance voyage dans getAll).
  {
    const { ctx, page, appels } = await preparer(browser);
    await page.goto(`http://127.0.0.1:${PORT}/index.html`, { waitUntil:'networkidle', timeout:20000 });
    await page.waitForTimeout(1500);
    verifier(
      'index — ouverture sans getConfig dédié',
      !appels.includes('getConfig'),
      appels.join(', ') || 'aucun',
    );
    await ctx.close();
  }

  // 2. Modifier un atelier depuis le panneau Historique ne doit PAS
  //    déclencher de rechargement complet.
  {
    const { ctx, page, appels } = await preparer(browser);
    await page.goto(`http://127.0.0.1:${PORT}/index.html`, { waitUntil:'networkidle', timeout:20000 });
    await connecter(page);
    await page.waitForTimeout(800);

    const carte = page.getByText('Démarches en ligne').first();
    if (await carte.isVisible({ timeout: 4000 }).catch(() => false)) {
      await carte.click();
      await page.waitForTimeout(500);
      const bouton = page.getByRole('button', { name: /Enregistrer|Mettre à jour|Valider/ }).first();
      if (await bouton.isVisible({ timeout: 3000 }).catch(() => false)) {
        appels.length = 0;               // on ne compte que l'après-clic
        await bouton.click();
        await page.waitForTimeout(2500);
        verifier(
          'index — modifier un atelier : saveEntry seul, pas de getAll',
          appels.includes('saveEntry') && !appels.includes('getAll'),
          appels.join(', ') || 'aucun',
        );
      } else {
        verifier('index — modifier un atelier : saveEntry seul, pas de getAll', false, 'bouton de sauvegarde introuvable');
      }
    } else {
      verifier('index — modifier un atelier : saveEntry seul, pas de getAll', false, 'atelier de test introuvable dans Historique');
    }
    await ctx.close();
  }

  // 3. Deux onglets Admin ouverts en même temps ne doivent pas s'écraser le
  //    journal — c'est l'outil qui sert à mesurer la latence, et on travaille
  //    rarement avec un seul onglet quand on diagnostique.
  //    window.gasLogHook (exposé par admin_app.js) appelle addLog : il permet
  //    de faire journaliser un onglet précis sans piloter son interface.
  {
    const { ctx, page: A } = await preparer(browser);
    const connecterAdmin = async (page) => {
      await page.goto(`http://127.0.0.1:${PORT}/admin.html`, { waitUntil:'networkidle', timeout:20000 });
      const pwd = page.locator('input[type="password"]').first();
      if (await pwd.isVisible({ timeout:4000 }).catch(() => false)) {
        await pwd.fill('test');
        await page.getByRole('button', { name:/Connexion/ }).click();
        await page.waitForTimeout(1500);
      }
    };
    const marquer = (page, nom) => page.evaluate(n => {
      if (!window.gasLogHook) throw new Error('gasLogHook absent');
      window.gasLogHook({ action:n, attempt:1, ms:100, issue:'ok' });
    }, nom);

    await connecterAdmin(A);
    const B = await ctx.newPage();            // même contexte = même localStorage
    await connecterAdmin(B);

    await marquer(A, 'MARQUEUR_A1'); await A.waitForTimeout(300);
    await marquer(B, 'MARQUEUR_B1'); await B.waitForTimeout(300);
    await marquer(A, 'MARQUEUR_A2'); await A.waitForTimeout(500);  // A écrit APRÈS B

    const msgs = JSON.parse(await A.evaluate(() => localStorage.getItem('newgen:adm_logs') || '[]'))
      .map(e => e.msg || '');
    verifier(
      'admin — deux onglets ne s’écrasent pas le journal',
      msgs.some(m => m.includes('MARQUEUR_B1')) && msgs.some(m => m.includes('MARQUEUR_A2')),
      `${msgs.length} entrées conservées`,
    );
    await ctx.close();
  }

  await browser.close();
  server.close();

  const echecs = resultats.filter(r => !r.ok).length;
  if (echecs === 0) console.log('\n✅ appels — aucun appel GAS superflu sur les parcours couverts');
  else console.log(`\n❌ appels — ${echecs} problème(s) détecté(s)`);
  process.exit(echecs > 0 ? 1 : 0);
})();
