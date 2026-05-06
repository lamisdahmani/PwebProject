<?php
// ============================================================
//  authguard.php — Garde d'authentification
//  À inclure en haut de chaque page protégée :
//      session_start();
//      require_once 'authguard.php';
// ============================================================

// Si session non démarrée, la démarrer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rediriger si non connecté
if (!isset($_SESSION['connecte']) || $_SESSION['connecte'] !== true) {
    header('Location: Login.php');
    exit;
}

/**
 * Exiger un rôle précis.
 *  1 = administrateur
 *  2 = utilisateur simple
 *
 * @param int $role_requis
 */
function exiger_role(int $role_requis): void
{
    if ((int)$_SESSION['user_role'] !== $role_requis) {
        header('Location: 403.php');
        exit;
    }
}