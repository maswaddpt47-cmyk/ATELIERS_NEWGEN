<?php
// Fonctions de copie de la base : tâche de nuit (sauvegarde.php) et bouton
// « Copie maintenant » de l'Admin (AG-014). Choix détaillés dans sauvegarde.php.

require_once __DIR__ . '/base.php';

const SAUVEGARDE_JOURS = 30;

// lib/ → api/ → www/ → dossier personnel du compte.
function sauvegarde_dossier(): string
{
    return getenv('ATELIERS_SAUVEGARDE_DIR') ?: dirname(__DIR__, 3) . '/sauvegardes';
}

// Fait une copie et purge les anciennes. Renvoie ['ok' => true, 'message' =>
// compte rendu sans donnée, 'fichier' => nom] ou ['ok' => false, 'message'].
function sauvegarde_faire(): array
{
    $c = api_config();
    foreach (['db_hote', 'db_nom', 'db_utilisateur', 'db_mot_de_passe'] as $cle) {
        if (($c[$cle] ?? '') === '') return ['ok' => false, 'message' => "$cle manquant dans config-api.php"];
    }
    $dossier = sauvegarde_dossier();
    if (!is_dir($dossier) && !@mkdir($dossier, 0700, true)) return ['ok' => false, 'message' => 'dossier des copies impossible à créer'];
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
        return ['ok' => false, 'message' => "mysqldump code $code : " . mb_substr(implode(' ', $sortie), 0, 300)];
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
    return ['ok' => true, 'fichier' => basename($final), 'message' => sprintf('ok — %s (%d Ko), %d copie(s) gardée(s), %d purgée(s)',
        basename($final), (int) ceil(filesize($final) / 1024), count($fichiers) - $purges, $purges)];
}
