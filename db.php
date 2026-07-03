<?php
$conn = new mysqli("localhost", "root", "", "traffic_system");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

function ensure_logged_in(?string $requiredRole = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!isset($_SESSION['id'], $_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }

    if ($requiredRole !== null && $_SESSION['role'] !== $requiredRole) {
        header("Location: logout.php");
        exit();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
