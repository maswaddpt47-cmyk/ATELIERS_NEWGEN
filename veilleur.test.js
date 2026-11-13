// Essais du veilleur du contrôle d'intégrité (AG-039, 02/11/2026).
// Le script réellement publié (étape « Veille » de veilleur.yml) est extrait
// du YAML et exécuté avec un faux `gh` et un faux `sleep` : aucun appel à
// GitHub, aucune donnée réelle, aucune attente. Les dates sont celles de
// l'horloge réelle, décalées par scénario.
// Cible alternative pour vérifier un test par mutation : VEILLEUR_YML=<fichier>.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawn, spawnSync } = require('child_process');

const YML = process.env.VEILLEUR_YML || path.join(__dirname, '.github', 'workflows', 'veilleur.yml');
const yml = fs.readFileSync(YML, 'utf8');

function scriptVeille() {
  const lignes = yml.split('\n');
  const i = lignes.findIndex(l => /^\s*- name: Veille\s*$/.test(l));
  assert.ok(i >= 0, 'étape « Veille » introuvable dans le YAML');
  assert.match(lignes[i + 1], /^\s+run: \|\s*$/, 'l\'étape doit être un bloc `run: |`');
  const indent = lignes[i + 1].match(/^\s+/)[0].length + 2;
  const corps = [];
  for (let j = i + 2; j < lignes.length; j++) {
    const l = lignes[j];
    if (l.trim() !== '' && l.search(/\S/) < indent) break;
    corps.push(l.slice(indent));
  }
  return corps.join('\n');
}
const SCRIPT = scriptVeille();

const FAUX_GH = `#!/bin/bash
echo "gh $*" >> "$LOG"
S="$SCENARIO"
vrai(){ [ "$(jq -r "$1" "$S")" = true ]; }
if [ "$1 $2" = "workflow run" ]; then
  if [ "$3" = "veilleur.yml" ]; then vrai .releve_refusee && exit 1; exit 0; fi
  vrai .lancement_refuse && exit 1
  touch "$STATE/lance"; exit 0
fi
[ "$1" = "api" ] || exit 2
url="$2"; expr=""; [ "$3" = "--jq" ] && expr="$4"
sortie(){ if [ -n "$expr" ]; then jq -r "$expr"; else cat; fi; }
case "$url" in
  *"status=success&per_page=1"*)
    vrai .erreur_succes && exit 1
    jq '{workflow_runs: (if .dernier_succes then [{run_started_at: .dernier_succes}] else [] end)}' "$S" | sortie ;;
  *"event=workflow_dispatch"*)
    if [ -f "$STATE/lance" ]; then jq '{workflow_runs: [{id: .lance.id, triggering_actor: {login: "github-actions[bot]"}}]}' "$S" | sortie
    else echo '{"workflow_runs": []}' | sortie; fi ;;
  *"/runs?per_page=10"*)
    vrai .erreur_historique && exit 1
    jq '{workflow_runs: .runs}' "$S" | sortie ;;
  *"/runs?per_page=1"*)
    vrai .erreur_historique && exit 1
    jq '{workflow_runs: (.runs | sort_by(.created_at) | reverse | .[0:1])}' "$S" | sortie ;;
  *"/actions/runs/"*)
    id="\${url##*/}"
    jq --arg id "$id" '(([.lance] + .runs) | map(select(.id == ($id|tonumber))) | .[0]) // {} | {status: (.status_final // "completed"), conclusion: (.conclusion_finale // .conclusion)}' "$S" | sortie ;;
  *) echo "url inattendue: $url" >&2; exit 2 ;;
esac
`;

