<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié.']);
    exit;
}

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'list':
        $stmt = $pdo->prepare("SELECT id, nom, email, telephone, ville FROM clients WHERE user_id = ? ORDER BY nom");
        $stmt->execute([$user['id']]);
        echo json_encode(['success' => true, 'clients' => $stmt->fetchAll()]);
        break;

    case 'save':
        $id  = (int)($input['id'] ?? 0);
        $fields = [
            'nom'       => trim($input['nom'] ?? ''),
            'email'     => trim($input['email'] ?? ''),
            'telephone' => trim($input['telephone'] ?? ''),
            'adresse'   => trim($input['adresse'] ?? ''),
            'ville'     => trim($input['ville'] ?? ''),
            'pays'      => trim($input['pays'] ?? 'Maroc'),
            'ice'       => trim($input['ice'] ?? ''),
            'notes'     => trim($input['notes'] ?? ''),
        ];
        if (empty($fields['nom'])) {
            echo json_encode(['success' => false, 'error' => 'Le nom est obligatoire.']);
            break;
        }
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE clients SET nom=?,email=?,telephone=?,adresse=?,ville=?,pays=?,ice=?,notes=?
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([...array_values($fields), $id, $user['id']]);
            logAction($user['id'], 'update', 'client', $id, $fields['nom']);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO clients (user_id,nom,email,telephone,adresse,ville,pays,ice,notes)
                VALUES (?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([$user['id'], ...array_values($fields)]);
            $newId = (int)$pdo->lastInsertId();
            logAction($user['id'], 'create', 'client', $newId, $fields['nom']);
            echo json_encode(['success' => true, 'id' => $newId]);
        }
        break;

    case 'delete':
        $id = (int)($input['id'] ?? 0);
        $ref = $pdo->prepare("SELECT nom FROM clients WHERE id=? AND user_id=?");
        $ref->execute([$id, $user['id']]);
        $nom = $ref->fetchColumn();
        $pdo->prepare("DELETE FROM clients WHERE id=? AND user_id=?")->execute([$id, $user['id']]);
        if ($nom) logAction($user['id'], 'delete', 'client', $id, (string)$nom);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
}
