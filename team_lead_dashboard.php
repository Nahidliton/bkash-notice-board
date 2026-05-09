<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

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

// Handle new evaluation
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
    
    $check_sql = "SELECT id FROM performance WHERE employee_id = $emp_id AND evaluator_id = $tl_id AND date = '$date'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result && $check_result->num_rows > 0) {
        $error = "❌ This employee has already been evaluated today! Use the edit option to update marks.";
    } else {
        $sql = "INSERT INTO performance (employee_id, evaluator_id, csat, tickets, fcr, resolution_time, response_time, total, comments, date) VALUES ($emp_id, $tl_id, $csat, $tickets, $fcr, $resolution_time, $response_time, $total, '$comments', '$date')";
        if ($conn->query($sql)) {
            if ($total < 80) {
                $perf_id = $conn->insert_id;
                $conn->query("INSERT INTO consultations (employee_id, team_lead_id, performance_id, consultation_date, notes, status) VALUES ($emp_id, $tl_id, $perf_id, CURDATE(), 'Performance below 80 - consultation required', 'scheduled')");
                $conn->query("UPDATE performance SET status = 'consulted' WHERE id = $perf_id");
                $notice_title = "Consultation Required";
                $notice_desc = "Your performance score is $total/100 (below 80). Please schedule a consultation with your Team Lead.";
                $conn->query("INSERT INTO notices (title, description, type, employee_id, created_by) VALUES ('$notice_title', '$notice_desc', 'individual', $emp_id, $tl_id)");
            }
            $success = "✅ Evaluation saved! Total: $total/100";
        } else { $error = "❌ Error: " . $conn->error; }
    }
}

