// Essais de la requête-témoin du contrôle d'intégrité (AG-039, étape 2,
// 03/11/2026). Les scripts réellement publiés sont extraits de
// integrite-api.yml : l'étape « Requête-témoin » (côté GitHub) avec un faux
// `curl`, et le script envoyé au serveur (heredoc FIN) avec un faux dossier
// personnel et des journaux HTTP fictifs. Aucun appel réseau, aucune donnée
// réelle. Adresse des journaux fictifs : 203.0.113.7 (bloc réservé à la doc).
// Cible alternative, pour vérifier un test par mutation : INTEGRITE_YML=<fichier>.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const YML = process.env.INTEGRITE_YML || path.join(__dirname, '.github', 'workflows', 'integrite-api.yml');
const yml = fs.readFileSync(YML, 'utf8');
const lignes = yml.split('\n');

function etape(nom) {
  const i = lignes.findIndex(l => l.trim() === '- name: ' + nom);
  assert.ok(i >= 0, 'étape « ' + nom + ' » introuvable');
  const j = lignes.findIndex((l, k) => k > i && /^\s+run: \|\s*$/.test(l));
  const indent = lignes[j].search(/\S/) + 2;
  const corps = [];
  for (let k = j + 1; k < lignes.length; k++) {
    if (lignes[k].trim() !== '' && lignes[k].search(/\S/) < indent) break;
    corps.push(lignes[k].slice(indent));
  }
  return corps.join('\n');
}
function scriptDistant() {
  const i = lignes.findIndex(l => l.includes("'bash -s' <<'FIN'"));
  assert.ok(i >= 0, 'script distant (heredoc FIN) introuvable');
  const indent = lignes[i + 1].search(/\S/);
  const corps = [];
  for (let k = i + 1; k < lignes.length; k++) {
    if (lignes[k].trim() === 'FIN') break;
    corps.push(lignes[k].slice(indent));
  }
  return corps.join('\n');
}
const TEMOIN_GH = etape('Requête-témoin');
const DISTANT = scriptDistant();

const cmd = (c) => spawnSync('bash', ['-c', c], { encoding: 'utf8', env: Object.assign({}, process.env, { LC_ALL: 'C' }) }).stdout.trim();
const AUJ = cmd('date +%d/%b/%Y'), HIER = cmd('date -d yesterday +%d/%b/%Y'), AVANT_HIER = cmd('date -d "2 days ago" +%d/%b/%Y');
const DEPUIS_HIER = cmd('date -d yesterday +%F'), DEPUIS_AVANT_HIER = cmd('date -d "2 days ago" +%F'), AVANT_HIER_ISO = DEPUIS_AVANT_HIER;
const MARQUE = 't20261103102830-123456789-1-a1b2c3d4e5f6';
const ligne = (jour, req, code) => `203.0.113.7 - - [${jour}:10:28:30 +0100] "${req} HTTP/1.1" ${code} 12 "-" "curl/8.5.0"`;
const temoin = (m = MARQUE, code = 200, jour = AUJ) => ligne(jour, 'GET /api/index.php?action=logLogin&integrite=' + m, code);
const ordinaire = (jour) => ligne(jour, 'POST /api/index.php', 200);

function outils(bin, extra) {
  const ecrire = (n, c) => fs.writeFileSync(path.join(bin, n), c, { mode: 0o755 });
  ecrire('sleep', `#!/bin/bash
echo "sleep $1" >> "$LOG"
if [ -n "\${DIFFERE:-}" ] && [ -f "$DIFFERE" ]; then cat "$DIFFERE" >> "$JOURNAL"; rm -f "$DIFFERE"; fi
exit 0
`);
  ecrire('php', '#!/bin/bash\nexit 1\n');
  Object.entries(extra || {}).forEach(([n, c]) => ecrire(n, c));
}

// Script distant, dans un faux dossier personnel avec un journal HTTP du jour.
function distant({ journal = [], temoinEnv = MARQUE, depuis = DEPUIS_HIER, differe = null }) {
  const home = fs.mkdtempSync(path.join(os.tmpdir(), 'integrite-'));
  const bin = path.join(home, '.bin'); fs.mkdirSync(bin);
  outils(bin);
  fs.mkdirSync(path.join(home, 'www', 'api'), { recursive: true });
  fs.mkdirSync(path.join(home, 'admin', 'logs'), { recursive: true });
  const fichier = path.join(home, 'admin', 'logs', 'ateliers_http.log');
  fs.writeFileSync(fichier, journal.length ? journal.join('\n') + '\n' : '');
  const log = path.join(home, 'appels'); fs.writeFileSync(log, '');
  const env = Object.assign({}, process.env, { HOME: home, PATH: bin + ':' + process.env.PATH, LOG: log, JOURNAL: fichier, DEPUIS: depuis, TEMOIN: temoinEnv });
  if (differe) { const f = path.join(home, 'differe'); fs.writeFileSync(f, differe + '\n'); env.DIFFERE = f; }
  const r = spawnSync('bash', ['-s'], { input: DISTANT, env, encoding: 'utf8', timeout: 30000 });
  const out = (r.stdout || '') + (r.stderr || '');
  return { out, appels: fs.readFileSync(log, 'utf8').split('\n').filter(Boolean) };
}
const impossibleTemoin = out => out.split('\n').filter(l => /^IMPOSSIBLE : (requête-témoin|marque)/.test(l));

