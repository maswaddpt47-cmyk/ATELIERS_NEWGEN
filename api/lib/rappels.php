<?php
// Rappels des ateliers en retard, remplaçant d'envoyerAlertesRetard (GAS
// NextStep, déclencheur supprimé à la bascule du 25/09/2026).
//
//   php ~/www/api/lib/rappels.php                  envoi réel
//   php ~/www/api/lib/rappels.php --test=adresse   un seul mail [TEST] avec
//                                                  tous les retards, à cette
//                                                  adresse, rien d'autre
//
// Lancé chaque jour par une tâche planifiée Alwaysdata.
//
// Règle reprise du GAS : un atelier est « en retard » s'il est encore
// « Planifié » alors que sa date est passée. Chaque conseiller reçoit la
// liste des siens, à l'adresse de Listes → mails.
// Écart voulu avec le GAS : l'interrupteur de rappel par conseiller (Listes,
// config rappels_actifs = {"Nom": false}) est respecté. Le GAS ne lisait que
// rappels_actifs === 'false' en bloc et ignorait l'interrupteur individuel.
//
// N'écrit sur la sortie qu'un décompte, jamais de nom ni d'adresse : la
// sortie finit dans les journaux de tâches et les mails d'erreur.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/api.php';   // api_config_base, api_json, api_journal

const RAPPELS_URL_APPLI = 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/';

$test = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--test=')) $test = substr($arg, 7);
}
if ($test !== null && !filter_var($test, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "rappels : adresse de test invalide\n");
    exit(1);
}

$db = api_base();
$cfg = api_config_base($db);
if (in_array((string) ($cfg['rappels_actifs'] ?? ''), ['false', 'FALSE'], true)) {
    echo "rappels : désactivés (réglage général)\n";
    exit(0);
}
$actifs = api_json($cfg['rappels_actifs'] ?? '', []);
$emails = api_json($cfg['emails'] ?? '', []);
if (!is_array($emails)) $emails = [];
$actifs = is_array($actifs) ? $actifs : [];

$st = $db->query("SELECT date, horaire, thematique, conseiller, commune FROM ateliers
                  WHERE statut = 'Planifié' AND date < CURDATE() ORDER BY conseiller, date, horaire");
$parConseiller = [];
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $parConseiller[$r['conseiller']][] = $r;

// Mail d'un conseiller : sujet, texte, HTML. Tout ce qui vient de la base est
// échappé (le GAS l'insérait tel quel dans le HTML).
function rappels_mail(string $nom, array $retards, bool $test): array
{
    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $date = fn($r) => date('d/m/Y', strtotime($r['date'])) . ($r['horaire'] ? ' ' . $r['horaire'] : '');
    $sujet = ($test ? '[TEST] ' : '') . count($retards) . ' atelier(s) en attente de mise à jour';
    $texte = "Bonjour $nom,\n\nCes ateliers ont le statut « Planifié » mais leur date est passée :\n\n";
    $lignes = '';
    foreach ($retards as $r) {
        $texte .= '- ' . $date($r) . ' — ' . ($r['thematique'] ?: '—') . ' — ' . ($r['commune'] ?: '—') . "\n";
        $lignes .= '<tr><td style="padding:6px 10px;border-bottom:1px solid #e2e8f0">' . $h($date($r)) . '</td>'
                 . '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;font-weight:600">' . $h($r['thematique'] ?: '—') . '</td>'
                 . '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0">' . $h($r['conseiller'] ?: '—') . '</td>'
                 . '<td style="padding:6px 10px;border-bottom:1px solid #e2e8f0;color:#718096">' . $h($r['commune'] ?: '—') . '</td></tr>';
    }
    $texte .= "\nPour les mettre à jour : " . RAPPELS_URL_APPLI . "\n";
    $html = '<div style="font-family:sans-serif;max-width:640px;margin:0 auto">'
          . '<div style="background:#1e3a8a;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0"><h2 style="margin:0;font-size:18px">Ateliers en attente de mise à jour</h2>'
          . ($test ? '<p style="margin:6px 0 0;font-size:12px;opacity:.8">— MAIL DE TEST —</p>' : '') . '</div>'
          . '<div style="background:#fff;padding:20px 24px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px">'
          . '<p style="color:#4a5568">Bonjour ' . $h($nom) . ',</p>'
          . '<p style="color:#4a5568">Ces ateliers ont le statut <strong>Planifié</strong> mais leur date est passée :</p>'
          . '<table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr style="background:#f7fafc">'
          . '<th style="padding:8px 10px;text-align:left;color:#718096">Date</th><th style="padding:8px 10px;text-align:left;color:#718096">Atelier</th>'
          . '<th style="padding:8px 10px;text-align:left;color:#718096">Conseiller</th><th style="padding:8px 10px;text-align:left;color:#718096">Commune</th>'
          . '</tr></thead><tbody>' . $lignes . '</tbody></table>'
          . '<div style="text-align:center;margin-top:20px"><a href="' . $h(RAPPELS_URL_APPLI) . '" style="display:inline-block;background:#1e3a8a;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700"><span style="color:#ffffff">Ouvrir l\'application</span></a></div>'
          . '</div></div>';
    return [$sujet, $texte, $html];
}

if ($test !== null) {
    $tous = array_merge([], ...array_values($parConseiller));
    if (!$tous) $tous = [['date' => date('Y-m-d', strtotime('-3 days')), 'horaire' => '09:00', 'thematique' => 'Atelier exemple', 'conseiller' => 'Exemple', 'commune' => 'Exemple']];
    [$sujet, $texte, $html] = rappels_mail('(test)', $tous, true);
    $ok = mail_envoyer($test, $sujet, $texte, $html);
    echo 'rappels : test ' . ($ok ? 'envoyé' : 'ÉCHEC') . ' (' . count($tous) . " atelier(s))\n";
    exit($ok ? 0 : 1);
}

$envoyes = 0; $echecs = 0; $sansAdresse = 0; $coupes = 0;
foreach ($parConseiller as $nom => $retards) {
    if (($actifs[$nom] ?? true) === false) { $coupes++; continue; }
    $adresse = trim((string) (is_array($emails[$nom] ?? null) ? ($emails[$nom]['email'] ?? '') : ($emails[$nom] ?? '')));
    if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) { $sansAdresse++; continue; }
    [$sujet, $texte, $html] = rappels_mail((string) $nom, $retards, false);
    if (mail_envoyer($adresse, $sujet, $texte, $html)) {
        $envoyes++;
        api_journal($db, 'alertesRetard', (string) $nom, count($retards) . ' atelier(s)', '', 1, 0, '', 'tache');
    } else {
        $echecs++;
    }
}
printf("rappels : %d mail(s) envoyé(s), %d échec(s), %d conseiller(s) sans adresse, %d rappel(s) coupé(s)\n",
    $envoyes, $echecs, $sansAdresse, $coupes);
exit($echecs ? 1 : 0);
