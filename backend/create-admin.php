<?php
include "db.php";

$full_name = "Admin";
$email = "admin@ohmygracious.com";
$password = "Admin123!";
$role = "admin";

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "Admin account already exists.";
    exit;
}

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo "Admin account created successfully.<br>";
    echo "Email: admin@ohmygracious.com<br>";
    echo "Password: Admin123!";
} else {
    echo "Failed to create admin account.";
}

$stmt->close();
$conn->close();
?>