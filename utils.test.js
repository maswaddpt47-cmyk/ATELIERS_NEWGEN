// Tests unitaires — runner : node --test utils.test.js
const {describe,it} = require('node:test');
const assert = require('node:assert/strict');
const {
  normCommune, normalizeCommune,
  stripAccents, htmlEsc,
  normalizeDate, normalizeHoraire, fmtDate, fmtCardDate,
  escapeICS, foldICSLine, parseHoraireICS, parseDateICS, buildICS,
} = require('./utils.js');

// ── normCommune ────────────────────────────────────────────
describe('normCommune', () => {
  it('retire le code postal entre parenthèses', () => {
    assert.equal(normCommune('AGEN (47000)'), 'AGEN');
  });
  it('retire plusieurs espaces autour du code', () => {
    assert.equal(normCommune('NERAC  (47600) '), 'NERAC');
  });
  it('laisse intact un nom sans code postal', () => {
    assert.equal(normCommune('VILLENEUVE SUR LOT'), 'VILLENEUVE SUR LOT');
  });
  it('retourne chaîne vide sur entrée vide', () => {
    assert.equal(normCommune(''), '');
    assert.equal(normCommune(null), '');
  });
});

// ── normalizeCommune ───────────────────────────────────────
describe('normalizeCommune', () => {
  it('corrige TEMPLE SUR LOT → LE TEMPLE SUR LOT', () => {
    assert.equal(normalizeCommune('TEMPLE SUR LOT'), 'LE TEMPLE SUR LOT');
  });
  it('corrige VILLENEUVE-SUR-LOT → VILLENEUVE SUR LOT', () => {
    assert.equal(normalizeCommune('VILLENEUVE-SUR-LOT'), 'VILLENEUVE SUR LOT');
  });
  it('laisse intact un nom non listé', () => {
    assert.equal(normalizeCommune('AGEN'), 'AGEN');
  });
  it('gère null et undefined', () => {
    assert.equal(normalizeCommune(null), '');
    assert.equal(normalizeCommune(undefined), '');
  });
});

// ── stripAccents ───────────────────────────────────────────
describe('stripAccents', () => {
  it('supprime les accents et passe en minuscules', () => {
    assert.equal(stripAccents('Réalisé'), 'realise');
    assert.equal(stripAccents('Thématique'), 'thematique');
    assert.equal(stripAccents('Écriture'), 'ecriture');
  });
  it('retourne chaîne vide sur entrée vide', () => {
    assert.equal(stripAccents(''), '');
    assert.equal(stripAccents(null), '');
  });
});

// ── htmlEsc ────────────────────────────────────────────────
describe('htmlEsc', () => {
  it('échappe les caractères HTML spéciaux', () => {
    assert.equal(htmlEsc('<script>'), '&lt;script&gt;');
    assert.equal(htmlEsc('a & b'), 'a &amp; b');
    assert.equal(htmlEsc('<b>texte</b>'), '&lt;b&gt;texte&lt;/b&gt;');
  });
  it('laisse intact un texte sans caractères spéciaux', () => {
    assert.equal(htmlEsc('Atelier numérique'), 'Atelier numérique');
  });
  it('gère null', () => {
    assert.equal(htmlEsc(null), '');
  });
});

// ── normalizeDate ──────────────────────────────────────────
describe('normalizeDate', () => {
  it('accepte le format ISO YYYY-MM-DD tel quel', () => {
    assert.equal(normalizeDate('2026-06-16'), '2026-06-16');
  });
  it('extrait la date d\'un datetime ISO', () => {
    assert.equal(normalizeDate('2026-06-16T09:00:00'), '2026-06-16');
  });
  it('convertit le format français DD/MM/YYYY', () => {
    assert.equal(normalizeDate('16/06/2026'), '2026-06-16');
  });
  it('retourne chaîne vide sur entrée vide', () => {
    assert.equal(normalizeDate(''), '');
    assert.equal(normalizeDate(null), '');
  });
});

// ── normalizeHoraire ───────────────────────────────────────
describe('normalizeHoraire', () => {
  it('convertit le format 9H00 en 9:00', () => {
    assert.equal(normalizeHoraire('9H00'), '9:00');
  });
  it('convertit le format 14h30 en 14:30', () => {
    assert.equal(normalizeHoraire('14h30'), '14:30');
  });
  it('accepte HH:MM tel quel', () => {
    assert.equal(normalizeHoraire('10:30'), '10:30');
  });
  it('extrait l\'horaire d\'un datetime ISO', () => {
    assert.equal(normalizeHoraire('2026-06-16T09:30:00'), '09:30');
  });
  it('retourne chaîne vide sur entrée vide', () => {
    assert.equal(normalizeHoraire(''), '');
    assert.equal(normalizeHoraire(null), '');
  });
});

