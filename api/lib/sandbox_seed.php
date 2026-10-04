<?php
// Remplit la base du bac à sable de FAUSSES données (AG-019, 04/10/2026).
//
//   php ~/www/api-sandbox/lib/sandbox_seed.php
//
// Lancé par deploy-sandbox.yml à chaque déploiement du bac à sable. Ne fait
// rien si la base contient déjà des ateliers (les essais de l'utilisateur
// sont gardés d'un déploiement à l'autre).
//
// Garde-fou : refuse de tourner si la configuration ne porte pas
// « bac_a_sable » => true — clé écrite par le seul workflow du bac à sable,
// jamais dans ~/config-api.php de la production.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/import.php';   // import_requetes_schema

$cfg = api_config();
if (($cfg['bac_a_sable'] ?? false) !== true) {
    fwrite(STDERR, "sandbox_seed : configuration sans « bac_a_sable », refusé\n");
    exit(1);
}
$mdp = (string) ($cfg['mdp_demo'] ?? '');
if (strlen($mdp) < 12) {
    fwrite(STDERR, "sandbox_seed : mot de passe des comptes de démonstration absent ou trop court\n");
    exit(1);
}

$db = api_base();
foreach (import_requetes_schema() as $sql) $db->exec($sql);
if ((int) $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn() > 0) {
    echo "sandbox_seed : base déjà remplie, rien à faire\n";
    exit(0);
}

$conseillers = ['Alice Démo', 'Bruno Démo', 'Chloé Démo'];
$comptes = [['Démo Admin', 'admin'], ['Démo Superviseure', 'superviseur'],
            ['Alice Démo', 'user'], ['Bruno Démo', 'user'], ['Chloé Démo', 'user']];
$hash = password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT);
$ins = $db->prepare('REPLACE INTO comptes (conseiller, hash, role, actif, doit_changer) VALUES (?, ?, ?, 1, 0)');
foreach ($comptes as [$nom, $role]) $ins->execute([$nom, $hash, $role]);

$listes = [
    'list_conseillers' => $conseillers,
    'list_statuts' => ['Planifié', 'Réalisé', 'Annulé', 'Non réalisé', 'Reporté'],
    'list_publics' => ['Séniors', 'Demandeurs d\'emploi', 'Tout public', 'Jeunes'],
    'list_materiels' => ['Classe mobile', 'Vidéoprojecteur', 'Tablette', 'Boitier 4G'],
];
$cfgIns = $db->prepare('REPLACE INTO config (cle, valeur) VALUES (?, ?)');
foreach ($listes as $cle => $v) $cfgIns->execute([$cle, implode("\n", $v)]);
$cfgIns->execute(['conseiller_colors', json_encode(['Alice Démo' => '#2563eb', 'Bruno Démo' => '#16a34a', 'Chloé Démo' => '#db2777'])]);

// Ateliers fictifs : de 60 jours avant à 60 jours après aujourd'hui, jours
// ouvrés, tirage reproductible (mt_srand) pour que deux déploiements
// produisent la même base.
mt_srand(47);
$communes = ['AGEN (47000)', 'FUMEL (47500)', 'NÉRAC (47600)', 'MARMANDE (47200)', 'VILLENEUVE-SUR-LOT (47300)'];
$themes = ['Smartphone', 'Messagerie', 'Démarches en ligne', 'Sécurité', 'IA', 'TBD'];
$orienteurs = ['CCAS Démo', 'France Travail Démo', 'Médiathèque Démo'];
$horaires = ['09:00', '09:30', '10:00', '14:00', '14:30', '15:00'];
$a = $db->prepare('INSERT INTO ateliers (id, n, statut, date, horaire, duree, ampm, orienteur, commune, lieu, thematique, inscrits, presents, public, conseiller, co_animateur, residence, remarques)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'\', \'\', \'\')');
$n = 0;
$aujourdhui = date('Y-m-d');
for ($j = -60; $j <= 60; $j++) {
    $date = date('Y-m-d', strtotime("$j days"));
    if ((int) date('N', strtotime($date)) > 5 || mt_rand(0, 2) === 0) continue;
    $h = $horaires[mt_rand(0, count($horaires) - 1)];
    $inscrits = mt_rand(3, 10);
    $passe = $date < $aujourdhui;
    $statut = $passe ? (mt_rand(0, 9) === 0 ? 'Annulé' : (mt_rand(0, 6) === 0 ? 'Planifié' : 'Réalisé')) : 'Planifié';
    $a->execute([
        'demo_' . (++$n), $n, $statut, $date, $h, [60, 90, 90, 120][mt_rand(0, 3)], (int) substr($h, 0, 2) < 12 ? 'AM' : 'PM',
        $orienteurs[mt_rand(0, 2)], $communes[mt_rand(0, 4)], 'Salle de démonstration', $themes[mt_rand(0, 5)],
        $inscrits, $statut === 'Réalisé' ? mt_rand(1, $inscrits) : null, $listes['list_publics'][mt_rand(0, 3)],
        $conseillers[mt_rand(0, 2)],
    ]);
}
echo "sandbox_seed : $n ateliers fictifs, " . count($comptes) . " comptes de démonstration\n";
