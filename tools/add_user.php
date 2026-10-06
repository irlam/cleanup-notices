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

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<main class="docs-account-page">
<div class="container">
    <h2>Create New User</h2>

    <?php if ($success): ?>
      <div class="msg success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="msg error"><?= $error ?></div>
    <?php endif; ?>

    <form method="post">
      <label for="username">Username</label><input id="username" type="text" name="username" placeholder="Username" required>
      <label for="email">Email address</label><input id="email" type="email" name="email" placeholder="Email" required>
      <label for="password">Password</label><input id="password" type="password" name="password" placeholder="Password" required>
      <label for="role">Role</label><select id="role" name="role">
        <option value="user">User</option>
        <option value="admin">Admin</option>
      </select>
      <button class="btn-primary" type="submit">Add User</button>
    </form>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

