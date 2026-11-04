// Tickets (AG-045, 04/11/2026) : l'Admin voit l'interrupteur « prévenir le demandeur par mail », le
// message après enregistrement dit ce qui s'est vraiment passé (mail envoyé / sans adresse / désactivé),
// et l'interrupteur écrit la config. Serveur simulé.
const { test, expect } = require('@playwright/test');
const ticket = { id: 'tk_1', cree_le: '2026-11-03 10:00:00', auteur: 'Nouveau Venu', site: 'nextstep', version: '', appareil: 'PC', type: 'Question', onglet: '', gene: '', titre: 'Où voir mes ateliers ?', description: 'desc', statut: 'Nouveau', reponse: '', repondu_le: null, repondu_par: '', doublon_de: '', clos_le: null };
async function ouvrir(page, baseURL, { mail = 'envoye', mailDem = true, appels = [] } = {}) {
  await page.route('**/ateliers-numeriques.alwaysdata.net/**', async route => {
    const q = new URLSearchParams(route.request().postData() || ''); const url = route.request().url();
    const action = q.get('action') || new URL(url).searchParams.get('action');
    let r = { ok: true };
    if (action === 'checkPassword') r = { ok: true, role: 'admin', token: 'a'.repeat(64) };
    else if (action === 'getAll') r = { ok: true, entries: [], years: [], lists: { statuts: ['Réalisé'], conseillers: ['Alice Martin'], publics: [], materiels: [] }, config: {}, role: 'admin', comptes: [], visibility: {}, colors: {} };
    else if (action === 'getComptes') r = { ok: true, comptes: [{ conseiller: 'Alice Martin', role: 'admin', actif: 'OUI' }] };
    else if (action === 'getTickets') r = { ok: true, tickets: [ticket], moi: 'Alice Martin', mail_demandeur: mailDem };
    else if (action === 'repondreTicket') { appels.push({ action, statut: q.get('statut'), reponse: q.get('reponse') }); r = { ok: true, ticket: { ...ticket, statut: q.get('statut'), reponse: q.get('reponse') }, mail, mail_a: ['inchange', 'deja'].includes(mail) ? '' : 'Nouveau Venu' }; }
    else if (action === 'setConfig') { appels.push({ action, key: q.get('key'), value: q.get('value') }); }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(r) });
  });
  await page.goto(baseURL + '/admin.html');
  const pwd = page.locator('input[type="password"]').first();
  await pwd.waitFor({ timeout: 10000 });
  await page.locator('select').first().selectOption({ index: 1 }).catch(() => {});
  await pwd.fill('test'); await page.getByRole('button', { name: 'Connexion' }).click();
  await page.getByRole('button', { name: /Tickets/ }).first().click();
  await page.getByText('Où voir mes ateliers ?').click();
}
const cas = [
  ['envoye', /mail envoyé à Nouveau Venu/],
  ['sans_adresse', /pas de mail : aucune adresse enregistrée pour Nouveau Venu/],
  ['desactive', /pas de mail : le mail au demandeur est désactivé/],
  ['inchange', /pas de mail : ni le statut ni la réponse n'ont changé/],
  ['auteur', /pas de mail : vous êtes l'auteur de ce ticket/],
  ['anonyme', /pas de mail : l'auteur de ce ticket est anonymisé/],
  ['deja', /pas de mail : un envoi identique vient d'être fait/],
  ['echec', /le mail à Nouveau Venu n'a pas pu partir/],
];
for (const [etat, motif] of cas) {
  test(`Admin — tickets : après enregistrement, l'état « ${etat} » est dit tel quel`, async ({ page, baseURL }) => {
    const errs = []; page.on('pageerror', e => errs.push(e.message));
    await ouvrir(page, baseURL, { mail: etat });
    await page.getByRole('button', { name: /Enregistrer/ }).first().click();
    await expect(page.getByText(motif)).toBeVisible();
    expect(errs).toEqual([]);
  });
}
test('Admin — tickets : l\'interrupteur du mail au demandeur écrit la config', async ({ page, baseURL }) => {
  const errs = []; page.on('pageerror', e => errs.push(e.message));
  const appels = [];
  await ouvrir(page, baseURL, { mail: 'envoye', appels });
  const sw = page.getByLabel(/Prévenir le demandeur par mail/);
  await expect(sw).toBeChecked();
  await sw.uncheck();
  await expect.poll(() => appels.find(a => a.action === 'setConfig')).toEqual({ action: 'setConfig', key: 'tickets_mail_demandeur', value: 'false' });
  expect(errs).toEqual([]);
});
