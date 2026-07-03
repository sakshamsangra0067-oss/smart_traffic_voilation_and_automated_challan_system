<?php
session_start();
include("db.php");

if (!isset($_SESSION['captcha'])) {
    $_SESSION['captcha'] = rand(1000, 9999);
}

$error = '';

if (isset($_POST['login'])) {
    $user = strtolower(trim($_POST['user'] ?? ''));
    $pass = $_POST['pass'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? 'user'));
    $role = in_array($role, ['admin', 'user'], true) ? $role : 'user';

    $captchaMatches = $captcha === (string) $_SESSION['captcha'];

    if (!$captchaMatches) {
        $error = 'Wrong CAPTCHA. Please enter the number shown below.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND role = ? LIMIT 1");
        $data = null;
        $passwordMatches = false;

        if ($stmt) {
            $stmt->bind_param("ss", $user, $role);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res ? $res->fetch_assoc() : null;

            if ($data) {
                $storedPassword = (string) ($data['password'] ?? '');
                $passwordMatches = password_verify($pass, $storedPassword) || $pass === $storedPassword;
            }

            $stmt->close();
        }

        if ($data && $passwordMatches) {
            session_regenerate_id(true);
            $_SESSION['id'] = $data['id'];
            $_SESSION['user'] = $data['email'];
            $_SESSION['role'] = $data['role'];

            if ($role === 'admin') {
                header("Location: admin_dashboard.php");
                exit();
            }

            header("Location: user_dashboard.php");
            exit();
        }

        $error = 'Invalid email, password, or selected role.';
    }

    $_SESSION['captcha'] = rand(1000, 9999);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Traffic System Login</title>
<style>
:root{
    --navy-950:#071423;
    --navy-900:#0d1b2a;
    --navy-800:#16324f;
    --blue-500:#3b82f6;
    --blue-400:#60a5fa;
    --cyan:#22d3ee;
    --panel:#ffffff;
    --panel-soft:#f4f8fd;
    --line:#d8e2ec;
    --ink:#162334;
    --muted:#66788a;
    --success:#15803d;
    --danger:#b33c2f;
    --shadow:0 24px 70px rgba(15, 23, 42, 0.16);
}

*{ box-sizing:border-box; }

body{
    margin:0;
    min-height:100vh;
    font-family:'Segoe UI',Tahoma,sans-serif;
    color:var(--ink);
    background:
        radial-gradient(circle at 82% 14%, rgba(59, 130, 246, 0.16), transparent 26%),
        linear-gradient(135deg, #edf4fb 0%, #dfe9f5 100%);
}

.page{
    min-height:100vh;
    display:grid;
    grid-template-columns:1.05fr 0.95fr;
}

.hero{
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:48px;
    color:#fff;
    background:
        linear-gradient(135deg, rgba(13, 27, 42, 0.96), rgba(29, 64, 96, 0.94)),
        radial-gradient(circle at 30% 20%, rgba(96, 165, 250, 0.28), transparent 24%);
}

.hero:before,
.hero:after{
    content:"";
    position:absolute;
    border-radius:999px;
    border:1px solid rgba(255,255,255,0.12);
}

.hero:before{
    width:420px;
    height:420px;
    left:-140px;
    top:-90px;
}

.hero:after{
    width:560px;
    height:560px;
    right:-240px;
    bottom:-260px;
}

.hero-card{
    position:relative;
    z-index:1;
    max-width:560px;
}

.badge{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    background:rgba(96, 165, 250, 0.16);
    border:1px solid rgba(96, 165, 250, 0.26);
    color:#dbeafe;
    font-size:12px;
    letter-spacing:0.08em;
    text-transform:uppercase;
}

.emblem{
    width:150px;
    height:150px;
    margin:34px 0 24px;
    display:grid;
    place-items:center;
    border-radius:36px;
    background:linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0.06));
    box-shadow:inset 0 0 0 1px rgba(255,255,255,0.16);
    font-size:68px;
}

