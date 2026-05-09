<?php
require_once 'includes/db_connection.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = sanitize($_POST['employee_id']);
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $dob = sanitize($_POST['dob']);
    $designation = sanitize($_POST['designation']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $check_stmt->bind_param("s", $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Employee ID already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO employees (employee_id, name, phone, dob, designation, password, role) VALUES (?, ?, ?, ?, ?, ?, 'employee')");
            $stmt->bind_param("ssssss", $employee_id, $name, $phone, $dob, $designation, $hashed_password);
            
            if ($stmt->execute()) {
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - bKash Notice Board</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #FCE4EC 0%, #F8BBD0 50%, #FCE4EC 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .signup-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
        }
        .signup-container h3 { color: #E91E63; font-size: 24px; margin-bottom: 25px; text-align: center; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; color: #555; font-size: 13px; font-weight: 600; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus { border-color: #E91E63; }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(233, 30, 99, 0.3); }
        .error { background: #FFF0F0; color: #D32F2F; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .link { color: #E91E63; text-decoration: none; font-weight: 500; display: block; text-align: center; margin-top: 20px; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="signup-container">
        <h3>Employee Registration</h3>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Employee ID *</label>
                <input type="text" name="employee_id" required placeholder="Enter Employee ID (e.g., EMP011)">
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" required placeholder="Enter your full name">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" placeholder="Enter phone number">
            </div>
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" name="dob">
            </div>
            <div class="form-group">
                <label>Designation *</label>
                <select name="designation" required>
                    <option value="">Select Designation</option>
                    <option value="Manager">Manager</option>
                    <option value="Team Lead">Team Lead</option>
                    <option value="Team Coordinator">Team Coordinator</option>
                    <option value="Full Timer">Full Timer</option>
                    <option value="Part Timer">Part Timer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required placeholder="Enter password (min 6 characters)">
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <a href="login.php" class="link">Already have an account? Login here</a>
    </div>
</body>
</html>