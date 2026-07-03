<?php include 'db.php'; ?>

<h2>⚠ High Risk Drivers</h2>

<?php
$res=$conn->query("
SELECT v.number_plate, COUNT(*) total
FROM Detection d
JOIN Vehicle v ON d.vehicle_id=v.vehicle_id
GROUP BY d.vehicle_id
HAVING total >= 3
");

while($r=$res->fetch_assoc()){
echo "<p style='color:red;'>".$r['number_plate']." → HIGH RISK</p>";
}
?>