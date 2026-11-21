// Mémoire « déjà lu » côté serveur (AG-066, 21/11/2026) : vider les données du site (le « hard reset »
// de l'utilisateur) ne rallume plus les pastilles Nouveautés et tickets, ni les étiquettes « Nouveau ».
// Serveur simulé : getAll renvoie `lu`, marquerLu l'enregistre sans jamais le diminuer.
const { test, expect } = require('@playwright/test');

const ENTREE = { _id: 'e1', _n: 1, statut: 'Planifié', date: '2026-12-01', horaire: '09:00', ampm: 'AM', orienteur: 'CCAS', commune: 'AGEN (47000)', lieu: 'Mairie', thematique: 'Démarches en ligne', inscrits: 8, presents: 0, public: 'Séniors', conseiller: 'Alice Martin', co_animateur: '', residence: '', remarques: '', materiel: [], nb_ordinateurs: '' };
const DERNIERE_REPONSE = '2026-11-20 10:00:00';

async function serveur(page, { avecLu = true } = {}) {
  const etat = { lu: { nouveautes_index: 0, nouveautes_admin: 0, tickets: '' }, marquages: [] };
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const q = new URLSearchParams(route.request().postData() || ''); const url = route.request().url();
    const action = q.get('action') || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'user', token: 'b'.repeat(64) };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin' }], maintenance: false, maintenance_msg: '' };
    else if (action === 'getAll') {
      r = { ok: true, years: [2026], entries: [ENTREE], lists: { statuts: ['Planifié', 'Réalisé'], conseillers: ['Alice Martin'], publics: ['Séniors'], materiels: [] }, visibility: { saisie: true, historique: true, dashboard: true, carte: true, bingo: true, calendrier: true, agenda: true, roadmap: true, gestion_ordi: true }, conseiller_colors: {}, conseillers_inactifs: [], stockOrdinateurs: 10, materielsCaches: [], tickets: { nouveaux: 0, derniere_reponse: DERNIERE_REPONSE } };
      if (avecLu) r.lu = { ...etat.lu };
    } else if (action === 'getTickets') r = { ok: true, tickets: [], moi: 'Alice Martin' };
    else if (action === 'marquerLu') {
      const cle = q.get('cle'), valeur = q.get('valeur');
      etat.marquages.push({ cle, valeur });
      if (cle === 'tickets') { if (valeur > etat.lu.tickets) etat.lu.tickets = valeur; }
      else etat.lu[cle] = Math.max(etat.lu[cle], Number(valeur));
      r = { ok: true, lu: { ...etat.lu } };
    }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  return etat;
}

async function connecter(page, baseURL) {
  await page.goto(baseURL + '/index.html', { waitUntil: 'networkidle', timeout: 20000 });
  const pwd = page.locator('input[type="password"]').first();
  if (await pwd.isVisible({ timeout: 4000 }).catch(() => false)) {
    await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
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

const pastilleNouveautes = page => page.locator('button[title="Nouveautés"] .nouv-blink');
const pastilleTickets = page => page.locator('[aria-label$="réponse(s) à vos contributions"]');

test('Index — mémoire « déjà lu » : après un nettoyage des données du site, ni pastille ni « Nouveau »', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const etat = await serveur(page);
  await connecter(page, baseURL);
  await expect(pastilleNouveautes(page)).toHaveCount(1);   // rien de lu : pastille allumée
  await expect(pastilleTickets(page)).not.toHaveCount(0);

  const dernier = await page.evaluate(() => NOUVEAUTES.reduce((m, n) => Math.max(m, n.id), 0));
  await page.locator('button[title="Nouveautés"]').click();
  await expect.poll(() => etat.marquages.filter(m => m.cle === 'nouveautes_index')).toEqual([{ cle: 'nouveautes_index', valeur: String(dernier) }]);
  await page.getByRole('button', { name: 'Contribuer', exact: true }).click();
  await expect.poll(() => etat.marquages.filter(m => m.cle === 'tickets')).toEqual([{ cle: 'tickets', valeur: DERNIERE_REPONSE }]);

  // « Hard reset » : données du site vidées (localStorage et sessionStorage), nouvelle connexion.
  await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
  const avant = etat.marquages.length;
  await connecter(page, baseURL);
  await expect(pastilleNouveautes(page)).toHaveCount(0);
  await expect(pastilleTickets(page)).toHaveCount(0);
  await page.locator('button[title="Nouveautés"]').click();
  await expect(page.getByRole('heading', { name: /Nouveautés/ })).toBeVisible();
  const rubrique = page.locator('.card', { has: page.getByRole('heading', { name: /Nouveautés/ }) });
  await expect(rubrique.getByText('Nouveau', { exact: true })).toHaveCount(0);   // aucune étiquette « Nouveau » rallumée dans la rubrique
  expect(etat.marquages.length).toBe(avant);   // le serveur savait déjà : aucune écriture de plus
  expect(errs).toEqual([]);
});

test('Index — mémoire « déjà lu » : serveur sans le champ (ancienne API), le secours du navigateur tient', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  await serveur(page, { avecLu: false });
  await connecter(page, baseURL);
  await expect(pastilleNouveautes(page)).toHaveCount(1);
  await page.locator('button[title="Nouveautés"]').click();
  await expect(pastilleNouveautes(page)).toHaveCount(0);   // la pastille s'éteint à l'ouverture, comme avant
  await connecter(page, baseURL);   // rechargement sans nettoyage
  await expect(pastilleNouveautes(page)).toHaveCount(0);
  expect(errs).toEqual([]);
});
