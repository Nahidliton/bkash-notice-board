<?php
// fix_passwords.php
require_once 'includes/db_connection.php';

echo "<h2>Password Fix Tool</h2>";

// Create a fresh password hash
$new_password = 'password123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<p>New Hash Created: " . $hashed_password . "</p>";

// Update ALL employees
$sql = "UPDATE employees SET password = '$hashed_password'";
if ($conn->query($sql)) {
    $count = $conn->affected_rows;
    echo "<p style='color:green;font-size:18px;'>✅ Successfully updated $count employee passwords!</p>";
} else {
    echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
}

// Verify each employee
echo "<h3>Verification Check:</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr style='background:#FCE4EC;'><th>Employee ID</th><th>Name</th><th>Role</th><th>Password Works?</th></tr>";

$result = $conn->query("SELECT employee_id, name, role, password FROM employees");
while ($row = $result->fetch_assoc()) {
    $works = password_verify('password123', $row['password']) ? '✅ YES' : '❌ NO';
    $color = password_verify('password123', $row['password']) ? 'green' : 'red';
    echo "<tr>";
    echo "<td><strong>{$row['employee_id']}</strong></td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['role']}</td>";
    echo "<td style='color:$color; font-weight:bold;'>$works</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>Login Credentials:</h3>";
echo "<div style='background:#FCE4EC; padding:15px; border-radius:10px;'>";
echo "<p><strong>Password for ALL users:</strong> <code>password123</code></p>";
echo "<ul>";
echo "<li>HR: <strong>HR001@bkash.com</strong></li>";
echo "<li>Team Lead 1: <strong>TL001@bkash.com</strong></li>";
echo "<li>Team Lead 2: <strong>TL002@bkash.com</strong></li>";
echo "<li>Employee: <strong>EMP001@bkash.com</strong></li>";
echo "</ul>";
echo "</div>";

echo "<p style='margin-top:20px;'><a href='login.php' style='background:#E91E63; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Login Page</a></p>";

$conn->close();
?>