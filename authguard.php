<?php
// authguard.php — Vérification de session
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['connecte']) || $_SESSION['connecte'] !== true) {
    header('Location: Login.php');
    exit;
}

function exiger_role(int $role_requis): void {
    if ((int)$_SESSION['user_role'] !== $role_requis) {
        header('Location: 403.php');
        exit;
    }
}