<?php
include("db.php");
ensure_logged_in('user');

$userId = (int) $_SESSION['id'];
$userEmail = $_SESSION['user'] ?? 'user@trafficdemo.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

$stmt = $conn->prepare("SELECT * FROM challans WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$challans = [];
while ($row = $result->fetch_assoc()) {
    $challans[] = $row;
}
$stmt->close();

$usingDemoData = false;
if (count($challans) === 0) {
    $usingDemoData = true;
    $challans = [
        ['id' => 201, 'vehicle_no' => 'DL01AB1234', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 187, 'vehicle_no' => 'DL01AB1234', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Paid'],
        ['id' => 166, 'vehicle_no' => 'DL01AB1234', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
    ];
}

$totalChallans = count($challans);
$paidCount = 0;
$unpaidCount = 0;
$totalFine = 0;
$pendingAmount = 0;

foreach ($challans as $challan) {
    $amount = (float) $challan['fine_amount'];
    $totalFine += $amount;

    if (strtolower((string) $challan['status']) === 'paid') {
        $paidCount++;
    } else {
        $unpaidCount++;
        $pendingAmount += $amount;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Dashboard</title>
<style>
:root{
    --navy-950:#0d1b2a;
    --navy-900:#13273d;
    --navy-800:#1d4060;
    --blue-500:#3b82f6;
    --blue-400:#60a5fa;
    --panel:#ffffff;
    --panel-soft:#f4f8fd;
    --line:#d8e2ec;
    --ink:#182433;
    --muted:#66788a;
    --success:#15803d;
    --danger:#b33c2f;
    --shadow:0 14px 32px rgba(15, 23, 42, 0.08);
}

*{ box-sizing:border-box; }

body{
    margin:0;
    min-height:100vh;
    font-family:'Segoe UI',Tahoma,sans-serif;
    color:var(--ink);
    background:linear-gradient(180deg, #edf3f9 0%, #dfe8f2 100%);
}

.shell{
    min-height:100vh;
    display:flex;
    flex-direction:column;
}

.topbar{
    background:linear-gradient(135deg, var(--navy-900), var(--navy-950));
    color:#fff;
    padding:20px 24px;
}

.topbar-inner{
    max-width:1200px;
    margin:0 auto;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(96, 165, 250, 0.18);
    color:#dbeafe;
    font-size:11px;
    letter-spacing:0.08em;
    text-transform:uppercase;
}

.title-block h1{
    margin:10px 0 8px;
    font-size:34px;
    line-height:1.1;
}

.title-block p{
    margin:0;
    max-width:720px;
    color:rgba(255,255,255,0.74);
    font-size:14px;
}

.profile-card{
    min-width:280px;
    background:rgba(255,255,255,0.96);
    color:var(--ink);
    border-radius:18px;
    padding:16px;
    box-shadow:var(--shadow);
}

.profile-row{
    display:flex;
    align-items:center;
    gap:12px;
}

.avatar{
    width:48px;
    height:48px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:linear-gradient(135deg, var(--blue-400), var(--blue-500));
    color:#fff;
    font-weight:700;
}

.profile-card strong{
    display:block;
    font-size:16px;
}

.profile-card small{
    color:var(--muted);
}

.toolbar{
    display:flex;
    gap:10px;
    margin-top:12px;
}

.toolbar a{
    text-decoration:none;
    padding:9px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
}

.toolbar .primary{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}

.toolbar .secondary{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
}

.main{
    max-width:1200px;
    width:100%;
    margin:0 auto;
    padding:24px;
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    margin-bottom:16px;
}

.stat-card,.table-panel,.notice{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}

.stat-card{
    padding:18px;
    position:relative;
    overflow:hidden;
}

.stat-card:before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, var(--navy-800), var(--blue-400));
}

.label{
    margin:0 0 8px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.value{
    margin-top:12px;
    font-size:34px;
    font-weight:700;
    color:#0d2238;
}

.trend,.panel-head p,.notice p,.table-note{
    color:var(--muted);
    font-size:13px;
}

.notice{
    padding:16px 18px;
    margin-bottom:16px;
}

.notice strong{
    display:block;
    margin-bottom:6px;
    color:#0d2238;
}

.table-panel{
    padding:18px;
}

.panel-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    margin-bottom:14px;
}

.panel-head h3{
    margin:0 0 6px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.table-wrap{
    overflow:auto;
}

table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
}

th,td{
    padding:13px 12px;
    border-bottom:1px solid #e9eef3;
    text-align:left;
    font-size:14px;
}

th{
    background:#f7fafc;
    color:#55687a;
    text-transform:uppercase;
    letter-spacing:0.08em;
    font-size:11px;
}

tbody tr:hover{
    background:#fbfdff;
}

.status-badge{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.06em;
}

.status-paid{
    background:rgba(21, 128, 61, 0.12);
    color:var(--success);
}

.status-unpaid{
    background:rgba(179, 60, 47, 0.12);
    color:var(--danger);
}

.violation-tag{
    display:inline-flex;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    background:#eef4ff;
    color:#2459a8;
}

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:90px;
    padding:9px 13px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
}

.btn-pay{
    background:linear-gradient(135deg, #1d8f61, #0e5c3d);
    color:#fff;
}

.btn-light{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
}

.table-note{
    margin-top:14px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
}

@media (max-width: 1180px){
    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .topbar-inner{
        flex-direction:column;
    }

    .profile-card{
        min-width:0;
        width:100%;
    }
}

@media (max-width: 640px){
    .cards{
        grid-template-columns:1fr;
    }

    .title-block h1{
        font-size:28px;
    }

    .toolbar{
        flex-direction:column;
    }
}
</style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <div class="topbar-inner">
            <div class="title-block">
                <div class="eyebrow">Citizen Payment Portal</div>
                <h1>User Dashboard</h1>
                <p>View your challan history, track payment status, and complete pending payments from one clean user portal.</p>
            </div>

            <div class="profile-card">
                <div class="profile-row">
                    <div class="avatar"><?php echo e($userInitial); ?></div>
                    <div>
                        <strong>Registered User</strong>
                        <small><?php echo e($userEmail); ?></small><br>
                        <small>Role: Citizen/User</small>
                    </div>
                </div>
                <div class="toolbar">
                    <a href="logout.php" class="primary">Logout</a>
                    <a href="login.php" class="secondary">Switch Account</a>
                </div>
            </div>
        </div>
    </header>

    <main class="main">
        <section class="cards">
            <div class="stat-card">
                <div class="label">Total Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">All challans linked to your account.</div>
            </div>
            <div class="stat-card">
                <div class="label">Paid Challans</div>
                <div class="value"><?php echo e((string) $paidCount); ?></div>
                <div class="trend">Cleared cases in your record.</div>
            </div>
            <div class="stat-card">
                <div class="label">Unpaid Challans</div>
                <div class="value"><?php echo e((string) $unpaidCount); ?></div>
                <div class="trend">Cases still awaiting payment.</div>
            </div>
            <div class="stat-card">
                <div class="label">Pending Amount</div>
                <div class="value">₹<?php echo e((string) $pendingAmount); ?></div>
                <div class="trend">Amount currently due for payment.</div>
            </div>
        </section>

        <section class="notice">
            <strong>Payment guidance</strong>
            <p>Use the pay button for any unpaid challan below. Once payment is recorded, the status changes automatically in your dashboard.</p>
        </section>

        <section class="table-panel">
            <div class="panel-head">
                <div>
                    <h3>Your Challan History</h3>
                    <p>All challans registered under your user account with payment status and quick action.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Challan ID</th>
                        <th>Vehicle No</th>
                        <th>Violation</th>
                        <th>Fine Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($challans as $row) { ?>
                    <?php $isPaid = strtolower((string) $row['status']) === 'paid'; ?>
                    <tr>
                        <td>#<?php echo e($row['id']); ?></td>
                        <td><?php echo e($row['vehicle_no']); ?></td>
                        <td><span class="violation-tag"><?php echo e($row['violation']); ?></span></td>
                        <td>₹<?php echo e($row['fine_amount']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo e($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isPaid) { ?>
                            <span class="btn btn-light" style="cursor:default;">Paid</span>
                            <?php } else { ?>
                            <a href="pay.php?id=<?php echo e($row['id']); ?>" class="btn btn-pay">Pay Now</a>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
            </div>

            <div class="table-note">
                <?php if ($usingDemoData) { ?>
                Demo challans are being shown because no user-specific challans were found in the database yet.
                <?php } else { ?>
                This table is linked to your account and updates automatically after successful payment.
                <?php } ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
