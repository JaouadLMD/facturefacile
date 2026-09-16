<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Non authentifié']); exit; }

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'save':   saveDevis($user, $input);   break;
    case 'statut': updateStatut($user, $input); break;
    case 'delete': deleteDevis($user, $input); break;
    case 'convert': convertToFacture($user, $input); break;
    default: echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}

function saveDevis(array $user, array $data): void {
    global $pdo;
    $clientNom     = trim($data['client_nom'] ?? 'Client');
    $clientEmail   = trim($data['client_email'] ?? '');
    $clientId      = !empty($data['client_id']) ? (int)$data['client_id'] : null;
    $notes         = trim($data['notes'] ?? '');
    $devise        = trim($data['devise'] ?? $user['devise'] ?? 'MAD');
    $dateDevis     = $data['date_devis'] ?? date('Y-m-d');
    $dateValidite  = !empty($data['date_validite']) ? $data['date_validite'] : null;
    $statut        = $data['statut'] ?? 'brouillon';
    $items         = $data['items'] ?? [];
    $id            = (int)($data['id'] ?? 0);

    // Enforce plan devis limit on new devis only
    if ($id === 0) {
        $limits = getPlanLimits($user['plan'] ?? 'gratuit');
        if ($limits['devis'] < 999999) {
            $stmtDC = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND MONTH(date_devis)=MONTH(CURDATE()) AND YEAR(date_devis)=YEAR(CURDATE())");
            $stmtDC->execute([$user['id']]);
            if ((int)$stmtDC->fetchColumn() >= $limits['devis']) {
                echo json_encode(['success' => false, 'error' => 'Limite de devis atteinte. Passez au plan Pro pour continuer.', 'limit' => true]);
                return;
            }
        }
    }

    $sousTotal = 0; $totalTVA = 0;
    foreach ($items as $item) {
        $qte  = (float)($item['quantite'] ?? 1);
        $pu   = (float)($item['prix_unitaire'] ?? 0);
        $tva  = (float)($item['tva'] ?? 20);
        $sub  = $qte * $pu;
        $sousTotal += $sub;
        $totalTVA  += $sub * $tva / 100;
    }
    $total = round($sousTotal + $totalTVA, 2);

    try {
        $pdo->beginTransaction();
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE devis SET client_id=?,client_nom=?,client_email=?,numero=numero,date_devis=?,date_validite=?,statut=?,sous_total=?,tva_montant=?,total=?,devise=?,notes=? WHERE id=? AND user_id=?");
            $stmt->execute([$clientId,$clientNom,$clientEmail,$dateDevis,$dateValidite,$statut,round($sousTotal,2),round($totalTVA,2),$total,$devise,$notes,$id,$user['id']]);
            $pdo->prepare("DELETE FROM devis_items WHERE devis_id=?")->execute([$id]);
            $devisId = $id;
        } else {
            $numero = genererNumeroDevis($user['id']);
            $stmt = $pdo->prepare("INSERT INTO devis (user_id,client_id,client_nom,client_email,numero,date_devis,date_validite,statut,sous_total,tva_montant,total,devise,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$user['id'],$clientId,$clientNom,$clientEmail,$numero,$dateDevis,$dateValidite,$statut,round($sousTotal,2),round($totalTVA,2),$total,$devise,$notes]);
            $devisId = $pdo->lastInsertId();
            $numero  = $numero;
        }

        $stmtItem = $pdo->prepare("INSERT INTO devis_items (devis_id,description,quantite,prix_unitaire,tva,total) VALUES (?,?,?,?,?,?)");
        foreach ($items as $item) {
            $qte  = (float)($item['quantite'] ?? 1);
            $pu   = (float)($item['prix_unitaire'] ?? 0);
            $tva  = (float)($item['tva'] ?? 20);
            $stmtItem->execute([$devisId, trim($item['description']??''), $qte, $pu, $tva, round($qte*$pu,2)]);
        }
        $pdo->commit();

        $stmtN = $pdo->prepare("SELECT numero FROM devis WHERE id=?");
        $stmtN->execute([$devisId]);
        $finalNumero = $stmtN->fetchColumn();
        logAction($user['id'], $id > 0 ? 'update' : 'create', 'devis', (int)$devisId, (string)$finalNumero, 'Client : ' . $clientNom);
        echo json_encode(['success'=>true,'devis_id'=>$devisId,'numero'=>$finalNumero]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'error'=>'Erreur sauvegarde: '.$e->getMessage()]);
    }
}

