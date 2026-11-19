// Suppression d'un avis isolé, page Admin seulement (04/10/2026) : la ligne
// dépliée montre les avis un par un ; « Supprimer » appelle supprimerAvis puis
// recharge le tableau. Serveur simulé, action par action.
const { test, expect } = require('@playwright/test');
const auj = new Date().toISOString().slice(0,10);
test('Admin — avis par atelier : avis un par un, suppression d\'un avis', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  let supprime = 0;
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const body = route.request().postData() || ''; const url = route.request().url();
    const action = (new URLSearchParams(body).get('action')) || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [{ _id: 'x1', date: auj, statut: 'Réalisé', thematique: 'IA', commune: 'AGEN', conseiller: 'Alice Martin' }],
      lists: { statuts: ['Réalisé'], conseillers: ['Alice Martin'], publics: [], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'avisParAtelier') r = { ok: true, ateliers: supprime ? [] : [{ atelier_id: 'x1', n: 1, papier: 0, attentes: 5, clarte: 4, rythme_ok: 1, rythme_n: 1, aise_oui: 1, aise_n: 1, autonomie_oui: 0, autonomie_n: 1, remarques: ['Merci; à bientôt'],
      detail: { rythme: { 'Trop lent': 0, 'Adapté': 1, 'Trop rapide': 0 }, aise: { 'Non': 0, 'Un peu': 0, 'Oui': 1 }, autonomie: { 'Oui': 0, "Avec de l'aide": 0, 'Non': 1 } } }] };
    else if (action === 'avisAtelier') r = { ok: true, avis: supprime ? [] : [{ id: 7, cree_le: auj, source: 'qr', attentes: 5, clarte: 4, rythme: 'Adapté', aise: 'Oui', autonomie: 'Non', sujet: ['Messagerie', 'Autre'], sujet_autre: 'Tablette', remarque: 'Merci' }] };
    else if (action === 'supprimerAvis') { supprime = 1; r = { ok: true }; }
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  page.on('dialog', d => d.accept());
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: 'Dashboard', exact: true }).first().click();
  await page.getByRole('button', { name: '💬 Avis par atelier', exact: true }).click();
  // Détail des réponses et export CSV (06/10/2026).
  await expect(page.locator('td', { hasText: '1 adapté' })).toBeVisible();
  const [dl] = await Promise.all([page.waitForEvent('download'), page.getByRole('button', { name: '⬇️ Export CSV' }).click()]);
  const csv = require('fs').readFileSync(await dl.path(), 'utf8').split('\r\n');
  expect(csv[0]).toContain('Rythme : Adapté;Rythme : Trop lent');
  expect(csv[1]).toContain(';1;0;5;4;1;0;0;1;0;0;0;0;1;"Merci; à bientôt"');
  await page.locator('td', { hasText: 'AGEN' }).first().click();
  await expect(page.getByText('Rythme : Adapté')).toBeVisible();
  await expect(page.getByText('Sujets : Messagerie, Autre — Tablette')).toBeVisible();
  await page.getByRole('button', { name: '🗑️ Supprimer' }).click();
  await expect(page.getByText('Aucun avis pour les ateliers de cette période.')).toBeVisible();
  expect(errs).toEqual([]);
});

// Vue en graphes (AG-063, 19/11/2026) : même population que le tableau, sans
// erreur ni débordement sur le téléphone de référence (390 px).
test('Admin — avis par atelier : vue en graphes à 390 px, sans erreur ni débordement', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const z = k => Object.fromEntries(k.map(x => [x, 0]));
  const det = o => ({ attentes: { ...z(['1','2','3','4','5']), ...o.attentes }, clarte: { ...z(['1','2','3','4','5']), ...o.clarte },
    rythme: { ...z(['Trop lent','Adapté','Trop rapide']), ...o.rythme }, aise: { ...z(['Non','Un peu','Oui']), ...o.aise },
    autonomie: { ...z(['Oui',"Avec de l'aide",'Non']), ...o.autonomie },
    sujet: { ...z(['Smartphone','Messagerie','Démarches en ligne','Sécurité','Intelligence artificielle','Autre']), ...o.sujet }, sans_sujet: o.sans_sujet || 0, total: o.total });
  const ligne = (id, n, d) => ({ atelier_id: id, n, papier: 0, attentes: null, clarte: null, rythme_ok: 0, rythme_n: 0, aise_oui: 0, aise_n: 0, autonomie_oui: 0, autonomie_n: 0, remarques: [], detail: d });
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const body = route.request().postData() || ''; const url = route.request().url();
    const action = (new URLSearchParams(body).get('action')) || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [{ _id: 'g1', date: auj, statut: 'Réalisé', thematique: 'IA', commune: 'AGEN', conseiller: 'Alice Martin' },
      { _id: 'g2', date: auj, statut: 'Réalisé', thematique: 'Mail', commune: 'NÉRAC', conseiller: 'Alice Martin' }],
      lists: { statuts: ['Réalisé'], conseillers: ['Alice Martin'], publics: [], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'avisParAtelier') r = { ok: true, ateliers: [
      ligne('g1', 3, det({ attentes: { '5': 2, '3': 1 }, clarte: { '4': 2 }, rythme: { 'Adapté': 2, 'Trop rapide': 1 }, aise: { 'Oui': 1, 'Un peu': 1 }, autonomie: { 'Oui': 3 }, sujet: { 'Smartphone': 2, 'Sécurité': 2 }, sans_sujet: 1, total: 3 })),
      ligne('g2', 2, det({ attentes: { '4': 1 }, rythme: { 'Trop lent': 2 }, aise: { 'Non': 2 }, autonomie: { 'Non': 1 }, sans_sujet: 2, total: 2 }))] };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: 'Dashboard', exact: true }).first().click();
  await page.getByRole('button', { name: '💬 Avis par atelier', exact: true }).click();
  await page.setViewportSize({ width: 390, height: 844 });   // après la navigation : le toast du harnais couvrait « Dashboard »
  await page.getByRole('button', { name: '📊 Graphes' }).click();
  await expect(page.getByText('Profil des avis')).toBeVisible();
  await expect(page.getByText(/^5 avis sur 2 ateliers/)).toBeVisible();
  await expect(page.getByText('Attentes — moyenne 4,3/5')).toBeVisible();
  await expect(page.getByText('3/4 favorables (75 %)').first()).toBeVisible();   // base du radar affichée (Codex, AG-063)
  await page.waitForTimeout(600);
  if (process.env.CAPTURE_GRAPHES) {   // contrôle visuel : faire défiler pour que chaque graphe se dessine
    for (let y = 0; y < 3000; y += 400) { await page.mouse.wheel(0, 400); await page.waitForTimeout(250); }
    await page.evaluate(() => { const t = document.getElementById('toast'); if (t) t.remove(); });
    await page.screenshot({ path: process.env.CAPTURE_GRAPHES, fullPage: true });
  }
  // Aucune carte ne dépasse la largeur de l'écran.
  const deborde = await page.evaluate(() => [...document.querySelectorAll('div')].filter(d => /^(Profil des avis|Rythme|Sujet souhaité)/.test(d.textContent) && d.children.length >= 2)
    .map(d => Math.round(d.getBoundingClientRect().right)).filter(x => x > window.innerWidth));
  expect(deborde).toEqual([]);
  expect(await page.locator('canvas').count()).toBeGreaterThan(0);
  await page.getByRole('button', { name: '📋 Tableau' }).click();
  await expect(page.locator('td', { hasText: 'AGEN' })).toBeVisible();
  expect(errs).toEqual([]);
});
