<?php
include("db.php");
ensure_logged_in('admin');

$search = trim($_GET['search'] ?? '');
$stateFilter = trim($_GET['state'] ?? 'all');

$query = "
    SELECT
        vehicle_no,
        COUNT(*) AS total_cases,
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) AS paid_cases,
        SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) AS unpaid_cases,
        COALESCE(SUM(fine_amount), 0) AS total_fine,
        MAX(id) AS latest_challan_id
    FROM challans
    WHERE 1=1
";

$params = [];
$types = '';

if ($search !== '') {
    $query .= " AND vehicle_no LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($stateFilter !== 'all') {
    $query .= " AND vehicle_no LIKE ?";
    $params[] = $stateFilter . '%';
    $types .= 's';
}

$query .= " GROUP BY vehicle_no ORDER BY latest_challan_id DESC";

$vehicles = [];
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
    $stmt->close();
}

$usingDemoData = false;
if (count($vehicles) === 0) {
    $usingDemoData = true;
    $vehicles = [
        ['vehicle_no' => 'DL01AB1234', 'total_cases' => 3, 'paid_cases' => 1, 'unpaid_cases' => 2, 'total_fine' => 3000, 'latest_challan_id' => 301],
        ['vehicle_no' => 'HR26JK9087', 'total_cases' => 2, 'paid_cases' => 1, 'unpaid_cases' => 1, 'total_fine' => 2500, 'latest_challan_id' => 302],
        ['vehicle_no' => 'UP16CC3003', 'total_cases' => 4, 'paid_cases' => 2, 'unpaid_cases' => 2, 'total_fine' => 6500, 'latest_challan_id' => 303],
        ['vehicle_no' => 'MH12EE5656', 'total_cases' => 1, 'paid_cases' => 1, 'unpaid_cases' => 0, 'total_fine' => 1000, 'latest_challan_id' => 304],
        ['vehicle_no' => 'KA03BB2468', 'total_cases' => 2, 'paid_cases' => 0, 'unpaid_cases' => 2, 'total_fine' => 4000, 'latest_challan_id' => 305],
        ['vehicle_no' => 'GJ01CC7070', 'total_cases' => 3, 'paid_cases' => 2, 'unpaid_cases' => 1, 'total_fine' => 5000, 'latest_challan_id' => 306],
        ['vehicle_no' => 'PB10AA5050', 'total_cases' => 2, 'paid_cases' => 1, 'unpaid_cases' => 1, 'total_fine' => 2500, 'latest_challan_id' => 307],
        ['vehicle_no' => 'RJ14DD4444', 'total_cases' => 1, 'paid_cases' => 0, 'unpaid_cases' => 1, 'total_fine' => 1500, 'latest_challan_id' => 308],
    ];
}

$totalVehicles = count($vehicles);
$highRiskVehicles = 0;
$totalVehicleCases = 0;
$totalVehicleFine = 0;

