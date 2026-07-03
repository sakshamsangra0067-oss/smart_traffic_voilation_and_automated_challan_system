<?php
include("db.php");
ensure_logged_in('admin');

// ===== FETCH DATA =====
$total = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans"))['c'];

$paid = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE status='Paid'"))['c'];

$unpaid = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE status='Unpaid'"))['c'];

$revenue = mysqli_fetch_assoc(mysqli_query($conn,"SELECT SUM(fine_amount) s FROM challans WHERE status='Paid'"))['s'] ?? 0;

// Violations
$helmet = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE violation='Helmet'"))['c'];
$overspeed = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE violation='Overspeed'"))['c'];
$signal = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE violation='Signal Jump'"))['c'];
$mobile = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM challans WHERE violation='Mobile Usage'"))['c'];

// Latest challans
$list = mysqli_query($conn,"SELECT * FROM challans ORDER BY id DESC LIMIT 10");

$latestChallans = [];
if ($list instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($list)) {
        $latestChallans[] = $row;
    }
}

$mapCases = [
    [
        'lat' => 28.61,
        'lng' => 77.20,
        'title' => 'ITO Junction',
        'detail' => 'Helmet violation detected',
        'time' => '10:15 AM',
    ],
    [
        'lat' => 28.65,
        'lng' => 77.25,
        'title' => 'Akshardham Corridor',
        'detail' => 'Overspeed case reported',
        'time' => '12:40 PM',
    ],
];

$usingDemoData = false;

