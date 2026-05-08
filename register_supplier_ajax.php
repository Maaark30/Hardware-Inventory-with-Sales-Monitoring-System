<?php
include 'project.php';
header('Content-Type: application/json');

$supplier_name = trim($_POST['supplier_name'] ?? '');
$contact_person = trim($_POST['contact_person'] ?? '');
$contact_no = trim($_POST['contact_no'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$item_description = trim($_POST['item_description'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($supplier_name === '') {
    echo json_encode(['success' => false, 'message' => 'Supplier name is required.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, contact_person, contact_no, email, address, item_description, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssss", $supplier_name, $contact_person, $contact_no, $email, $address, $item_description, $notes);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'supplier_id' => $conn->insert_id,
        'supplier_name' => $supplier_name,
        'contact_no' => $contact_no
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
