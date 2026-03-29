<?php
// Telegram Bot Token
$bot_token = '8691749735:AAEjY95anTeR0v6a4vB9t4HHqajWgrQrElo';
$admin_id = 7134813314;

// SMS API Configuration
$sms_api_user = '212313';
$sms_api_key = 'b564b0ffd61fb5ee89a02dae5fe01cae';

// Database file
$db_file = __DIR__ . '/users.db';

// Create database connection
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        telegram_id TEXT UNIQUE,
        phone_number TEXT,
        is_verified INTEGER DEFAULT 0,
        otp_code TEXT,
        otp_expires DATETIME,
        joined_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        verified_date DATETIME,
        is_banned INTEGER DEFAULT 0
    )");
    
    // Create groups table
    $pdo->exec("CREATE TABLE IF NOT EXISTS groups (
        group_id TEXT PRIMARY KEY,
        verification_enabled INTEGER DEFAULT 1
    )");
    
} catch(PDOException $e) {
    error_log("DB Error: " . $e->getMessage());
}

// Function to send SMS
function send_sms($phone, $message, $user, $key) {
    $url = "https://sendmysms.net/api.php?user=".$user."&key=".$key."&to=".$phone."&msg=".urlencode($message);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if($error) {
        error_log("SMS Error: " . $error);
        return false;
    }
    
    $result = json_decode($response);
    return isset($result->status) && $result->status == 'OK';
}

