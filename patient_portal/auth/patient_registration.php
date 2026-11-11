<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Global Includes
|--------------------------------------------------------------------------
*/

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'config.php';

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'functions.php';

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'validation.php';

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'mail.php';


if ($_SERVER["REQUEST_METHOD"] !== "POST")
{
    redirect("register.php");
}


/* ============================
   Get Form Data
============================ */

$first_name = cleanInput($_POST['first_name'] ?? '');
$last_name = cleanInput($_POST['last_name'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$mobile = cleanInput($_POST['mobile'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';


/* ============================
   Required Validation
============================ */

if (
    empty($first_name) ||
    empty($last_name) ||
    empty($email) ||
    empty($mobile) ||
    empty($password) ||
    empty($confirm_password)
)
{
    die("All fields are required.");
}


/* ============================
   Name Validation
============================ */






/* ============================
   Email Validation
============================ */

if (!validateEmail($email))
{
    die("Invalid Email Address.");
}


/* ============================
   Mobile Validation
============================ */

if (!validateMobile($mobile))
{
    die("Invalid Mobile Number.");
}


/* ============================
   Password Validation
============================ */

if (!validatePassword($password))
{
    die(
        "Password must contain uppercase,
        lowercase,
        number,
        special character
        and minimum 8 characters."
    );
}


/* ============================
   Confirm Password
============================ */

if ($password !== $confirm_password)
{
    die("Passwords do not match.");
}


/* ============================
   Duplicate Email Check
============================ */

$query = "
SELECT account_id
FROM patient_accounts
WHERE email = ?
LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $email
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0)
{
    die("Email already registered.");
}


/* ============================
   Duplicate Mobile Check
============================ */

$query = "
SELECT account_id
FROM patient_accounts
WHERE mobile = ?
LIMIT 1
";

$stmt = mysqli_prepare($conn, $query);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $mobile
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0)
{
    die("Mobile Number already registered.");
}


/* ============================
   Generate UUID & OTP
============================ */

$patient_uuid = generateUUID();

$otp = generateOTP();

$password = password_hash(
    $password,
    PASSWORD_DEFAULT
);


/* ============================
   Store Registration Data
============================ */

$_SESSION['patient_registration'] = [

    'patient_uuid' => $patient_uuid,

    'first_name' => $first_name,

    'last_name' => $last_name,

    'email' => $email,

    'mobile' => $mobile,

    'password' => $password,

    'registration_source' => 'SELF',

    'mobile_verified' => 0,

    'account_status' => 'Pending',

    'created_by_hospital' => null,

    'otp' => $otp,

    'otp_generated_time' => time(),

    'otp_expiry_time' => time() + 600,

    'otp_attempts' => 0

];


/* ============================
   Send OTP Email
============================ */

$email_sent = sendOTP(

    $email,

    $first_name,

    (string)$otp,

    "Patient Registration OTP"

);

if (!$email_sent)
{
    unset($_SESSION['patient_registration']);

    die("Unable to send OTP. Please try again.");
}


/* ============================
   Redirect
============================ */

redirect("verify_otp.php");

?>