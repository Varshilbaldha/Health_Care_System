<?php
require_once __DIR__ . '/../config/database.php';


session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (!isset($_SESSION['reset_otp_verified']) ||$_SESSION['reset_otp_verified'] !== true) {
    header("Location: forgot_password.php");
    exit();
}



if (!isset($_SESSION['reset_application_no'])) {

    header("Location: forgot_password.php");
    exit();

}


$error = "";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';

    $confirm_password = $_POST['confirm_password'] ?? '';


    

    if ($password === '' || $confirm_password === '') {

        $error = "Please enter both password fields.";

    }


    elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    }



    elseif (
        strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[@$!%*?&]/', $password)
    ) {

        $error = "Password is not strong enough.";

    }


    else {

        $conn = db_connect();


        if (!$conn) {

            die(
                "Connection Error: " .
                mysqli_connect_error()
            );

        }


        $application_no =
            $_SESSION['reset_application_no'];


        

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        // UPDATE PASSWORD

        $query = "UPDATE hospital_registration
                  SET password = ?
                  WHERE application_no = ?";


        $stmt = mysqli_prepare($conn, $query);


        if (!$stmt) {

            die(
                "Statement Error: " .
                mysqli_error($conn)
            );

        }


        mysqli_stmt_bind_param(
            $stmt,
            "ss",
            $hashed_password,
            $application_no
        );


        if (mysqli_stmt_execute($stmt)) {

            unset($_SESSION['reset_application_no']);

            unset($_SESSION['reset_otp_verified']);


            echo "<script>

            alert('Password Reset Successfully.');

            window.location='login.php';

            </script>";

            exit();

        }

        else {

            $error = "Password could not be updated.";

        }


        mysqli_stmt_close($stmt);

        mysqli_close($conn);

    }

}

?> 

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Reset Password</title>

    <link rel="stylesheet" href="css/reset.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

</head>

<body>


<div class="reset-card">

    <h1>
        <i class="fa-solid fa-key"></i>
        Reset Password
    </h1>

    <p>
        Create a new password for your
        hospital administrator account.
    </p>


    <?php

    if ($error !== '') {

        echo "
        <p style='color:red;'>
            " . htmlspecialchars($error) . "
        </p>
        ";

    }

    ?>


    <form method="POST" action="">

        <div class="password-box">
            <label>New Password :</label>

            <input
                type="password"
                name="password"
                required>


            <label>Confirm Password :</label>

            <input
                type="password"
                name="confirm_password"
                required>


            <button type="submit">

                Reset Password

        </button>

        </div>
    </form>

</div>


</body>

</html>