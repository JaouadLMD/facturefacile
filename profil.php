<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();
requireSettingsAccess();
$user = getCurrentUser();
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $stmt = $pdo->prepare("UPDATE users SET nom=?,entreprise=?,telephone=?,adresse=?,ville=?,pays=?,ice=?,devise=?,tva_defaut=? WHERE id=?");
        $ok = $stmt->execute([
            trim($_POST['nom']       ?? ''),
            trim($_POST['entreprise']?? ''),
            trim($_POST['telephone'] ?? ''),
            trim($_POST['adresse']   ?? ''),
            trim($_POST['ville']     ?? ''),
            trim($_POST['pays']      ?? 'Maroc'),
            trim($_POST['ice']       ?? ''),
            $_POST['devise']         ?? 'MAD',
            (float)($_POST['tva_defaut'] ?? 20),
            $user['id']
        ]);
        $success = $ok ? 'Profil mis à jour avec succès.' : 'Erreur lors de la mise à jour.';
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password']))   $error = 'Mot de passe actuel incorrect.';
        elseif (strlen($new) < 6)                             $error = 'Minimum 6 caractères requis.';
        elseif ($new !== $confirm)                            $error = 'Les mots de passe ne correspondent pas.';
        else {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $success = 'Mot de passe modifié avec succès.';
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch();
}

$_dispInit = strtoupper(mb_substr($user['nom'] ?: 'U', 0, 1));
$_photoUrl = !empty($user['photo_path']) ? APP_URL.'/'.ltrim($user['photo_path'],'/') : '';
$pageTitle = 'Mon profil';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div style="display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--text-soft);margin-bottom:20px;">
    <a href="<?= APP_URL ?>/parametres.php" style="color:var(--text-mid);text-decoration:none;">Paramètres</a>
    <span>›</span>
    <span style="font-weight:500;color:var(--text);">Mon profil</span>
</div>

<h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 24px;">👤 Mon profil</h1>

<?php if ($success): ?>
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#10B981;">✅ <?= h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#EF4444;">⚠️ <?= h($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start;">

    <!-- Left: Avatar + account info -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Avatar card -->
        <div class="card" style="padding:28px;text-align:center;">
            <div id="profileAvatarWrap" style="position:relative;display:inline-block;margin-bottom:16px;cursor:pointer;" onclick="openPhotoModal()">
                <div id="profileAvatar" style="width:90px;height:90px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;color:white;overflow:hidden;border:3px solid var(--border2);margin:0 auto;">
                    <?php if ($_photoUrl): ?><img src="<?= h($_photoUrl) ?>?t=<?= time() ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: echo $_dispInit; endif; ?>
                </div>
                <div style="position:absolute;bottom:0;right:0;width:28px;height:28px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;border:2px solid var(--card);">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
                </div>
            </div>
            <p style="font-size:16px;font-weight:800;color:var(--text);margin:0 0 4px;"><?= h($user['nom']) ?></p>
            <p style="font-size:12px;color:var(--text-soft);margin:0 0 14px;"><?= h($user['email']) ?></p>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <button onclick="openPhotoModal()" class="btn-secondary" style="width:100%;justify-content:center;font-size:12.5px;">📷 Changer la photo</button>
                <?php if ($_photoUrl): ?>
                <button onclick="deleteProfilePhoto()" class="btn-secondary" style="width:100%;justify-content:center;font-size:12.5px;color:#EF4444;border-color:rgba(239,68,68,.3);">🗑️ Supprimer la photo</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account stats -->
        <div class="card" style="padding:20px;">
            <h3 style="font-size:12px;font-weight:700;color:var(--text-soft);text-transform:uppercase;letter-spacing:.5px;margin:0 0 14px;">Informations du compte</h3>
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-soft);">Plan</span>
                    <span style="font-weight:700;color:var(--primary-2);"><?= planLabel($user['plan'] ?? 'gratuit') ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-soft);">Factures créées</span>
                    <span style="font-weight:600;color:var(--text);"><?= (int)$user['factures_count'] ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-soft);">Membre depuis</span>
                    <span style="font-weight:600;color:var(--text);"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-soft);">Email</span>
                    <span style="font-weight:600;color:var(--text);font-size:12px;"><?= h($user['email']) ?></span>
                </div>
            </div>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:8px;">
                <a href="<?= APP_URL ?>/plans.php" class="btn-secondary" style="width:100%;justify-content:center;font-size:12.5px;">⭐ Gérer le plan</a>
                <a href="<?= APP_URL ?>/logout.php" style="display:block;text-align:center;padding:8px;font-size:12.5px;color:#EF4444;border:1px solid rgba(239,68,68,.3);border-radius:10px;text-decoration:none;background:rgba(239,68,68,.06);">🚪 Déconnexion</a>
            </div>
        </div>
    </div>

    <!-- Right: Forms -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Profile info -->
        <div class="card" style="padding:24px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;">Informations personnelles & entreprise</h3>
            <p style="font-size:12px;color:var(--text-soft);margin:0 0 20px;">Ces informations apparaissent sur vos factures et devis.</p>
            <form method="POST">
                <input type="hidden" name="action" value="profile">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Nom complet *</label>
                        <input type="text" name="nom" value="<?= h($user['nom']??'') ?>" required style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Nom entreprise</label>
                        <input type="text" name="entreprise" value="<?= h($user['entreprise']??'') ?>" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Téléphone</label>
                        <input type="text" name="telephone" value="<?= h($user['telephone']??'') ?>" placeholder="+212 6 XX XX XX XX" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">ICE</label>
                        <input type="text" name="ice" value="<?= h($user['ice']??'') ?>" placeholder="001234567000012" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Ville</label>
                        <input type="text" name="ville" value="<?= h($user['ville']??'') ?>" placeholder="Casablanca" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Pays</label>
                        <input type="text" name="pays" value="<?= h($user['pays']??'Maroc') ?>" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Adresse</label>
                        <input type="text" name="adresse" value="<?= h($user['adresse']??'') ?>" placeholder="N° Rue, Quartier..." style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Devise</label>
                        <select name="devise" style="width:100%;padding:9px 12px;font-size:13px;">
                            <?php foreach(['MAD','EUR','USD','GBP','CAD'] as $d): ?>
                            <option value="<?= $d ?>" <?= ($user['devise']??'MAD')===$d?'selected':'' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">TVA par défaut (%)</label>
                        <input type="number" name="tva_defaut" value="<?= h($user['tva_defaut']??'20') ?>" min="0" max="100" step="0.5" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-top:18px;">💾 Enregistrer le profil</button>
            </form>
        </div>

        <!-- Password -->
        <div class="card" style="padding:24px;">
            <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 4px;">🔐 Changer le mot de passe</h3>
            <p style="font-size:12px;color:var(--text-soft);margin:0 0 20px;">Minimum 6 caractères.</p>
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Mot de passe actuel</label>
                        <input type="password" name="current_password" required style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Nouveau mot de passe</label>
                            <input type="password" name="new_password" required minlength="6" style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Confirmer</label>
                            <input type="password" name="confirm_password" required style="width:100%;padding:9px 12px;font-size:13px;box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-secondary" style="width:100%;justify-content:center;margin-top:18px;font-weight:700;">🔐 Modifier le mot de passe</button>
            </form>
        </div>

    </div><!-- /right -->
</div>

</div></div></body></html>
