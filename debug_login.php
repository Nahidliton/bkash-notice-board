<?php
session_start();
require_once 'includes/db_connection.php';

echo "<h2>Login Debug Tool</h2>";

// Test employee lookup
$test_id = 'EMP001';
$test_password = 'password123';

echo "<h3>Testing Login with: $test_id / $test_password</h3>";

// 1. Check if employee exists
$stmt = $conn->prepare("SELECT id, employee_id, name, password, role FROM employees WHERE employee_id = ?");
$stmt->bind_param("s", $test_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color:red;'>❌ Employee ID not found in database!</p>";
    
    // Show all employee IDs
    $all = $conn->query("SELECT employee_id, name FROM employees");
    echo "<p>Available Employee IDs:</p><ul>";
    while ($row = $all->fetch_assoc()) {
        echo "<li>" . $row['employee_id'] . " - " . $row['name'] . "</li>";
    }
    echo "</ul>";
} else {
    $user = $result->fetch_assoc();
    echo "<p style='color:green;'>✅ Employee found: " . $user['name'] . "</p>";
    echo "<p>Stored password hash: <small>" . $user['password'] . "</small></p>";
    
    // 2. Test password verification
    if (password_verify($test_password, $user['password'])) {
        echo "<p style='color:green;'>✅ Password verification SUCCESS!</p>";
        echo "<p>You can now <a href='login.php'>Login here</a></p>";
    } else {
        echo "<p style='color:red;'>❌ Password verification FAILED!</p>";
        echo "<p>The password doesn't match the stored hash.</p>";
        
        // 3. Show what the correct hash should be
        $correct_hash = password_hash($test_password, PASSWORD_DEFAULT);
        echo "<p>Correct hash for 'password123': <small>" . $correct_hash . "</small></p>";
        
        // 4. Offer to fix
        echo "<form method='post'>";
        echo "<input type='hidden' name='fix_password' value='1'>";
        echo "<input type='hidden' name='emp_id' value='" . $test_id . "'>";
        echo "<button type='submit'>Fix Password for $test_id</button>";
        echo "</form>";
    }
}

// Fix password if requested
if (isset($_POST['fix_password'])) {
    $emp_id = $_POST['emp_id'];
    $new_hash = password_hash('password123', PASSWORD_DEFAULT);
    
    $fix_stmt = $conn->prepare("UPDATE employees SET password = ? WHERE employee_id = ?");
    $fix_stmt->bind_param("ss", $new_hash, $emp_id);
    
    if ($fix_stmt->execute()) {
        echo "<p style='color:green;'>✅ Password fixed! New hash: " . $new_hash . "</p>";
        echo "<p>Try logging in now with: $emp_id / password123</p>";
    }
    $fix_stmt->close();
}

$stmt->close();
$conn->close();
?>