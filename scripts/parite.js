#!/usr/bin/env node
// Contrôle de parité NEWGEN / NextStep — AG-015, tranché le 26/09/2026.
//
// Les deux applis partagent le même serveur et la même interface (CLAUDE.md,
// règle 18) : une correction faite d'un seul côté laisse l'autre sur l'ancien
// comportement, et personne ne le voit. Ce script compare, page par page, ce
// que le navigateur exécute réellement : les scripts locaux de index.html et
// admin.html, dans l'ordre de chargement, découpés en instructions de premier
// niveau (fonctions, constantes, `window.X = …`, IIFE — dont l'injection du
// CSS). Une même définition chargée deux fois : la dernière gagne, comme dans
// le navigateur. La comparaison se fait sur l'arbre (acorn), positions et
// guillemets ignorés : un déplacement entre fichiers ou un reformatage n'est
// pas un écart.
//
// Chaque écart constaté doit figurer dans scripts/parite-ecarts.json avec un
// statut que l'utilisateur tranche : `voulu` (la différence est un choix) ou
// `à aligner` (dette connue). `à trancher` = pas encore arbitré. L'empreinte
// de chaque côté y est notée : si un écart déjà listé bouge encore, le script
// le signale, car un `voulu` d'hier ne couvre pas le code d'aujourd'hui.
//
// Échoue (code 1) si : un écart n'est pas listé, une empreinte a changé, ou
// un écart listé a disparu (le retirer de la liste). Les `à aligner` connus
// ne font pas échouer : c'est la liste qui les porte.
//
// Usage : node scripts/parite.js <chemin NextStep> [--maj]
//   --maj : réécrit la liste — ajoute les nouveaux écarts en `à trancher`,
//           retire les disparus, remet en `à trancher` ceux qui ont bougé.
//
// Jamais bloquant pour la mise en ligne : workflow séparé (parite.yml).

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const acorn = require('acorn');

const NEWGEN = path.resolve(__dirname, '..');
const LISTE = path.join(__dirname, 'parite-ecarts.json');
const PAGES = ['index.html', 'admin.html'];
const STATUTS = ['voulu', 'à aligner', 'à trancher'];
// Bibliothèques tierces servies hors de vendor/ : pas du code des applis.
// NextStep charge xlsxstyle.js dans admin.html, NEWGEN à la demande.
const TIERS = ['xlsxstyle.js'];

