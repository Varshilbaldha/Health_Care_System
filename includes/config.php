<?php



error_reporting(E_ALL);
ini_set('display_errors',"1");

date_default_timezone_set('Asia/Kolkata');

if (session_status() === PHP_SESSION_NONE)
{
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$host = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$database = DB_NAME;

$conn = db_connect();

if (!$conn)
{
    die("Database Connection Failed : " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>