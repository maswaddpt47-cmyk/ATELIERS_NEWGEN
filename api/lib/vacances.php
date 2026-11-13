<?php
// Vacances scolaires de l'académie de Bordeaux (AG-057, demande de
// l'utilisateur du 13/11/2026) : un atelier avec un collège ne se programme
// pas pendant les vacances sans confirmation.
//
// Source : calendrier officiel du ministère, jeu ouvert « fr-en-calendrier-
// scolaire » de data.education.gouv.fr (données publiques, aucune donnée
// d'usager envoyée). Lu par le serveur seulement, jamais par les navigateurs,
// gardé en base (config « vacances_cache ») et transmis dans getAll : aucun
// appel de plus au démarrage des pages.
//
// Rafraîchi au plus une fois par 24 h ; après un échec, nouvel essai au plus
// une fois par heure ; délai de 3 s. En échec, l'ancien cache continue de
// servir. Diagnostic du 13/11/2026 : HTTP 200 depuis Alwaysdata.

const VACANCES_SOURCE = 'https://data.education.gouv.fr/api/explore/v2.1/catalog/datasets/fr-en-calendrier-scolaire/records'
    . '?where=location%3D%22Bordeaux%22%20and%20end_date%3E%3D%22{DEPUIS}%22&order_by=start_date&limit=100'
    . '&select=description,population,start_date,end_date';
const VACANCES_FRAIS = 86400;     // secondes avant de relire la source
const VACANCES_REESSAI = 3600;    // après un échec
const VACANCES_DELAI = 3;         // secondes, ne retarde jamais getAll davantage

// Jour de Paris d'une date de la source (« 2026-10-16T22:00:00+00:00 »).
function vacances_jour(mixed $v): ?string
{
    $v = (string) $v;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) return null;
    try { $t = new DateTimeImmutable($v); } catch (Exception) { return null; }
    return $t->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
}

// Réponse de la source → ['periodes' => [{debut, fin (dernier jour, inclus),
// libelle}] triées, 'au' => dernier jour couvert], ou null si la réponse n'a
// pas la forme attendue.
// end_date = jour de la reprise des cours, exclu (Toussaint 2026 : reprise le
// lundi 02/11) ; end_date = start_date = une seule journée (pont de
// l'Ascension 2027 : 07/05). « Enseignants » ignoré.
// « Début des … » (été 2028, bornes égales) n'est PAS une journée : seule la
// date de départ est publiée (Codex, AG-057). Le calendrier n'est alors connu
// que jusqu'à la veille ; au-delà, les pages suspendent le contrôle.
function vacances_periodes(string $json): ?array
{
    $d = json_decode($json, true);
    if (!is_array($d) || !is_array($d['results'] ?? null)) return null;
    $out = []; $bornes = [];
    foreach ($d['results'] as $r) {
        if (!is_array($r) || trim((string) ($r['population'] ?? '')) === 'Enseignants') continue;
        $deb = vacances_jour($r['start_date'] ?? '');
        $fin = vacances_jour($r['end_date'] ?? '');
        if ($deb === null || $fin === null) continue;
        $lib = trim((string) ($r['description'] ?? '')) ?: 'Vacances scolaires';
        if (preg_match('/^d[ée]but\b/iu', $lib)) { $bornes[] = $deb; continue; }
        $dernier = $fin > $deb ? (new DateTimeImmutable($fin))->modify('-1 day')->format('Y-m-d') : $deb;
        $out["$deb|$dernier|$lib"] = ['debut' => $deb, 'fin' => $dernier, 'libelle' => $lib];
    }
    $out = array_values($out);
    usort($out, fn($a, $b) => strcmp($a['debut'], $b['debut']));
    $au = $bornes ? (new DateTimeImmutable(min($bornes)))->modify('-1 day')->format('Y-m-d')
        : ($out ? max(array_column($out, 'fin')) : '');
    return ['periodes' => array_values(array_filter($out, fn($p) => $au === '' || $p['debut'] <= $au)), 'au' => $au];
}

// Calendrier à transmettre aux pages ; rafraîchit le cache si besoin.
// du / au = jours couverts (au-delà : inconnu) ; maj vide = jamais lu : les
// pages suspendent alors le contrôle.
function vacances_scolaires(PDO $db): array
{
    $cache = api_json((string) (api_config_base($db)['vacances_cache'] ?? ''), []);
    $periodes = is_array($cache['periodes'] ?? null) ? $cache['periodes'] : [];
    $du = (string) ($cache['du'] ?? ''); $au = (string) ($cache['au'] ?? '');
    $maj = (int) ($cache['maj'] ?? 0);
    $essai = (int) ($cache['essai'] ?? 0);
    $now = time();
    if ($now - $maj > VACANCES_FRAIS && $now - $essai > VACANCES_REESSAI) {
        $depuis = (date('Y') - 1) . '-09-01';
        $src = str_replace('{DEPUIS}', $depuis, (string) (api_config()['vacances_source'] ?? VACANCES_SOURCE));
        $ctx = stream_context_create(['http' => ['timeout' => VACANCES_DELAI, 'ignore_errors' => false]]);
        $json = @file_get_contents($src, false, $ctx);
        $p = is_string($json) ? vacances_periodes($json) : null;
        if ($p !== null && $p['periodes'] !== []) { $periodes = $p['periodes']; $du = $depuis; $au = $p['au']; $maj = $now; }
        $essai = $now;
        $db->prepare('INSERT INTO config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
            ->execute(['vacances_cache', json_encode(['maj' => $maj, 'essai' => $essai, 'du' => $du, 'au' => $au, 'periodes' => $periodes], JSON_UNESCAPED_UNICODE)]);
    }
    return ['periodes' => $periodes, 'du' => $du, 'au' => $au, 'maj' => $maj ? date('Y-m-d H:i', $maj) : ''];
}
