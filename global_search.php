<?php
include("db.php");
ensure_logged_in('admin');

$q = trim($_GET['q'] ?? '');
$challans = [];
$users = [];
$vehicles = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $stmt = $conn->prepare("SELECT id, vehicle_no, violation, fine_amount, status FROM challans WHERE vehicle_no LIKE ? OR violation LIKE ? OR CAST(id AS CHAR) LIKE ? ORDER BY id DESC LIMIT 8");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $challans[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, email, role FROM users WHERE email LIKE ? OR CAST(id AS CHAR) LIKE ? ORDER BY id ASC LIMIT 8");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT vehicle_no, COUNT(*) total_cases, SUM(CASE WHEN status='Unpaid' THEN 1 ELSE 0 END) unpaid_cases FROM challans WHERE vehicle_no LIKE ? GROUP BY vehicle_no ORDER BY total_cases DESC LIMIT 8");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Global Search</title>
<style>
body{ margin:0; min-height:100vh; font-family:'Segoe UI',Tahoma,sans-serif; background:#edf3f9; color:#182433; padding:26px; }
.wrap{ max-width:1100px; margin:0 auto; }
.top{ display:flex; justify-content:space-between; gap:16px; align-items:center; margin-bottom:18px; }
h1{ margin:0; font-size:34px; }
form{ display:flex; gap:10px; margin:16px 0; }
input{ flex:1; padding:13px 14px; border-radius:12px; border:1px solid #d8e2ec; }
button,.btn{ padding:12px 14px; border-radius:12px; border:none; background:#13273d; color:#fff; text-decoration:none; font-weight:800; cursor:pointer; }
.panel{ background:#fff; border:1px solid #d8e2ec; border-radius:18px; padding:18px; margin-bottom:14px; box-shadow:0 14px 32px rgba(15,23,42,.08); }
table{ width:100%; border-collapse:collapse; }
th,td{ padding:12px; border-bottom:1px solid #e9eef3; text-align:left; }
th{ font-size:12px; text-transform:uppercase; color:#66788a; }
@media(max-width:720px){ .top,form{ flex-direction:column; align-items:stretch; } }
</style>
</head>
<body>
<main class="wrap">
    <div class="top">
        <div>
            <h1>Global Search</h1>
            <p>Search challans, users, and vehicles from one place.</p>
        </div>
        <a href="admin_dashboard.php" class="btn">Back to Dashboard</a>
    </div>

    <form method="GET">
        <input name="q" value="<?php echo e($q); ?>" placeholder="Search by vehicle number, challan ID, violation, or email">
        <button type="submit">Search</button>
    </form>

    <section class="panel">
        <h3>Challan Results</h3>
        <table>
            <tr><th>ID</th><th>Vehicle</th><th>Violation</th><th>Fine</th><th>Status</th><th>Open</th></tr>
            <?php foreach ($challans as $row) { ?>
            <tr><td>#<?php echo e((string) $row['id']); ?></td><td><?php echo e($row['vehicle_no']); ?></td><td><?php echo e($row['violation']); ?></td><td>₹<?php echo e((string) $row['fine_amount']); ?></td><td><?php echo e($row['status']); ?></td><td><a href="challans.php?search=<?php echo urlencode((string) $row['id']); ?>">Open</a></td></tr>
            <?php } ?>
        </table>
    </section>

    <section class="panel">
        <h3>User Results</h3>
        <table>
            <tr><th>ID</th><th>Email</th><th>Role</th><th>Open</th></tr>
            <?php foreach ($users as $row) { ?>
            <tr><td>#<?php echo e((string) $row['id']); ?></td><td><?php echo e($row['email']); ?></td><td><?php echo e($row['role']); ?></td><td><a href="users.php?search=<?php echo urlencode($row['email']); ?>">Open</a></td></tr>
            <?php } ?>
        </table>
    </section>

    <section class="panel">
        <h3>Vehicle Results</h3>
        <table>
            <tr><th>Vehicle</th><th>Total Cases</th><th>Unpaid</th><th>Open</th></tr>
            <?php foreach ($vehicles as $row) { ?>
            <tr><td><?php echo e($row['vehicle_no']); ?></td><td><?php echo e((string) $row['total_cases']); ?></td><td><?php echo e((string) $row['unpaid_cases']); ?></td><td><a href="vechile.php?search=<?php echo urlencode($row['vehicle_no']); ?>">Open</a></td></tr>
            <?php } ?>
        </table>
    </section>
</main>
</body>
</html>
