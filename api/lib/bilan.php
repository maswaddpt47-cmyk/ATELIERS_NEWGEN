<?php
// Bilan mensuel des ateliers, envoyé à la superviseure (demande de
// l'utilisateur, 03/10/2026).
//
//   php ~/www/api/lib/bilan.php                    envoi réel, mois écoulé
//   php ~/www/api/lib/bilan.php --mois=2026-09     un autre mois
//   php ~/www/api/lib/bilan.php --test=adresse     un seul mail [TEST] à
//                                                  cette adresse, rien d'autre
//
// Lancé le 1er de chaque mois par une tâche planifiée Alwaysdata.
//
// Destinataires : comptes « superviseur » actifs ayant une adresse dans
// Listes → mails. Contenu : chiffres du mois comparés au mois précédent, par
// conseiller, par partenaire, points d'attention ; depuis le 04/10/2026,
// qualité des ateliers réalisés : fiches bilan et avis des stagiaires, en
// chiffres seulement — jamais les remarques libres, qui ne partent pas par
// mail. Ni par conseiller : la satisfaction n'est pas une évaluation d'agent.
//
// N'écrit sur la sortie qu'un décompte, jamais de nom ni d'adresse : la
// sortie finit dans les journaux de tâches et les mails d'erreur.

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/api.php';   // api_config_base, api_json, api_journal

const BILAN_URL_APPLI = 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/';
const BILAN_MOIS_FR = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

$test = null;
$mois = date('Y-m', strtotime('first day of last month'));
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--test=')) $test = substr($arg, 7);
    elseif (str_starts_with($arg, '--mois=')) $mois = substr($arg, 7);
    else {
        // Option inconnue : on s'arrête. Le 03/10/2026, un « --adresse » sans
        // « test= » a lancé un envoi réel toutes les 5 minutes.
        fwrite(STDERR, "bilan : option inconnue (attendu --test=adresse ou --mois=AAAA-MM)\n");
        exit(1);
    }
}
if ($test !== null && !filter_var($test, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "bilan : adresse de test invalide\n");
    exit(1);
}
if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $mois, $m)) {
    fwrite(STDERR, "bilan : mois invalide (attendu AAAA-MM)\n");
    exit(1);
}

$db = api_base();
ateliers_colonne_duree($db);
$debut = "$mois-01";
$fin = date('Y-m-t', strtotime($debut));
$precDebut = date('Y-m-01', strtotime("$debut -1 month"));
$precFin = date('Y-m-t', strtotime($precDebut));
$suivDebut = date('Y-m-01', strtotime("$debut +1 month"));
$suivFin = date('Y-m-t', strtotime($suivDebut));
$aujourdhui = date('Y-m-d');   // heure de Paris (base.php)

// Chiffres d'une période : nombre par statut ; inscrits et présents des
// ateliers réalisés (même règle que kpiHistorique côté pages).
function bilan_chiffres(PDO $db, string $de, string $a, string $grouper = ''): array
{
    $col = $grouper !== '' ? "$grouper AS cle," : "'' AS cle,";
    $st = $db->prepare("SELECT $col statut, COUNT(*) AS n,
                               SUM(CASE WHEN statut = 'Réalisé' THEN COALESCE(inscrits, 0) ELSE 0 END) AS inscrits,
                               SUM(CASE WHEN statut = 'Réalisé' THEN COALESCE(presents, 0) ELSE 0 END) AS presents
                        FROM ateliers WHERE date BETWEEN ? AND ? GROUP BY cle, statut");
    $st->execute([$de, $a]);
    $r = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $l) {
        $k = trim((string) $l['cle']) !== '' ? trim((string) $l['cle']) : '—';
        $r[$k] ??= ['total' => 0, 'statuts' => [], 'inscrits' => 0, 'presents' => 0];
        $r[$k]['total'] += (int) $l['n'];
        $r[$k]['statuts'][$l['statut'] ?: '—'] = (int) $l['n'];
        $r[$k]['inscrits'] += (int) $l['inscrits'];
        $r[$k]['presents'] += (int) $l['presents'];
    }
    return $grouper !== '' ? $r : ($r['—'] ?? ['total' => 0, 'statuts' => [], 'inscrits' => 0, 'presents' => 0]);
}

function bilan_taux(int $presents, int $inscrits): string
{
    return $inscrits > 0 ? round($presents / $inscrits * 100) . ' %' : '—';
}

