<?php
// add_user.php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/mail.php';

// Check if admin
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? 'user');

    if (!$username || !$password || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'All fields are required and email must be valid.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, confirm_token, confirmed) VALUES (?, ?, ?, ?, ?, 0)");
        if ($stmt->execute([$username, $hash, $email, $role, $token])) {
            $confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirm.php?token=" . urlencode($token);

            $body = "
                <p>Hello <strong>$username</strong>,</p>
                <p>Please confirm your email address by clicking the link below:</p>
                <p><a href='$confirmLink'>$confirmLink</a></p>
                <p>If you did not request this, you can ignore this email.</p>
            ";

            send_mail($email, 'Confirm your account', $body);
            $success = "✅ User '$username' created and confirmation email sent.";
        } else {
            $error = 'Failed to create user.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add User</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background: #f9f9f9;
      font-family: 'Segoe UI', sans-serif;
      padding: 20px;
      margin: 0;
    }
    .container {
      max-width: 400px;
      margin: 0 auto;
      background: #fff;
      padding: 24px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      margin-bottom: 1rem;
    }
    input, select {
      width: 100%;
      padding: 12px;
      margin-bottom: 1rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 16px;
    }
    button {
      width: 100%;
      background: #007bff;
      color: white;
      padding: 12px;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      cursor: pointer;
    }
    button:hover {
      background: #0056b3;
    }
    .msg {
      padding: 12px;
      margin-bottom: 1rem;
      border-radius: 6px;
    }
    .success { background: #d4edda; color: #155724; }
    .error   { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Create New User</h2>

    <?php if ($success): ?>
      <div class="msg success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="msg error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <select name="role">
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </select>
      <button type="submit">Add User</button>
    </form>
  </div>
</body>
</html>
