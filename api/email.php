<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

header('Content-Type: application/json; charset=utf-8');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'error'=>'Non authentifié']); exit; }

$user   = getCurrentUser();
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'send_facture':
        $factureId = (int)($input['facture_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM factures WHERE id=? AND user_id=?");
        $stmt->execute([$factureId,$user['id']]);
        $facture = $stmt->fetch();
        if (!$facture) { echo json_encode(['success'=>false,'error'=>'Facture introuvable']); break; }

        $stmt2 = $pdo->prepare("SELECT * FROM facture_items WHERE facture_id=?");
        $stmt2->execute([$factureId]);
        $items = $stmt2->fetchAll();

        $result = sendFactureEmail($user, $facture, $items, 'facture');

        // Log
        $pdo->prepare("INSERT INTO email_logs (user_id,facture_id,recipient_email,recipient_nom,subject,status,error_msg) VALUES (?,?,?,?,?,?,?)")
            ->execute([$user['id'],$factureId,$facture['client_email'],$facture['client_nom'],'Facture '.$facture['numero'],$result['success']?'sent':'failed',$result['error']??null]);

        if ($result['success'] && $facture['statut']==='brouillon') {
            $pdo->prepare("UPDATE factures SET statut='envoyee' WHERE id=?")->execute([$factureId]);
        }
        echo json_encode($result);
        break;

    case 'send_devis':
        $devisId = (int)($input['devis_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM devis WHERE id=? AND user_id=?");
        $stmt->execute([$devisId,$user['id']]);
        $devis = $stmt->fetch();
        if (!$devis) { echo json_encode(['success'=>false,'error'=>'Devis introuvable']); break; }

        $stmt2 = $pdo->prepare("SELECT * FROM devis_items WHERE devis_id=?");
        $stmt2->execute([$devisId]);
        $items = $stmt2->fetchAll();

        $result = sendFactureEmail($user, $devis, $items, 'devis');
        if ($result['success'] && $devis['statut']==='brouillon') {
            $pdo->prepare("UPDATE devis SET statut='envoye' WHERE id=?")->execute([$devisId]);
        }
        echo json_encode($result);
        break;

    case 'relance':
        $factureId = (int)($input['facture_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM factures WHERE id=? AND user_id=? AND statut='impayee'");
        $stmt->execute([$factureId,$user['id']]);
        $facture = $stmt->fetch();
        if (!$facture) { echo json_encode(['success'=>false,'error'=>'Facture impayée introuvable']); break; }
        if (empty($facture['client_email'])) { echo json_encode(['success'=>false,'error'=>'Pas d\'email client']); break; }

        // Numéro de relance
        $stmtR = $pdo->prepare("SELECT COUNT(*)+1 FROM relances WHERE facture_id=?");
        $stmtR->execute([$factureId]);
        $numRelance = (int)$stmtR->fetchColumn();
        $labels = [1=>'1ère',2=>'2ème',3=>'3ème'];
        $label  = ($labels[$numRelance] ?? $numRelance.'ème');

        $entreprise = $user['entreprise'] ?: $user['nom'];
        $montant    = number_format($facture['total'],2,',',' ').' '.$facture['devise'];
        $echeance   = $facture['date_echeance'] ? date('d/m/Y',strtotime($facture['date_echeance'])) : 'N/A';

        $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
        <body style='font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:20px;'>
        <div style='max-width:580px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
            <div style='background:linear-gradient(135deg,#DC2626,#9B1C1C);padding:28px;text-align:center;'>
                <h1 style='color:white;margin:0;font-size:22px;font-weight:800;'>Rappel de paiement</h1>
                <p style='color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:13px;'>{$label} relance – Facture {$facture['numero']}</p>
            </div>
            <div style='padding:28px;'>
                <p style='font-size:15px;color:#374151;'>Bonjour <strong>{$facture['client_nom']}</strong>,</p>
                <p style='font-size:14px;color:#6B7280;'>Nous vous contactons pour vous rappeler que la facture ci-dessous est toujours en attente de règlement.</p>
                <div style='background:#FEF2F2;border:1px solid #FECACA;border-radius:12px;padding:16px;margin:20px 0;'>
                    <div style='display:flex;justify-content:space-between;margin-bottom:8px;'><span style='color:#6B7280;font-size:13px;'>Facture N°</span><span style='font-weight:700;color:#111827;'>{$facture['numero']}</span></div>
                    <div style='display:flex;justify-content:space-between;margin-bottom:8px;'><span style='color:#6B7280;font-size:13px;'>Échéance</span><span style='font-weight:700;color:#DC2626;'>{$echeance}</span></div>
                    <div style='display:flex;justify-content:space-between;'><span style='color:#6B7280;font-size:13px;'>Montant dû</span><span style='font-weight:800;color:#DC2626;font-size:18px;'>{$montant}</span></div>
                </div>
                <p style='font-size:13px;color:#6B7280;'>Merci de procéder au règlement dans les meilleurs délais. Pour toute question, contactez-nous.</p>
                <p style='font-size:14px;color:#374151;margin-top:20px;'>Cordialement,<br><strong>{$entreprise}</strong></p>
            </div>
        </div></body></html>";

        $smtpUser = !empty($user['smtp_user']) ? $user['smtp_user'] : SMTP_USER;
        $smtpPass = !empty($user['smtp_pass']) ? $user['smtp_pass'] : SMTP_PASS;
        $smtpHost = !empty($user['smtp_host']) ? $user['smtp_host'] : SMTP_HOST;
        $smtpPort = !empty($user['smtp_port']) ? (int)$user['smtp_port'] : SMTP_PORT;

        if (empty($smtpUser)||empty($smtpPass)) { echo json_encode(['success'=>false,'error'=>'SMTP non configuré dans les Paramètres.']); break; }

        $mailer = new SMTPMailer(['host'=>$smtpHost,'port'=>$smtpPort,'username'=>$smtpUser,'password'=>$smtpPass,'from_name'=>$entreprise]);
        $ok = $mailer->send($facture['client_email'], $facture['client_nom'], "{$label} relance – Facture {$facture['numero']} – {$entreprise}", $html);

        if ($ok) {
            $pdo->prepare("INSERT INTO relances (facture_id,user_id,numero_relance,email_envoye) VALUES (?,?,?,?)")
                ->execute([$factureId,$user['id'],$numRelance,$facture['client_email']]);
            $pdo->prepare("INSERT INTO email_logs (user_id,facture_id,recipient_email,recipient_nom,subject,status) VALUES (?,?,?,?,?,?)")
                ->execute([$user['id'],$factureId,$facture['client_email'],$facture['client_nom'],"Relance {$label}",'sent']);
        }
        echo json_encode(['success'=>$ok,'error'=>$ok?'':'Échec SMTP. Vérifiez votre App Password Gmail.','numero'=>$numRelance]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}