// APPARITION : run du contrôle ajouté à l'historique pendant la première
// attente d'au moins 1000 s (lancement concurrent, AG-040).
const FAUX_SLEEP = `#!/bin/bash
echo "sleep $1" >> "$LOG"
if [ -n "\${APPARITION:-}" ] && [ -f "$APPARITION" ] && [ "$1" -ge 1000 ]; then
  jq --slurpfile r "$APPARITION" '.runs += $r' "$SCENARIO" > "$SCENARIO.tmp" && mv "$SCENARIO.tmp" "$SCENARIO"; rm -f "$APPARITION"
fi
[ "$SLEEP_REEL" = 1 ] && exec /bin/sleep 5
# Horloge simulée (AG-055) : chaque attente avance le faux \`date\`.
if [ "\${HORLOGE:-}" = 1 ]; then o=$(cat "$STATE/decalage" 2>/dev/null || echo 0); echo $(( o + $1 )) > "$STATE/decalage"; fi
exit 0
`;

// Faux \`date\` (HORLOGE=1) : l'heure courante avance des attentes déjà faites ;
// une date explicite (-d) est lue telle quelle.
const FAUX_DATE = `#!/bin/bash
if [ "\${HORLOGE:-}" != 1 ]; then exec /bin/date "$@"; fi
for a in "$@"; do case "$a" in -d|--date|--date=*) exec /bin/date "$@";; esac; done
o=$(cat "$STATE/decalage" 2>/dev/null || echo 0)
exec /bin/date -d "@$(( $(/bin/date +%s) + o ))" "$@"
`;

const iso = (decalageSec) => new Date(Date.now() + decalageSec * 1000).toISOString().replace(/\.\d{3}Z$/, 'Z');

function monter(scenario) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'veilleur-'));
  const bin = path.join(dir, 'bin'); fs.mkdirSync(bin);
  fs.writeFileSync(path.join(bin, 'gh'), FAUX_GH, { mode: 0o755 });
  fs.writeFileSync(path.join(bin, 'sleep'), FAUX_SLEEP, { mode: 0o755 });
  fs.writeFileSync(path.join(bin, 'date'), FAUX_DATE, { mode: 0o755 });
  const state = path.join(dir, 'etat'); fs.mkdirSync(state);
  const sc = path.join(dir, 'scenario.json');
  fs.writeFileSync(sc, JSON.stringify(Object.assign({ runs: [], lance: { id: 900, conclusion: 'success' } }, scenario)));
  const log = path.join(dir, 'log'); fs.writeFileSync(log, '');
  const env = Object.assign({}, process.env, {
    PATH: bin + ':' + process.env.PATH, LOG: log, SCENARIO: sc, STATE: state,
    GH_TOKEN: 'faux', R: 'org/depot', REF: 'main',
    PASSAGES: '1', INTERVALLE: '60', SEUIL: '125', ATTENTE: '0', CIBLE: 'integrite-api.yml',
  });
  return { dir, env, log, scenario: sc };
}

function lancer(scenario, envPlus, apparition) {
  const m = monter(scenario);
  Object.assign(m.env, envPlus || {});
  if (apparition) { const f = path.join(m.dir, 'apparition.json'); fs.writeFileSync(f, JSON.stringify(apparition)); m.env.APPARITION = f; }
  const r = spawnSync('bash', ['-c', SCRIPT], { env: m.env, encoding: 'utf8', timeout: 20000 });
  const journal = fs.readFileSync(m.log, 'utf8').split('\n').filter(Boolean);
  return Object.assign(m, { code: r.status, out: (r.stdout || '') + (r.stderr || ''), journal });
}

const indice = (journal, motif) => journal.findIndex(l => motif.test(l));
const lancementControle = j => indice(j, /^gh workflow run integrite-api\.yml/);
const releves = j => j.filter(l => /^gh workflow run veilleur\.yml/.test(l));
const dormirAvant = (j, fin) => j.slice(0, fin).filter(l => /^sleep /.test(l)).map(l => Number(l.split(' ')[1]));

test('après un échec récent, le contrôle n\'est relancé qu\'au bout de l\'intervalle (boucle du 31/10 → 02/11)', () => {
  const r = lancer({
    dernier_succes: iso(-3 * 3600),
    runs: [{ id: 5, status: 'completed', conclusion: 'failure', created_at: iso(-5) }],
    lance: { id: 900, conclusion: 'failure' },
  });
  const l = lancementControle(r.journal);
  assert.ok(l >= 0, 'un passage doit finir par être lancé\n' + r.out);
  const attentes = dormirAvant(r.journal, l);
  assert.ok(attentes.some(s => s >= 3500 && s <= 3600), 'attente d\'environ 60 min avant le lancement, vu : ' + JSON.stringify(attentes) + '\n' + r.out);
});

