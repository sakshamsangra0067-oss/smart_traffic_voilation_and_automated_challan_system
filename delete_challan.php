<?php
include("db.php");
ensure_logged_in('admin');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    header("Location: challans.php?message=invalid");
    exit();
}

$paymentStmt = $conn->prepare("DELETE FROM payments WHERE challan_id = ?");
if ($paymentStmt) {
    $paymentStmt->bind_param("i", $id);
    $paymentStmt->execute();
    $paymentStmt->close();
}

$stmt = $conn->prepare("DELETE FROM challans WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: challans.php?message=deleted");
exit();
?>
