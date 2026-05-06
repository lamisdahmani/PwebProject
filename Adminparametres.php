<?php
// ============================================================
//  Adminparametres.php — Paramétrage du tirage (avec MySQL)
//  Remplace Adminparametres.html
// ============================================================
session_start();
require_once 'db.php';
require_once 'authguard.php';
exiger_role(1);

$db = get_db();

// ============================================================
//  TRAITEMENT DES FORMULAIRES (POST)
// ============================================================
$message_ok  = '';
$message_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Récupérer le tirage en cours (pas encore effectué)
    $stmt = $db->prepare("SELECT * FROM tirages WHERE etat < 4 ORDER BY annee DESC LIMIT 1");
    $stmt->execute();
    $tirage = $stmt->fetch();

    if (!$tirage) {
        $message_err = "Aucun tirage en cours à configurer.";
    } else {
        $id_tirage = (int)$tirage['id_tirage'];

        // --- Étape 1 : Nombre de gagnants ---
        if ($action === 'nb_gagnants') {
            $nb = (int)($_POST['nb_gagnants'] ?? 0);
            if ($nb < 1) {
                $message_err = "Le nombre de gagnants doit être au moins 1.";
            } else {
                $db->prepare('UPDATE tirages SET nb_gagnants = ? WHERE id_tirage = ?')
                   ->execute([$nb, $id_tirage]);
                // Passer à l'état 1 si encore à 0
                if ((int)$tirage['etat'] === 0) {
                    $db->prepare('UPDATE tirages SET etat = 1 WHERE id_tirage = ?')
                       ->execute([$id_tirage]);
                }
                $message_ok = "Nombre de gagnants mis à jour.";
            }
        }

        // --- Étape 2 : Date du tirage ---
        elseif ($action === 'date_tirage') {
            if ((int)$tirage['etat'] < 1) {
                $message_err = "Veuillez d'abord définir le nombre de gagnants.";
            } else {
                $date = $_POST['date_tirage'] ?? '';
                if (!$date || strtotime($date) === false) {
                    $message_err = "Date invalide.";
                } elseif (strtotime($date) <= time()) {
                    $message_err = "La date du tirage doit être dans le futur.";
                } else {
                    $db->prepare('UPDATE tirages SET date_tirage = ? WHERE id_tirage = ?')
                       ->execute([$date, $id_tirage]);
                    if ((int)$tirage['etat'] === 1) {
                        $db->prepare('UPDATE tirages SET etat = 2 WHERE id_tirage = ?')
                           ->execute([$id_tirage]);
                    }
                    // Notifier tous les utilisateurs actifs
                    $users_actifs = $db->query(
                        'SELECT id_utilisateur FROM utilisateurs WHERE etat_compte = 1 AND role = 2'
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $stmt_notif = $db->prepare(
                        'INSERT INTO notifications (id_utilisateur, type_notif, message)
                         VALUES (?, ?, ?)'
                    );
                    foreach ($users_actifs as $uid) {
                        $stmt_notif->execute([
                            $uid,
                            'date_tirage',
                            "La date du tirage au sort a été fixée au : " . date('d/m/Y', strtotime($date)) . ".",
                        ]);
                    }
                    $message_ok = "Date du tirage enregistrée.";
                }
            }
        }

        // --- Étape 3 : Dates d'inscription ---
        elseif ($action === 'dates_inscr') {
            if ((int)$tirage['etat'] < 2) {
                $message_err = "Veuillez d'abord fixer la date du tirage.";
            } else {
                $ouv = $_POST['date_ouverture'] ?? '';
                $clo = $_POST['date_cloture']   ?? '';

                if (!$ouv || !$clo) {
                    $message_err = "Les deux dates sont obligatoires.";
                } elseif (strtotime($ouv) >= strtotime($clo)) {
                    $message_err = "La date d'ouverture doit être avant la date de clôture.";
                } elseif (strtotime($clo) >= strtotime($tirage['date_tirage'])) {
                    $message_err = "La clôture doit être avant la date du tirage.";
                } else {
                    $db->prepare(
                        'UPDATE tirages SET date_ouverture_inscr=?, date_cloture_inscr=? WHERE id_tirage=?'
                    )->execute([$ouv, $clo, $id_tirage]);

                    // Notifier les utilisateurs de l'ouverture des inscriptions
                    $users_actifs = $db->query(
                        'SELECT id_utilisateur FROM utilisateurs WHERE etat_compte = 1 AND role = 2'
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $stmt_notif = $db->prepare(
                        'INSERT INTO notifications (id_utilisateur, type_notif, message)
                         VALUES (?, ?, ?)'
                    );
                    foreach ($users_actifs as $uid) {
                        $stmt_notif->execute([
                            $uid,
                            'ouverture_inscr',
                            "Les inscriptions au tirage sont ouvertes du "
                              . date('d/m/Y', strtotime($ouv))
                              . " au "
                              . date('d/m/Y', strtotime($clo)) . ".",
                        ]);
                    }
                    $message_ok = "Dates d'inscription enregistrées.";
                }
            }
        }
    }

    // Recharger après traitement
    header('Location: Adminparametres.php?msg=' . urlencode($message_ok ?: $message_err));
    exit;
}

// ============================================================
//  LECTURE DONNÉES POUR L'AFFICHAGE
// ============================================================
$stmt = $db->prepare("SELECT * FROM tirages WHERE etat < 4 ORDER BY annee DESC LIMIT 1");
$stmt->execute();
$tirage = $stmt->fetch();

// Si aucun tirage en cours, créer un pour l'année prochaine
if (!$tirage) {
    $annee_prochaine = (int)date('Y') + 1;
    $db->prepare('INSERT INTO tirages (annee, etat) VALUES (?, 0)')
       ->execute([$annee_prochaine]);
    header('Location: Adminparametres.php');
    exit;
}

// Nombre de participants validés inscrits au tirage en cours
$stmt2 = $db->prepare(
    'SELECT COUNT(*) FROM inscriptions i
     JOIN utilisateurs u ON u.id_utilisateur = i.id_utilisateur
     WHERE i.id_tirage = ? AND u.etat_compte = 1'
);
$stmt2->execute([$tirage['id_tirage']]);
$nb_participants = (int)$stmt2->fetchColumn();

// Aujourd'hui pour comparaison
$aujourd_hui    = date('Y-m-d');
$peut_lancer    = ($tirage['date_tirage'] === $aujourd_hui) && ((int)$tirage['etat'] >= 2);

$etat = (int)$tirage['etat'];
// Étapes : 0=rien, 1=nb_gagnants ok, 2=date_tirage ok, 3=dates_inscr ok, 4=effectué
$step1_done = $etat >= 1;
$step2_done = $etat >= 2;
$step3_done = $tirage['date_ouverture_inscr'] !== null;

$msg_url = htmlspecialchars($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Paramétrage – Admin</title>
  <link rel="stylesheet" href="AdminView.css"/>
</head>
<body>
<div class="shell">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar">
    <div class="sidebar-brand"><h2>Tirage au sort</h2><span>Espace Admin</span></div>
    <div class="sidebar-divider"></div>
    <p class="sidebar-nav-label">Navigation</p>
    <nav class="sidebar-nav">
      <a href="Admindash.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="Adminusers.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        Utilisateurs
      </a>
      <a href="Adminparametres.php" class="active">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        Paramétrage
      </a>
      <a href="Adminreslts.php">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Résultats
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </div>
        <div>
          <div class="sidebar-username"><?= htmlspecialchars($_SESSION['user_nom']) ?></div>
          <div class="sidebar-role">Administrateur</div>
        </div>
      </div>
      <button class="sidebar-logout-btn" onclick="document.getElementById('logoutModal').classList.add('open')">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      </button>
    </div>
  </aside>

  <!-- ===== MAIN ===== -->
  <main class="main">
    <div class="page-header">
      <h1>Paramétrage du tirage <?= htmlspecialchars($tirage['annee']) ?></h1>
      <p>Suivre l'ordre des étapes</p>
    </div>

    <?php if ($msg_url): ?>
      <div class="<?= str_contains($msg_url,'succès')||str_contains($msg_url,'mis à jour')||str_contains($msg_url,'enregistr') ? 'info-strip' : 'warn-strip' ?>">
        <?= htmlspecialchars($msg_url) ?>
      </div>
    <?php endif; ?>

    <!-- Barre d'étapes -->
    <div class="steps-bar">
      <div class="step <?= $step1_done ? 'done' : 'current' ?>">
        <div class="step-num"><?= $step1_done ? '✓' : '1' ?></div>
        <div class="step-label">Nombre de gagnants</div>
      </div>
      <div class="step <?= $step2_done ? 'done' : ($step1_done ? 'current' : '') ?>">
        <div class="step-num"><?= $step2_done ? '✓' : '2' ?></div>
        <div class="step-label">Date du tirage</div>
      </div>
      <div class="step <?= $step3_done ? 'done' : ($step2_done ? 'current' : '') ?>">
        <div class="step-num"><?= $step3_done ? '✓' : '3' ?></div>
        <div class="step-label">Dates d'inscription</div>
      </div>
      <div class="step <?= $peut_lancer ? 'current' : '' ?>">
        <div class="step-num">4</div>
        <div class="step-label">Lancement</div>
      </div>
    </div>

    <div class="info-strip">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <span>Ordre strict : <strong>Nombre de gagnants → Date du tirage → Dates d'inscription → Lancement</strong>.</span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

      <!-- Étape 1 : Nombre de gagnants -->
      <div class="card">
        <div class="card-header">
          <div><h2>① Nombre de gagnants</h2><p>Définir le quota de places</p></div>
          <span class="pill <?= $step1_done ? 'pill-done' : 'pill-pending' ?>"><?= $step1_done ? 'Complété' : 'À faire' ?></span>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="nb_gagnants">
            <div class="form-group">
              <label class="lbl">Nombre de gagnants <span class="req">*</span></label>
              <input type="number" name="nb_gagnants" class="form-input"
                     value="<?= (int)$tirage['nb_gagnants'] ?>" min="1" placeholder="ex: 500">
              <span class="hint">Places disponibles pour ce tirage</span>
            </div>
            <div style="margin-top:1rem;">
              <button type="submit" class="btn btn-<?= $step1_done ? 'outline' : 'primary' ?>">
                <?= $step1_done ? 'Mettre à jour' : 'Enregistrer' ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Étape 2 : Date du tirage -->
      <div class="card">
        <div class="card-header">
          <div><h2>② Date du tirage</h2><p>Doit être après la clôture des inscriptions</p></div>
          <span class="pill <?= $step2_done ? 'pill-done' : ($step1_done ? 'pill-pending' : 'pill-closed') ?>">
            <?= $step2_done ? 'Complété' : ($step1_done ? 'À faire' : 'Verrouillé') ?>
          </span>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="date_tirage">
            <div class="form-group">
              <label class="lbl">Date du tirage <span class="req">*</span></label>
              <input type="date" name="date_tirage" class="form-input"
                     value="<?= htmlspecialchars($tirage['date_tirage'] ?? '') ?>"
                     <?= !$step1_done ? 'disabled' : '' ?>>
              <span class="hint">Le tirage ne peut être lancé qu'à cette date exacte</span>
            </div>
            <div style="margin-top:1rem;">
              <button type="submit" class="btn btn-<?= $step2_done ? 'outline' : 'primary' ?>"
                      <?= !$step1_done ? 'disabled' : '' ?>>
                <?= $step2_done ? 'Mettre à jour' : 'Enregistrer' ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Étape 3 : Dates d'inscription -->
      <div class="card">
        <div class="card-header">
          <div><h2>③ Période d'inscription</h2><p>Ouverture et clôture</p></div>
          <span class="pill <?= $step3_done ? 'pill-done' : ($step2_done ? 'pill-pending' : 'pill-closed') ?>">
            <?= $step3_done ? 'Complété' : ($step2_done ? 'À faire' : 'Verrouillé') ?>
          </span>
        </div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="dates_inscr">
            <div class="form-grid">
              <div class="form-group">
                <label class="lbl">Date d'ouverture <span class="req">*</span></label>
                <input type="date" name="date_ouverture" class="form-input"
                       value="<?= htmlspecialchars($tirage['date_ouverture_inscr'] ?? '') ?>"
                       <?= !$step2_done ? 'disabled' : '' ?>>
              </div>
              <div class="form-group">
                <label class="lbl">Date de clôture <span class="req">*</span></label>
                <input type="date" name="date_cloture" class="form-input"
                       value="<?= htmlspecialchars($tirage['date_cloture_inscr'] ?? '') ?>"
                       <?= !$step2_done ? 'disabled' : '' ?>>
                <span class="hint">Doit être avant la date du tirage</span>
              </div>
            </div>
            <div style="margin-top:1rem;">
              <button type="submit" class="btn btn-<?= $step3_done ? 'outline' : 'primary' ?>"
                      <?= !$step2_done ? 'disabled' : '' ?>>
                <?= $step3_done ? 'Mettre à jour' : 'Enregistrer' ?>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Étape 4 : Lancement -->
      <div class="card">
        <div class="card-header">
          <div>
            <h2>④ Lancement du tirage</h2>
            <p>Disponible uniquement le <?= $tirage['date_tirage'] ? date('d/m/Y', strtotime($tirage['date_tirage'])) : '—' ?></p>
          </div>
          <span class="pill <?= $peut_lancer ? 'pill-open' : 'pill-closed' ?>">
            <?= $peut_lancer ? 'Disponible' : 'À venir' ?>
          </span>
        </div>
        <div class="card-body">
          <?php if (!$peut_lancer): ?>
          <div class="warn-strip">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>Le tirage ne peut être lancé que le <strong><?= $tirage['date_tirage'] ? date('d/m/Y', strtotime($tirage['date_tirage'])) : '—' ?></strong>.</span>
          </div>
          <?php endif; ?>
          <table class="param-tbl" style="margin-bottom:1.25rem;">
            <tr><td>Participants validés</td><td><?= $nb_participants ?></td></tr>
            <tr><td>Nombre de gagnants</td><td><?= (int)$tirage['nb_gagnants'] ?></td></tr>
            <tr><td>Date de lancement</td><td><?= $tirage['date_tirage'] ? date('d/m/Y', strtotime($tirage['date_tirage'])) : '—' ?></td></tr>
          </table>
          <div style="display:flex;justify-content:center;">
            <button class="btn-launch" <?= !$peut_lancer ? 'disabled' : '' ?>
                    onclick="lancerTirage()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              <?= $peut_lancer ? 'Lancer le tirage' : 'Lancer le tirage (disponible le ' . ($tirage['date_tirage'] ? date('d/m/Y', strtotime($tirage['date_tirage'])) : '—') . ')' ?>
            </button>
          </div>
        </div>
      </div>

    </div><!-- /grid -->
  </main>
</div><!-- /shell -->

<!-- LOGOUT MODAL -->
<div id="logoutModal" class="overlay">
  <div class="modal">
    <div class="modal-icon" style="background:#fce4e4;color:#b71c1c;">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    </div>
    <h2>Se déconnecter ?</h2>
    <p>Êtes-vous sûr(e) de vouloir quitter votre session administrateur ?</p>
    <div class="modal-actions">
      <button class="btn-cancel-modal" onclick="document.getElementById('logoutModal').classList.remove('open')">Annuler</button>
      <a href="logout.php" class="btn-confirm-modal">Se déconnecter</a>
    </div>
  </div>
</div>

<script>
// Lancer le tirage via fetch → tirage.php
function lancerTirage() {
  if (!confirm("Lancer le tirage au sort ? Cette action est irréversible.")) return;

  fetch('tirage.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      if (data.succes) {
        alert('✅ ' + data.message + '\n\nGagnants : ' + data.nb_gagnants + ' / ' + data.total + ' participants.');
        location.reload();
      } else {
        alert('❌ ' + data.message);
      }
    })
    .catch(() => alert('Erreur réseau. Réessayez.'));
}

// Fermer les modals en cliquant sur l'overlay
document.querySelectorAll('.overlay').forEach(ov => {
  ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('open'); });
});
</script>
</body>
</html>