<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

// Helper function for security (Ensure this is defined if not in includes)
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
        .sidebar-header { padding: 20px 25px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .sidebar-header img { width: 60px; height: 60px; border-radius: 12px; margin-bottom: 10px; background: white; padding: 5px; }
        .sidebar-header .team-name { font-size: 14px; opacity: 0.9; margin-top: 8px; font-weight: 600; }
        .sidebar-header .title { font-size: 10px; opacity: 0.7; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px; }
        
        .sidebar-nav { padding: 15px 0; flex: 1; }
        .sidebar-nav .nav-section { padding: 12px 25px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; opacity: 0.6; margin-top: 10px; font-weight: 600; }
        .sidebar-nav a { display: flex; align-items: center; gap: 14px; padding: 13px 25px; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: all 0.3s; border-left: 3px solid transparent; font-weight: 500; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.12); border-left-color: white; color: white; }
        .sidebar-nav a.active { background: rgba(255,255,255,0.18); border-left-color: white; color: white; font-weight: 600; }
        
        .sidebar-footer { padding: 20px 25px; border-top: 1px solid rgba(255,255,255,0.2); }
        .logout-btn { display: block; text-align: center; padding: 10px; background: rgba(255,255,255,0.15); color: white; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid rgba(255,255,255,0.2); }

        .main-content { margin-left: var(--sidebar-width); flex: 1; min-height: 100vh; }
        .top-bar { background: white; padding: 18px 35px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 100; border-bottom: 1px solid var(--border); }
        .digital-clock { font-size: 30px; font-weight: 700; color: var(--pink); font-family: 'Courier New', monospace; }
        
        .content-area { padding: 30px 35px; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 22px; border-radius: 16px; border: 1px solid var(--border); display: flex; align-items: center; gap: 18px; }
        .stat-icon { width: 55px; height: 55px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: white; }
        .stat-icon.blue { background: #3B82F6; }
        .stat-icon.red { background: #EF4444; }
        .stat-icon.orange { background: #F59E0B; }
        .stat-icon.green { background: #10B981; }

        .card { background: white; border-radius: 16px; padding: 28px; border: 1px solid var(--border); margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; border-bottom: 2px solid var(--pink-light); padding-bottom: 10px; }
        
        .table-wrapper { overflow-x: auto; border-radius: 12px; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        table th { background: #FDF2F8; color: var(--pink-dark); padding: 13px; text-align: left; }
        table td { padding: 12px; border-bottom: 1px solid var(--border); }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-danger { background: #FEE2E2; color: #991B1B; }
        .badge-warning { background: #FEF3C7; color: #92400E; }
        .badge-success { background: #D1FAE5; color: #065F46; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-primary { background: var(--pink); color: white; }
        .btn-warning { background: #F59E0B; color: white; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 8px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 16px; padding: 30px; width: 90%; max-width: 500px; }

        .section { display: none; }
        .section.active { display: block; }
        
        .notice-card { padding: 15px; border-radius: 10px; margin-bottom: 10px; border-left: 4px solid var(--pink); background: #F8FAFC; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">
        <img src="logo/logo.jpg" alt="bKash Logo">
        <div class="team-name"><?php echo $team ? htmlspecialchars($team['team_name']) : 'No Team'; ?></div>
        <div class="title">Team Lead</div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Main Menu</div>
        <a href="#" class="active" onclick="showSection('dashboard', this)"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="#" onclick="showSection('evaluate', this)"><i class="fas fa-edit"></i> Evaluate Member</a>
        <a href="#" onclick="showSection('performance', this)"><i class="fas fa-chart-bar"></i> Performance</a>
        <a href="#" onclick="showSection('consultations', this)"><i class="fas fa-comments"></i> Consultations</a>
        <a href="#" onclick="showSection('export-data', this)"><i class="fas fa-download"></i> Previous Data</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title" id="pageTitle">Dashboard Overview</div>
        <div class="top-right">
            <div class="digital-clock" id="digitalClock">00:00:00</div>
            <div id="clockDate" style="text-align:right; font-size: 12px; color: var(--text-gray);"></div>
        </div>
    </div>

    <div class="content-area">
        <?php if($success): ?><div class="badge badge-success" style="display:block; margin-bottom:20px; padding:15px;"><?php echo $success; ?></div><?php endif; ?>
        <?php if($error): ?><div class="badge badge-danger" style="display:block; margin-bottom:20px; padding:15px;"><?php echo $error; ?></div><?php endif; ?>

        <!-- DASHBOARD -->
        <div id="section-dashboard" class="section active">
            <div class="stats-row">
                <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-users"></i></div><div class="stat-info"><h3><?php echo $total_members; ?></h3><p>Team Members</p></div></div>
                <div class="stat-card"><div class="stat-icon green"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?php echo $evaluated_today; ?></h3><p>Evaluated Today</p></div></div>
                <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h3><?php echo $below_80; ?></h3><p>Below 80</p></div></div>
                <div class="stat-card"><div class="stat-icon red"><i class="fas fa-comment-dots"></i></div><div class="stat-info"><h3><?php echo $pending_consult; ?></h3><p>Pending Consults</p></div></div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Recent Notices</h3></div>
                <?php while($n=$notices->fetch_assoc()): ?>
                    <div class="notice-card">
                        <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                        <p style="font-size:12px;"><?php echo htmlspecialchars($n['description']); ?></p>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- EVALUATE SECTION -->
        <div id="section-evaluate" class="section">
            <div class="card">
                <h3>Submit Daily Evaluation</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Member</label>
                        <select name="employee_id" required>
                            <option value="">Choose...</option>
                            <?php mysqli_data_seek($members_query, 0); while($m=$members_query->fetch_assoc()): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Date</label><input type="date" name="date" value="<?php echo $today; ?>"></div>
                    <div style="display:grid; grid-template-columns: repeat(5, 1fr); gap:10px;">
                        <div class="form-group"><label>CSAT</label><input type="number" name="csat" step="0.01" max="20" required></div>
                        <div class="form-group"><label>Tickets</label><input type="number" name="tickets" step="0.01" max="20" required></div>
                        <div class="form-group"><label>FCR</label><input type="number" name="fcr" step="0.01" max="20" required></div>
                        <div class="form-group"><label>Resolution</label><input type="number" name="resolution_time" step="0.01" max="20" required></div>
                        <div class="form-group"><label>Response</label><input type="number" name="response_time" step="0.01" max="20" required></div>
                    </div>
                    <div class="form-group"><label>Comments</label><textarea name="comments"></textarea></div>
                    <button type="submit" name="submit_eval" class="btn btn-primary">Save Evaluation</button>
                </form>
            </div>
        </div>

        <!-- PERFORMANCE HISTORY -->
        <div id="section-performance" class="section">
            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Name</th><th>Date</th><th>Score</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while($e=$evaluations->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($e['emp_name']); ?></td>
                                <td><?php echo $e['date']; ?></td>
                                <td><span class="badge <?php echo $e['total'] < 80 ? 'badge-danger' : 'badge-success'; ?>"><?php echo $e['total']; ?></span></td>
                                <td><button class="btn btn-warning" onclick='openEditModal(<?php echo json_encode($e); ?>)'>Edit</button></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CONSULTATIONS -->
        <div id="section-consultations" class="section">
            <div class="card">
                <?php while($c=$consultations->fetch_assoc()): ?>
                    <div class="notice-card" style="border-left-color: <?php echo $c['status'] == 'scheduled' ? '#F59E0B' : '#10B981'; ?>">
                        <strong><?php echo htmlspecialchars($c['emp_name']); ?> (Score: <?php echo $c['total']; ?>)</strong>
                        <p><?php echo htmlspecialchars($c['notes']); ?></p>
                        <button class="btn btn-primary" style="margin-top:10px;" onclick='openConsultModal(<?php echo $c['id']; ?>, "<?php echo addslashes($c['notes']); ?>", "<?php echo $c['status']; ?>")'>Update Status</button>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- EXPORT SECTION -->
        <div id="section-export-data" class="section">
            <div class="card">
                <h3>Export Excel Reports</h3>
                <form method="GET" action="export_performance.php">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Download Report</button>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- MODALS -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Edit Marks</h3>
        <form method="POST">
            <input type="hidden" name="eval_id" id="edit_eval_id">
            <div class="form-group"><label>CSAT</label><input type="number" step="0.01" name="edit_csat" id="edit_csat"></div>
            <div class="form-group"><label>Tickets</label><input type="number" step="0.01" name="edit_tickets" id="edit_tickets"></div>
            <div class="form-group"><label>FCR</label><input type="number" step="0.01" name="edit_fcr" id="edit_fcr"></div>
            <div class="form-group"><label>Resolution</label><input type="number" step="0.01" name="edit_resolution_time" id="edit_res"></div>
            <div class="form-group"><label>Response</label><input type="number" step="0.01" name="edit_response_time" id="edit_resp"></div>
            <div class="form-group"><label>Comments</label><textarea name="edit_comments" id="edit_comments"></textarea></div>
            <button type="submit" name="update_eval" class="btn btn-primary">Update</button>
            <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
        </form>
    </div>
</div>

<div id="consultModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Update Consultation</h3>
        <form method="POST">
            <input type="hidden" name="consult_id" id="consult_id">
            <div class="form-group"><label>Notes</label><textarea name="consult_notes" id="consult_notes"></textarea></div>
            <div class="form-group">
                <label>Status</label>
                <select name="consult_status" id="consult_status">
                    <option value="scheduled">Scheduled</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <button type="submit" name="update_consultation" class="btn btn-primary">Update</button>
            <button type="button" class="btn" onclick="closeModal('consultModal')">Cancel</button>
        </form>
    </div>
</div>

<script>
    function showSection(id, btn) {
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.remove('active'));
        document.getElementById('section-' + id).classList.add('active');
        btn.classList.add('active');
        document.getElementById('pageTitle').innerText = id.charAt(0).toUpperCase() + id.slice(1);
    }

    function updateClock() {
        const now = new Date();
        document.getElementById('digitalClock').innerText = now.toLocaleTimeString();
        document.getElementById('clockDate').innerText = now.toDateString();
    }
    setInterval(updateClock, 1000); updateClock();

    function openEditModal(data) {
        document.getElementById('edit_eval_id').value = data.id;
        document.getElementById('edit_csat').value = data.csat;
        document.getElementById('edit_tickets').value = data.tickets;
        document.getElementById('edit_fcr').value = data.fcr;
        document.getElementById('edit_res').value = data.resolution_time;
        document.getElementById('edit_resp').value = data.response_time;
        document.getElementById('edit_comments').value = data.comments;
        document.getElementById('editModal').classList.add('active');
    }

    function openConsultModal(id, notes, status) {
        document.getElementById('consult_id').value = id;
        document.getElementById('consult_notes').value = notes;
        document.getElementById('consult_status').value = status;
        document.getElementById('consultModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
</script>

</body>
</html>