// ── fmtDate ────────────────────────────────────────────────
describe('fmtDate', () => {
  it('formate 2026-06-16 (mardi) correctement', () => {
    assert.equal(fmtDate('2026-06-16'), 'Mar 16/06/2026');
  });
  it('retourne chaîne vide sur entrée vide', () => {
    assert.equal(fmtDate(''), '');
    assert.equal(fmtDate(null), '');
  });
});

// ── fmtCardDate ────────────────────────────────────────────
describe('fmtCardDate', () => {
  it('retourne les composants séparés pour 2026-06-16', () => {
    const r = fmtCardDate('2026-06-16');
    assert.equal(r.day, '16');
    assert.equal(r.month, 'Jun');
    assert.equal(r.jour, 'Mar');
  });
  it('retourne un objet vide sur entrée vide', () => {
    const r = fmtCardDate('');
    assert.equal(r.day, '');
    assert.equal(r.month, '');
    assert.equal(r.jour, '');
  });
});

// ── escapeICS ──────────────────────────────────────────────
describe('escapeICS', () => {
  it('échappe les virgules, points-virgules et backslashes', () => {
    assert.equal(escapeICS('a,b'), 'a\\,b');
    assert.equal(escapeICS('a;b'), 'a\\;b');
    assert.equal(escapeICS('a\\b'), 'a\\\\b');
  });
  it('remplace les sauts de ligne par \\n ICS', () => {
    assert.equal(escapeICS('ligne1\nligne2'), 'ligne1\\nligne2');
  });
  it('gère null', () => {
    assert.equal(escapeICS(null), '');
  });
});

// ── foldICSLine ────────────────────────────────────────────
describe('foldICSLine', () => {
  it('laisse intact une ligne de 75 caractères ou moins', () => {
    const line = 'A'.repeat(75);
    assert.equal(foldICSLine(line), line);
  });
  it('découpe une ligne de 76 caractères', () => {
    const line = 'A'.repeat(76);
    const folded = foldICSLine(line);
    assert.ok(folded.includes('\r\n '), 'doit contenir CRLF + espace');
    assert.equal(folded.split('\r\n')[0].length, 75);
  });
  it('découpe correctement une longue ligne en plusieurs segments', () => {
    const line = 'X'.repeat(200);
    const parts = foldICSLine(line).split('\r\n');
    assert.equal(parts[0].length, 75);
    parts.slice(1).forEach(p => assert.ok(p.startsWith(' ')));
  });
});

// ── parseHoraireICS ────────────────────────────────────────
describe('parseHoraireICS', () => {
  it('parse 9H00 → hh:09 mm:00', () => {
    assert.deepEqual(parseHoraireICS('9H00'), {hh:'09', mm:'00'});
  });
  it('parse 14H30 → hh:14 mm:30', () => {
    assert.deepEqual(parseHoraireICS('14H30'), {hh:'14', mm:'30'});
  });
  it('parse 9:30 (format avec deux-points)', () => {
    assert.deepEqual(parseHoraireICS('9:30'), {hh:'09', mm:'30'});
  });
  it('retourne 09:00 par défaut sur entrée vide', () => {
    assert.deepEqual(parseHoraireICS(''), {hh:'09', mm:'00'});
    assert.deepEqual(parseHoraireICS(null), {hh:'09', mm:'00'});
  });
});

// ── parseDateICS ───────────────────────────────────────────
describe('parseDateICS', () => {
  it('parse 2026-06-16 correctement', () => {
    assert.deepEqual(parseDateICS('2026-06-16'), {y:'2026', mo:'06', j:'16'});
  });
  it('retourne null sur entrée invalide', () => {
    assert.equal(parseDateICS(''), null);
    assert.equal(parseDateICS(null), null);
    assert.equal(parseDateICS('16/06/2026'), null);
  });
});

