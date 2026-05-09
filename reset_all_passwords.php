<?php
// reset_all_passwords.php
require_once 'includes/db_connection.php';

echo "<h2>Password Reset Tool</h2>";

// Create new password hash
$new_password = 'password123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<p>New Hash: " . $hashed_password . "</p>";

// Update ALL employees with the new hash
$sql = "UPDATE employees SET password = '$hashed_password'";
if ($conn->query($sql)) {
    $affected = $conn->affected_rows;
    echo "<p style='color:green;'>✅ Successfully updated $affected employees!</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}

// Verify
echo "<h3>Verification:</h3>";
$result = $conn->query("SELECT employee_id, name, role, password FROM employees LIMIT 5");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Employee ID</th><th>Name</th><th>Role</th><th>Hash</th><th>Verify</th></tr>";
while ($row = $result->fetch_assoc()) {
    $verify = password_verify('password123', $row['password']) ? '✅ Works' : '❌ Fails';
    echo "<tr>";
    echo "<td>" . $row['employee_id'] . "</td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td><small>" . substr($row['password'], 0, 30) . "...</small></td>";
    echo "<td>" . $verify . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Login Credentials (All passwords: <strong>password123</strong>):</h3>";
echo "<ul>";
echo "<li><strong>HR:</strong> HR001@bkash.com</li>";
echo "<li><strong>Team Lead 1:</strong> TL001@bkash.com</li>";
echo "<li><strong>Team Lead 2:</strong> TL002@bkash.com</li>";
echo "<li><strong>Employee:</strong> EMP001@bkash.com</li>";
echo "</ul>";

echo "<p><a href='login.php' style='color:#E91E63; font-size:16px;'>→ Go to Login Page</a></p>";

$conn->close();
?>