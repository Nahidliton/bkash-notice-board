<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

// Only team lead and HR can access
if ($_SESSION['role'] !== 'team_lead' && $_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$employee_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;

// Fetch employee details
$emp = $conn->query("SELECT e.*, t.team_name FROM employees e LEFT JOIN teams t ON e.team_id = t.id WHERE e.id = $employee_id")->fetch_assoc();

if (!$emp) {
    header('Location: ' . ($_SESSION['role'] === 'hr' ? 'hr_dashboard.php' : 'team_lead_dashboard.php'));
    exit();
}

// Handle evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csat = floatval($_POST['csat']);
    $tickets = floatval($_POST['tickets']);
    $fcr = floatval($_POST['fcr']);
    $resolution_time = floatval($_POST['resolution_time']);
    $response_time = floatval($_POST['response_time']);
    $comments = sanitize($_POST['comments']);
    $date = sanitize($_POST['date']);
    $total = $csat + $tickets + $fcr + $resolution_time + $response_time;
    $evaluator_id = $_SESSION['user_id'];
    
    $sql = "INSERT INTO performance (employee_id, evaluator_id, csat, tickets, fcr, resolution_time, response_time, total, comments, date) 
            VALUES ($employee_id, $evaluator_id, $csat, $tickets, $fcr, $resolution_time, $response_time, $total, '$comments', '$date')";
    
    if ($conn->query($sql)) {
        $success = "Evaluation saved! Total: $total/100";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// Fetch evaluations
$evaluations = $conn->query("SELECT * FROM performance WHERE employee_id = $employee_id ORDER BY date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluate - <?php echo htmlspecialchars($emp['name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #F5F5F5; }
        .navbar { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { color: #E91E63; }
        .container { max-width: 1000px; margin: 20px auto; padding: 0 20px; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h3 { color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #FCE4EC; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 5px; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 2px solid #E0E0E0; border-radius: 8px; font-size: 14px; outline: none; }
        .form-group input:focus, .form-group textarea:focus { border-color: #E91E63; }
        .marks-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .btn { padding: 12px 24px; background: #E91E63; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; }
        table th { background: #FCE4EC; color: #E91E63; padding: 10px; }
        table td { padding: 10px; border-bottom: 1px solid #E0E0E0; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
        .alert-success { background: #E8F5E9; color: #2E7D32; }
        .alert-error { background: #FFEBEE; color: #C62828; }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>📊 Evaluate Employee</h2>
        <a href="<?php echo $_SESSION['role'] === 'hr' ? 'hr_dashboard.php' : 'team_lead_dashboard.php'; ?>" style="color: #E91E63;">← Back</a>
    </nav>
    
    <div class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Evaluation Form -->
            <div class="card">
                <h3>New Evaluation</h3>
                <div style="background: #F5F5F5; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                    <strong><?php echo htmlspecialchars($emp['name']); ?></strong> (<?php echo $emp['employee_id']; ?>@bkash.com)<br>
                    Team: <?php echo $emp['team_name'] ?? 'Unassigned'; ?>
                </div>
                <form method="POST">
                    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="marks-row">
                        <div class="form-group"><label>CSAT</label><input type="number" name="csat" min="0" max="20" step="0.01" required></div>
                        <div class="form-group"><label>Tickets</label><input type="number" name="tickets" min="0" max="20" step="0.01" required></div>
                        <div class="form-group"><label>FCR</label><input type="number" name="fcr" min="0" max="20" step="0.01" required></div>
                        <div class="form-group"><label>Resolution</label><input type="number" name="resolution_time" min="0" max="20" step="0.01" required></div>
                        <div class="form-group"><label>Response</label><input type="number" name="response_time" min="0" max="20" step="0.01" required></div>
                    </div>
                    <div class="form-group"><label>Comments</label><textarea name="comments" rows="2"></textarea></div>
                    <button type="submit" class="btn">💾 Save Evaluation</button>
                </form>
            </div>
            
            <!-- History -->
            <div class="card">
                <h3>Evaluation History</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead><tr><th>Date</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php while ($eval = $evaluations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $eval['date']; ?></td>
                                    <td><?php echo $eval['csat']; ?></td>
                                    <td><?php echo $eval['tickets']; ?></td>
                                    <td><?php echo $eval['fcr']; ?></td>
                                    <td><?php echo $eval['resolution_time']; ?></td>
                                    <td><?php echo $eval['response_time']; ?></td>
                                    <td><strong><?php echo $eval['total']; ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>