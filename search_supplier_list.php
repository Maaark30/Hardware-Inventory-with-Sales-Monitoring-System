<?php
include 'project.php';

if (isset($_GET['q'])) {
  $q = $conn->real_escape_string($_GET['q']);
  $query = "
    SELECT supplier_id, supplier_name, contact_no, email, address, notes 
    FROM suppliers 
    WHERE supplier_name LIKE '%$q%'
    ORDER BY supplier_name ASC
    LIMIT 10
  ";
  $result = $conn->query($query);

  $suppliers = [];
  while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
  }

  echo json_encode(['success' => true, 'suppliers' => $suppliers]);
}
?>
