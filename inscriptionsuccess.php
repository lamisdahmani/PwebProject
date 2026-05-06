<?php
session_start();
if (!isset($_SESSION['inscription_ok'])) {
    header('Location: Register.html');
    exit;
}
// Récupère le nom de l'utilisateur inscrit et le sécurise contre les injections XSS
$nom = htmlspecialchars($_SESSION['new_user_nom']);
unset($_SESSION['inscription_ok'], $_SESSION['new_user_nom']); //var session temp 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Inscription réussie</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand">Tirage au sort</div>
  </nav>
  <main class="form-page">
    <div class="form-container" style="text-align:center; padding: 3rem;">
      <div style="font-size:3rem; margin-bottom:1rem;"></div>
      <h1 style="color:var(--green-dark); margin-bottom:1rem;">
        Inscription réussie, <?= $nom ?> !
      </h1>
      <p style="color:var(--text-muted); margin-bottom:2rem;">
        Votre compte est <strong>en attente de validation</strong> par un administrateur.<br>
        Vous recevrez une notification dès qu'il sera activé.
      </p>
      <a href="Login.html" class="btn-submit" style="display:inline-block; padding: 0.75rem 2rem;">
        Se connecter
      </a>
    </div>
  </main>
</body>
</html>