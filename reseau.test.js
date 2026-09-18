#!/usr/bin/env node
/**
 * reseau.test.js — Playwright : vérifie la politique d'appel GAS de shared.js
 * (gasAppel / gasLectureDoublee / gasUnAppel) dans un vrai navigateur, avec un
 * fetch instrumenté qui rejoue les pannes observées en production.
 *
 * Lance avec : node reseau.test.js   (exige `npm ci` + Chromium)
 *
 * Pourquoi ce fichier : le mode de défaillance de GAS relevé le 18/09/2026
 * n'est pas « le serveur est lent » mais « la réponse est perdue » — 404 au
 * bout de 27 s, appels jamais résolus. La politique de reprise est donc du
 * code métier à part entière, et les trois suites Node (fonctions pures) ne
 * la voient pas. Ce runner vérifie ce qui compte vraiment :
 *   1. une réponse saine n'est jamais doublée inutilement ;
 *   2. une lecture sans réponse EST doublée, sans attendre son abandon ;
 *   3. un 404 (= livraison ratée) est repris, pas remonté à l'utilisateur ;
 *   4. une ÉCRITURE n'est jamais doublée (deux appendRow concurrents) ;
 *   5. une erreur définitive n'est pas repassée en boucle.
 */

'use strict';

const { chromium } = require('playwright');
const http = require('http');
const fs   = require('fs');
const path = require('path');

const ROOT = __dirname;
const PORT = 17342;
const CHROMIUM_PREINSTALLED = '/opt/pw-browsers/chromium';

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js':   'application/javascript; charset=utf-8',
  '.css':  'text/css; charset=utf-8',
};

// Page nue : juste de quoi charger shared.js (React est exigé à son sommet).
const PAGE = `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"/>
<script src="/node_modules/react/umd/react.production.min.js"></script>
<script src="/node_modules/react-dom/umd/react-dom.production.min.js"></script>
<script src="/utils.js"></script>
<script src="/shared.js"></script>
</head><body><div id="root"></div></body></html>`;

function startServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      const url = req.url.split('?')[0];
      if (url === '/' || url === '/test.html') {
        res.writeHead(200, { 'Content-Type': MIME['.html'] });
        res.end(PAGE);
        return;
      }
      const filePath = path.join(ROOT, url);
      if (!filePath.startsWith(ROOT) || !fs.existsSync(filePath)) {
        res.writeHead(404); res.end('Not found'); return;
      }
      res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream' });
      fs.createReadStream(filePath).pipe(res);
    });
    server.listen(PORT, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

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
    nom: 'écriture : jamais deux appels en vol en même temps',
    plan: [{ delai: 150, status: 404 }, { delai: 150 }],
    action: "gasAppel(URL,'saveEntry',{ecriture:true})",
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
    action: "gasAppel(URL,'logAccesIndex')",
    verifier: r => r.ok === true && r.appels.total === 2 && r.appels.max === 1,
  },
  {
    // Une écriture simple ne doit plus attendre le plafond du cas le plus
    // lourd : coupée à 12 s, reprise aussitôt. Le seuil de 16 s échouerait si
    // quelqu'un remettait saveEntry sur le plafond long (20 s → ~21 s ici).
    nom: 'saveEntry perdu : coupé à 12 s, pas au plafond des lots',
    plan: [{ delai: null }, { delai: 200 }],
    action: "gasAppel(URL,'saveEntry',{ecriture:true})",
    verifier: r => r.ok === true && r.ms > 11000 && r.ms < 16000,
  },
  {
    // saveMany écrit N ateliers d'affilée : il doit garder le plafond long,
    // sinon un import de lot repartirait de zéro en cours de route.
    nom: 'saveMany lent : laissé finir au-delà de 12 s',
    plan: [{ delai: 15000 }],
    action: "gasAppel(URL,'saveMany',{ecriture:true})",
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

(async () => {
  const server  = await startServer();
  const browser = await chromium.launch({
    executablePath: fs.existsSync(CHROMIUM_PREINSTALLED) ? CHROMIUM_PREINSTALLED : undefined,
    args: ['--no-sandbox'],
  });
  const page = await browser.newPage();
  const jsErrors = [];
  page.on('pageerror', err => jsErrors.push(err.message));

  await page.goto(`http://127.0.0.1:${PORT}/test.html`);

  const chargement = await page.evaluate(() => ({
    gasAppel: typeof window.gasAppel === 'function',
    apiFetch: typeof window.apiFetch === 'function',
    fetchAll: typeof window.fetchAll === 'function',
  }));

  let fail = 0;
  for (const [nom, ok] of Object.entries(chargement)) {
    if (!ok) { console.log(`  ✗ window.${nom} absent — shared.js n'a pas chargé`); fail++; }
  }
  if (jsErrors.length) {
    for (const e of jsErrors) { console.log(`  ✗ erreur JS au chargement : ${e}`); fail++; }
  }

  if (fail === 0) {
    console.log('\n── Politique d’appel GAS ──');
    for (const cas of CAS) {
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

      const passe = cas.verifier(res);
      if (passe) {
        console.log(`  ✓ ${cas.nom}  (${res.appels.total} appel(s), ${(res.ms / 1000).toFixed(1)} s)`);
      } else {
        console.log(`  ✗ ${cas.nom}`);
        console.log(`      obtenu : ok=${res.ok} appels=${res.appels.total} max_simultanés=${res.appels.max} encore_en_vol=${res.appels.enCours} abandons=${res.appels.abandons} durée=${(res.ms / 1000).toFixed(1)}s erreur=${res.erreur || '—'}`);
        console.log(`      journal : ${res.journal.join(' | ') || '(vide)'}`);
        fail++;
      }
    }
  }

  await browser.close();
  server.close();

  if (fail === 0) console.log('\n✅ reseau — politique d’appel GAS conforme');
  else console.log(`\n❌ reseau — ${fail} problème(s) détecté(s)`);
  process.exit(fail > 0 ? 1 : 0);
})();
