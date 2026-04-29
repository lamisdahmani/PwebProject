<?php
// Inclure en haut de chaque page protégée : require_once 'auth_guard.php';
session_start();

if (!isset($_SESSION['connecte']) || $_SESSION['connecte'] !== true) {
    header('Location: Login.html');
    exit;
}

// Vérification du rôle : passer $role_requis = 1 pour admin
function exiger_role($role_requis) {
    if ($_SESSION['user_role'] !== $role_requis) {
        header('Location: 403.php');
        exit;
    }
}
?>