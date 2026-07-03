<?php
include("db.php");
ensure_logged_in('admin');

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$violationFilter = trim($_GET['violation'] ?? 'all');

$baseQuery = "SELECT * FROM challans WHERE 1=1";
$params = [];
$types = '';

if ($search !== '') {
    $baseQuery .= " AND (vehicle_no LIKE ? OR CAST(id AS CHAR) LIKE ? OR violation LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($statusFilter !== 'all') {
    $baseQuery .= " AND status = ?";
    $params[] = ucfirst(strtolower($statusFilter));
    $types .= 's';
}

if ($violationFilter !== 'all') {
    $baseQuery .= " AND violation = ?";
    $params[] = $violationFilter;
    $types .= 's';
}

$baseQuery .= " ORDER BY id DESC";

$challans = [];
$stmt = $conn->prepare($baseQuery);

if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $challans[] = $row;
    }
    $stmt->close();
}

$usingDemoData = false;
if (count($challans) === 0) {
    $usingDemoData = true;
    $challans = [
        ['id' => 250, 'vehicle_no' => 'PB10BB4040', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 249, 'vehicle_no' => 'PB10AA5050', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 248, 'vehicle_no' => 'GJ01DD6060', 'violation' => 'Mobile Usage', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 247, 'vehicle_no' => 'GJ01CC7070', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Paid'],
        ['id' => 246, 'vehicle_no' => 'GJ01BB8080', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Unpaid'],
        ['id' => 245, 'vehicle_no' => 'GJ01AA9090', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 244, 'vehicle_no' => 'KA03FF6802', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 243, 'vehicle_no' => 'KA03EE5791', 'violation' => 'Mobile Usage', 'fine_amount' => 1000, 'status' => 'Paid'],
        ['id' => 242, 'vehicle_no' => 'KA03DD4680', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Unpaid'],
        ['id' => 241, 'vehicle_no' => 'KA03CC3579', 'violation' => 'Signal Jump', 'fine_amount' => 1500, 'status' => 'Paid'],
        ['id' => 240, 'vehicle_no' => 'KA03BB2468', 'violation' => 'Overspeed', 'fine_amount' => 2000, 'status' => 'Unpaid'],
        ['id' => 239, 'vehicle_no' => 'KA03AA1357', 'violation' => 'Helmet', 'fine_amount' => 1000, 'status' => 'Paid'],
    ];
}

$totalChallans = count($challans);
$paidCount = 0;
$unpaidCount = 0;
$totalAmount = 0;

foreach ($challans as $challan) {
    $totalAmount += (float) $challan['fine_amount'];
    if (strtolower((string) $challan['status']) === 'paid') {
        $paidCount++;
    } else {
        $unpaidCount++;
    }
}

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$violationOptions = ['Helmet', 'Overspeed', 'Signal Jump', 'Mobile Usage'];
$message = $_GET['message'] ?? '';

$userOptions = [];
$userResult = mysqli_query($conn, "SELECT id, email FROM users WHERE role = 'user' ORDER BY email ASC");
if ($userResult instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($userResult)) {
        $userOptions[] = $row;
    }
}

$editChallan = null;
$editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
if ($editId > 0) {
    $editStmt = $conn->prepare("SELECT id, user_id, vehicle_no, violation, fine_amount, status FROM challans WHERE id = ? LIMIT 1");
    if ($editStmt) {
        $editStmt->bind_param("i", $editId);
        $editStmt->execute();
        $editChallan = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Challan Management</title>
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

.cards,
.filters,
.table-panel{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}

.cards{
    display:grid;
    grid-template-columns:repeat(4, minmax(0, 1fr));
    gap:14px;
    background:transparent;
    border:none;
    box-shadow:none;
    margin-bottom:16px;
}

.stat-card{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
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

.stat-card .label{
    margin:0 0 8px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}

.stat-card .value{
    margin-top:12px;
    font-size:34px;
    font-weight:700;
    color:#0d2238;
}

.stat-card .trend{
    color:var(--muted);
    font-size:13px;
}

.filters,
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

.panel-head p{
    margin:0;
    color:var(--muted);
    font-size:13px;
}

.filter-grid{
    display:grid;
    grid-template-columns:2fr 1fr 1fr auto;
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

.field input,
.field select{
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

.table-note{
    margin-top:14px;
    padding:10px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
    color:var(--muted);
    font-size:13px;
}

.form-panel{
    margin-bottom:16px;
}

.form-grid{
    display:grid;
    grid-template-columns:1.2fr 1fr 1fr 1fr 1fr auto;
    gap:12px;
    align-items:end;
}

.notice{
    margin-bottom:14px;
    padding:11px 12px;
    border-radius:12px;
    background:#eef8f0;
    border:1px solid rgba(21,128,61,0.18);
    color:var(--success);
    font-size:13px;
    font-weight:700;
}

.row-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}

.danger-btn{
    background:rgba(179,60,47,0.10);
    color:var(--danger);
    border:1px solid rgba(179,60,47,0.18);
}

@media (max-width: 1180px){
    .cards{
        grid-template-columns:repeat(2, minmax(0, 1fr));
    }

    .filter-grid,
    .form-grid{
        grid-template-columns:1fr 1fr;
    }

    .actions{
        grid-column:1 / -1;
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
    .cards,
    .filter-grid,
    .form-grid{
        grid-template-columns:1fr;
    }

    .title-block h1{
        font-size:28px;
    }

    .toolbar,
    .actions{
        flex-direction:column;
        align-items:stretch;
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
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php" class="active"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>System Status</small>
            <strong>Challan management is active</strong>
            <div class="status-pill">● Admin access enabled</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Challan Management Module</div>
                <h1>Manage Challans and Cases</h1>
                <p>Search, review, and monitor challan records with quick filters and a table layout that matches the main dashboard.</p>
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
                    <a href="report.php" class="secondary">View Reports</a>
                </div>
            </div>
        </div>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Visible Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">Records matching current filters.</div>
            </div>
            <div class="stat-card">
                <div class="label">Paid Cases</div>
                <div class="value"><?php echo e((string) $paidCount); ?></div>
                <div class="trend">Settled challans in this view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Unpaid Cases</div>
                <div class="value"><?php echo e((string) $unpaidCount); ?></div>
                <div class="trend">Pending challans in this view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Fine Value</div>
                <div class="value">₹<?php echo e((string) $totalAmount); ?></div>
                <div class="trend">Combined amount of visible challans.</div>
            </div>
        </section>

        <?php if ($message !== '') { ?>
        <div class="notice">
            <?php
            $messages = [
                'created' => 'Challan created successfully.',
                'updated' => 'Challan updated successfully.',
                'deleted' => 'Challan deleted successfully.',
                'invalid' => 'Please check the entered challan details.',
            ];
            echo e($messages[$message] ?? 'Action completed.');
            ?>
        </div>
        <?php } ?>

        <section class="filters form-panel">
            <div class="panel-head">
                <div>
                    <h3><?php echo $editChallan ? 'Edit Challan' : 'Create New Challan'; ?></h3>
                    <p>Add a real challan record or update an existing case from the admin panel.</p>
                </div>
            </div>

            <form method="POST" action="save_challan.php" class="form-grid">
                <input type="hidden" name="id" value="<?php echo e((string) ($editChallan['id'] ?? 0)); ?>">

                <div class="field">
                    <label for="user_id">Citizen</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select user</option>
                        <?php foreach ($userOptions as $userOption) { ?>
                        <option value="<?php echo e((string) $userOption['id']); ?>" <?php echo (int) ($editChallan['user_id'] ?? 0) === (int) $userOption['id'] ? 'selected' : ''; ?>>
                            <?php echo e($userOption['email']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field">
                    <label for="vehicle_no">Vehicle No</label>
                    <input id="vehicle_no" name="vehicle_no" value="<?php echo e($editChallan['vehicle_no'] ?? ''); ?>" placeholder="DL01AB1234" required>
                </div>

                <div class="field">
                    <label for="new_violation">Violation</label>
                    <select id="new_violation" name="violation" required>
                        <?php foreach ($violationOptions as $option) { ?>
                        <option value="<?php echo e($option); ?>" <?php echo ($editChallan['violation'] ?? '') === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="field">
                    <label for="fine_amount">Fine</label>
                    <input id="fine_amount" type="number" min="1" step="1" name="fine_amount" value="<?php echo e((string) ($editChallan['fine_amount'] ?? '1000')); ?>" required>
                </div>

                <div class="field">
                    <label for="new_status">Status</label>
                    <select id="new_status" name="status">
                        <option value="Unpaid" <?php echo ($editChallan['status'] ?? 'Unpaid') === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="Paid" <?php echo ($editChallan['status'] ?? '') === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary"><?php echo $editChallan ? 'Update' : 'Create'; ?></button>
                    <?php if ($editChallan) { ?>
                    <a href="challans.php" class="btn btn-light">Cancel</a>
                    <?php } ?>
                </div>
            </form>
        </section>

        <section class="filters">
            <div class="panel-head">
                <div>
                    <h3>Search and Filters</h3>
                    <p>Use the controls below to find challans by vehicle number, ID, violation, or payment status.</p>
                </div>
            </div>

            <form method="GET" class="filter-grid">
                <div class="field">
                    <label for="search">Search</label>
                    <input id="search" type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search by challan ID, vehicle no, or violation">
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All statuses</option>
                        <option value="Paid" <?php echo strtolower($statusFilter) === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Unpaid" <?php echo strtolower($statusFilter) === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                    </select>
                </div>

                <div class="field">
                    <label for="violation">Violation</label>
                    <select id="violation" name="violation">
                        <option value="all" <?php echo $violationFilter === 'all' ? 'selected' : ''; ?>>All violations</option>
                        <?php foreach ($violationOptions as $option) { ?>
                        <option value="<?php echo e($option); ?>" <?php echo $violationFilter === $option ? 'selected' : ''; ?>><?php echo e($option); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="challans.php" class="btn btn-light">Reset</a>
                </div>
            </form>
        </section>

        <section class="table-panel" style="margin-top:16px;">
            <div class="panel-head">
                <div>
                    <h3>Challan Records</h3>
                    <p>Full-page administrative view for challan monitoring and status tracking.</p>
                </div>
                <a href="export_csv.php?type=challans" class="btn btn-light">Export CSV</a>
            </div>

            <div class="table-wrap">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Vehicle No</th>
                        <th>Violation</th>
                        <th>Fine Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($challans as $row) { ?>
                    <?php $isPaid = strtolower((string) $row['status']) === 'paid'; ?>
                    <tr>
                        <td><?php echo e($row['id']); ?></td>
                        <td><?php echo e($row['vehicle_no']); ?></td>
                        <td><span class="violation-tag"><?php echo e($row['violation']); ?></span></td>
                        <td>₹<?php echo e($row['fine_amount']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $isPaid ? 'status-paid' : 'status-unpaid'; ?>">
                                <?php echo e($row['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="challans.php?edit_id=<?php echo e((string) $row['id']); ?>" class="btn btn-light">Edit</a>
                                <?php if ($isPaid) { ?>
                                <a href="receipt.php?id=<?php echo e((string) $row['id']); ?>" class="btn btn-primary">Receipt</a>
                                <?php } else { ?>
                                <a href="mark_paid.php?id=<?php echo e((string) $row['id']); ?>" class="btn btn-primary">Mark Paid</a>
                                <?php } ?>
                                <form method="POST" action="delete_challan.php" onsubmit="return confirm('Delete this challan?');">
                                    <input type="hidden" name="id" value="<?php echo e((string) $row['id']); ?>">
                                    <button type="submit" class="btn danger-btn">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </table>
            </div>

            <div class="table-note">
                <?php if ($usingDemoData) { ?>
                Demo challans are currently shown because no matching records were found in the database. Import your sample SQL to replace them automatically.
                <?php } else { ?>
                This table is connected to the current challan dataset and updates based on your search and filter selection.
                <?php } ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