.hero h1{
    margin:0;
    font-size:46px;
    line-height:1.05;
    letter-spacing:-0.04em;
}

.hero p{
    max-width:500px;
    margin:16px 0 0;
    color:rgba(255,255,255,0.74);
    font-size:16px;
    line-height:1.65;
}

.stats{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:12px;
    margin-top:30px;
}

.stat{
    padding:14px;
    border-radius:18px;
    background:rgba(255,255,255,0.08);
    border:1px solid rgba(255,255,255,0.10);
}

.stat strong{
    display:block;
    font-size:22px;
}

.stat span{
    display:block;
    margin-top:4px;
    color:rgba(255,255,255,0.68);
    font-size:12px;
}

.login-area{
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
}

.login-card{
    width:min(460px, 100%);
    background:rgba(255,255,255,0.96);
    border:1px solid rgba(20, 33, 47, 0.08);
    border-radius:28px;
    padding:26px;
    box-shadow:var(--shadow);
}

.login-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    margin-bottom:20px;
}

.login-head h2{
    margin:0 0 6px;
    font-size:28px;
}

.login-head p{
    margin:0;
    color:var(--muted);
    font-size:14px;
}

.status-dot{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 11px;
    border-radius:999px;
    background:rgba(21, 128, 61, 0.10);
    color:var(--success);
    font-size:12px;
    font-weight:700;
}

.toggle{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    margin-bottom:18px;
    padding:6px;
    border-radius:16px;
    background:#eef4fb;
    border:1px solid var(--line);
}

.toggle button{
    padding:11px;
    border:none;
    border-radius:12px;
    cursor:pointer;
    background:transparent;
    color:#53677c;
    font-weight:800;
    transition:0.2s ease;
}

