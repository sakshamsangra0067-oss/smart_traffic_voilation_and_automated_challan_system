<?php include 'db.php'; ?>

<h2>📍 Locations</h2>

<table border="1">
<tr><th>ID</th><th>Area</th></tr>

<?php
$res=$conn->query("SELECT * FROM Location");
while($r=$res->fetch_assoc()){
echo "<tr>
<td>{$r['location_id']}</td>
<td>{$r['area_name']}</td>
</tr>";
}
?>
</table>