<?php
session_start();
header("Content-Type: application/json");
include "db.php";

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Please enter admin email and password."
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ? AND role = 'admin'");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid admin email or password."
    ]);
    exit;
}

$admin = $result->fetch_assoc();

if (!password_verify($password, $admin["password"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid admin email or password."
    ]);
    exit;
}

$_SESSION["user_id"] = $admin["id"];
$_SESSION["full_name"] = $admin["full_name"];
$_SESSION["email"] = $admin["email"];
$_SESSION["role"] = $admin["role"];

echo json_encode([
    "status" => "success",
    "message" => "Admin login successful.",
    "redirect" => "admin-dashboard.php"
]);

$stmt->close();
$conn->close();
?>