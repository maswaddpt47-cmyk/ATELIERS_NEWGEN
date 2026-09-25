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

require_once __DIR__ . '/copie.php';

$r = sauvegarde_faire();
if (!$r['ok']) {
    fwrite(STDERR, "sauvegarde : ÉCHEC — {$r['message']}\n");
    exit(1);
}
echo "sauvegarde : {$r['message']}\n";
