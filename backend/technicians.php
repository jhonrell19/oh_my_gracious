<?php

header("Content-Type: application/json");

require "db.php";

$result = $conn->query(
    "SELECT id, full_name, specialization
     FROM technicians
     WHERE status = 'active'
     ORDER BY full_name ASC"
);

$technicians = [];

while ($row = $result->fetch_assoc()) {

    $technicians[] = $row;

}

echo json_encode([
    "status" => "success",
    "technicians" => $technicians
]);