test('témoin présent dans les journaux du jour : retrouvé, aucune ligne IMPOSSIBLE à son sujet', () => {
  const r = distant({ journal: [ordinaire(HIER), ordinaire(AUJ), temoin()] });
  assert.match(r.out, /retrouvée : 1 ligne\(s\) conforme\(s\) .* en 1 lecture\(s\)/);
  assert.deepEqual(impossibleTemoin(r.out), []);
});

test('témoin absent : IMPOSSIBLE après 9 lectures, attente bornée', () => {
  const r = distant({ journal: [ordinaire(HIER), ordinaire(AUJ)] });
  assert.deepEqual(impossibleTemoin(r.out).length, 1);
  assert.match(r.out, /^IMPOSSIBLE : requête-témoin absente des journaux HTTP après 9 lecture\(s\)/m);
  assert.equal(r.appels.filter(l => l === 'sleep 10').length, 8, 'huit attentes de 10 s entre neuf lectures');
});

test('témoin ancien ou d\'une autre marque : IMPOSSIBLE (la marque est neuve à chaque passage)', () => {
  for (const autre of ['t20261102091500-111-1-ffffffffffff', MARQUE + '0', MARQUE.slice(0, -1)]) {
    const r = distant({ journal: [ordinaire(HIER), temoin(autre)] });
    assert.match(r.out, /^IMPOSSIBLE : requête-témoin absente/m, 'marque ' + autre);
  }
});

test('témoin journalisé sous une forme inattendue (statut 500, POST, date trop ancienne) : IMPOSSIBLE', () => {
  const cas = [
    temoin(MARQUE, 500),
    ligne(AUJ, 'POST /api/index.php?action=logLogin&integrite=' + MARQUE, 200),
    temoin(MARQUE, 200, AVANT_HIER),
  ];
  for (const l of cas) {
    const r = distant({ journal: [ordinaire(HIER), l] });
    assert.match(r.out, /^IMPOSSIBLE : requête-témoin journalisée sous une forme inattendue/m, l);
  }
});

test('journal écrit avec retard : témoin retrouvé à la lecture suivante', () => {
  const r = distant({ journal: [ordinaire(HIER), ordinaire(AUJ)], differe: temoin() });
  assert.match(r.out, /retrouvée : 1 ligne\(s\) conforme\(s\) .* en 2 lecture\(s\)/);
  assert.deepEqual(impossibleTemoin(r.out), []);
});