$ce = bilan_chiffres($db, $debut, $fin);
$prec = bilan_chiffres($db, $precDebut, $precFin);
$parConseiller = bilan_chiffres($db, $debut, $fin, 'conseiller');
$parPartenaire = bilan_chiffres($db, $debut, $fin, 'orienteur');
ksort($parConseiller, SORT_STRING | SORT_FLAG_CASE);
uasort($parPartenaire, fn($x, $y) => ($y['statuts']['Réalisé'] ?? 0) <=> ($x['statuts']['Réalisé'] ?? 0) ?: $y['total'] <=> $x['total']);
$st = $db->prepare("SELECT COUNT(*) FROM ateliers WHERE statut = 'Planifié' AND date BETWEEN ? AND ? AND date < ?");
$st->execute([$debut, $fin, $aujourdhui]);
$enRetard = (int) $st->fetchColumn();
$st = $db->prepare("SELECT COUNT(*) FROM ateliers WHERE statut = 'Planifié' AND date BETWEEN ? AND ?");
$st->execute([$suivDebut, $suivFin]);
$aVenir = (int) $st->fetchColumn();

// Qualité des ateliers réalisés du mois (04/10/2026) : fiches bilan (champs à
// choix seulement, pas la précision libre) et avis des stagiaires (notes et
// réponses fermées, pas les remarques).
function bilan_qualite(PDO $db, string $de, string $a): array
{
    $st = $db->prepare("SELECT fiche_bilan FROM ateliers WHERE statut = 'Réalisé' AND date BETWEEN ? AND ?");
    $st->execute([$de, $a]);
    $q = ['realises' => 0, 'fiches' => 0, 'objectif' => [], 'difficultes' => [], 'suite' => []];
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $f) {
        $q['realises']++;
        $b = api_bilan_lu($f);
        if (!is_array($b) || !$b) continue;
        $q['fiches']++;
        foreach (['objectif', 'suite'] as $k) if (is_string($b[$k] ?? null)) $q[$k][$b[$k]] = ($q[$k][$b[$k]] ?? 0) + 1;
        foreach ((array) ($b['difficultes'] ?? []) as $d) if (is_string($d)) $q['difficultes'][$d] = ($q['difficultes'][$d] ?? 0) + 1;
    }
    avis_schema($db);
    $st = $db->prepare("SELECT COUNT(*) n, COUNT(DISTINCT v.atelier_id) ateliers, AVG(v.attentes) attentes, AVG(v.clarte) clarte,
                               SUM(v.aise = 'Oui') aise_oui, SUM(v.aise IS NOT NULL) aise_n,
                               SUM(v.autonomie = 'Oui') autonomie_oui, SUM(v.autonomie IS NOT NULL) autonomie_n
                        FROM avis v JOIN ateliers a ON a.id = v.atelier_id
                        WHERE a.statut = 'Réalisé' AND a.date BETWEEN ? AND ?");
    $st->execute([$de, $a]);
    $q['avis'] = array_map(fn($v) => $v === null ? null : $v + 0, $st->fetch(PDO::FETCH_ASSOC));
    return $q;
}
$qualite = bilan_qualite($db, $debut, $fin);

