<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$username = "root";
$password = "";
$dbName = "login";

$conn = new mysqli($host, $username, $password, $dbName);

if ($conn->connect_error){
    die("Database connection failed: " . $conn->connect_error);
}

function flash(string $type, string $text){
    $_SESSION['flash'] = [
        'type' => $type,
        'text' => $text
    ];
}

function redirect_home(){
    header("Location: index.php");
    exit;
}
?>