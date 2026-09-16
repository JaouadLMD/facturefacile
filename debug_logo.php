<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
$user = getCurrentUser();

$logoPath = $user['logo_path'] ?? '';

echo '<pre style="font-family:monospace;font-size:14px;padding:20px;">';
echo "=== DEBUG LOGO ===\n\n";
echo "1. logo_path en BDD        : " . var_export($logoPath, true) . "\n";
echo "2. DOCUMENT_ROOT           : " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "3. APP_URL                 : " . APP_URL . "\n";
echo "4. __DIR__                 : " . __DIR__ . "\n\n";

if ($logoPath) {
    $fullPath1 = rtrim($_SERVER['DOCUMENT_ROOT'],'/') . '/' . ltrim($logoPath,'/');
    $fullPath2 = __DIR__ . '/' . ltrim($logoPath, '/');
    $fullPath3 = 'C:/wamp64/www/facturefacile/' . ltrim($logoPath, '/');

    echo "5. Chemin construit (DOCUMENT_ROOT) : $fullPath1\n";
    echo "   → file_exists ? " . (file_exists($fullPath1) ? '✅ OUI' : '❌ NON') . "\n\n";

    echo "6. Chemin construit (__DIR__)       : $fullPath2\n";
    echo "   → file_exists ? " . (file_exists($fullPath2) ? '✅ OUI' : '❌ NON') . "\n\n";

    echo "7. Chemin absolu WAMP              : $fullPath3\n";
    echo "   → file_exists ? " . (file_exists($fullPath3) ? '✅ OUI' : '❌ NON') . "\n\n";

    echo "8. URL de l'image : " . APP_URL . '/' . ltrim($logoPath,'/') . "\n\n";

    // List uploads directory
    $uploadsDir = __DIR__ . '/uploads';
    echo "9. Contenu du dossier /uploads :\n";
    if (is_dir($uploadsDir)) {
        $files = scandir($uploadsDir);
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') echo "   - $f\n";
        }
    } else {
        echo "   ❌ Dossier /uploads inexistant !\n";
    }

    // Check logos subdir
    $logosDir = __DIR__ . '/uploads/logos';
    echo "\n10. Contenu du dossier /uploads/logos :\n";
    if (is_dir($logosDir)) {
        $files = scandir($logosDir);
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..') echo "   - $f\n";
        }
    } else {
        echo "   ❌ Dossier /uploads/logos inexistant !\n";
    }
} else {
    echo "❌ logo_path est VIDE en base de données.\n";
    echo "   Le logo n'a pas été sauvegardé en BDD après l'upload.\n";
}

echo '</pre>';

// Afficher l'image si trouvée
if ($logoPath) {
    $fullPath1 = rtrim($_SERVER['DOCUMENT_ROOT'],'/') . '/' . ltrim($logoPath,'/');
    if (file_exists($fullPath1)) {
        echo '<p>Image via DOCUMENT_ROOT :</p>';
        echo '<img src="' . APP_URL . '/' . htmlspecialchars(ltrim($logoPath,'/')) . '" style="max-height:100px;border:2px solid green;">';
    }
}