// Mail : sujet, texte, HTML. Tout ce qui vient de la base est échappé.
function bilan_mail(string $mois, array $ce, array $prec, array $parConseiller, array $parPartenaire, int $enRetard, int $aVenir, bool $test, array $qualite = []): array
{
    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    [$an, $mm] = explode('-', $mois);
    $libelle = BILAN_MOIS_FR[(int) $mm] . " $an";
    $libPrec = BILAN_MOIS_FR[(int) date('n', strtotime("$mois-01 -1 month"))];
    $libSuiv = BILAN_MOIS_FR[(int) date('n', strtotime("$mois-01 +1 month"))];
    $s = fn($c, $k) => (int) ($c['statuts'][$k] ?? 0);
    $ecart = function (int $a, int $b): string {
        $d = $a - $b;
        return $d === 0 ? '=' : ($d > 0 ? "+$d" : (string) $d);
    };
    $sujet = ($test ? '[TEST] ' : '') . "Bilan des ateliers — $libelle";

    $lignesChiffres = [
        ['Ateliers au total', $ce['total'], $prec['total']],
        ['Réalisés', $s($ce, 'Réalisé'), $s($prec, 'Réalisé')],
        ['Planifiés', $s($ce, 'Planifié'), $s($prec, 'Planifié')],
        ['Annulés', $s($ce, 'Annulé'), $s($prec, 'Annulé')],
        ['Reportés', $s($ce, 'Reporté'), $s($prec, 'Reporté')],
        ['Non réalisés', $s($ce, 'Non réalisé'), $s($prec, 'Non réalisé')],
        ['Inscrits (ateliers réalisés)', $ce['inscrits'], $prec['inscrits']],
        ['Présents (ateliers réalisés)', $ce['presents'], $prec['presents']],
    ];
    $texte = "Bilan des ateliers numériques — $libelle\n\n";
    foreach ($lignesChiffres as [$l, $a, $b]) $texte .= "$l : $a ($libPrec : $b, " . $ecart($a, $b) . ")\n";
    $texte .= 'Taux de présence : ' . bilan_taux($ce['presents'], $ce['inscrits']) . " ($libPrec : " . bilan_taux($prec['presents'], $prec['inscrits']) . ")\n\nPar conseiller :\n";
    foreach ($parConseiller as $nom => $c) $texte .= "- $nom : " . $s($c, 'Réalisé') . " réalisé(s) sur {$c['total']}, {$c['presents']} présent(s) / {$c['inscrits']} inscrit(s)\n";
    $texte .= "\nPar partenaire :\n";
    foreach ($parPartenaire as $nom => $c) $texte .= "- $nom : " . $s($c, 'Réalisé') . " réalisé(s) sur {$c['total']}\n";
    // Qualité (fiches bilan, avis) : ordre des choix comme dans la fiche.
    $q = $qualite + ['realises' => 0, 'fiches' => 0, 'objectif' => [], 'difficultes' => [], 'suite' => [], 'avis' => []];
    $av = $q['avis'] + ['n' => 0, 'ateliers' => 0, 'attentes' => null, 'clarte' => null, 'aise_oui' => 0, 'aise_n' => 0, 'autonomie_oui' => 0, 'autonomie_n' => 0];
    $repart = function (array $compte, string $cle): string {
        $ordre = BILAN_CHOIX[$cle] ?? [];
        $cles = array_merge(array_values(array_filter($ordre, fn($k) => isset($compte[$k]))), array_diff(array_keys($compte), $ordre));
        return $cles ? implode(', ', array_map(fn($k) => "$k : {$compte[$k]}", $cles)) : '—';
    };
    $note = fn($v) => $v === null ? '—' : str_replace('.', ',', (string) round((float) $v, 1)) . '/5';
    $part = fn($o, $n) => $n ? "$o sur $n" : '—';
    $lignesQualite = [
        ['Fiches bilan remplies', $q['fiches'] . ' sur ' . $q['realises'] . ' atelier(s) réalisé(s)'],
        ['Objectif atteint', $repart($q['objectif'], 'objectif')],
        ['Difficultés rencontrées', $repart($q['difficultes'], 'difficultes')],
        ['Suite à donner', $repart($q['suite'], 'suite')],
        ['Avis des stagiaires', $av['n'] . ' avis sur ' . $av['ateliers'] . ' atelier(s)'],
        ['Réponse aux attentes', $note($av['attentes'])],
        ['Clarté', $note($av['clarte'])],
        ['Plus à l\'aise (« oui »)', $part((int) $av['aise_oui'], (int) $av['aise_n'])],
        ['Pourra refaire seul(e) (« oui »)', $part((int) $av['autonomie_oui'], (int) $av['autonomie_n'])],
    ];
    $texte .= "\nQualité des ateliers réalisés :\n";
    foreach ($lignesQualite as [$l, $v]) $texte .= "- $l : $v\n";
    $texte .= "\nPoints d'attention :\n- $enRetard atelier(s) de $libelle encore « Planifié » alors que leur date est passée\n- $aVenir atelier(s) planifié(s) en $libSuiv\n\nApplication : " . BILAN_URL_APPLI . "\n";

    $td = 'padding:6px 10px;border-bottom:1px solid #e2e8f0';
    $th = 'padding:8px 10px;text-align:left;color:#718096;background:#f7fafc';
    $tableau = function (array $entetes, array $lignes) use ($h, $td, $th): string {
        $r = '<table style="width:100%;border-collapse:collapse;font-size:13px;margin:6px 0 18px"><thead><tr>';
        foreach ($entetes as $i => $e) $r .= '<th style="' . $th . ($i ? ';text-align:right' : '') . '">' . $h($e) . '</th>';
        $r .= '</tr></thead><tbody>';
        foreach ($lignes as $l) {
            $r .= '<tr>';
            foreach ($l as $i => $v) $r .= '<td style="' . $td . ($i ? ';text-align:right' : ';font-weight:600') . '">' . $h($v) . '</td>';
            $r .= '</tr>';
        }
        return $r . '</tbody></table>';
    };
    $chiffres = array_map(fn($l) => [$l[0], $l[1], $l[2], $ecart($l[1], $l[2])], $lignesChiffres);
    $chiffres[] = ['Taux de présence', bilan_taux($ce['presents'], $ce['inscrits']), bilan_taux($prec['presents'], $prec['inscrits']), ''];
    $cons = [];
    foreach ($parConseiller as $nom => $c) $cons[] = [$nom, $s($c, 'Réalisé') . ' / ' . $c['total'], $c['presents'] . ' / ' . $c['inscrits'], bilan_taux($c['presents'], $c['inscrits'])];
    $part = [];
    foreach ($parPartenaire as $nom => $c) $part[] = [$nom, $s($c, 'Réalisé'), $c['total']];
    $titre = fn($t) => '<h3 style="color:#1e3a8a;font-size:15px;margin:14px 0 4px">' . $h($t) . '</h3>';
    $html = '<div style="font-family:sans-serif;max-width:680px;margin:0 auto">'
          . '<div style="background:#1e3a8a;color:#fff;padding:20px 24px;border-radius:8px 8px 0 0"><h2 style="margin:0;font-size:18px">Bilan des ateliers — ' . $h($libelle) . '</h2>'
          . ($test ? '<p style="margin:6px 0 0;font-size:12px;opacity:.8">— MAIL DE TEST —</p>' : '') . '</div>'
          . '<div style="background:#fff;padding:12px 24px 20px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px">'
          . $titre('Chiffres du mois') . $tableau(['', ucfirst($libelle), ucfirst($libPrec), 'Écart'], $chiffres)
          . $titre('Par conseiller') . ($cons ? $tableau(['Conseiller', 'Réalisés / total', 'Présents / inscrits', 'Taux'], $cons) : '<p style="color:#718096">Aucun atelier ce mois-ci.</p>')
          . $titre('Par partenaire') . ($part ? $tableau(['Partenaire', 'Réalisés', 'Total'], $part) : '<p style="color:#718096">Aucun atelier ce mois-ci.</p>')
          . $titre('Qualité des ateliers réalisés') . $tableau(['', ucfirst($libelle)], $lignesQualite)
          . '<p style="color:#718096;font-size:12px;margin-top:-10px">Avis anonymes des stagiaires ; les remarques libres ne sont pas reprises dans ce mail (Mes bilans / Dashboard de l\'application).</p>'
          . $titre('Points d\'attention')
          . '<ul style="color:#4a5568;font-size:13px;padding-left:18px">'
          . '<li><strong>' . $enRetard . '</strong> atelier(s) de ' . $h($libelle) . ' encore « Planifié » alors que leur date est passée</li>'
          . '<li><strong>' . $aVenir . '</strong> atelier(s) planifié(s) en ' . $h($libSuiv) . '</li></ul>'
          . '<p style="color:#718096;font-size:12px">Inscrits et présents : ateliers réalisés seulement. Un atelier mis à jour après l\'envoi n\'est pas repris ici.</p>'
          . '<div style="text-align:center;margin-top:16px"><a href="' . $h(BILAN_URL_APPLI) . '" style="display:inline-block;background:#1e3a8a;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700"><span style="color:#ffffff">Ouvrir l\'application</span></a></div>'
          . '</div></div>';
    return [$sujet, $texte, $html];
}

