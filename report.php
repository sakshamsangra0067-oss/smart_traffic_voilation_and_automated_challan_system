<?php
include("db.php");
ensure_logged_in('admin');

$totals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_challans,
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_challans,
        SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid_challans,
        COALESCE(SUM(CASE WHEN status = 'Paid' THEN fine_amount ELSE 0 END), 0) AS revenue,
        COALESCE(SUM(fine_amount), 0) AS total_fine
    FROM challans
"));

$violationResult = mysqli_query($conn, "
    SELECT violation, COUNT(*) AS total, COALESCE(SUM(fine_amount), 0) AS amount
    FROM challans
    GROUP BY violation
    ORDER BY total DESC, violation ASC
");

$vehicleResult = mysqli_query($conn, "
    SELECT vehicle_no, COUNT(*) AS total_cases, COALESCE(SUM(fine_amount), 0) AS total_fine
    FROM challans
    GROUP BY vehicle_no
    ORDER BY total_cases DESC, total_fine DESC
    LIMIT 6
");

$violations = [];
while ($row = mysqli_fetch_assoc($violationResult)) {
    $violations[] = $row;
}

$vehicles = [];
if ($vehicleResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($vehicleResult)) {
        $vehicles[] = $row;
    }
}

$usingDemoData = false;
if ((int) ($totals['total_challans'] ?? 0) === 0) {
    $usingDemoData = true;
    $totals = [
        'total_challans' => 50,
        'paid_challans' => 27,
        'unpaid_challans' => 23,
        'revenue' => 36000,
        'total_fine' => 70500,
    ];

    $violations = [
        ['violation' => 'Helmet', 'total' => 15, 'amount' => 15000],
        ['violation' => 'Overspeed', 'total' => 14, 'amount' => 28000],
        ['violation' => 'Signal Jump', 'total' => 11, 'amount' => 16500],
        ['violation' => 'Mobile Usage', 'total' => 10, 'amount' => 10000],
    ];

    $vehicles = [
        ['vehicle_no' => 'UP16CC3003', 'total_cases' => 4, 'total_fine' => 6500],
        ['vehicle_no' => 'DL01AB1234', 'total_cases' => 3, 'total_fine' => 3000],
        ['vehicle_no' => 'GJ01CC7070', 'total_cases' => 3, 'total_fine' => 5000],
        ['vehicle_no' => 'KA03BB2468', 'total_cases' => 2, 'total_fine' => 4000],
        ['vehicle_no' => 'PB10AA5050', 'total_cases' => 2, 'total_fine' => 2500],
        ['vehicle_no' => 'HR26JK9087', 'total_cases' => 2, 'total_fine' => 2500],
    ];
}

$totalChallans = (int) ($totals['total_challans'] ?? 0);
$paidChallans = (int) ($totals['paid_challans'] ?? 0);
$unpaidChallans = (int) ($totals['unpaid_challans'] ?? 0);
$revenue = (float) ($totals['revenue'] ?? 0);
$totalFine = (float) ($totals['total_fine'] ?? 0);
$collectionRate = $totalChallans > 0 ? round(($paidChallans / $totalChallans) * 100) : 0;
$pendingRate = $totalChallans > 0 ? round(($unpaidChallans / $totalChallans) * 100) : 0;

$topViolation = $violations[0]['violation'] ?? 'N/A';
$topViolationCases = $violations[0]['total'] ?? 0;

$violationLabels = array_column($violations, 'violation');
$violationCounts = array_map('intval', array_column($violations, 'total'));
$violationAmounts = array_map('floatval', array_column($violations, 'amount'));
$vehicleLabels = array_column($vehicles, 'vehicle_no');
$vehicleCases = array_map('intval', array_column($vehicles, 'total_cases'));

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Traffic Reports</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    --warning:#b7791f;
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
.shell{ display:flex; min-height:100vh; }
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
.seal small,.status-note small{
    display:block;
    color:rgba(255,255,255,0.65);
    letter-spacing:0.08em;
    text-transform:uppercase;
    font-size:10px;
}
.seal strong{ display:block; margin-top:4px; font-size:18px; }
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
.sidebar a span{ color:rgba(255,255,255,0.56); font-size:12px; }
.sidebar a:hover,.sidebar a.active{
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
.main{ flex:1; padding:24px; }
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
    max-width:760px;
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
.profile-row{ display:flex; align-items:center; gap:12px; }
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
.profile-card strong{ display:block; font-size:16px; }
.profile-card small{ color:var(--muted); }
.toolbar{ display:flex; gap:10px; margin-top:12px; }
.toolbar a,.toolbar button{
    text-decoration:none;
    padding:9px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:600;
    border:none;
    cursor:pointer;
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
.cards,.insight-grid,.charts-row{
    display:grid;
    gap:14px;
}
.cards{
    grid-template-columns:repeat(4, minmax(0, 1fr));
    margin-bottom:16px;
}
.insight-grid{
    grid-template-columns:repeat(3, minmax(0, 1fr));
    margin-bottom:16px;
}
.charts-row{
    grid-template-columns:1fr 1fr;
    margin-bottom:16px;
}
.stat-card,.panel,.insight-card{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}
.stat-card,.panel,.insight-card{ padding:18px; }
.stat-card{
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
.label,.panel h3,.insight-card small{
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
.trend,.panel p,.table-note,.insight-card span{
    color:var(--muted);
    font-size:13px;
}
.insight-card strong{
    display:block;
    margin:8px 0 4px;
    font-size:20px;
}
.tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.tab-btn{
    padding:10px 14px;
    border:none;
    border-radius:999px;
    background:#fff;
    color:#35516c;
    border:1px solid var(--line);
    font-weight:800;
    cursor:pointer;
}
.tab-btn.active{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}
.tab-section{ display:none; }
.tab-section.active{ display:block; }
.chart-box{
    height:290px;
    position:relative;
}
.table-wrap{ overflow:auto; }
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
tbody tr:hover{ background:#fbfdff; }
.tag{
    display:inline-flex;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    background:#eef4ff;
    color:#2459a8;
}
.table-note{
    margin-top:14px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
}
@media print{
    .sidebar,.profile-card,.tabs,.toolbar{ display:none !important; }
    .shell{ display:block; }
    .main{ padding:0; }
    body{ background:#fff; }
    .tab-section{ display:block !important; }
}
@media (max-width:1180px){
    .cards{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .insight-grid,.charts-row{ grid-template-columns:1fr; }
    .topbar{ flex-direction:column; }
    .profile-card{ min-width:0; width:100%; }
}
@media (max-width:920px){
    .shell{ flex-direction:column; }
    .sidebar{ width:100%; height:auto; position:relative; }
    .main{ padding:18px; }
}
@media (max-width:640px){
    .cards{ grid-template-columns:1fr; }
    .title-block h1{ font-size:28px; }
    .toolbar{ flex-direction:column; }
}
</style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">📊</div>
            <div>
                <small>Traffic Control System</small>
                <strong>Reports</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php" class="active"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>Report Status</small>
            <strong>Interactive analytics available</strong>
            <div class="status-pill">● Same tab view</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Interactive Report Section</div>
                <h1>Traffic Reports</h1>
                <p>Analyze challan recovery, violation categories, and repeat vehicle trends without leaving this tab.</p>
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
                    <a href="admin_dashboard.php" class="primary">Back to Dashboard</a>
                    <button type="button" class="secondary" onclick="window.print()">Print Report</button>
                    <a href="print.php" class="secondary">PDF View</a>
                    <a href="export_csv.php?type=reports" class="secondary">Export CSV</a>
                </div>
            </div>
        </div>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Total Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">All challans in reporting view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Paid Cases</div>
                <div class="value"><?php echo e((string) $paidChallans); ?></div>
                <div class="trend"><?php echo e((string) $collectionRate); ?>% collection rate.</div>
            </div>
            <div class="stat-card">
                <div class="label">Unpaid Cases</div>
                <div class="value"><?php echo e((string) $unpaidChallans); ?></div>
                <div class="trend"><?php echo e((string) $pendingRate); ?>% pending rate.</div>
            </div>
            <div class="stat-card">
                <div class="label">Revenue</div>
                <div class="value">₹<?php echo e((string) $revenue); ?></div>
                <div class="trend">Collected from paid challans.</div>
            </div>
        </section>

        <section class="insight-grid">
            <div class="insight-card">
                <small>Top Violation</small>
                <strong><?php echo e($topViolation); ?></strong>
                <span><?php echo e((string) $topViolationCases); ?> cases reported.</span>
            </div>
            <div class="insight-card">
                <small>Total Fine Value</small>
                <strong>₹<?php echo e((string) $totalFine); ?></strong>
                <span>Complete fine amount across all challans.</span>
            </div>
            <div class="insight-card">
                <small>Recommended Action</small>
                <strong>Follow up unpaid cases</strong>
                <span>Use challan management to close pending payments.</span>
            </div>
        </section>

        <?php if ($usingDemoData) { ?>
        <div class="table-note" style="margin-bottom:16px;">Demo report data is displayed because the database has no challan records yet.</div>
        <?php } ?>

        <div class="tabs">
            <button class="tab-btn active" type="button" data-tab="overview">Overview</button>
            <button class="tab-btn" type="button" data-tab="violations">Violation Report</button>
            <button class="tab-btn" type="button" data-tab="vehicles">Vehicle Report</button>
        </div>

        <section id="overview" class="tab-section active">
            <div class="charts-row">
                <div class="panel">
                    <h3>Payment Status</h3>
                    <p>Paid versus unpaid challan distribution.</p>
                    <div class="chart-box"><canvas id="statusChart"></canvas></div>
                </div>
                <div class="panel">
                    <h3>Violation Comparison</h3>
                    <p>Case volume by violation category.</p>
                    <div class="chart-box"><canvas id="violationChart"></canvas></div>
                </div>
            </div>
        </section>

        <section id="violations" class="tab-section">
            <div class="panel">
                <h3>Violation Summary</h3>
                <p>Detailed report of cases and fine amount by violation type.</p>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>Violation</th>
                            <th>Total Cases</th>
                            <th>Total Fine</th>
                            <th>Share</th>
                        </tr>
                        <?php foreach ($violations as $row) { ?>
                        <?php $share = $totalChallans > 0 ? round(((int) $row['total'] / $totalChallans) * 100) : 0; ?>
                        <tr>
                            <td><span class="tag"><?php echo e($row['violation']); ?></span></td>
                            <td><?php echo e((string) $row['total']); ?></td>
                            <td>₹<?php echo e((string) $row['amount']); ?></td>
                            <td><?php echo e((string) $share); ?>%</td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="table-note">This report helps identify which violation categories need more enforcement attention.</div>
            </div>
        </section>

        <section id="vehicles" class="tab-section">
            <div class="panel">
                <h3>Repeat Vehicle Report</h3>
                <p>Vehicles with the highest challan frequency in the current dataset.</p>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>Vehicle Number</th>
                            <th>Total Cases</th>
                            <th>Total Fine</th>
                        </tr>
                        <?php foreach ($vehicles as $row) { ?>
                        <tr>
                            <td><span class="tag"><?php echo e($row['vehicle_no']); ?></span></td>
                            <td><?php echo e((string) $row['total_cases']); ?></td>
                            <td>₹<?php echo e((string) $row['total_fine']); ?></td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
                <div class="table-note">Use this view to explain repeat-offender analysis in your project presentation.</div>
            </div>
        </section>
    </main>
</div>

<script>
const statusData = [<?php echo $paidChallans; ?>, <?php echo $unpaidChallans; ?>];
const violationLabels = <?php echo json_encode($violationLabels); ?>;
const violationCounts = <?php echo json_encode($violationCounts); ?>;

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: ['Paid', 'Unpaid'],
        datasets: [{
            data: statusData,
            backgroundColor: ['#27ae60', '#ff7675'],
            borderWidth: 0
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

new Chart(document.getElementById('violationChart'), {
    type: 'bar',
    data: {
        labels: violationLabels,
        datasets: [{
            label: 'Cases',
            data: violationCounts,
            backgroundColor: ['#4f8df7', '#f5b041', '#27ae60', '#ff7675', '#9b59b6'],
            borderRadius: 10,
            borderSkipped: false
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { precision: 0 },
                grid: { color: 'rgba(96, 116, 137, 0.12)' }
            },
            x: { grid: { display: false } }
        }
    }
});

document.querySelectorAll('.tab-btn').forEach(function(button){
    button.addEventListener('click', function(){
        document.querySelectorAll('.tab-btn').forEach(function(item){
            item.classList.remove('active');
        });
        document.querySelectorAll('.tab-section').forEach(function(section){
            section.classList.remove('active');
        });

        button.classList.add('active');
        document.getElementById(button.dataset.tab).classList.add('active');
    });
});
</script>
</body>
</html>
