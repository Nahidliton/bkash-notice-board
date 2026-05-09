<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'team_lead') {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $type = sanitize($_POST['type']);
    $employee_id = ($type === 'individual') ? intval($_POST['employee_id']) : null;
    $team_id = ($type === 'team') ? intval($_POST['team_id']) : null;
    $created_by = $_SESSION['user_id'];
    
    $sql = "INSERT INTO notices (title, description, type, employee_id, team_id, created_by) 
            VALUES ('$title', '$description', '$type', " . ($employee_id ? $employee_id : "NULL") . ", " . ($team_id ? $team_id : "NULL") . ", $created_by)";
    
    $conn->query($sql);
    
    $redirect = ($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'admin') ? 'hr_dashboard.php' : 'team_lead_dashboard.php';
    header('Location: ' . $redirect . '?notice_added=1');
    exit();
}

header('Location: ' . (($_SESSION['role'] === 'hr' || $_SESSION['role'] === 'admin') ? 'hr_dashboard.php' : 'team_lead_dashboard.php'));
exit();
?>