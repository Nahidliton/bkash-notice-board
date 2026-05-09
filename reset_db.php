<?php
// reset_db.php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'notice_board';

$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop and recreate database
$conn->query("DROP DATABASE IF EXISTS notice_board");
$conn->query("CREATE DATABASE notice_board");
$conn->select_db("notice_board");

// Create employees table
$conn->query("CREATE TABLE employees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(15),
    dob DATE,
    designation ENUM('Manager', 'Team Lead', 'Team Coordinator', 'Full Timer', 'Part Timer') NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('employee', 'admin') DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Create notices table
$conn->query("CREATE TABLE notices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('general', 'individual') NOT NULL,
    employee_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

// Create performance table
$conn->query("CREATE TABLE performance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    csat DECIMAL(5,2) DEFAULT 0,
    tickets DECIMAL(5,2) DEFAULT 0,
    fcr DECIMAL(5,2) DEFAULT 0,
    resolution_time DECIMAL(5,2) DEFAULT 0,
    response_time DECIMAL(5,2) DEFAULT 0,
    total DECIMAL(5,2) DEFAULT 0,
    comments TEXT,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
)");

// Create password hash
$hashed_password = password_hash('password123', PASSWORD_DEFAULT);

// Insert test users
$stmt = $conn->prepare("INSERT INTO employees (employee_id, name, phone, dob, designation, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");

$users = [
    ['EMP001', 'Admin User', '01712345678', '1990-01-01', 'Manager', 'admin'],
    ['EMP002', 'Fatema Akter', '01712345679', '1992-03-20', 'Team Lead', 'employee'],
    ['EMP003', 'Shakib Hasan', '01712345680', '1993-05-10', 'Team Coordinator', 'employee']
];

foreach ($users as $user) {
    $stmt->bind_param("sssssss", $user[0], $user[1], $user[2], $user[3], $user[4], $hashed_password, $user[5]);
    $stmt->execute();
}

$stmt->close();

echo "<h2>✅ Database Reset Complete!</h2>";
echo "<p>Password for all users: <strong>password123</strong></p>";
echo "<p>Password Hash: " . $hashed_password . "</p>";

// Test verification
$result = $conn->query("SELECT * FROM employees WHERE employee_id = 'EMP001'");
$user = $result->fetch_assoc();
echo "<p>Verification test: ";
if (password_verify('password123', $user['password'])) {
    echo "✅ SUCCESS - Password works!";
} else {
    echo "❌ FAILED - Password doesn't match!";
}
echo "</p>";

echo "<h3>Login Credentials:</h3>";
echo "<ul>";
echo "<li>Admin: EMP001 / password123</li>";
echo "<li>Employee: EMP002 / password123</li>";
echo "<li>Employee: EMP003 / password123</li>";
echo "</ul>";

echo "<p><a href='login.php'>Go to Login Page</a></p>";

$conn->close();
?>