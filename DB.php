<?php
// ============================================================
//  db.php — Connexion centralisée à MySQL (PDO)
//  À inclure en haut de chaque script PHP qui touche la BDD :
//      require_once 'db.php';
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'hadj_tirage');
define('DB_USER', 'root');        // ← adapter selon votre config WAMP/XAMPP
define('DB_PASS', '');            // ← adapter
define('DB_CHARSET', 'utf8mb4');

/**
 * Retourne l'instance PDO (singleton).
 * Toutes les requêtes passent par cette connexion.
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // vraies requêtes préparées
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Ne jamais afficher le message brut en production
            error_log('Connexion BDD échouée : ' . $e->getMessage());
            die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
        }
    }

    return $pdo;
}