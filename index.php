<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (login($email, $password)) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
    $error = 'Email ou mot de passe incorrect.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion – FactureFacile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body style="min-height:100vh;background:linear-gradient(135deg,#1C1917 0%,#292524 50%,#1C1917 100%);display:flex;align-items:center;justify-content:center;padding:16px;font-family:'Inter',sans-serif;">

<div style="width:100%;max-width:900px;display:grid;grid-template-columns:1fr 1fr;background:white;border-radius:24px;box-shadow:0 30px 80px rgba(0,0,0,0.4);overflow:hidden;">

    <!-- Left panel -->
    <div style="background:linear-gradient(135deg,#1C1917,#292524);padding:40px;display:flex;flex-direction:column;justify-content:space-between;color:white;">
        <div>
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:40px;">
                <div style="width:44px;height:44px;background:linear-gradient(135deg,#D97706,#F59E0B);border-radius:13px;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:22px;color:#1C1917;box-shadow:0 4px 14px rgba(217,119,6,0.5);">F</div>
                <span style="font-weight:800;font-size:22px;letter-spacing:-.3px;">FactureFacile</span>
            </div>
            <h1 style="font-size:28px;font-weight:800;line-height:1.3;margin:0 0 14px;color:#F5F5F4;">Créez vos factures en quelques secondes grâce à l'IA</h1>
            <p style="color:#78716C;font-size:13.5px;line-height:1.6;margin:0;">Tapez simplement <em>"Facture pour Ahmed, 3 logos à 500 DH"</em> et laissez l'IA faire le reste.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px;">
            <?php foreach ([
                ['🤖','Intelligence Artificielle','Génération de factures en langage naturel'],
                ['⚡','Ultra rapide','Facture prête en moins de 10 secondes'],
                ['👥','Gestion d\'équipe','Ajoutez vos collaborateurs avec permissions'],
            ] as [$emoji,$title,$sub]): ?>
            <div style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.06);border-radius:12px;padding:12px 14px;border:1px solid rgba(255,255,255,0.06);">
                <span style="font-size:22px;"><?= $emoji ?></span>
                <div>
                    <p style="font-weight:600;font-size:13px;margin:0;color:#F5F5F4;"><?= $title ?></p>
                    <p style="color:#78716C;font-size:11.5px;margin:1px 0 0;"><?= $sub ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Right panel -->
    <div style="padding:40px;display:flex;flex-direction:column;justify-content:center;">
        <div style="margin-bottom:28px;">
            <h2 style="font-size:22px;font-weight:800;color:#111827;margin:0 0 4px;">Bon retour ! 👋</h2>
            <p style="color:#6B7280;font-size:13px;margin:0;">Connectez-vous à votre espace</p>
        </div>

        <?php if ($error): ?>
        <div style="margin-bottom:16px;background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;border-radius:12px;padding:12px 16px;font-size:13px;display:flex;align-items:center;gap:8px;">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
            <div>
                <label style="display:block;font-size:12.5px;font-weight:600;color:#374151;margin-bottom:6px;">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="vous@exemple.com"
                       style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:13px;font-family:inherit;box-sizing:border-box;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#D97706';this.style.boxShadow='0 0 0 3px rgba(217,119,6,0.12)'"
                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow=''">
            </div>
            <div>
                <label style="display:block;font-size:12.5px;font-weight:600;color:#374151;margin-bottom:6px;">Mot de passe</label>
                <input type="password" name="password" required
                       placeholder="••••••••"
                       style="width:100%;padding:11px 14px;border:1.5px solid #E5E7EB;border-radius:12px;font-size:13px;font-family:inherit;box-sizing:border-box;transition:border-color .15s;"
                       onfocus="this.style.borderColor='#D97706';this.style.boxShadow='0 0 0 3px rgba(217,119,6,0.12)'"
                       onblur="this.style.borderColor='#E5E7EB';this.style.boxShadow=''">
            </div>
            <button type="submit"
                    style="width:100%;padding:13px;background:linear-gradient(135deg,#D97706,#F59E0B);color:#1C1917;font-weight:800;font-size:14px;border:none;border-radius:12px;cursor:pointer;box-shadow:0 4px 14px rgba(217,119,6,0.4);transition:all .15s;"
                    onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 18px rgba(217,119,6,0.5)'"
                    onmouseout="this.style.transform='';this.style.boxShadow='0 4px 14px rgba(217,119,6,0.4)'">
                Se connecter →
            </button>
        </form>

        <div style="margin-top:16px;text-align:center;">
            <p style="color:#6B7280;font-size:13px;">
                Pas encore de compte ?
                <a href="<?= APP_URL ?>/register.php" style="color:#D97706;font-weight:700;text-decoration:none;">S'inscrire gratuitement</a>
            </p>
        </div>

        <div style="margin-top:20px;padding-top:20px;border-top:1px solid #F3F4F6;">
            <p style="text-align:center;font-size:11px;color:#9CA3AF;margin:0 0 8px;">Compte démo</p>
            <div style="background:#F9FAFB;border-radius:10px;padding:10px 14px;font-size:12px;color:#6B7280;text-align:center;">
                Email : <strong>demo@facturefacile.ma</strong> · MDP : <strong>password</strong>
            </div>
            <p style="text-align:center;font-size:11px;color:#D1D5DB;margin:8px 0 0;">👥 Les membres d'équipe utilisent leurs identifiants configurés par l'administrateur</p>
        </div>
    </div>
</div>
</body>
</html>
