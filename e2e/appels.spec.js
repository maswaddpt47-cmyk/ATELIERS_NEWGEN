// Nombre d'appels serveur émis à l'ouverture et après écriture, intégrité du
// journal Admin, contrat de l'API (jeton, POST), mot de passe oublié. Porté de
// appels.test.js le 26/09/2026 : mêmes parcours, mêmes contrôles (chaque
// contrôle reste signalé séparément, expect.soft), un test par parcours, en
// parallèle. Échoue si un appel supprimé au démarrage réapparaît.
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
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

const JETON = 'a'.repeat(64);

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
  await ctx.route('**/vendor/leaflet-*/leaflet.js',  r => r.fulfill({ status:200, contentType:MIME['.js'], body:STUBS.leaflet }));
  await ctx.route('**/vendor/leaflet-*/leaflet.css', r => r.fulfill({ status:200, contentType:MIME['.css'], body:'' }));
  await ctx.route('**cdnjs.cloudflare.com/ajax/libs/echarts/**',       r => r.fulfill({ status:200, contentType:MIME['.js'], body:STUBS.echarts }));
  // geo.api.gouv.fr : coordonnees (fetchGPSCommune) et contours de communes.
  // Jamais intercepte jusqu'ici — un vrai appel sortait pendant les suites.
  await ctx.route('**/geo.api.gouv.fr/**', r => r.abort());
  await ctx.route('**/tile.openstreetmap.org/**', r => r.abort());
  await ctx.route('**/tile.openstreetmap.fr/**', r => r.abort());
  await ctx.route('**/tiles.stadiamaps.com/**',   r => r.abort());

  const gas = [];
  await ctx.route('**/script.google.com/**', route => {
    const action = new URL(route.request().url()).searchParams.get('action') || '?';
    appels.push(action);
    gas.push(action);
    route.fulfill({ status:200, contentType:'application/json', body:MOCK });
  });

  // API Alwaysdata : serveur par défaut depuis la bascule du 25/09/2026.
  // Refuse getAll/getConfig sans jeton ; jetonRefuse simule un jeton expiré.
  const p = { ctx, appels, gas, requetes: [], jetonRefuse: false };
  await ctx.route('**/ateliers-numeriques.alwaysdata.net/**', route => {
    const req = route.request();
    const action = new URL(req.url()).searchParams.get('action') || '?';
    const corps = new URLSearchParams(req.postData() || '');
    appels.push(action);
    p.requetes.push({ action, methode: req.method(), url: req.url(), jeton: corps.get('token') });
    let rep = { ok: true };
    if (action === 'getComptes') rep = { ok: true, comptes: p.comptes || [{ conseiller: 'Alice Martin' }], maintenance: false, maintenance_msg: '' };
    else if (action === 'checkPassword') rep = { ok: true, role: 'admin', token: JETON };
    else if (action === 'demanderReinit') rep = { ok: true, message: 'Si une adresse mail est enregistrée pour ce compte, un lien vient d\'y être envoyé.' };
    else if (action === 'reinitMotDePasse') rep = { ok: true, conseiller: 'Alice Martin' };
    if (action === 'demanderReinit' || action === 'reinitMotDePasse') p.corpsReinit = Object.fromEntries(corps);
    else if (p.jetonRefuse) rep = { ok: false, error: 'Non autorisé : jeton manquant ou expiré', auth: true };
    else if (action === 'getAll') rep = { ...JSON.parse(MOCK), conseillers_inactifs: [] };
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(rep) });
  });

  p.page = await ctx.newPage();
  return p;
}

async function connecter(page) {
  const pwd = page.locator('input[type="password"]').first();
  if (await pwd.isVisible({ timeout: 4000 }).catch(() => false)) {
    await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});  // aucun nom présélectionné (25/09/2026)
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

function verifier(nom, condition, detail) {
  expect.soft(!!condition, detail ? `${nom}  (${detail})` : nom).toBe(true);
}

const preparerApi = preparer;
const ecranConnexion = page => page.locator('input[type="password"]').first().isVisible({ timeout: 4000 }).catch(() => false);

// 1. Ouverture d'index.html : getConfig ne doit plus partir (le drapeau
//    maintenance voyage dans getAll).
test('index — ouverture : API seule, sans getConfig dédié', async ({ browser, baseURL }) => {
    const { ctx, page, appels, gas } = await preparer(browser);
    await page.goto(`${baseURL}/index.html`, { waitUntil:'networkidle', timeout:20000 });
    await page.waitForTimeout(1500);
    // Bascule du 25/09/2026 : sans paramètre, plus aucun appel au GAS.
    verifier('index — par défaut : API Alwaysdata, aucun appel GAS', gas.length === 0 && appels.length > 0, gas.join(', ') || 'aucun');
    verifier(
      'index — ouverture sans getConfig dédié',
      !appels.includes('getConfig'),
      appels.join(', ') || 'aucun',
    );
    await ctx.close();
});

