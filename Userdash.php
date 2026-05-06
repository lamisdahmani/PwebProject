<?php
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(2);

$db      = get_db();
$id_user = (int)$_SESSION['user_id'];

// Infos utilisateur
$stmt = $db->prepare('SELECT u.*,w.nom_wilaya FROM utilisateurs u JOIN wilayas w ON w.id_wilaya=u.id_wilaya WHERE u.id_utilisateur=?');
$stmt->execute([$id_user]);
$user = $stmt->fetch();

// Nb participations
$stmt = $db->prepare('SELECT COUNT(*) FROM inscriptions WHERE id_utilisateur=?');
$stmt->execute([$id_user]);
$nb_part = (int)$stmt->fetchColumn();

// Tirage actif
$auj = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM tirages WHERE etat=2 AND date_ouverture_inscr<=? AND date_cloture_inscr>=? LIMIT 1");
$stmt->execute([$auj,$auj]);
$tirage_ouvert = $stmt->fetch();

// Déjà inscrit ce tirage ?
$deja_inscrit = false;
if ($tirage_ouvert) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM inscriptions WHERE id_utilisateur=? AND id_tirage=?');
    $stmt->execute([$id_user,(int)$tirage_ouvert['id_tirage']]);
    $deja_inscrit = (int)$stmt->fetchColumn() > 0;
}

// Prochain tirage (pour affichage)
$stmt = $db->prepare("SELECT * FROM tirages WHERE etat<4 ORDER BY annee DESC LIMIT 1");
$stmt->execute();
$prochain = $stmt->fetch();

