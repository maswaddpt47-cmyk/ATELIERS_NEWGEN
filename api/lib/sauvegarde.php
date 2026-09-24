<?php
// Copie de nuit de la base, gardée 30 jours sur le compte Alwaysdata.
//
//   php ~/www/api/lib/sauvegarde.php
//
// Lancée chaque nuit par une tâche planifiée Alwaysdata (Avancé → Tâches
// planifiées), et une fois à chaque déploiement (deploy-api.yml) pour
// prouver qu'elle marche. Raison (24/09/2026) : l'offre gratuite ne garde
// que 3 jours de sauvegardes Alwaysdata ; une erreur vue au bout d'une
// semaine serait irrattrapable.
//
// Fichiers : ~/sauvegardes/ateliers-AAAA-MM-JJ_HHMMSS.sql.gz, droits 600,
// hors du dossier servi ~/www/. Ne protège pas contre une perte du compte
// lui-même (chantier « copie chiffrée hors Alwaysdata »).
// Restaurer : phpMyAdmin → Importer le .sql.gz, ou
//   gunzip < fichier.sql.gz | mysql -h <hôte> -u <utilisateur> -p <base>
//
// N'écrit sur la sortie qu'une ligne de compte rendu, jamais de donnée :
// elle finit dans le journal public des GitHub Actions.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/base.php';

const SAUVEGARDE_JOURS = 30;

function sauvegarde_echec(string $msg): never
{
    fwrite(STDERR, "sauvegarde : ÉCHEC — $msg\n");
    exit(1);
}

$c = api_config();
foreach (['db_hote', 'db_nom', 'db_utilisateur', 'db_mot_de_passe'] as $cle) {
    if (($c[$cle] ?? '') === '') sauvegarde_echec("$cle manquant dans config-api.php");
}
// lib/ → api/ → www/ → dossier personnel du compte.
$dossier = getenv('ATELIERS_SAUVEGARDE_DIR') ?: dirname(__DIR__, 3) . '/sauvegardes';
if (!is_dir($dossier) && !mkdir($dossier, 0700, true)) sauvegarde_echec("dossier $dossier impossible à créer");
@chmod($dossier, 0700);

// Identifiants passés par un fichier temporaire (600), jamais en ligne de
// commande : ils seraient visibles dans la liste des processus.
$options = tempnam(sys_get_temp_dir(), 'sauv');
chmod($options, 0600);
$q = fn(string $v) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
file_put_contents($options, "[client]\nhost=" . $q($c['db_hote']) . "\nuser=" . $q($c['db_utilisateur']) . "\npassword=" . $q($c['db_mot_de_passe']) . "\n");

$final = $dossier . '/ateliers-' . date('Y-m-d_His') . '.sql.gz';
$partiel = $final . '.partiel';
$cmd = 'set -o pipefail; mysqldump --defaults-extra-file=' . escapeshellarg($options)
     . ' --single-transaction --no-tablespaces --default-character-set=utf8mb4 '
     . escapeshellarg($c['db_nom']) . ' | gzip -9 > ' . escapeshellarg($partiel);
exec('bash -c ' . escapeshellarg($cmd) . ' 2>&1', $sortie, $code);
unlink($options);

// Un dump complet se termine par « -- Dump completed » : un fichier coupé
// en route ne doit jamais passer pour une sauvegarde.
$complet = $code === 0 && is_file($partiel) && str_contains((string) shell_exec('gzip -dc ' . escapeshellarg($partiel) . ' | tail -c 200'), 'Dump completed');
if (!$complet) {
    @unlink($partiel);
    // Le message de mysqldump peut contenir le nom d'utilisateur, pas de donnée.
    sauvegarde_echec("mysqldump code $code : " . mb_substr(implode(' ', $sortie), 0, 300));
}
rename($partiel, $final);
chmod($final, 0600);

// Purge : au-delà de SAUVEGARDE_JOURS, mais jamais la plus récente.
$fichiers = glob($dossier . '/ateliers-*.sql.gz') ?: [];
sort($fichiers);
$purges = 0;
foreach (array_slice($fichiers, 0, -1) as $f) {
    if (filemtime($f) < time() - SAUVEGARDE_JOURS * 86400) { unlink($f); $purges++; }
}
printf("sauvegarde : ok — %s (%d Ko), %d copie(s) gardée(s), %d purgée(s)\n",
    basename($final), (int) ceil(filesize($final) / 1024), count($fichiers) - $purges, $purges);
