<?php
header('Content-Type: application/json');
include 'project.php';

$id = intval($_GET['id'] ?? 0);
$query = $conn->prepare("SELECT contact_no FROM suppliers WHERE supplier_id = ?");
$query->bind_param('i', $id);
$query->execute();
$result = $query->get_result()->fetch_assoc();

if ($result) {
  echo json_encode(['success' => true, 'contact_no' => $result['contact_no']]);
} else {
  echo json_encode(['success' => false]);
}
?>
