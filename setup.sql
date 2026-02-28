-- ============================================================
--  Flor de Tango – Base de données
--  Exécuter une seule fois :  mysql -u root -p < setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `flor_de_tango`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `flor_de_tango`;

CREATE TABLE IF NOT EXISTS `inscriptions` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(100) NOT NULL                   COMMENT 'Nom complet',
  `email`       VARCHAR(150) NOT NULL                   COMMENT 'Adresse e-mail',
  `telephone`   VARCHAR(30)  NOT NULL DEFAULT ''        COMMENT 'Téléphone (optionnel)',
  `cours`       VARCHAR(200) NOT NULL                   COMMENT 'Cours sélectionné',
  `message`     TEXT                  DEFAULT NULL      COMMENT 'Message libre (optionnel)',
  `fichier_nom` VARCHAR(255)          DEFAULT NULL      COMMENT 'Nom original du fichier uploadé',
  `fichier`     VARCHAR(64)           DEFAULT NULL      COMMENT 'Nom de stockage (aléatoire)',
  `statut`      ENUM('en_attente','valide') NOT NULL DEFAULT 'en_attente',
  `cree_le`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_statut`   (`statut`),
  KEY `idx_cree_le`  (`cree_le`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
