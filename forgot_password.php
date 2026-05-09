<?php
session_start();
require_once 'includes/db_connection.php';

$error = '';
$success = '';
$step = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_id'])) {
        $employee_id = sanitize($_POST['employee_id']);
        
        $stmt = $conn->prepare("SELECT id, name, employee_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $step = 2;
            $_SESSION['reset_id'] = $user['id'];
            $_SESSION['reset_name'] = $user['name'];
            $_SESSION['reset_emp_id'] = $user['employee_id'];
        } else {
            $error = "Employee ID not found!";
        }
        $stmt->close();
        
    } elseif (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error = "Passwords do not match!";
            $step = 2;
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters!";
            $step = 2;
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $reset_id = $_SESSION['reset_id'];
            
            $update_stmt = $conn->prepare("UPDATE employees SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $reset_id);
            
            if ($update_stmt->execute()) {
                $success = "Password reset successful! You can now login.";
                unset($_SESSION['reset_id'], $_SESSION['reset_name'], $_SESSION['reset_emp_id']);
                $step = 1;
            } else {
                $error = "Failed to reset password.";
                $step = 2;
            }
            $update_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - bKash Notice Board</title>
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
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
            text-align: center;
        }
        .container h3 { color: #E91E63; font-size: 22px; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; color: #555; font-size: 13px; font-weight: 600; margin-bottom: 6px; text-transform: uppercase; }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #E8E8E8;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }
        .form-group input:focus { border-color: #E91E63; }
        .btn {
            width: 100%;
            padding: 14px;
            background: #E91E63;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn:hover { background: #C2185B; }
        .error { background: #FFF0F0; color: #D32F2F; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .success { background: #F0FFF0; color: #2E7D32; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .info-box { background: #FCE4EC; padding: 15px; border-radius: 10px; margin-bottom: 20px; color: #E91E63; }
        .link { color: #E91E63; text-decoration: none; font-weight: 500; display: block; margin-top: 20px; }
        .link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h3>Reset Password</h3>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">✅ <?php echo $success; ?></div>
            <a href="login.php" class="btn" style="display:block; text-decoration:none; text-align:center;">Go to Login</a>
        <?php endif; ?>
        
        <?php if ($step === 1 && !$success): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" required placeholder="Enter your Employee ID (e.g., EMP001)">
                </div>
                <button type="submit" name="check_id" class="btn">Verify ID</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step === 2): ?>
            <div class="info-box">
                Resetting password for:<br>
                <strong><?php echo htmlspecialchars($_SESSION['reset_name']); ?></strong>
                (<?php echo htmlspecialchars($_SESSION['reset_emp_id']); ?>)
            </div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required placeholder="Enter new password (min 6 characters)">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="Confirm new password">
                </div>
                <button type="submit" name="reset_password" class="btn">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <a href="login.php" class="link">← Back to Login</a>
    </div>
</body>
</html>