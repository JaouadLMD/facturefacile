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

    if ($action === 'ia') {
        $stmt = $pdo->prepare("UPDATE users SET groq_api_key=?,claude_api_key=?,ai_provider=? WHERE id=?");
        $ok = $stmt->execute([trim($_POST['groq_api_key'] ?? ''), trim($_POST['claude_api_key'] ?? ''), trim($_POST['ai_provider'] ?? 'groq'), $user['id']]);
        $success = $ok ? 'Configuration IA enregistrée.' : 'Erreur.';
    }

    if ($action === 'smtp') {
        $stmt = $pdo->prepare("UPDATE users SET smtp_host=?,smtp_port=?,smtp_user=?,smtp_pass=? WHERE id=?");
        $ok = $stmt->execute([trim($_POST['smtp_host'] ?? ''), trim($_POST['smtp_port'] ?? '465'), trim($_POST['smtp_user'] ?? ''), trim($_POST['smtp_pass'] ?? ''), $user['id']]);
        $success = $ok ? 'Configuration email enregistrée.' : 'Erreur.';
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$user['id']]);
    $user = $stmt->fetch();
}

$pageTitle = 'Paramètres';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<h1 style="font-size:20px;font-weight:800;color:var(--text);margin:0 0 6px;">⚙️ Paramètres</h1>
<p style="font-size:13px;color:var(--text-soft);margin:0 0 24px;">Gérez votre compte, vos abonnements et personnalisez vos factures.</p>

<?php if ($success): ?>
<div style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#10B981;">✅ <?= h($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#EF4444;">⚠️ <?= h($error) ?></div>
<?php endif; ?>

<!-- Navigation cards -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:28px;">

    <a href="<?= APP_URL ?>/profil.php" style="text-decoration:none;">
        <div class="card" style="padding:22px;display:flex;align-items:center;gap:16px;transition:all 0.15s;cursor:pointer;border:1.5px solid var(--border);">
            <div style="width:48px;height:48px;border-radius:14px;background:var(--primary-bg);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;border:1.5px solid var(--border2);">👤</div>
            <div>
                <p style="font-size:13.5px;font-weight:700;color:var(--text);margin:0 0 2px;">Mon profil</p>
                <p style="font-size:11.5px;color:var(--text-soft);margin:0;">Nom, entreprise, photo, mot de passe</p>
            </div>
            <span style="margin-left:auto;color:var(--text-soft);font-size:16px;">›</span>
        </div>
    </a>

    <a href="<?= APP_URL ?>/plans.php" style="text-decoration:none;">
        <div class="card" style="padding:22px;display:flex;align-items:center;gap:16px;transition:all 0.15s;cursor:pointer;border:1.5px solid var(--border);">
            <div style="width:48px;height:48px;border-radius:14px;background:rgba(245,158,11,.12);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;border:1.5px solid rgba(245,158,11,.25);">⭐</div>
            <div>
                <p style="font-size:13.5px;font-weight:700;color:var(--text);margin:0 0 2px;">Plans & Abonnement</p>
                <p style="font-size:11.5px;color:var(--text-soft);margin:0;">Plan actuel : <strong style="color:var(--primary-2);"><?= planLabel($user['plan'] ?? 'gratuit') ?></strong></p>
            </div>
            <span style="margin-left:auto;color:var(--text-soft);font-size:16px;">›</span>
        </div>
    </a>

    <a href="<?= APP_URL ?>/parametres-facture.php" style="text-decoration:none;">
        <div class="card" style="padding:22px;display:flex;align-items:center;gap:16px;transition:all 0.15s;cursor:pointer;border:1.5px solid var(--border);">
            <div style="width:48px;height:48px;border-radius:14px;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;border:1.5px solid rgba(16,185,129,.2);">🧾</div>
            <div>
                <p style="font-size:13.5px;font-weight:700;color:var(--text);margin:0 0 2px;">Paramètres Facture</p>
                <p style="font-size:11.5px;color:var(--text-soft);margin:0;">Logo, couleurs, colonnes, pied de page</p>
            </div>
            <span style="margin-left:auto;color:var(--text-soft);font-size:16px;">›</span>
        </div>
    </a>

</div>

