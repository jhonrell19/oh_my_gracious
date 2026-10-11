<?php

require __DIR__ . "/backend/authorization.php";

requireSuperAdmin();

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Super Admin Test
    </title>

</head>

<body>

    <h1>
        Super Admin Access Successful
    </h1>

    <p>
        Welcome,
        <?= htmlspecialchars(
            $_SESSION["full_name"]
        ) ?>
    </p>

    <p>
        Your role is:
        <?= htmlspecialchars(
            $_SESSION["role"]
        ) ?>
    </p>

    <a href="admin-dashboard.php">
        Back to Dashboard
    </a>

</body>

</html>