test('un échec demande une relève qui porte l\'attente et le seuil', () => {
  const r = lancer({ lance: { id: 900, conclusion: 'failure' } }, { SEUIL: '200' });
  assert.equal(r.code, 1, r.out);
  const rel = releves(r.journal);
  assert.equal(rel.length, 1, r.out);
  assert.match(rel[0], /-f attente=60\b/);
  assert.match(rel[0], /-f seuil=200\b/);
  assert.match(rel[0], /-f intervalle=60\b/);
});

test('lancement refusé : échec rouge, relève espacée, un seul essai', () => {
  const r = lancer({ lancement_refuse: true });
  assert.equal(r.code, 1, r.out);
  assert.match(r.out, /lancement du contrôle refusé/);
  const rel = releves(r.journal);
  assert.equal(rel.length, 1);
  assert.match(rel[0], /-f attente=60\b/);
  assert.equal(r.journal.filter(l => /^gh workflow run integrite-api\.yml/.test(l)).length, 1);
});

test('historique illisible : échec rouge, relève espacée, aucun lancement', () => {
  const r = lancer({ erreur_succes: true });
  assert.equal(r.code, 1, r.out);
  assert.equal(lancementControle(r.journal), -1, 'aucun contrôle ne doit être lancé');
  const rel = releves(r.journal);
  assert.equal(rel.length, 1);
  assert.match(rel[0], /-f attente=60\b/);
  const r2 = lancer({ erreur_historique: true });
  assert.equal(r2.code, 1, r2.out);
  assert.equal(lancementControle(r2.journal), -1);
  assert.match(releves(r2.journal)[0] || '', /-f attente=60\b/);
});

test('un successeur à attente imposée attend avant tout lancement', () => {
  const r = lancer({}, { ATTENTE: '60' });
  const l = lancementControle(r.journal);
  assert.ok(l >= 0, r.out);
  const attentes = dormirAvant(r.journal, l);
  assert.equal(attentes[0], 3600, 'la première attente est celle de la relève : ' + JSON.stringify(attentes));
  assert.match(r.out, /attente imposée après un échec : 60 min/);
});

test('un contrôle déjà en cours est suivi, jamais doublé', () => {
  const r = lancer({
    runs: [{ id: 7, status: 'in_progress', conclusion: null, status_final: 'completed', conclusion_finale: 'success', created_at: iso(-60) }],
  });
  assert.equal(r.code, 0, r.out);
  assert.equal(lancementControle(r.journal), -1, 'aucun nouveau contrôle');
  assert.match(r.out, /contrôle 7 déjà en cours/);
  assert.ok(r.journal.some(l => /^gh api repos\/org\/depot\/actions\/runs\/7/.test(l)), 'le contrôle en cours est suivi');
  assert.equal(releves(r.journal).length, 1);
  assert.match(releves(r.journal)[0], /-f attente=0\b/, 'relève normale : aucune attente imposée');
});

test('l\'alerte de rupture reste fondée sur le dernier succès, pas sur le dernier essai', () => {
  // Dernier essai récent (échoué) mais dernier succès vieux de 5 h : le passage
  // de reprise réussit, la rupture est quand même signalée en rouge.
  const r = lancer({
    dernier_succes: iso(-300 * 60),
    runs: [{ id: 5, status: 'completed', conclusion: 'failure', created_at: iso(-120) }],
    lance: { id: 900, conclusion: 'success' },
  });
  assert.equal(r.code, 1, r.out);
  assert.match(r.out, /surveillance interrompue : aucun contrôle réussi pendant 30\d min/);
  assert.match(r.out, /contrôle de reprise \(900\) est réussi/);
  assert.match(releves(r.journal)[0] || '', /-f attente=60\b/);
});

