<?php
require_once __DIR__ . '/../config/database.php';


session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);


if (!isset($_SESSION['reset_application_no'])) {

    header("Location: forgot_password.php");
    exit();
}


$application_no = $_SESSION['reset_application_no'];


$conn = db_connect();


if (!$conn) {

    die("Connection Error: " . mysqli_connect_error());

}


$query = "SELECT admin_name, admin_email
          FROM hospital_registration
          WHERE application_no = ?";


$stmt = mysqli_prepare($conn, $query);


if (!$stmt) {

    die("Statement Error: " . mysqli_error($conn));

}


mysqli_stmt_bind_param(
    $stmt,
    "s",
    $application_no
);


mysqli_stmt_execute($stmt);


$result = mysqli_stmt_get_result($stmt);


if (mysqli_num_rows($result) !== 1) {

    header("Location: forgot_password.php");
    exit();

}


$row = mysqli_fetch_assoc($result);


$admin_name = $row['admin_name'];

$admin_email = $row['admin_email'];


// GENERATE RESET OTP

$reset_otp = random_int(100000, 999999);


// STORE RESET OTP

$_SESSION['reset_otp'] = $reset_otp;

$_SESSION['reset_otp_generated_time'] = time();

$_SESSION['reset_otp_expiry'] =
    $_SESSION['reset_otp_generated_time'] + 600;



// PHP MAILER

use PHPMailer\PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\Exception;


require '../PHPMailer/src/Exception.php';

require '../PHPMailer/src/PHPMailer.php';

require '../PHPMailer/src/SMTP.php';


$mail = new PHPMailer(true);


try {

    $mail->isSMTP();

    $mail->Host = 'smtp.gmail.com';

    $mail->SMTPAuth = true;

    $mail->Username = 'hospitalmanagement222@gmail.com';

    $mail->Password = 'evqnycxncsjydmgs';

    $mail->SMTPSecure =
        PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = 587;


    $mail->setFrom(
        'hospitalmanagement222@gmail.com',
        'Hospital Management System'
    );


    $mail->addAddress($admin_email);


    $mail->isHTML(true);


    $mail->Subject =
        "Password Reset OTP";


    $mail->Body = "

        <h2>Password Reset Request</h2>

        <p>Dear {$admin_name},</p>

        <p>
            We received a request to reset the password
            for your hospital administrator account.
        </p>

        <h1>
            {$reset_otp}
        </h1>

        <p>
            This OTP is valid for 10 minutes.
        </p>

        <p>
            Do not share this OTP with anyone.
        </p>

        <p>
            If you did not request a password reset,
            please ignore this email.
        </p>

        <br>

        <p>
            Hospital Management System Team
        </p>

    ";


    $mail->send();


    header("Location: verify_reset_otp.php");

    exit();


}
catch (Exception $e) {

    echo "OTP Email could not be sent.";

    echo "<br>";

    echo $mail->ErrorInfo;

}

?>