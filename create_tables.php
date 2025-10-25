<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'database.php';

try {
    $pdo = Database::getInstance();
    $prefix = DB_PREFIX;

    $sql = "
    CREATE TABLE IF NOT EXISTS `{$prefix}users` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
        `nom` VARCHAR(100),
        `email` VARCHAR(255),
        `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `dernier_login` TIMESTAMP NULL,
        `actif` BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `{$prefix}conducteurs` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `nom` VARCHAR(100) NOT NULL,
        `prenom` VARCHAR(100) NOT NULL,
        `permis` JSON NOT NULL COMMENT 'Liste des permis du conducteur',
        `contact` VARCHAR(100),
        `experience` INT DEFAULT 0,
        `tournees_maitrisees` JSON COMMENT 'IDs des tournées que le conducteur maîtrise',
        `tournee_titulaire` INT COMMENT 'ID de la tournée dont le conducteur est titulaire',
        `statut_entreprise` ENUM('CDI','CDD','sous-traitant','interimaire') DEFAULT 'CDI',
        `repos_recurrents` JSON COMMENT 'Jours de repos récurrents',
        `conges` JSON COMMENT 'Périodes de congés',
        `statut_temporaire` ENUM('disponible','conge','malade','formation','repos') DEFAULT 'disponible',
        `statut_temporaire_fin` DATE NULL COMMENT 'Date de fin du statut temporaire',
        `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `{$prefix}tournees` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `nom` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `zone_geo` VARCHAR(100),
        `type_vehicule` VARCHAR(50),
        `type_tournee` VARCHAR(100) COMMENT 'Type de tournée pour le tri',
        `permis_requis` JSON NOT NULL COMMENT 'Liste des permis requis pour cette tournée',
        `difficulte` INT DEFAULT 1,
        `duree` ENUM('journee','matin','apres-midi') DEFAULT 'journee',
        `date_creation` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `{$prefix}planning` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `date` DATE NOT NULL,
        `periode` ENUM('matin','apres-midi') NOT NULL,
        `conducteur_id` INT,
        `tournee_id` INT NOT NULL,
        `score_ia` FLOAT DEFAULT 0,
        `statut` VARCHAR(50) DEFAULT 'planifie',
        FOREIGN KEY (`conducteur_id`) REFERENCES `{$prefix}conducteurs`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`tournee_id`) REFERENCES `{$prefix}tournees`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_conducteur_periode` (`date`, `periode`, `conducteur_id`),
        UNIQUE KEY `unique_tournee_periode` (`date`, `periode`, `tournee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS `{$prefix}config` (
        `cle` VARCHAR(100) PRIMARY KEY,
        `valeur` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $pdo->exec($sql);

    echo "Tables créées ou déjà présentes (préfixe: {$prefix})";
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage();
}
