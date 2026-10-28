<?php
// Tests du bilan mensuel (api/lib/bilan.php).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/bilan.test.php
//
// Le script est lancé comme la tâche planifiée le lancera, sur une base
// jetable ; les mails sont écrits dans un dossier au lieu d'être envoyés.

require __DIR__ . '/outils.php';
require_once __DIR__ . '/../api/lib/avis.php';   // avis_schema

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(bilan non testé : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_bilan');
$db->exec('CREATE DATABASE ateliers_test_bilan CHARACTER SET utf8mb4');
$db->exec('USE ateliers_test_bilan');
foreach (import_requetes_schema() as $sql) $db->exec($sql);

$ins = $db->prepare("INSERT INTO ateliers (id, statut, date, horaire, thematique, conseiller, commune, orienteur, lieu, co_animateur, residence, remarques, inscrits, presents)
                     VALUES (?, ?, ?, '09:00', 'T', ?, 'Nérac', ?, '', '', '', '', ?, ?)");
$ins->execute(['s1', 'Réalisé', '2026-09-03', 'Alice', 'CAF', 6, 5]);
$ins->execute(['s2', 'Réalisé', '2026-09-10', 'Bruno', 'CAF', 4, 3]);
$ins->execute(['s3', 'Annulé', '2026-09-12', 'Alice', 'MSA <b>', 4, null]);
$ins->execute(['s4', 'Planifié', '2026-09-20', 'Bruno', 'MSA <b>', 4, null]);   // date passée : en retard
$ins->execute(['a1', 'Réalisé', '2026-08-05', 'Alice', 'CAF', 5, 2]);           // mois précédent
$ins->execute(['o1', 'Planifié', '2026-10-06', 'Alice', 'CAF', 4, null]);        // mois suivant
$db->exec("UPDATE ateliers SET co_animateur = 'Alice' WHERE id = 's2'");          // co-animé (AG-037, D1)
// Qualité (04/10/2026) : fiches bilan et avis des ateliers réalisés du mois.
$db->prepare("UPDATE ateliers SET fiche_bilan = ? WHERE id = 's1'")->execute([json_encode(['objectif' => 'Oui', 'difficultes' => ['Connexion', 'Autre'], 'difficultes_autre' => 'Salle Martin', 'suite' => 'Nouvel atelier'], JSON_UNESCAPED_UNICODE)]);
avis_schema($db);
$av = $db->prepare("INSERT INTO avis (atelier_id, cree_le, attentes, clarte, aise, autonomie, remarque, source) VALUES (?, ?, ?, ?, ?, ?, ?, 'qr')");
$av->execute(['s1', '2026-09-03', 5, 4, 'Oui', 'Non', 'Merci à Alice']);
$av->execute(['s1', '2026-09-03', 4, 4, 'Un peu', 'Oui', '']);
$av->execute(['s1', '2026-09-03', null, null, null, null, '']);   // tout sans réponse (AG-032)
$av->execute(['s3', '2026-09-12', 1, 1, 'Non', 'Non', '']);   // atelier annulé : ignoré
$cpt = $db->prepare("INSERT INTO comptes (conseiller, hash, role, actif, doit_changer) VALUES (?, NULL, ?, ?, 0)");
$cpt->execute(['Sophie', 'superviseur', 1]);
$cpt->execute(['Ancienne', 'superviseur', 0]);
$cpt->execute(['Alice', 'user', 1]);
$db->prepare('INSERT INTO config (cle, valeur) VALUES (?, ?)')->execute(['emails', json_encode([
    'Sophie' => 'sophie@example.org', 'Ancienne' => 'ancienne@example.org', 'Alice' => 'alice@example.org'])]);

$tmp = sys_get_temp_dir() . '/bilan-test-' . getmypid();
@mkdir("$tmp/mails", 0700, true);
file_put_contents("$tmp/config.php", '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_bilan', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp], true) . ';');
$lancer = function (string $args = '') use ($tmp): array {
    $env = 'ATELIERS_API_CONFIG=' . escapeshellarg("$tmp/config.php") . ' ATELIERS_MAIL_TEST_DIR=' . escapeshellarg("$tmp/mails");
    exec("$env php " . escapeshellarg(__DIR__ . '/../api/lib/bilan.php') . " $args 2>&1", $sortie, $code);
    return [$code, implode("\n", $sortie)];
};
$mails = fn() => array_map('file_get_contents', glob("$tmp/mails/*.txt") ?: []);

