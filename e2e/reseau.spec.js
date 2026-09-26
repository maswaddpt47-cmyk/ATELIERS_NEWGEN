// Politique d'appel réseau de shared.js (gasAppel / gasLectureDoublee /
// gasUnAppel) dans un vrai navigateur, avec un fetch instrumenté qui rejoue les
// pannes observées en production. Porté de reseau.test.js le 26/09/2026 :
// mêmes scénarios, mêmes seuils, mais un test par scénario, en parallèle
// (ils attendent de vrais délais, jusqu'à ~26 s — en série : 85 s).
//
// Ce qui compte : une réponse saine n'est jamais doublée ; une lecture sans
// réponse EST doublée sans attendre son abandon ; un 404 (livraison ratée)
// est repris ; une ÉCRITURE n'est jamais doublée ; une erreur définitive
// n'est pas repassée en boucle. Si un seuil échoue, c'est qu'on est en train
// de rallonger les plafonds (CLAUDE.md, section 5).
const { test, expect } = require('@playwright/test');

// ── Faux fetch installé dans la page ───────────────────────────────────────
// `plan` = liste de réponses consommées dans l'ordre d'arrivée des appels.
// Chaque entrée : {delai, status} ou {delai:null} pour « ne répond jamais ».
function installerFetch(plan) {
  window.__appels = { total: 0, enCours: 0, max: 0, abandons: 0 };
  window.fetch = function (url, opts) {
    const idx = window.__appels.total++;
    window.__appels.enCours++;
    window.__appels.max = Math.max(window.__appels.max, window.__appels.enCours);
    const spec = plan[Math.min(idx, plan.length - 1)];
    return new Promise((resolve, reject) => {
      let minuteur = null, compte = false;
      // Idempotent : un abort qui arrive après la réponse ne doit pas
      // décompter l'appel une seconde fois (le vrai fetch l'ignore).
      const fini = () => {
        if (compte) return;
        compte = true;
        window.__appels.enCours--;
        if (minuteur) clearTimeout(minuteur);
      };
      if (opts && opts.signal) {
        opts.signal.addEventListener('abort', () => {
          window.__appels.abandons++;
          fini();
          reject(new DOMException('Aborted', 'AbortError'));
        });
      }
      if (spec.delai === null) return; // réponse perdue : ne se résout jamais
      minuteur = setTimeout(() => {
        fini();
        const status = spec.status || 200;
        resolve({
          ok: status >= 200 && status < 300,
          status,
          text: () => Promise.resolve(JSON.stringify({ ok: true, entries: [], marque: idx })),
        });
      }, spec.delai);
    });
  };
}

