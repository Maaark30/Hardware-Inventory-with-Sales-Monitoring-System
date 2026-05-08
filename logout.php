<?php
session_start();
include "project.php";

if (isset($_SESSION['id'])) {
    $id = $_SESSION['id'];
    // ✅ Mark user as logged out
    $update = $conn->prepare("UPDATE users SET is_logged_in = 0 WHERE id = ?");
    $update->bind_param("i", $id);
    $update->execute();
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
