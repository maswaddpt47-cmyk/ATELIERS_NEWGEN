// logic.js — fonctions pures métier testables sous Node.js
// Miroir de la logique embarquée dans les composants React de shared.js.
// Chargé uniquement pour les tests (node --test logic.test.js).

if (typeof require !== 'undefined') {
  var {stripAccents, normCommune} = require('./utils.js');
}
// En contexte navigateur, stripAccents et normCommune sont déjà des globals (utils.js chargé avant)

// ── Matériel ──────────────────────────────────────────────────

// Normalise vers le pipe-string attendu par GAS (pour toute sauvegarde)
function normalizeMateriel(val) {
  if (!val) return '';
  if (Array.isArray(val)) return val.filter(Boolean).join('|');
  const s = String(val).trim();
  if (!s) return '';
  const sep = s.includes('|') ? '|' : ',';
  return s.split(sep).map(v => v.trim()).filter(Boolean).join('|');
}

// Normalise vers Array pour l'affichage et les formulaires
function parseMateriel(val) {
  if (!val) return [];
  if (Array.isArray(val)) return val.filter(Boolean);
  const s = String(val).trim();
  if (!s) return [];
  const sep = s.includes('|') ? '|' : ',';
  return s.split(sep).map(v => v.trim()).filter(Boolean);
}

// ── Validation lot ────────────────────────────────────────────

// Valide une ligne du tableau lot — retourne {} si valide
function validateLotRow(row) {
  const e = {};
  if (!row.date)                     e.date      = 'Requis';
  if (!row.horaire)                  e.horaire   = 'Requis';
  if (!row.ampm)                     e.ampm      = 'Requis';
  if (!(row.thematique || '').trim()) e.thematique = 'Requis';
  if (row.inscrits === '')           e.inscrits  = 'Requis';
  return e;
}

// Valide le formulaire commun du lot — retourne {} si valide
function validateLotForm(form) {
  const e = {};
  if (!(form.commune  || '').trim()) e.commune   = 'Requis';
  if (!(form.lieu     || '').trim()) e.lieu      = 'Requis';
  if (!form.conseiller)              e.conseiller = 'Requis';
  if (!(form.orienteur || '').trim()) e.orienteur = 'Requis';
  if (!form.public)                  e.public    = 'Requis';
  return e;
}

// Filtre les lignes vides du tableau lot
function filterLotRows(rows) {
  return rows.filter(r => r.date || r.horaire || (r.thematique || '').trim());
}

// ── KPI ───────────────────────────────────────────────────────

// Calcule les KPIs sur un tableau d'entrées déjà filtré.
// Inscrits/présents ne portent que sur les Réalisés.
function computeKpi(entries) {
  const realises = entries.filter(e => e.statut === 'Réalisé');
  const annules  = entries.filter(e => e.statut === 'Annulé').length;
  const inscrits = realises.reduce((s, e) => s + (parseInt(e.inscrits) || 0), 0);
  const presents = realises.reduce((s, e) => s + (parseInt(e.presents) || 0), 0);
  const tx       = inscrits > 0 ? Math.round(presents / inscrits * 100) : 0;
  return {total: entries.length, realises: realises.length, annules, inscrits, presents, tx};
}

// ── Statut ────────────────────────────────────────────────────

// today = chaîne ISO YYYY-MM-DD (paramètre explicite pour testabilité)
function isEntryRetard(entry, today) {
  return entry.statut === 'Planifié' && !!entry.date && entry.date < today;
}
function isEntryPasse(entry, today) {
  return entry.statut === 'Réalisé' && !!entry.date && entry.date < today;
}

// ── Filtres ───────────────────────────────────────────────────

// Applique les filtres de l'historique et retourne les entrées triées.
// filtres: {statut, mois, commune, conseiller, public, dateFrom, dateTo, search, sortDir}
function applyFilters(entries, filtres = {}) {
  const {
    statut     = 'Tous',
    mois       = 'Tous',
    commune    = 'Toutes',
    conseiller = 'Tous',
    public: pub = 'Tous',
    dateFrom   = '',
    dateTo     = '',
    search     = '',
    sortDir    = 1,
  } = filtres;

  let r = entries;

  if (statut !== 'Tous')
    r = r.filter(e => e.statut === statut);

  if (mois !== 'Tous')
    r = r.filter(e => e.date && e.date.startsWith(mois));

  if (commune !== 'Toutes') {
    const normFilt = normCommune(String(commune).toUpperCase());
    r = r.filter(e =>
      e.commune === commune ||
      normCommune(String(e.commune || '').toUpperCase()) === normFilt
    );
  }

  if (conseiller !== 'Tous')
    r = r.filter(e => e.conseiller === conseiller);

  if (pub !== 'Tous')
    r = r.filter(e => (e.public || 'Tous publics') === pub);

  if (dateFrom)
    r = r.filter(e => e.date && e.date >= dateFrom);

  if (dateTo)
    r = r.filter(e => e.date && e.date <= dateTo);

  if (search) {
    const q = stripAccents(search);
    r = r.filter(e =>
      [e.lieu, e.thematique, e.orienteur, e.commune, e.public, e.remarques]
        .some(v => stripAccents(String(v || '')).includes(q))
    );
  }

  return [...r].sort((a, b) => {
    const va = a.date || '', vb = b.date || '';
    return va < vb ? -sortDir : va > vb ? sortDir : 0;
  });
}

if (typeof module !== 'undefined') {
  module.exports = {
    normalizeMateriel, parseMateriel,
    validateLotRow, validateLotForm, filterLotRows,
    computeKpi,
    isEntryRetard, isEntryPasse,
    applyFilters,
  };
}