const CAS = [
  {
    nom: 'réponse saine : un seul appel réseau, aucun doublon',
    plan: [{ delai: 200 }],
    // On laisse passer le seuil de doublage (7 s) pour vérifier que le
    // minuteur a bien été annulé et qu'aucun second appel ne part après coup.
    attendreApres: 8500,
    action: "gasAppel(URL,'getAll')",
    verifier: r => r.ok === true && r.appels.total === 1 && r.appels.max === 1,
  },
  {
    nom: 'lecture sans réponse : doublée vers 7 s, le doublon gagne',
    plan: [{ delai: null }, { delai: 300 }],
    action: "gasAppel(URL,'getAll')",
    // Résolution attendue ~7,3 s : le doublon répond sans qu'on ait attendu
    // l'abandon du premier à 12 s.
    verifier: r => r.ok === true && r.appels.total === 2 && r.ms > 6500 && r.ms < 10000,
  },
  {
    // Le doublon part à 7 s, le premier répond à 9 s : le doublon doit être
    // annulé sur-le-champ, pas laissé courir jusqu'à son plafond. Sinon il
    // consomme une exécution GAS pour rien et finit par écrire « bloqué —
    // abandonné après 12s » dans le journal, alors que l'appel avait réussi
    // (observé en production le 18/09/2026 à 23:04:23).
    nom: 'doublon devenu inutile : annulé, et pas journalisé en erreur',
    plan: [{ delai: 9000 }, { delai: null }],
    action: "gasAppel(URL,'getAll')",
    attendreApres: 6000,
    verifier: r => r.ok === true && r.appels.total === 2
      && r.appels.enCours === 0
      && !r.journal.some(l => l.includes('bloqué')),
  },
  {
    nom: 'HTTP 404 (livraison ratée) : repris, pas remonté',
    plan: [{ delai: 150, status: 404 }, { delai: 150 }],
    action: "gasAppel(URL,'getAll')",
    verifier: r => r.ok === true && r.appels.total === 2,
  },
  {
    // L'appel ne déclare RIEN : c'est gasAppel qui reconnaît saveEntry via
    // GAS_ACTIONS_ECRITURE et refuse de la doubler. Ce test échouerait si
    // cette liste redevenait une option à passer par l'appelant — un oubli
    // suffirait alors à faire deux appendRow concurrents, donc un atelier en
    // double.
    nom: 'écriture reconnue sans rien déclarer : jamais deux appels en vol',
    plan: [{ delai: 150, status: 404 }, { delai: 150 }],
    action: "gasAppel(URL,'saveEntry')",
    verifier: r => r.ok === true && r.appels.max === 1 && r.appels.total === 2,
  },
  {
    nom: 'checkPassword : repris mais jamais doublé (compteur d’échecs)',
    plan: [{ delai: 150, status: 404 }, { delai: 150 }],
    action: "gasAppel(URL,'checkPassword')",
    verifier: r => r.ok === true && r.appels.max === 1,
  },
  {
    nom: 'erreur définitive (403) : échec immédiat, aucune reprise',
    plan: [{ delai: 100, status: 403 }],
    action: "gasAppel(URL,'getAll')",
    verifier: r => r.ok === false && r.appels.total === 1,
  },
  {
    // Les logs ont d'abord été limités à une seule tentative (« personne
    // n'attend le résultat »). Corrigé le 18/09/2026 : une connexion perdue
    // n'est jamais écrite dans Logs_Connexion, et la traçabilité des accès en
    // dépend. Ils reprennent donc comme une lecture, mais sans doublage.
    nom: 'log en arrière-plan : repris, mais jamais doublé',
    plan: [{ delai: 100, status: 404 }, { delai: 100 }],
    action: "gasAppel(URL,'logLogin')",
    verifier: r => r.ok === true && r.appels.total === 2 && r.appels.max === 1,
  },
  {
    // Une écriture simple ne doit plus attendre le plafond du cas le plus
    // lourd : coupée à 12 s, reprise aussitôt. Le seuil de 16 s échouerait si
    // quelqu'un remettait saveEntry sur le plafond long (20 s → ~21 s ici).
    nom: 'saveEntry perdu : coupé à 12 s, pas au plafond des lots',
    plan: [{ delai: null }, { delai: 200 }],
    action: "gasAppel(URL,'saveEntry')",
    verifier: r => r.ok === true && r.ms > 11000 && r.ms < 16000,
  },
  {
    // saveMany écrit N ateliers d'affilée : il doit garder le plafond long,
    // sinon un import de lot repartirait de zéro en cours de route.
    nom: 'saveMany lent : laissé finir au-delà de 12 s',
    plan: [{ delai: 15000 }],
    action: "gasAppel(URL,'saveMany')",
    verifier: r => r.ok === true && r.appels.total === 1 && r.ms > 14000,
  },
  {
    // Rejoue le pire tirage relevé dans le journal de production du
    // 18/09/2026 : un appel jamais livré, puis un 404 qui met 26 s à venir,
    // puis une réponse saine. L'ancienne politique (35 s de plafond, une
    // seule reprise, pas de doublage) mettait 70 s à l'écran avant de rendre
    // la main en erreur. Le seuil de ce test est là pour que personne ne
    // rallonge les plafonds sans voir ce que ça coûte.
    nom: 'pire tirage du 18/09 rejoué : sous 25 s au lieu de 70',
    plan: [{ delai: null }, { delai: 26000, status: 404 }, { delai: 1100 }],
    action: "gasAppel(URL,'getAll')",
    verifier: r => r.ok === true && r.ms < 25000,
  },
];

test('shared.js charge et expose la couche réseau', async ({ page }) => {
  const erreurs = [];
  page.on('pageerror', e => erreurs.push(e.message));
  await page.goto('/test-reseau.html');
  const presentes = await page.evaluate(() => ['gasAppel', 'apiFetch', 'fetchAll'].filter(n => typeof window[n] === 'function'));
  expect(erreurs).toEqual([]);
  expect(presentes).toEqual(['gasAppel', 'apiFetch', 'fetchAll']);
});

for (const cas of CAS) {
  test(cas.nom, async ({ page }) => {
    await page.goto('/test-reseau.html');
    const res = await page.evaluate(async ({ plan, action, installerSrc, attendreApres }) => {
      eval('(' + installerSrc + ')')(plan);
      const URL = 'https://script.google.com/macros/s/TEST/exec?action=x';
      const t0 = Date.now();
      let ok = false, erreur = null;
      try { await eval(action); ok = true; }
      catch (e) { erreur = e.message; }
      const ms = Date.now() - t0;
      if (attendreApres) await new Promise(r => setTimeout(r, attendreApres));
      const journal = (window.__gasLog || []).map(e => `${e.action} ${e.issue}`);
      return { ok, erreur, ms, appels: { ...window.__appels }, journal };
    }, {
      plan: cas.plan,
      action: cas.action,
      installerSrc: installerFetch.toString(),
      attendreApres: cas.attendreApres || 0,
    });
    const detail = `ok=${res.ok} appels=${res.appels.total} max_simultanés=${res.appels.max} `
      + `encore_en_vol=${res.appels.enCours} abandons=${res.appels.abandons} `
      + `durée=${(res.ms / 1000).toFixed(1)}s erreur=${res.erreur || '—'} `
      + `journal=${res.journal.join(' | ') || '(vide)'}`;
    expect(cas.verifier(res), detail).toBe(true);
  });
}
