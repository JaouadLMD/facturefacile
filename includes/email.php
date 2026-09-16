<?php
/**
 * Envoi d'email via SMTP SSL sans dépendance externe
 * Compatible Gmail (ssl://smtp.gmail.com:465)
 */
class SMTPMailer
{
    private $socket;
    private array $cfg;

    public function __construct(array $cfg) {
        $this->cfg = array_merge([
            'host'      => 'smtp.gmail.com',
            'port'      => 465,
            'username'  => '',
            'password'  => '',
            'from_name' => 'FactureFacile',
            'ssl'       => true,
        ], $cfg);
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody): bool {
        try {
            $host   = ($this->cfg['ssl'] ? 'ssl://' : '') . $this->cfg['host'];
            $this->socket = fsockopen($host, $this->cfg['port'], $errno, $errstr, 20);
            if (!$this->socket) return false;

            $this->read();
            $this->cmd("EHLO localhost");
            $this->cmd("AUTH LOGIN");
            $this->cmd(base64_encode($this->cfg['username']));
            $this->cmd(base64_encode($this->cfg['password']));
            $this->cmd("MAIL FROM:<{$this->cfg['username']}>");
            $this->cmd("RCPT TO:<{$to}>");
            $this->cmd("DATA");

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $body  = "From: {$this->cfg['from_name']} <{$this->cfg['username']}>\r\n";
            $body .= "To: {$toName} <{$to}>\r\n";
            $body .= "Subject: {$encodedSubject}\r\n";
            $body .= "MIME-Version: 1.0\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "\r\n" . $htmlBody . "\r\n.";
            $this->cmd($body);
            $this->cmd("QUIT");
            fclose($this->socket);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function cmd(string $data): string {
        fputs($this->socket, $data . "\r\n");
        return $this->read();
    }

    private function read(): string {
        $res = '';
        while ($line = fgets($this->socket, 515)) {
            $res .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $res;
    }
}

function sendFactureEmail(array $user, array $facture, array $items, string $type = 'facture'): array {
    $smtpUser = !empty($user['smtp_user']) ? $user['smtp_user'] : SMTP_USER;
    $smtpPass = !empty($user['smtp_pass']) ? $user['smtp_pass'] : SMTP_PASS;
    $smtpHost = !empty($user['smtp_host']) ? $user['smtp_host'] : SMTP_HOST;
    $smtpPort = !empty($user['smtp_port']) ? (int)$user['smtp_port'] : SMTP_PORT;

    if (empty($smtpUser) || empty($smtpPass)) {
        return ['success' => false, 'error' => 'SMTP non configuré. Ajoutez votre email dans les Paramètres.'];
    }

    $label    = $type === 'devis' ? 'Devis' : 'Facture';
    $clientTo = $facture['client_email'] ?? '';
    $clientNom= $facture['client_nom']   ?? 'Client';
    if (empty($clientTo)) {
        return ['success' => false, 'error' => 'Pas d\'email client pour cet enregistrement.'];
    }

    $entreprise = $user['entreprise'] ?: $user['nom'];
    $lignes = '';
    foreach ($items as $item) {
        $lignes .= "<tr>
            <td style='padding:8px;border-bottom:1px solid #f1f5f9;'>{$item['description']}</td>
            <td style='padding:8px;border-bottom:1px solid #f1f5f9;text-align:center;'>{$item['quantite']}</td>
            <td style='padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;'>".number_format($item['prix_unitaire'],2,',',' ')." {$facture['devise']}</td>
            <td style='padding:8px;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600;'>".number_format($item['total'],2,',',' ')." {$facture['devise']}</td>
        </tr>";
    }

    $total = number_format($facture['total'], 2, ',', ' ') . ' ' . $facture['devise'];

    $html = "<!DOCTYPE html><html lang='fr'><head><meta charset='UTF-8'></head>
    <body style='font-family:Inter,Arial,sans-serif;background:#f8fafc;margin:0;padding:20px;'>
    <div style='max-width:600px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
        <div style='background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:32px;text-align:center;'>
            <h1 style='color:white;margin:0;font-size:24px;font-weight:800;'>FactureFacile</h1>
            <p style='color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:14px;'>{$label} N° {$facture['numero']}</p>
        </div>
        <div style='padding:32px;'>
            <p style='color:#374151;font-size:15px;'>Bonjour <strong>{$clientNom}</strong>,</p>
            <p style='color:#6b7280;font-size:14px;'>Veuillez trouver ci-joint votre {$label} de la part de <strong>{$entreprise}</strong>.</p>
            <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                <thead><tr style='background:#f8fafc;'>
                    <th style='padding:10px 8px;text-align:left;font-size:12px;color:#6b7280;text-transform:uppercase;'>Description</th>
                    <th style='padding:10px 8px;text-align:center;font-size:12px;color:#6b7280;'>Qté</th>
                    <th style='padding:10px 8px;text-align:right;font-size:12px;color:#6b7280;'>P.U.</th>
                    <th style='padding:10px 8px;text-align:right;font-size:12px;color:#6b7280;'>Total HT</th>
                </tr></thead>
                <tbody>{$lignes}</tbody>
            </table>
            <div style='text-align:right;margin:16px 0;'>
                <span style='font-size:20px;font-weight:800;color:#6366f1;'>Total TTC : {$total}</span>
            </div>
            <hr style='border:none;border-top:1px solid #f1f5f9;margin:24px 0;'>
            <p style='color:#6b7280;font-size:12px;text-align:center;'>Facturé par <strong>{$entreprise}</strong>
            " . ($user['telephone'] ? "· {$user['telephone']}" : '') . "
            " . ($user['email']     ? "· {$user['email']}"     : '') . "</p>
        </div>
    </div></body></html>";

    $mailer = new SMTPMailer([
        'host'      => $smtpHost,
        'port'      => $smtpPort,
        'username'  => $smtpUser,
        'password'  => $smtpPass,
        'from_name' => $entreprise,
    ]);

    $subject = "{$label} N° {$facture['numero']} – {$entreprise}";
    $ok = $mailer->send($clientTo, $clientNom, $subject, $html);
    return ['success' => $ok, 'error' => $ok ? '' : 'Échec envoi SMTP. Vérifiez votre App Password Gmail.'];
}