// Function to send Telegram message
function send_telegram($chat_id, $text, $token, $keyboard = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Function to delete message
function delete_message($chat_id, $message_id, $token) {
    $url = "https://api.telegram.org/bot{$token}/deleteMessage";
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

// Get incoming update
$update = json_decode(file_get_contents('php://input'), true);

if(!$update) {
    // Auto ban unverified users after 2 days
    $stmt = $pdo->prepare("UPDATE users SET is_banned = 1 
                           WHERE is_verified = 0 
                           AND is_banned = 0 
                           AND datetime(joined_date, '+2 days') < datetime('now')");
    $stmt->execute();
    exit;
}

// Handle message
if(isset($update['message'])) {
    $msg = $update['message'];
    $chat_id = $msg['chat']['id'];
    $user_id = $msg['from']['id'];
    $text = isset($msg['text']) ? $msg['text'] : '';
    $is_group = in_array($msg['chat']['type'], ['group', 'supergroup']);
    
    // Get or create user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        $stmt = $pdo->prepare("INSERT INTO users (telegram_id) VALUES (?)");
        $stmt->execute([$user_id]);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Group message handling
    if($is_group && $user['is_verified'] == 0 && $user_id != $admin_id) {
        // Delete message
        delete_message($chat_id, $msg['message_id'], $bot_token);
        
        // Send verification warning
        $warn = "⚠️ <b>Verification Required!</b>\n\n";
        $warn .= "You must verify your phone number to send messages.\n";
        $warn .= "Please use /verify command in private chat with me.";
        
        send_telegram($user_id, $warn, $bot_token);
        exit;
    }
    
    // Private chat commands
    if(!$is_group) {
        // Start command
        if($text == '/start') {
            $welcome = "🤖 <b>Welcome to Verification Bot</b>\n\n";
            $welcome .= "This bot verifies users via SMS.\n\n";
            $welcome .= "<b>Commands:</b>\n";
            $welcome .= "/verify - Start verification\n";
            $welcome .= "/status - Check status\n";
            
            if($user_id == $admin_id) {
                $welcome .= "\n<b>Admin Commands:</b>\n";
                $welcome .= "/allusers - List all users\n";
                $welcome .= "/verifygroup - Enable verification in group\n";
                $welcome .= "/unverifygroup - Disable verification\n";
            }
            
            send_telegram($chat_id, $welcome, $bot_token);
        }
        
        // Verify command
        elseif($text == '/verify') {
            if($user['is_verified'] == 1) {
                send_telegram($chat_id, "✅ You are already verified!", $bot_token);
            } else {
                $keyboard = [
                    'keyboard' => [
                        [['text' => '📱 Share Phone Number', 'request_contact' => true]]
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true
                ];
                send_telegram($chat_id, "📞 Please share your phone number:", $bot_token, $keyboard);
            }
        }
        
        // Status command
        elseif($text == '/status') {
            $status = "📊 <b>Your Status</b>\n\n";
            $status .= "Verification: " . ($user['is_verified'] ? "✅ Verified" : "❌ Not Verified") . "\n";
            $status .= "Phone: " . ($user['phone_number'] ? $user['phone_number'] : "Not provided") . "\n";
            $status .= "Joined: " . $user['joined_date'] . "\n";
            if($user['is_verified']) {
                $status .= "Verified: " . $user['verified_date'];
            }
            send_telegram($chat_id, $status, $bot_token);
        }
        
        // All users command (admin only)
        elseif($text == '/allusers' && $user_id == $admin_id) {
            $stmt = $pdo->query("SELECT telegram_id, phone_number, is_verified, joined_date FROM users ORDER BY joined_date DESC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total = count($users);
            $verified = $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn();
            
            $response = "📊 <b>User Statistics</b>\n\n";
            $response .= "Total: $total\n";
            $response .= "Verified: $verified\n";
            $response .= "Unverified: " . ($total - $verified) . "\n\n";
            $response .= "<b>Recent Users:</b>\n";
            
            $count = 0;
            foreach($users as $u) {
                if($count++ >= 10) break;
                $response .= "• {$u['telegram_id']} | ";
                $response .= ($u['phone_number'] ? $u['phone_number'] : 'N/A') . " | ";
                $response .= ($u['is_verified'] ? '✓' : '✗') . "\n";
            }
            
            send_telegram($chat_id, $response, $bot_token);
        }
        
        // Handle contact sharing
        elseif(isset($msg['contact'])) {
            $phone = $msg['contact']['phone_number'];
            
            // Validate BD number
            if(!preg_match("/^01[3-9][0-9]{8}$/", $phone)) {
                send_telegram($chat_id, "❌ Invalid Bangladeshi number!\nUse /verify to try again.", $bot_token);
            } else {
                $otp = rand(100000, 999999);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                $stmt = $pdo->prepare("UPDATE users SET phone_number = ?, otp_code = ?, otp_expires = ? WHERE telegram_id = ?");
                $stmt->execute([$phone, $otp, $expires, $user_id]);
                
                if(send_sms($phone, "Your verification code is: $otp", $sms_api_user, $sms_api_key)) {
                    $keyboard = [
                        'keyboard' => [
                            [['text' => 'Enter OTP']]
                        ],
                        'resize_keyboard' => true
                    ];
                    send_telegram($chat_id, "✅ OTP sent to $phone\n\nEnter the 6-digit code:", $bot_token, $keyboard);
                    $_SESSION['otp_' . $user_id] = $otp;
                } else {
                    send_telegram($chat_id, "❌ Failed to send SMS!\nDebug Code: $otp", $bot_token);
                }
            }
        }
        
        // Handle OTP input
        elseif(preg_match('/^\d{6}$/', $text)) {
            if(isset($_SESSION['otp_' . $user_id]) && $_SESSION['otp_' . $user_id] == $text) {
                $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verified_date = CURRENT_TIMESTAMP, otp_code = NULL WHERE telegram_id = ?");
                $stmt->execute([$user_id]);
                
                send_telegram($chat_id, "✅ <b>Verification Successful!</b>\n\nYou can now send messages in all groups.", $bot_token);
                unset($_SESSION['otp_' . $user_id]);
            } else {
                send_telegram($chat_id, "❌ Invalid OTP!\nUse /verify to try again.", $bot_token);
            }
        }
    }
    
    // Group admin commands
    if($is_group && $user_id == $admin_id) {
        if($text == '/verifygroup') {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO groups (group_id, verification_enabled) VALUES (?, 1)");
            $stmt->execute([$chat_id]);
            send_telegram($chat_id, "✅ Verification enabled for this group!", $bot_token);
        }
        
        elseif($text == '/unverifygroup') {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO groups (group_id, verification_enabled) VALUES (?, 0)");
            $stmt->execute([$chat_id]);
            send_telegram($chat_id, "❌ Verification disabled for this group!", $bot_token);
        }
    }
}
?>