foreach ($vehicles as &$vehicle) {
    $vehicle['region_code'] = substr((string) $vehicle['vehicle_no'], 0, 2);
    $vehicle['risk_level'] = ((int) $vehicle['total_cases'] >= 3 || (int) $vehicle['unpaid_cases'] >= 2 || (float) $vehicle['total_fine'] >= 5000) ? 'High' : (((int) $vehicle['unpaid_cases'] >= 1) ? 'Medium' : 'Low');

    if ($vehicle['risk_level'] === 'High') {
        $highRiskVehicles++;
    }

    $totalVehicleCases += (int) $vehicle['total_cases'];
    $totalVehicleFine += (float) $vehicle['total_fine'];
}
unset($vehicle);

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$stateOptions = ['DL', 'HR', 'UP', 'MH', 'KA', 'GJ', 'PB', 'RJ'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle Management</title>
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
.profile-card strong{ display:block; font-size:16px; }
.profile-card small{ color:var(--muted); }
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
    margin-bottom:16px;
}
.stat-card,.filters,.table-panel{
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
.trend,.panel-head p,.table-note{
    color:var(--muted);
    font-size:13px;
}
.filters,.table-panel{ padding:18px; }
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
.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr auto;
    gap:12px;
}
.field{
    display:flex;
    flex-direction:column;
    gap:6px;
}
.field label{
    color:#5d7084;
    font-size:12px;
    font-weight:600;
}
.field input,.field select{
    width:100%;
    padding:11px 12px;
    border:1px solid var(--line);
    border-radius:12px;
    background:#fff;
    font-size:14px;
    color:var(--ink);
}
.actions{
    display:flex;
    align-items:flex-end;
    gap:10px;
}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-width:100px;
    padding:10px 14px;
    border:none;
    border-radius:10px;
    cursor:pointer;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
}
.btn-primary{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}
.btn-light{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
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
.plate-tag{
    display:inline-flex;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    background:#eef4ff;
    color:#2459a8;
}
.risk-badge{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0.06em;
}
.risk-low{ background:rgba(21,128,61,.12); color:var(--success); }
.risk-medium{ background:rgba(183,121,31,.12); color:var(--warning); }
.risk-high{ background:rgba(179,60,47,.12); color:var(--danger); }
.table-note{
    margin-top:14px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
}
@media (max-width:1180px){
    .cards{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .filter-grid{ grid-template-columns:1fr 1fr; }
    .actions{ grid-column:1 / -1; }
    .topbar{ flex-direction:column; }
    .profile-card{ min-width:0; width:100%; }
}
@media (max-width:920px){
    .shell{ flex-direction:column; }
    .sidebar{ width:100%; height:auto; position:relative; }
    .main{ padding:18px; }
}
@media (max-width:640px){
    .cards,.filter-grid{ grid-template-columns:1fr; }
    .title-block h1{ font-size:28px; }
    .toolbar,.actions{ flex-direction:column; align-items:stretch; }
}
</style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">🚗</div>
            <div>
                <small>Traffic Control System</small>
                <strong>Vehicle Registry</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php" class="active"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>Registry Status</small>
            <strong>Vehicle screening records available</strong>
            <div class="status-pill">● Live vehicle view</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Vehicle Management Module</div>
                <h1>Vehicle Registry and Risk View</h1>
                <p>Track registered vehicle numbers seen in challan records, review repeat offenders, and monitor pending fine exposure by vehicle.</p>
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
                    <a href="challans.php" class="secondary">Open Challans</a>
                </div>
            </div>
        </div>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Visible Vehicles</div>
                <div class="value"><?php echo e((string) $totalVehicles); ?></div>
                <div class="trend">Unique vehicle numbers in this view.</div>
            </div>
            <div class="stat-card">
                <div class="label">High Risk Vehicles</div>
                <div class="value"><?php echo e((string) $highRiskVehicles); ?></div>
                <div class="trend">Vehicles with repeated or pending cases.</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Cases</div>
                <div class="value"><?php echo e((string) $totalVehicleCases); ?></div>
                <div class="trend">Combined challans across visible vehicles.</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Fine Exposure</div>
                <div class="value">₹<?php echo e((string) $totalVehicleFine); ?></div>
                <div class="trend">Overall fine value in this vehicle list.</div>
            </div>
        </section>

        <section class="filters">
            <div class="panel-head">
                <div>
                    <h3>Search and Filters</h3>
                    <p>Filter vehicles by plate number or state/region code to review relevant registry entries quickly.</p>
                </div>
            </div>

            <form method="GET" class="filter-grid">
                <div class="field">
                    <label for="search">Search Vehicle Number</label>
                    <input id="search" type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by vehicle number">
                </div>

                <div class="field">
                    <label for="state">State Code</label>
                    <select id="state" name="state">
                        <option value="all" <?php echo $stateFilter === 'all' ? 'selected' : ''; ?>>All states</option>
                        <?php foreach ($stateOptions as $option) { ?>
                        <option value="<?php echo e($option); ?>" <?php echo $stateFilter === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="vechile.php" class="btn btn-light">Reset</a>
                </div>
            </form>
        </section>

        <section class="table-panel" style="margin-top:16px;">
            <div class="panel-head">
                <div>
                    <h3>Vehicle Records</h3>
                    <p>Vehicle-level administrative view built from the current challan history.</p>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Vehicle No</th>
                        <th>Region</th>
                        <th>Total Cases</th>
                        <th>Paid</th>
                        <th>Unpaid</th>
                        <th>Total Fine</th>
                        <th>Risk Level</th>
                        <th>Latest Challan</th>
                    </tr>
                    <?php foreach ($vehicles as $row) { ?>
                    <?php $riskClass = strtolower((string) $row['risk_level']); ?>
                    <tr>
                        <td><span class="plate-tag"><?php echo e($row['vehicle_no']); ?></span></td>
                        <td><?php echo e($row['region_code']); ?></td>
                        <td><?php echo e((string) $row['total_cases']); ?></td>
                        <td><?php echo e((string) $row['paid_cases']); ?></td>
                        <td><?php echo e((string) $row['unpaid_cases']); ?></td>
                        <td>₹<?php echo e((string) $row['total_fine']); ?></td>
                        <td><span class="risk-badge risk-<?php echo e($riskClass); ?>"><?php echo e($row['risk_level']); ?></span></td>
                        <td>#<?php echo e((string) $row['latest_challan_id']); ?></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>

            <div class="table-note">
                <?php if ($usingDemoData) { ?>
                Demo vehicle records are being shown because no grouped vehicle data was found yet. Once challans are available, this page will build the registry automatically.
                <?php } else { ?>
                This vehicle registry is generated from the challan dataset, so it stays aligned with your real violation and payment records.
                <?php } ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
