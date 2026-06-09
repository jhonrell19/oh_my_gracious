<?php
session_start();
header("Content-Type: application/json");
include "db.php";

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    echo json_encode([
        "status" => "error",
        "message" => "Please enter your email and password."
    ]);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, password, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or password."
    ]);
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user["password"])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email or password."
    ]);
    exit;
}

if ($user["role"] === "admin") {
    echo json_encode([
        "status" => "error",
        "message" => "This is an admin account. Please use the admin login page."
    ]);
    exit;
}

$_SESSION["user_id"] = $user["id"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $user["role"];

echo json_encode([
    "status" => "success",
    "message" => "Login successful.",
    "redirect" => "user-dashboard.php"
]);

$stmt->close();
$conn->close();
?>