test('témoin non envoyé : le script distant ne le cherche pas, l\'étape finale l\'impose IMPOSSIBLE', () => {
  const r = distant({ journal: [ordinaire(HIER), temoin()], temoinEnv: '' });
  assert.match(r.out, /non envoyée \(cause à l'étape « Requête-témoin »\)/);
  assert.equal(r.appels.filter(l => l.startsWith('sleep')).length, 0);
  const final = etape('Fichiers, configuration, accès, comptes et journaux');
  assert.match(final, /\[ -n "\$\{TEMOIN:-\}" \] \|\| echo "IMPOSSIBLE : requête-témoin non envoyée \(\$\{TEMOIN_ECHEC:-étape non exécutée\}\)" >> rapport\.txt/);
  assert.match(final, /DEPUIS="\$DEPUIS" TEMOIN="\$\{TEMOIN:-\}" 'bash -s'/);
});

test('marque transmise invalide (injection) : IMPOSSIBLE, rien n\'est cherché', () => {
  const r = distant({ journal: [temoin()], temoinEnv: 'x;touch /tmp/pwn' });
  assert.match(r.out, /^IMPOSSIBLE : marque de la requête-témoin invalide/m);
  assert.equal(r.appels.filter(l => l.startsWith('sleep')).length, 0);
});

test('une anomalie réelle reste rouge malgré un témoin valide', () => {
  const r = distant({ journal: [ordinaire(HIER), temoin(), ligne(AUJ, 'GET /index.php?x=php://input', 404)] });
  assert.match(r.out, /retrouvée : 1 ligne\(s\) conforme\(s\)/);
  assert.match(r.out, /^ALERTE : 1 requête\(s\) avec un motif d'exploitation connu/m);
});

test('un jour clos vide reste IMPOSSIBLE même avec un témoin du jour (aucune tolérance rétroactive)', () => {
  const r = distant({ journal: [ordinaire(HIER), temoin()], depuis: DEPUIS_AVANT_HIER });
  assert.match(r.out, new RegExp('^IMPOSSIBLE : aucune requête journalisée le ' + AVANT_HIER_ISO + ' : couverture non établie', 'm'));
  assert.match(r.out, /retrouvée : 1 ligne\(s\) conforme\(s\)/);
});

test('le rapport ne montre ni l\'adresse IP ni la requête brute du témoin', () => {
  const r = distant({ journal: [ordinaire(HIER), temoin()] });
  assert.doesNotMatch(r.out, /203\.0\.113\.7/);
  assert.doesNotMatch(r.out, /action=logLogin&integrite=/);
});

// --- Étape GitHub « Requête-témoin » avec un faux curl -----------------------
function cote_github({ code = '200', corps = '{"ok":true}', sortie = 0 } = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'temoin-'));
  const bin = path.join(dir, 'bin'); fs.mkdirSync(bin);
  fs.writeFileSync(path.join(bin, 'curl'), `#!/bin/bash
echo "curl $*" >> "$LOG"
o=""; while [ $# -gt 0 ]; do [ "$1" = "-o" ] && { o="$2"; shift; }; shift; done
[ -n "$o" ] && printf '%s' "$CORPS" > "$o"
printf '%s' "$CODE"
exit "$SORTIE"
`, { mode: 0o755 });
  const envFile = path.join(dir, 'github_env'); fs.writeFileSync(envFile, '');
  const log = path.join(dir, 'appels'); fs.writeFileSync(log, '');
  const env = Object.assign({}, process.env, { PATH: bin + ':' + process.env.PATH, LOG: log, GITHUB_ENV: envFile, GITHUB_RUN_ID: '123456789', GITHUB_RUN_ATTEMPT: '1', CODE: code, CORPS: corps, SORTIE: String(sortie) });
  const r = spawnSync('bash', ['-c', TEMOIN_GH], { cwd: dir, env, encoding: 'utf8', timeout: 20000 });
  return { code: r.status, out: (r.stdout || '') + (r.stderr || ''), env: fs.readFileSync(envFile, 'utf8'), appels: fs.readFileSync(log, 'utf8'), reste: fs.existsSync(path.join(dir, 'temoin.json')) };
}

test('réponse HTTP 200 et ok:true : marque neuve transmise, réponse jamais affichée', () => {
  const a = cote_github({ corps: '{"ok":true,"secret":"ne-pas-afficher"}' });
  const b = cote_github();
  assert.equal(a.code, 0, a.out);
  const m = (a.env.match(/^TEMOIN=(.+)$/m) || [])[1];
  assert.match(m || '', /^t\d{14}-123456789-1-[0-9a-f]{12}$/);
  assert.notEqual(m, (b.env.match(/^TEMOIN=(.+)$/m) || [])[1], 'deux passages, deux marques');
  assert.doesNotMatch(a.env, /TEMOIN_ECHEC/);
  assert.doesNotMatch(a.out, /ne-pas-afficher/);
  assert.equal(a.reste, false, 'la réponse est effacée');
  assert.match(a.appels, /--max-time 20 https:\/\/ateliers-numeriques\.alwaysdata\.net\/api\/index\.php\?action=logLogin&integrite=t\d{14}-/);
  assert.doesNotMatch(a.appels, /token|password|jeton/i);
});

test('échecs de la requête-témoin : cause transmise, jamais de marque, l\'étape ne s\'arrête pas', () => {
  const cas = [
    [{ code: '403' }, /^TEMOIN_ECHEC=réponse HTTP 403$/m],
    [{ code: '500', corps: '{"ok":false}' }, /^TEMOIN_ECHEC=réponse HTTP 500$/m],
    [{ corps: '{"ok":false,"error":"Erreur serveur"}' }, /^TEMOIN_ECHEC=réponse sans ok:true$/m],
    [{ corps: 'pas du json' }, /^TEMOIN_ECHEC=réponse sans ok:true$/m],
    [{ code: '000', sortie: 28 }, /^TEMOIN_ECHEC=réponse HTTP 000$/m],
  ];
  for (const [p, attendu] of cas) {
    const r = cote_github(p);
    assert.equal(r.code, 0, JSON.stringify(p) + r.out);
    assert.match(r.env, attendu, JSON.stringify(p));
    assert.doesNotMatch(r.env, /^TEMOIN=/m, JSON.stringify(p));
  }
});
