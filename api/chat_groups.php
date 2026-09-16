<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Non authentifié']); exit; }

$user      = getCurrentUser();
$isMember  = isTeamMember();
$memberId  = getTeamMemberId();
$workspaceId = $user['id']; // admin's user_id = workspace identifier

// Who am I in the member system?
$myType = $isMember ? 'team_member' : 'user';
$myId   = $isMember ? $memberId     : $user['id'];

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

/* ─── helpers ─── */
function isGroupMember(int $groupId, string $type, int $id): bool {
    global $pdo;
    $s = $pdo->prepare("SELECT 1 FROM chat_group_members WHERE group_id=? AND member_type=? AND member_id=?");
    $s->execute([$groupId, $type, $id]);
    return (bool)$s->fetch();
}

function getGroupOrFail(int $groupId, int $workspaceId): ?array {
    global $pdo;
    $s = $pdo->prepare("SELECT * FROM chat_groups WHERE id=? AND workspace_user_id=?");
    $s->execute([$groupId, $workspaceId]);
    return $s->fetch() ?: null;
}

function updateReadPosition(int $groupId, string $type, int $id, int $lastId): void {
    global $pdo;
    if ($lastId <= 0) return;
    $pdo->prepare("INSERT INTO chat_group_reads (group_id, member_type, member_id, last_read_id)
                   VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))")
        ->execute([$groupId, $type, $id, $lastId]);
}