[$sujet, $texte, $html] = bilan_mail($mois, $ce, $prec, $parConseiller, $parPartenaire, $enRetard, $aVenir, $test !== null, $qualite);

if ($test !== null) {
    $ok = mail_envoyer($test, $sujet, $texte, $html);
    echo "bilan $mois : test " . ($ok ? 'envoyé' : 'ÉCHEC') . " ({$ce['total']} atelier(s))\n";
    exit($ok ? 0 : 1);
}

$cfg = api_config_base($db);
$emails = api_json($cfg['emails'] ?? '', []);
if (!is_array($emails)) $emails = [];
$noms = $db->query("SELECT conseiller FROM comptes WHERE role = 'superviseur' AND actif = 1")->fetchAll(PDO::FETCH_COLUMN);
$envoyes = 0; $echecs = 0; $sansAdresse = 0;
// Un seul bilan par mois et par destinataire, même si la tâche est lancée
// plusieurs fois (tâche mal réglée, relance) : le journal fait foi.
$dejaEnvoye = $db->prepare("SELECT COUNT(*) FROM journal WHERE action = 'bilanMensuel' AND conseiller = ? AND ref = ?");
$doublons = 0;
foreach ($noms as $nom) {
    $dejaEnvoye->execute([$nom, $mois]);
    if ((int) $dejaEnvoye->fetchColumn() > 0) { $doublons++; continue; }
    $e = $emails[$nom] ?? '';
    $adresse = trim((string) (is_array($e) ? ($e['email'] ?? '') : $e));
    if (!filter_var($adresse, FILTER_VALIDATE_EMAIL)) { $sansAdresse++; continue; }
    if (mail_envoyer($adresse, $sujet, $texte, $html)) {
        $envoyes++;
        api_journal($db, 'bilanMensuel', (string) $nom, $mois, '', 1, 0, '', 'tache');
    } else {
        $echecs++;
    }
}
printf("bilan %s : %d mail(s) envoyé(s), %d échec(s), %d superviseur(s) sans adresse, %d déjà servi(s)\n", $mois, $envoyes, $echecs, $sansAdresse, $doublons);
exit($echecs ? 1 : 0);