test('sans rupture ni échec, la relève planifiée n\'impose aucune attente', () => {
  const r = lancer({ dernier_succes: iso(-10 * 60) });
  assert.equal(r.code, 0, r.out);
  assert.match(releves(r.journal)[0] || '', /-f attente=0\b/);
  assert.match(releves(r.journal)[0] || '', /-f seuil=125\b/);
});

test('deux échecs successifs : un seul passage par intervalle, chaîne comprise', () => {
  const premier = lancer({ dernier_succes: iso(-5 * 3600), runs: [{ id: 5, status: 'completed', conclusion: 'failure', created_at: iso(-3 * 3600) }], lance: { id: 900, conclusion: 'failure' } });
  assert.equal(premier.code, 1, premier.out);
  const attente = Number((releves(premier.journal)[0].match(/-f attente=(\d+)/) || [])[1]);
  assert.equal(attente, 60);
  // Le successeur reçoit l'attente de la relève ; le premier passage figure maintenant à l'historique.
  const second = lancer({ dernier_succes: iso(-5 * 3600), runs: [{ id: 900, status: 'completed', conclusion: 'failure', created_at: iso(-2) }], lance: { id: 901, conclusion: 'failure' } }, { ATTENTE: String(attente) });
  assert.equal(second.code, 1, second.out);
  const l = lancementControle(second.journal);
  assert.ok(l >= 0, second.out);
  assert.equal(dormirAvant(second.journal, l)[0], 3600);
  assert.match(releves(second.journal)[0], /-f attente=60\b/, 'et l\'échec suivant espace encore le suivant');
});

test('annulation pendant l\'attente imposée : sortie 130, aucun lancement, aucune relève', async () => {
  const m = monter({});
  Object.assign(m.env, { ATTENTE: '60', SLEEP_REEL: '1' });
  const enfant = spawn('bash', ['-c', SCRIPT], { env: m.env });
  let out = ''; enfant.stdout.on('data', d => out += d); enfant.stderr.on('data', d => out += d);
  await new Promise(r => setTimeout(r, 600));
  const t0 = Date.now();
  enfant.kill('SIGTERM');
  // « exit » et non « close » : l'attente orpheline garde les tubes ouverts quelques secondes.
  const code = await new Promise(r => enfant.on('exit', c => r(c)));
  const journal = fs.readFileSync(m.log, 'utf8');
  assert.equal(code, 130, out);
  assert.ok(Date.now() - t0 < 2500, 'la sortie doit être immédiate, pas à la fin de l\'attente');
  assert.match(out, /veilleur annulé : pas de relève/);
  assert.doesNotMatch(journal, /gh workflow run/);
});

test('le YAML déclare l\'entrée `attente`, la variable ATTENTE et propage le seuil', () => {
  assert.match(yml, /^\s+attente:\n\s+description:.*\n\s+default: "0"/m);
  assert.match(yml, /ATTENTE: \$\{\{ inputs\.attente \|\| '0' \}\}/);
  assert.match(SCRIPT, /-f seuil="\$SEUIL"/);
  assert.match(SCRIPT, /-f attente="\$\{1:-0\}"/);
  assert.match(SCRIPT, /echec\(\)\{ echo "::error::\$\*"; releve "\$INTERVALLE"; exit 1; \}/);
});

// --- AG-040 (Codex, 03/11/2026) : deux écarts démontrés ---------------------
test('successeur à attente imposée dont l\'historique reste illisible : il attend avant sa relève, sans lancer', () => {
  const r = lancer({ erreur_succes: true }, { ATTENTE: '60' });
  assert.equal(r.code, 1, r.out);
  const rel = r.journal.findIndex(l => /^gh workflow run veilleur\.yml/.test(l));
  assert.ok(rel >= 0, 'une relève est demandée');
  assert.deepEqual(dormirAvant(r.journal, rel), [3600], 'l\'attente de 60 min précède la relève');
  assert.equal(lancementControle(r.journal), -1);
});

