<?php
session_start();
require_once 'includes/db_connection.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'hr' || $role === 'admin') {
        header('Location: hr_dashboard.php');
    } elseif ($role === 'team_lead') {
        header('Location: team_lead_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['employee_id']);
    $password = trim($_POST['password']);
    $employee_id = str_replace('', '', $login_input);
    
    if (empty($employee_id) || empty($password)) {
        $error = "Please enter both Employee ID and Password!";
    } else {
        $stmt = $conn->prepare("SELECT id, employee_id, name, password, role, team_id FROM employees WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['employee_id'] = $user['employee_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['team_id'] = $user['team_id'];
                $_SESSION['full_id'] = $user['employee_id'] . '';
                
                if ($user['role'] === 'hr' || $user['role'] === 'admin') {
                    header('Location: hr_dashboard.php');
                } elseif ($user['role'] === 'team_lead') {
                    header('Location: team_lead_dashboard.php');
                } else {
                    header('Location: dashboard.php');
                }
                exit();
            } else {
                $error = "Invalid Employee ID or Password!";
            }
        } else {
            $error = "Invalid Employee ID or Password!";
        }
        $stmt->close();
    }
}

if (isset($_GET['registered'])) {
    $success = "Registration successful! Please login.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - bKash Notice Board</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .login-wrapper {
            display: flex;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.2);
            max-width: 900px;
            width: 100%;
            min-height: 550px;
        }
        .branding-side {
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            flex: 1;
            position: relative;
            overflow: hidden;
        }
        .branding-side::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .branding-content { position: relative; z-index: 1; }
        .branding-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            filter: brightness(0) invert(1);
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        .branding-title { font-size: 20px; font-weight: 500; margin-bottom: 10px; letter-spacing: 1px; }
        .branding-subtitle { font-size: 14px; opacity: 0.9; line-height: 1.6; }
        .branding-divider { width: 50px; height: 3px; background: white; margin: 20px auto; border-radius: 2px; }
        .login-side { padding: 50px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .login-header { margin-bottom: 35px; }
        .login-header h2 { color: #333; font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .login-header p { color: #999; font-size: 14px; }
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error { background: #FFF0F0; color: #D32F2F; border: 1px solid #FFCDD2; }
        .alert-success { background: #F0FFF0; color: #2E7D32; border: 1px solid #C8E6C9; }
        .form-group { margin-bottom: 22px; }
        .form-group label {
            display: block;
            color: #555;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-field { position: relative; }
        .input-field .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #BDBDBD;
            font-size: 16px;
            transition: color 0.3s;
        }
        .input-field input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #E8E8E8;
            border-radius: 12px;
            font-size: 15px;
            color: #333;
            transition: all 0.3s;
            background: #FAFAFA;
            outline: none;
        }
        .input-field input:focus {
            border-color: #E91E63;
            background: white;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.05);
        }
        .id-suffix {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #E91E63;
            font-weight: 600;
            font-size: 13px;
            pointer-events: none;
            background: #FAFAFA;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .login-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #E91E63 0%, #C2185B 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.3);
        }
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.4);
        }
        .login-footer { margin-top: 25px; text-align: center; }
        .login-footer a { color: #E91E63; text-decoration: none; font-size: 14px; font-weight: 500; }
        .footer-links { display: flex; justify-content: center; gap: 20px; align-items: center; }
        .footer-divider { color: #E0E0E0; }
        
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; max-width: 450px; }
            .branding-side { padding: 40px 30px; }
            .branding-logo { width: 100px; height: 100px; }
            .login-side { padding: 35px 30px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="branding-side">
            <div class="branding-content">
                <img src="assets/images/logo.png" alt="bKash Logo" class="branding-logo"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=font-size:64px;font-weight:800;>bK</div>';">
                <div class="branding-title">bKash Notice Board</div>
                <div class="branding-divider"></div>
                <div class="branding-subtitle">Your central hub for company<br>announcements and updates</div>
            </div>
        </div>
        <div class="login-side">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to access your dashboard</p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label>Employee ID</label>
                    <div class="input-field">
                        <i class="fas fa-id-card icon"></i>
                        <input type="text" name="employee_id" placeholder="Enter your employee ID" required>
                        <span class="id-suffix"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock icon"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>Sign In
                </button>
            </form>
            <div class="login-footer">
                <div class="footer-links">
                    <a href="signup.php"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Create Account</a>
                    <span class="footer-divider">|</span>
                    <a href="forgot_password.php"><i class="fas fa-key" style="margin-right: 5px;"></i>Forgot Password?</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>