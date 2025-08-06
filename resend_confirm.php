<?php
// resend_confirm.php
require_once 'includes/db.php';
require_once 'includes/mail.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '❌ Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, confirmed FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $message = '❌ No user found with that email address.';
        } elseif ((int)$user['confirmed'] === 1) {
            $message = '✅ This account is already confirmed.';
        } else {
            // Generate a new token
            $token = bin2hex(random_bytes(16));
            $upd = $pdo->prepare("UPDATE users SET confirm_token = ? WHERE id = ?");
            $upd->execute([$token, $user['id']]);

            // Send confirmation email
            $subject = "Confirm Your Account";
            $link = "https://docs.defecttracker.uk/confirm.php?token=" . urlencode($token);
            $body = "
                <p>Hello <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>
                <p>Please confirm your account by clicking the link below:</p>
                <p><a href='$link'>$link</a></p>
            ";

            $result = send_mail($email, $subject, $body);

            if ($result['ok']) {
                $message = '✅ Confirmation email sent successfully.';
            } else {
                $message = '❌ Failed to send confirmation email. ' . htmlspecialchars($result['error']);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Resend Confirmation Email</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #f0f4f8;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            background: #fff;
            padding: 2em;
            border-radius: 10px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 1em;
            text-align: center;
        }
        input[type="email"] {
            width: 100%;
            padding: 0.75em;
            margin-bottom: 1em;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            width: 100%;
            background: #007bff;
            color: #fff;
            padding: 0.75em;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .msg {
            text-align: center;
            margin-top: 1em;
            color: #333;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Resend Confirmation</h1>
    <form method="POST">
        <input type="email" name="email" placeholder="Enter your email..." required>
        <button type="submit">Resend Email</button>
    </form>
    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>
</div>
</body>
</html>
