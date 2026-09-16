<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nom'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm'] ?? '';
    $entreprise = trim($_POST['entreprise'] ?? '');

    if (empty($nom) || empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif ($password !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        // Check email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé.';
        } elseif (register(['nom' => $nom, 'email' => $email, 'password' => $password, 'entreprise' => $entreprise])) {
            login($email, $password);
            header('Location: ' . APP_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Erreur lors de la création du compte.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription – FactureFacile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-900 flex items-center justify-center p-4">

<div class="w-full max-w-md bg-white rounded-3xl shadow-2xl p-10">
    <div class="flex items-center gap-2 mb-6">
        <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center font-bold text-white text-lg">F</div>
        <span class="font-bold text-indigo-800 text-xl">FactureFacile</span>
    </div>

    <h2 class="text-2xl font-bold text-slate-800 mb-1">Créez votre compte</h2>
    <p class="text-slate-500 text-sm mb-6">Gratuit · 3 factures incluses · Sans CB</p>

    <?php if ($error): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Nom complet *</label>
                <input type="text" name="nom" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                       placeholder="Votre nom"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div class="col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Entreprise / Activité</label>
                <input type="text" name="entreprise" value="<?= htmlspecialchars($_POST['entreprise'] ?? '') ?>"
                       placeholder="Ex: Design Studio, Auto-entrepreneur..."
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="vous@exemple.com"
                   class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Mot de passe *</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Confirmer *</label>
                <input type="password" name="confirm" required placeholder="••••••••"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
        </div>
        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition text-sm">
            Créer mon compte gratuitement →
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-slate-500">
        Déjà un compte ? <a href="<?= APP_URL ?>/index.php" class="text-indigo-600 font-semibold hover:underline">Se connecter</a>
    </p>
</div>
</body>
</html>
