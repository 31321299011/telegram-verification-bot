<?php
$bot_token = '8691749735:AAEjY95anTeR0v6a4vB9t4HHqajWgrQrElo';

// Get current domain from server
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $_SERVER['HTTP_HOST'];
$webhook_url = $protocol . "://" . $domain . "/bot.php";

// Set webhook
$url = "https://api.telegram.org/bot{$bot_token}/setWebhook";
$data = ['url' => $webhook_url];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($ch);
curl_close($ch);

echo "<pre>";
echo "Webhook Set Response:\n";
print_r(json_decode($result, true));
echo "\n\nWebhook URL: " . $webhook_url;
echo "</pre>";
?>