.toggle button.active{
    background:linear-gradient(135deg, var(--blue-500), #2563eb);
    color:#fff;
    box-shadow:0 10px 24px rgba(37, 99, 235, 0.22);
}

.field{
    margin-bottom:13px;
}

.field label{
    display:flex;
    justify-content:space-between;
    gap:12px;
    margin-bottom:7px;
    color:#51657a;
    font-size:12px;
    font-weight:700;
}

.input-wrap{
    position:relative;
}

.input-wrap input{
    width:100%;
    padding:13px 44px 13px 14px;
    border:1px solid var(--line);
    border-radius:14px;
    background:#fff;
    color:var(--ink);
    font-size:14px;
    outline:none;
    transition:0.2s ease;
}

.input-wrap input:focus{
    border-color:var(--blue-400);
    box-shadow:0 0 0 4px rgba(59, 130, 246, 0.12);
}

.input-icon{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    color:#8ca0b3;
}

.password-toggle{
    position:absolute;
    right:8px;
    top:50%;
    transform:translateY(-50%);
    border:none;
    background:#eef4fb;
    border-radius:10px;
    padding:7px 9px;
    cursor:pointer;
    color:#35516c;
    font-weight:700;
}

.captcha-row{
    display:grid;
    grid-template-columns:130px 1fr;
    gap:10px;
    align-items:center;
}

.captcha-code{
    padding:13px;
    border-radius:14px;
    background:
        repeating-linear-gradient(135deg, #eef4fb 0 8px, #e7eff8 8px 16px);
    border:1px dashed #afbfd1;
    text-align:center;
    letter-spacing:8px;
    font-size:18px;
    font-weight:900;
    color:#13273d;
}

.error{
    margin:0 0 14px;
    padding:11px 12px;
    border-radius:14px;
    background:rgba(179, 60, 47, 0.10);
    color:var(--danger);
    border:1px solid rgba(179, 60, 47, 0.16);
    font-size:13px;
    font-weight:700;
}

.btn{
    width:100%;
    padding:13px 14px;
    border:none;
    border-radius:14px;
    background:linear-gradient(135deg, var(--navy-800), var(--navy-950));
    color:#fff;
    cursor:pointer;
    font-weight:900;
    letter-spacing:0.02em;
    box-shadow:0 14px 30px rgba(13, 27, 42, 0.18);
}

.btn:hover{
    transform:translateY(-1px);
}

.security-note{
    display:flex;
    gap:10px;
    margin-top:14px;
    color:var(--muted);
    font-size:12px;
    line-height:1.5;
}

@media (max-width: 920px){
    .page{
        grid-template-columns:1fr;
    }

    .hero{
        min-height:auto;
        padding:34px 24px;
    }

    .hero h1{
        font-size:34px;
    }

    .login-area{
        padding:24px;
    }
}

@media (max-width: 520px){
    .stats,
    .captcha-row{
        grid-template-columns:1fr;
    }
}
</style>
</head>
<body>
<div class="page">
    <section class="hero">
        <div class="hero-card">
            <div class="badge">Smart Traffic Management Project</div>
            <div class="emblem">🚦</div>
            <h1>Traffic Control & Challan Portal</h1>
            <p>College-level traffic management system with admin analytics, challan monitoring, vehicle risk tracking, and citizen payment access.</p>

            <div class="stats">
                <div class="stat">
                    <strong>50+</strong>
                    <span>Registered users</span>
                </div>
                <div class="stat">
                    <strong>50+</strong>
                    <span>Challan cases</span>
                </div>
                <div class="stat">
                    <strong>Live</strong>
                    <span>Admin dashboard</span>
                </div>
            </div>
        </div>
    </section>

    <section class="login-area">
        <div class="login-card">
            <div class="login-head">
                <div>
                    <h2>Login Portal</h2>
                    <p>Choose your role and access the traffic system.</p>
                </div>
                <div class="status-dot">● Online</div>
            </div>

            <?php if ($error !== '') { ?>
            <p class="error"><?php echo e($error); ?></p>
            <?php } ?>

            <div class="toggle">
                <button type="button" id="adminBtn" class="active" onclick="setRole('admin')">Admin</button>
                <button type="button" id="userBtn" onclick="setRole('user')">User</button>
            </div>

            <form method="POST" id="loginForm">
                <input type="hidden" name="role" id="role" value="admin">

                <div class="field">
                    <label for="user">Email address</label>
                    <div class="input-wrap">
                        <input id="user" type="email" name="user" placeholder="Enter email" autocomplete="username" required>
                        <span class="input-icon">✉</span>
                    </div>
                </div>

                <div class="field">
                    <label for="pass">Password</label>
                    <div class="input-wrap">
                        <input id="pass" type="password" name="pass" placeholder="Enter password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">Show</button>
                    </div>
                </div>

                <div class="field">
                    <label for="captcha">CAPTCHA verification</label>
                    <div class="captcha-row">
                        <div class="captcha-code" id="captchaText"><?php echo e((string) $_SESSION['captcha']); ?></div>
                        <div class="input-wrap">
                            <input id="captcha" type="text" name="captcha" placeholder="Enter CAPTCHA" required>
                            <span class="input-icon">✓</span>
                        </div>
                    </div>
                </div>

                <button class="btn" name="login">LOGIN SECURELY</button>
            </form>

            <div class="security-note">
                <span>🔒</span>
                <span>Secure login now verifies email, password, selected role, and CAPTCHA from database records only.</span>
            </div>
        </div>
    </section>
</div>

<script>
function setRole(role){
    document.getElementById("role").value = role;
    document.getElementById("adminBtn").classList.remove("active");
    document.getElementById("userBtn").classList.remove("active");

    if(role === "admin"){
        document.getElementById("adminBtn").classList.add("active");
    } else {
        document.getElementById("userBtn").classList.add("active");
    }
}

function togglePassword(){
    const pass = document.getElementById("pass");
    const button = document.querySelector(".password-toggle");
    const isHidden = pass.type === "password";
    pass.type = isHidden ? "text" : "password";
    button.textContent = isHidden ? "Hide" : "Show";
}

</script>
</body>
</html>
