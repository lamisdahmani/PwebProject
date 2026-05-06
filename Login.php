<?php
session_start();

$erreur = '';
if (isset($_SESSION['login_erreur'])) {
    $erreur = $_SESSION['login_erreur'];
    unset($_SESSION['login_erreur']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Tirage au sort</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
  <nav class="navbar">
    <div class="nav-brand">Tirage au sort</div>
    <ul class="nav-links">
      <li><a href="index.html">Accueil</a></li>
      <li><a href="Modalites.html">Modalités</a></li>
      <li><a href="Regles.html">Règles</a></li>
      <li><a href="Resultats.html">Résultats</a></li>
    </ul>
    <div class="nav-actions">
      <a href="Register.html" class="btn btn-outline">S'inscrire</a>
      <a href="Login.php" class="btn btn-primary">Se connecter</a>
    </div>
  </nav>

  <main class="form-page">
    <div class="form-container">
      <header class="form-header">
        <div class="divider-line"></div>
        <h1>Formulaire de connexion</h1>
        <div class="divider-line"></div>
      </header>

      <?php if ($erreur): ?>
      <div style="background:#fce4e4;color:#b71c1c;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.88rem;">
        ⚠️ <?= htmlspecialchars($erreur) ?>
      </div>
      <?php endif; ?>

      <form action="traitementlogin.php" method="POST" novalidate>
        <div class="form-row">
          <div class="form-group">
            <label for="nin">NIN <span class="required">*</span></label>
            <input type="text" id="nin" name="nin" placeholder="18 chiffres"
                   inputmode="numeric" pattern="[0-9]{18}" maxlength="18" required/>
            <span class="error-msg" id="err-nin"></span>
          </div>
          <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" id="email" name="email" placeholder="username@gmail.com" required/>
            <span class="error-msg" id="err-email"></span>
          </div>
        </div>

        <div class="form-row row-center">
          <div class="form-group" style="max-width:400px;width:100%;">
            <label for="password">Mot de passe <span class="required">*</span></label>
            <input type="password" id="password" name="mot_de_passe" placeholder="**********" required/>
            <span class="error-msg" id="err-password"></span>
          </div>
        </div>

        <div style="background:#e8f5e9;color:#1b5e35;padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-size:0.82rem;">
          <strong>Comptes de test :</strong><br>
          Admin : NIN=111111111111111111 | email=admin@hadj.dz | mdp=Admin123!<br>
          User  : NIN=222222222222222222 | email=lamis@gmail.com | mdp=User1234!
        </div>

        <div class="form-submit">
          <button type="submit" class="btn-submit">Se connecter</button>
        </div>
        <p class="form-login-link">Pas encore de compte ? <a href="Register.html">S'inscrire</a></p>
      </form>
    </div>
  </main>
  <script src="Login.js"></script>
</body>
</html>