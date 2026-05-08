<?php
// Database configuration
$host     = "localhost";   // usually "localhost"
$user     = "root";        // default XAMPP/WAMP username
$password = "";            // default XAMPP/WAMP password is empty
$database = "project"; // replace with your database name

// Create connection
$conn = mysqli_connect($host, $user, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/**
 * Formats a quantity value for display.
 * Strips trailing zeros from decimals and shows as integer if whole number.
 */
function formatQty($q) {
    if ($q == (int)$q) return (int)$q;
    return rtrim(rtrim(number_format((float)$q, 4), '0'), '.');
}
?>
