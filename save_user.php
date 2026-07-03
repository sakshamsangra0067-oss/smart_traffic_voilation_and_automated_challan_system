<?php
include("db.php");
ensure_logged_in('admin');

$email = strtolower(trim($_POST['email'] ?? ''));
$password = trim($_POST['password'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 4) {
    header("Location: users.php?message=invalid");
    exit();
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'user';

$checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$checkStmt->bind_param("s", $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    $stmt = $conn->prepare("UPDATE users SET password = ?, role = ? WHERE id = ?");
    $existingId = (int) $existing['id'];
    $stmt->bind_param("ssi", $passwordHash, $role, $existingId);
} else {
    $stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $passwordHash, $role);
}

$stmt->execute();
$stmt->close();

header("Location: users.php?message=created");
exit();
?>
