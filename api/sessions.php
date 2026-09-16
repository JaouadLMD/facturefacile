<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn() || isTeamMember()) {
    echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit;
}

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {

    case 'list':
        // All login history for this workspace: admin + all team members
        $stmt = $pdo->prepare("
            SELECT lh.id, lh.user_id, lh.team_member_id, lh.ip, lh.user_agent,
                   lh.login_at, lh.logout_at, lh.is_active,
                   CASE WHEN lh.team_member_id IS NULL THEN 'admin' ELSE 'member' END as account_type,
                   CASE WHEN lh.team_member_id IS NULL THEN u.nom ELSE CONCAT(COALESCE(tm.prenom,''),' ',tm.nom) END as account_name,
                   CASE WHEN lh.team_member_id IS NULL THEN u.email ELSE tm.email END as account_email,
                   tm.poste as member_poste,
                   tm.couleur as member_couleur
            FROM login_history lh
            JOIN users u ON u.id = lh.user_id
            LEFT JOIN team_members tm ON tm.id = lh.team_member_id
            WHERE lh.user_id = ?
            ORDER BY lh.login_at DESC
            LIMIT 200
        ");
        $stmt->execute([$user['id']]);
        $sessions = $stmt->fetchAll();

        // Parse user agent
        foreach ($sessions as &$s) {
            $s['browser'] = _parseBrowser($s['user_agent']);
            $s['account_name'] = trim($s['account_name']);
        }
        unset($s);

        echo json_encode(['success'=>true,'sessions'=>$sessions]);
        break;

    case 'disconnect':
        $targetType = $input['target_type'] ?? ''; // 'user' or 'team_member'
        $targetId   = (int)($input['target_id'] ?? 0);

        if (!$targetType || !$targetId) {
            echo json_encode(['success'=>false,'error'=>'Paramètre manquant']); break;
        }

        if ($targetType === 'user') {
            // Disconnecting the admin themselves — not allowed via this endpoint
            if ($targetId === $user['id']) {
                echo json_encode(['success'=>false,'error'=>'Utilisez le bouton Déconnexion pour vous déconnecter.']); break;
            }
        } elseif ($targetType === 'team_member') {
            // Verify member belongs to this workspace
            $check = $pdo->prepare("SELECT id FROM team_members WHERE id=? AND user_id=?");
            $check->execute([$targetId, $user['id']]);
            if (!$check->fetch()) {
                echo json_encode(['success'=>false,'error'=>'Membre introuvable']); break;
            }
            // Set force_logout flag
            $pdo->prepare("UPDATE team_members SET force_logout=1 WHERE id=?")->execute([$targetId]);
            // Mark all active sessions as inactive
            $pdo->prepare("UPDATE login_history SET is_active=0, logout_at=NOW() WHERE team_member_id=? AND is_active=1")->execute([$targetId]);
        } else {
            echo json_encode(['success'=>false,'error'=>'Type invalide']); break;
        }

        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}

function _parseBrowser(string $ua): string {
    if (!$ua) return 'Inconnu';
    if (str_contains($ua, 'Edg/'))    return 'Edge';
    if (str_contains($ua, 'Chrome'))  return 'Chrome';
    if (str_contains($ua, 'Firefox')) return 'Firefox';
    if (str_contains($ua, 'Safari'))  return 'Safari';
    if (str_contains($ua, 'Opera'))   return 'Opera';
    if (str_contains($ua, 'MSIE') || str_contains($ua, 'Trident')) return 'Internet Explorer';
    // Mobile
    if (str_contains($ua, 'Android')) return 'Android';
    if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
    return 'Autre';
}