echo "Bilan mensuel\n";
[$code, $sortie] = $lancer('--mois=2026-09');
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: sophie@example.org'), "un seul mail, à la superviseure active : $sortie");
$t = $m[0] ?? '';
verifier(str_contains($t, 'Ateliers au total : 4 (août : 1, +3)') && str_contains($t, 'Réalisés : 2 (août : 1, +1)'), 'chiffres du mois comparés au mois précédent');
verifier(str_contains($t, 'Inscrits (ateliers réalisés) : 10') && str_contains($t, 'Présents (ateliers réalisés) : 8') && str_contains($t, 'Taux de présence : 80 % (août : 40 %)'), 'inscrits et présents des seuls ateliers réalisés, taux');
verifier(str_contains($t, '- Alice : 2 réalisé(s) sur 3, 8 présent(s) / 10 inscrit(s)') && str_contains($t, '- Bruno : 1 réalisé(s) sur 2, 3 présent(s) / 4 inscrit(s)') && str_contains($t, '- CAF : 2 réalisé(s) sur 2'), 'par conseiller (co-animation comprise, AG-037) et par partenaire');
// Concordance mail / écran « Bilans » (AG-037, D5) : mêmes données passées à
// bilanPeriode (logic.js), les chiffres communs doivent se retrouver dans le mail.
$lignes = $db->query("SELECT id AS _id, statut, date, conseiller, co_animateur, orienteur, commune, thematique, inscrits, presents, fiche_bilan FROM ateliers")->fetchAll(PDO::FETCH_ASSOC);
foreach ($lignes as &$l) { $l['fiche_bilan'] = $l['fiche_bilan'] ? json_decode($l['fiche_bilan'], true) : null; foreach (['inscrits', 'presents'] as $k) $l[$k] = $l[$k] === null ? '' : (string) $l[$k]; }
unset($l);
$avisJs = $db->query("SELECT atelier_id, attentes, clarte, aise, autonomie FROM avis")->fetchAll(PDO::FETCH_ASSOC);
file_put_contents("$tmp/fixture.json", json_encode(['e' => $lignes, 'a' => $avisJs], JSON_UNESCAPED_UNICODE));
$js = 'const L=require(process.argv[1]+"/logic.js");Object.assign(global,require(process.argv[1]+"/utils.js"));'
    . 'const f=require(process.argv[2]);const b=L.bilanPeriode(f.e,"2026-09-01","2026-09-30",f.a.map(a=>({...a,attentes:+a.attentes||null,clarte:+a.clarte||null})),"2026-10-01");'
    . 'console.log(JSON.stringify(b));';