function updateStatut(array $user, array $data): void {
    global $pdo;
    $id     = (int)($data['id'] ?? 0);
    $statut = $data['statut'] ?? '';
    $allowed = ['brouillon','envoye','accepte','refuse','expire'];
    if (!in_array($statut, $allowed, true)) { echo json_encode(['success'=>false,'error'=>'Statut invalide']); return; }
    $pdo->prepare("UPDATE devis SET statut=? WHERE id=? AND user_id=?")->execute([$statut,$id,$user['id']]);
    $ref = $pdo->prepare("SELECT numero FROM devis WHERE id=?"); $ref->execute([$id]);
    logAction($user['id'], 'status_change', 'devis', $id, (string)$ref->fetchColumn(), 'Statut : ' . $statut);
    echo json_encode(['success'=>true]);
}

function deleteDevis(array $user, array $data): void {
    global $pdo;
    $id = (int)($data['id'] ?? 0);
    $ref = $pdo->prepare("SELECT numero FROM devis WHERE id=? AND user_id=?"); $ref->execute([$id,$user['id']]);
    $num = $ref->fetchColumn();
    $pdo->prepare("DELETE FROM devis WHERE id=? AND user_id=?")->execute([$id,$user['id']]);
    if ($num) logAction($user['id'], 'delete', 'devis', $id, (string)$num);
    echo json_encode(['success'=>true]);
}

function convertToFacture(array $user, array $data): void {
    global $pdo;
    if (!canCreateFacture($user)) {
        echo json_encode(['success'=>false,'error'=>'Limite de factures atteinte. Passez au plan Pro.']);
        return;
    }
    $devisId = (int)($data['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM devis WHERE id=? AND user_id=?");
    $stmt->execute([$devisId,$user['id']]);
    $devis = $stmt->fetch();
    if (!$devis) { echo json_encode(['success'=>false,'error'=>'Devis introuvable']); return; }

    $stmtItems = $pdo->prepare("SELECT * FROM devis_items WHERE devis_id=?");
    $stmtItems->execute([$devisId]);
    $items = $stmtItems->fetchAll();

    try {
        $pdo->beginTransaction();
        $numero = genererNumeroFacture($user['id']);
        $stmt2  = $pdo->prepare("INSERT INTO factures (user_id,client_id,client_nom,client_email,numero,date_facture,statut,sous_total,tva_montant,total,devise,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt2->execute([$user['id'],$devis['client_id'],$devis['client_nom'],$devis['client_email'],$numero,date('Y-m-d'),'brouillon',$devis['sous_total'],$devis['tva_montant'],$devis['total'],$devis['devise'],$devis['notes']]);
        $factureId = $pdo->lastInsertId();

        $stmtFI = $pdo->prepare("INSERT INTO facture_items (facture_id,description,quantite,prix_unitaire,tva,total) VALUES (?,?,?,?,?,?)");
        foreach ($items as $item) {
            $stmtFI->execute([$factureId,$item['description'],$item['quantite'],$item['prix_unitaire'],$item['tva'],$item['total']]);
        }
        // Marquer le devis comme accepté
        $pdo->prepare("UPDATE devis SET statut='accepte' WHERE id=?")->execute([$devisId]);
        $pdo->prepare("UPDATE users SET factures_count=factures_count+1 WHERE id=?")->execute([$user['id']]);
        $pdo->commit();
        echo json_encode(['success'=>true,'facture_id'=>$factureId,'numero'=>$numero]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success'=>false,'error'=>'Erreur conversion: '.$e->getMessage()]);
    }
}
