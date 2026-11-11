<?php
require_once __DIR__ . '/../config/database.php';


session_start();
$conn = db_connect();

if (!$conn) {
    die("connetion Error" . mysqli_connect_errno());
} else {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $app = $_POST['app'];
        $password = $_POST['password'];


        $query = "SELECT * FROM hospital_registration WHERE application_no = ? ";

        $stmt = mysqli_prepare($conn, $query);

        if (!$stmt) {
            echo "error in preparing statement: " . mysqli_error($conn);
        }

        mysqli_stmt_bind_param($stmt, "s", $app);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) == 1) {
            $row = mysqli_fetch_assoc($result);
           
            $hashed_password = $row['password'];
            if (password_verify($password, $hashed_password)) {
                session_regenerate_id(true);

                $_SESSION['logged_in'] = true;
                $_SESSION['user_role'] = "hospital_admin";

                $_SESSION['application_no'] = $row['application_no'];
                $_SESSION['hospital_name'] = $row['hospital_name'];
                $_SESSION['admin_name'] = $row['admin_name'];
                $_SESSION['admin_username'] = $row['admin_username'];

                header("Location: ../admin/admin_dashboard.php");
                exit();
            } else {
                $_SESSION['login_error'] = "Invalid password.";
               header("Location: login.php");
                exit();
            }


        } else {

            $_SESSION['login_error'] = "Invalid application number.";
            header("Location: login.php");
            exit();
        }
    }
}

?>