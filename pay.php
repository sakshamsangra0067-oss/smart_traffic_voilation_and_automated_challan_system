<?php
include("db.php");
ensure_logged_in('user');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$userId = (int) $_SESSION['id'];

if ($id <= 0) {
    exit("Invalid challan ID");
}

$challanStmt = $conn->prepare("SELECT id, fine_amount, status FROM challans WHERE id = ? AND user_id = ? LIMIT 1");
$challanStmt->bind_param("ii", $id, $userId);
$challanStmt->execute();
$challan = $challanStmt->get_result()->fetch_assoc();
$challanStmt->close();

if (!$challan) {
    exit("Challan not found");
}

if (strtolower((string) $challan['status']) !== 'paid') {
    $updateStmt = $conn->prepare("UPDATE challans SET status = 'Paid' WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param("ii", $id, $userId);
    $updateStmt->execute();
    $updateStmt->close();

    $paymentStmt = $conn->prepare("INSERT INTO payments (challan_id, amount) VALUES (?, ?)");
    if ($paymentStmt) {
        $amount = (float) $challan['fine_amount'];
        $paymentStmt->bind_param("id", $id, $amount);
        $paymentStmt->execute();
        $paymentStmt->close();
    }
}

header("Location: receipt.php?id=" . $id);
exit();
?>
