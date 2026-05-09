<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$hr_name = $_SESSION['name'];
$hr_id = $_SESSION['user_id'];
$hr_full_id = $_SESSION['full_id'];
$success = '';
$error = '';

// Handle urgent call
if (isset($_POST['urgent_call'])) {
    $emp_id_input = sanitize($_POST['employee_id']);
    $emp_result = $conn->query("SELECT id FROM employees WHERE employee_id = '$emp_id_input'");
    if ($emp_result && $emp_result->num_rows > 0) {
        $emp_row = $emp_result->fetch_assoc();
        $emp_db_id = $emp_row['id'];
        $urgent_title = "🚨 URGENT: Meeting with HR";
        $urgent_desc = "Your recent performance has been critically flagged (below 60 marks). You are required to schedule an urgent meeting with the HR department immediately.";
        $conn->query("INSERT INTO notices (title, description, type, employee_id, created_by) VALUES ('$urgent_title', '$urgent_desc', 'urgent', $emp_db_id, $hr_id)");
        $success = "✅ Urgent call notice sent successfully!";
    } else {
        $error = "❌ Employee not found!";
    }
}

// Handle notice deletion
if (isset($_GET['delete_notice'])) {
    $notice_id = intval($_GET['delete_notice']);
    $conn->query("DELETE FROM notices WHERE id = $notice_id AND created_by = $hr_id");
    header('Location: hr_dashboard.php?deleted=1');
    exit();
}

// Handle notice update
if (isset($_POST['update_notice'])) {
    $notice_id = intval($_POST['notice_id']);
    $new_title = sanitize($_POST['update_title']);
    $new_desc = sanitize($_POST['update_description']);
    $new_type = sanitize($_POST['update_type']);
    $conn->query("UPDATE notices SET title='$new_title', description='$new_desc', type='$new_type' WHERE id=$notice_id AND created_by=$hr_id");
    $success = "✅ Notice updated successfully!";
}

// Handle team assignment
if (isset($_POST['assign_team'])) {
    $emp_id = intval($_POST['emp_id']);
    $team_id = intval($_POST['team_id']);
    $conn->query("UPDATE employees SET team_id = $team_id WHERE id = $emp_id");
    $success = "✅ Employee assigned to team successfully!";
}

// Handle add notice
if (isset($_POST['add_notice'])) {
    $notice_title = sanitize($_POST['notice_title']);
    $notice_desc = sanitize($_POST['notice_description']);
    $notice_type = sanitize($_POST['notice_type']);
    
    if (($notice_type == 'individual' || $notice_type == 'urgent') && !empty($_POST['notice_employee'])) {
        $notice_emp = intval($_POST['notice_employee']);
        $sql = "INSERT INTO notices (title, description, type, employee_id, created_by) VALUES ('$notice_title', '$notice_desc', '$notice_type', $notice_emp, $hr_id)";
    } elseif ($notice_type == 'team' && !empty($_POST['notice_team'])) {
        $notice_team = intval($_POST['notice_team']);
        $sql = "INSERT INTO notices (title, description, type, team_id, created_by) VALUES ('$notice_title', '$notice_desc', '$notice_type', $notice_team, $hr_id)";
    } else {
        $sql = "INSERT INTO notices (title, description, type, created_by) VALUES ('$notice_title', '$notice_desc', '$notice_type', $hr_id)";
    }
    if ($conn->query($sql)) { $success = "✅ Notice published successfully!"; } else { $error = "❌ Error: " . $conn->error; }
}

// Handle consultation schedule
if (isset($_POST['schedule_consult'])) {
    $consult_emp = intval($_POST['consult_emp']);
    $consult_date = sanitize($_POST['consult_date']);
    $consult_notes = sanitize($_POST['consult_notes']);
    $conn->query("INSERT INTO consultations (employee_id, team_lead_id, consultation_date, notes, status) VALUES ($consult_emp, $hr_id, '$consult_date', '$consult_notes', 'scheduled')");
    $notice_title = "Consultation Scheduled";
    $notice_desc = "You have a consultation with HR scheduled on " . date('d M Y', strtotime($consult_date)) . ". Notes: $consult_notes";
    $conn->query("INSERT INTO notices (title, description, type, employee_id, created_by) VALUES ('$notice_title', '$notice_desc', 'individual', $consult_emp, $hr_id)");
    $success = "✅ Consultation scheduled and employee notified!";
}

