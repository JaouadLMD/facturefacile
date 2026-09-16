<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) { header('Location: '.APP_URL.'/index.php'); exit; }

$user = getCurrentUser();
$type = $_GET['type'] ?? 'factures';
$date = date('Y-m-d');

function csvRow(array $cols): string {
    return implode(';', array_map(fn($c) => '"'.str_replace('"','""',(string)$c).'"', $cols))."\r\n";
}

switch ($type) {
    case 'factures':
        $stmt = $pdo->prepare("
            SELECT f.numero, f.client_nom, f.client_email, f.date_facture, f.date_echeance,
                   f.sous_total, f.tva_montant, f.total, f.statut, f.devise, f.notes,
                   f.created_at
            FROM factures f WHERE f.user_id=? ORDER BY f.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="factures_'.$date.'.csv"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        echo csvRow(['Numéro','Client','Email','Date','Échéance','Sous-total HT','TVA','Total TTC','Statut','Devise','Notes','Créée le']);
        foreach ($rows as $r) {
            echo csvRow([$r['numero'],$r['client_nom'],$r['client_email'],
                $r['date_facture'],$r['date_echeance']??'',
                number_format($r['sous_total'],2,',',''),
                number_format($r['tva_montant'],2,',',''),
                number_format($r['total'],2,',',''),
                $r['statut'],$r['devise'],$r['notes'],$r['created_at']]);
        }
        logAction($user['id'],'export','facture',0,'factures_'.$date.'.csv',count($rows).' lignes');
        break;

    case 'devis':
        $stmt = $pdo->prepare("
            SELECT d.numero, d.client_nom, d.client_email, d.date_devis, d.date_validite,
                   d.sous_total, d.tva_montant, d.total, d.statut, d.devise, d.notes, d.created_at
            FROM devis d WHERE d.user_id=? ORDER BY d.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="devis_'.$date.'.csv"');
        echo "\xEF\xBB\xBF";
        echo csvRow(['Numéro','Client','Email','Date','Validité','Sous-total HT','TVA','Total TTC','Statut','Devise','Notes','Créé le']);
        foreach ($rows as $r) {
            echo csvRow([$r['numero'],$r['client_nom'],$r['client_email'],
                $r['date_devis'],$r['date_validite']??'',
                number_format($r['sous_total'],2,',',''),
                number_format($r['tva_montant'],2,',',''),
                number_format($r['total'],2,',',''),
                $r['statut'],$r['devise'],$r['notes'],$r['created_at']]);
        }
        logAction($user['id'],'export','devis',0,'devis_'.$date.'.csv',count($rows).' lignes');
        break;

    case 'clients':
        $stmt = $pdo->prepare("
            SELECT c.nom, c.email, c.telephone, c.ville, c.adresse, c.ice,
                   COUNT(f.id) as nb_factures, COALESCE(SUM(f.total),0) as ca_total
            FROM clients c LEFT JOIN factures f ON c.id=f.client_id
            WHERE c.user_id=? GROUP BY c.id ORDER BY c.nom
        ");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clients_'.$date.'.csv"');
        echo "\xEF\xBB\xBF";
        echo csvRow(['Nom','Email','Téléphone','Ville','Adresse','ICE','Nb factures','CA total']);
        foreach ($rows as $r) {
            echo csvRow([$r['nom'],$r['email'],$r['telephone'],$r['ville'],
                $r['adresse'],$r['ice'],$r['nb_factures'],
                number_format($r['ca_total'],2,',','')]);
        }
        logAction($user['id'],'export','client',0,'clients_'.$date.'.csv',count($rows).' lignes');
        break;

    case 'historique':
        $stmt = $pdo->prepare("
            SELECT l.created_at, l.action, l.module, l.entity_ref, l.details, l.ip, u.nom as user_nom
            FROM audit_logs l LEFT JOIN users u ON l.user_id=u.id
            WHERE l.user_id=? ORDER BY l.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="historique_'.$date.'.csv"');
        echo "\xEF\xBB\xBF";
        echo csvRow(['Date','Heure','Utilisateur','Action','Module','Référence','Détails','IP']);
        foreach ($rows as $r) {
            $dt = explode(' ', $r['created_at']);
            echo csvRow([date('d/m/Y',strtotime($r['created_at'])),
                date('H:i:s',strtotime($r['created_at'])),
                $r['user_nom'],$r['action'],$r['module'],
                $r['entity_ref'],$r['details'],$r['ip']]);
        }
        break;

    default:
        http_response_code(400);
        echo 'Type inconnu';
}