<!-- IA + SMTP side by side -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

    <!-- IA Config -->
    <div class="card" style="padding:22px;">
        <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 3px;">🤖 Configuration IA</h3>
        <p style="font-size:11.5px;color:var(--text-soft);margin:0 0 16px;">Fournisseur d'IA pour la génération de factures</p>
        <form method="POST">
            <input type="hidden" name="action" value="ia">
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:6px;">Fournisseur IA</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid <?= ($user['ai_provider'] ?? 'groq') === 'groq' ? 'var(--primary)' : 'var(--border)' ?>;border-radius:10px;cursor:pointer;background:<?= ($user['ai_provider'] ?? 'groq') === 'groq' ? 'var(--primary-bg)' : 'var(--bg3)' ?>;">
                        <input type="radio" name="ai_provider" value="groq" <?= ($user['ai_provider'] ?? 'groq') === 'groq' ? 'checked' : '' ?>>
                        <div>
                            <p style="font-size:13px;font-weight:600;color:var(--text);margin:0;">Groq <span style="background:rgba(16,185,129,.15);color:#10B981;font-size:10px;padding:1px 6px;border-radius:999px;font-weight:600;">GRATUIT</span></p>
                            <p style="font-size:11px;color:var(--text-soft);margin:0;">llama3 · rapide · sans CC</p>
                        </div>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border:1.5px solid <?= ($user['ai_provider'] ?? '') === 'claude' ? 'var(--primary)' : 'var(--border)' ?>;border-radius:10px;cursor:pointer;background:var(--bg3);">
                        <input type="radio" name="ai_provider" value="claude" <?= ($user['ai_provider'] ?? '') === 'claude' ? 'checked' : '' ?>>
                        <div>
                            <p style="font-size:13px;font-weight:600;color:var(--text);margin:0;">Claude (Anthropic)</p>
                            <p style="font-size:11px;color:var(--text-soft);margin:0;">Haute qualité · Payant</p>
                        </div>
                    </label>
                </div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">
                    🟢 Clé API Groq —
                    <a href="https://console.groq.com" target="_blank" style="color:var(--primary-2);text-decoration:none;font-weight:600;">console.groq.com →</a>
                </label>
                <input type="text" name="groq_api_key" value="<?= h($user['groq_api_key'] ?? '') ?>" placeholder="gsk_..."
                       style="width:100%;padding:8px 12px;font-size:13px;font-family:monospace;box-sizing:border-box;">
                <p style="font-size:11px;color:var(--text-soft);margin:4px 0 0;">✅ Gratuit · 14 400 requêtes/jour · Pas de carte bancaire</p>
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Clé API Claude (optionnel)</label>
                <input type="password" name="claude_api_key" value="<?= h($user['claude_api_key'] ?? '') ?>" placeholder="sk-ant-api03-..."
                       style="width:100%;padding:8px 12px;font-size:13px;font-family:monospace;box-sizing:border-box;">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">💾 Enregistrer IA</button>
        </form>
    </div>

    <!-- Email SMTP -->
    <div class="card" style="padding:22px;">
        <h3 style="font-size:14px;font-weight:700;color:var(--text);margin:0 0 3px;">📧 Email (SMTP)</h3>
        <p style="font-size:11.5px;color:var(--text-soft);margin:0 0 16px;">Pour envoyer les factures et relances par email</p>
        <form method="POST">
            <input type="hidden" name="action" value="smtp">
            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:grid;grid-template-columns:1fr auto;gap:8px;">
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Hôte SMTP</label>
                        <input type="text" name="smtp_host" value="<?= h($user['smtp_host'] ?? 'smtp.gmail.com') ?>" style="width:100%;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Port</label>
                        <input type="number" name="smtp_port" value="<?= $user['smtp_port'] ?? '465' ?>" style="width:80px;padding:8px 10px;font-size:13px;">
                    </div>
                </div>
                <div>
                    <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">Email d'envoi (Gmail)</label>
                    <input type="email" name="smtp_user" value="<?= h($user['smtp_user'] ?? '') ?>" placeholder="votre.email@gmail.com" style="width:100%;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:11.5px;color:var(--text-mid);display:block;margin-bottom:4px;">
                        App Password Gmail
                        <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color:var(--primary-2);font-size:10.5px;font-weight:600;">→ Générer ici</a>
                    </label>
                    <input type="password" name="smtp_pass" value="<?= h($user['smtp_pass'] ?? '') ?>" placeholder="xxxx xxxx xxxx xxxx (16 caractères)" style="width:100%;padding:8px 12px;font-size:13px;box-sizing:border-box;">
                    <p style="font-size:11px;color:var(--text-soft);margin:5px 0 0;">Google Account → Sécurité → Validation 2 étapes → Mots de passe des applications</p>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-top:14px;">💾 Enregistrer SMTP</button>
        </form>
    </div>

</div>

</div></div></body></html>