// Critical Performance Query
$critical_sql = "SELECT p.id as perf_id, p.employee_id, p.csat, p.tickets, p.fcr, p.resolution_time, p.response_time, p.total, p.date, p.status, e.name, e.employee_id as emp_code, e.team_id, COALESCE(t.team_name, 'Unassigned') as team_name FROM performance p JOIN employees e ON p.employee_id = e.id LEFT JOIN teams t ON e.team_id = t.id WHERE p.total < 60 ORDER BY p.total ASC";
$critical_result = $conn->query($critical_sql);
$critical_count = ($critical_result) ? $critical_result->num_rows : 0;

// Performance Tabulation
$tabulation_sql = "SELECT e.id as emp_table_id, e.name, e.employee_id, e.designation, e.team_id, COALESCE(t.team_name, 'Unassigned') as team_name, COALESCE(latest_p.csat, 0) as csat, COALESCE(latest_p.tickets, 0) as tickets, COALESCE(latest_p.fcr, 0) as fcr, COALESCE(latest_p.resolution_time, 0) as resolution_time, COALESCE(latest_p.response_time, 0) as response_time, COALESCE(latest_p.total, 0) as total, latest_p.date as last_evaluated, latest_p.status FROM employees e LEFT JOIN teams t ON e.team_id = t.id LEFT JOIN (SELECT p1.* FROM performance p1 WHERE p1.id = (SELECT MAX(p2.id) FROM performance p2 WHERE p2.employee_id = p1.employee_id)) latest_p ON e.id = latest_p.employee_id WHERE e.role = 'employee' ORDER BY e.name ASC";
$perf_table = $conn->query($tabulation_sql);

// Other queries
$unassigned = $conn->query("SELECT * FROM employees WHERE team_id IS NULL AND role = 'employee'");
$all_employees = $conn->query("SELECT * FROM employees WHERE role = 'employee' ORDER BY name");
$hr_notices = $conn->query("SELECT * FROM notices WHERE type = 'general' OR created_by = $hr_id OR (type = 'urgent' AND created_by = $hr_id) ORDER BY created_at DESC LIMIT 20");
$all_notices_count = $conn->query("SELECT COUNT(*) as total FROM notices WHERE created_by = $hr_id")->fetch_assoc()['total'] ?? 0;
$team_list = $conn->query("SELECT t.*, e.name as lead_name, (SELECT COUNT(*) FROM employees WHERE team_id = t.id) as member_count FROM teams t LEFT JOIN employees e ON t.team_lead_id = e.id");

