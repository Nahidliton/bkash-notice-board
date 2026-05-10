<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
    }
}

if ($_SESSION['role'] !== 'team_lead') {
    header('Location: dashboard.php');
    exit();
}

$tl_id = $_SESSION['user_id'];
$tl_name = $_SESSION['name'];
$team_id = $_SESSION['team_id'];
$tl_full_id = $_SESSION['full_id'];
$success = '';
$error = '';

$team_result = $conn->query("SELECT * FROM teams WHERE id = $team_id");
$team = $team_result ? $team_result->fetch_assoc() : null;

// ========== 1. HANDLE NEW EVALUATION ==========
if (isset($_POST['submit_eval'])) {
    $emp_id = intval($_POST['employee_id']);
    $csat = floatval($_POST['csat']);
    $tickets = floatval($_POST['tickets']);
    $fcr = floatval($_POST['fcr']);
    $resolution_time = floatval($_POST['resolution_time']);
    $response_time = floatval($_POST['response_time']);
    $comments = sanitize($_POST['comments']);
    $date = sanitize($_POST['date']);
    $total = $csat + $tickets + $fcr + $resolution_time + $response_time;
    
        // Check if this employee was already evaluated TODAY (by anyone)
    $check_sql = "SELECT id FROM performance WHERE employee_id = $emp_id AND date = '$date'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        $error = "❌ This employee has already been evaluated today! Use the edit option to update marks.";
    } else {
        $sql = "INSERT INTO performance (employee_id, evaluator_id, csat, tickets, fcr, resolution_time, response_time, total, comments, date, status) 
                VALUES ($emp_id, $tl_id, $csat, $tickets, $fcr, $resolution_time, $response_time, $total, '$comments', '$date', 'pending')";
        
        if ($conn->query($sql)) {
            $perf_id = $conn->insert_id;
            
            if ($total < 80) {
                // Below 80 - Create consultation
                $conn->query("INSERT INTO consultations (employee_id, team_lead_id, performance_id, consultation_date, notes, status) 
                              VALUES ($emp_id, $tl_id, $perf_id, CURDATE(), 'Performance below 80 - consultation required', 'scheduled')");
                $conn->query("UPDATE performance SET status = 'consulted' WHERE id = $perf_id");
                
                $notice_title = "Consultation Required";
                $notice_desc = "Your performance score is $total/100 (below 80). Please schedule a consultation with your Team Lead.";
                $conn->query("INSERT INTO notices (title, description, type, employee_id, created_by) 
                              VALUES ('$notice_title', '$notice_desc', 'individual', $emp_id, $tl_id)");
            } else {
                // 80 or above - No consultation needed
                $conn->query("UPDATE performance SET status = 'reviewed' WHERE id = $perf_id");
            }
            
            $success = "✅ Evaluation saved! Total: $total/100";
        } else { 
            $error = "❌ Error: " . $conn->error; 
        }
    }
}

// ========== 2. HANDLE MARK UPDATE (WITH AUTO-REMOVE FROM CONSULTATION) ==========
if (isset($_POST['update_eval'])) {
    $eval_id = intval($_POST['eval_id']);
    $csat = floatval($_POST['edit_csat']);
    $tickets = floatval($_POST['edit_tickets']);
    $fcr = floatval($_POST['edit_fcr']);
    $resolution_time = floatval($_POST['edit_resolution_time']);
    $response_time = floatval($_POST['edit_response_time']);
    $comments = sanitize($_POST['edit_comments']);
    $update_reason = sanitize($_POST['update_reason']);
    $total = $csat + $tickets + $fcr + $resolution_time + $response_time;
    
    // Save history with reason
    $full_comments = "[Updated: " . date('Y-m-d H:i') . "] Reason: $update_reason | Comments: $comments";
    
    // Update performance record
    $conn->query("UPDATE performance SET 
                  csat = $csat, 
                  tickets = $tickets, 
                  fcr = $fcr, 
                  resolution_time = $resolution_time, 
                  response_time = $response_time, 
                  total = $total, 
                  comments = '$full_comments' 
                  WHERE id = $eval_id AND evaluator_id = $tl_id");
    
    if ($total >= 80) {
        // Score is 80+ - Mark as reviewed AND auto-cancel consultations
        $conn->query("UPDATE performance SET status = 'reviewed' WHERE id = $eval_id");
        
        // AUTO-REMOVE FROM CONSULTATION
        $conn->query("UPDATE consultations SET 
                      status = 'cancelled', 
                      notes = CONCAT(notes, ' | [AUTO-CANCELLED: Score updated to $total/100 on " . date('Y-m-d H:i') . " by $tl_name]') 
                      WHERE performance_id = $eval_id AND status = 'scheduled'");
        
        $success = "✅ Evaluation updated! Total: $total/100 (Score 80+ - Consultations auto-cancelled)";
    } else {
        // Below 80 - Mark as consulted
        $conn->query("UPDATE performance SET status = 'consulted' WHERE id = $eval_id");
        
        // Ensure consultation exists
        $consult_check = $conn->query("SELECT id FROM consultations WHERE performance_id = $eval_id AND status != 'cancelled'");
        if (!$consult_check || $consult_check->num_rows == 0) {
            $perf_data = $conn->query("SELECT employee_id FROM performance WHERE id = $eval_id")->fetch_assoc();
            $emp_id = $perf_data['employee_id'];
            $conn->query("INSERT INTO consultations (employee_id, team_lead_id, performance_id, consultation_date, notes, status) 
                          VALUES ($emp_id, $tl_id, $eval_id, CURDATE(), 'Performance below 80 - consultation required (Updated)', 'scheduled')");
        }
        $success = "✅ Evaluation updated! New Total: $total/100 (Below 80 - Consultation required)";
    }
}

// Handle consultation update
if (isset($_POST['update_consultation'])) {
    $consult_id = intval($_POST['consult_id']);
    $notes = sanitize($_POST['consult_notes']);
    $status = sanitize($_POST['consult_status']);
    $conn->query("UPDATE consultations SET notes='$notes', status='$status' WHERE id=$consult_id");
    $success = "✅ Consultation updated!";
}

$today = date('Y-m-d');

// Get team members with evaluation status
$members_query = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM performance WHERE employee_id = e.id AND date = '$today') as evaluated_today FROM employees e WHERE e.team_id = $team_id AND e.role = 'employee' ORDER BY e.name");
$today_evals = $conn->query("SELECT p.*, e.name as emp_name, e.employee_id as emp_code FROM performance p JOIN employees e ON p.employee_id = e.id WHERE p.date = '$today' AND e.team_id = $team_id");
$evaluations = $conn->query("SELECT p.*, e.name as emp_name, e.employee_id as emp_code FROM performance p JOIN employees e ON p.employee_id = e.id WHERE e.team_id = $team_id ORDER BY p.date DESC, p.id DESC LIMIT 30");
$consultations = $conn->query("SELECT c.*, e.name as emp_name, e.employee_id as emp_code, p.total FROM consultations c JOIN employees e ON c.employee_id = e.id JOIN performance p ON c.performance_id = p.id WHERE c.team_lead_id = $tl_id AND c.status != 'cancelled' ORDER BY c.status ASC, c.created_at DESC");
$notices = $conn->query("SELECT * FROM notices WHERE type = 'general' OR team_id = $team_id OR created_by = $tl_id ORDER BY created_at DESC LIMIT 15");

$total_members = $conn->query("SELECT COUNT(*) as total FROM employees WHERE team_id = $team_id AND role = 'employee'")->fetch_assoc()['total'] ?? 0;
$evaluated_today = $conn->query("SELECT COUNT(DISTINCT employee_id) as total FROM performance WHERE date = '$today' AND employee_id IN (SELECT id FROM employees WHERE team_id = $team_id)")->fetch_assoc()['total'] ?? 0;
$below_80 = $conn->query("SELECT COUNT(DISTINCT employee_id) as total FROM performance WHERE total < 80 AND employee_id IN (SELECT id FROM employees WHERE team_id = $team_id)")->fetch_assoc()['total'] ?? 0;
$pending_consult = $conn->query("SELECT COUNT(*) as total FROM consultations WHERE team_lead_id = $tl_id AND status = 'scheduled'")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Lead Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --pink: #E91E63; --pink-dark: #C2185B; --pink-light: #FCE4EC; --white: #FFFFFF; --gray-bg: #F1F5F9; --text-dark: #1E293B; --text-gray: #64748B; --border: #E2E8F0; --danger: #EF4444; --warning: #F59E0B; --success: #10B981; --sidebar-width: 260px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--gray-bg); display: flex; min-height: 100vh; }
        
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, #E91E63 0%, #AD1457 100%); color: white; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-shadow: 4px 0 20px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header .logo { font-size: 32px; font-weight: 800; letter-spacing: 2px; }
        .sidebar-header .team-name { font-size: 14px; opacity: 0.9; margin-top: 5px; font-weight: 600; }
        .sidebar-header .title { font-size: 10px; opacity: 0.7; text-transform: uppercase; letter-spacing: 2px; }
        .sidebar-nav { padding: 15px 0; flex: 1; }
        .sidebar-nav .nav-section { padding: 12px 25px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.6; font-weight: 600; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 25px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.3s; border-left: 3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.15); border-left-color: white; color: white; }
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(255,255,255,0.2); }
        .sidebar-footer .user-info { margin-bottom: 10px; font-size: 12px; }
        .logout-btn { display: block; text-align: center; padding: 8px; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 6px; font-size: 13px; }

        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; }
        .top-bar { background: white; padding: 18px 35px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; }
        .top-bar .page-title { font-size: 20px; font-weight: 700; color: var(--text-dark); }
        .digital-clock { font-size: 26px; font-weight: 700; color: var(--pink); font-family: 'Courier New', monospace; }
        .clock-date { font-size: 11px; color: var(--text-gray); text-align: right; }
        .content-area { padding: 25px 35px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 14px; border: 1px solid var(--border); display: flex; align-items: center; gap: 15px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; }
        .stat-icon.blue { background: #3B82F6; } .stat-icon.red { background: #EF4444; } .stat-icon.orange { background: #F59E0B; } .stat-icon.green { background: #10B981; }
        .stat-info h3 { font-size: 22px; font-weight: 700; } .stat-info p { font-size: 10px; color: var(--text-gray); text-transform: uppercase; }
        
        .card { background: white; border-radius: 14px; padding: 25px; border: 1px solid var(--border); margin-bottom: 20px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; border-bottom: 2px solid var(--pink-light); padding-bottom: 10px; }
        .card-header h3 { font-size: 16px; color: var(--text-dark); }
        
        .table-wrapper { overflow-x: auto; border-radius: 10px; border: 1px solid var(--border); max-height: 400px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        table th { background: #FDF2F8; color: var(--pink-dark); padding: 10px; text-align: left; font-weight: 700; font-size: 10px; text-transform: uppercase; position: sticky; top: 0; z-index: 5; }
        table td { padding: 8px 10px; border-bottom: 1px solid var(--border); }
        table tbody tr:hover { background: #FFF5F8; }
        table tbody tr.evaluated-row { background: #F0FDF4; }
        
        .badge { padding: 3px 8px; border-radius: 15px; font-size: 10px; font-weight: 700; }
        .badge-danger { background: #FEE2E2; color: #991B1B; } .badge-warning { background: #FEF3C7; color: #92400E; } .badge-success { background: #D1FAE5; color: #065F46; }
        
        .btn { padding: 7px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: var(--pink); color: white; } .btn-primary:hover { background: var(--pink-dark); }
        .btn-warning { background: #F59E0B; color: white; } .btn-warning:hover { background: #D97706; }
        .btn-sm { padding: 4px 8px; font-size: 10px; }
        
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 10px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 10px; border: 2px solid var(--border); border-radius: 6px; font-size: 12px; outline: none; background: #FAFAFA; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--pink); background: white; }
        .marks-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 15px; font-size: 13px; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 14px; padding: 25px; width: 90%; max-width: 500px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-box h3 { color: var(--pink); margin-bottom: 15px; text-align: center; font-size: 18px; }
        
        .section { display: none; }
        .section.active { display: block; }
        .notice-card { padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 4px solid var(--pink); background: #F8FAFC; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        @media (max-width: 1024px) { .two-col { grid-template-columns: 1fr; } .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .sidebar { width: 60px; } .sidebar .nav-section, .sidebar-header .team-name, .sidebar-header .title, .sidebar-footer .user-info { display: none; } .sidebar-header { padding: 15px 10px; } .sidebar-nav a { justify-content: center; padding: 12px; } .main-content { margin-left: 60px; } .marks-row { grid-template-columns: repeat(3, 1fr); } .content-area { padding: 15px; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">bK</div>
        <div class="team-name"><?php echo $team ? htmlspecialchars($team['team_name']) : 'No Team'; ?></div>
        <div class="title">Team Lead Panel</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="#" class="active" onclick="showSection('dashboard', this)"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="#" onclick="showSection('evaluate', this)"><i class="fas fa-edit"></i> Evaluate Member</a>
        <a href="#" onclick="showSection('performance', this)"><i class="fas fa-chart-bar"></i> Performance History</a>
        <a href="#" onclick="showSection('consultations', this)"><i class="fas fa-comments"></i> Consultations</a>
        <div class="nav-section">Reports</div>
        <a href="#" onclick="showSection('export-data', this)"><i class="fas fa-download"></i> Previous Data</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info"><strong><?php echo htmlspecialchars($tl_name); ?></strong><br><small><?php echo $tl_full_id; ?></small></div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title" id="pageTitle">📊 Dashboard Overview</div>
        <div style="text-align:right;"><div class="digital-clock" id="digitalClock">00:00:00</div><div class="clock-date" id="clockDate"></div></div>
    </div>

    <div class="content-area">
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- DASHBOARD -->
        <div id="section-dashboard" class="section active">
            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $total_members; ?></h3><p>Team Members</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?php echo $evaluated_today; ?>/<?php echo $total_members; ?></h3><p>Evaluated Today</p></div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h3><?php echo $below_80; ?></h3><p>Below 80</p></div></div>
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-comment-dots"></i></div><div class="stat-info"><h3><?php echo $pending_consult; ?></h3><p>Pending Consults</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-clipboard-check"></i> Today's Evaluation Status</h3></div>
                <div class="table-wrapper"><table><thead><tr><th>Employee</th><th>ID</th><th>Designation</th><th>Status</th></tr></thead><tbody>
                    <?php if($members_query): mysqli_data_seek($members_query,0); while($m=$members_query->fetch_assoc()): ?>
                        <tr class="<?php echo $m['evaluated_today']>0?'evaluated-row':''; ?>">
                            <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                            <td><?php echo $m['employee_id']; ?></td>
                            <td><?php echo $m['designation']; ?></td>
                            <td><?php echo $m['evaluated_today']>0?'<span style="color:#10B981;">✅ Evaluated</span>':'<span style="color:#F59E0B;">⏳ Pending</span>'; ?></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody></table></div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-bell"></i> Recent Notices</h3></div>
                <?php if($notices && $notices->num_rows>0): $c=0; mysqli_data_seek($notices,0); while($n=$notices->fetch_assoc()): if($c++>=5)break; ?>
                    <div class="notice-card"><strong><?php echo htmlspecialchars($n['title']); ?></strong><p style="font-size:11px;color:#666;"><?php echo htmlspecialchars(substr($n['description'],0,120)); ?>...</p></div>
                <?php endwhile; else: ?><p style="color:#999;text-align:center;">No notices</p><?php endif; ?>
            </div>
        </div>

        <!-- 3. EVALUATE MEMBER (IDs visible) -->
        <div id="section-evaluate" class="section">
            <div class="two-col">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-edit"></i> New Evaluation</h3></div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Member (Name + ID)</label>
                            <select name="employee_id" required>
                                <option value="">Choose Member...</option>
                                <?php if($members_query): mysqli_data_seek($members_query,0); while($m=$members_query->fetch_assoc()): 
                                    $disabled = $m['evaluated_today'] > 0 ? 'disabled' : '';
                                    $label = $m['evaluated_today'] > 0 ? ' [Evaluated Today]' : '';
                                ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo $disabled; ?>>
                                        <?php echo htmlspecialchars($m['name'] . ' - ' . $m['employee_id'] . $label); ?>
                                    </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo $today; ?>" readonly></div>
                        <div class="marks-row">
                            <div class="form-group"><label>CSAT</label><input type="number" name="csat" min="0" max="20" step="0.01" required></div>
                            <div class="form-group"><label>Tickets</label><input type="number" name="tickets" min="0" max="20" step="0.01" required></div>
                            <div class="form-group"><label>FCR</label><input type="number" name="fcr" min="0" max="20" step="0.01" required></div>
                            <div class="form-group"><label>Resolution</label><input type="number" name="resolution_time" min="0" max="20" step="0.01" required></div>
                            <div class="form-group"><label>Response</label><input type="number" name="response_time" min="0" max="20" step="0.01" required></div>
                        </div>
                        <div class="form-group"><label>Comments</label><textarea name="comments" rows="2"></textarea></div>
                        <button type="submit" name="submit_eval" class="btn btn-primary" style="width:100%;">💾 Save Evaluation</button>
                    </form>
                </div>
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-check-circle"></i> Evaluated Today (<?php echo $evaluated_today; ?>)</h3></div>
                    <div class="table-wrapper"><table><thead><tr><th>Employee</th><th>ID</th><th>Total</th><th>Action</th></tr></thead><tbody>
                        <?php if($today_evals && $today_evals->num_rows>0): while($e=$today_evals->fetch_assoc()): ?>
                            <tr class="evaluated-row">
                                <td><?php echo htmlspecialchars($e['emp_name']); ?></td>
                                <td><?php echo $e['emp_code']; ?></td>
                                <td><span class="badge <?php echo $e['total']>=80?'badge-success':($e['total']>=60?'badge-warning':'badge-danger'); ?>"><?php echo $e['total']; ?></span></td>
                                <td><button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $e['id']; ?>,<?php echo $e['csat']; ?>,<?php echo $e['tickets']; ?>,<?php echo $e['fcr']; ?>,<?php echo $e['resolution_time']; ?>,<?php echo $e['response_time']; ?>,"<?php echo addslashes($e['comments']); ?>")'>✏️ Edit</button></td>
                            </tr>
                        <?php endwhile; else: ?><tr><td colspan="4" style="text-align:center;color:#999;">No evaluations yet</td></tr><?php endif; ?>
                    </tbody></table></div>
                </div>
            </div>
        </div>

        <!-- 2. PERFORMANCE HISTORY (with reason tracking) -->
        <div id="section-performance" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-history"></i> Performance History</h3></div>
                <div class="table-wrapper" style="max-height:500px;"><table><thead><tr><th>Employee</th><th>ID</th><th>Date</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th><th>Comments/History</th><th>Action</th></tr></thead><tbody>
                    <?php if($evaluations && $evaluations->num_rows>0): while($e=$evaluations->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($e['emp_name']); ?></strong></td>
                            <td><?php echo $e['emp_code']; ?></td>
                            <td><?php echo date('d/m/Y',strtotime($e['date'])); ?></td>
                            <td><?php echo $e['csat']; ?></td><td><?php echo $e['tickets']; ?></td><td><?php echo $e['fcr']; ?></td><td><?php echo $e['resolution_time']; ?></td><td><?php echo $e['response_time']; ?></td>
                            <td><span class="badge <?php echo $e['total']>=80?'badge-success':($e['total']>=60?'badge-warning':'badge-danger'); ?>"><?php echo $e['total']; ?></span></td>
                            <td style="max-width:120px;font-size:10px;color:#666;"><?php echo htmlspecialchars(substr($e['comments'],0,60)); ?></td>
                            <td><button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $e['id']; ?>,<?php echo $e['csat']; ?>,<?php echo $e['tickets']; ?>,<?php echo $e['fcr']; ?>,<?php echo $e['resolution_time']; ?>,<?php echo $e['response_time']; ?>,"<?php echo addslashes($e['comments']); ?>")'>✏️ Edit</button></td>
                        </tr>
                    <?php endwhile; endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <!-- CONSULTATIONS -->
        <div id="section-consultations" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-comments"></i> Active Consultations</h3><span class="badge badge-warning"><?php echo $pending_consult; ?> Pending</span></div>
                <?php if($consultations && $consultations->num_rows>0): while($c=$consultations->fetch_assoc()): ?>
                    <div style="background:<?php echo $c['status']=='scheduled'?'#FFF7ED':'#F0FDF4';?>;padding:14px;border-radius:10px;margin-bottom:10px;border-left:4px solid <?php echo $c['status']=='scheduled'?'#F59E0B':'#10B981';?>;">
                        <strong><?php echo htmlspecialchars($c['emp_name']); ?> (<?php echo $c['emp_code']; ?>)</strong>
                        <span class="badge <?php echo $c['status']=='scheduled'?'badge-warning':'badge-success';?>" style="float:right;"><?php echo ucfirst($c['status']); ?></span>
                        <p style="font-size:11px;color:#666;">Score: <?php echo $c['total']; ?>/100 | <?php echo $c['consultation_date']; ?></p>
                        <p style="font-size:12px;"><?php echo htmlspecialchars($c['notes']); ?></p>
                        <button class="btn btn-sm btn-primary" onclick='openConsultModal(<?php echo $c['id']; ?>,"<?php echo addslashes($c['notes']); ?>","<?php echo $c['status']; ?>")'>Update</button>
                    </div>
                <?php endwhile; else: ?><p style="text-align:center;color:#10B981;padding:30px;">✅ No active consultations</p><?php endif; ?>
            </div>
        </div>

        <!-- EXPORT -->
        <div id="section-export-data" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-download"></i> Export Data</h3></div>
                <form method="GET" action="export_performance.php" target="_blank">
                    <div class="form-group"><label>Type</label><select name="type" id="exportType" required onchange="toggleExport()"><option value="all">All Members</option><option value="specific">Specific Member</option></select></div>
                    <div class="form-group" id="empExport" style="display:none;"><label>Member</label><select name="emp_id"><?php $ex=$conn->query("SELECT id,name,employee_id FROM employees WHERE team_id=$team_id AND role='employee'"); while($e=$ex->fetch_assoc()): ?><option value="<?php echo $e['id']; ?>"><?php echo $e['name'].' ('.$e['employee_id'].')'; ?></option><?php endwhile; ?></select></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;"><div class="form-group"><label>Start</label><input type="date" name="start_date" required value="<?php echo date('Y-m-01'); ?>"></div><div class="form-group"><label>End</label><input type="date" name="end_date" required value="<?php echo date('Y-m-d'); ?>"></div></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">📥 Download Excel</button>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- EDIT MODAL (with reason field) -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h3>✏️ Edit Evaluation</h3>
        <form method="POST">
            <input type="hidden" name="eval_id" id="edit_eval_id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group"><label>CSAT</label><input type="number" id="edit_csat" name="edit_csat" step="0.01" required></div>
                <div class="form-group"><label>Tickets</label><input type="number" id="edit_tickets" name="edit_tickets" step="0.01" required></div>
                <div class="form-group"><label>FCR</label><input type="number" id="edit_fcr" name="edit_fcr" step="0.01" required></div>
                <div class="form-group"><label>Resolution</label><input type="number" id="edit_resolution_time" name="edit_resolution_time" step="0.01" required></div>
                <div class="form-group"><label>Response</label><input type="number" id="edit_response_time" name="edit_response_time" step="0.01" required></div>
            </div>
            <div class="form-group"><label>⚠️ Reason for Update (Required)</label><textarea name="update_reason" id="update_reason" rows="2" required placeholder="Explain why you are updating these marks..."></textarea></div>
            <div class="form-group"><label>New Comments</label><textarea name="edit_comments" id="edit_comments" rows="2"></textarea></div>
            <div id="editTotal" style="text-align:center;font-size:22px;font-weight:700;color:#E91E63;margin:10px 0;">Total: 0/100</div>
            <div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeModal('editModal')">Cancel</button><button type="submit" name="update_eval" class="btn btn-primary" style="flex:1;">💾 Update</button></div>
        </form>
    </div>
</div>

<!-- CONSULT MODAL -->
<div id="consultModal" class="modal-overlay">
    <div class="modal-box">
        <h3>💬 Update Consultation</h3>
        <form method="POST">
            <input type="hidden" name="consult_id" id="consult_id">
            <div class="form-group"><label>Notes</label><textarea name="consult_notes" id="consult_notes" rows="3"></textarea></div>
            <div class="form-group"><label>Status</label><select name="consult_status" id="consult_status"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
            <div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeModal('consultModal')">Cancel</button><button type="submit" name="update_consultation" class="btn btn-primary" style="flex:1;">Update</button></div>
        </form>
    </div>
</div>

<script>
    function updateClock(){const n=new Date();document.getElementById('digitalClock').textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate').textContent=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][n.getDay()]+', '+n.getDate()+' '+['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][n.getMonth()]+' '+n.getFullYear();}
    updateClock();setInterval(updateClock,1000);
    
    function showSection(id,btn){document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));document.querySelectorAll('.sidebar-nav a').forEach(a=>a.classList.remove('active'));document.getElementById('section-'+id).classList.add('active');if(btn)btn.classList.add('active');document.getElementById('pageTitle').textContent={'dashboard':'📊 Dashboard Overview','evaluate':'📝 Evaluate Team Member','performance':'📊 Performance History','consultations':'💬 Consultations','export-data':'📥 Export Previous Data'}[id];}
    
    function toggleExport(){document.getElementById('empExport').style.display=document.getElementById('exportType').value==='specific'?'block':'none';}
    
    function openEditModal(id,csat,tickets,fcr,res,resp,comments){document.getElementById('edit_eval_id').value=id;document.getElementById('edit_csat').value=csat;document.getElementById('edit_tickets').value=tickets;document.getElementById('edit_fcr').value=fcr;document.getElementById('edit_resolution_time').value=res;document.getElementById('edit_response_time').value=resp;document.getElementById('edit_comments').value='';document.getElementById('update_reason').value='';updateEditTotal();document.getElementById('editModal').classList.add('active');}
    
    function closeModal(id){document.getElementById(id).classList.remove('active');}
    
    function openConsultModal(id,notes,status){document.getElementById('consult_id').value=id;document.getElementById('consult_notes').value=notes;document.getElementById('consult_status').value=status;document.getElementById('consultModal').classList.add('active');}
    
    document.querySelectorAll('#editModal input[type="number"]').forEach(i=>i.addEventListener('input',updateEditTotal));
    function updateEditTotal(){const t=(parseFloat(document.getElementById('edit_csat').value)||0)+(parseFloat(document.getElementById('edit_tickets').value)||0)+(parseFloat(document.getElementById('edit_fcr').value)||0)+(parseFloat(document.getElementById('edit_resolution_time').value)||0)+(parseFloat(document.getElementById('edit_response_time').value)||0);document.getElementById('editTotal').textContent='Total: '+t.toFixed(2)+'/100';document.getElementById('editTotal').style.color=t>=80?'#10B981':(t>=60?'#F59E0B':'#EF4444');}
    
    document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeModal('editModal');});
    document.getElementById('consultModal').addEventListener('click',function(e){if(e.target===this)closeModal('consultModal');});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal('editModal');closeModal('consultModal');}});
</script>
</body>
</html>