// ── buildICS ───────────────────────────────────────────────
describe('buildICS', () => {
  const evt = {
    _id: 'test-001',
    date: '2026-06-16',
    horaire: '9H00',
    thematique: 'Initiation smartphone',
    commune: 'AGEN',
    lieu: 'Médiathèque',
    orienteur: 'CCAS Agen',
    conseiller: 'Michel Aswad',
    statut: 'Planifié',
    public: 'Seniors',
    inscrits: 8,
    presents: '',
    remarques: '',
  };

  it('produit un fichier ICS valide avec les balises obligatoires', () => {
    const ics = buildICS([evt]);
    assert.ok(ics.startsWith('BEGIN:VCALENDAR'));
    assert.ok(ics.includes('BEGIN:VEVENT'));
    assert.ok(ics.includes('END:VEVENT'));
    assert.ok(ics.endsWith('END:VCALENDAR\r\n'));
  });
  it('contient DTSTART et DTEND corrects', () => {
    const ics = buildICS([evt]);
    assert.ok(ics.includes('DTSTART:20260616T090000'));
    assert.ok(ics.includes('DTEND:20260616T100000'));
  });
  it('contient le bon SUMMARY', () => {
    const ics = buildICS([evt]);
    assert.ok(ics.includes('SUMMARY:Initiation smartphone | AGEN'));
  });
  it('contient le bon UID', () => {
    const ics = buildICS([evt]);
    assert.ok(ics.includes('UID:test-001@ateliers-newgen'));
  });
  it('ignore les événements sans date valide', () => {
    const ics = buildICS([{...evt, date: ''}]);
    assert.ok(!ics.includes('BEGIN:VEVENT'));
  });
  it('gère une liste vide', () => {
    const ics = buildICS([]);
    assert.ok(!ics.includes('BEGIN:VEVENT'));
    assert.ok(ics.includes('END:VCALENDAR'));
  });
  it('utilise les séparateurs CRLF', () => {
    const ics = buildICS([evt]);
    assert.ok(ics.includes('\r\n'));
  });
  it('DTEND valide pour un atelier à 23H00 — rollover minuit J+1', () => {
    const ics = buildICS([{...evt, date:'2026-06-16', horaire:'23H00'}]);
    assert.ok(ics.includes('DTSTART:20260616T230000'), 'DTSTART doit être 23H00');
    assert.ok(ics.includes('DTEND:20260617T000000'), 'DTEND doit être minuit J+1');
    assert.ok(!ics.includes('T240000'), 'T240000 est invalide RFC 5545 — ne doit pas apparaître');
  });
  it('DTEND valide pour un atelier à 23H00 en fin de mois — rollover 1er du mois suivant', () => {
    const ics = buildICS([{...evt, date:'2026-06-30', horaire:'23H00'}]);
    assert.ok(ics.includes('DTEND:20260701T000000'), 'DTEND doit basculer au 1er juillet');
  });
  it('plusieurs événements → plusieurs VEVENT dans le même fichier', () => {
    const evt2 = {...evt, _id:'test-002', date:'2026-06-17', thematique:'Cybersécurité'};
    const ics = buildICS([evt, evt2]);
    const count = (ics.match(/BEGIN:VEVENT/g) || []).length;
    assert.equal(count, 2);
  });
  it('description contient inscrits quand renseigné', () => {
    const ics = buildICS([{...evt, inscrits:8}]);
    assert.ok(ics.includes('Inscrits : 8'));
  });
  it('description n\'inclut pas inscrits quand vide', () => {
    const ics = buildICS([{...evt, inscrits:'', presents:''}]);
    assert.ok(!ics.includes('Inscrits'));
  });
});

// ── normalizeCommune — cas limites ─────────────────────────
describe('normalizeCommune — strip postal avant lookup', () => {
  it('strip code postal PUIS lookup → "TEMPLE SUR LOT (47)" corrigé', () => {
    assert.equal(normalizeCommune('TEMPLE SUR LOT (47)'), 'LE TEMPLE SUR LOT');
  });
  it('strip code postal PUIS lookup → "VILLENEUVE-SUR-LOT (47300)" corrigé', () => {
    assert.equal(normalizeCommune('VILLENEUVE-SUR-LOT (47300)'), 'VILLENEUVE SUR LOT');
  });
  it('commune inconnue avec code postal → strip seulement', () => {
    assert.equal(normalizeCommune('MARMANDE (47200)'), 'MARMANDE');
  });
  it('commune minuscule → passée en majuscules', () => {
    assert.equal(normalizeCommune('agen'), 'AGEN');
  });
});

// ── normalizeDate — cas limites ────────────────────────────
describe('normalizeDate — cas limites', () => {
  it('nombre entier (date Excel sérialisée) → retourné tel quel (non planté)', () => {
    const r = normalizeDate(45000);
    assert.equal(typeof r, 'string');
  });
  it('format DD/MM/YYYY avec tirets → non reconnu, retourné tel quel', () => {
    const r = normalizeDate('16-06-2026');
    assert.equal(typeof r, 'string');
  });
  it('datetime ISO avec Z → extrait la date', () => {
    assert.equal(normalizeDate('2026-06-16T09:00:00Z'), '2026-06-16');
  });
});

// ── normalizeHoraire — cas limites ─────────────────────────
describe('normalizeHoraire — cas limites', () => {
  it('HH:MM:SS → tronqué à HH:MM', () => {
    assert.equal(normalizeHoraire('09:30:00'), '09:30');
  });
  it('9h00 minuscule → normalisé', () => {
    assert.equal(normalizeHoraire('9h00'), '9:00');
  });
});
