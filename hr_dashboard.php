<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

// Helper function for security
if (!function_exists('sanitize')) {
    function sanitize($data) {
        global $conn;
        return mysqli_real_escape_string($conn, htmlspecialchars(trim($data)));
    }
}

if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$hr_name = $_SESSION['name'];
$hr_id = $_SESSION['user_id'];
$hr_full_id = $_SESSION['full_id'];
$success = '';
$error = '';

// ========== HANDLE ADD NEW TEAM ==========
if (isset($_POST['create_team'])) {
    $team_name = sanitize($_POST['team_name']);
    
    // Check if team name already exists
    $check_team = $conn->query("SELECT id FROM teams WHERE team_name = '$team_name'");
    if ($check_team && $check_team->num_rows > 0) {
        $error = "❌ Team name '$team_name' already exists!";
    } else {
        $conn->query("INSERT INTO teams (team_name) VALUES ('$team_name')");
        $new_team_id = $conn->insert_id;
        $success = "✅ Team '$team_name' created successfully! Team ID: $new_team_id";
    }
}

// Handle team lead assignment
if (isset($_POST['assign_team_lead'])) {
    $team_id = intval($_POST['team_id']);
    $lead_id = intval($_POST['lead_id']);
    
    // Update team lead in teams table
    $conn->query("UPDATE teams SET team_lead_id = $lead_id WHERE id = $team_id");
    
    // Update employee's role to team_lead
    $conn->query("UPDATE employees SET role = 'team_lead', team_id = $team_id WHERE id = $lead_id");
    
    $success = "✅ Team lead assigned successfully!";
}

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

