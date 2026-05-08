<?php
session_start();
// Assuming 'project.php' contains your $conn (MySQLi connection object)
include "project.php";

/// ================= REGISTER ================= ///
if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $username  = trim($_POST['username']);
    $password  = $_POST['password'];
    $role      = ($_POST['role'] == 'admin') ? 'admin' : 'staff';

    // ✅ Validate required fields
    if (empty($full_name) || empty($username) || empty($password)) {
        header("Location: register.php?error=fields");
        exit();
    }

    // ✅ Check if username already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        header("Location: register.php?error=username");
        $check->close();
        exit();
    }
    $check->close();

    // ✅ Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ✅ Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, username, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssss",
        $full_name,
        $username,
        $hashed_password,
        $role
    );

    if ($stmt->execute()) {
        header("Location: login.php?registered=1");
        exit();
    } else {
        echo "<script>alert('Error during registration: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
}

// ----------------------------------------------------------------------------------------------------------------------

/// ================= LOGIN ================= ///
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password']; 

    // Retrieve user credentials
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $uname, $hashed_pass, $role);

    if ($stmt->num_rows > 0) {
        $stmt->fetch();
        $login_successful = false;
        
        if (password_verify($password, $hashed_pass)) {
            $login_successful = true;
        }
        if ($login_successful) {
            // ✅ Update login status
            $update = $conn->prepare("UPDATE users SET is_logged_in = 1, last_login = NOW() WHERE id = ?");
            $update->bind_param("i", $id);
            $update->execute();

            $_SESSION['id'] = $id;
            $_SESSION['username'] = $uname;
            $_SESSION['role'] = $role;

            if ($role == "admin") {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: staff_dashboard.php");
            }
            exit();
        } else {
            header("Location: login.php?error=invalid&u=" . urlencode($username));
            exit();
        }
    } else {
        header("Location: login.php?error=notfound&u=" . urlencode($username));
        exit();
    }
    $stmt->close();
}

// =======================
// 💼 SAVE SUPPLIER
// =======================
if (isset($_POST['save_supplier'])) {
    $supplier_name = trim($_POST['supplier_name']);
    $contact_person = trim($_POST['contact_person']);
    $contact_no = trim($_POST['contact_no']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $item_description = trim($_POST['item_description']);
    $notes = trim($_POST['notes']);

    $errors = [];

    if ($supplier_name == '') {
        $errors['supplier_name'] = "Supplier name is required.";
    }

    if (!empty($errors)) {
        echo json_encode(['status' => 422, 'errors' => $errors]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO suppliers 
        (supplier_name, contact_person, contact_no, email, address, item_description, notes) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssss", $supplier_name, $contact_person, $contact_no, $email, $address, $item_description, $notes);

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        echo json_encode([
            'status' => 200,
            'message' => 'Supplier added successfully!',
            'new_supplier' => [
                'id' => $new_id,
                'name' => $supplier_name
            ]
        ]);
    } else {
        echo json_encode(['status' => 500, 'message' => 'Database error.']);
    }
    $stmt->close();
    exit;
}


?>