function getGroupUnread(int $groupId, string $myType, int $myId, string $senderType, int $senderTeamId): int {
    global $pdo;
    $lastRead = (int)$pdo->prepare("SELECT COALESCE(last_read_id,0) FROM chat_group_reads WHERE group_id=? AND member_type=? AND member_id=?")
        ->execute([$groupId, $myType, $myId]) ? 0 : 0;
    $s = $pdo->prepare("SELECT COALESCE(last_read_id,0) FROM chat_group_reads WHERE group_id=? AND member_type=? AND member_id=?");
    $s->execute([$groupId, $myType, $myId]);
    $lastRead = (int)($s->fetchColumn() ?: 0);

    // Count messages newer than my last read that are not from me
    $s2 = $pdo->prepare("SELECT COUNT(*) FROM chat_messages
        WHERE group_id=? AND id>?
        AND NOT (sender_type=? AND COALESCE(sender_team_id,-1)=?)");
    $s2->execute([$groupId, $lastRead, $senderType, $senderTeamId]);
    return (int)$s2->fetchColumn();
}

switch ($action) {

    /* ─── list: all groups + DMs the caller belongs to ─── */
    case 'list':
        $stmt = $pdo->prepare("
            SELECT g.id, g.name, g.couleur, g.is_dm, g.created_at,
                (SELECT COUNT(*) FROM chat_group_members WHERE group_id=g.id) as member_count,
                (SELECT MAX(id) FROM chat_messages WHERE group_id=g.id) as last_msg_id,
                (SELECT message FROM chat_messages WHERE group_id=g.id ORDER BY id DESC LIMIT 1) as last_preview,
                (SELECT sender_nom FROM chat_messages WHERE group_id=g.id ORDER BY id DESC LIMIT 1) as last_sender,
                COALESCE((SELECT last_read_id FROM chat_group_reads WHERE group_id=g.id AND member_type=? AND member_id=?),0) as my_last_read
            FROM chat_groups g
            JOIN chat_group_members cgm ON cgm.group_id=g.id AND cgm.member_type=? AND cgm.member_id=?
            WHERE g.workspace_user_id=?
            ORDER BY last_msg_id DESC, g.created_at DESC
        ");
        $stmt->execute([$myType, $myId, $myType, $myId, $workspaceId]);
        $groups = $stmt->fetchAll();

        // Compute unread per group
        $senderTeamId = $isMember ? $memberId : -1;
        $senderType   = $isMember ? 'team_member' : 'user';
        foreach ($groups as &$g) {
            $lr = (int)$g['my_last_read'];
            $s  = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE group_id=? AND id>? AND NOT (sender_type=? AND COALESCE(sender_team_id,-1)=?)");
            $s->execute([$g['id'], $lr, $senderType, $senderTeamId]);
            $g['unread'] = (int)$s->fetchColumn();

            // For DMs: fetch the other person's info
            if ($g['is_dm']) {
                $s2 = $pdo->prepare("SELECT member_type, member_id FROM chat_group_members WHERE group_id=? AND NOT (member_type=? AND member_id=?) LIMIT 1");
                $s2->execute([$g['id'], $myType, $myId]);
                $other = $s2->fetch();
                if ($other) {
                    if ($other['member_type'] === 'user') {
                        $su = $pdo->prepare("SELECT nom FROM users WHERE id=?");
                        $su->execute([$other['member_id']]);
                        $row = $su->fetch();
                        $g['dm_other_name'] = $row['nom'] ?? 'Admin';
                        $g['dm_other_type'] = 'user';
                        $g['dm_other_id']   = $other['member_id'];
                    } else {
                        $sm = $pdo->prepare("SELECT nom, prenom, couleur FROM team_members WHERE id=?");
                        $sm->execute([$other['member_id']]);
                        $row = $sm->fetch();
                        $g['dm_other_name'] = trim(($row['prenom']??'').' '.($row['nom']??''));
                        $g['dm_other_couleur'] = $row['couleur'] ?? '#D97706';
                        $g['dm_other_type'] = 'team_member';
                        $g['dm_other_id']   = $other['member_id'];
                    }
                }
            }
        }
        unset($g);

        echo json_encode(['success'=>true,'groups'=>$groups]);
        break;

    /* ─── messages: load messages for a group ─── */
    case 'messages':
        $groupId = (int)($_GET['group_id'] ?? 0);
        $since   = (int)($_GET['since'] ?? 0);
        if (!$groupId || !isGroupMember($groupId, $myType, $myId)) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        $stmt = $pdo->prepare("
            SELECT id, sender_id, sender_nom, sender_type, sender_team_id, message,
                   file_name, file_path, file_type, file_size,
                   DATE_FORMAT(created_at,'%H:%i') as heure,
                   DATE_FORMAT(created_at,'%d/%m/%Y %H:%i') as datetime_full
            FROM chat_messages
            WHERE group_id=? AND id>?
            ORDER BY id DESC LIMIT 80
        ");
        $stmt->execute([$groupId, $since]);
        $msgs = array_reverse($stmt->fetchAll());

        // Update read position
        if ($msgs) {
            $lastId = (int)end($msgs)['id'];
            updateReadPosition($groupId, $myType, $myId, $lastId);
        }

        echo json_encode(['success'=>true,'messages'=>$msgs]);
        break;

    /* ─── send: post a message to a group ─── */
    case 'send':
        $groupId  = (int)($input['group_id'] ?? 0);
        $message  = trim($input['message'] ?? '');
        $fileName = trim($input['file_name'] ?? '');
        $filePath = trim($input['file_path'] ?? '');
        $fileType = trim($input['file_type'] ?? '');
        $fileSize = (int)($input['file_size'] ?? 0);

        if (!$groupId || !isGroupMember($groupId, $myType, $myId)) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        if (empty($message) && empty($fileName)) {
            echo json_encode(['success'=>false,'error'=>'Message vide']); break;
        }

        if ($isMember) {
            $tmData    = getTeamMemberData();
            $senderNom = trim(($tmData['prenom']??'').' '.($tmData['nom']??''));
            $sType     = 'team_member';
            $sTeamId   = $memberId;
        } else {
            $senderNom = $user['nom'];
            $sType     = 'admin';
            $sTeamId   = null;
        }

        $pdo->prepare("INSERT INTO chat_messages
            (admin_id, sender_id, sender_nom, sender_type, sender_team_id, group_id, message, file_name, file_path, file_type, file_size)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $workspaceId, $user['id'], $senderNom, $sType, $sTeamId,
                $groupId, $message,
                $fileName ?: null, $filePath ?: null, $fileType ?: null, $fileSize ?: null
            ]);

        $newId = (int)$pdo->lastInsertId();
        updateReadPosition($groupId, $myType, $myId, $newId);

        echo json_encode(['success'=>true]);
        break;

    /* ─── create: new group ─── */
    case 'create':
        $name    = trim($input['name'] ?? '');
        $couleur = trim($input['couleur'] ?? '#D97706');
        $members = $input['members'] ?? []; // [{type, id}, ...]

        if (!$name) { echo json_encode(['success'=>false,'error'=>'Nom requis']); break; }

        // Allowed colors
        $validColors = ['#D97706','#0891B2','#059669','#7C3AED','#DC2626','#1C1917'];
        if (!in_array($couleur, $validColors)) $couleur = '#D97706';

        $pdo->prepare("INSERT INTO chat_groups (workspace_user_id, name, couleur, is_dm) VALUES (?,?,?,0)")
            ->execute([$workspaceId, $name, $couleur]);
        $groupId = (int)$pdo->lastInsertId();

        // Add creator
        $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
            ->execute([$groupId, $myType, $myId]);

        // Add admin always (if creator is a member)
        if ($isMember) {
            $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
                ->execute([$groupId, 'user', $workspaceId]);
        }

        // Add other members
        $allowedMembers = _loadWorkspaceMembers($workspaceId);
        foreach ($members as $m) {
            $mType = $m['type'] ?? '';
            $mId   = (int)($m['id'] ?? 0);
            if (!$mType || !$mId) continue;
            // Verify member belongs to this workspace
            if (_verifyMember($mType, $mId, $workspaceId, $allowedMembers)) {
                $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
                    ->execute([$groupId, $mType, $mId]);
            }
        }

        echo json_encode(['success'=>true,'id'=>$groupId]);
        break;

    /* ─── start_dm: get or create 1-to-1 DM group ─── */
    case 'start_dm':
        $otherType = trim($input['member_type'] ?? '');
        $otherId   = (int)($input['member_id'] ?? 0);
        if (!$otherType || !$otherId) { echo json_encode(['success'=>false,'error'=>'Paramètre manquant']); break; }

        // Verify the other person is in this workspace
        $allowed = _loadWorkspaceMembers($workspaceId);
        if (!_verifyMember($otherType, $otherId, $workspaceId, $allowed)) {
            echo json_encode(['success'=>false,'error'=>'Membre introuvable']); break;
        }

        // Check for existing DM group between myType/myId and otherType/otherId
        $existingId = _findDmGroup($workspaceId, $myType, $myId, $otherType, $otherId);

        if ($existingId) {
            echo json_encode(['success'=>true,'group_id'=>$existingId,'exists'=>true]);
            break;
        }

        // Create DM group
        $otherName = _getMemberName($otherType, $otherId);
        $myName    = _getMemberName($myType, $myId);

        $pdo->prepare("INSERT INTO chat_groups (workspace_user_id, name, couleur, is_dm) VALUES (?,?,?,1)")
            ->execute([$workspaceId, $myName.' & '.$otherName, '#1C1917']);
        $groupId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
            ->execute([$groupId, $myType, $myId]);
        $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
            ->execute([$groupId, $otherType, $otherId]);

        echo json_encode(['success'=>true,'group_id'=>$groupId,'exists'=>false]);
        break;

    /* ─── members: list members of a group ─── */
    case 'members':
        $groupId = (int)($_GET['group_id'] ?? 0);
        if (!$groupId || !isGroupMember($groupId, $myType, $myId)) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        $stmt = $pdo->prepare("SELECT member_type, member_id FROM chat_group_members WHERE group_id=?");
        $stmt->execute([$groupId]);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $r) {
            if ($r['member_type'] === 'user') {
                $s = $pdo->prepare("SELECT id, nom, email FROM users WHERE id=?");
                $s->execute([$r['member_id']]);
                $u = $s->fetch();
                if ($u) $result[] = ['type'=>'user','id'=>$u['id'],'name'=>$u['nom'],'initials'=>strtoupper(mb_substr($u['nom'],0,1)),'couleur'=>'#D97706'];
            } else {
                $s = $pdo->prepare("SELECT id, nom, prenom, couleur FROM team_members WHERE id=?");
                $s->execute([$r['member_id']]);
                $m = $s->fetch();
                if ($m) {
                    $nm = trim(($m['prenom']??'').' '.$m['nom']);
                    $result[] = ['type'=>'team_member','id'=>$m['id'],'name'=>$nm,
                        'initials'=>strtoupper(mb_substr($m['prenom']?:$m['nom'],0,1)).strtoupper(mb_substr($m['nom'],0,1)),
                        'couleur'=>$m['couleur']??'#D97706'];
                }
            }
        }
        echo json_encode(['success'=>true,'members'=>$result]);
        break;

    /* ─── add_member ─── */
    case 'add_member':
        $groupId = (int)($input['group_id'] ?? 0);
        $mType   = $input['member_type'] ?? '';
        $mId     = (int)($input['member_id'] ?? 0);
        if (!$groupId || !isGroupMember($groupId, $myType, $myId)) {
            echo json_encode(['success'=>false,'error'=>'Accès refusé']); break;
        }
        $group = getGroupOrFail($groupId, $workspaceId);
        if (!$group || $group['is_dm']) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); break; }
        $allowed = _loadWorkspaceMembers($workspaceId);
        if (!_verifyMember($mType, $mId, $workspaceId, $allowed)) {
            echo json_encode(['success'=>false,'error'=>'Membre introuvable']); break;
        }
        $pdo->prepare("INSERT IGNORE INTO chat_group_members (group_id, member_type, member_id) VALUES (?,?,?)")
            ->execute([$groupId, $mType, $mId]);
        echo json_encode(['success'=>true]);
        break;

    /* ─── remove_member ─── */
    case 'remove_member':
        $groupId = (int)($input['group_id'] ?? 0);
        $mType   = $input['member_type'] ?? '';
        $mId     = (int)($input['member_id'] ?? 0);
        // Only admin (user type) can remove members, or member removes themselves
        if (!$groupId) { echo json_encode(['success'=>false,'error'=>'Groupe manquant']); break; }
        $group = getGroupOrFail($groupId, $workspaceId);
        if (!$group || $group['is_dm']) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); break; }
        if (!isGroupMember($groupId, $myType, $myId)) { echo json_encode(['success'=>false,'error'=>'Accès refusé']); break; }
        if (!$isMember && ($mType !== $myType || $mId !== $myId)) {
            // Admin can remove anyone
        } elseif ($mType === $myType && $mId === $myId) {
            // Member removes themselves
        } else {
            echo json_encode(['success'=>false,'error'=>'Non autorisé']); break;
        }
        $pdo->prepare("DELETE FROM chat_group_members WHERE group_id=? AND member_type=? AND member_id=?")
            ->execute([$groupId, $mType, $mId]);
        echo json_encode(['success'=>true]);
        break;

    /* ─── rename ─── */
    case 'rename':
        $groupId = (int)($input['group_id'] ?? 0);
        $name    = trim($input['name'] ?? '');
        if (!$name || !$groupId) { echo json_encode(['success'=>false,'error'=>'Paramètre manquant']); break; }
        $group = getGroupOrFail($groupId, $workspaceId);
        if (!$group || !isGroupMember($groupId, $myType, $myId)) { echo json_encode(['success'=>false,'error'=>'Accès refusé']); break; }
        $pdo->prepare("UPDATE chat_groups SET name=? WHERE id=?")->execute([$name, $groupId]);
        echo json_encode(['success'=>true]);
        break;

    /* ─── delete ─── */
    case 'delete':
        $groupId = (int)($input['group_id'] ?? 0);
        $group   = getGroupOrFail($groupId, $workspaceId);
        if (!$group) { echo json_encode(['success'=>false,'error'=>'Groupe introuvable']); break; }
        // Only admin (user type) can delete groups
        if ($isMember) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); break; }
        $pdo->prepare("DELETE FROM chat_messages WHERE group_id=?")->execute([$groupId]);
        $pdo->prepare("DELETE FROM chat_group_members WHERE group_id=?")->execute([$groupId]);
        $pdo->prepare("DELETE FROM chat_group_reads WHERE group_id=?")->execute([$groupId]);
        $pdo->prepare("DELETE FROM chat_groups WHERE id=?")->execute([$groupId]);
        echo json_encode(['success'=>true]);
        break;

    /* ─── count: total unread across all groups ─── */
    case 'count':
        $stmt = $pdo->prepare("
            SELECT g.id,
                COALESCE((SELECT last_read_id FROM chat_group_reads WHERE group_id=g.id AND member_type=? AND member_id=?),0) as lr
            FROM chat_groups g
            JOIN chat_group_members cgm ON cgm.group_id=g.id AND cgm.member_type=? AND cgm.member_id=?
            WHERE g.workspace_user_id=?
        ");
        $stmt->execute([$myType,$myId,$myType,$myId,$workspaceId]);
        $gs = $stmt->fetchAll();
        $total = 0;
        $sType = $isMember ? 'team_member' : 'admin';
        $sTid  = $isMember ? $memberId : -1;
        foreach ($gs as $g) {
            $s = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE group_id=? AND id>? AND NOT (sender_type=? AND COALESCE(sender_team_id,-1)=?)");
            $s->execute([$g['id'], $g['lr'], $sType, $sTid]);
            $total += (int)$s->fetchColumn();
        }
        echo json_encode(['success'=>true,'count'=>$total]);
        break;

    /* ─── upload: file attachment ─── */
    case 'upload':
        if (empty($_FILES['file'])) { echo json_encode(['success'=>false,'error'=>'Aucun fichier']); break; }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'error'=>'Erreur upload']); break; }
        if ($file['size'] > 10 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'Fichier > 10 Mo']); break; }
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf','text/plain','text/csv',
            'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip'];
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed)) { echo json_encode(['success'=>false,'error'=>'Type non autorisé']); break; }
        $dir = __DIR__ . '/../uploads/chat/' . $workspaceId . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safe  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fname = $safe . '_' . uniqid() . ($ext ? '.'.$ext : '');
        if (!move_uploaded_file($file['tmp_name'], $dir . $fname)) { echo json_encode(['success'=>false,'error'=>'Déplacement impossible']); break; }
        echo json_encode(['success'=>true,'file_name'=>$file['name'],'file_path'=>'uploads/chat/'.$workspaceId.'/'.$fname,'file_type'=>$mime,'file_size'=>$file['size']]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}

