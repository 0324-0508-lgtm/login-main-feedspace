<?php
$c = mysqli_connect('localhost', 'root', '', 'db_feedspace');
if (!$c) {
    echo 'CONNECTFAIL: ' . mysqli_connect_error();
    exit(1);
}
$r = mysqli_query($c, 'SELECT * FROM otp ORDER BY created_at DESC LIMIT 5');
while ($row = mysqli_fetch_assoc($r)) {
    echo json_encode($row) . "\n";
}
?>