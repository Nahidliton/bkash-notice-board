<?php
require_once 'includes/db_connection.php';

echo "<h2>Debug: Critical Performance Query</h2>";

// Check EMP001 performance data
echo "<h3>EMP001 Performance Records:</h3>";
$check = $conn->query("SELECT * FROM performance WHERE employee_id = (SELECT id FROM employees WHERE employee_id = 'EMP001') ORDER BY date DESC");
if ($check && $check->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Total</th><th>Status</th><th>Date</th></tr>";
    while ($row = $check->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['total']}</td>";
        echo "<td>{$row['status']}</td>";
        echo "<td>{$row['date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No performance records found for EMP001!</p>";
    echo "<p>Let's check if EMP001 exists in employees table:</p>";
    $emp = $conn->query("SELECT * FROM employees WHERE employee_id = 'EMP001'");
    if ($emp && $emp->num_rows > 0) {
        $e = $emp->fetch_assoc();
        echo "<p style='color:green;'>✅ EMP001 found: ID={$e['id']}, Name={$e['name']}</p>";
    } else {
        echo "<p style='color:red;'>❌ EMP001 not found!</p>";
    }
}

// Test the critical query directly
echo "<h3>Critical Query Test (All records with total < 60):</h3>";
$test = $conn->query("SELECT e.name, e.employee_id, p.total, p.status, p.date 
                       FROM performance p 
                       JOIN employees e ON p.employee_id = e.id 
                       WHERE p.total < 60");
if ($test && $test->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Name</th><th>ID</th><th>Total</th><th>Status</th><th>Date</th></tr>";
    while ($row = $test->fetch_assoc()) {
        echo "<tr><td>{$row['name']}</td><td>{$row['employee_id']}</td><td>{$row['total']}</td><td>{$row['status']}</td><td>{$row['date']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No records found with total < 60!</p>";
}

echo "<h3>All Performance Records:</h3>";
$all = $conn->query("SELECT e.name, e.employee_id, p.total, p.status, p.date 
                      FROM performance p 
                      JOIN employees e ON p.employee_id = e.id 
                      ORDER BY p.date DESC LIMIT 20");
if ($all && $all->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Name</th><th>ID</th><th>Total</th><th>Status</th><th>Date</th></tr>";
    while ($row = $all->fetch_assoc()) {
        $color = $row['total'] < 60 ? 'red' : 'black';
        echo "<tr style='color:$color'><td>{$row['name']}</td><td>{$row['employee_id']}</td><td><strong>{$row['total']}</strong></td><td>{$row['status']}</td><td>{$row['date']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>No performance records at all!</p>";
}

$conn->close();
?>