/* ─── private helpers ─── */
function _loadWorkspaceMembers(int $workspaceId): array {
    global $pdo;
    $s = $pdo->prepare("SELECT id FROM team_members WHERE user_id=? AND statut='actif'");
    $s->execute([$workspaceId]);
    return $s->fetchAll(\PDO::FETCH_COLUMN) ?: [];
}

function _verifyMember(string $type, int $id, int $workspaceId, array $tmIds): bool {
    global $pdo;
    if ($type === 'user') {
        return $id === $workspaceId;
    }
    return in_array($id, $tmIds);
}

function _findDmGroup(int $workspaceId, string $t1, int $id1, string $t2, int $id2): ?int {
    global $pdo;
    $s = $pdo->prepare("
        SELECT g.id FROM chat_groups g
        WHERE g.workspace_user_id=? AND g.is_dm=1
        AND (SELECT COUNT(*) FROM chat_group_members WHERE group_id=g.id)=2
        AND EXISTS (SELECT 1 FROM chat_group_members WHERE group_id=g.id AND member_type=? AND member_id=?)
        AND EXISTS (SELECT 1 FROM chat_group_members WHERE group_id=g.id AND member_type=? AND member_id=?)
        LIMIT 1
    ");
    $s->execute([$workspaceId, $t1, $id1, $t2, $id2]);
    $row = $s->fetch();
    return $row ? (int)$row['id'] : null;
}

function _getMemberName(string $type, int $id): string {
    global $pdo;
    if ($type === 'user') {
        $s = $pdo->prepare("SELECT nom FROM users WHERE id=?");
        $s->execute([$id]);
        return $s->fetchColumn() ?: 'Admin';
    }
    $s = $pdo->prepare("SELECT nom, prenom FROM team_members WHERE id=?");
    $s->execute([$id]);
    $r = $s->fetch();
    return $r ? trim(($r['prenom']??'').' '.$r['nom']) : 'Membre';
}
