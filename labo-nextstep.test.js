#!/usr/bin/env node
/**
 * labo-nextstep.test.js — Playwright : la copie de l'interface NextStep
 * (labo-nextstep/) branchée sur l'API Alwaysdata.
 *
 * Lance avec : node labo-nextstep.test.js   (exige `npm ci` + Chromium)
 *
 * Garde-fou principal : le labo ne doit JAMAIS appeler le GAS de production
 * (il écrirait dans le classeur de l'équipe). Toute requête vers
 * script.google.com fait échouer le test. Vérifie aussi l'ordre de démarrage
 * (AG-011), le jeton dans le corps POST, le retour à la connexion sur
 * {auth:true}, et l'absence d'erreur JS.
 */
'use strict';

const { chromium } = require('playwright');
const http = require('http');
const fs   = require('fs');
const path = require('path');

const ROOT = __dirname;
const PORT = 17351;
const CHROMIUM_PREINSTALLED = '/opt/pw-browsers/chromium';
const MIME = { '.html':'text/html; charset=utf-8', '.js':'application/javascript; charset=utf-8', '.css':'text/css; charset=utf-8', '.png':'image/png' };
const JETON = 'b'.repeat(64);

const ENTREE = {
  _id:'entry_labo_1', _n:1, statut:'Planifié', date:`${new Date().getFullYear()}-10-01`, horaire:'09:00', ampm:'AM',
  orienteur:'CCAS', commune:'AGEN', lieu:'Mairie', thematique:'Démarches en ligne', inscrits:8, presents:'',
  public:'Séniors', conseiller:'Alice Martin', co_animateur:'', residence:'', remarques:'', materiel:[],
  nb_ordinateurs:'', date_prelevement_materiel:'', date_retour_materiel:'',
};
const GETALL = {
  ok:true, entries:[ENTREE],
  lists:{ statuts:['Planifié','Réalisé','Annulé','Reporté','Non réalisé'], conseillers:['Alice Martin'], publics:['Séniors'], materiels:['Ordinateur'] },
  visibility:{}, conseiller_colors:{}, emails:{}, stockOrdinateurs:10, materielsCaches:[], conseillers_inactifs:[],
};

function serveur() {
  return new Promise((resolve, reject) => {
    const s = http.createServer((req, res) => {
      const f = path.join(ROOT, req.url.split('?')[0]);
      if (!f.startsWith(ROOT) || !fs.existsSync(f) || fs.statSync(f).isDirectory()) { res.writeHead(404); res.end(); return; }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(f)] || 'application/octet-stream' });
      fs.createReadStream(f).pipe(res);
    });
    s.listen(PORT, '127.0.0.1', () => resolve(s)); s.on('error', reject);
  });
}

async function preparer(browser) {
  const ctx = await browser.newContext();
  const p = { ctx, appels:[], requetes:[], gas:[], erreurs:[], jetonRefuse:false };
  const js = f => fs.readFileSync(path.join(ROOT, f));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react/**',     r => r.fulfill({ status:200, contentType:MIME['.js'], body:js('node_modules/react/umd/react.production.min.js') }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/react-dom/**', r => r.fulfill({ status:200, contentType:MIME['.js'], body:js('node_modules/react-dom/umd/react-dom.production.min.js') }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/xlsx/**',      r => r.fulfill({ status:200, contentType:MIME['.js'], body:'window.XLSX={utils:{}};' }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/echarts/**',   r => r.fulfill({ status:200, contentType:MIME['.js'], body:'window.echarts={init:()=>({setOption(){},resize(){},dispose(){},off(){}}),registerMap(){}};' }));
  await ctx.route('**/vendor/leaflet-*/leaflet.js',  r => r.fulfill({ status:200, contentType:MIME['.js'], body:'window.L={map:()=>{const m={setView:()=>m,remove(){},invalidateSize(){},off(){}};return m;},tileLayer:()=>({addTo(){}}),circleMarker:()=>({addTo:()=>({bindPopup:()=>({})}),bindPopup:()=>({})})};' }));
  await ctx.route('**/vendor/leaflet-*/leaflet.css', r => r.fulfill({ status:200, contentType:MIME['.css'], body:'' }));
  for (const h of ['geo.api.gouv.fr','tile.openstreetmap.org','tile.openstreetmap.fr','tiles.stadiamaps.com']) await ctx.route(`**/${h}/**`, r => r.abort());
  // Garde-fou : le GAS de production ne doit jamais être appelé.
  await ctx.route('**/script.google.com/**', r => { p.gas.push(r.request().url()); r.abort(); });
  await ctx.route('**/ateliers-numeriques.alwaysdata.net/**', route => {
    const req = route.request();
    const action = new URL(req.url()).searchParams.get('action') || '?';
    const corps = new URLSearchParams(req.postData() || '');
    p.appels.push(action);
    p.requetes.push({ action, methode:req.method(), url:req.url(), jeton:corps.get('token') });
    let rep = { ok:true };
    if (action === 'getComptes') rep = { ok:true, comptes:[{ conseiller:'Alice Martin' }], maintenance:false, maintenance_msg:'' };
    else if (action === 'checkPassword') rep = { ok:true, role:'admin', token:JETON };
    else if (p.jetonRefuse) rep = { ok:false, error:'Non autorisé : jeton manquant ou expiré', auth:true };
    else if (action === 'getAll') rep = GETALL;
    route.fulfill({ status:200, contentType:'application/json', body:JSON.stringify(rep) });
  });
  p.page = await ctx.newPage();
  p.page.on('pageerror', e => p.erreurs.push(e.message));
  return p;
}

