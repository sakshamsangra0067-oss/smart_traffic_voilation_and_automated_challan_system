<?php
include("db.php");
ensure_logged_in('admin');

$totals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) total_challans,
           SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) paid_challans,
           SUM(CASE WHEN status='Unpaid' THEN 1 ELSE 0 END) unpaid_challans,
           COALESCE(SUM(CASE WHEN status='Paid' THEN fine_amount ELSE 0 END),0) revenue
    FROM challans
"));

$summary = mysqli_query($conn, "
    SELECT violation, COUNT(*) total_cases, COALESCE(SUM(fine_amount),0) total_fine
    FROM challans
    GROUP BY violation
    ORDER BY total_cases DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Printable Traffic Report</title>
<style>
body{ margin:0; font-family:'Segoe UI',Tahoma,sans-serif; background:#f3f6fa; color:#182433; padding:28px; }
.sheet{ max-width:900px; margin:0 auto; background:#fff; border:1px solid #d8e2ec; border-radius:18px; padding:28px; box-shadow:0 16px 38px rgba(15,23,42,.10); }
.head{ display:flex; justify-content:space-between; gap:18px; border-bottom:2px solid #13273d; padding-bottom:18px; margin-bottom:18px; }
h1{ margin:0 0 6px; }
.grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:18px 0; }
.card{ border:1px solid #e2e8f0; border-radius:14px; padding:14px; background:#f8fafc; }
.card small{ display:block; color:#64748b; text-transform:uppercase; letter-spacing:.08em; font-size:11px; }
.card strong{ display:block; margin-top:8px; font-size:24px; }
table{ width:100%; border-collapse:collapse; margin-top:18px; }
th,td{ padding:12px; border-bottom:1px solid #e2e8f0; text-align:left; }
th{ background:#f8fafc; text-transform:uppercase; color:#64748b; font-size:12px; }
.actions{ display:flex; gap:10px; margin-top:20px; }
.btn{ padding:11px 14px; border-radius:10px; text-decoration:none; border:none; cursor:pointer; font-weight:800; }
.primary{ background:#13273d; color:#fff; }
.secondary{ border:1px solid #d8e2ec; color:#13273d; background:#fff; }
@media print{ body{ background:#fff; padding:0; } .sheet{ box-shadow:none; border:none; border-radius:0; } .actions{ display:none; } }
@media(max-width:720px){ .head,.actions{ flex-direction:column; } .grid{ grid-template-columns:1fr 1fr; } }
</style>
</head>
<body>
<main class="sheet">
    <div class="head">
        <div>
            <h1>Traffic Challan Report</h1>
            <p>Generated on <?php echo e(date('d M Y, h:i A')); ?></p>
        </div>
        <strong>Traffic Control System</strong>
    </div>

    <section class="grid">
        <div class="card"><small>Total</small><strong><?php echo e((string) ($totals['total_challans'] ?? 0)); ?></strong></div>
        <div class="card"><small>Paid</small><strong><?php echo e((string) ($totals['paid_challans'] ?? 0)); ?></strong></div>
        <div class="card"><small>Unpaid</small><strong><?php echo e((string) ($totals['unpaid_challans'] ?? 0)); ?></strong></div>
        <div class="card"><small>Revenue</small><strong>₹<?php echo e((string) ($totals['revenue'] ?? 0)); ?></strong></div>
    </section>

    <table>
        <tr><th>Violation</th><th>Total Cases</th><th>Total Fine</th></tr>
        <?php if ($summary instanceof mysqli_result) { ?>
        <?php while ($row = mysqli_fetch_assoc($summary)) { ?>
        <tr><td><?php echo e($row['violation']); ?></td><td><?php echo e((string) $row['total_cases']); ?></td><td>₹<?php echo e((string) $row['total_fine']); ?></td></tr>
        <?php } ?>
        <?php } ?>
    </table>

    <div class="actions">
        <button class="btn primary" onclick="window.print()">Print / Save PDF</button>
        <a class="btn secondary" href="report.php">Back to Reports</a>
    </div>
</main>
</body>
</html>
