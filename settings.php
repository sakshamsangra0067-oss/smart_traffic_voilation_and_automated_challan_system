<?php
include("db.php");
ensure_logged_in('admin');

$adminEmail = $_SESSION['user'] ?? 'admin@traffic.com';
$adminInitials = strtoupper(substr($adminEmail, 0, 1));
$settingsMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    $newEmail = strtolower(trim($_POST['email'] ?? ''));
    $newPassword = trim($_POST['password'] ?? '');
    $adminId = (int) ($_SESSION['id'] ?? 0);

    if ($adminId > 0 && filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        if ($newPassword !== '') {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $profileStmt = $conn->prepare("UPDATE users SET email = ?, password = ? WHERE id = ? AND role = 'admin'");
            if ($profileStmt) {
                $profileStmt->bind_param("ssi", $newEmail, $passwordHash, $adminId);
            }
        } else {
            $profileStmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ? AND role = 'admin'");
            if ($profileStmt) {
                $profileStmt->bind_param("si", $newEmail, $adminId);
            }
        }

        if ($profileStmt) {
            $profileStmt->execute();
            $profileStmt->close();
            $_SESSION['user'] = $newEmail;
            $adminEmail = $newEmail;
            $adminInitials = strtoupper(substr($adminEmail, 0, 1));
            $settingsMessage = 'Admin profile updated successfully.';
        }
    } else {
        $settingsMessage = 'Please enter a valid admin email.';
    }
}

$totalChallans = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM challans"))['c'];
$paidChallans = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM challans WHERE status='Paid'"))['c'];
$unpaidChallans = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM challans WHERE status='Unpaid'"))['c'];
$totalUsers = 0;

$userCheck = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if ($userCheck instanceof mysqli_result && mysqli_num_rows($userCheck) > 0) {
    $totalUsers = (int) mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users"))['c'];
}

