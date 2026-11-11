<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';


require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/config.php';

$application_no = (string) ($_SESSION['application_no'] ?? '');
if ($application_no === '' || !preg_match('/^[A-Za-z0-9]+$/', $application_no)) {
    die('Hospital application number is missing or invalid.');
}

$ctx_stmt = mysqli_prepare($conn, "SELECT hospital_id, hospital_name, database_name FROM hospital_registration WHERE application_no = ? LIMIT 1");
if (!$ctx_stmt)
    die('Unable to load hospital information.');
mysqli_stmt_bind_param($ctx_stmt, 's', $application_no);
mysqli_stmt_execute($ctx_stmt);
$ctx_result = mysqli_stmt_get_result($ctx_stmt);
$hospital_context = $ctx_result ? mysqli_fetch_assoc($ctx_result) : null;
if ($ctx_result)
    mysqli_free_result($ctx_result);
mysqli_stmt_close($ctx_stmt);
if (!$hospital_context)
    die('Hospital record not found.');

$hospital_id = (int) $hospital_context['hospital_id'];
$hospital_database = (string) $hospital_context['database_name'];
if ($hospital_database === '' || !preg_match('/^[A-Za-z0-9_]+$/', $hospital_database))
    die('Invalid hospital database.');

$hospital_conn = db_connect($hospital_database);
if (!$hospital_conn)
    die('Hospital database connection failed: ' . mysqli_connect_error());
mysqli_set_charset($hospital_conn, 'utf8mb4');

function h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>