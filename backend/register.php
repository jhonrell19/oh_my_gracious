<?php
header("Content-Type: application/json");
include "db.php";

$full_name = trim($_POST["full_name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($full_name === "" || $email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Please complete all fields."
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Please enter a valid email address."
    ]);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must be at least 8 characters long."
    ]);
    exit;
}

if (!preg_match("/[A-Z]/", $password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must contain at least 1 uppercase letter."
    ]);
    exit;
}

if (!preg_match("/[a-z]/", $password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must contain at least 1 lowercase letter."
    ]);
    exit;
}

if (!preg_match("/[0-9]/", $password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must contain at least 1 number."
    ]);
    exit;
}

if (!preg_match("/[^A-Za-z0-9]/", $password)) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must contain at least 1 special character."
    ]);
    exit;
}

$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email is already registered."
    ]);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = "user";

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Registration successful. You can now login."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Registration failed. Please try again."
    ]);
}

$stmt->close();
$conn->close();
?>