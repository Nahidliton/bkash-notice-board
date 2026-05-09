<?php
// test_db.php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'notice_board';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}
echo "✅ Database connected successfully!<br><br>";

// Check if employees table exists
$result = $conn->query("SHOW TABLES LIKE 'employees'");
if ($result->num_rows > 0) {
    echo "✅ employees table exists<br>";
} else {
    echo "❌ employees table missing!<br>";
}

// Check if EMP001 exists
$check = $conn->query("SELECT * FROM employees WHERE employee_id = 'EMP001'");
if ($check->num_rows > 0) {
    $user = $check->fetch_assoc();
    echo "✅ EMP001 found: " . $user['name'] . "<br>";
    echo "Role: " . $user['role'] . "<br>";
    echo "Password Hash: " . $user['password'] . "<br>";
    
    // Test password
    if (password_verify('password123', $user['password'])) {
        echo "✅ Password 'password123' is CORRECT!<br>";
    } else {
        echo "❌ Password 'password123' is WRONG!<br>";
        
        // Fix the password
        $newHash = password_hash('password123', PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = 'EMP001'");
        $update->bind_param("s", $newHash);
        $update->execute();
        echo "✅ Password has been reset to 'password123'<br>";
        echo "New hash: " . $newHash . "<br>";
    }
} else {
    echo "❌ EMP001 not found! You need to import the database.<br>";
}

// Show all employees
echo "<br><h3>All Employees in Database:</h3>";
$all = $conn->query("SELECT employee_id, name, role FROM employees");
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Employee ID</th><th>Name</th><th>Role</th></tr>";
while ($row = $all->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['employee_id'] . "</td>";
    echo "<td>" . $row['name'] . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "</tr>";
}
echo "</table>";

$conn->close();
?>