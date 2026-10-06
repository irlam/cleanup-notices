<?php
// confirm.php – Handles account confirmation via emailed token
require_once 'includes/db.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (!$token) {
    echo '❌ Invalid confirmation link.';
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, confirmed FROM users WHERE confirm_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        echo '❌ Invalid or expired token.';
        exit;
    }

    if ((int)$user['confirmed'] === 1) {
        echo '✅ Your account is already confirmed.';
        exit;
    }

    // Update confirmed status
    $update = $pdo->prepare("UPDATE users SET confirmed = 1, confirm_token = NULL WHERE id = ?");
    $update->execute([$user['id']]);

    echo '✅ Your email has been confirmed successfully. You may now <a href="index.php">log in</a>.';

} catch (Throwable $e) {
    echo '❌ An error occurred. Please try again later.';
    error_log('confirm.php error: ' . $e->getMessage());
}
