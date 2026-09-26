// Les deux pages s'ouvrent et chaque onglet répond sans erreur JS (porté de
// e2e.test.js le 26/09/2026 : même parcours, même mock serveur ; index et
// admin tournent en parallèle). Un onglet dont le bouton est absent est
// sauté, comme avant (onglet masqué par la visibilité) — il est listé dans
// les annotations du rapport.
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

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
  { label: 'Gestion ordi', ariaLabel: 'Gestion ordi' },
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
  { label: 'Gestion ordi', ariaLabel: 'Gestion ordi' },
  { label: 'Admin',      ariaLabel: 'Admin'      },
  { label: 'Corbeille',  ariaLabel: 'Corbeille'  },
  { label: 'Sauvegardes', ariaLabel: 'Sauvegardes' },
];

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
  await ctx.route('**/vendor/leaflet-*/leaflet.js', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: LEAFLET_STUB });
  });
  await ctx.route('**/vendor/leaflet-*/leaflet.css', route => {
    route.fulfill({ status:200, contentType:'text/css; charset=utf-8', body: '' });
  });
  // Stub echarts minimal
  const ECHARTS_STUB = `window.echarts = { init:()=>({ setOption:()=>{}, resize:()=>{}, dispose:()=>{}, off:()=>{} }), registerMap:()=>{} };`;
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/echarts/**', route => {
    route.fulfill({ status:200, contentType:'application/javascript; charset=utf-8', body: ECHARTS_STUB });
  });
  // API Alwaysdata (serveur par défaut depuis le 25/09/2026) → même mock,
  // avec un jeton pour la connexion.
  await ctx.route('**/ateliers-numeriques.alwaysdata.net/**', route => {
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ ...JSON.parse(MOCK_RESPONSE), token: 'a'.repeat(64) }),
    });
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
  // geo.api.gouv.fr : coordonnees (fetchGPSCommune) et contours de communes.
  // Jamais intercepte jusqu'ici — un vrai appel sortait pendant les suites.
  await ctx.route('**/geo.api.gouv.fr/**', route => route.abort());
  await ctx.route('**/tile.openstreetmap.org/**', route => route.abort());
  await ctx.route('**/tile.openstreetmap.fr/**', route => route.abort());
  await ctx.route('**/tiles.stadiamaps.com/**', route => route.abort());

  await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });

  // Sur index.html, passer l'écran de connexion (mot de passe par conum),
  // puis l'écran d'accueil (sélection du conum, affiché après connexion)
  if (!skipAccueil) {
    try {
      const pwdInput = page.locator('input[type="password"]').first();
      if (await pwdInput.isVisible({ timeout: 3000 })) {
        await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});  // aucun nom présélectionné (25/09/2026)
        await pwdInput.fill('test');
        await page.getByRole('button', { name: 'Connexion' }).click();
        await page.waitForTimeout(1500);
      }
    } catch (_) { /* pas d'écran de connexion */ }
    try {
      const accessBtn = page.getByText('Accéder à mes ateliers');
      if (await accessBtn.isVisible({ timeout: 3000 })) {
        await page.locator('select.accueil-select').selectOption({ index: 1 });
        await accessBtn.click();
        await page.waitForTimeout(600);
      }
    } catch (_) { /* pas d'écran accueil */ }
  }

  // Sur admin.html, passer l'écran de login (formulaire mot de passe)
  if (skipAccueil) {
    try {
      const pwdInput = page.locator('input[type="password"],input[type="text"][placeholder="Mot de passe"]').first();
      if (await pwdInput.isVisible({ timeout: 3000 })) {
        await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});  // aucun nom présélectionné (25/09/2026)
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


for (const [nom, page, vues, skipAccueil] of [
  ['index.html', '/index.html', INDEX_VIEWS, false],
  ['admin.html', '/admin.html', ADMIN_VIEWS, true],
]) {
  test(`${nom} : chaque onglet répond sans erreur JS`, async ({ browser, baseURL }, info) => {
    const { errors, results } = await testPage(browser, baseURL + page, vues, skipAccueil);
    for (const r of results.filter(r => r.status === 'skip')) {
      info.annotations.push({ type: 'onglet sauté', description: `${r.view} — ${r.reason}` });
    }
    expect(results.filter(r => r.status === 'fail').map(r => `${r.view} : ${r.reason}`)).toEqual([]);
    expect(errors.filter(e => e.type === 'pageerror').map(e => e.msg)).toEqual([]);
    expect(results.filter(r => r.status === 'ok').length).toBeGreaterThan(0);
  });
}
