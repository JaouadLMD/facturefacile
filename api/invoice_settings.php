<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn() || (isTeamMember() && !isPrivilegedMember())) {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'save':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $validKeys = ['description','qty','price','tva','total'];
        $cols = [
            'description' => substr(trim($input['description'] ?? 'Description'), 0, 50),
            'qty'         => substr(trim($input['qty']         ?? 'Qté'),         0, 50),
            'price'       => substr(trim($input['price']       ?? 'Prix U.'),     0, 50),
            'tva'         => substr(trim($input['tva']         ?? 'TVA %'),       0, 50),
            'total'       => substr(trim($input['total']       ?? 'Total'),       0, 50),
        ];
        // Column order
        $order = array_values(array_intersect($input['order'] ?? [], $validKeys));
        if (count($order) !== 5) $order = $validKeys;
        $cols['order'] = $order;

        // Header display mode: 'logo_name' (logo + nom) or 'logo_only' (logo seul)
        $hdMode = in_array($input['header_display'] ?? '', ['logo_name','logo_only'], true)
                  ? $input['header_display'] : 'logo_name';
        $cols['header_display'] = $hdMode;

        $header = substr(trim($input['header'] ?? ''), 0, 1000);
        $footer = substr(trim($input['footer'] ?? ''), 0, 1000);

        $pdo->prepare("UPDATE users SET invoice_columns=?, invoice_header=?, invoice_footer=? WHERE id=?")
            ->execute([json_encode($cols, JSON_UNESCAPED_UNICODE), $header ?: null, $footer ?: null, $user['id']]);

        echo json_encode(['success' => true]);
        break;

    case 'logo':
        if (empty($_FILES['logo'])) {
            echo json_encode(['success' => false, 'error' => 'Aucun fichier reçu']);
            break;
        }
        $file = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowed, true)) {
            echo json_encode(['success' => false, 'error' => 'Format non supporté (JPEG, PNG, GIF, WebP, SVG)']);
            break;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'Fichier trop grand (max 2 Mo)']);
            break;
        }

        $uploadDir = __DIR__ . '/../uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Remove old logo if present
        $stmt = $pdo->prepare("SELECT logo_path FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $old = $stmt->fetchColumn();
        if ($old) {
            $oldFile = __DIR__ . '/../' . ltrim($old, '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'logo_' . $user['id'] . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'error' => 'Échec de l\'enregistrement']);
            break;
        }

        $relativePath = 'uploads/logos/' . $filename;
        $pdo->prepare("UPDATE users SET logo_path=? WHERE id=?")
            ->execute([$relativePath, $user['id']]);

        echo json_encode(['success' => true, 'logo_url' => APP_URL . '/' . $relativePath]);
        break;

    case 'logo_delete':
        $stmt = $pdo->prepare("SELECT logo_path FROM users WHERE id=?");
        $stmt->execute([$user['id']]);
        $old = $stmt->fetchColumn();
        if ($old) {
            $oldFile = __DIR__ . '/../' . ltrim($old, '/');
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }
        $pdo->prepare("UPDATE users SET logo_path=NULL WHERE id=?")->execute([$user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'colors':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $allowed = ['primary','accent','text','text_soft','bg','parties_bg','border','table_alt'];
        $colors = [];
        foreach ($allowed as $k) {
            $v = trim($input[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $v)) {
                $colors[$k] = $v;
            }
        }
        $pdo->prepare("UPDATE users SET invoice_colors=? WHERE id=?")
            ->execute([json_encode($colors, JSON_UNESCAPED_UNICODE), $user['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'footer_info':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $fi = [
            'show'      => !empty($input['show']),
            'position'  => in_array($input['position'] ?? '', ['top','bottom','both'], true) ? $input['position'] : 'bottom',
            'adresse'   => substr(trim($input['adresse']   ?? ''), 0, 200),
            'telephone' => substr(trim($input['telephone'] ?? ''), 0, 50),
            'email'     => substr(trim($input['email']     ?? ''), 0, 100),
            'site_web'  => substr(trim($input['site_web']  ?? ''), 0, 100),
            'cnss'      => substr(trim($input['cnss']      ?? ''), 0, 50),
            'ice'       => substr(trim($input['ice']       ?? ''), 0, 50),
            'rc'        => substr(trim($input['rc']        ?? ''), 0, 50),
            'patente'   => substr(trim($input['patente']   ?? ''), 0, 50),
            'font_size' => max(9, min(16, (int)($input['font_size'] ?? 11))),
            'font_color'=> preg_match('/^#[0-9a-fA-F]{3,8}$/', $input['font_color'] ?? '') ? $input['font_color'] : '#64748b',
            'separator' => substr($input['separator'] ?? ' · ', 0, 10),
        ];
        $pdo->prepare("UPDATE users SET invoice_footer_info=? WHERE id=?")
            ->execute([json_encode($fi, JSON_UNESCAPED_UNICODE), $user['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
}