exec('node -e ' . escapeshellarg($js) . ' ' . escapeshellarg(realpath(__DIR__ . '/..')) . ' ' . escapeshellarg("$tmp/fixture.json") . ' 2>&1', $sortieJs, $codeJs);
$b = json_decode(implode('', $sortieJs), true);
if (!is_array($b)) { verifier(false, 'concordance : bilanPeriode non exécuté (' . implode(' ', $sortieJs) . ')'); }
else {
    $a = $b['activite'];
    $attendus = ["Ateliers au total : {$a['programmes']} ", "Réalisés : {$a['realises']} ", "Inscrits (ateliers réalisés) : {$a['inscrits']} ", "Présents (ateliers réalisés) : {$a['presents']} ",
        "Taux de présence : {$a['tauxPresence']} %", "Fiches bilan remplies : {$b['fiches']['remplies']} sur {$a['realises']} ",
        "Avis des stagiaires : {$b['avis']['n']} avis sur {$b['avis']['ateliers']} ", 'Réponse aux attentes : ' . str_replace('.', ',', (string) $b['avis']['attentes']) . '/5'];
    foreach ($b['parConseiller'] as $c) $attendus[] = "- {$c['conseiller']} : {$c['realises']} réalisé(s) sur {$c['programmes']}, {$c['presents']} présent(s)";
    foreach ($b['parOrienteur'] as $o) $attendus[] = "- {$o['orienteur']} : {$o['ateliers']} réalisé(s)";
    $manque = array_values(array_filter($attendus, fn($x) => !str_contains($t, $x)));
    verifier(count($attendus) > 10 && $manque === [], 'concordance écran « Bilans » / mail mensuel : ' . ($manque ? 'absent du mail : ' . implode(' | ', $manque) : count($attendus) . ' chiffres communs identiques'));
}
verifier(str_contains($t, '1 atelier(s) de septembre 2026 encore « Planifié »') && str_contains($t, '1 atelier(s) planifié(s) en octobre'), 'points d\'attention : retards, mois suivant');
verifier(!preg_match('/Alice|Sophie|example\.org/', $sortie), 'compte rendu sans nom ni adresse');
verifier(str_contains($t, 'Fiches bilan remplies : 1 sur 2 atelier(s) réalisé(s)') && str_contains($t, 'Objectif atteint : Oui : 1') && str_contains($t, 'Difficultés rencontrées : Connexion : 1, Autre : 1')
    && str_contains($t, 'Avis des stagiaires : 3 avis sur 1 atelier(s)') && str_contains($t, 'Réponse aux attentes : 4,5/5') && str_contains($t, 'Plus à l\'aise (« oui ») : 1 sur 3 avis (1 sans réponse)'), 'qualité : fiches bilan et avis des seuls ateliers réalisés');
verifier(!str_contains($t, 'Merci à Alice') && !str_contains($t, 'Salle Martin'), 'qualité : ni remarque d\'avis ni précision libre dans le mail');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'bilanMensuel' AND ref = '2026-09'")->fetchColumn() === 1, 'envoi journalisé');

array_map('unlink', glob("$tmp/mails/*.txt"));
[$code, $sortie] = $lancer('--mois=2026-09');
verifier($code === 0 && $mails() === [] && str_contains($sortie, '1 déjà servi(s)'), 'relancé le même mois : aucun second envoi');
[$code, $sortie] = $lancer('--mois=2026-09 --moi@example.org');
verifier($code === 1 && $mails() === [], 'option inconnue (« test= » oublié) : arrêt, aucun envoi');

[$code, $sortie] = $lancer('--mois=2026-09 --test=moi@example.org');
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: moi@example.org') && str_contains($m[0], '[TEST]'), 'mode test : un seul mail, à l\'adresse donnée');
[$code] = $lancer('--mois=2026-13');
verifier($code === 1, 'mois invalide : refusé');

// Taux de présence (règle du 20/10/2026, identique à utils.js et logic.js) :
// un atelier réalisé dont les présents ne sont pas saisis sort du taux au lieu
// d'être compté à 0 présent. Novembre : 8 inscrits / 6 présents, plus 4 inscrits
// sans présents saisis → 6/8 = 75 % (et non 6/12 = 50 %) ; les lignes
// « Inscrits » et « Présents » restent des totaux (12 et 6).
echo "Taux de présence\n";
$ins->execute(['t1', 'Réalisé', '2026-11-03', 'Alice', 'CAF', 8, 6]);
$ins->execute(['t2', 'Réalisé', '2026-11-10', 'Alice', 'CAF', 4, null]);
array_map('unlink', glob("$tmp/mails/*.txt"));
[$code, $sortie] = $lancer('--mois=2026-11');
$t = ($mails())[0] ?? '';
verifier($code === 0 && str_contains($t, 'Inscrits (ateliers réalisés) : 12') && str_contains($t, 'Présents (ateliers réalisés) : 6'), 'les lignes Inscrits / Présents restent des totaux');
verifier(str_contains($t, 'Taux de présence : 75 %'), 'présents non saisis : hors du taux (75 %, pas 50 %)');

$db->exec('DROP DATABASE ateliers_test_bilan');
exec('rm -rf ' . escapeshellarg($tmp));

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
