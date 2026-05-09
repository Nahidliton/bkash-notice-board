<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'notice_board';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}
?>