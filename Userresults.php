<?php
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(2);

$db      = get_db();
$id_user = (int)$_SESSION['user_id'];

// Historique participations
$stmt = $db->prepare(
    "SELECT t.annee, t.date_tirage, r.gagnant, i.nb_participations
     FROM inscriptions i
     JOIN tirages t   ON t.id_tirage=i.id_tirage
     LEFT JOIN resultats r ON r.id_inscription=i.id_inscription
     WHERE i.id_utilisateur=?
     ORDER BY t.annee DESC"
);
$stmt->execute([$id_user]);
$historique = $stmt->fetchAll();

$nb_part     = count($historique);
$nb_victoires = 0;
$nb_pertes    = 0;
foreach ($historique as $h) {
    if ($h['gagnant']===null) continue;
    if ((int)$h['gagnant']===1) $nb_victoires++;
    else $nb_pertes++;
}
$bonus = $nb_pertes * 5;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Mes Résultats — Hadj</title>
<link rel="stylesheet" href="UserView.css">
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-logo-text">Tirage au sort</div>
      <div class="sidebar-logo-sub">Hadj</div>
    </div>
    <div class="nav-label">Navigation</div>
    <a href="Userdash.php" class="nav-item">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><path d="M2 6.5L8 2l6 4.5V14H10v-3.5H6V14H2V6.5z" fill="currentColor"/></svg>
      Dashboard
    </a>
    <a href="UserResults.php" class="nav-item active">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M5 7h6M5 10h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Mes résultats
    </a>
    <div class="sidebar-footer">
      <div class="avatar"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="2.5" fill="#d6e9d6"/><path d="M3 13c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="#d6e9d6" stroke-width="1.2" fill="none"/></svg></div>
      <span class="footer-name"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
      <div class="logout-btn" onclick="openLogoutModal()">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9.5 7H3m0 0 2-2M3 7l2 2M6 4V2.5A.5.5 0 0 1 6.5 2H12a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5H6.5a.5.5 0 0 1-.5-.5V10" stroke="#d6e9d6" stroke-width="1.2" stroke-linecap="round"/></svg>
      </div>
    </div>
  </div>

  <div class="main">
    <h1 class="hero-title" style="color:var(--text-dark);margin-bottom:4px;">Mes résultats</h1>
    <p class="hero-sub" style="color:var(--text-muted);margin-bottom:24px;">Historique de toutes vos participations</p>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon stat-icon--green"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
        <div class="stat-value"><?= str_pad($nb_part,2,'0',STR_PAD_LEFT) ?></div>
        <div class="stat-label">Participations totales</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--amber"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
        <div class="stat-value"><?= str_pad($nb_victoires,2,'0',STR_PAD_LEFT) ?></div>
        <div class="stat-label">Fois sélectionné(e)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon stat-icon--teal"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <div class="stat-value">+<?= $bonus ?>%</div>
        <div class="stat-label">Bonus de chances accumulé</div>
      </div>
    </div>

    <div class="results-container">
      <div class="results-header">Historique des participations</div>
      <div class="table-wrapper">
        <table class="results-table">
          <thead>
            <tr><th>ÉDITION</th><th>DATE DU TIRAGE</th><th>RÉSULTAT</th><th>POIDS DANS L'URNE</th></tr>
          </thead>
          <tbody>
            <?php if (empty($historique)): ?>
            <tr><td colspan="4" style="text-align:center;color:#6b8c6b;padding:2rem;">Aucune participation enregistrée.</td></tr>
            <?php else: foreach($historique as $h): ?>
            <tr>
              <td><span class="edition-tag">🕋 Hadj <?= htmlspecialchars($h['annee']) ?></span></td>
              <td><?= $h['date_tirage'] ? date('d/m/Y',strtotime($h['date_tirage'])) : '—' ?></td>
              <td>
                <?php if ($h['gagnant']===null): ?>
                  <span class="status-badge status--pending">● En attente</span>
                <?php elseif ((int)$h['gagnant']===1): ?>
                  <span class="status-badge status--win">● Gagnant</span>
                <?php else: ?>
                  <span class="status-badge status--lose">● Non Gagnant</span>
                <?php endif; ?>
              </td>
              <td><span class="bonus-tag">x<?= (int)$h['nb_participations'] ?></span></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="info-alert">
      <div class="alert-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#1b5e35" stroke-width="2"/><path d="M12 7V13" stroke="#1b5e35" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="17" r="1" fill="#1b5e35"/></svg></div>
      <div class="alert-text">Chaque participation sans succès vous accorde un <strong>bonus de +5%</strong> et vous fait apparaître une fois de plus dans l'urne du prochain tirage.</div>
    </div>
  </div>
</div>

<div id="logoutModal" class="modal-overlay">
  <div class="modal-card">
    <div class="modal-title">Se déconnecter ?</div>
    <div class="modal-text">Êtes-vous sûr(e) de vouloir quitter votre session ?</div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeLogoutModal()">Annuler</button>
      <a href="logout.php" class="btn-confirm">Se déconnecter</a>
    </div>
  </div>
</div>
<script>
const modal=document.getElementById('logoutModal');
function openLogoutModal(){modal.style.display='flex';}
function closeLogoutModal(){modal.style.display='none';}
window.onclick=e=>{if(e.target===modal)closeLogoutModal();};
</script>
</body>
</html>