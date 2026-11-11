<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['hospital_name'] = $_POST['hname'];
    $_SESSION['registration_no'] = $_POST['lino'];
    $_SESSION['hospital_type'] = $_POST['htype'];

    $_SESSION['hospital_email'] = $_POST['email'];
    $_SESSION['hospital_phone'] = $_POST['phone'];
    $_SESSION['emergency_no'] = $_POST['eno'];
    $_SESSION['website'] = $_POST['website'];

    $_SESSION['address1'] = $_POST['address1'];
    $_SESSION['address2'] = $_POST['address2'];
    $_SESSION['city'] = $_POST['city'];
    $_SESSION['state'] = $_POST['state'];
    $_SESSION['zip'] = $_POST['zip'];

    $_SESSION['admin_name'] = $_POST['adminname'];
    $_SESSION['admin_username'] = $_POST['adminusername'];
    $_SESSION['admin_email'] = $_POST['adminemail'];
    $_SESSION['admin_mobile'] = $_POST['adminmobile'];
    $_SESSION['password'] = $_POST['adminpassword'];
}

else {
    die("Invalid Request Method");

}



$required = [
    'hname',
    'lino',
    'htype',
    'email',
    'phone',
    'eno',
    'address1',
    'address2',
    'city',
    'state',
    'zip',
    'adminname',
    'adminusername',
    'adminemail',
    'adminmobile',
    'adminpassword'
];

foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) == "") {
        die("Error: $field is required.");
    }
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    die("Invalid Hospital Email.");
}

if (!filter_var($_POST['adminemail'], FILTER_VALIDATE_EMAIL)) {
    die("Invalid Administrator Email.");
}

if (!preg_match('/^[6-9][0-9]{9}$/', $_POST['phone'])) {
    die("Invalid Hospital Phone Number.");
}

if (!preg_match('/^[6-9][0-9]{9}$/', $_POST['eno'])) {
    die("Invalid Emergency Number.");
}

if (!preg_match('/^[6-9][0-9]{9}$/', $_POST['adminmobile'])) {
    die("Invalid Administrator Mobile Number.");
}

if (!preg_match('/^[0-9]{6}$/', $_POST['zip'])) {
    die("Invalid Zip Code.");
}


$password = $_POST['adminpassword'];

if (
    strlen($password) < 8 ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[@$!%*?&]/', $password)
) {
    die("Password is not strong enough.");
}
if (!isset($_FILES['license_doc']) || $_FILES['license_doc']['error'] != 0) {
    die("Please upload License Document.");
}

$allowed = [
    "application/pdf",
    "image/jpeg",
    "image/png"
];

if (!in_array($_FILES['license_doc']['type'], $allowed)) {
    die("Only PDF, JPG and PNG files are allowed.");
}

if ($_FILES['license_doc']['size'] > 200 * 1024) {
    die("License document must be less than 200 KB.");
}

$upload_dir = __DIR__ . "/../uploads/";

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$filename = time() . "_" . basename($_FILES['license_doc']['name']);

$target_file = $upload_dir . $filename;

if (move_uploaded_file($_FILES['license_doc']['tmp_name'], $target_file)) {

    $_SESSION['license_doc'] = $filename;

} else {

    die("Failed to upload the license document.");

}


$otp = rand(100000, 999999);

// Store OTP in Session
$_SESSION['otp'] = $otp;

$_SESSION['otp_generated_time'] = time();
$_SESSION['otp_expiry_time'] = $_SESSION['otp_generated_time'] + 600;


$_SESSION['application_no'] = "APP" . date("YmdHis") . rand(100, 999);