$collectionRate = $totalChallans > 0 ? round(($paidChallans / $totalChallans) * 100) : 0;
$lastSync = date('d M Y, h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Settings</title>
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
.profile-card,.stat-card,.panel,.setting-card{
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
.toolbar a,.toolbar button,.action-btn{
    text-decoration:none;
    padding:10px 13px;
    border-radius:10px;
    font-size:13px;
    font-weight:700;
    border:none;
    cursor:pointer;
}
.primary,.action-btn.primary{
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
}
.secondary,.action-btn.secondary{
    background:#fff;
    color:var(--navy-800);
    border:1px solid var(--line);
}
.cards,.settings-grid,.quick-grid{
    display:grid;
    gap:14px;
}
.cards{
    grid-template-columns:repeat(4,minmax(0,1fr));
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
.label,.panel h3,.setting-card small{
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
.trend,.panel p,.setting-card p{
    color:var(--muted);
    font-size:13px;
}
.settings-grid{
    grid-template-columns:1.1fr 0.9fr;
}
.panel{ padding:18px; }
.setting-card{
    padding:16px;
    margin-bottom:12px;
}
.setting-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    padding:13px 0;
    border-bottom:1px solid #e9eef3;
}
.setting-row:last-child{ border-bottom:none; }
.toggle{
    width:54px;
    height:30px;
    border-radius:999px;
    border:none;
    background:#cbd5df;
    padding:4px;
    cursor:pointer;
    transition:0.2s ease;
}
.toggle:before{
    content:"";
    display:block;
    width:22px;
    height:22px;
    border-radius:50%;
    background:#fff;
    box-shadow:0 2px 8px rgba(15,23,42,0.2);
    transition:0.2s ease;
}
.toggle.active{ background:var(--success); }
.toggle.active:before{ transform:translateX(24px); }
.quick-grid{
    grid-template-columns:repeat(2,minmax(0,1fr));
}
.quick-link{
    display:block;
    padding:15px;
    border-radius:14px;
    background:var(--panel-soft);
    border:1px solid var(--line);
    color:var(--ink);
    text-decoration:none;
    transition:0.2s ease;
}
.quick-link:hover{
    transform:translateY(-2px);
    border-color:rgba(59,130,246,0.42);
}
.quick-link strong{ display:block; margin-bottom:6px; }
.credential-box{
    display:grid;
    gap:10px;
    margin-top:10px;
}
.credential{
    padding:12px;
    border-radius:12px;
    background:#f7fafc;
    border:1px solid var(--line);
    font-size:13px;
}
.credential code{
    display:block;
    margin-top:5px;
    color:#0d2238;
    font-weight:800;
}
.toast{
    position:fixed;
    right:24px;
    bottom:24px;
    padding:13px 16px;
    border-radius:12px;
    background:#102033;
    color:#fff;
    box-shadow:var(--shadow);
    opacity:0;
    transform:translateY(12px);
    pointer-events:none;
    transition:0.2s ease;
}
.toast.show{
    opacity:1;
    transform:translateY(0);
}
@media (max-width:1180px){
    .cards{ grid-template-columns:repeat(2,minmax(0,1fr)); }
    .settings-grid{ grid-template-columns:1fr; }
    .topbar{ flex-direction:column; }
    .profile-card{ min-width:0; width:100%; }
}
@media (max-width:920px){
    .shell{ flex-direction:column; }
    .sidebar{ width:100%; height:auto; position:relative; }
    .main{ padding:18px; }
}
@media (max-width:640px){
    .cards,.quick-grid{ grid-template-columns:1fr; }
    .title-block h1{ font-size:28px; }
    .toolbar{ flex-direction:column; }
}
</style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="seal">
            <div class="seal-mark">S</div>
            <div>
                <small>Traffic Control System</small>
                <strong>Settings</strong>
            </div>
        </div>

        <div class="nav-label">Command Menu</div>
        <a href="admin_dashboard.php"><strong>Dashboard</strong><span>Live</span></a>
        <a href="challans.php"><strong>Challans</strong><span>Records</span></a>
        <a href="users.php"><strong>Users</strong><span>Citizen Desk</span></a>
        <a href="vechile.php"><strong>Vehicles</strong><span>Registry</span></a>
        <a href="report.php"><strong>Reports</strong><span>Analytics</span></a>
        <a href="settings.php" class="active"><strong>Settings</strong><span>Control</span></a>

        <div class="status-note">
            <small>System Status</small>
            <strong>Configuration ready</strong>
            <div class="status-pill">Active</div>
        </div>

        <a href="logout.php" class="logout"><strong>Logout</strong></a>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="title-block">
                <div class="eyebrow">Admin Control Panel</div>
                <h1>System Settings</h1>
                <p>Manage secure access, project controls, and quick navigation from one clean settings screen.</p>
            </div>

            <div class="profile-card">
                <div class="profile-row">
                    <div class="avatar"><?php echo e($adminInitials); ?></div>
                    <div>
                        <strong>Administrator</strong>
                        <small><?php echo e($adminEmail); ?></small><br>
                        <small>Last sync: <?php echo e($lastSync); ?></small>
                    </div>
                </div>
                <div class="toolbar">
                    <a href="admin_dashboard.php" class="primary">Back to Dashboard</a>
                    <button type="button" class="secondary" onclick="showToast('Settings checked successfully')">Check Status</button>
                </div>
            </div>
        </div>

        <section class="cards">
            <div class="stat-card">
                <div class="label">Total Challans</div>
                <div class="value"><?php echo e((string) $totalChallans); ?></div>
                <div class="trend">Records available in system.</div>
            </div>
            <div class="stat-card">
                <div class="label">Collection Rate</div>
                <div class="value"><?php echo e((string) $collectionRate); ?>%</div>
                <div class="trend">Paid challans compared to total.</div>
            </div>
            <div class="stat-card">
                <div class="label">Unpaid Cases</div>
                <div class="value"><?php echo e((string) $unpaidChallans); ?></div>
                <div class="trend">Needs follow-up action.</div>
            </div>
            <div class="stat-card">
                <div class="label">Registered Users</div>
                <div class="value"><?php echo e((string) $totalUsers); ?></div>
                <div class="trend">Citizen records in database.</div>
            </div>
        </section>

        <?php if ($settingsMessage !== '') { ?>
        <div class="setting-card" style="margin-bottom:16px; border-color:rgba(21,128,61,0.18); color:var(--success); font-weight:800;">
            <?php echo e($settingsMessage); ?>
        </div>
        <?php } ?>

        <section class="settings-grid">
            <div class="panel">
                <h3>Project Controls</h3>
                <p>These switches are interactive for your final-year presentation and show realistic admin configuration behavior.</p>

                <div class="setting-row">
                    <div>
                        <strong>Database-only login</strong>
                        <p>Require real stored accounts, selected role, password, and CAPTCHA verification.</p>
                    </div>
                    <button class="toggle active" type="button" aria-label="Toggle database-only login"></button>
                </div>

                <div class="setting-row">
                    <div>
                        <strong>Payment monitoring</strong>
                        <p>Highlight unpaid challans and pending recovery cases.</p>
                    </div>
                    <button class="toggle active" type="button" aria-label="Toggle payment monitoring"></button>
                </div>

                <div class="setting-row">
                    <div>
                        <strong>Report print mode</strong>
                        <p>Allow reports to be printed for college project documentation.</p>
                    </div>
                    <button class="toggle active" type="button" aria-label="Toggle report print mode"></button>
                </div>

                <div class="setting-row">
                    <div>
                        <strong>Strict review mode</strong>
                        <p>Use this when presenting admin control and system governance.</p>
                    </div>
                    <button class="toggle" type="button" aria-label="Toggle strict review mode"></button>
                </div>
            </div>

            <div>
                <div class="setting-card">
                    <small>Access Policy</small>
                    <strong>Demo shortcuts removed</strong>
                    <div class="credential-box">
                        <div class="credential">
                            Admin access
                            <code>Use account saved in users table</code>
                        </div>
                        <div class="credential">
                            Citizen access
                            <code>Use registered user account only</code>
                        </div>
                    </div>
                </div>

                <div class="setting-card">
                    <small>Admin Profile</small>
                    <strong>Update email or password</strong>
                    <form method="POST" style="display:grid; gap:10px; margin-top:12px;">
                        <input type="hidden" name="action" value="profile">
                        <input type="email" name="email" value="<?php echo e($adminEmail); ?>" required style="padding:12px; border:1px solid var(--line); border-radius:12px;">
                        <input type="password" name="password" placeholder="New password (optional)" style="padding:12px; border:1px solid var(--line); border-radius:12px;">
                        <button class="action-btn primary" type="submit">Save Profile</button>
                    </form>
                </div>

                <div class="setting-card">
                    <small>Quick Actions</small>
                    <strong>Jump to important modules</strong>
                    <div class="quick-grid" style="margin-top:12px;">
                        <a class="quick-link" href="challans.php"><strong>Manage Challans</strong><span>Search, filter, and update cases.</span></a>
                        <a class="quick-link" href="vechile.php"><strong>Vehicle Registry</strong><span>Review vehicle risk levels.</span></a>
                        <a class="quick-link" href="report.php"><strong>Open Reports</strong><span>View analytics in same tab.</span></a>
                        <a class="quick-link" href="users.php"><strong>User Records</strong><span>Check citizen-facing records.</span></a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="toast" class="toast">Updated</div>

<script>
const toast = document.getElementById('toast');

function showToast(message){
    toast.textContent = message;
    toast.classList.add('show');
    setTimeout(function(){
        toast.classList.remove('show');
    }, 1800);
}

document.querySelectorAll('.toggle').forEach(function(button){
    button.addEventListener('click', function(){
        button.classList.toggle('active');
        showToast(button.classList.contains('active') ? 'Setting enabled' : 'Setting disabled');
    });
});
</script>
</body>
</html>
