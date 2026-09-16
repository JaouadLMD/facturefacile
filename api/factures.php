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
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'save':
        saveFacture($user, $input);
        break;
    case 'update_statut':
        updateStatut($user, $input);
        break;
    case 'delete':
        deleteFacture($user, $input);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
}

function saveFacture(array $user, array $data): void {
    global $pdo;

    if (!canCreateFacture($user)) {
        echo json_encode(['success' => false, 'error' => 'Limite de factures atteinte. Passez au plan Pro pour des factures illimitées.', 'limit' => true]);
        return;
    }

    $clientNom     = trim($data['client_nom'] ?? 'Client');
    $clientEmail   = trim($data['client_email'] ?? '');
    $clientAdresse = trim($data['client_adresse'] ?? '');
    $clientIce     = trim($data['client_ice'] ?? '');
    $clientId      = !empty($data['client_id']) ? (int)$data['client_id'] : null;
    $notes         = trim($data['notes'] ?? '');
    $devise        = trim($data['devise'] ?? $user['devise'] ?? 'MAD');
    $dateFacture   = $data['date_facture'] ?? date('Y-m-d');
    $dateEcheance  = !empty($data['date_echeance']) ? $data['date_echeance'] : null;
    $statut        = $data['statut'] ?? 'brouillon';
    $items         = $data['items'] ?? [];
    $numero        = genererNumeroFacture($user['id']);

    // Calcul totaux
    $sousTotalHT = 0;
    $totalTVA    = 0;
    foreach ($items as $item) {
        $qte  = (float)($item['quantite'] ?? 1);
        $pu   = (float)($item['prix_unitaire'] ?? 0);
        $tva  = (float)($item['tva'] ?? 20);
        $subtotal = $qte * $pu;
        $sousTotalHT += $subtotal;
        $totalTVA    += $subtotal * $tva / 100;
    }
    $total = round($sousTotalHT + $totalTVA, 2);

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO factures
                (user_id, client_id, client_nom, client_email, client_adresse, client_ice,
                 numero, date_facture, date_echeance, statut, sous_total, tva_montant, total, devise, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $user['id'], $clientId, $clientNom, $clientEmail, $clientAdresse, $clientIce,
            $numero, $dateFacture, $dateEcheance, $statut,
            round($sousTotalHT, 2), round($totalTVA, 2), $total, $devise, $notes
        ]);
        $factureId = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO facture_items (facture_id, description, quantite, prix_unitaire, tva, total)
            VALUES (?,?,?,?,?,?)
        ");
        foreach ($items as $item) {
            $qte   = (float)($item['quantite'] ?? 1);
            $pu    = (float)($item['prix_unitaire'] ?? 0);
            $tva   = (float)($item['tva'] ?? 20);
            $total_item = round($qte * $pu, 2);
            $stmtItem->execute([
                $factureId,
                trim($item['description'] ?? ''),
                $qte, $pu, $tva, $total_item
            ]);
        }

        // Incrémenter compteur factures user
        $pdo->prepare("UPDATE users SET factures_count = factures_count + 1 WHERE id = ?")
            ->execute([$user['id']]);

        $pdo->commit();
        logAction($user['id'], 'create', 'facture', (int)$factureId, $numero, 'Client : ' . $clientNom . ' — ' . $total . ' ' . $devise);
        echo json_encode(['success' => true, 'facture_id' => $factureId, 'numero' => $numero]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde.']);
    }
}

function updateStatut(array $user, array $data): void {
    global $pdo;
    $id     = (int)($data['id'] ?? 0);
    $statut = $data['statut'] ?? '';
    $allowed = ['brouillon','envoyee','payee','impayee','annulee'];
    if (!in_array($statut, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'Statut invalide.']);
        return;
    }
    $stmt = $pdo->prepare("UPDATE factures SET statut = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$statut, $id, $user['id']]);
    // Get numero for audit log
    $ref = $pdo->prepare("SELECT numero FROM factures WHERE id=?");
    $ref->execute([$id]);
    $num = $ref->fetchColumn();
    logAction($user['id'], 'status_change', 'facture', $id, (string)$num, 'Nouveau statut : ' . $statut);
    echo json_encode(['success' => true]);
}

function deleteFacture(array $user, array $data): void {
    global $pdo;
    $id = (int)($data['id'] ?? 0);
    // Grab ref before delete
    $ref = $pdo->prepare("SELECT numero FROM factures WHERE id=? AND user_id=?");
    $ref->execute([$id, $user['id']]);
    $num = $ref->fetchColumn();
    $stmt = $pdo->prepare("DELETE FROM factures WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user['id']]);
    if ($num) logAction($user['id'], 'delete', 'facture', $id, (string)$num);
    echo json_encode(['success' => true]);
}
