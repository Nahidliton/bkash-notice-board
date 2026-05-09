<?php
require_once 'includes/db_connection.php';

// Helper function for security
if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
    }
}

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
        $error = "❌ Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "❌ Password must be at least 6 characters!";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM employees WHERE employee_id = ?");
        $check_stmt->bind_param("s", $employee_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "❌ Employee ID already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Default role is set to 'employee'
            $stmt = $conn->prepare("INSERT INTO employees (employee_id, name, phone, dob, designation, password, role) VALUES (?, ?, ?, ?, ?, ?, 'employee')");
            $stmt->bind_param("ssssss", $employee_id, $name, $phone, $dob, $designation, $hashed_password);
            
            if ($stmt->execute()) {
                header('Location: login.php?registered=1');
                exit();
            } else {
                $error = "❌ Registration failed. Please try again.";
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
    <title>Employee Registration - bKash Notice Board</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { 
            --pink: #E91E63; 
            --pink-dark: #C2185B; 
            --pink-light: #FCE4EC; 
            --white: #FFFFFF; 
            --text-dark: #1E293B; 
            --border: #E2E8F0; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--pink-light) 0%, #F8BBD0 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }
        .signup-wrapper {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.15);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .signup-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 15px;
            background: white;
            padding: 8px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid var(--pink-light);
        }
        .signup-header h2 {
            color: var(--pink);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .signup-header p {
            color: #64748B;
            font-size: 14px;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px 15px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            border: 1px solid #FECACA;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .full-width { grid-column: span 2; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            color: var(--text-dark);
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-field { position: relative; }
        .input-field i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 14px;
        }
        .input-field input, .input-field select {
            width: 100%;
            padding: 12px 12px 12px 38px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #F8FAFC;
            outline: none;
        }
        .input-field input:focus, .input-field select:focus {
            border-color: var(--pink);
            background: white;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.05);
        }
        
        .signup-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.2);
        }
        .signup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.3);
        }
        
        .signup-footer {
            margin-top: 25px;
            text-align: center;
            font-size: 14px;
            color: #64748B;
        }
        .signup-footer a {
            color: var(--pink);
            text-decoration: none;
            font-weight: 600;
        }
        .signup-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <div class="signup-wrapper">
        <div class="signup-header">
            <img src="logo/logo.jpg" alt="bKash Logo" class="signup-logo" onerror="this.src='https://via.placeholder.com/80?text=bKash';">
            <h2>Create Account</h2>
            <p>Join the bKash Notice Board system</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID *</label>
                    <div class="input-field">
                        <i class="fas fa-id-badge"></i>
                        <input type="text" name="employee_id" placeholder="e.g. EMP123" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Full Name *</label>
                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" name="name" placeholder="John Doe" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <div class="input-field">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" placeholder="01XXX-XXXXXX">
                    </div>
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <div class="input-field">
                        <i class="fas fa-calendar"></i>
                        <input type="date" name="dob">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Designation *</label>
                    <div class="input-field">
                        <i class="fas fa-briefcase"></i>
                        <select name="designation" required>
                            <option value="">Select Position</option>
                            <option value="Manager">Manager</option>
                            <option value="Team Lead">Team Lead</option>
                            <option value="Team Coordinator">Team Coordinator</option>
                            <option value="Full Timer">Full Timer</option>
                            <option value="Part Timer">Part Timer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Min 6 chars" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <div class="input-field">
                        <i class="fas fa-shield-alt"></i>
                        <input type="password" name="confirm_password" placeholder="Repeat password" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="signup-button">
                <i class="fas fa-user-plus" style="margin-right: 8px;"></i>Register Now
            </button>
        </form>

        <div class="signup-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>