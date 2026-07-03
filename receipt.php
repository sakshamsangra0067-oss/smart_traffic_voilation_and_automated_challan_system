<?php
include("db.php");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['id'], $_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    exit("Invalid receipt request");
}

$sql = "
    SELECT c.id, c.user_id, c.vehicle_no, c.violation, c.fine_amount, c.status, u.email,
           MAX(p.id) AS payment_id
    FROM challans c
    LEFT JOIN users u ON u.id = c.user_id
    LEFT JOIN payments p ON p.challan_id = c.id
    WHERE c.id = ?
";

if ($_SESSION['role'] === 'user') {
    $sql .= " AND c.user_id = ?";
}

$sql .= " GROUP BY c.id, c.user_id, c.vehicle_no, c.violation, c.fine_amount, c.status, u.email LIMIT 1";
$stmt = $conn->prepare($sql);

if ($_SESSION['role'] === 'user') {
    $sessionUserId = (int) $_SESSION['id'];
    $stmt->bind_param("ii", $id, $sessionUserId);
} else {
    $stmt->bind_param("i", $id);
}

$stmt->execute();
$receipt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$receipt) {
    exit("Receipt not found");
}

$paid = strtolower((string) $receipt['status']) === 'paid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Receipt</title>
<style>
body{ margin:0; min-height:100vh; font-family:'Segoe UI',Tahoma,sans-serif; background:#edf3f9; color:#182433; display:grid; place-items:center; padding:24px; }
.receipt{ width:min(760px,100%); background:#fff; border-radius:22px; box-shadow:0 18px 42px rgba(15,23,42,.12); overflow:hidden; border:1px solid #d8e2ec; }
.head{ padding:24px; background:linear-gradient(135deg,#13273d,#0d1b2a); color:#fff; display:flex; justify-content:space-between; gap:18px; }
.head h1{ margin:0 0 6px; font-size:28px; }
.head p{ margin:0; color:rgba(255,255,255,.72); }
.status{ align-self:flex-start; padding:8px 12px; border-radius:999px; background:<?php echo $paid ? '#dcfce7' : '#fee2e2'; ?>; color:<?php echo $paid ? '#166534' : '#991b1b'; ?>; font-weight:800; }
.body{ padding:24px; }
.grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
.item{ padding:14px; border-radius:14px; background:#f7fafc; border:1px solid #e2e8f0; }
.item small{ display:block; color:#66788a; text-transform:uppercase; letter-spacing:.08em; font-size:11px; margin-bottom:6px; }
.item strong{ font-size:18px; }
.actions{ display:flex; gap:10px; margin-top:20px; }
.btn{ padding:11px 14px; border-radius:11px; border:none; text-decoration:none; font-weight:800; cursor:pointer; }
.primary{ background:#13273d; color:#fff; }
.secondary{ background:#fff; color:#13273d; border:1px solid #d8e2ec; }
@media print{ body{ background:#fff; padding:0; } .receipt{ box-shadow:none; border:none; } .actions{ display:none; } }
@media (max-width:640px){ .head,.actions{ flex-direction:column; } .grid{ grid-template-columns:1fr; } }
</style>
</head>
<body>
<main class="receipt">
    <div class="head">
        <div>
            <h1>Traffic Challan Receipt</h1>
            <p>Official payment acknowledgement for challan #<?php echo e((string) $receipt['id']); ?></p>
        </div>
        <div class="status"><?php echo $paid ? 'Paid' : 'Unpaid'; ?></div>
    </div>
    <div class="body">
        <div class="grid">
            <div class="item"><small>Receipt No</small><strong>RCPT-<?php echo e((string) $receipt['id']); ?></strong></div>
            <div class="item"><small>Citizen Email</small><strong><?php echo e($receipt['email'] ?? 'N/A'); ?></strong></div>
            <div class="item"><small>Vehicle Number</small><strong><?php echo e($receipt['vehicle_no']); ?></strong></div>
            <div class="item"><small>Violation</small><strong><?php echo e($receipt['violation']); ?></strong></div>
            <div class="item"><small>Fine Amount</small><strong>₹<?php echo e((string) $receipt['fine_amount']); ?></strong></div>
            <div class="item"><small>Generated On</small><strong><?php echo e(date('d M Y, h:i A')); ?></strong></div>
        </div>
        <div class="actions">
            <button class="btn primary" onclick="window.print()">Print Receipt</button>
            <a class="btn secondary" href="<?php echo $_SESSION['role'] === 'admin' ? 'challans.php' : 'user_dashboard.php'; ?>">Back</a>
        </div>
    </div>
</main>
</body>
</html>