// Stats
$total_emp = $conn->query("SELECT COUNT(*) as total FROM employees WHERE role = 'employee'")->fetch_assoc()['total'] ?? 0;
$below_60 = $conn->query("SELECT COUNT(DISTINCT employee_id) as total FROM performance WHERE total < 60")->fetch_assoc()['total'] ?? 0;
$total_teams = $conn->query("SELECT COUNT(*) as total FROM teams")->fetch_assoc()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - bKash Notice Board</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --pink: #E91E63; --pink-dark: #C2185B; --pink-light: #FCE4EC; --white: #FFFFFF; --gray-bg: #F1F5F9; --text-dark: #1E293B; --text-gray: #64748B; --border: #E2E8F0; --danger: #EF4444; --warning: #F59E0B; --success: #10B981; --sidebar-width: 260px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--gray-bg); display: flex; min-height: 100vh; }
        
        .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, #E91E63 0%, #AD1457 100%); color: white; position: fixed; top: 0; left: 0; height: 100vh; overflow-y: auto; z-index: 1000; box-shadow: 4px 0 20px rgba(0,0,0,0.15); display: flex; flex-direction: column; }
        .sidebar-header { padding: 30px 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header .logo { font-size: 36px; font-weight: 800; letter-spacing: 3px; }
        .sidebar-header .title { font-size: 11px; opacity: 0.85; margin-top: 8px; text-transform: uppercase; letter-spacing: 2px; }
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
        .stat-icon.green { background: linear-gradient(135deg, #10B981, #059669); }
        .stat-info .stat-value { font-size: 26px; font-weight: 700; color: var(--text-dark); line-height: 1; }
        .stat-info .stat-label { font-size: 11px; color: var(--text-gray); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; font-weight: 600; }
        
        .card { background: white; border-radius: 16px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 25px; border: 1px solid var(--border); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 2px solid var(--pink-light); }
        .card-header h3 { color: var(--text-dark); font-size: 18px; display: flex; align-items: center; gap: 10px; font-weight: 700; }
        .card-header h3 i { color: var(--pink); }
        .badge-count { background: var(--pink-light); color: var(--pink-dark); padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 700; }
        
        .table-wrapper { overflow-x: auto; max-height: 500px; overflow-y: auto; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table thead { position: sticky; top: 0; z-index: 10; }
        table th { background: #FDF2F8; color: var(--pink-dark); padding: 13px 12px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; white-space: nowrap; }
        table td { padding: 12px; border-bottom: 1px solid var(--border); color: var(--text-dark); }
        table tbody tr:hover { background: #FFF5F8; }
        table tbody tr.critical-row { background: #FEF2F2; }
        table tbody tr.critical-row:hover { background: #FEE2E2; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: 0.5px; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        .badge-info { background: #DBEAFE; color: #1E40AF; }
        .badge-gray { background: #F3F4F6; color: #6B7280; }
        
        .btn { padding: 9px 18px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s; letter-spacing: 0.5px; white-space: nowrap; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #DC2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,0.3); }
        .btn-primary { background: var(--pink); color: white; }
        .btn-primary:hover { background: var(--pink-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(233,30,99,0.3); }
        .btn-warning { background: #F59E0B; color: white; }
        .btn-warning:hover { background: #D97706; }
        .btn-sm { padding: 6px 12px; font-size: 11px; }
        
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: var(--text-dark); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.8px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 11px 14px; border: 2px solid var(--border); border-radius: 10px; font-size: 14px; outline: none; transition: all 0.3s; font-family: inherit; background: #FAFAFA; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--pink); background: white; box-shadow: 0 0 0 3px rgba(233,30,99,0.08); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        
        .alert { padding: 16px 22px; border-radius: 12px; margin-bottom: 22px; font-size: 14px; display: flex; align-items: center; gap: 12px; font-weight: 500; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        .section { display: none; }
        .section.active { display: block; }
        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-gray); }
        .empty-state i { font-size: 50px; color: #D1D5DB; margin-bottom: 15px; display: block; }
        
        .notice-card { padding: 16px; border-radius: 12px; margin-bottom: 12px; border-left: 4px solid #E91E63; position: relative; }
        .notice-urgent { background: #FEF2F2; border-color: #EF4444; }
        .notice-general { background: #F8FAFC; }
        .notice-individual { background: #FFF7ED; border-color: #F59E0B; }
        .notice-team { background: #EFF6FF; border-color: #3B82F6; }
        .notice-actions { display: flex; gap: 8px; margin-top: 10px; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 550px; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-box h3 { color: #E91E63; margin-bottom: 20px; text-align: center; font-size: 20px; }
        
        .info-box { padding: 15px; border-radius: 10px; margin-top: 20px; }
        .info-box.blue { background: #F0F9FF; border-left: 4px solid #3B82F6; }
        .info-box ol { margin: 10px 0 0 20px; font-size: 13px; color: #64748B; }
        
        @media (max-width: 1200px) { .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { width: 65px; }
            .sidebar .nav-text, .sidebar-header .title, .sidebar-footer .user-info, .sidebar-nav .nav-section { display: none; }
            .sidebar-header { padding: 20px 10px; }
            .sidebar-header .logo { font-size: 24px; }
            .sidebar-nav a { justify-content: center; padding: 15px; }
            .sidebar-nav a i { font-size: 18px; }
            .main-content { margin-left: 65px; }
            .stats-row { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .digital-clock { font-size: 20px; }
            .content-area { padding: 20px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header"><div class="logo">bK</div><div class="title">HR Management</div></div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="#" class="active" onclick="showSection('dashboard')"><i class="fas fa-th-large"></i> <span class="nav-text">Dashboard</span></a>
        <a href="#" onclick="showSection('critical')"><i class="fas fa-exclamation-triangle"></i> <span class="nav-text">Critical Performance</span><?php if($critical_count>0): ?><span style="background:#EF4444;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto;"><?php echo $critical_count; ?></span><?php endif; ?></a>
        <a href="#" onclick="showSection('tabulation')"><i class="fas fa-table"></i> <span class="nav-text">Performance Tabulation</span></a>
        <div class="nav-section">Management</div>
        <a href="#" onclick="showSection('assign')"><i class="fas fa-user-plus"></i> <span class="nav-text">Assign to Team</span></a>
        <a href="#" onclick="showSection('teams')"><i class="fas fa-users"></i> <span class="nav-text">Team List</span></a>
        <a href="#" onclick="showSection('consult')"><i class="fas fa-comments"></i> <span class="nav-text">Consult Employee</span></a>
        <div class="nav-section">Communication</div>
        <a href="#" onclick="showSection('notices')"><i class="fas fa-bullhorn"></i> <span class="nav-text">View Notices</span><?php if($all_notices_count>0): ?><span style="background:white;color:#E91E63;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:auto;"><?php echo $all_notices_count; ?></span><?php endif; ?></a>
        <a href="#" onclick="showSection('add-notice')"><i class="fas fa-plus-circle"></i> <span class="nav-text">Publish Notice</span></a>
        <div class="nav-section">Reports</div>
        <a href="#" onclick="showSection('export-data')"><i class="fas fa-download"></i> <span class="nav-text">Previous Data</span></a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info"><strong><?php echo htmlspecialchars($hr_name); ?></strong><br><small><?php echo $hr_full_id; ?></small></div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title" id="pageTitle">📊 Dashboard Overview</div>
        <div class="top-right"><div><div class="digital-clock" id="digitalClock">00:00:00</div><div class="clock-date" id="clockDate"></div></div></div>
    </div>

    <div class="content-area">
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <!-- DASHBOARD -->
        <div id="section-dashboard" class="section active">
            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><div class="stat-value"><?php echo $total_emp; ?></div><div class="stat-label">Total Employees</div></div></div>
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $below_60; ?></div><div class="stat-label">Critical</div></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value"><?php echo $total_emp - $below_60; ?></div><div class="stat-label">Good Standing</div></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-layer-group"></i></div><div class="stat-info"><div class="stat-value"><?php echo $total_teams; ?></div><div class="stat-label">Total Teams</div></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-bell"></i> Recent Notices</h3></div>
                <?php if($hr_notices && $hr_notices->num_rows>0): $c=0; mysqli_data_seek($hr_notices,0); while($n=$hr_notices->fetch_assoc()): if($c++>=5)break; ?>
                    <div style="padding:12px;background:<?php echo $n['type']=='urgent'?'#FEF2F2':'#F8FAFC';?>;border-radius:10px;margin-bottom:8px;border-left:4px solid <?php echo $n['type']=='urgent'?'#EF4444':'#E91E63';?>;">
                        <strong><?php echo htmlspecialchars($n['title']); ?></strong><span class="badge <?php echo $n['type']=='urgent'?'badge-danger':'badge-info';?>" style="float:right;"><?php echo ucfirst($n['type']); ?></span>
                        <p style="font-size:12px;color:#64748B;"><?php echo htmlspecialchars(substr($n['description'],0,100)); ?>...</p>
                        <small style="color:#94A3B8;"><?php echo date('d M Y',strtotime($n['created_at'])); ?></small>
                    </div>
                <?php endwhile; else: ?><div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notices yet.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- CRITICAL PERFORMANCE -->
        <div id="section-critical" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:#EF4444;"></i> Critical Performance (Below 60)</h3><span class="badge-count"><?php echo $critical_count; ?> Employees</span></div>
                <?php if($critical_count>0): ?>
                    <div class="table-wrapper"><table><thead><tr><th>Employee</th><th>ID</th><th>Team</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th><th>Date</th><th>Action</th></tr></thead><tbody>
                        <?php mysqli_data_seek($critical_result,0); while($crit=$critical_result->fetch_assoc()): ?>
                            <tr class="critical-row"><td><strong><?php echo htmlspecialchars($crit['name']); ?></strong></td><td><?php echo $crit['emp_code']; ?></td><td><?php echo $crit['team_name']; ?></td>
                                <td><?php echo number_format($crit['csat'],1); ?></td><td><?php echo number_format($crit['tickets'],1); ?></td><td><?php echo number_format($crit['fcr'],1); ?></td>
                                <td><?php echo number_format($crit['resolution_time'],1); ?></td><td><?php echo number_format($crit['response_time'],1); ?></td>
                                <td><span class="badge badge-danger"><?php echo number_format($crit['total'],1); ?>/100</span></td><td><?php echo date('d/m/Y',strtotime($crit['date'])); ?></td>
                                <td><form method="POST" onsubmit="return confirm('Send urgent call?');"><input type="hidden" name="employee_id" value="<?php echo $crit['emp_code']; ?>"><button type="submit" name="urgent_call" class="btn btn-danger btn-sm">📞 Urgent Call</button></form></td></tr>
                        <?php endwhile; ?>
                    </tbody></table></div>
                <?php else: ?><div class="empty-state"><i class="fas fa-check-circle" style="color:#10B981;font-size:60px;"></i><p style="font-size:18px;color:#10B981;">✅ No Critical Cases</p></div><?php endif; ?>
            </div>
        </div>

        <!-- PERFORMANCE TABULATION -->
        <div id="section-tabulation" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-table"></i> Complete Performance Tabulation</h3><span class="badge-count"><?php echo $total_emp; ?> Employees</span></div>
                <div class="table-wrapper" style="max-height:550px;"><table><thead><tr><th>Employee</th><th>ID</th><th>Designation</th><th>Team</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th><th>Evaluated</th><th>Status</th></tr></thead><tbody>
                    <?php if($perf_table && $perf_table->num_rows>0): while($perf=$perf_table->fetch_assoc()): $total=floatval($perf['total']); $badge=$total>0?($total>=80?'badge-success':($total>=60?'badge-warning':'badge-danger')):'badge-gray'; $status=$total>0?($total>=80?'Excellent':($total>=60?'Average':'Critical')):'Not Evaluated'; ?>
                        <tr class="<?php echo ($total>0&&$total<60)?'critical-row':''; ?>"><td><strong><?php echo htmlspecialchars($perf['name']); ?></strong></td><td><?php echo $perf['employee_id']; ?></td><td><?php echo $perf['designation']; ?></td><td><?php echo $perf['team_name']; ?></td>
                            <td><?php echo number_format($perf['csat'],1); ?></td><td><?php echo number_format($perf['tickets'],1); ?></td><td><?php echo number_format($perf['fcr'],1); ?></td>
                            <td><?php echo number_format($perf['resolution_time'],1); ?></td><td><?php echo number_format($perf['response_time'],1); ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo number_format($total,1); ?></span></td>
                            <td style="font-size:11px;"><?php echo $perf['last_evaluated']?date('d/m/Y',strtotime($perf['last_evaluated'])):'-'; ?></td><td><span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span></td></tr>
                    <?php endwhile; endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <!-- ASSIGN TO TEAM -->
        <div id="section-assign" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-user-plus"></i> Assign Employees to Teams</h3></div>
                <?php if($unassigned && $unassigned->num_rows>0): while($emp=$unassigned->fetch_assoc()): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px;background:#F8FAFC;border-radius:12px;margin-bottom:12px;border:1px solid #E2E8F0;">
                        <div><strong><?php echo htmlspecialchars($emp['name']); ?></strong><br><small><?php echo $emp['employee_id']; ?> | <?php echo $emp['designation']; ?></small></div>
                        <form method="POST" style="display:flex;gap:10px;"><input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>"><select name="team_id" style="padding:10px;border-radius:8px;border:2px solid #E2E8F0;"><?php $tl=$conn->query("SELECT * FROM teams"); if($tl): while($t=$tl->fetch_assoc()): ?><option value="<?php echo $t['id']; ?>"><?php echo $t['team_name']; ?></option><?php endwhile; endif; ?></select><button type="submit" name="assign_team" class="btn btn-primary">Assign</button></form>
                    </div>
                <?php endwhile; else: ?><div class="empty-state"><i class="fas fa-check-circle" style="color:#10B981;"></i><p>All employees assigned.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- TEAM LIST -->
        <div id="section-teams" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-users"></i> Team List</h3></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <?php if($team_list && $team_list->num_rows>0): while($team=$team_list->fetch_assoc()): ?>
                        <div style="background:#F8FAFC;padding:18px;border-radius:12px;border:1px solid #E2E8F0;border-left:4px solid #E91E63;">
                            <h4>👥 <?php echo htmlspecialchars($team['team_name']); ?></h4><p><strong>Lead:</strong> <?php echo $team['lead_name']??'Not Assigned'; ?></p><p><strong>Members:</strong> <?php echo $team['member_count']; ?></p>
                            <?php $mbr=$conn->query("SELECT name,employee_id FROM employees WHERE team_id={$team['id']} LIMIT 10"); if($mbr && $mbr->num_rows>0): while($m=$mbr->fetch_assoc()): ?><p style="font-size:11px;color:#64748B;">• <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['employee_id']; ?>)</p><?php endwhile; endif; ?>
                        </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>

        <!-- CONSULT EMPLOYEE -->
        <div id="section-consult" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-comments"></i> Schedule Consultation</h3></div>
                <form method="POST"><div class="form-row"><div class="form-group"><label>Employee</label><select name="consult_emp" required><option value="">Choose...</option><?php if($all_employees): mysqli_data_seek($all_employees,0); while($emp=$all_employees->fetch_assoc()): ?><option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name'].' ('.$emp['employee_id'].')'); ?></option><?php endwhile; endif; ?></select></div><div class="form-group"><label>Date</label><input type="date" name="consult_date" required value="<?php echo date('Y-m-d'); ?>"></div></div><div class="form-group"><label>Notes</label><textarea name="consult_notes" rows="3"></textarea></div><button type="submit" name="schedule_consult" class="btn btn-primary">📅 Schedule</button></form>
            </div>
        </div>

        <!-- VIEW NOTICES -->
        <div id="section-notices" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-bell"></i> All Notices</h3><span class="badge-count"><?php echo $all_notices_count; ?> Notices</span></div>
                <?php if($hr_notices && $hr_notices->num_rows>0): mysqli_data_seek($hr_notices,0); while($n=$hr_notices->fetch_assoc()): $bg='notice-'.$n['type']; ?>
                    <div class="notice-card <?php echo $bg; ?>"><strong><?php echo htmlspecialchars($n['title']); ?></strong><span class="badge <?php echo $n['type']=='urgent'?'badge-danger':($n['type']=='individual'?'badge-warning':'badge-info');?>" style="float:right;"><?php echo ucfirst($n['type']); ?></span><p style="font-size:13px;color:#64748B;"><?php echo htmlspecialchars($n['description']); ?></p><small style="color:#94A3B8;"><?php echo date('d M Y, h:i A',strtotime($n['created_at'])); ?></small><div class="notice-actions"><button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $n['id']; ?>,"<?php echo htmlspecialchars(addslashes($n['title'])); ?>","<?php echo htmlspecialchars(addslashes($n['description'])); ?>","<?php echo $n['type']; ?>")'>✏️ Edit</button><a href="?delete_notice=<?php echo $n['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑️ Delete</a></div></div>
                <?php endwhile; else: ?><div class="empty-state"><i class="fas fa-bell-slash"></i><p>No notices.</p></div><?php endif; ?>
            </div>
        </div>

        <!-- PUBLISH NOTICE -->
        <div id="section-add-notice" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-plus-circle"></i> Publish New Notice</h3></div>
                <form method="POST"><div class="form-group"><label>Title</label><input type="text" name="notice_title" required></div><div class="form-group"><label>Description</label><textarea name="notice_description" rows="4" required></textarea></div><div class="form-row"><div class="form-group"><label>Type</label><select name="notice_type" id="noticeType" required onchange="toggleNoticeTarget()"><option value="general">📢 General</option><option value="team">👥 Team</option><option value="individual">👤 Individual</option><option value="urgent">🚨 Urgent</option></select></div><div class="form-group" id="teamSelect" style="display:none;"><label>Team</label><select name="notice_team"><option value="">Choose...</option><?php $tf=$conn->query("SELECT * FROM teams"); if($tf): while($t=$tf->fetch_assoc()): ?><option value="<?php echo $t['id']; ?>"><?php echo $t['team_name']; ?></option><?php endwhile; endif; ?></select></div><div class="form-group" id="employeeSelect" style="display:none;"><label>Employee</label><select name="notice_employee"><option value="">Choose...</option><?php if($all_employees): mysqli_data_seek($all_employees,0); while($emp=$all_employees->fetch_assoc()): ?><option value="<?php echo $emp['id']; ?>"><?php echo $emp['name'].' ('.$emp['employee_id'].')'; ?></option><?php endwhile; endif; ?></select></div></div><button type="submit" name="add_notice" class="btn btn-primary">📢 Publish</button></form>
            </div>
        </div>

        <!-- EXPORT DATA -->
        <div id="section-export-data" class="section">
            <div class="card"><div class="card-header"><h3><i class="fas fa-download"></i> Export Previous Data</h3></div>
                <form method="GET" action="export_performance.php" target="_blank">
                    <div class="form-group"><label>Export Type</label><select name="type" id="exportTypeHR" required onchange="toggleExportHR()"><option value="all">📊 All Employees Data</option><option value="specific">👤 Specific Employee</option></select></div>
                    <div class="form-group" id="empSelectHR" style="display:none;"><label>Select Employee</label><select name="emp_id"><option value="">Choose...</option><?php $exphr=$conn->query("SELECT id,name,employee_id FROM employees WHERE role='employee' ORDER BY name"); if($exphr): while($e=$exphr->fetch_assoc()): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name'].' ('.$e['employee_id'].')'); ?></option><?php endwhile; endif; ?></select></div>
                    <div class="form-row"><div class="form-group"><label>Start Date</label><input type="date" name="start_date" required value="<?php echo date('Y-m-01'); ?>"></div><div class="form-group"><label>End Date</label><input type="date" name="end_date" required value="<?php echo date('Y-m-d'); ?>"></div></div>
                    <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fas fa-file-excel"></i> 📥 Download Excel Report</button>
                </form>
                <div class="info-box blue"><strong>📋 Instructions:</strong><ol><li>Select <strong>All Employees</strong> or a <strong>Specific Employee</strong></li><li>Choose the <strong>Start Date</strong> and <strong>End Date</strong></li><li>Click <strong>Download Excel Report</strong></li><li>The file will download as an .xls file</li></ol></div>
            </div>
        </div>
    </div>
</main>

<!-- Edit Notice Modal -->
<div id="editModal" class="modal-overlay"><div class="modal-box"><h3>✏️ Edit Notice</h3><form method="POST"><input type="hidden" name="notice_id" id="edit_notice_id"><div class="form-group"><label>Title</label><input type="text" name="update_title" id="edit_title" required></div><div class="form-group"><label>Description</label><textarea name="update_description" id="edit_description" rows="3" required></textarea></div><div class="form-group"><label>Type</label><select name="update_type" id="edit_type"><option value="general">General</option><option value="team">Team</option><option value="individual">Individual</option><option value="urgent">Urgent</option></select></div><div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeEditModal()">Cancel</button><button type="submit" name="update_notice" class="btn btn-primary" style="flex:1;">Update</button></div></form></div></div>

<script>
    function updateClock(){const n=new Date();document.getElementById('digitalClock').textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate').textContent=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][n.getDay()]+', '+n.getDate()+' '+['January','February','March','April','May','June','July','August','September','October','November','December'][n.getMonth()]+' '+n.getFullYear();}
    updateClock();setInterval(updateClock,1000);
    
    function showSection(n){
        document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));
        document.getElementById('section-'+n).classList.add('active');
        document.getElementById('pageTitle').textContent={'dashboard':'📊 Dashboard Overview','critical':'🚨 Critical Performance','tabulation':'📊 Performance Tabulation','assign':'👤 Assign to Team','teams':'👥 Team List','consult':'💬 Consult Employee','notices':'📢 View Notices','add-notice':'📝 Publish Notice','export-data':'📥 Export Previous Data'}[n]||'Dashboard';
        document.querySelectorAll('.sidebar-nav a').forEach(a=>a.classList.remove('active'));
        if(event&&event.target){const l=event.target.closest('a');if(l)l.classList.add('active');}
    }
    
    function toggleNoticeTarget(){const t=document.getElementById('noticeType').value;document.getElementById('teamSelect').style.display=t==='team'?'block':'none';document.getElementById('employeeSelect').style.display=(t==='individual'||t==='urgent')?'block':'none';}
    function toggleExportHR(){const t=document.getElementById('exportTypeHR').value;document.getElementById('empSelectHR').style.display=t==='specific'?'block':'none';}
    function openEditModal(i,t,d,ty){document.getElementById('edit_notice_id').value=i;document.getElementById('edit_title').value=t;document.getElementById('edit_description').value=d;document.getElementById('edit_type').value=ty;document.getElementById('editModal').classList.add('active');}
    function closeEditModal(){document.getElementById('editModal').classList.remove('active');}
    document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeEditModal();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeEditModal();});
</script>
</body>
</html>