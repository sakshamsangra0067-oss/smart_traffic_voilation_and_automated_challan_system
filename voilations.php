<?php include 'db.php'; ?>

<h2>🚨 Violation Types</h2>

<table border="1">
<tr><th>ID</th><th>Type</th><th>Fine</th></tr>

<?php
$res=$conn->query("SELECT * FROM Violation");
while($r=$res->fetch_assoc()){
echo "<tr>
<td>{$r['violation_id']}</td>
<td>{$r['type']}</td>
<td>₹{$r['fine_amount']}</td>
</tr>";
}
?>
</table>