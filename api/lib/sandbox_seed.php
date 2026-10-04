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
require_once __DIR__ . '/avis.php';     // avis_schema, AVIS_CHOIX
require_once __DIR__ . '/ecriture.php'; // BILAN_CHOIX

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
    echo "sandbox_seed : ateliers déjà là, gardés\n";
    seed_avis_et_fiches($db);
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
seed_avis_et_fiches($db);

// Avis de stagiaires et fiches bilan FICTIFS (demande du 04/10/2026, pour
// tester « Avis par atelier », le bilan trimestriel et la suppression d'un
// avis) : ateliers de démonstration « Réalisé » seulement (demo_…), jamais
// ceux saisis par l'utilisateur ; une seule fois (aucun avis sur un atelier
// de démonstration) ; au plus un avis par présent, comme le plafond réel.
function seed_avis_et_fiches(PDO $db): void
{
    avis_schema($db);
    if ((int) $db->query("SELECT COUNT(*) FROM avis WHERE atelier_id LIKE 'demo\\_%'")->fetchColumn() > 0) {
        echo "sandbox_seed : avis fictifs déjà là\n";
        return;
    }
    mt_srand(4747);
    $choix = fn(array $l) => $l[mt_rand(0, count($l) - 1)];
    $remarques = ['Très clair, merci !', 'Un peu rapide pour moi.', 'J\'aimerais un deuxième atelier.', 'Bonne ambiance.',
        'Les exercices étaient utiles.', 'Difficile de suivre sur le petit écran.', 'Merci pour la patience.', 'Trop court.', '', '', '', ''];
    $ateliers = $db->query("SELECT id, date, presents, fiche_bilan FROM ateliers WHERE id LIKE 'demo\\_%' AND statut = 'Réalisé'")->fetchAll(PDO::FETCH_ASSOC);
    $ins = $db->prepare('INSERT INTO avis (atelier_id, cree_le, attentes, rythme, clarte, aise, autonomie, sujet, sujet_autre, remarque, source)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)');
    $fiche = $db->prepare('UPDATE ateliers SET fiche_bilan = ? WHERE id = ?');
    $nAvis = 0; $nFiches = 0;
    foreach ($ateliers as $a) {
        $n = mt_rand(0, max(0, (int) $a['presents']));
        for ($i = 0; $i < $n; $i++) {
            $ins->execute([$a['id'], $a['date'], mt_rand(3, 5), $choix(AVIS_CHOIX['rythme']), mt_rand(2, 5), $choix(AVIS_CHOIX['aise']),
                $choix(AVIS_CHOIX['autonomie']), $choix(AVIS_CHOIX['sujet']), $choix($remarques), mt_rand(0, 4) ? 'qr' : 'papier']);
            $nAvis++;
        }
        if ((string) $a['fiche_bilan'] === '' && mt_rand(0, 3)) {
            $b = ['niveau' => $choix(BILAN_CHOIX['niveau']), 'objectif' => $choix(BILAN_CHOIX['objectif']),
                  'supports' => [$choix(BILAN_CHOIX['supports'])], 'suite' => $choix(BILAN_CHOIX['suite'])];
            if (mt_rand(0, 1)) $b['difficultes'] = [$choix(BILAN_CHOIX['difficultes'])];
            if (in_array('Autre', $b['difficultes'] ?? [], true)) $b['difficultes_autre'] = 'Salle trop petite';
            $fiche->execute([json_encode($b, JSON_UNESCAPED_UNICODE), $a['id']]);
            $nFiches++;
        }
    }
    echo "sandbox_seed : $nAvis avis fictifs, $nFiches fiches bilan fictives\n";
}