function scriptsLocaux(depot, page) {
  const html = fs.readFileSync(path.join(depot, page), 'utf8');
  return [...html.matchAll(/<script[^>]*\bsrc="([^"?]+)(?:\?[^"]*)?"/g)]
    .map(m => m[1])
    .filter(src => !/^(https?:)?\/\//.test(src) && !src.startsWith('vendor/') && !TIERS.includes(src));
}

// Arbre sans positions ni forme brute des littéraux : deux instructions
// équivalentes au reformatage près ont la même empreinte.
function empreinte(noeud) {
  const json = JSON.stringify(noeud, (k, v) =>
    (k === 'start' || k === 'end' || k === 'loc' || k === 'raw' || k === 'range') ? undefined : v);
  return crypto.createHash('sha256').update(json).digest('hex').slice(0, 10);
}

function nomDe(noeud, fichier, rangs) {
  const rang = type => { rangs[type] = (rangs[type] || 0) + 1; return `${fichier}:${type}#${rangs[type]}`; };
  if (noeud.type === 'FunctionDeclaration' || noeud.type === 'ClassDeclaration') return [noeud.id.name];
  if (noeud.type === 'VariableDeclaration') {
    return noeud.declarations.map(d => d.id.type === 'Identifier' ? d.id.name : rang('destructuration'));
  }
  if (noeud.type === 'ExpressionStatement') {
    const e = noeud.expression;
    if (e.type === 'AssignmentExpression' && e.left.type === 'MemberExpression' && !e.left.computed) {
      const chemin = [];
      let m = e.left;
      while (m.type === 'MemberExpression' && !m.computed) { chemin.unshift(m.property.name); m = m.object; }
      if (m.type === 'Identifier') return [[m.name, ...chemin].join('.')];
    }
    if (e.type === 'AssignmentExpression' && e.left.type === 'Identifier') return [e.left.name];
    const appel = e.type === 'UnaryExpression' ? e.argument : e;
    if (appel.type === 'CallExpression' && /Function/.test(appel.callee.type)) {
      // Nommée d'après ce qu'elle est (injectCSS) ou ce qu'elle publie (le premier
      // window.X par ordre alphabétique) : le rang seul décale tout dès qu'un côté en ajoute une.
      if (appel.callee.id) return [`${fichier}:IIFE ${appel.callee.id.name}`];
      const corps = corpsIIFE(noeud);
      const publie = corps && corps.body.map(nomWindow).filter(Boolean).sort()[0];
      return [publie ? `${fichier}:IIFE → ${publie}` : rang('IIFE')];
    }
  }
  return [rang(noeud.type)];
}

function corpsIIFE(noeud) {
  if (noeud.type !== 'ExpressionStatement') return null;
  const e = noeud.expression.type === 'UnaryExpression' ? noeud.expression.argument : noeud.expression;
  if (e.type !== 'CallExpression' || !/Function/.test(e.callee.type)) return null;
  return e.callee.body.type === 'BlockStatement' ? e.callee.body : null;
}

function nomWindow(instr) {
  if (instr.type !== 'ExpressionStatement') return null;
  const e = instr.expression;
  if (e.type !== 'AssignmentExpression' || e.left.type !== 'MemberExpression' || e.left.computed) return null;
  if (e.left.object.type !== 'Identifier' || e.left.object.name !== 'window') return null;
  return `window.${e.left.property.name}`;
}

// Pour une page : nom → { empreinte, lieu }. Une déclaration à plusieurs noms
// (`let a=1, b=2`) est découpée par déclarateur.
function unites(depot, page) {
  const res = {};
  const doublons = [];
  for (const fichier of scriptsLocaux(depot, page)) {
    const src = fs.readFileSync(path.join(depot, fichier), 'utf8');
    const ast = acorn.parse(src, { ecmaVersion: 'latest', sourceType: 'script', locations: true, allowHashBang: true });
    const rangs = {};
    const noter = (nom, partie) => {
      const lieu = `${fichier}:${partie.loc.start.line}`;
      if (res[nom] && !nom.includes(':')) doublons.push(`${nom} (${res[nom].lieu} écrasé par ${lieu})`);
      res[nom] = { empreinte: empreinte(partie), lieu };
    };
    for (const noeud of ast.body) {
      const noms = nomDe(noeud, fichier, rangs);
      // IIFE : les `window.X = …` de premier niveau de son corps sont des
      // unités à part (c'est ce que le reste du code voit) ; l'IIFE elle-même
      // est comparée sans eux.
      const corps = corpsIIFE(noeud);
      if (corps && noms[0].includes(':IIFE')) {
        const reste = [];
        for (const instr of corps.body) {
          const nom = nomWindow(instr);
          if (nom) noter(nom, instr); else reste.push(instr);
        }
        noter(noms[0], { type: 'IIFE', loc: noeud.loc, corps: reste });
        continue;
      }
      noms.forEach((nom, i) => noter(nom, noeud.type === 'VariableDeclaration' ? noeud.declarations[i] : noeud));
    }
  }
  return { res, doublons };
}

function ecarts(nextstep) {
  const constat = {};
  const doublons = {};
  for (const page of PAGES) {
    const a = unites(NEWGEN, page), b = unites(nextstep, page);
    doublons[`NEWGEN ${page}`] = a.doublons;
    doublons[`NextStep ${page}`] = b.doublons;
    for (const nom of new Set([...Object.keys(a.res), ...Object.keys(b.res)])) {
      const ua = a.res[nom], ub = b.res[nom];
      if (ua && ub && ua.empreinte === ub.empreinte) continue;
      const e = constat[nom] || (constat[nom] = { pages: [], newgen: '-', nextstep: '-', lieux: new Set() });
      e.pages.push(page.replace('.html', ''));
      // Empreintes des deux pages mises bout à bout : `App` n'est pas la même
      // fonction dans index (app.js) et dans admin (admin_app.js), un
      // changement d'une seule des deux doit se voir.
      const joindre = (avant, u) => [avant, u ? u.empreinte : '-'].filter(x => x !== null).join('/');
      e.newgen = joindre(e.pages.length > 1 ? e.newgen : null, ua);
      e.nextstep = joindre(e.pages.length > 1 ? e.nextstep : null, ub);
      if (ua) e.lieux.add(`NEWGEN ${ua.lieu}`);
      if (ub) e.lieux.add(`NextStep ${ub.lieu}`);
    }
  }
  return { constat, doublons };
}

function nature(e) {
  if (/^-(\/-)*$/.test(e.newgen)) return 'absent de NEWGEN';
  if (/^-(\/-)*$/.test(e.nextstep)) return 'absent de NextStep';
  return 'différent';
}

function main() {
  const args = process.argv.slice(2);
  const maj = args.includes('--maj');
  const nextstep = args.find(a => !a.startsWith('--'));
  if (!nextstep) { console.error('Usage : node scripts/parite.js <chemin NextStep> [--maj]'); process.exit(2); }

  const { constat, doublons } = ecarts(path.resolve(nextstep));
  const liste = fs.existsSync(LISTE) ? JSON.parse(fs.readFileSync(LISTE, 'utf8')) : {};
  const problemes = [];
  const parStatut = {};

  for (const [nom, e] of Object.entries(constat)) {
    const connu = liste[nom];
    if (!connu) { problemes.push(`NOUVEL ÉCART ${nom} — ${nature(e)} (${[...e.lieux].join(', ')})`); continue; }
    if (!STATUTS.includes(connu.statut)) problemes.push(`STATUT INVALIDE ${nom} : « ${connu.statut} »`);
    if (connu.newgen !== e.newgen || connu.nextstep !== e.nextstep) {
      problemes.push(`ÉCART MODIFIÉ ${nom} (${connu.statut}) — ${[...e.lieux].join(', ')} : à revérifier`);
    }
    parStatut[connu.statut] = (parStatut[connu.statut] || 0) + 1;
  }
  for (const nom of Object.keys(liste)) {
    if (!constat[nom]) problemes.push(`ÉCART RÉSOLU ${nom} — à retirer de la liste`);
  }

  console.log(`Parité NEWGEN / NextStep : ${Object.keys(constat).length} écarts constatés`);
  for (const [s, n] of Object.entries(parStatut)) console.log(`  ${s} : ${n}`);
  for (const [cote, d] of Object.entries(doublons)) {
    for (const x of d) console.log(`  ⚠ définition écrasée, ${cote} : ${x}`);
  }

  if (maj) {
    const neuve = {};
    for (const nom of Object.keys(constat).sort()) {
      const e = constat[nom], connu = liste[nom];
      const bouge = !connu || connu.newgen !== e.newgen || connu.nextstep !== e.nextstep;
      neuve[nom] = {
        statut: bouge ? 'à trancher' : connu.statut,
        nature: nature(e),
        pages: e.pages.join('+'),
        lieux: [...e.lieux],
        newgen: e.newgen,
        nextstep: e.nextstep,
        note: connu ? connu.note : '',
      };
    }
    fs.writeFileSync(LISTE, JSON.stringify(neuve, null, 2) + '\n');
    console.log(`Liste réécrite : ${LISTE}`);
    return;
  }

  if (problemes.length) {
    console.log(`\n${problemes.length} point(s) à traiter :`);
    for (const p of problemes) console.log(`  - ${p}`);
    console.log('\nTrancher chaque écart (voulu / à aligner) dans scripts/parite-ecarts.json,'
      + ' ou aligner le code. `--maj` réécrit la liste, les écarts changés repassent en « à trancher ».');
    process.exit(1);
  }
  console.log('Aucun écart non listé.');
}

main();
