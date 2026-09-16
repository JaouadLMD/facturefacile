<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Non authentifié']); exit; }
if (isTeamMember() && !isPrivilegedMember()) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); exit; }

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'save':
        $id     = (int)($input['id'] ?? 0);
        $nom    = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email  = trim($input['email'] ?? '');
        $tel    = trim($input['telephone'] ?? '');
        $poste  = $input['poste'] ?? 'commercial';
        $statut = $input['statut'] ?? 'actif';
        $notes  = trim($input['notes'] ?? '');
        $perms  = json_encode($input['permissions'] ?? []);
        $rawPwd = $input['password'] ?? '';

        $allowed = ['gerant','directeur','commercial','comptable','technicien','assistant','rh','autre'];
        if (!in_array($poste, $allowed)) $poste = 'autre';
        if (!$nom) { echo json_encode(['success'=>false,'error'=>'Nom requis']); break; }

        // Email is the login email — validate uniqueness
        if ($email) {
            $chkUser = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $chkUser->execute([$email]);
            if ($chkUser->fetch()) {
                echo json_encode(['success'=>false,'error'=>'Cet email est déjà utilisé par un compte administrateur.']);
                break;
            }
            $chkMember = $pdo->prepare("SELECT id FROM team_members WHERE email=? AND user_id=? AND id!=?");
            $chkMember->execute([$email, $user['id'], $id]);
            if ($chkMember->fetch()) {
                echo json_encode(['success'=>false,'error'=>'Cet email est déjà utilisé par un autre membre.']);
                break;
            }
        }

        // Plan limit for new members
        if (!$id) {
            $planLimits = getPlanLimits($user['plan'] ?? 'gratuit');
            $countStmt  = $pdo->prepare("SELECT COUNT(*) FROM team_members WHERE user_id=?");
            $countStmt->execute([$user['id']]);
            if ((int)$countStmt->fetchColumn() >= $planLimits['team_members']) {
                echo json_encode(['success'=>false,'error'=>'Limite de membres atteinte pour votre plan.']);
                break;
            }
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT id,password FROM team_members WHERE id=? AND user_id=?");
            $stmt->execute([$id, $user['id']]);
            $existing = $stmt->fetch();
            if (!$existing) { echo json_encode(['success'=>false,'error'=>'Membre introuvable']); break; }

            $hashedPwd = $existing['password'];
            if ($rawPwd !== '') {
                if (strlen($rawPwd) < 6) { echo json_encode(['success'=>false,'error'=>'Mot de passe trop court (min 6 caractères)']); break; }
                $hashedPwd = password_hash($rawPwd, PASSWORD_DEFAULT);
            }
            // If email cleared, also clear password
            if ($email === '') $hashedPwd = null;

            $pdo->prepare("UPDATE team_members SET nom=?,prenom=?,email=?,telephone=?,poste=?,statut=?,notes=?,permissions=?,login_email=?,password=? WHERE id=?")
                ->execute([$nom,$prenom,$email,$tel,$poste,$statut,$notes,$perms,$email ?: null,$hashedPwd,$id]);
            logAction($user['id'],'update','equipe',$id,$nom,'Poste: '.$poste);
        } else {
            $hashedPwd = null;
            if ($email && $rawPwd !== '') {
                if (strlen($rawPwd) < 6) { echo json_encode(['success'=>false,'error'=>'Mot de passe trop court (min 6 caractères)']); break; }
                $hashedPwd = password_hash($rawPwd, PASSWORD_DEFAULT);
            }
            $pdo->prepare("INSERT INTO team_members (user_id,nom,prenom,email,telephone,poste,statut,notes,permissions,login_email,password) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$user['id'],$nom,$prenom,$email,$tel,$poste,$statut,$notes,$perms,$email ?: null,$hashedPwd]);
            $id = (int)$pdo->lastInsertId();
            logAction($user['id'],'create','equipe',$id,$nom,'Poste: '.$poste);
        }
        echo json_encode(['success'=>true,'id'=>$id]);
        break;

    case 'delete':
        $id   = (int)($input['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT nom FROM team_members WHERE id=? AND user_id=?");
        $stmt->execute([$id,$user['id']]);
        $m = $stmt->fetch();
        if (!$m) { echo json_encode(['success'=>false,'error'=>'Membre introuvable']); break; }
        $pdo->prepare("DELETE FROM team_members WHERE id=?")->execute([$id]);
        logAction($user['id'],'delete','equipe',$id,$m['nom'],'');
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}
