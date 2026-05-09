<?php
require_once 'includes/session_check.php';
require_once 'includes/db_connection.php';

if ($_SESSION['role'] !== 'hr' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'team_lead') {
    header('Location: dashboard.php');
    exit();
}

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$team_id = $_SESSION['team_id'];

$export_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$employee_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

if ($export_type === 'specific' && $employee_id > 0) {
    $query = "SELECT 
                e.name AS 'Employee Name',
                e.employee_id AS 'Employee ID',
                e.designation AS 'Designation',
                COALESCE(t.team_name, 'Unassigned') AS 'Team',
                p.csat AS 'CSAT (20)',
                p.tickets AS 'Tickets (20)',
                p.fcr AS 'FCR (20)',
                p.resolution_time AS 'Resolution Time (20)',
                p.response_time AS 'Response Time (20)',
                p.total AS 'Total (100)',
                p.comments AS 'Comments',
                p.date AS 'Date',
                p.status AS 'Status'
              FROM performance p
              JOIN employees e ON p.employee_id = e.id
              LEFT JOIN teams t ON e.team_id = t.id
              WHERE p.employee_id = $employee_id
              AND p.date BETWEEN '$start_date' AND '$end_date'
              ORDER BY p.date DESC";
} else {
    if ($user_role === 'team_lead') {
        $query = "SELECT 
                    e.name AS 'Employee Name',
                    e.employee_id AS 'Employee ID',
                    e.designation AS 'Designation',
                    COALESCE(t.team_name, 'Unassigned') AS 'Team',
                    p.csat AS 'CSAT (20)',
                    p.tickets AS 'Tickets (20)',
                    p.fcr AS 'FCR (20)',
                    p.resolution_time AS 'Resolution Time (20)',
                    p.response_time AS 'Response Time (20)',
                    p.total AS 'Total (100)',
                    p.comments AS 'Comments',
                    p.date AS 'Date',
                    p.status AS 'Status'
                  FROM performance p
                  JOIN employees e ON p.employee_id = e.id
                  LEFT JOIN teams t ON e.team_id = t.id
                  WHERE e.team_id = $team_id
                  AND p.date BETWEEN '$start_date' AND '$end_date'
                  ORDER BY p.date DESC, e.name ASC";
    } else {
        $query = "SELECT 
                    e.name AS 'Employee Name',
                    e.employee_id AS 'Employee ID',
                    e.designation AS 'Designation',
                    COALESCE(t.team_name, 'Unassigned') AS 'Team',
                    p.csat AS 'CSAT (20)',
                    p.tickets AS 'Tickets (20)',
                    p.fcr AS 'FCR (20)',
                    p.resolution_time AS 'Resolution Time (20)',
                    p.response_time AS 'Response Time (20)',
                    p.total AS 'Total (100)',
                    p.comments AS 'Comments',
                    p.date AS 'Date',
                    p.status AS 'Status'
                  FROM performance p
                  JOIN employees e ON p.employee_id = e.id
                  LEFT JOIN teams t ON e.team_id = t.id
                  WHERE p.date BETWEEN '$start_date' AND '$end_date'
                  ORDER BY e.name ASC, p.date DESC";
    }
}

$result = $conn->query($query);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Performance_Report_' . $start_date . '_to_' . $end_date . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '<style>
    table { border-collapse: collapse; }
    th, td { border: 1px solid #333; padding: 8px; text-align: left; }
    th { background-color: #E91E63; color: white; font-weight: bold; }
    tr:nth-child(even) { background-color: #FCE4EC; }
    .header { background-color: #E91E63; color: white; padding: 15px; text-align: center; font-size: 18px; font-weight: bold; }
    .sub-header { color: #666; text-align: center; margin-bottom: 10px; font-size: 13px; }
    .summary { margin-bottom: 20px; font-weight: bold; }
</style>';
echo '</head><body>';

echo '<div class="header">bKash Notice Board - Performance Report</div>';
echo '<div class="sub-header">Period: ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)) . '</div>';
echo '<div class="sub-header">Generated on: ' . date('d M Y, h:i A') . '</div>';

if ($export_type === 'specific' && $employee_id > 0) {
    $emp_info = $conn->query("SELECT name, employee_id FROM employees WHERE id = $employee_id")->fetch_assoc();
    if ($emp_info) {
        echo '<div class="sub-header">Employee: ' . htmlspecialchars($emp_info['name']) . ' (' . $emp_info['employee_id'] . ')</div>';
    }
}

echo '<br>';

if ($result && $result->num_rows > 0) {
    $total_records = $result->num_rows;
    
    // Calculate summary
    $sum_query = str_replace(
        "SELECT e.name AS 'Employee Name', e.employee_id AS 'Employee ID', e.designation AS 'Designation', COALESCE(t.team_name, 'Unassigned') AS 'Team', p.csat AS 'CSAT (20)', p.tickets AS 'Tickets (20)', p.fcr AS 'FCR (20)', p.resolution_time AS 'Resolution Time (20)', p.response_time AS 'Response Time (20)', p.total AS 'Total (100)', p.comments AS 'Comments', p.date AS 'Date', p.status AS 'Status'",
        "SELECT AVG(p.total) as avg_total, MAX(p.total) as max_total, MIN(p.total) as min_total, COUNT(*) as total_count",
        $query
    );
    $sum_result = $conn->query($sum_query);
    $summary = $sum_result->fetch_assoc();
    
    echo '<div class="summary">';
    echo 'Total Records: ' . $total_records . ' | ';
    echo 'Average Score: ' . number_format($summary['avg_total'], 1) . ' | ';
    echo 'Highest Score: ' . number_format($summary['max_total'], 1) . ' | ';
    echo 'Lowest Score: ' . number_format($summary['min_total'], 1);
    echo '</div><br>';
    
    echo '<table>';
    echo '<tr>';
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo '<th>' . $field->name . '</th>';
    }
    echo '</tr>';
    
    mysqli_data_seek($result, 0);
    while ($row = $result->fetch_assoc()) {
        echo '<tr>';
        foreach ($row as $value) {
            echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p style="text-align:center; color:red; font-size:16px;">No data found for the selected period.</p>';
}

echo '</body></html>';
exit();
?>