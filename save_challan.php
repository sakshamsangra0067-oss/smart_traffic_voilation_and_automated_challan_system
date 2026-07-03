<?php
include("db.php");
ensure_logged_in('admin');

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
$vehicleNo = strtoupper(trim($_POST['vehicle_no'] ?? ''));
$violation = trim($_POST['violation'] ?? '');
$fineAmount = isset($_POST['fine_amount']) ? (float) $_POST['fine_amount'] : 0;
$status = trim($_POST['status'] ?? 'Unpaid');
$allowedViolations = ['Helmet', 'Overspeed', 'Signal Jump', 'Mobile Usage'];
$allowedStatuses = ['Paid', 'Unpaid'];

if ($userId <= 0 || $vehicleNo === '' || !in_array($violation, $allowedViolations, true) || $fineAmount <= 0 || !in_array($status, $allowedStatuses, true)) {
    header("Location: challans.php?message=invalid");
    exit();
}

if ($id > 0) {
    $stmt = $conn->prepare("UPDATE challans SET user_id = ?, vehicle_no = ?, violation = ?, fine_amount = ?, status = ? WHERE id = ?");
    $stmt->bind_param("issdsi", $userId, $vehicleNo, $violation, $fineAmount, $status, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: challans.php?message=updated");
    exit();
}

$stmt = $conn->prepare("INSERT INTO challans (user_id, vehicle_no, violation, fine_amount, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("issds", $userId, $vehicleNo, $violation, $fineAmount, $status);
$stmt->execute();
$stmt->close();

header("Location: challans.php?message=created");
exit();
?>