// Notifications non lues
$stmt = $db->prepare('SELECT * FROM notifications WHERE id_utilisateur=? ORDER BY date_notif DESC LIMIT 5');
$stmt->execute([$id_user]);
$notifs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Tirage au sort — Hadj</title>
<link rel="stylesheet" href="UserView.css">
</head>
<body>
<div class="layout">
  <div class="sidebar">
    <div class="sidebar-logo">
      <div class="sidebar-logo-text">Tirage au sort</div>
      <div class="sidebar-logo-sub">Hadj <?= $prochain ? $prochain['annee'] : date('Y') ?></div>
    </div>
    <div class="nav-label">Navigation</div>
    <a href="Userdash.php" class="nav-item active">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><path d="M2 6.5L8 2l6 4.5V14H10v-3.5H6V14H2V6.5z" fill="currentColor"/></svg>
      Dashboard
    </a>
    <a href="UserResults.php" class="nav-item">
      <svg class="nav-icon" viewBox="0 0 16 16" fill="none"><rect x="2" y="3" width="12" height="10" rx="1.5" stroke="currentColor" stroke-width="1.2" fill="none"/><path d="M5 7h6M5 10h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
      Mes résultats
    </a>
    <div class="sidebar-footer">
      <div class="avatar">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="2.5" fill="#d6e9d6"/><path d="M3 13c0-2.761 2.239-5 5-5s5 2.239 5 5" stroke="#d6e9d6" stroke-width="1.2" stroke-linecap="round" fill="none"/></svg>
      </div>
      <span class="footer-name"><?= htmlspecialchars($_SESSION['user_nom']) ?></span>
      <div class="logout-btn" onclick="openLogoutModal()" style="cursor:pointer;">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9.5 7H3m0 0 2-2M3 7l2 2M6 4V2.5A.5.5 0 0 1 6.5 2H12a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5H6.5a.5.5 0 0 1-.5-.5V10" stroke="#d6e9d6" stroke-width="1.2" stroke-linecap="round"/></svg>
      </div>
    </div>
  </div>

  <div class="main">
    <!-- Hero -->
    <div class="hero hero--dashboard">
      <div class="hero-badge"><div class="hero-badge-dot"></div>Saison <?= $prochain ? $prochain['annee'] : date('Y') ?></div>
      <div class="hero-title">Bienvenue, <?= htmlspecialchars($user['prenom']) ?> !</div>
      <div class="hero-sub">Gérez vos inscriptions et suivez vos résultats du tirage au sort.</div>
      <div class="hero-year"><?= $prochain ? $prochain['annee'] : date('Y') ?></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon stat-icon--green">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1b5e35" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </div>
        <div>
          <div class="stat-value"><?= str_pad($nb_part,2,'0',STR_PAD_LEFT) ?></div>
          <div class="stat-label">Participations effectuées</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--teal">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7.5" stroke="#0d6e4f" stroke-width="1.4"/><path d="M6.5 10l2.5 2.5 4.5-5" stroke="#0d6e4f" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <?php if ($tirage_ouvert): ?>
            <div class="stat-badge stat-badge--open"><div class="stat-badge-dot"></div>Ouverte</div>
          <?php else: ?>
            <div class="stat-badge" style="background:#fce4e4;color:#b71c1c;">Fermée</div>
          <?php endif; ?>
          <div class="stat-label" style="margin-top:6px;">Inscription actuellement</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon stat-icon--amber">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="4" width="14" height="13" rx="2" stroke="#a06010" stroke-width="1.4" fill="none"/><path d="M3 8.5h14M7 3v3M13 3v3" stroke="#a06010" stroke-width="1.4" stroke-linecap="round"/></svg>
        </div>
        <div>
          <div class="stat-value" style="font-size:1.1rem;line-height:1.3;">
            <?= $prochain && $prochain['date_tirage'] ? date('d/m/Y',strtotime($prochain['date_tirage'])) : '—' ?>
          </div>
          <div class="stat-label">Date du tirage</div>
        </div>
      </div>
    </div>

    <!-- Bottom row -->
    <div class="bottom-row">
      <!-- Notifications -->
      <div class="info-card">
        <div class="info-card-title">Mes notifications récentes</div>
        <?php if (empty($notifs)): ?>
          <p style="font-size:0.85rem;color:#6b8c6b;">Aucune notification.</p>
        <?php else: foreach($notifs as $n): ?>
          <div style="padding:0.6rem 0;border-bottom:1px solid #f0f4f0;font-size:0.82rem;">
            <strong><?= htmlspecialchars($n['type_notif']) ?></strong><br>
            <?= htmlspecialchars($n['message']) ?>
            <div style="font-size:0.72rem;color:#a0b8a0;margin-top:3px;"><?= htmlspecialchars($n['date_notif']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- CTA Inscription -->
      <div class="cta-card">
        <div class="cta-card-icon">
          <svg width="26" height="26" viewBox="0 0 26 26" fill="none"><rect x="3" y="5" width="20" height="17" rx="2.5" stroke="#1b5e35" stroke-width="1.6" fill="none"/><path d="M3 10h20M9 3v4M17 3v4M13 14.5v4M11 16.5h4" stroke="#1b5e35" stroke-width="1.6" stroke-linecap="round"/></svg>
        </div>
        <?php if ($deja_inscrit): ?>
          <div class="cta-card-title">✅ Déjà inscrit(e)</div>
          <div class="cta-card-sub">Vous êtes inscrit(e) au tirage <?= $tirage_ouvert['annee'] ?>. Résultats le <?= date('d/m/Y',strtotime($tirage_ouvert['date_tirage'])) ?>.</div>
          <button class="btn-inscription" disabled style="background:#a0b8a0;cursor:not-allowed;">Inscription enregistrée</button>
        <?php elseif ($tirage_ouvert): ?>
          <div class="cta-card-title">S'inscrire au tirage</div>
          <div class="cta-card-sub">Inscriptions ouvertes jusqu'au <?= date('d/m/Y',strtotime($tirage_ouvert['date_cloture_inscr'])) ?>.</div>
          <button class="btn-inscription" onclick="sInscrire()">Inscription au tirage</button>
        <?php else: ?>
          <div class="cta-card-title">Inscriptions fermées</div>
          <div class="cta-card-sub">Aucun tirage ouvert actuellement. Consultez les dates prochainement.</div>
          <button class="btn-inscription" disabled style="background:#a0b8a0;cursor:not-allowed;">Non disponible</button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Toast notification -->
    <div id="toast" style="display:none;position:fixed;bottom:2rem;right:2rem;background:#1b5e35;color:#fff;padding:1rem 1.5rem;border-radius:10px;font-size:0.88rem;z-index:999;max-width:320px;box-shadow:0 4px 20px rgba(0,0,0,0.2);">
    </div>
  </div>
</div>

<!-- Logout modal -->
<div id="logoutModal" class="modal-overlay">
  <div class="modal-card">
    <div style="background:#fff5f5;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#b71c1c" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </div>
    <div class="modal-title">Se déconnecter ?</div>
    <div class="modal-text">Êtes-vous sûr(e) de vouloir quitter votre session ?</div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeLogoutModal()">Annuler</button>
      <a href="logout.php" class="btn-confirm">Se déconnecter</a>
    </div>
  </div>
</div>

<script>
const modal = document.getElementById('logoutModal');
function openLogoutModal()  { modal.style.display='flex'; }
function closeLogoutModal() { modal.style.display='none'; }
window.onclick = e => { if (e.target===modal) closeLogoutModal(); };

function showToast(msg, ok=true) {
  const t = document.getElementById('toast');
  t.style.background = ok ? '#1b5e35' : '#b71c1c';
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(() => t.style.display='none', 4000);
}

function sInscrire() {
  fetch('inscription_tirage.php', {method:'POST'})
    .then(r => r.json())
    .then(data => {
      if (data.succes) {
        showToast('✅ ' + data.message, true);
        setTimeout(() => location.reload(), 2000);
      } else {
        showToast('❌ ' + data.message, false);
      }
    })
    .catch(() => showToast('❌ Erreur réseau.', false));
}
</script>
</body>
</html>