use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {

    // Tell PHPMailer to use SMTP
    $mail->isSMTP();

    // Gmail SMTP Server
    $mail->Host = 'smtp.gmail.com';

    // Enable SMTP Authentication
    $mail->SMTPAuth = true;

    // Your Gmail Address
    $mail->Username = 'hospitalmanagement222@gmail.com';

    // Your App Password
    $mail->Password = 'fakkdmpaplhhavbs';

    // Encryption
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    // Gmail Port
    $mail->Port = 587;

    // Sender
    $mail->setFrom(
        'hospitalmanagement222@gmail.com',
        'Hospital Management System'

    );

    // Receiver
    $mail->addAddress($_SESSION['admin_email']);

   
$mail->Subject = "Hospital Registration - OTP Verification";

$mail->isHTML(true);

$mail->Body = "
<div style='max-width:700px;margin:auto;font-family:Arial,Helvetica,sans-serif;background:#f4f9fc;padding:30px;border-radius:10px;border:1px solid #d6eaf8;'>

    <div style='text-align:center;padding-bottom:20px;border-bottom:2px solid #0d6efd;'>
        <h1 style='color:#0d6efd;margin:0;'>🏥 Hospital Management System</h1>
        <p style='color:#555;font-size:15px;margin-top:8px;'>
            Smart Hospital Registration Portal
        </p>
    </div>

    <br>

    <h2 style='color:#222;'>Dear Administrator,</h2>

    <p style='font-size:16px;color:#444;line-height:1.8;'>
        Thank you for registering your hospital with the
        <b>Hospital Management System</b>.
        Your registration request has been received successfully.
    </p>

    <div style='background:#ffffff;border-left:5px solid #0d6efd;padding:18px;margin:25px 0;border-radius:8px;'>

        <h3 style='margin-top:0;color:#0d6efd;'>Registration Details</h3>

        <table style='width:100%;font-size:14px;color:#333;'>

            <tr>
                <td><b>Hospital Name</b></td>
                <td>{$_SESSION['hospital_name']}</td>
            </tr>

            <tr>
                <td><b>Application Number</b></td>
                <td>{$_SESSION['application_no']}</td>
            </tr>

            <tr>
                <td><b>Administrator</b></td>
                <td>{$_SESSION['admin_name']}</td>
            </tr>

            <tr>
                <td><b>Administrator Email</b></td>
                <td>{$_SESSION['admin_email']}</td>
            </tr>

        </table>

    </div>

    <div style='text-align:center;background:#e8f4ff;padding:20px;border-radius:10px;border:2px dashed #0d6efd;'>

        <p style='font-size:18px;margin:0;color:#333;'>
            Your One-Time Password (OTP)
        </p>

        <h1 style='letter-spacing:8px;color:#0d6efd;margin:15px 0;'>
            {$otp}
        </h1>

        <p style='color:#d9534f;font-weight:bold;margin:0;'>
            This OTP is valid for 10 minutes.
        </p>

    </div>

    <br>

    <p style='font-size:15px;color:#444;line-height:1.8;'>
        Please enter the above OTP on the verification page to complete
        your hospital registration.
    </p>

    <div style='background:#fff8e5;padding:15px;border-left:5px solid orange;border-radius:8px;'>

        <h3 style='margin-top:0;color:#d35400;'>Security Notice</h3>

        <ul style='color:#555;line-height:1.8;'>

            <li>Never share your OTP with anyone.</li>

            <li>Our team will never ask for your OTP via phone, email or message.</li>

            <li>If you did not request this registration, please ignore this email.</li>

        </ul>

    </div>

    <br>

    <div style='text-align:center;border-top:1px solid #ddd;padding-top:20px;'>

        <p style='margin:0;font-size:15px;color:#555;'>
            Thank you for choosing
            <b>Hospital Management System</b>.
        </p>

        <p style='margin-top:10px;color:#888;font-size:13px;'>
            This is an automated email. Please do not reply.
        </p>

    </div>

</div>
";



    $mail->send();   

        header("Location: verify_otp.php");
        exit();
    

} catch (Exception $e) {

    echo "Email could not be sent.";

    echo "<br>";

    echo $mail->ErrorInfo;

}

