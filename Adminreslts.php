<?php
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

$db = get_db();
$annee = (int)($_GET['annee'] ?? date('Y')-1);

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM resultats r JOIN inscriptions i ON i.id_inscription=r.id_inscription JOIN tirages t ON t.id_tirage=i.id_tirage WHERE r.gagnant=1 AND t.annee=?");
$stmt->execute([$annee]); $nb_g = (int)$stmt->fetchColumn();
$stmt->execute([$annee]); // reuse — actually redo non-gagnants
$stmt2 = $db->prepare("SELECT COUNT(*) FROM resultats r JOIN inscriptions i ON i.id_inscription=r.id_inscription JOIN tirages t ON t.id_tirage=i.id_tirage WHERE r.gagnant=0 AND t.annee=?");
$stmt2->execute([$annee]); $nb_ng = (int)$stmt2->fetchColumn();

// Résultats
$stmt = $db->prepare(
    "SELECT u.nin, CONCAT(u.nom,' ',u.prenom) AS nom_complet, w.nom_wilaya, r.gagnant, i.nb_participations
     FROM resultats r
     JOIN inscriptions i ON i.id_inscription=r.id_inscription
     JOIN utilisateurs u ON u.id_utilisateur=i.id_utilisateur
     JOIN wilayas w      ON w.id_wilaya=u.id_wilaya
     JOIN tirages t      ON t.id_tirage=i.id_tirage
     WHERE t.annee=?
     ORDER BY r.gagnant DESC, u.nom ASC"
);
$stmt->execute([$annee]);
$resultats = $stmt->fetchAll();

$annees = $db->query("SELECT annee FROM tirages WHERE etat=4 ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Résultats – Admin</title>
  <link rel="stylesheet" href="AdminView.css"/>
</head>
<body>
<div class="shell">
  <aside class="sidebar">
    <div class="sidebar-brand"><h2>Tirage au sort</h2><span>Espace Admin</span></div>
    <div class="sidebar-divider"></div>
    <p class="sidebar-nav-label">Navigation</p>
    <nav class="sidebar-nav">
      <a href="Admindash.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard</a>
      <a href="Adminusers.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>Utilisateurs</a>
      <a href="Adminparametres.php"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/></svg>Paramétrage</a>
      <a href="Adminreslts.php" class="active"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>Résultats</a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user"><div class="sidebar-avatar"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
      <div><div class="sidebar-username"><?=htmlspecialchars($_SESSION['user_nom'])?></div><div class="sidebar-role">Administrateur</div></div></div>
      <button class="sidebar-logout-btn" onclick="document.getElementById('logoutModal').classList.add('open')"><svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></button>
    </div>
  </aside>

  <main class="main">
    <div class="page-header"><h1>Résultats du tirage</h1><p>Consultation par édition</p></div>

    <div class="results-summary">
      <div class="rs-card win"><div class="rs-val"><?=$nb_g?></div><div class="rs-lbl">Gagnants — <?=$annee?></div></div>
      <div class="rs-card lose"><div class="rs-val"><?=$nb_ng?></div><div class="rs-lbl">Non gagnants — <?=$annee?></div></div>
      <div class="rs-card"><div class="rs-val"><?=$nb_g+$nb_ng?></div><div class="rs-lbl">Total — <?=$annee?></div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <div><h2>Liste des résultats</h2><p>Tirage <?=$annee?></p></div>
        <form method="GET" style="display:flex;gap:.5rem;align-items:center;">
          <select name="annee" class="filter-select" onchange="this.form.submit()">
            <?php foreach($annees as $a): ?>
              <option value="<?=$a?>" <?=$a==$annee?'selected':''?>>Édition <?=$a?></option>
            <?php endforeach; ?>
            <?php if(empty($annees)): ?><option value="<?=date('Y')-1?>">Édition <?=date('Y')-1?></option><?php endif; ?>
          </select>
        </form>
      </div>
      <div style="padding:.75rem 1.5rem 0;">
        <div class="toolbar">
          <div class="search-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a0b8a0" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Rechercher…" oninput="filtrer(this.value)">
          </div>
          <select class="filter-select" onchange="filtrerRes(this.value)">
            <option value="all">Tous</option>
            <option value="1">Gagnants</option>
            <option value="0">Non gagnants</option>
          </select>
        </div>
      </div>
      <div class="tbl-wrap">
        <table class="tbl" id="tbl">
          <thead><tr><th>NIN</th><th>Nom et prénom</th><th>Wilaya</th><th>Participations</th><th>Résultat</th></tr></thead>
          <tbody>
            <?php if(empty($resultats)): ?>
            <tr><td colspan="5" style="text-align:center;color:#6b8c6b;padding:2rem;">Aucun résultat pour <?=$annee?>.</td></tr>
            <?php else: foreach($resultats as $r): ?>
            <tr data-gagnant="<?=(int)$r['gagnant']?>">
              <td><?=htmlspecialchars($r['nin'])?></td>
              <td><strong><?=htmlspecialchars($r['nom_complet'])?></strong></td>
              <td><?=htmlspecialchars($r['nom_wilaya'])?></td>
              <td><?=(int)$r['nb_participations']?></td>
              <td><?=(int)$r['gagnant']===1 ? '<span class="badge badge-winner">Gagnant</span>' : '<span class="badge badge-loser">Non Gagnant</span>'?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<div id="logoutModal" class="overlay">
  <div class="modal">
    <div class="modal-icon" style="background:#fce4e4;color:#b71c1c;"><svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
    <h2>Se déconnecter ?</h2><p>Êtes-vous sûr(e) ?</p>
    <div class="modal-actions">
      <button class="btn-cancel-modal" onclick="document.getElementById('logoutModal').classList.remove('open')">Annuler</button>
      <a href="logout.php" class="btn-confirm-modal">Se déconnecter</a>
    </div>
  </div>
</div>
<script>
function filtrer(q){q=q.toLowerCase();document.querySelectorAll('#tbl tbody tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});}
function filtrerRes(v){document.querySelectorAll('#tbl tbody tr').forEach(r=>{r.style.display=(v==='all'||r.dataset.gagnant===v)?'':'none';});}
document.querySelectorAll('.overlay').forEach(ov=>{ov.addEventListener('click',e=>{if(e.target===ov)ov.classList.remove('open');});});
</script>
</body>
</html>