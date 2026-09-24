-- Schéma de la base des ateliers (refonte GAS + Sheets → PHP + MySQL).
-- Spécification : migration/INVENTAIRE.md §1, bloc AGORA AG-010.
--
-- Rejouable : chaque table n'est créée que si elle n'existe pas encore.
-- Appliqué par l'import (api/import.php, via lib/import.php) avant de charger les données.
-- Encodage utf8mb4 partout : accents et émojis des remarques passent.

-- Un atelier par ligne. Reprend les 20 colonnes non-matériel de la feuille
-- « Ateliers_next_step ». Une valeur vide dans la feuille devient NULL pour
-- les dates et les nombres ; l'API la renverra en chaîne vide '' comme le
-- faisait GAS (le formulaire distingue « presents vide » de « 0 présent »).
CREATE TABLE IF NOT EXISTS ateliers (
  id                        VARCHAR(64)  NOT NULL,           -- _id, fourni par le client
  n                         INT          NULL,               -- _n, numéro historique de ligne (non unique)
  statut                    VARCHAR(50)  NOT NULL DEFAULT '',
  date                      DATE         NOT NULL,
  horaire                   CHAR(5)      NULL,               -- 'HH:mm'
  ampm                      VARCHAR(10)  NOT NULL DEFAULT '',
  orienteur                 TEXT         NOT NULL,
  commune                   VARCHAR(255) NOT NULL DEFAULT '',
  lieu                      TEXT         NOT NULL,
  thematique                TEXT         NOT NULL,
  inscrits                  INT          NULL,
  presents                  INT          NULL,
  public                    VARCHAR(255) NOT NULL DEFAULT '',
  conseiller                VARCHAR(100) NOT NULL DEFAULT '',
  co_animateur              TEXT         NOT NULL,
  residence                 TEXT         NOT NULL,
  remarques                 TEXT         NOT NULL,
  nb_ordinateurs            INT          NULL,
  date_prelevement_materiel DATE         NULL,
  date_retour_materiel      DATE         NULL,
  PRIMARY KEY (id),
  KEY idx_date (date),
  KEY idx_conseiller (conseiller)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Matériel emprunté : une ligne par (atelier, matériel). Remplace les
-- colonnes OUI/vide de la feuille : un nouveau matériel n'ajoute plus de
-- colonne. Le nom est celui de l'en-tête d'origine (ex. « Classe mobile »).
CREATE TABLE IF NOT EXISTS ateliers_materiel (
  atelier_id VARCHAR(64)  NOT NULL,
  materiel   VARCHAR(100) NOT NULL,
  PRIMARY KEY (atelier_id, materiel),
  CONSTRAINT fk_materiel_atelier FOREIGN KEY (atelier_id)
    REFERENCES ateliers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paires clé/valeur de la feuille « Config » (listes, couleurs, e-mails,
-- visibilité, maintenance…). Valeurs gardées en texte, souvent du JSON.
CREATE TABLE IF NOT EXISTS config (
  cle    VARCHAR(100) NOT NULL,
  valeur MEDIUMTEXT   NOT NULL,
  PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comptes des conseillers. Jamais de mot de passe en clair :
-- hash = password_hash(sha256_hex(mot de passe)). Le SHA-256 intermédiaire
-- permet de reprendre les empreintes existantes de la feuille sans connaître
-- les mots de passe. hash NULL : compte sans mot de passe utilisable, à
-- réinitialiser par un admin. doit_changer : mot de passe à changer à la
-- prochaine connexion (ancien mot de passe resté en clair dans la feuille).
CREATE TABLE IF NOT EXISTS comptes (
  conseiller   VARCHAR(100) NOT NULL,
  hash         VARCHAR(255) NULL,
  role         VARCHAR(20)  NOT NULL DEFAULT 'user',
  actif        TINYINT(1)   NOT NULL DEFAULT 1,
  doit_changer TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (conseiller)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des connexions et des actions (feuille « Logs_Connexion », dont
-- les deux formats historiques sont unifiés à l'import).
-- RGPD : conservé 12 mois (décision de l'utilisateur, 24/09/2026), purgé à
-- chaque connexion réussie (API_JOURNAL_MOIS, lib/api.php).
CREATE TABLE IF NOT EXISTS journal (
  id          BIGINT       NOT NULL AUTO_INCREMENT,
  horodatage  DATETIME     NOT NULL,                -- heure de Paris
  action      VARCHAR(30)  NOT NULL DEFAULT '',
  conseiller  VARCHAR(100) NOT NULL DEFAULT '',
  ref         VARCHAR(100) NOT NULL DEFAULT '',
  role        VARCHAR(20)  NOT NULL DEFAULT '',
  succes      TINYINT(1)   NOT NULL DEFAULT 0,
  tentatives  INT          NOT NULL DEFAULT 0,
  user_agent  VARCHAR(500) NOT NULL DEFAULT '',
  source      VARCHAR(50)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY idx_horodatage (horodatage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jetons de connexion (6 h). On ne garde que l'empreinte SHA-256 du jeton :
-- une fuite de la table ne donne aucun jeton utilisable.
CREATE TABLE IF NOT EXISTS sessions (
  jeton_hash CHAR(64)     NOT NULL,
  conseiller VARCHAR(100) NOT NULL,
  role       VARCHAR(20)  NOT NULL,
  expire     DATETIME     NOT NULL,
  PRIMARY KEY (jeton_hash),
  KEY idx_expire (expire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anti-force brute : 5 échecs → blocage 15 min (comme le GAS).
CREATE TABLE IF NOT EXISTS tentatives (
  conseiller    VARCHAR(100) NOT NULL,
  nb            INT          NOT NULL DEFAULT 0,
  bloque_jusqua DATETIME     NULL,
  PRIMARY KEY (conseiller)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- État technique de la base (date du dernier import, empreinte du fichier,
-- verrou d'import après la bascule). Jamais renvoyé par l'API.
CREATE TABLE IF NOT EXISTS meta (
  cle    VARCHAR(100) NOT NULL,
  valeur TEXT         NOT NULL,
  PRIMARY KEY (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- « Mot de passe oublié » (AG-013) : une ligne par lien envoyé par mail.
-- Seule l'empreinte SHA-256 du jeton est gardée ; lien valable 30 min,
-- usage unique. Sert aussi à limiter les demandes (3 par heure et par compte).
CREATE TABLE IF NOT EXISTS reinitialisations (
  jeton_hash CHAR(64)     NOT NULL,
  conseiller VARCHAR(100) NOT NULL,
  cree       DATETIME     NOT NULL,
  expire     DATETIME     NOT NULL,
  utilise    TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (jeton_hash),
  KEY idx_conseiller_cree (conseiller, cree)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
