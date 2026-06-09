<?php
session_start();
include "backend/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin-login.html");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: user-dashboard.php");
    exit;
}

$users = $conn->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <header class="navbar">
    <div class="logo">Admin Dashboard</div>
    <nav>
      <a href="admin-dashboard.php">Dashboard</a>
<a href="admin-bookings.php">Bookings</a>
<a href="logout.php" class="logout-btn">Logout</a>
    </nav>
  </header>

  <main class="dashboard">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h1>
    <p>You are logged in as admin.</p>

    <h2>Registered Users</h2>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Full Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Created At</th>
        </tr>
      </thead>

      <tbody>
        <?php while ($row = $users->fetch_assoc()) { ?>
          <tr>
            <td><?php echo htmlspecialchars($row["id"]); ?></td>
            <td><?php echo htmlspecialchars($row["full_name"]); ?></td>
            <td><?php echo htmlspecialchars($row["email"]); ?></td>
            <td><?php echo htmlspecialchars($row["role"]); ?></td>
            <td><?php echo htmlspecialchars($row["created_at"]); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </main>

</body>
</html>