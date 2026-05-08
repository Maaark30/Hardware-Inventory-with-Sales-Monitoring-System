<?php
include 'project.php';

$query = "SELECT COUNT(*) AS total FROM users WHERE role = 'staff' AND is_logged_in = 1";
$result = $conn->query($query);
$count = $result->fetch_assoc()['total'];

echo json_encode(['count' => $count]);
