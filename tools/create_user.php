<?php
require_once 'includes/db.php';

$username = 'mcgoff';
$password = 'mcgoff'; // Change this if you want
$email = 'chris.irlam@mcgoffgroup.com';
$role = 'User';

$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
if ($stmt->execute([$username, $hashed, $email, $role])) {
    echo "✅ User '$username' created successfully.";
} else {
    echo "❌ Failed to create user.";
}
