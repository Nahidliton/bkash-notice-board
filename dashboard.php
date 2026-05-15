<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

if ($_SESSION['role'] !== 'employee') {
    if ($_SESSION['role'] === 'team_lead') header('Location: team_lead_dashboard.php');
    elseif ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'admin') header('Location: hr_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$team_id = $_SESSION['team_id'];

// Fetch notices safely
$urgent_notices = $conn->query("SELECT * FROM notices WHERE type = 'urgent' AND employee_id = " . intval($user_id) . " ORDER BY created_at DESC");

$individual_notices = $conn->query("SELECT * FROM notices WHERE type = 'individual' AND employee_id = " . intval($user_id) . " ORDER BY created_at DESC");

// Only query team notices if team_id is not null
if ($team_id) {
    $team_notices = $conn->query("SELECT * FROM notices WHERE type = 'team' AND team_id = " . intval($team_id) . " ORDER BY created_at DESC");
} else {
    $team_notices = false;
}

$general_notices = $conn->query("SELECT * FROM notices WHERE type = 'general' ORDER BY created_at DESC");

// Fetch latest performance
$latest_perf = null;
$perf_result = $conn->query("SELECT * FROM performance WHERE employee_id = " . intval($user_id) . " ORDER BY date DESC LIMIT 1");
if ($perf_result && $perf_result->num_rows > 0) {
    $latest_perf = $perf_result->fetch_assoc();
}

// Fetch consultations
$consultations = $conn->query("SELECT * FROM consultations WHERE employee_id = " . intval($user_id) . " AND status = 'scheduled'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - bKash Notice Board</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #F5F5F5; }
        .navbar { background: white; padding: 15px 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .navbar h2 { color: #E91E63; font-size: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }
        
        .urgent-alert { background: #D32F2F; color: white; padding: 20px; border-radius: 12px; margin-bottom: 15px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(211, 47, 47, 0.4); } 50% { box-shadow: 0 0 0 15px rgba(211, 47, 47, 0); } }
        .urgent-alert h3 { font-size: 18px; margin-bottom: 10px; }
        
        .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card h3 { color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #FCE4EC; font-size: 16px; }
        
        .notice-item { padding: 12px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #E91E63; }
        .notice-urgent { background: #FFEBEE; border-color: #F44336; }
        .notice-general { background: #F5F5F5; }
        .notice-individual { background: #FFF3E0; border-color: #FF9800; }
        .notice-team { background: #E3F2FD; border-color: #2196F3; }
        
        .perf-table { width: 100%; border-collapse: collapse; }
        .perf-table th { background: #FCE4EC; color: #E91E63; padding: 10px; text-align: left; }
        .perf-table td { padding: 10px; border-bottom: 1px solid #E0E0E0; }
        .total-score { font-size: 36px; font-weight: 700; text-align: center; margin: 15px 0; }
        .score-green { color: #4CAF50; } .score-orange { color: #FF9800; } .score-red { color: #F44336; }
        .consult-card { background: #FFF3E0; padding: 15px; border-radius: 10px; margin-bottom: 10px; border-left: 4px solid #FF9800; }
        
        @media (max-width: 768px) { .dashboard-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <h2>Employee Dashboard</h2>
        <div>
            <span style="color: #666;">Welcome, <?php echo htmlspecialchars($name); ?> | <?php echo $_SESSION['full_id']; ?></span>
            <a href="logout.php" style="color: #E91E63; margin-left: 20px; text-decoration: none;" onclick="return confirm('Logout?')">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Urgent Alerts -->
        <?php if ($urgent_notices && $urgent_notices->num_rows > 0): ?>
            <?php while ($urgent = $urgent_notices->fetch_assoc()): ?>
                <div class="urgent-alert">
                    <h3><?php echo htmlspecialchars($urgent['title']); ?></h3>
                    <p><?php echo htmlspecialchars($urgent['description']); ?></p>
                    <small>Date: <?php echo date('d M Y', strtotime($urgent['created_at'])); ?></small>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
        
        <div class="dashboard-grid">
            <!-- Notices -->
            <div class="card">
                <h3>All Notices</h3>
                
                <?php
                $hasNotices = false;
                
                // Urgent
                if ($urgent_notices && $urgent_notices->num_rows > 0) {
                    $urgent_notices->data_seek(0);
                    while ($n = $urgent_notices->fetch_assoc()) {
                        $hasNotices = true;
                        echo '<div class="notice-item notice-urgent"><strong>' . htmlspecialchars($n['title']) . '</strong><p style="font-size:13px;color:#666;">' . htmlspecialchars($n['description']) . '</p><small>' . date('d M Y', strtotime($n['created_at'])) . '</small></div>';
                    }
                }
                
                // Individual
                if ($individual_notices && $individual_notices->num_rows > 0) {
                    $individual_notices->data_seek(0);
                    while ($n = $individual_notices->fetch_assoc()) {
                        $hasNotices = true;
                        echo '<div class="notice-item notice-individual"><strong>' . htmlspecialchars($n['title']) . '</strong><p style="font-size:13px;color:#666;">' . htmlspecialchars($n['description']) . '</p><small>' . date('d M Y', strtotime($n['created_at'])) . '</small></div>';
                    }
                }
                
                // Team
                if ($team_notices && $team_notices->num_rows > 0) {
                    $team_notices->data_seek(0);
                    while ($n = $team_notices->fetch_assoc()) {
                        $hasNotices = true;
                        echo '<div class="notice-item notice-team"><strong>👥 ' . htmlspecialchars($n['title']) . '</strong><p style="font-size:13px;color:#666;">' . htmlspecialchars($n['description']) . '</p><small>' . date('d M Y', strtotime($n['created_at'])) . '</small></div>';
                    }
                }
                
                // General
                if ($general_notices && $general_notices->num_rows > 0) {
                    $general_notices->data_seek(0);
                    while ($n = $general_notices->fetch_assoc()) {
                        $hasNotices = true;
                        echo '<div class="notice-item notice-general"><strong>' . htmlspecialchars($n['title']) . '</strong><p style="font-size:13px;color:#666;">' . htmlspecialchars($n['description']) . '</p><small>' . date('d M Y', strtotime($n['created_at'])) . '</small></div>';
                    }
                }
                
                if (!$hasNotices) {
                    echo '<p style="color:#999; text-align:center; padding:20px;">No notices available.</p>';
                }
                ?>
            </div>
            
            <!-- Performance -->
            <div class="card">
                <h3>Latest Performance</h3>
                <?php if ($latest_perf): 
                    $total = $latest_perf['total'];
                    $score_class = $total >= 80 ? 'score-green' : ($total >= 60 ? 'score-orange' : 'score-red');
                ?>
                    <table class="perf-table">
                        <tr><th>Metric</th><th>Score</th></tr>
                        <tr><td>CSAT</td><td><?php echo number_format($latest_perf['csat'], 1); ?>/20</td></tr>
                        <tr><td>Tickets Handled</td><td><?php echo number_format($latest_perf['tickets'], 1); ?>/20</td></tr>
                        <tr><td>FCR</td><td><?php echo number_format($latest_perf['fcr'], 1); ?>/20</td></tr>
                        <tr><td>Resolution Time</td><td><?php echo number_format($latest_perf['resolution_time'], 1); ?>/20</td></tr>
                        <tr><td>Response Time</td><td><?php echo number_format($latest_perf['response_time'], 1); ?>/20</td></tr>
                    </table>
                    <div class="total-score <?php echo $score_class; ?>"><?php echo number_format($total, 1); ?>/100</div>
                    <?php if ($latest_perf['comments']): ?>
                        <div style="background:#FCE4EC; padding:10px; border-radius:8px; margin-top:10px;"> <?php echo htmlspecialchars($latest_perf['comments']); ?></div>
                    <?php endif; ?>
                    <p style="text-align:center; color:#999; font-size:12px; margin-top:10px;">Date: <?php echo date('d M Y', strtotime($latest_perf['date'])); ?></p>
                <?php else: ?>
                    <p style="color:#999; text-align:center; padding:30px;">No performance data yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Consultations -->
        <?php if ($consultations && $consultations->num_rows > 0): ?>
            <div class="card" style="margin-top:20px;">
                <h3>Scheduled Consultations</h3>
                <?php while ($c = $consultations->fetch_assoc()): ?>
                    <div class="consult-card">
                        <strong><?php echo date('d M Y', strtotime($c['consultation_date'])); ?></strong>
                        <p style="font-size:13px; margin-top:5px;"><?php echo htmlspecialchars($c['notes']); ?></p>
                        <span style="font-size:11px; color:#999;">Status: <?php echo ucfirst($c['status']); ?></span>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>