// Handle mark update
if (isset($_POST['update_eval'])) {
    $eval_id = intval($_POST['eval_id']);
    $csat = floatval($_POST['edit_csat']);
    $tickets = floatval($_POST['edit_tickets']);
    $fcr = floatval($_POST['edit_fcr']);
    $resolution_time = floatval($_POST['edit_resolution_time']);
    $response_time = floatval($_POST['edit_response_time']);
    $comments = sanitize($_POST['edit_comments']);
    $total = $csat + $tickets + $fcr + $resolution_time + $response_time;
    $conn->query("UPDATE performance SET csat=$csat, tickets=$tickets, fcr=$fcr, resolution_time=$resolution_time, response_time=$response_time, total=$total, comments='$comments' WHERE id=$eval_id AND evaluator_id=$tl_id");
    $conn->query("UPDATE performance SET status = " . ($total < 80 ? "'consulted'" : "'reviewed'") . " WHERE id = $eval_id");
    $success = "✅ Evaluation updated! New Total: $total/100";
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
$members_query = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM performance WHERE employee_id = e.id AND date = '$today') as evaluated_today FROM employees e WHERE e.team_id = $team_id AND e.role = 'employee' ORDER BY e.name");
$today_evals = $conn->query("SELECT p.*, e.name as emp_name FROM performance p JOIN employees e ON p.employee_id = e.id WHERE p.date = '$today' AND e.team_id = $team_id");
$evaluations = $conn->query("SELECT p.*, e.name as emp_name FROM performance p JOIN employees e ON p.employee_id = e.id WHERE e.team_id = $team_id ORDER BY p.date DESC, p.id DESC LIMIT 30");
$consultations = $conn->query("SELECT c.*, e.name as emp_name, p.total FROM consultations c JOIN employees e ON c.employee_id = e.id JOIN performance p ON c.performance_id = p.id WHERE c.team_lead_id = $tl_id ORDER BY c.created_at DESC");
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
    <title>Team Lead Dashboard - bKash Notice Board</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --pink: #E91E63; --pink-dark: #C2185B; --pink-light: #FCE4EC; --white: #FFFFFF; --gray-bg: #F1F5F9; --text-dark: #1E293B; --text-gray: #64748B; --border: #E2E8F0; --danger: #EF4444; --warning: #F59E0B; --success: #10B981; --sidebar-width: 260px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--gray-bg); display: flex; min-height: 100vh; }
        
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, #E91E63 0%, #AD1457 100%); color: white; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-shadow: 4px 0 20px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
        .sidebar-header { padding: 30px 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header .logo { font-size: 36px; font-weight: 800; letter-spacing: 3px; }
        .sidebar-header .team-name { font-size: 14px; opacity: 0.9; margin-top: 8px; font-weight: 600; }
        .sidebar-header .title { font-size: 10px; opacity: 0.7; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px; }
        .sidebar-nav { padding: 15px 0; flex: 1; }
        .sidebar-nav .nav-section { padding: 12px 25px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.6; margin-top: 10px; font-weight: 600; }
        .sidebar-nav a { display: flex; align-items: center; gap: 14px; padding: 13px 25px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.3s; border-left: 3px solid transparent; font-weight: 500; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.12); border-left-color: white; color: white; }
        .sidebar-nav a.active { background: rgba(255,255,255,0.18); border-left-color: white; color: white; font-weight: 600; }
        .sidebar-nav a i { width: 20px; text-align: center; font-size: 15px; }
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(255,255,255,0.2); }
        .sidebar-footer .user-info { margin-bottom: 12px; font-size: 13px; line-height: 1.5; }
        .sidebar-footer .logout-btn { display: block; text-align: center; padding: 10px; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600; transition: all 0.3s; border: 1px solid rgba(255,255,255,0.2); }
        .sidebar-footer .logout-btn:hover { background: rgba(255,255,255,0.25); }
        
        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; }
        .top-bar { background: white; padding: 18px 35px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .top-bar .page-title { font-size: 22px; color: var(--text-dark); font-weight: 700; }
        .top-bar .top-right { display: flex; align-items: center; gap: 30px; }
        .digital-clock { font-size: 30px; font-weight: 700; color: var(--pink); font-family: 'Courier New', monospace; letter-spacing: 3px; }
        .clock-date { font-size: 12px; color: var(--text-gray); text-align: center; font-weight: 500; }
        .content-area { padding: 30px 35px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 22px; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 18px; transition: all 0.3s; border: 1px solid var(--border); }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; flex-shrink: 0; }
        .stat-icon.blue { background: linear-gradient(135deg, #3B82F6, #2563EB); }
        .stat-icon.red { background: linear-gradient(135deg, #EF4444, #DC2626); }
        .stat-icon.orange { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .stat-icon.green { background: linear-gradient(135deg, #10B981, #059669); }
        .stat-info .stat-value { font-size: 26px; font-weight: 700; color: var(--text-dark); line-height: 1; }
        .stat-info .stat-label { font-size: 11px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; font-weight: 600; }
        
        .card { background: white; border-radius: 16px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid var(--border); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 2px solid var(--pink-light); }
        .card-header h3 { color: var(--text-dark); font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 700; }
        .card-header h3 i { color: var(--pink); }
        .badge-count { background: var(--pink-light); color: var(--pink-dark); padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; }
        
        .table-wrapper { overflow-x: auto; max-height: 450px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table thead { position: sticky; top: 0; z-index: 10; }
        table th { background: #FDF2F8; color: var(--pink-dark); padding: 13px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; }
        table td { padding: 12px; border-bottom: 1px solid var(--border); color: var(--text-dark); }
        table tbody tr:hover { background: #FFF5F8; }
        table tbody tr.evaluated-row { background: #F0FDF4; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-info { background: #DBEAFE; color: #1E40AF; }
        
        .btn { padding: 9px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s; letter-spacing: 0.5px; white-space: nowrap; }
        .btn-primary { background: var(--pink); color: white; }
        .btn-primary:hover { background: var(--pink-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(233,30,99,0.3); }
        .btn-warning { background: #F59E0B; color: white; }
        .btn-warning:hover { background: #D97706; }
        .btn-sm { padding: 6px 12px; font-size: 11px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 2px solid var(--border); border-radius: 10px; font-size: 14px; outline: none; transition: all 0.3s; font-family: inherit; background: #FAFAFA; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--pink); background: white; box-shadow: 0 0 0 3px rgba(233,30,99,0.08); }
        .marks-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        
        .alert { padding: 16px 22px; border-radius: 12px; margin-bottom: 22px; font-size: 14px; display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        .section { display: none; }
        .section.active { display: block; }
        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-gray); }
        .empty-state i { font-size: 50px; color: #D1D5DB; margin-bottom: 15px; display: block; }
        
        .notice-card { padding: 14px; border-radius: 10px; margin-bottom: 10px; border-left: 4px solid #E91E63; background: #F8FAFC; }
        .notice-urgent { background: #FEF2F2; border-color: #EF4444; }
        
        .evaluated-badge { display: inline-block; padding: 4px 10px; background: #D1FAE5; color: #065F46; border-radius: 20px; font-size: 10px; font-weight: 700; }
        .not-evaluated-badge { display: inline-block; padding: 4px 10px; background: #FEF3C7; color: #92400E; border-radius: 20px; font-size: 10px; font-weight: 700; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 550px; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-box h3 { color: #E91E63; margin-bottom: 20px; text-align: center; font-size: 20px; }
        
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
        .info-box { padding: 15px; border-radius: 10px; margin-top: 20px; }
        .info-box.blue { background: #F0F9FF; border-left: 4px solid #3B82F6; }
        .info-box ol { margin: 10px 0 0 20px; font-size: 13px; color: #64748B; }
        
        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(2, 1fr); } .two-col { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            .sidebar { width: 65px; }
            .sidebar .nav-text, .sidebar-header .team-name, .sidebar-header .title, .sidebar-footer .user-info, .sidebar-nav .nav-section { display: none; }
            .sidebar-header { padding: 20px 10px; }
            .sidebar-header .logo { font-size: 24px; }
            .sidebar-nav a { justify-content: center; padding: 15px; }
            .sidebar-nav a i { font-size: 18px; }
            .main-content { margin-left: 65px; }
            .stats-row { grid-template-columns: 1fr; }
            .marks-row { grid-template-columns: repeat(3, 1fr); }
            .digital-clock { font-size: 20px; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header"><div class="logo">bK</div><div class="team-name"><?php echo $team ? htmlspecialchars($team['team_name']) : 'No Team'; ?></div><div class="title">Team Lead</div></div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="#" class="active" onclick="showSection('dashboard')"><i class="fas fa-th-large"></i> <span class="nav-text">Dashboard</span></a>
        <a href="#" onclick="showSection('evaluate')"><i class="fas fa-edit"></i> <span class="nav-text">Evaluate Member</span><?php if($total_members-$evaluated_today>0): ?><span style="background:#F59E0B;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto;"><?php echo $total_members-$evaluated_today; ?></span><?php endif; ?></a>
        <a href="#" onclick="showSection('performance')"><i class="fas fa-chart-bar"></i> <span class="nav-text">Team Performance</span></a>
        <a href="#" onclick="showSection('consultations')"><i class="fas fa-comments"></i> <span class="nav-text">Consultations</span><?php if($pending_consult>0): ?><span style="background:#EF4444;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto;"><?php echo $pending_consult; ?></span><?php endif; ?></a>
        <div class="nav-section">Reports</div>
        <a href="#" onclick="showSection('export-data')"><i class="fas fa-download"></i> <span class="nav-text">Previous Data</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info"><strong><?php echo htmlspecialchars($tl_name); ?></strong><br><small><?php echo $tl_full_id; ?></small></div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title" id="pageTitle">Dashboard Overview</div>
        <div class="top-right"><div><div class="digital-clock" id="digitalClock">00:00:00</div><div class="clock-date" id="clockDate"></div></div></div>
    </div>

    <div class="content-area">
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- DASHBOARD -->
        <div id="section-dashboard" class="section active">
            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-value"><?php echo $total_members; ?></div><div class="stat-label">Team Members</div></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $evaluated_today; ?>/<?php echo $total_members; ?></div><div class="stat-label">Evaluated Today</div></div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $below_80; ?></div><div class="stat-label">Below 80</div></div></div>
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-comment-dots"></i></div><div class="stat-info"><div class="stat-value"><?php echo $pending_consult; ?></div><div class="stat-label">Pending Consultations</div></div></div>
            </div>
            <div class="card"><div class="card-header"><h3><i class="fas fa-clipboard-check"></i> Today's Evaluation Status</h3><span class="badge-count"><?php echo date('d M Y'); ?></span></div>
                <?php if($members_query && $members_query->num_rows>0): ?><div class="table-wrapper" style="max-height:300px;"><table><thead><tr><th>Employee</th><th>ID</th><th>Designation</th><th>Status</th></tr></thead><tbody>
                    <?php while($mem=$members_query->fetch_assoc()): ?><tr class="<?php echo $mem['evaluated_today']>0?'evaluated-row':''; ?>"><td><strong><?php echo htmlspecialchars($mem['name']); ?></strong></td><td><?php echo $mem['employee_id']; ?></td><td><?php echo $mem['designation']; ?></td><td><?php echo $mem['evaluated_today']>0?'<span class="evaluated-badge">✅ Evaluated</span>':'<span class="not-evaluated-badge">⏳ Pending</span>'; ?></td></tr><?php endwhile; ?>
                </tbody></table></div><?php endif; ?>
            </div>
            <div class="card"><div class="card-header"><h3><i class="fas fa-bell"></i> Recent Notices</h3></div>
                <?php if($notices && $notices->num_rows>0): $c=0; mysqli_data_seek($notices,0); while($n=$notices->fetch_assoc()): if($c++>=5)break; ?><div class="notice-card <?php echo $n['type']=='urgent'?'notice-urgent':''; ?>"><strong><?php echo htmlspecialchars($n['title']); ?></strong><span class="badge <?php echo $n['type']=='urgent'?'badge-danger':'badge-info';?>" style="float:right;"><?php echo ucfirst($n['type']); ?></span><p style="font-size:12px;color:#64748B;"><?php echo htmlspecialchars(substr($n['description'],0,120)); ?>...</p><small style="color:#94A3B8;"><?php echo date('d M Y',strtotime($n['created_at'])); ?></small></div><?php endwhile; else: ?><div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notices.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- EVALUATE MEMBER -->
        <div id="section-evaluate" class="section">
            <div class="two-col">
                <div class="card"><div class="card-header"><h3><i class="fas fa-edit"></i> Evaluate Team Member</h3></div>
                    <form method="POST"><div class="form-group"><label>Select Employee</label><select name="employee_id" required><option value="">Choose...</option><?php if($members_query): mysqli_data_seek($members_query,0); while($mem=$members_query->fetch_assoc()): $disabled=$mem['evaluated_today']>0?'disabled':''; $label=$mem['evaluated_today']>0?' (Already Evaluated Today)':''; ?><option value="<?php echo $mem['id']; ?>" <?php echo $disabled; ?>><?php echo htmlspecialchars($mem['name'].' ('.$mem['employee_id'].')'.$label); ?></option><?php endwhile; endif; ?></select></div>
                    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo $today; ?>" readonly></div>
                    <div class="marks-row"><div class="form-group"><label>CSAT</label><input type="number" name="csat" min="0" max="20" step="0.01" required></div><div class="form-group"><label>Tickets</label><input type="number" name="tickets" min="0" max="20" step="0.01" required></div><div class="form-group"><label>FCR</label><input type="number" name="fcr" min="0" max="20" step="0.01" required></div><div class="form-group"><label>Resolution</label><input type="number" name="resolution_time" min="0" max="20" step="0.01" required></div><div class="form-group"><label>Response</label><input type="number" name="response_time" min="0" max="20" step="0.01" required></div></div>
                    <div class="form-group"><label>Comments</label><textarea name="comments" rows="2"></textarea></div><button type="submit" name="submit_eval" class="btn btn-primary" style="width:100%;">Save Evaluation</button></form>
                </div>
                <div class="card"><div class="card-header"><h3><i class="fas fa-check-circle"></i> Today's Evaluations</h3><span class="badge-count"><?php echo $evaluated_today; ?> Done</span></div>
                    <?php if($today_evals && $today_evals->num_rows>0): ?><div class="table-wrapper" style="max-height:400px;"><table><thead><tr><th>Employee</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Total</th><th>Action</th></tr></thead><tbody><?php while($eval=$today_evals->fetch_assoc()): ?><tr class="evaluated-row"><td><strong><?php echo htmlspecialchars($eval['emp_name']); ?></strong></td><td><?php echo $eval['csat']; ?></td><td><?php echo $eval['tickets']; ?></td><td><?php echo $eval['fcr']; ?></td><td><span class="badge <?php echo $eval['total']>=80?'badge-success':($eval['total']>=60?'badge-warning':'badge-danger'); ?>"><?php echo $eval['total']; ?></span></td><td><button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $eval['id']; ?>,<?php echo $eval['csat']; ?>,<?php echo $eval['tickets']; ?>,<?php echo $eval['fcr']; ?>,<?php echo $eval['resolution_time']; ?>,<?php echo $eval['response_time']; ?>,"<?php echo addslashes($eval['comments']); ?>")'>✏️ Edit</button></td></tr><?php endwhile; ?></tbody></table></div><?php else: ?><div class="empty-state"><i class="fas fa-clipboard"></i><p>No evaluations done today.</p></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TEAM PERFORMANCE -->
        <div id="section-performance" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-chart-bar"></i> Team Performance History</h3></div><div class="table-wrapper" style="max-height:500px;"><table><thead><tr><th>Employee</th><th>Date</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th><th>Action</th></tr></thead><tbody><?php if($evaluations && $evaluations->num_rows>0): while($eval=$evaluations->fetch_assoc()): ?><tr><td><strong><?php echo htmlspecialchars($eval['emp_name']); ?></strong></td><td><?php echo date('d/m',strtotime($eval['date'])); ?></td><td><?php echo $eval['csat']; ?></td><td><?php echo $eval['tickets']; ?></td><td><?php echo $eval['fcr']; ?></td><td><?php echo $eval['resolution_time']; ?></td><td><?php echo $eval['response_time']; ?></td><td><span class="badge <?php echo $eval['total']>=80?'badge-success':($eval['total']>=60?'badge-warning':'badge-danger'); ?>"><?php echo $eval['total']; ?></span></td><td><button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $eval['id']; ?>,<?php echo $eval['csat']; ?>,<?php echo $eval['tickets']; ?>,<?php echo $eval['fcr']; ?>,<?php echo $eval['resolution_time']; ?>,<?php echo $eval['response_time']; ?>,"<?php echo addslashes($eval['comments']); ?>")'>✏️ Edit</button></td></tr><?php endwhile; endif; ?></tbody></table></div></div>
        </div>

        <!-- CONSULTATIONS -->
        <div id="section-consultations" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-comments"></i> Consultations</h3><span class="badge-count"><?php echo $pending_consult; ?> Pending</span></div>
                <?php if($consultations && $consultations->num_rows>0): while($c=$consultations->fetch_assoc()): ?><div style="background:<?php echo $c['status']=='scheduled'?'#FFF7ED':'#F0FDF4';?>;padding:16px;border-radius:12px;margin-bottom:12px;border-left:4px solid <?php echo $c['status']=='scheduled'?'#F59E0B':'#10B981';?>;"><strong><?php echo htmlspecialchars($c['emp_name']); ?></strong><span class="badge <?php echo $c['status']=='scheduled'?'badge-warning':'badge-success';?>" style="float:right;"><?php echo ucfirst($c['status']); ?></span><p style="font-size:12px;color:#64748B;">Score: <?php echo $c['total']; ?>/100 | Date: <?php echo $c['consultation_date']; ?></p><p style="font-size:13px;"><?php echo htmlspecialchars($c['notes']); ?></p><button class="btn btn-sm btn-primary" onclick='openConsultModal(<?php echo $c['id']; ?>,"<?php echo addslashes($c['notes']); ?>","<?php echo $c['status']; ?>")'>Update</button></div><?php endwhile; else: ?><div class="empty-state"><i class="fas fa-check-circle" style="color:#10B981;"></i><p>No consultations.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- EXPORT DATA -->
        <div id="section-export-data" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-download"></i> Export Previous Data</h3></div>
                <form method="GET" action="export_performance.php" target="_blank">
                    <div class="form-group"><label>Export Type</label><select name="type" id="exportTypeTL" required onchange="toggleExportTL()"><option value="all">All Team Members Data</option><option value="specific">Specific Member</option></select></div>
                    <div class="form-group" id="empSelectTL" style="display:none;"><label>Select Team Member</label><select name="emp_id"><option value="">Choose...</option><?php $exptl=$conn->query("SELECT id,name,employee_id FROM employees WHERE team_id=$team_id AND role='employee' ORDER BY name"); if($exptl): while($e=$exptl->fetch_assoc()): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name'].' ('.$e['employee_id'].')'); ?></option><?php endwhile; endif; ?></select></div>
                    <div class="form-row"><div class="form-group"><label>Start Date</label><input type="date" name="start_date" required value="<?php echo date('Y-m-01'); ?>"></div><div class="form-group"><label>End Date</label><input type="date" name="end_date" required value="<?php echo date('Y-m-d'); ?>"></div></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-file-excel"></i> Download Excel Report</button>
                </form>
                <div class="info-box blue"><strong>Instructions:</strong><ol><li>Select <strong>All Team Members</strong> or a <strong>Specific Member</strong></li><li>Choose the <strong>Start Date</strong> and <strong>End Date</strong></li><li>Click <strong>Download Excel Report</strong></li><li>The file will download as an .xls file</li></ol></div>
            </div>
        </div>
    </div>
</main>

<!-- Edit Evaluation Modal -->
<div id="editModal" class="modal-overlay"><div class="modal-box"><h3>✏️ Edit Evaluation Marks</h3><form method="POST"><input type="hidden" name="eval_id" id="edit_eval_id"><div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"><div class="form-group"><label>CSAT</label><input type="number" id="edit_csat" name="edit_csat" step="0.01" required></div><div class="form-group"><label>Tickets</label><input type="number" id="edit_tickets" name="edit_tickets" step="0.01" required></div><div class="form-group"><label>FCR</label><input type="number" id="edit_fcr" name="edit_fcr" step="0.01" required></div><div class="form-group"><label>Resolution Time</label><input type="number" id="edit_resolution_time" name="edit_resolution_time" step="0.01" required></div><div class="form-group"><label>Response Time</label><input type="number" id="edit_response_time" name="edit_response_time" step="0.01" required></div></div><div class="form-group"><label>Comments</label><textarea id="edit_comments" name="edit_comments" rows="2"></textarea></div><div id="editTotal" style="text-align:center;font-size:24px;font-weight:700;color:#E91E63;margin:15px 0;">Total: 0/100</div><div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeEditModal()">Cancel</button><button type="submit" name="update_eval" class="btn btn-primary" style="flex:1;">Update</button></div></form></div></div>

<!-- Consultation Modal -->
<div id="consultModal" class="modal-overlay"><div class="modal-box"><h3>Update Consultation</h3><form method="POST"><input type="hidden" name="consult_id" id="consult_id"><div class="form-group"><label>Notes</label><textarea id="consult_notes" name="consult_notes" rows="3"></textarea></div><div class="form-group"><label>Status</label><select id="consult_status" name="consult_status"><option value="scheduled">Scheduled</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div><div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeConsultModal()">Cancel</button><button type="submit" name="update_consultation" class="btn btn-primary" style="flex:1;">Update</button></div></form></div></div>

<script>
    function updateClock(){const n=new Date();document.getElementById('digitalClock').textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate').textContent=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][n.getDay()]+', '+n.getDate()+' '+['January','February','March','April','May','June','July','August','September','October','November','December'][n.getMonth()]+' '+n.getFullYear();}
    updateClock();setInterval(updateClock,1000);
    
    function showSection(n){
        document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
        document.getElementById('section-'+n).classList.add('active');
        document.getElementById('pageTitle').textContent={'dashboard':'Dashboard Overview','evaluate':'Evaluate Team Member','performance':'Team Performance','consultations':'Consultations','export-data':'Export Previous Data'}[n]||'Dashboard';
        document.querySelectorAll('.sidebar-nav a').forEach(a=>a.classList.remove('active'));
        if(event&&event.target){const l=event.target.closest('a');if(l)l.classList.add('active');}
    }
    
    function toggleExportTL(){document.getElementById('empSelectTL').style.display=document.getElementById('exportTypeTL').value==='specific'?'block':'none';}
    
    function openEditModal(i,c,t,f,r,rs,cm){document.getElementById('edit_eval_id').value=i;document.getElementById('edit_csat').value=c;document.getElementById('edit_tickets').value=t;document.getElementById('edit_fcr').value=f;document.getElementById('edit_resolution_time').value=r;document.getElementById('edit_response_time').value=rs;document.getElementById('edit_comments').value=cm||'';updateEditTotal();document.getElementById('editModal').classList.add('active');}
    function closeEditModal(){document.getElementById('editModal').classList.remove('active');}
    function openConsultModal(i,n,s){document.getElementById('consult_id').value=i;document.getElementById('consult_notes').value=n;document.getElementById('consult_status').value=s;document.getElementById('consultModal').classList.add('active');}
    function closeConsultModal(){document.getElementById('consultModal').classList.remove('active');}
    
    document.querySelectorAll('#editModal input[type="number"]').forEach(i=>i.addEventListener('input',updateEditTotal));
    function updateEditTotal(){const t=(parseFloat(document.getElementById('edit_csat').value)||0)+(parseFloat(document.getElementById('edit_tickets').value)||0)+(parseFloat(document.getElementById('edit_fcr').value)||0)+(parseFloat(document.getElementById('edit_resolution_time').value)||0)+(parseFloat(document.getElementById('edit_response_time').value)||0);document.getElementById('editTotal').textContent='Total: '+t.toFixed(2)+'/100';}
    
    document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});
    document.getElementById('consultModal').addEventListener('click',function(e){if(e.target===this)closeConsultModal();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeEditModal();closeConsultModal();}});
</script>
</body>
</html>