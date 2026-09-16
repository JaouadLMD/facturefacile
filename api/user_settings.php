<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Non authentifié']); exit; }

$user   = getCurrentUser();
$isMember = isTeamMember();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'upload_photo':
        if (empty($_FILES['photo'])) { echo json_encode(['success'=>false,'error'=>'Aucun fichier']); break; }
        $file    = $_FILES['photo'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($file['type'], $allowed, true)) { echo json_encode(['success'=>false,'error'=>'Format non supporté (JPEG, PNG, GIF, WebP)']); break; }
        if ($file['size'] > 3 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'Fichier trop grand (max 3 Mo)']); break; }

        $uploadDir = __DIR__ . '/../uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Remove old photo
        if ($isMember) {
            $tm = getTeamMemberData();
            $old = $tm['photo_path'] ?? null;
            $table = 'team_members'; $idCol = 'id'; $idVal = getTeamMemberId();
        } else {
            $old = $user['photo_path'] ?? null;
            $table = 'users'; $idCol = 'id'; $idVal = $user['id'];
        }
        if ($old) { $oldFile = __DIR__ . '/../' . ltrim($old,'/'); if (file_exists($oldFile)) @unlink($oldFile); }

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $prefix   = $isMember ? 'member_' : 'user_';
        $filename = $prefix . $idVal . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) { echo json_encode(['success'=>false,'error'=>'Échec enregistrement']); break; }

        $relPath = 'uploads/avatars/' . $filename;
        $pdo->prepare("UPDATE {$table} SET photo_path=? WHERE {$idCol}=?")->execute([$relPath, $idVal]);
        echo json_encode(['success'=>true,'photo_url'=> APP_URL.'/'.$relPath]);
        break;

    case 'delete_photo':
        if ($isMember) {
            $tm = getTeamMemberData();
            $old = $tm['photo_path'] ?? null;
            $pdo->prepare("UPDATE team_members SET photo_path=NULL WHERE id=?")->execute([getTeamMemberId()]);
        } else {
            $old = $user['photo_path'] ?? null;
            $pdo->prepare("UPDATE users SET photo_path=NULL WHERE id=?")->execute([$user['id']]);
        }
        if ($old) { $f = __DIR__.'/../'.ltrim($old,'/'); if (file_exists($f)) @unlink($f); }
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}
