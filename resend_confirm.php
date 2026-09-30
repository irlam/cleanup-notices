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

<?php require_once __DIR__ . '/includes/header.php'; ?>
<main class="docs-account-page">
<div class="container">
    <h1>Resend Confirmation</h1>
    <form method="POST">
        <label for="email">Email address</label><input id="email" type="email" name="email" placeholder="Enter your email..." required>
        <button class="btn-primary" type="submit">Resend Email</button>
    </form>
    <?php if ($message): ?>
        <div class="msg"><?= $message ?></div>
    <?php endif; ?>
</div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