// 2. Modifier un atelier depuis le panneau Historique ne doit PAS
//    déclencher de rechargement complet.
test('index — modifier un atelier : saveEntry seul, pas de getAll', async ({ browser, baseURL }) => {
    const { ctx, page, appels } = await preparer(browser);
    await page.goto(`${baseURL}/index.html`, { waitUntil:'networkidle', timeout:20000 });
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
});

// 3. Deux onglets Admin ouverts en même temps ne doivent pas s'écraser le
//    journal — c'est l'outil qui sert à mesurer la latence, et on travaille
//    rarement avec un seul onglet quand on diagnostique.
//    window.gasLogHook (exposé par admin_app.js) appelle addLog : il permet
//    de faire journaliser un onglet précis sans piloter son interface.
test("admin — deux onglets ne s'écrasent pas le journal", async ({ browser, baseURL }) => {
    const { ctx, page: A } = await preparer(browser);
    const connecterAdmin = async (page) => {
      await page.goto(`${baseURL}/admin.html`, { waitUntil:'networkidle', timeout:20000 });
      const pwd = page.locator('input[type="password"]').first();
      if (await pwd.isVisible({ timeout:4000 }).catch(() => false)) {
        await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});  // aucun nom présélectionné (25/09/2026)
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
});

// 4 à 6. Mode API (?backend=php, AG-011) : l'API refuse getAll/getConfig
//    sans jeton. Avant connexion, seul getComptes (public) doit partir ;
//    après, tout part en POST avec le jeton dans le corps, jamais dans
//    l'URL ; une réponse {auth:true} ramène à l'écran de connexion.
test('api — index : avant/après connexion, jeton, jeton refusé', async ({ browser, baseURL }) => {
    const p = await preparerApi(browser);
    await p.page.goto(`${baseURL}/index.html?backend=php`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.waitForTimeout(1200);
    verifier('api — index avant connexion : getComptes seul', p.appels.join(',') === 'getComptes', p.appels.join(', ') || 'aucun');
    verifier('api — aucun appel GAS en mode API', !p.appels.includes('?') && p.requetes.length === p.appels.length);
    await connecter(p.page);
    await p.page.waitForTimeout(800);
    const getAll = p.requetes.find(r => r.action === 'getAll');
    verifier('api — index après connexion : getAll part', !!getAll, p.appels.join(', '));
    verifier('api — POST, jeton dans le corps, rien dans l\'URL',
      !!getAll && getAll.methode === 'POST' && getAll.jeton === JETON && !/token|password/.test(getAll.url),
      getAll ? `${getAll.methode} ${getAll.url}` : '');
    verifier('api — pas de getComptes après connexion (inactifs dans getAll)', p.appels.filter(a => a === 'getComptes').length === 1, p.appels.join(', '));
    // Ajouté au portage (26/09/2026) : depuis la bascule, rien ne se charge
    // avant la connexion, donc « ouverture sans getConfig » ne voyait plus
    // rien. Le drapeau maintenance voyage dans getAll : pas d'appel dédié.
    verifier('api — index après connexion : pas de getConfig dédié', !p.appels.includes('getConfig'), p.appels.join(', '));
    p.jetonRefuse = true;
    await p.page.evaluate(() => window.fetchAll(new Date().getFullYear(), { force: true }).catch(() => {}));
    await p.page.waitForTimeout(800);
    verifier('api — index : jeton refusé → écran de connexion', await ecranConnexion(p.page));
    await p.ctx.close();
});

test('api — admin : avant/après connexion, jeton refusé', async ({ browser, baseURL }) => {
    const p = await preparerApi(browser);
    await p.page.goto(`${baseURL}/admin.html?backend=php`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.waitForTimeout(1200);
    verifier('api — admin avant connexion : ni getAll ni getConfig', p.appels.join(',') === 'getComptes', p.appels.join(', ') || 'aucun');
    const pwd = p.page.locator('input[type="password"]').first();
    await p.page.locator('select').first().selectOption({ index: 1 }).catch(() => {});  // aucun nom présélectionné (25/09/2026)
    await pwd.fill('test');
    await p.page.getByRole('button', { name:/Connexion/ }).click();
    await p.page.waitForTimeout(1500);
    verifier('api — admin après connexion : getAll part', p.appels.includes('getAll'), p.appels.join(', '));
    p.jetonRefuse = true;
    await p.page.evaluate(() => window.fetchAll(new Date().getFullYear(), { force: true, source: 'admin' }).catch(() => {}));
    await p.page.waitForTimeout(800);
    verifier('api — admin : jeton refusé → écran de connexion', await ecranConnexion(p.page));
    await p.ctx.close();
});

test('api — liste de connexion Admin sans les comptes « sans Admin »', async ({ browser, baseURL }) => {
    // Connecté en admin, getComptes rend TOUS les comptes avec leur
    // interrupteur (onglet Listes) : la liste de connexion (bouton « Changer »)
    // ne doit garder que les « accès Admin » (26/09/2026).
    const p = await preparerApi(browser);
    p.comptes = [
      { conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' },
      { conseiller: 'Bruno Sansadmin', role: 'user', actif: 'NON' },
    ];
    await p.page.goto(`${baseURL}/admin.html?backend=php`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.waitForTimeout(1200);
    const noms = await p.page.locator('select').first().locator('option').allTextContents();
    verifier('api — liste de connexion Admin sans les comptes « sans Admin »',
      noms.includes('Alice Martin') && !noms.includes('Bruno Sansadmin'), noms.join(', '));
    await p.ctx.close();
});

// 8. Déconnexion automatique après 30 min d'inactivité (24/09/2026),
//    sauf pendant la saisie d'un atelier (rien n'est gardé en brouillon).
test("index — déconnexion après 30 min d'inactivité, sauf en saisie", async ({ browser, baseURL }) => {
    const { ctx, page } = await preparer(browser);
    await page.goto(`${baseURL}/index.html`, { waitUntil:'networkidle', timeout:20000 });
    await connecter(page);
    const vieillir = () => page.evaluate(() => {
      localStorage.setItem('newgen:idx_derniere_activite', String(Date.now() - 31 * 60 * 1000));
      window.dispatchEvent(new Event('focus'));
    });
    const pwdVisible = () => page.locator('input[type="password"]').first().isVisible({ timeout:1500 }).catch(() => false);
    await page.locator('nav.bottom-nav-v2').getByText('Nouveau', { exact:true }).click();
    await page.waitForTimeout(500);
    // Le titre de l'onglet suit la vue (app.js : « Nouveau — … » en saisie).
    const enSaisie = (await page.title()).startsWith('Nouveau');
    await vieillir(); await page.waitForTimeout(400);
    verifier('index — 30 min d\'inactivité pendant la saisie : pas déconnecté', enSaisie && !(await pwdVisible()), enSaisie ? '' : 'formulaire de saisie non ouvert');
    await page.locator('nav.bottom-nav-v2').getByText('Historique', { exact:true }).click();
    await page.waitForTimeout(400);
    await vieillir(); await page.waitForTimeout(400);
    verifier('index — 30 min d\'inactivité hors saisie : retour à la connexion', await pwdVisible());
    await ctx.close();
});

// 7. Mot de passe oublié (AG-013) : lien sur l'écran de connexion, puis
//    formulaire ouvert par le lien reçu par mail (?reinit=…).
test('api — mot de passe oublié et lien reçu', async ({ browser, baseURL }) => {
    const p = await preparerApi(browser);
    await p.page.goto(`${baseURL}/index.html?backend=php`, { waitUntil:'networkidle', timeout:20000 });
    // Aucun nom présélectionné (25/09/2026) : sans choix, le lien ne peut
    // rien demander — vérifié ici, avant de choisir.
    await p.page.getByRole('button', { name:'Mot de passe oublié ?' }).click();
    verifier('index — aucun nom présélectionné : demande de lien impossible sans choix',
      await p.page.getByRole('button', { name:/Recevoir un lien/ }).isDisabled());
    await p.page.locator('select').first().selectOption({ index: 1 });
    await p.page.getByRole('button', { name:/Recevoir un lien/ }).click();
    await p.page.waitForTimeout(600);
    const retour = (p.corpsReinit && p.corpsReinit.retour) || '';
    verifier('api — mot de passe oublié : demande envoyée, retour vers la page (API par défaut)',
      p.appels.includes('demanderReinit') && /\/index\.html$/.test(retour), retour);
    verifier('api — mot de passe oublié : message affiché',
      await p.page.getByText(/un lien vient d/).isVisible().catch(() => false));
    const jeton = 'c'.repeat(64);
    await p.page.goto(`${baseURL}/index.html?backend=php&reinit=${jeton}`, { waitUntil:'networkidle', timeout:20000 });
    await p.page.getByPlaceholder('Nouveau mot de passe').fill('Nouveau-Mdp-2026!');
    await p.page.getByPlaceholder('Confirmer').fill('Nouveau-Mdp-2026!');
    await p.page.getByRole('button', { name:/Valider/ }).click();
    await p.page.waitForTimeout(600);
    const req = p.requetes.filter(r => r.action === 'reinitMotDePasse').pop();
    verifier('api — lien reçu : jeton et mot de passe dans le corps POST, pas dans l\'URL',
      !!req && req.methode === 'POST' && p.corpsReinit.jeton === jeton && !/jeton|password/.test(req.url));
    verifier('api — lien reçu : succès affiché, jeton retiré de la barre d\'adresse',
      await p.page.getByText(/Mot de passe changé/).isVisible().catch(() => false) && !p.page.url().includes('reinit='), p.page.url());
    await p.ctx.close();
});

