<?php
include("db.php");
ensure_logged_in('admin');

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');

$users = [];
$userTableExists = false;
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($tableCheck instanceof mysqli_result && mysqli_num_rows($tableCheck) > 0) {
    $userTableExists = true;
}

if ($userTableExists) {
    $query = "
        SELECT
            u.id,
            u.email,
            u.role,
            COUNT(c.id) AS total_cases,
            COALESCE(SUM(CASE WHEN c.status = 'Paid' THEN 1 ELSE 0 END), 0) AS paid_cases,
            COALESCE(SUM(CASE WHEN c.status = 'Unpaid' THEN 1 ELSE 0 END), 0) AS unpaid_cases,
            COALESCE(SUM(c.fine_amount), 0) AS total_fine,
            COALESCE(SUM(CASE WHEN c.status = 'Unpaid' THEN c.fine_amount ELSE 0 END), 0) AS pending_amount,
            MAX(c.id) AS latest_challan
        FROM users u
        LEFT JOIN challans c ON c.user_id = u.id
        WHERE u.role = 'user'
    ";

    $params = [];
    $types = '';

    if ($search !== '') {
        $query .= " AND (u.email LIKE ? OR CAST(u.id AS CHAR) LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    $query .= " GROUP BY u.id, u.email, u.role";

    if ($statusFilter === 'pending') {
        $query .= " HAVING unpaid_cases > 0";
    } elseif ($statusFilter === 'clear') {
        $query .= " HAVING unpaid_cases = 0";
    }

    $query .= " ORDER BY unpaid_cases DESC, total_cases DESC, u.id ASC";

    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $stmt->close();
    }
}

$usingDemoData = false;
if (count($users) === 0) {
    $usingDemoData = true;
    $users = [
        ['id' => 11, 'email' => 'aarav.sharma@trafficdemo.com', 'role' => 'user', 'total_cases' => 3, 'paid_cases' => 1, 'unpaid_cases' => 2, 'total_fine' => 4500, 'pending_amount' => 3000, 'latest_challan' => 250],
        ['id' => 21, 'email' => 'ananya.sharma@trafficdemo.com', 'role' => 'user', 'total_cases' => 2, 'paid_cases' => 1, 'unpaid_cases' => 1, 'total_fine' => 2500, 'pending_amount' => 1000, 'latest_challan' => 232],
        ['id' => 31, 'email' => 'rohan.malhotra@trafficdemo.com', 'role' => 'user', 'total_cases' => 2, 'paid_cases' => 2, 'unpaid_cases' => 0, 'total_fine' => 3000, 'pending_amount' => 0, 'latest_challan' => 221],
        ['id' => 42, 'email' => 'riya.bajaj@trafficdemo.com', 'role' => 'user', 'total_cases' => 1, 'paid_cases' => 0, 'unpaid_cases' => 1, 'total_fine' => 1000, 'pending_amount' => 1000, 'latest_challan' => 232],
        ['id' => 53, 'email' => 'sameer.khan@trafficdemo.com', 'role' => 'user', 'total_cases' => 1, 'paid_cases' => 1, 'unpaid_cases' => 0, 'total_fine' => 1000, 'pending_amount' => 0, 'latest_challan' => 243],
    ];
}

$totalUsers = count($users);
$usersWithPending = 0;
$totalCases = 0;
$pendingAmount = 0;

