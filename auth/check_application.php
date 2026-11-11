<?php
require_once __DIR__ . '/../config/database.php';


session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_no = trim($_POST['application_no'] ?? '');

    if ($application_no === '') {
        echo "empty";
        exit();
    }


    $conn = db_connect();

    if (!$conn) {
        echo "server_error";
        exit();
    }


    $query = "SELECT application_no
              FROM hospital_registration
              WHERE application_no = ?";


    $stmt = mysqli_prepare($conn, $query);


    if (!$stmt) {
        echo "server_error";
        exit();
    }


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $application_no
    );


    mysqli_stmt_execute($stmt);


    $result = mysqli_stmt_get_result($stmt);


    if (mysqli_num_rows($result) === 1) {
        $_SESSION['reset_application_no'] = $application_no;

        echo "found";
    } else {
        echo "not_found";
    }


    mysqli_stmt_close($stmt);

    mysqli_close($conn);
} else {
    echo "invalid_request";
}

?>