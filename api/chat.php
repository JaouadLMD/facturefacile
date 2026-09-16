<?php
// Bot-thread chat API (admin ↔ AI assistant only).
// All group/DM conversations are handled by api/chat_groups.php.
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

    case 'list':
        $since = (int)($_GET['since'] ?? 0);
        // Bot thread: group_id IS NULL AND thread_member_id IS NULL
        $stmt = $pdo->prepare("
            SELECT id, sender_id, sender_nom, sender_type, message,
                   file_name, file_path, file_type, file_size, is_read,
                   DATE_FORMAT(created_at,'%H:%i') as heure,
                   DATE_FORMAT(created_at,'%d/%m/%Y %H:%i') as datetime_full
            FROM chat_messages
            WHERE admin_id=? AND thread_member_id IS NULL AND (group_id IS NULL OR group_id=0) AND id>?
            ORDER BY id DESC LIMIT 80
        ");
        $stmt->execute([$user['id'], $since]);
        $msgs = array_reverse($stmt->fetchAll());

        // Mark bot replies as read
        $pdo->prepare("UPDATE chat_messages SET is_read=1
                       WHERE admin_id=? AND thread_member_id IS NULL AND (group_id IS NULL OR group_id=0) AND sender_type='bot' AND is_read=0")
            ->execute([$user['id']]);

        echo json_encode(['success'=>true,'messages'=>$msgs]);
        break;

    case 'send':
        if (isTeamMember()) { echo json_encode(['success'=>false,'error'=>'Non autorisé']); break; }
        $message = trim($input['message'] ?? '');
        if (empty($message)) { echo json_encode(['success'=>false,'error'=>'Message vide']); break; }

        $pdo->prepare("INSERT INTO chat_messages (admin_id, sender_id, sender_nom, sender_type, thread_member_id, group_id, message) VALUES (?,?,?,'user',NULL,NULL,?)")
            ->execute([$user['id'], $user['id'], $user['nom'], $message]);

        $botReply = getBotReply($message, $user);
        if ($botReply) {
            $pdo->prepare("INSERT INTO chat_messages (admin_id, sender_id, sender_nom, sender_type, thread_member_id, group_id, message, is_read) VALUES (?,?,?,'bot',NULL,NULL,?,1)")
                ->execute([$user['id'], $user['id'], 'FactureFacile Bot', $botReply]);
        }
        echo json_encode(['success'=>true]);
        break;

    case 'count':
        // Unread bot messages
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE admin_id=? AND thread_member_id IS NULL AND (group_id IS NULL OR group_id=0) AND sender_type='bot' AND is_read=0");
        $stmt->execute([$user['id']]);
        echo json_encode(['success'=>true,'count'=>(int)$stmt->fetchColumn()]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Action inconnue']);
}

function getBotReply(string $message, array $user): string {
    global $pdo;
    $msg = mb_strtolower($message);

    if (str_contains($msg,'impay') || str_contains($msg,'retard')) {
        $stmt = $pdo->prepare("SELECT COUNT(*),COALESCE(SUM(total),0) FROM factures WHERE user_id=? AND statut='impayee'");
        $stmt->execute([$user['id']]);
        [$nb,$total] = $stmt->fetch(\PDO::FETCH_NUM);
        if ($nb > 0) return "⚠️ Vous avez **{$nb}** facture(s) impayée(s) pour un total de **".number_format($total,2,',',' ')." {$user['devise']}**. Pensez à envoyer des relances !";
        return "✅ Bonne nouvelle ! Aucune facture impayée pour le moment.";
    }
    if (str_contains($msg,'chiffre') || str_contains($msg,'ca ') || str_contains($msg,"c'a") || str_contains($msg,'revenu')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM factures WHERE user_id=? AND statut='payee' AND YEAR(date_facture)=YEAR(CURDATE())");
        $stmt->execute([$user['id']]);
        return "📊 Votre CA encaissé cette année : **".number_format($stmt->fetchColumn(),2,',',' ')." {$user['devise']}**.";
    }
    if (str_contains($msg,'client')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE user_id=?");
        $stmt->execute([$user['id']]);
        return "👥 Vous avez **".$stmt->fetchColumn()."** client(s) enregistré(s).";
    }
    if (str_contains($msg,'devis')) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM devis WHERE user_id=? AND statut NOT IN('refuse','expire')");
        $stmt->execute([$user['id']]);
        return "📋 Vous avez **".$stmt->fetchColumn()."** devis actif(s).";
    }
    if (str_contains($msg,'facture') && (str_contains($msg,'total') || str_contains($msg,'nombre'))) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM factures WHERE user_id=?");
        $stmt->execute([$user['id']]);
        return "📄 Vous avez créé **".$stmt->fetchColumn()."** facture(s) au total.";
    }
    if (str_contains($msg,'bonjour') || str_contains($msg,'salut') || str_contains($msg,'hello')) {
        return "👋 Bonjour ".explode(' ',$user['nom'])[0]." ! Comment puis-je vous aider ? Posez-moi des questions sur vos factures, clients ou devis.";
    }
    if (str_contains($msg,'merci')) return "😊 Avec plaisir ! N'hésitez pas si vous avez d'autres questions.";
    if (str_contains($msg,'aide') || str_contains($msg,'help')) {
        return "🤖 Je peux vous renseigner sur :\n• Vos factures impayées\n• Votre chiffre d'affaires\n• Vos clients et devis\n\nExemple : *\"Combien de factures impayées j'ai ?\"*";
    }
    return '';
}