foreach ($users as $user) {
    $totalCases += (int) $user['total_cases'];
    $pendingAmount += (float) $user['pending_amount'];
    if ((int) $user['unpaid_cases'] > 0) {
        $usersWithPending++;
    }
}

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$clearUsers = max(0, $totalUsers - $usersWithPending);
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management</title>
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
    --danger:#b33c2f;
    --warning:#b7791f;
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
    font-weight:800;
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
.profile-card,.stat-card,.panel{
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:18px;
    box-shadow:var(--shadow);
}
.profile-card{
    min-width:270px;
    padding:16px;
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
.toolbar a,.filter-form button,.create-form button{
    text-decoration:none;
    padding:10px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
    border:none;
    cursor:pointer;
}
.primary,.filter-form button{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}
.secondary{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
}
.cards{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:14px;
    margin-bottom:16px;
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
.label,.panel h3{
    margin:0 0 8px;
    font-size:13px;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#5d7084;
}
.value{
    margin-top:12px;
    font-size:32px;
    font-weight:800;
    color:#0d2238;
}
.trend,.panel p{
    color:var(--muted);
    font-size:13px;
}
.panel{ padding:18px; margin-bottom:16px; }
.filter-form,.create-form{
    display:grid;
    grid-template-columns:1fr 220px 90px 90px;
    gap:10px;
    align-items:end;
}
label{
    display:block;
    color:#51667a;
    font-size:12px;
    font-weight:800;
    margin-bottom:7px;
}
input,select{
    width:100%;
    padding:12px 14px;
    border:1px solid var(--line);
    border-radius:11px;
    background:#fff;
    color:var(--ink);
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
.badge{
    display:inline-flex;
    padding:7px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
}
.badge.pending{ background:#fff3cd; color:#8a5a00; }
.badge.clear{ background:#dcfce7; color:#166534; }
.badge.demo{ background:#eef4ff; color:#2459a8; }
.email{
    font-weight:800;
    color:#19324b;
}
.note{
    margin-top:14px;
    padding:11px 12px;
    border-radius:12px;
    background:#f7faff;
    border:1px solid var(--line);
    color:var(--muted);
    font-size:13px;
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
.create-form{
    grid-template-columns:1fr 1fr 140px;
}
.chart-wrap{
    height:230px;
    position:relative;
}
@media (max-width:1180px){
    .cards{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .filter-form,.create-form{ grid-template-columns:1fr 1fr; }
    .topbar{ flex-direction:column; }
    .profile-card{ min-width:0; width:100%; }
}
@media (max-width:920px){
    .shell{ flex-direction:column; }
    .sidebar{ width:100%; height:auto; position:relative; }
    .main{ padding:18px; }
}
@media (max-width:640px){
    .cards,.filter-form,.create-form{ grid-template-columns:1fr; }
    .title-block h1{ font-size:28px; }
    .toolbar{ flex-direction:column; }
}
</style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">U</div>
            <div>
                <small>Traffic Control System</small>
                <strong>User Records</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php" class="active"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>Citizen Desk</small>
            <strong>User monitoring available</strong>
            <div class="status-pill">Admin view</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Citizen Desk Module</div>
                <h1>User Management</h1>
                <p>Review registered citizens, their challan history, pending payment exposure, and account status without leaving admin mode.</p>
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
                <div class="label">Visible Users</div>
                <div class="value"><?php echo e((string) $totalUsers); ?></div>
                <div class="trend">Citizens in this filtered view.</div>
            </div>
            <div class="stat-card">
                <div class="label">Users With Pending</div>
                <div class="value"><?php echo e((string) $usersWithPending); ?></div>
                <div class="trend">Need payment follow-up.</div>
            </div>
            <div class="stat-card">
                <div class="label">Linked Cases</div>
                <div class="value"><?php echo e((string) $totalCases); ?></div>
                <div class="trend">Challans connected to users.</div>
            </div>
            <div class="stat-card">
                <div class="label">Pending Amount</div>
                <div class="value">₹<?php echo e((string) $pendingAmount); ?></div>
                <div class="trend">Unpaid citizen dues.</div>
            </div>
        </section>

        <?php if ($message !== '') { ?>
        <div class="notice">
            <?php
            $messages = [
                'created' => 'Citizen account created or updated successfully.',
                'invalid' => 'Please enter a valid email and a password with at least 4 characters.',
            ];
            echo e($messages[$message] ?? 'Action completed.');
            ?>
        </div>
        <?php } ?>

        <section class="panel">
            <h3>Create Citizen Account</h3>
            <p>Add a user account that can log in to the citizen dashboard and view assigned challans.</p>
            <form class="create-form" method="POST" action="save_user.php">
                <div>
                    <label for="new_email">Citizen email</label>
                    <input id="new_email" type="email" name="email" placeholder="citizen@example.com" required>
                </div>
                <div>
                    <label for="new_password">Password</label>
                    <input id="new_password" type="text" name="password" placeholder="Minimum 4 characters" required>
                </div>
                <button type="submit">Create User</button>
            </form>
        </section>

        <section class="panel">
            <h3>User Account Chart</h3>
            <p>Quick visual split between citizens who need payment follow-up and clear accounts.</p>
            <div class="chart-wrap"><canvas id="userChart"></canvas></div>
        </section>

        <section class="panel">
            <h3>Search and Filters</h3>
            <p>Filter citizens by email, user ID, or payment status.</p>
            <form class="filter-form" method="GET">
                <div>
                    <label for="search">Search user</label>
                    <input id="search" name="search" value="<?php echo e($search); ?>" placeholder="Search by email or user ID">
                </div>
                <div>
                    <label for="status">Payment status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All users</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Has pending cases</option>
                        <option value="clear" <?php echo $statusFilter === 'clear' ? 'selected' : ''; ?>>No pending cases</option>
                    </select>
                </div>
                <button type="submit">Apply</button>
                <a href="users.php" class="secondary" style="text-align:center;">Reset</a>
            </form>
        </section>

        <section class="panel">
            <h3>User Records</h3>
            <p>Admin-level citizen list generated from login accounts and challan history.</p>
            <div class="table-wrap">
                <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
                    <a href="export_csv.php?type=users" class="secondary" style="padding:10px 13px; border-radius:10px; text-decoration:none; font-weight:700;">Export CSV</a>
                </div>
                <table>
                    <tr>
                        <th>User ID</th>
                        <th>Email</th>
                        <th>Total Cases</th>
                        <th>Paid</th>
                        <th>Unpaid</th>
                        <th>Pending Amount</th>
                        <th>Latest Challan</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($users as $user) { ?>
                    <?php $hasPending = (int) $user['unpaid_cases'] > 0; ?>
                    <tr>
                        <td>#<?php echo e((string) $user['id']); ?></td>
                        <td class="email"><?php echo e($user['email']); ?></td>
                        <td><?php echo e((string) $user['total_cases']); ?></td>
                        <td><?php echo e((string) $user['paid_cases']); ?></td>
                        <td><?php echo e((string) $user['unpaid_cases']); ?></td>
                        <td>₹<?php echo e((string) $user['pending_amount']); ?></td>
                        <td><?php echo $user['latest_challan'] ? '#' . e((string) $user['latest_challan']) : 'No challan'; ?></td>
                        <td><span class="badge <?php echo $hasPending ? 'pending' : 'clear'; ?>"><?php echo $hasPending ? 'Follow up' : 'Clear'; ?></span></td>
                    </tr>
                    <?php } ?>
                </table>
            </div>
            <?php if ($usingDemoData) { ?>
            <div class="note">Demo users are displayed because no matching user records were found in the database.</div>
            <?php } else { ?>
            <div class="note">Use this module during evaluation to show that admin and citizen portals are separated correctly.</div>
            <?php } ?>
        </section>
    </main>
</div>
<script>
new Chart(document.getElementById('userChart'), {
    type: 'doughnut',
    data: {
        labels: ['Pending follow-up', 'Clear accounts'],
        datasets: [{
            data: [<?php echo $usersWithPending; ?>, <?php echo $clearUsers; ?>],
            backgroundColor: ['#f5b041', '#27ae60'],
            borderWidth: 0
        }]
    },
    options: {
        maintainAspectRatio: false,
        cutout: '64%',
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
</body>
</html>
