<?php
include("db.php");
ensure_logged_in('admin');

$type = $_GET['type'] ?? 'challans';
$allowed = ['challans', 'users', 'vehicles', 'reports'];
if (!in_array($type, $allowed, true)) {
    $type = 'challans';
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');

if ($type === 'users') {
    fputcsv($out, ['User ID', 'Email', 'Role', 'Total Cases', 'Paid Cases', 'Unpaid Cases', 'Pending Amount']);
    $result = mysqli_query($conn, "
        SELECT u.id, u.email, u.role,
               COUNT(c.id) total_cases,
               SUM(CASE WHEN c.status='Paid' THEN 1 ELSE 0 END) paid_cases,
               SUM(CASE WHEN c.status='Unpaid' THEN 1 ELSE 0 END) unpaid_cases,
               COALESCE(SUM(CASE WHEN c.status='Unpaid' THEN c.fine_amount ELSE 0 END),0) pending_amount
        FROM users u
        LEFT JOIN challans c ON c.user_id = u.id
        WHERE u.role = 'user'
        GROUP BY u.id, u.email, u.role
        ORDER BY u.id ASC
    ");
} elseif ($type === 'vehicles') {
    fputcsv($out, ['Vehicle No', 'Total Cases', 'Paid Cases', 'Unpaid Cases', 'Total Fine', 'Risk Level']);
    $result = mysqli_query($conn, "
        SELECT vehicle_no,
               COUNT(*) total_cases,
               SUM(CASE WHEN status='Paid' THEN 1 ELSE 0 END) paid_cases,
               SUM(CASE WHEN status='Unpaid' THEN 1 ELSE 0 END) unpaid_cases,
               COALESCE(SUM(fine_amount),0) total_fine
        FROM challans
        GROUP BY vehicle_no
        ORDER BY total_cases DESC
    ");
} elseif ($type === 'reports') {
    fputcsv($out, ['Violation', 'Total Cases', 'Total Fine']);
    $result = mysqli_query($conn, "
        SELECT violation, COUNT(*) total_cases, COALESCE(SUM(fine_amount),0) total_fine
        FROM challans
        GROUP BY violation
        ORDER BY total_cases DESC
    ");
} else {
    fputcsv($out, ['Challan ID', 'User ID', 'Vehicle No', 'Violation', 'Fine Amount', 'Status']);
    $result = mysqli_query($conn, "SELECT id, user_id, vehicle_no, violation, fine_amount, status FROM challans ORDER BY id DESC");
}

if ($result instanceof mysqli_result) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($type === 'vehicles') {
            $risk = ((int) $row['total_cases'] >= 3 || (int) $row['unpaid_cases'] >= 2 || (float) $row['total_fine'] >= 5000) ? 'High' : (((int) $row['unpaid_cases'] >= 1) ? 'Medium' : 'Low');
            fputcsv($out, [$row['vehicle_no'], $row['total_cases'], $row['paid_cases'], $row['unpaid_cases'], $row['total_fine'], $risk]);
        } else {
            fputcsv($out, $row);
        }
    }
}

fclose($out);
exit();
?>
