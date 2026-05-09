<?php
session_start();
require_once 'includes/db_connection.php';

// Redirect if already logged in
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
    $employee_id = str_replace(' ', '', $login_input);
    
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
                $_SESSION['full_id'] = $user['employee_id'];
                
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
            padding: 20px;
        }
        .login-wrapper {
            display: flex;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(233, 30, 99, 0.15);
            max-width: 900px;
            width: 100%;
            min-height: 550px;
        }
        .branding-side {
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            text-align: center;
            flex: 1;
            position: relative;
        }
        .branding-content { position: relative; z-index: 1; }
        .branding-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 25px;
            background: white;
            padding: 10px;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .branding-title { font-size: 24px; font-weight: 700; margin-bottom: 10px; letter-spacing: 1px; }
        .branding-subtitle { font-size: 14px; opacity: 0.9; line-height: 1.6; }
        .branding-divider { width: 50px; height: 3px; background: white; margin: 20px auto; border-radius: 2px; }
        
        .login-side { padding: 60px; flex: 1; display: flex; flex-direction: column; justify-content: center; background: white; }
        .login-header { margin-bottom: 35px; }
        .login-header h2 { color: var(--text-dark); font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .login-header p { color: #64748B; font-size: 14px; }
        
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
        }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        
        .form-group { margin-bottom: 22px; }
        .form-group label {
            display: block;
            color: var(--text-dark);
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .input-field { position: relative; }
        .input-field i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 16px;
        }
        .input-field input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #F8FAFC;
            outline: none;
        }
        .input-field input:focus {
            border-color: var(--pink);
            background: white;
            box-shadow: 0 0 0 4px rgba(233, 30, 99, 0.08);
        }
        
        .login-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--pink) 0%, var(--pink-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(233, 30, 99, 0.25);
        }
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 30, 99, 0.35);
        }
        
        .login-footer { margin-top: 30px; text-align: center; }
        .login-footer a { color: var(--pink); text-decoration: none; font-size: 14px; font-weight: 600; transition: color 0.3s; }
        .login-footer a:hover { color: var(--pink-dark); }
        .footer-links { display: flex; justify-content: center; gap: 20px; align-items: center; }
        .footer-divider { color: var(--border); }
        
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; max-width: 450px; }
            .branding-side { padding: 40px 30px; }
            .login-side { padding: 40px 30px; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="branding-side">
            <div class="branding-content">
                <img src="logo/logo.jpg" alt="bKash Logo" class="branding-logo" onerror="this.src='https://via.placeholder.com/100?text=bKash';">
                <div class="branding-title">bKash Notice Board</div>
                <div class="branding-divider"></div>
                <div class="branding-subtitle">Centralized hub for corporate<br>announcements and performance updates</div>
            </div>
        </div>

        <div class="login-side">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label>Employee ID</label>
                    <div class="input-field">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="employee_id" placeholder="Enter your ID" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>Sign In
                </button>
            </form>

            <div class="login-footer">
                <div class="footer-links">
                    <a href="signup.php"><i class="fas fa-user-plus" style="margin-right: 5px;"></i>Register</a>
                    <span class="footer-divider">|</span>
                    <a href="forgot_password.php"><i class="fas fa-key" style="margin-right: 5px;"></i>Forgot Password?</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>