if ((int) $total === 0 && count($latestChallans) === 0) {
    $usingDemoData = true;

    $helmet = 6;
    $overspeed = 4;
    $signal = 3;
    $mobile = 2;
    $paid = 5;
    $unpaid = 10;
    $total = 15;
    $revenue = 6800;

    $latestChallans = [
        ['id' => 101, 'vehicle_no' => 'DL01AB1234', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 102, 'vehicle_no' => 'DL05CD7788', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Paid'],
        ['id' => 103, 'vehicle_no' => 'DL03EF4567', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Unpaid'],
        ['id' => 104, 'vehicle_no' => 'DL09GH2201', 'violation' => 'Mobile Usage', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 105, 'vehicle_no' => 'HR26JK9087', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 106, 'vehicle_no' => 'DL08LM6621', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
    ];

    $mapCases = [
        [
            'lat' => 28.631,
            'lng' => 77.216,
            'title' => 'Connaught Place',
            'detail' => 'Helmet violation case logged',
            'time' => '09:20 AM',
        ],
        [
            'lat' => 28.613,
            'lng' => 77.229,
            'title' => 'India Gate Circle',
            'detail' => 'Signal jump detected',
            'time' => '11:05 AM',
        ],
        [
            'lat' => 28.635,
            'lng' => 77.241,
            'title' => 'ITO Flyover',
            'detail' => 'Overspeed challan generated',
            'time' => '01:10 PM',
        ],
    ];
}

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$collectionRate = $total > 0 ? round(($paid / $total) * 100) : 0;
$pendingRate = $total > 0 ? round(($unpaid / $total) * 100) : 0;
$violationChart = json_encode([$helmet, $overspeed, $signal, $mobile]);
$mapCasesJson = json_encode($mapCases);
$topViolation = 'Helmet';
$topViolationCount = $helmet;

if ($overspeed > $topViolationCount) {
    $topViolation = 'Overspeed';
    $topViolationCount = $overspeed;
}
if ($signal > $topViolationCount) {
    $topViolation = 'Signal Jump';
    $topViolationCount = $signal;
}
if ($mobile > $topViolationCount) {
    $topViolation = 'Mobile Usage';
    $topViolationCount = $mobile;
}

$highRiskVehicles = 0;
$riskResult = mysqli_query($conn, "
    SELECT COUNT(*) c FROM (
        SELECT vehicle_no, COUNT(*) total_cases,
               SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) unpaid_cases,
               COALESCE(SUM(fine_amount), 0) total_fine
        FROM challans
        GROUP BY vehicle_no
        HAVING total_cases >= 3 OR unpaid_cases >= 2 OR total_fine >= 5000
    ) high_risk
");
if ($riskResult instanceof mysqli_result) {
    $highRiskVehicles = (int) mysqli_fetch_assoc($riskResult)['c'];
}

$recentPayments = 0;
$paymentTable = mysqli_query($conn, "SHOW TABLES LIKE 'payments'");
if ($paymentTable instanceof mysqli_result && mysqli_num_rows($paymentTable) > 0) {
    $paymentResult = mysqli_query($conn, "SELECT COUNT(*) c FROM payments");
    if ($paymentResult instanceof mysqli_result) {
        $recentPayments = (int) mysqli_fetch_assoc($paymentResult)['c'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🚦 Traffic Admin Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

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

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    font-family:'Segoe UI',Tahoma,sans-serif;
    color:var(--ink);
    background:linear-gradient(180deg, #edf3f9 0%, #dfe8f2 100%);
}

.shell{
    display:flex;
    min-height:100vh;
}

.sidebar{
    width:245px;
    background:linear-gradient(180deg, var(--navy-900), var(--navy-950));
    color:#fff;
    padding:22px 16px;
    position:sticky;
    top:0;
    height:100vh;
}

.seal{
    display:flex;
    align-items:center;
    gap:12px;
    padding:14px;
    margin-bottom:18px;
    border:1px solid rgba(96, 165, 250, 0.18);
    border-radius:14px;
    background:rgba(255,255,255,0.03);
}

.seal-mark{
    width:44px;
    height:44px;
    border-radius:12px;
    background:linear-gradient(135deg, var(--blue-400), var(--blue-500));
    display:grid;
    place-items:center;
    color:#fff;
    font-size:20px;
}

.seal small,
.status-note small{
    display:block;
    color:rgba(255,255,255,0.65);
    letter-spacing:0.08em;
    text-transform:uppercase;
    font-size:10px;
}

.seal strong{
    display:block;
    margin-top:4px;
    font-size:18px;
}

.nav-label{
    margin:16px 10px 8px;
    color:rgba(255,255,255,0.5);
    text-transform:uppercase;
    letter-spacing:0.12em;
    font-size:11px;
}

.sidebar a{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:12px 14px;
    margin:8px 0;
    color:#eef4ff;
    text-decoration:none;
    border-radius:12px;
    border:1px solid transparent;
    transition:0.2s ease;
}

.sidebar a span{
    color:rgba(255,255,255,0.56);
    font-size:12px;
}

.sidebar a:hover,
.sidebar a.active{
    background:rgba(59, 130, 246, 0.15);
    border-color:rgba(96, 165, 250, 0.28);
}

.status-note{
    margin-top:18px;
    padding:14px;
    border-radius:14px;
    background:rgba(255,255,255,0.04);
    border:1px solid rgba(255,255,255,0.08);
}

.status-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:10px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(21, 128, 61, 0.18);
    color:#b6f2c7;
    font-size:13px;
}

.logout{
    margin-top:18px;
    justify-content:center !important;
    background:linear-gradient(135deg, #c84235, #9c2d23);
}

.main{
    flex:1;
    padding:24px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    margin-bottom:18px;
}

.eyebrow{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:7px 11px;
    border-radius:999px;
    background:rgba(59, 130, 246, 0.10);
    color:#2459a8;
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
    max-width:700px;
    color:var(--muted);
    font-size:14px;
}

.profile-card{
    min-width:270px;
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
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
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
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

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
}

.detail-strip{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    margin-top:14px;
}

.mini-row,
.charts-row,
.row{
    display:grid;
    gap:16px;
    margin-top:16px;
}

.mini-row{
    grid-template-columns:1fr 1fr;
}

.charts-row{
    grid-template-columns:1fr 1fr;
}

.row{
    grid-template-columns:1.1fr 0.9fr;
}

.mini-panel,
.card,
.box{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}

.mini-panel,
.card,
.box{
    padding:18px;
}

.mini-panel h3,
.card .label,
.box h3{
    margin:0 0 8px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.mini-panel .metric{
    font-size:30px;
    font-weight:700;
}

.mini-panel .caption,
.card .trend,
.panel-head p{
    color:var(--muted);
    font-size:13px;
}

.card{
    position:relative;
    overflow:hidden;
}

.card:before{
    content:"";
    position:absolute;
    inset:0 auto auto 0;
    width:100%;
    height:4px;
    background:linear-gradient(90deg, var(--navy-800), var(--blue-400));
}

.card .value{
    margin-top:12px;
    font-size:34px;
    font-weight:700;
    color:#0d2238;
}

.panel-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:14px;
}

#map{
    height:270px;
    border-radius:14px;
    overflow:hidden;
    border:1px solid var(--line);
}

.chart-box{
    height:280px;
    position:relative;
}

.summary-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:10px;
}

.detail-chip{
    padding:12px 14px;
    border-radius:14px;
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    box-shadow:var(--shadow);
}

.detail-chip small{
    display:block;
    color:#6e7f90;
    text-transform:uppercase;
    letter-spacing:0.08em;
    font-size:11px;
}

.detail-chip strong{
    display:block;
    margin-top:8px;
    font-size:18px;
    color:#11253a;
}

.detail-chip span{
    display:block;
    margin-top:4px;
    color:var(--muted);
    font-size:13px;
}

.box-note{
    margin-top:12px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
    color:var(--muted);
    font-size:13px;
}

.summary-chip{
    padding:12px;
    border-radius:14px;
    background:var(--panel-soft);
    border:1px solid var(--line);
}

.summary-chip strong{
    display:block;
    font-size:18px;
    color:#0d2238;
}

.summary-chip span{
    color:var(--muted);
    font-size:13px;
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

.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:92px;
    padding:9px 13px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
}

.pay{
    background:linear-gradient(135deg, #1d8f61, #0e5c3d);
    color:#fff;
}

.delete{
    background:#f8d9d5;
    color:#8e241b;
}

@media (max-width: 1180px){
    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .detail-strip{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .mini-row,
    .charts-row,
    .row{
        grid-template-columns:1fr;
    }

    .topbar{
        flex-direction:column;
    }

    .profile-card{
        min-width:0;
        width:100%;
    }
}

@media (max-width: 920px){
    .shell{
        flex-direction:column;
    }

    .sidebar{
        width:100%;
        height:auto;
        position:relative;
    }

    .main{
        padding:18px;
    }
}

@media (max-width: 640px){
    .cards{
        grid-template-columns:1fr;
    }

    .detail-strip{
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
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">🛡</div>
            <div>
                <small>Traffic Control System</small>
                <strong>Admin Dashboard</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php" class="active"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>System Status</small>
            <strong>Project dashboard running normally</strong>
            <div class="status-pill">● Active</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">College Project Dashboard</div>
                <h1>Traffic Admin Dashboard</h1>
                <p>A simple analytics dashboard for managing challans, monitoring violations, and tracking payment performance.</p>
            </div>

            <div class="profile-card">
                <div class="profile-row">
                    <div class="avatar"><?php echo e($adminInitials); ?></div>
                    <div>
                        <strong>Administrator</strong>
                        <small><?php echo e($adminEmail); ?></small><br>
                        <small>Role: Traffic Admin</small>
                    </div>
                </div>
                <div class="toolbar">
                    <a href="report.php" class="primary">View Reports</a>
                    <a href="challans.php" class="secondary">Review Challans</a>
                </div>
            </div>
        </div>

        <section class="detail-strip" style="margin-bottom:14px;">
            <div class="detail-chip" style="grid-column:1 / -1;">
                <small>Global Search</small>
                <form method="GET" action="global_search.php" style="display:flex; gap:10px; margin-top:8px;">
                    <input name="q" placeholder="Search vehicle number, challan ID, user email, or violation" style="flex:1; padding:12px 14px; border:1px solid #d8e2ec; border-radius:12px;">
                    <button type="submit" style="padding:12px 16px; border:none; border-radius:12px; background:#13273d; color:#fff; font-weight:800; cursor:pointer;">Search</button>
                </form>
            </div>
        </section>

        <section class="detail-strip" style="margin-bottom:14px;">
            <div class="detail-chip">
                <small>Alert</small>
                <strong><?php echo e((string) $unpaid); ?> unpaid challans pending</strong>
                <span>Use challan management for payment follow-up.</span>
            </div>
            <div class="detail-chip">
                <small>High-Risk Detection</small>
                <strong><?php echo e((string) $highRiskVehicles); ?> vehicles flagged</strong>
                <span>Based on repeat cases or high fine exposure.</span>
            </div>
            <div class="detail-chip">
                <small>Payment Activity</small>
                <strong><?php echo e((string) $recentPayments); ?> payment records</strong>
                <span>Receipt generation is available after payment.</span>
            </div>
            <div class="detail-chip">
                <small>Quick Action</small>
                <strong>Create challan</strong>
                <span><a href="challans.php" style="color:#2459a8; font-weight:800;">Open challan form</a></span>
            </div>
        </section>

        <section class="mini-row">
            <div class="mini-panel">
                <h3>Collection Rate</h3>
                <div class="metric"><?php echo e((string) $collectionRate); ?>%</div>
                <div class="caption">Percentage of challans marked as paid.</div>
            </div>

            <div class="mini-panel">
                <h3>Pending Rate</h3>
                <div class="metric"><?php echo e((string) $pendingRate); ?>%</div>
                <div class="caption">Percentage of challans still unpaid.</div>
            </div>
        </section>

        <section class="cards">
            <div class="card">
                <div class="label">Total Challans</div>
                <div class="value"><?php echo e((string) $total); ?></div>
                <div class="trend">Total challans in the system.</div>
            </div>
            <div class="card">
                <div class="label">Revenue Realized</div>
                <div class="value">₹<?php echo e((string) $revenue); ?></div>
                <div class="trend">Amount collected from paid challans.</div>
            </div>
            <div class="card">
                <div class="label">Unpaid Cases</div>
                <div class="value"><?php echo e((string) $unpaid); ?></div>
                <div class="trend">Challans still pending payment.</div>
            </div>
            <div class="card">
                <div class="label">Paid Cases</div>
                <div class="value"><?php echo e((string) $paid); ?></div>
                <div class="trend">Challans already cleared.</div>
            </div>
        </section>

        <section class="detail-strip">
            <div class="detail-chip">
                <small>Top Violation</small>
                <strong><?php echo e($topViolation); ?></strong>
                <span><?php echo e((string) $topViolationCount); ?> recorded cases</span>
            </div>
            <div class="detail-chip">
                <small>Monitored Zone</small>
                <strong>Central Delhi Cluster</strong>
                <span>ITO, Connaught Place, India Gate</span>
            </div>
            <div class="detail-chip">
                <small>Last Sync</small>
                <strong><?php echo e(date('d M Y')); ?></strong>
                <span>Dashboard refreshed for latest records</span>
            </div>
            <div class="detail-chip">
                <small>Recommended Action</small>
                <strong>Review unpaid challans</strong>
                <span>Prioritize pending payment follow-ups</span>
            </div>
        </section>

        <?php if ($usingDemoData) { ?>
        <section class="detail-strip" style="margin-top:12px;">
            <div class="detail-chip" style="grid-column:1 / -1;">
                <small>Demo Records Loaded</small>
                <strong>Sample challans and cases are currently displayed</strong>
                <span>Add real challans in the database to replace these presentation-ready demo entries automatically.</span>
            </div>
        </section>
        <?php } ?>

        <section class="charts-row">
            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3>Pie Chart Analysis</h3>
                        <p>Category-wise view of major traffic violations.</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="pieChart"></canvas>
                </div>
                <div class="box-note">This chart helps explain which violation type contributes most to total challan volume.</div>
            </div>

            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3>Bar Graph Analysis</h3>
                        <p>Comparison of violation counts in each category.</p>
                    </div>
                </div>
                <div class="chart-box">
                    <canvas id="barChart"></canvas>
                </div>
                <div class="box-note">The bar graph is useful for direct visual comparison during your project presentation.</div>
            </div>
        </section>

        <section class="row">
            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3>Live Urban Surveillance Map</h3>
                        <p>Map view of sample traffic violation locations.</p>
                    </div>
                </div>
                <div id="map"></div>
                <div class="box-note">Sample locations are displayed to represent field detections in a real deployment scenario.</div>
            </div>

            <div class="box">
                <div class="panel-head">
                    <div>
                        <h3>Violation Summary</h3>
                        <p>Quick summary of major violation categories.</p>
                    </div>
                </div>
                <div class="summary-grid">
                    <div class="summary-chip">
                        <strong><?php echo e((string) $helmet); ?></strong>
                        <span>Helmet cases</span>
                    </div>
                    <div class="summary-chip">
                        <strong><?php echo e((string) $overspeed); ?></strong>
                        <span>Overspeed cases</span>
                    </div>
                    <div class="summary-chip">
                        <strong><?php echo e((string) $signal); ?></strong>
                        <span>Signal violations</span>
                    </div>
                    <div class="summary-chip">
                        <strong><?php echo e((string) $mobile); ?></strong>
                        <span>Mobile usage cases</span>
                    </div>
                </div>
                <div class="box-note">These quick counts make the dashboard easier to read before checking the detailed charts.</div>
            </div>
        </section>

        <section class="row">
            <div class="box" style="grid-column:1 / -1;">
                <div class="panel-head">
                    <div>
                        <h3>Latest Challan Actions</h3>
                        <p>Recent challans available for review and update.</p>
                    </div>
                </div>

                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>ID</th>
                            <th>Vehicle</th>
                            <th>Violation</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                        <?php foreach($latestChallans as $row){ ?>
                        <?php $isPaid = strtolower((string) $row['status']) === 'paid'; ?>
                        <tr>
                            <td><?php echo e($row['id']) ?></td>
                            <td><?php echo e($row['vehicle_no']) ?></td>
                            <td><?php echo e($row['violation']) ?></td>
                            <td>₹<?php echo e($row['fine_amount']) ?></td>
                            <td>
                                <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                    <?php echo e($row['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isPaid) { ?>
                                    <span class="btn delete" style="cursor:default;">Cleared</span>
                                <?php } else { ?>
                                    <a href="mark_paid.php?id=<?php echo $row['id'] ?>" class="btn pay">Mark Paid</a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="box-note">Action buttons allow the administrator to update payment status directly from the dashboard.</div>
            </div>
        </section>
    </main>
</div>

<script>
// ===== MAP =====
var map = L.map('map').setView([28.6139,77.2090],11);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

const mapCases = <?php echo $mapCasesJson; ?>;
mapCases.forEach(function(item){
    L.marker([item.lat, item.lng]).addTo(map).bindPopup(
        '<strong>' + item.title + '</strong><br>' + item.detail + '<br>Time: ' + item.time
    );
});

const chartData = <?php echo $violationChart; ?>;
const commonLabels = ['Helmet','Overspeed','Signal','Mobile'];

// ===== PIE =====
new Chart(document.getElementById('pieChart'),{
    type:'doughnut',
    data:{
        labels:commonLabels,
        datasets:[{
            data:chartData,
            backgroundColor:['#ff7675','#4f8df7','#f5b041','#27ae60'],
            borderWidth:0
        }]
    },
    options:{
        maintainAspectRatio:false,
        plugins:{
            legend:{
                position:'bottom',
                labels:{
                    padding:16,
                    color:'#41586e',
                    font:{size:12, weight:'600'}
                }
            }
        },
        cutout:'65%'
    }
});

// ===== BAR =====
new Chart(document.getElementById('barChart'),{
    type:'bar',
    data:{
        labels:commonLabels,
        datasets:[{
            label:'Violations',
            data:chartData,
            backgroundColor:['#ff7675','#4f8df7','#f5b041','#27ae60'],
            borderRadius:10,
            borderSkipped:false
        }]
    },
    options:{
        responsive:true,
        plugins:{
            legend:{display:false},
            tooltip:{
                callbacks:{
                    label:function(context){
                        return context.label + ': ' + context.raw + ' cases';
                    }
                }
            }
        },
        scales:{
            y:{
                beginAtZero:true,
                ticks:{precision:0, color:'#607489'},
                grid:{color:'rgba(96, 116, 137, 0.12)'}
            },
            x:{
                ticks:{color:'#41586e'},
                grid:{display:false}
            }
        }
    }
});
</script>

</body>
</html>
