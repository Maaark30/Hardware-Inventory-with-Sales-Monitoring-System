<?php
// Ensure this path is correct for your database connection file
include 'project.php'; 

// Set header to indicate the response is JSON
header('Content-Type: application/json');

// Sanitize and get the category ID from the GET request
$category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

$subcategories = [];

if ($category_id > 0) {
    // Modify this query to match your table and column names (e.g., 'subcategories', 'category_id', 'subcategory_id', 'subcategory_name')
    $stmt = $conn->prepare("SELECT subcategory_id, subcategory_name FROM subcategories WHERE category_id = ? ORDER BY subcategory_name ASC");
    
    if ($stmt) {
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row;
        }
        
        $stmt->close();
    }
}

// Encode the resulting array into JSON format
echo json_encode($subcategories);
?>