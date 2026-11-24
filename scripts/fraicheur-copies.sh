#!/usr/bin/env bash
# Fraîcheur des copies (24/11/2026) : mêmes règles que les tuiles Admin → Sauvegardes
# (AG-052) : copie de nuit = fichier daté de 02:00 à 04:59 (heure de Paris),
# copie chiffrée hors site = marqueur .derniere-copie-chiffree ; seuil 26 h.
# Entrée standard : le contenu de ~/sauvegardes/ (un nom par ligne) puis une
# ligne « CHIFFREE=AAAA-MM-JJ HH:MM... » (vide si le marqueur manque).
# Sortie : « ALERTE : ... » (code 1) ou « OK » (code 0). Aucun contenu de copie
# n'est lu : seulement des noms de fichier et une date.
# MAINTENANT (secondes depuis 1970) et SEUIL_H servent aux essais.
set -u
SEUIL_H=${SEUIL_H:-26}
MAINTENANT=${MAINTENANT:-$(date +%s)}
nuit=""; chiffree=""
while IFS= read -r l; do
  l=${l%$'\r'}
  if [[ "$l" =~ ^ateliers-([0-9]{4}-[0-9]{2}-[0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})\.sql\.gz$ ]]; then
    h=${BASH_REMATCH[2]}
    [[ "$h" == 02 || "$h" == 03 || "$h" == 04 ]] || continue
    d="${BASH_REMATCH[1]} $h:${BASH_REMATCH[3]}:${BASH_REMATCH[4]}"
    [[ -z "$nuit" || "$d" > "$nuit" ]] && nuit="$d"
  elif [[ "$l" == CHIFFREE=* ]]; then
    chiffree=${l#CHIFFREE=}
  fi
done

echec=0
verifier() { # $1 libellé, $2 date « AAAA-MM-JJ HH:MM[:SS] » en heure de Paris ou vide
  local ts
  if [[ ! "$2" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}\ [0-9]{2}:[0-9]{2} ]] || ! ts=$(TZ=Europe/Paris date -d "${2:0:16}" +%s 2>/dev/null); then
    echo "ALERTE : $1 — aucune date lisible (copie absente ou marqueur manquant)"; echec=1; return
  fi
  local age=$(( (MAINTENANT - ts) / 3600 ))
  if (( MAINTENANT - ts > SEUIL_H * 3600 )); then
    echo "ALERTE : $1 — dernière copie du ${2:0:16} (Paris), il y a plus de ${SEUIL_H} h (${age} h)"; echec=1
  else
    echo "$1 — ${2:0:16} (Paris), il y a ${age} h"
  fi
}
verifier "copie de nuit (Alwaysdata)" "$nuit"
verifier "copie chiffrée hors site" "$chiffree"
(( echec == 0 )) && echo "OK"
exit $echec