const resultats = [];
function verifier(nom, ok, detail) {
  resultats.push(ok);
  console.log(`  ${ok ? '✓' : '✗'} ${nom}${detail ? `  (${detail})` : ''}`);
}
const ecranConnexion = page => page.locator('input[type="password"]').first().isVisible({ timeout:4000 }).catch(() => false);

(async () => {
  const srv = await serveur();
  const browser = await chromium.launch({ executablePath: fs.existsSync(CHROMIUM_PREINSTALLED) ? CHROMIUM_PREINSTALLED : undefined, args:['--no-sandbox'] });
  console.log('\n── Labo NextStep ──');

  {
    const p = await preparer(browser);
    await p.page.goto(`http://127.0.0.1:${PORT}/labo-nextstep/index.html`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.waitForTimeout(1000);
    verifier('index avant connexion : getComptes seul', p.appels.join(',') === 'getComptes', p.appels.join(', ') || 'aucun');
    verifier('bandeau LABO visible', await p.page.locator('#labo-bandeau').isVisible());
    const pwd = p.page.locator('input[type="password"]').first();
    await pwd.fill('test');
    await p.page.getByRole('button', { name:/Connexion/ }).click();
    await p.page.waitForTimeout(1500);
    const getAll = p.requetes.find(r => r.action === 'getAll');
    verifier('index après connexion : getAll en POST, jeton dans le corps, rien dans l\'URL',
      !!getAll && getAll.methode === 'POST' && getAll.jeton === JETON && !/token|password/.test(getAll.url), p.appels.join(', '));
    verifier('jeton rangé sous la clé du labo, pas sous celle de NextStep',
      await p.page.evaluate(() => sessionStorage.getItem('gs_token') === null && sessionStorage.getItem('labo-nextstep:gs_token') !== null));
    p.jetonRefuse = true;
    await p.page.evaluate(() => window.fetchAll(new Date().getFullYear(), { force:true }).catch(() => {}));
    await p.page.waitForTimeout(800);
    verifier('index : jeton refusé → écran de connexion', await ecranConnexion(p.page));
    verifier('index : aucune erreur JS', p.erreurs.length === 0, p.erreurs.join(' | '));
    verifier('index : aucun appel au GAS de production', p.gas.length === 0, p.gas.join(', '));
    await p.ctx.close();
  }
  {
    const p = await preparer(browser);
    await p.page.goto(`http://127.0.0.1:${PORT}/labo-nextstep/admin.html`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.waitForTimeout(1000);
    verifier('admin avant connexion : getComptes seul', p.appels.join(',') === 'getComptes', p.appels.join(', ') || 'aucun');
    const pwd = p.page.locator('input[type="password"]').first();
    await pwd.fill('test');
    await p.page.getByRole('button', { name:/Connexion/ }).click();
    await p.page.waitForTimeout(1500);
    verifier('admin après connexion : getAll part', p.appels.includes('getAll'), p.appels.join(', '));
    p.jetonRefuse = true;
    await p.page.evaluate(() => window.fetchAll(new Date().getFullYear(), { force:true, source:'admin' }).catch(() => {}));
    await p.page.waitForTimeout(800);
    verifier('admin : jeton refusé → écran de connexion', await ecranConnexion(p.page));
    verifier('admin : aucune erreur JS', p.erreurs.length === 0, p.erreurs.join(' | '));
    verifier('admin : aucun appel au GAS de production', p.gas.length === 0, p.gas.join(', '));
    await p.ctx.close();
  }

  await browser.close(); srv.close();
  const echecs = resultats.filter(r => !r).length;
  console.log(echecs ? `\n❌ labo-nextstep — ${echecs} problème(s)` : '\n✅ labo-nextstep — branché sur l\'API, jamais sur le GAS');
  process.exit(echecs ? 1 : 0);
})();