test('contrôle apparu pendant l\'attente de cadence : suivi, pas doublé', () => {
  const r = lancer(
    { dernier_succes: iso(-30 * 60), runs: [{ id: 5, status: 'completed', conclusion: 'success', created_at: iso(-10) }] },
    {},
    { id: 777, status: 'in_progress', conclusion: null, status_final: 'completed', conclusion_finale: 'success', created_at: iso(0) },
  );
  assert.equal(r.code, 0, r.out);
  assert.equal(lancementControle(r.journal), -1, 'aucun nouveau contrôle');
  assert.match(r.out, /contrôle 777 déjà en cours/);
  assert.ok(r.journal.filter(l => /runs\?per_page=10/.test(l)).length >= 2, 'l\'état est relu après l\'attente');
});

test('contrôle terminé apparu pendant l\'attente : le délai repart de ce lancement, puis un seul lancement', () => {
  const r = lancer(
    { dernier_succes: iso(-30 * 60), runs: [{ id: 5, status: 'completed', conclusion: 'success', created_at: iso(-10) }] },
    {},
    { id: 778, status: 'completed', conclusion: 'success', created_at: iso(0) },
  );
  assert.equal(r.code, 0, r.out);
  const l = lancementControle(r.journal);
  assert.ok(l >= 0, r.out);
  const attentes = dormirAvant(r.journal, l).filter(s => s >= 1000);
  assert.equal(attentes.length, 2, 'deux attentes : une pour le n° 5, une pour le n° 778 — vu ' + JSON.stringify(attentes));
  assert.equal(r.journal.filter(x => /^gh workflow run integrite-api\.yml/.test(x)).length, 1);
});

// --- AG-055 (13/11/2026) : relève avant la limite de GitHub ------------------
test('budget presque épuisé avant une attente de cadence : relève anticipée sans attente, aucun lancement', () => {
  const r = lancer({ dernier_succes: iso(-10 * 60), runs: [{ id: 5, status: 'completed', conclusion: 'success', created_at: iso(-10) }] }, { BUDGET: '40' });
  assert.equal(r.code, 0, r.out);
  assert.match(r.out, /relève anticipée/);
  assert.equal(lancementControle(r.journal), -1, 'aucun contrôle lancé');
  assert.deepEqual(dormirAvant(r.journal, r.journal.length).filter(x => x >= 1000), [], 'aucune longue attente');
  const rel = releves(r.journal);
  assert.equal(rel.length, 1);
  assert.match(rel[0], /-f attente=0\b/);
});

test('budget suffisant : la même attente de cadence a lieu, puis un seul lancement', () => {
  const r = lancer({ dernier_succes: iso(-10 * 60), runs: [{ id: 5, status: 'completed', conclusion: 'success', created_at: iso(-10) }] }, { BUDGET: '120' });
  assert.equal(r.code, 0, r.out);
  assert.doesNotMatch(r.out, /relève anticipée/);
  assert.equal(r.journal.filter(l => /^gh workflow run integrite-api\.yml/.test(l)).length, 1);
});

test('le budget par défaut laisse 5 min sous timeout-minutes', () => {
  const t = Number((yml.match(/timeout-minutes: (\d+)/) || [])[1]);
  const b = Number((yml.match(/BUDGET: '(\d+)'/) || [])[1]);
  assert.ok(t > 0 && b > 0 && b <= t - 5, `budget ${b} / timeout ${t}`);
});

test('cycle complet après une attente imposée (horloge simulée) : relève avant 340 min, jamais tué par GitHub', () => {
  // Rejeu de Codex (AG-055) : ATTENTE 60, 5 passages ; sans garde finale, l'attente visait la minute 359.
  const r = lancer({}, { HORLOGE: '1', ATTENTE: '60', PASSAGES: '5', INTERVALLE: '60', BUDGET: '335' });
  assert.equal(r.code, 0, r.out);
  const rel = r.journal.findIndex(l => /^gh workflow run veilleur\.yml/.test(l));
  assert.ok(rel >= 0, 'une relève est demandée\n' + r.out);
  const total = dormirAvant(r.journal, rel).reduce((a, b) => a + b, 0);
  assert.ok(total <= 335 * 60, `relève à ${Math.round(total / 60)} min, au-delà du budget`);
  assert.match(releves(r.journal)[0], /-f attente=0\b/);
});
