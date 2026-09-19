#!/usr/bin/env node
// Garde-fou CI — CLAUDE.md, section 3 "Cache-busting obligatoire".
//
// Si un commit modifie le contenu d'un fichier JS/CSS local versionné par
// ?v=N, la page HTML qui le charge doit voir son ?v= incrémenté dans le
// même diff. Porté depuis ateliers-cd47_NextStep (16/09/2026), suite à deux
// incidents en prod là-bas passés inaperçus faute de ça : un correctif
// jamais reçu par les navigateurs qui avaient déjà l'ancienne version en
// cache, et admin.html resté plusieurs semaines sur du code périmé. Aucun
// test existant (unitaires, e2e) ne pouvait le voir : le code déployé était
// correct, seul le versioning de cache était en cause. NEWGEN n'avait
// jusqu'ici AUCUN ?v= du tout (ajouté le 16/09/2026 en même temps que ce
// garde-fou) — même risque, jamais mitigé ici avant ça.
//
// Usage : node scripts/check-cache-busting.js <base_sha> <head_sha>
// Si base_sha est absent, vide, ou tout-zéro (premier push d'une branche,
// ou déclenchement manuel sans plage de commits) : rien à comparer, le
// script réussit sans vérifier.

const { execSync } = require('child_process');

const FILE_MAP = {
  'app.js':          ['index.html'],
  'app.css':         ['index.html'],
  'admin_app.js':    ['admin.html'],
  'admin_config.js': ['admin.html'],
  // xlsxstyle.js n'est plus dans le <head> d'admin.html : il est chargé à la
  // demande (chargerScriptUneFois) au premier export/import, et c'est donc
  // admin_app.js qui porte son ?v=. Sans cette ligne, le garde-fou le
  // laissait passer en silence — versionOf() ne trouvant plus de ?v= dans le
  // HTML, le fichier sortait simplement du périmètre.
  'xlsxstyle.js':    ['admin_app.js'],
  'admin.css':       ['admin.html'],
  'shared.js':       ['index.html', 'admin.html'],
  'utils.js':        ['index.html', 'admin.html'],
};

function sh(cmd) {
  return execSync(cmd, { encoding: 'utf8' }).trim();
}

function versionOf(content, file) {
  const esc = file.replace(/[.]/g, '\\.');
  const m = new RegExp(esc + String.raw`\?v=([^"'\s>]+)`).exec(content);
  return m ? m[1] : null;
}

function main() {
  const base = process.argv[2] || '';
  const head = process.argv[3] || 'HEAD';

  if (!base || /^0+$/.test(base)) {
    console.log('check-cache-busting: pas de base à comparer (premier push ou déclenchement manuel) — ignoré.');
    return;
  }

  let changed;
  try {
    changed = sh(`git diff --name-only ${base} ${head}`).split('\n').filter(Boolean);
  } catch (err) {
    console.log('check-cache-busting: impossible de calculer le diff (' + err.message.split('\n')[0] + ') — ignoré.');
    return;
  }

  const problems = [];

  for (const [file, htmlFiles] of Object.entries(FILE_MAP)) {
    if (!changed.includes(file)) continue;
    for (const html of htmlFiles) {
      let oldContent, newContent;
      try {
        oldContent = sh(`git show ${base}:${html}`);
      } catch (_) {
        continue; // html introuvable à la base (nouveau fichier) : rien à comparer
      }
      try {
        newContent = sh(`git show ${head}:${html}`);
      } catch (_) {
        continue; // html supprimé au head : rien à comparer
      }
      const oldV = versionOf(oldContent, file);
      const newV = versionOf(newContent, file);
      if (oldV === null || newV === null) continue; // pas de ?v= sur ce fichier : hors périmètre
      if (oldV === newV) {
        problems.push(`${file} a changé mais ${html} référence toujours ?v=${newV} (inchangé) — incrémenter le ?v= de ${file} dans ${html}.`);
      }
    }
  }

  if (problems.length) {
    console.error('❌ Cache-busting manquant :\n' + problems.map(p => '  - ' + p).join('\n'));
    process.exit(1);
  }
  console.log('check-cache-busting: OK.');
}

main();