// Handle team assignment (employee to team)
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
$all_team_leads = $conn->query("SELECT * FROM employees WHERE role = 'team_lead' ORDER BY name");
$hr_notices = $conn->query("SELECT * FROM notices WHERE type = 'general' OR created_by = $hr_id OR (type = 'urgent' AND created_by = $hr_id) ORDER BY created_at DESC LIMIT 20");
$all_notices_count = $conn->query("SELECT COUNT(*) as total FROM notices WHERE created_by = $hr_id")->fetch_assoc()['total'] ?? 0;
$team_list = $conn->query("SELECT t.*, e.name as lead_name, e.employee_id as lead_code, (SELECT COUNT(*) FROM employees WHERE team_id = t.id) as member_count FROM teams t LEFT JOIN employees e ON t.team_lead_id = e.id");

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
        .sidebar-header { padding: 20px 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        
        /* Updated Logo CSS for Better Fit */
        .sidebar-header img { 
            width: auto; 
            max-width: 80px; 
            height: auto; 
            max-height: 80px; 
            border-radius: 12px; 
            margin-bottom: 10px; 
            background: white; 
            padding: 5px; 
        }

        .sidebar-header .team-name { font-size: 14px; opacity: 0.9; margin-top: 8px; font-weight: 600; }
        .sidebar-header .title { font-size: 10px; opacity: 0.7; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px; }
        
        .sidebar-nav { padding: 15px 0; flex: 1; }
        .sidebar-nav .nav-section { padding: 12px 25px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.6; margin-top: 10px; font-weight: 600; }
        .sidebar-nav a { display: flex; align-items: center; gap: 14px; padding: 13px 25px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.3s; border-left: 3px solid transparent; font-weight: 500; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.12); border-left-color: white; color: white; }
        .sidebar-nav a.active { background: rgba(255,255,255,0.18); border-left-color: white; color: white; font-weight: 600; }
        
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(255,255,255,0.2); }
        .sidebar-footer .user-info { margin-bottom: 12px; font-size: 13px; line-height: 1.5; }
        .logout-btn { display: block; text-align: center; padding: 10px; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); }

        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; }
        .top-bar { background: white; padding: 18px 35px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .page-title { font-size: 20px; font-weight: 700; color: var(--text-dark); }
        .digital-clock { font-size: 28px; font-weight: 700; color: var(--pink); font-family: 'Courier New', monospace; letter-spacing: 2px; }
        .clock-date { font-size: 12px; color: var(--text-gray); text-align: right; }
        
        .content-area { padding: 30px 35px; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 22px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 18px; }
        .stat-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; }
        .stat-icon.blue { background: #3B82F6; } .stat-icon.red { background: #EF4444; } .stat-icon.orange { background: #F59E0B; } .stat-icon.green { background: #10B981; }
        .stat-info h3 { font-size: 22px; font-weight: 700; } .stat-info p { font-size: 10px; color: var(--text-gray); text-transform: uppercase; }

        .card { background: white; border-radius: 16px; padding: 28px; border: 1px solid var(--border); margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; border-bottom: 2px solid var(--pink-light); padding-bottom: 10px; }
        .card-header h3 { color: var(--text-dark); font-size: 18px; font-weight: 700; }
        
        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); max-height: 450px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table thead { position: sticky; top: 0; z-index: 5; }
        table th { background: #FDF2F8; color: var(--pink-dark); padding: 13px; text-align: left; font-weight: 700; font-size: 11px; text-transform: uppercase; }
        table td { padding: 12px; border-bottom: 1px solid var(--border); }
        table tbody tr:hover { background: #FFF5F8; }
        table tbody tr.critical-row { background: #FEF2F2; }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-danger { background: #FEE2E2; color: #991B1B; } .badge-warning { background: #FEF3C7; color: #92400E; } .badge-success { background: #D1FAE5; color: #065F46; } .badge-info { background: #DBEAFE; color: #1E40AF; }
        
        .btn { padding: 8px 16px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--pink); color: white; } .btn-primary:hover { background: var(--pink-dark); }
        .btn-danger { background: var(--danger); color: white; } .btn-danger:hover { background: #DC2626; }
        .btn-warning { background: #F59E0B; color: white; } .btn-warning:hover { background: #D97706; }
        .btn-success { background: var(--success); color: white; } .btn-success:hover { background: #059669; }
        .btn-sm { padding: 5px 10px; font-size: 10px; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; color: var(--text-dark); }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 2px solid var(--border); border-radius: 8px; font-size: 13px; outline: none; background: #FAFAFA; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: var(--pink); background: white; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 550px; max-height: 85vh; overflow-y: auto; }

        .section { display: none; }
        .section.active { display: block; }
        
        .notice-card { padding: 14px; border-radius: 10px; margin-bottom: 10px; border-left: 4px solid var(--pink); background: #F8FAFC; }
        .notice-urgent { background: #FEF2F2; border-color: #EF4444; }
        .notice-actions { display: flex; gap: 8px; margin-top: 10px; }
        
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
        .alert-error { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        @media (max-width: 1024px) { .two-col { grid-template-columns: 1fr; } .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .sidebar { width: 65px; } .sidebar .nav-section, .sidebar-header .team-name, .sidebar-header .title { display: none; } .main-content { margin-left: 65px; } .content-area { padding: 20px; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="logo/logo.jpg" alt="Logo"> 
        <div class="team-name">HR Management</div>
        <div class="title">Operations</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="#" class="active" onclick="showSection('dashboard', this)"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="#" onclick="showSection('critical', this)"><i class="fas fa-exclamation-triangle"></i> Critical<?php if($critical_count>0): ?><span style="background:#EF4444;color:white;padding:2px 8px;border-radius:10px;font-size:10px;margin-left:auto;"><?php echo $critical_count; ?></span><?php endif; ?></a>
        <a href="#" onclick="showSection('tabulation', this)"><i class="fas fa-table"></i> Tabulation</a>
        <div class="nav-section">Management</div>
        <a href="#" onclick="showSection('assign', this)"><i class="fas fa-user-plus"></i> Assign to Team</a>
        <a href="#" onclick="showSection('teams', this)"><i class="fas fa-users"></i> Team List</a>
        <a href="#" onclick="showSection('consult', this)"><i class="fas fa-comments"></i> Consult</a>
        <div class="nav-section">Communication</div>
        <a href="#" onclick="showSection('notices', this)"><i class="fas fa-bullhorn"></i> Notices<?php if($all_notices_count>0): ?><span style="background:white;color:#E91E63;padding:2px 8px;border-radius:10px;font-size:10px;margin-left:auto;"><?php echo $all_notices_count; ?></span><?php endif; ?></a>
        <a href="#" onclick="showSection('add-notice', this)"><i class="fas fa-plus-circle"></i> Publish</a>
        <div class="nav-section">Settings</div>
        <a href="#" onclick="showSection('create-team', this)"><i class="fas fa-users-cog"></i> Add New Team</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info"><strong><?php echo htmlspecialchars($hr_name); ?></strong><br><small><?php echo $hr_full_id; ?></small></div>
        <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title" id="pageTitle">Dashboard Overview</div>
        <div style="text-align:right;"><div class="digital-clock" id="digitalClock">00:00:00</div><div class="clock-date" id="clockDate"></div></div>
    </div>

    <div class="content-area">
        <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>

        <div id="section-dashboard" class="section active">
            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $total_emp; ?></h3><p>Total Employees</p></div></div>
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><h3><?php echo $below_60; ?></h3><p>Critical</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?php echo $total_emp - $below_60; ?></h3><p>Good Standing</p></div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-layer-group"></i></div><div class="stat-info"><h3><?php echo $total_teams; ?></h3><p>Total Teams</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-bell"></i> Recent Notices</h3></div>
                <?php if($hr_notices && $hr_notices->num_rows>0): $c=0; mysqli_data_seek($hr_notices,0); while($n=$hr_notices->fetch_assoc()): if($c++>=5)break; ?>
                    <div class="notice-card <?php echo $n['type']=='urgent'?'notice-urgent':''; ?>">
                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                        <p style="font-size:12px;color:#666;"><?php echo htmlspecialchars(substr($n['description'],0,100)); ?>...</p>
                    </div>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <div id="section-critical" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:#EF4444;"></i> Critical Performance (Below 60)</h3><span class="badge badge-danger"><?php echo $critical_count; ?></span></div>
                <div class="table-wrapper"><table><thead><tr><th>Employee</th><th>ID</th><th>Team</th><th>Score</th><th>Date</th><th>Action</th></tr></thead><tbody>
                    <?php if($critical_count>0): mysqli_data_seek($critical_result,0); while($crit=$critical_result->fetch_assoc()): ?>
                    <tr class="critical-row">
                        <td><strong><?php echo htmlspecialchars($crit['name']); ?></strong></td>
                        <td><?php echo $crit['emp_code']; ?></td>
                        <td><?php echo $crit['team_name']; ?></td>
                        <td><span class="badge badge-danger"><?php echo $crit['total']; ?>/100</span></td>
                        <td><?php echo date('d/m/Y',strtotime($crit['date'])); ?></td>
                        <td><form method="POST" onsubmit="return confirm('Send urgent call to <?php echo addslashes($crit['name']); ?>?');"><input type="hidden" name="employee_id" value="<?php echo $crit['emp_code']; ?>"><button type="submit" name="urgent_call" class="btn btn-danger btn-sm">📞 Urgent Call</button></form></td>
                    </tr>
                    <?php endwhile; else: ?><tr><td colspan="6" style="text-align:center;padding:30px;color:#10B981;">✅ No critical cases</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <div id="section-tabulation" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-table"></i> Complete Tabulation</h3></div>
                <div class="table-wrapper" style="max-height:500px;"><table><thead><tr><th>Name</th><th>ID</th><th>Team</th><th>CSAT</th><th>Tickets</th><th>FCR</th><th>Res.</th><th>Resp.</th><th>Total</th><th>Status</th></tr></thead><tbody>
                    <?php while($perf=$perf_table->fetch_assoc()): $total=floatval($perf['total']); $badge=$total>=80?'badge-success':($total>=60?'badge-warning':'badge-danger'); ?>
                    <tr class="<?php echo ($total>0&&$total<60)?'critical-row':''; ?>">
                        <td><?php echo htmlspecialchars($perf['name']); ?></td><td><?php echo $perf['employee_id']; ?></td><td><?php echo $perf['team_name']; ?></td>
                        <td><?php echo number_format($perf['csat'],1); ?></td><td><?php echo number_format($perf['tickets'],1); ?></td><td><?php echo number_format($perf['fcr'],1); ?></td>
                        <td><?php echo number_format($perf['resolution_time'],1); ?></td><td><?php echo number_format($perf['response_time'],1); ?></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo number_format($total,1); ?></span></td>
                        <td><?php echo $total>=80?'Excellent':($total>=60?'Average':($total>0?'Critical':'No Data')); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody></table></div>
            </div>
        </div>

        <div id="section-assign" class="section">
            <div class="two-col">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-user-plus"></i> Assign Employees</h3></div>
                    <?php if($unassigned && $unassigned->num_rows>0): while($emp=$unassigned->fetch_assoc()): ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#F8FAFC;border-radius:10px;margin-bottom:10px;border:1px solid var(--border);">
                            <div><strong><?php echo htmlspecialchars($emp['name']); ?></strong><br><small><?php echo $emp['employee_id']; ?> | <?php echo $emp['designation']; ?></small></div>
                            <form method="POST" style="display:flex;gap:8px;align-items:center;">
                                <input type="hidden" name="emp_id" value="<?php echo $emp['id']; ?>">
                                <select name="team_id" style="padding:8px;border-radius:6px;border:2px solid var(--border);font-size:12px;">
                                    <?php $tl=$conn->query("SELECT * FROM teams"); while($t=$tl->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo $t['team_name']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <button type="submit" name="assign_team" class="btn btn-primary btn-sm">Assign</button>
                            </form>
                        </div>
                    <?php endwhile; else: ?><p style="color:#10B981;text-align:center;padding:20px;">✅ All employees assigned to teams.</p><?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Add New Team</h3></div>
                    <form method="POST">
                        <div class="form-group">
                            <label>New Team Name</label>
                            <input type="text" name="team_name" required placeholder="Enter new team name (e.g., Team Gamma)">
                        </div>
                        <button type="submit" name="create_team" class="btn btn-success" style="width:100%;">Create Team</button>
                    </form>
                    
                    <div style="margin-top:30px;padding-top:20px;border-top:2px solid var(--pink-light);">
                        <h4 style="color:var(--text-dark);margin-bottom:15px;">Assign Team Lead</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label>Select Team</label>
                                <select name="team_id" required>
                                    <option value="">Choose Team...</option>
                                    <?php $tl2=$conn->query("SELECT * FROM teams"); while($t=$tl2->fetch_assoc()): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo $t['team_name']; ?> (Lead: <?php echo $t['team_lead_id']?'Assigned':'None'; ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Select Team Lead</label>
                                <select name="lead_id" required>
                                    <option value="">Choose Employee...</option>
                                    <?php 
                                    // Get employees who can be team leads (not already leads)
                                    $potential_leads = $conn->query("SELECT id, name, employee_id FROM employees WHERE role = 'employee' OR role = 'team_lead' ORDER BY name");
                                    while($pl=$potential_leads->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $pl['id']; ?>"><?php echo htmlspecialchars($pl['name'].' ('.$pl['employee_id'].')'); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" name="assign_team_lead" class="btn btn-primary" style="width:100%;"> Assign Team Lead</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-teams" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-users"></i> Team List</h3><span class="badge badge-info"><?php echo $total_teams; ?> Teams</span></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <?php if($team_list && $team_list->num_rows>0): mysqli_data_seek($team_list,0); while($team=$team_list->fetch_assoc()): ?>
                    <div style="background:#F8FAFC;padding:18px;border-radius:12px;border:1px solid var(--border);border-left:4px solid var(--pink);">
                        <h4>👥 <?php echo htmlspecialchars($team['team_name']); ?></h4>
                        <p><strong>Lead:</strong> <?php echo $team['lead_name'] ? htmlspecialchars($team['lead_name'].' ('.$team['lead_code'].')') : '<span style="color:#EF4444;">Not Assigned</span>'; ?></p>
                        <p><strong>Members:</strong> <?php echo $team['member_count']; ?></p>
                        <?php $mbr=$conn->query("SELECT name,employee_id FROM employees WHERE team_id={$team['id']} LIMIT 8"); if($mbr && $mbr->num_rows>0): while($m=$mbr->fetch_assoc()): ?>
                            <p style="font-size:10px;color:#666;margin:2px 0;">• <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['employee_id']; ?>)</p>
                        <?php endwhile; endif; ?>
                    </div>
                    <?php endwhile; endif; ?>
                </div>
            </div>
        </div>

        <div id="section-consult" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-comments"></i> Schedule Consultation</h3></div>
                <form method="POST">
                    <div class="form-group"><label>Employee</label><select name="consult_emp" required><option value="">Choose...</option><?php mysqli_data_seek($all_employees,0); while($e=$all_employees->fetch_assoc()): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name'].' ('.$e['employee_id'].')'); ?></option><?php endwhile; ?></select></div>
                    <div class="form-group"><label>Date</label><input type="date" name="consult_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label>Notes</label><textarea name="consult_notes" rows="3"></textarea></div>
                    <button type="submit" name="schedule_consult" class="btn btn-primary">📅 Schedule</button>
                </form>
            </div>
        </div>

        <div id="section-notices" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-bell"></i> All Notices</h3></div>
                <?php if($hr_notices && $hr_notices->num_rows>0): mysqli_data_seek($hr_notices,0); while($n=$hr_notices->fetch_assoc()): ?>
                    <div class="notice-card <?php echo $n['type']=='urgent'?'notice-urgent':''; ?>">
                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                        <span class="badge <?php echo $n['type']=='urgent'?'badge-danger':'badge-info';?>" style="float:right;"><?php echo ucfirst($n['type']); ?></span>
                        <p style="font-size:12px;color:#666;"><?php echo htmlspecialchars($n['description']); ?></p>
                        <small style="color:#999;"><?php echo date('d M Y',strtotime($n['created_at'])); ?></small>
                        <div class="notice-actions">
                            <button class="btn btn-warning btn-sm" onclick='openEditModal(<?php echo $n['id']; ?>,"<?php echo addslashes($n['title']); ?>","<?php echo addslashes($n['description']); ?>","<?php echo $n['type']; ?>")'>✏️ Edit</button>
                            <a href="?delete_notice=<?php echo $n['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">🗑️ Delete</a>
                        </div>
                    </div>
                <?php endwhile; endif; ?>
            </div>
        </div>

        <div id="section-add-notice" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Publish Notice</h3></div>
                <form method="POST">
                    <div class="form-group"><label>Title</label><input type="text" name="notice_title" required></div>
                    <div class="form-group"><label>Description</label><textarea name="notice_description" rows="4" required></textarea></div>
                    <div class="form-group"><label>Type</label><select name="notice_type" id="noticeType" onchange="toggleNoticeTarget()"><option value="general">General</option><option value="team">Team</option><option value="individual">Individual</option><option value="urgent">Urgent</option></select></div>
                    <div class="form-group" id="teamSelect" style="display:none;"><label>Team</label><select name="notice_team"><?php mysqli_data_seek($team_list,0); while($t=$team_list->fetch_assoc()): ?><option value="<?php echo $t['id']; ?>"><?php echo $t['team_name']; ?></option><?php endwhile; ?></select></div>
                    <div class="form-group" id="employeeSelect" style="display:none;"><label>Employee</label><select name="notice_employee"><?php mysqli_data_seek($all_employees,0); while($e=$all_employees->fetch_assoc()): ?><option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name'].' ('.$e['employee_id'].')'); ?></option><?php endwhile; ?></select></div>
                    <button type="submit" name="add_notice" class="btn btn-primary">📢 Publish</button>
                </form>
            </div>
        </div>

        <div id="section-create-team" class="section">
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-users-cog"></i> Create New Team</h3></div>
                <form method="POST">
                    <div class="form-group">
                        <label>Team Name</label>
                        <input type="text" name="team_name" required placeholder="Enter unique team name (e.g., Team Gamma, Team Delta)">
                    </div>
                    <button type="submit" name="create_team" class="btn btn-success" style="width:100%;">Create New Team</button>
                </form>
                
                <div style="margin-top:40px;padding-top:25px;border-top:2px solid var(--pink-light);">
                    <h4 style="margin-bottom:20px;">Assign Team Lead to Team</h4>
                    <form method="POST" class="form-row">
                        <div class="form-group">
                            <label>Select Team</label>
                            <select name="team_id" required>
                                <option value="">Choose Team...</option>
                                <?php $tl3=$conn->query("SELECT t.*, e.name as lead_name FROM teams t LEFT JOIN employees e ON t.team_lead_id = e.id"); while($t=$tl3->fetch_assoc()): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?> <?php echo $t['lead_name']?'(Lead: '.$t['lead_name'].')':'(No Lead)'; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Select Team Lead</label>
                            <select name="lead_id" required>
                                <option value="">Choose Employee...</option>
                                <?php $pl2=$conn->query("SELECT id, name, employee_id FROM employees WHERE role IN ('employee','team_lead') ORDER BY name"); while($pl=$pl2->fetch_assoc()): ?>
                                    <option value="<?php echo $pl['id']; ?>"><?php echo htmlspecialchars($pl['name'].' ('.$pl['employee_id'].')'); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div style="grid-column:1/-1;"><button type="submit" name="assign_team_lead" class="btn btn-primary" style="width:100%;">Assign as Team Lead</button></div>
                    </form>
                </div>
                
                <div style="margin-top:25px;padding:15px;background:#F0F9FF;border-radius:10px;border-left:4px solid #3B82F6;">
                    <strong>Instructions:</strong>
                    <ol style="margin:8px 0 0 20px;font-size:13px;color:#666;">
                        <li>First <strong>Create a New Team</strong> by entering a name above</li>
                        <li>Then <strong>Assign a Team Lead</strong> to the team</li>
                        <li>Go to <strong>Assign to Team</strong> section to add members</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h3>✏️ Edit Notice</h3>
        <form method="POST">
            <input type="hidden" name="notice_id" id="edit_notice_id">
            <div class="form-group"><label>Title</label><input type="text" name="update_title" id="edit_title" required></div>
            <div class="form-group"><label>Description</label><textarea name="update_description" id="edit_description" rows="3" required></textarea></div>
            <div class="form-group"><label>Type</label><select name="update_type" id="edit_type"><option value="general">General</option><option value="team">Team</option><option value="individual">Individual</option><option value="urgent">Urgent</option></select></div>
            <div style="display:flex;gap:10px;"><button type="button" class="btn" style="background:#999;color:white;flex:1;" onclick="closeModal()">Cancel</button><button type="submit" name="update_notice" class="btn btn-primary" style="flex:1;">Update</button></div>
        </form>
    </div>
</div>

<script>
    function showSection(id, btn) {
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
        document.getElementById('section-' + id).classList.add('active');
        if(btn) btn.classList.add('active');
        const titles = {'dashboard':'Dashboard Overview','critical':'Critical Performance','tabulation':'Performance Tabulation','assign':'Assign to Team','teams':'Team List','consult':'Consult Employee','notices':'View Notices','add-notice':'Publish Notice','create-team':'Add New Team'};
        document.getElementById('pageTitle').textContent = titles[id] || 'Dashboard';
    }
    
    function updateClock(){const n=new Date();document.getElementById('digitalClock').textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');document.getElementById('clockDate').textContent=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][n.getDay()]+', '+n.getDate()+' '+['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][n.getMonth()]+' '+n.getFullYear();}
    updateClock();setInterval(updateClock,1000);
    
    function toggleNoticeTarget(){const t=document.getElementById('noticeType').value;document.getElementById('teamSelect').style.display=t==='team'?'block':'none';document.getElementById('employeeSelect').style.display=(t==='individual'||t==='urgent')?'block':'none';}
    
    function openEditModal(id,title,desc,type){document.getElementById('edit_notice_id').value=id;document.getElementById('edit_title').value=title;document.getElementById('edit_description').value=desc;document.getElementById('edit_type').value=type;document.getElementById('editModal').classList.add('active');}
    function closeModal(){document.getElementById('editModal').classList.remove('active');}
    document.getElementById('editModal').addEventListener('click',function(e){if(e.target===this)closeModal();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeModal();});
</script>
</body>
</html>