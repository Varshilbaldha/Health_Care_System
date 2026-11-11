<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*==================================================
    GLOBAL FILES
==================================================*/

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/validation.php';
require_once dirname(__DIR__) . '/includes/mail.php';


/*==================================================
    REQUEST METHOD
==================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    GET LOGIN ID
==================================================*/

$login_id = trim(
    (string) ($_POST['login_id'] ?? '')
);


/*==================================================
    REQUIRED CHECK
==================================================*/

if ($login_id === '') {

    $_SESSION['forgot_error'] =
        'Please enter your Email or Mobile Number.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    VALIDATE EMAIL / MOBILE
==================================================*/

if (
    !validateEmail($login_id) &&
    !validateMobile($login_id)
) {

    $_SESSION['forgot_error'] =
        'Please enter a valid Email or Mobile Number.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    FIND PATIENT
==================================================*/

$query = "
    SELECT
        account_id,
        patient_uuid,
        first_name,
        last_name,
        email,
        mobile,
        account_status
    FROM patient_accounts
    WHERE email = ?
       OR mobile = ?
    LIMIT 1
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if (!$stmt) {

    $_SESSION['forgot_error'] =
        'Unable to process your request. Please try again.';

    header('Location: forgot_password.php');
    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    'ss',
    $login_id,
    $login_id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    $_SESSION['forgot_error'] =
        'Unable to process your request. Please try again.';

    header('Location: forgot_password.php');
    exit;
}


$result =
    mysqli_stmt_get_result($stmt);


if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    if ($result) {
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    $_SESSION['forgot_error'] =
        'No patient account was found with this Email or Mobile Number.';

    header('Location: forgot_password.php');
    exit;
}


$patient =
    mysqli_fetch_assoc($result);


mysqli_free_result($result);

mysqli_stmt_close($stmt);


/*==================================================
    ACCOUNT STATUS
==================================================*/

if (
    (string) $patient['account_status']
    !== 'Active'
) {

    $_SESSION['forgot_error'] =
        'Your patient account is not active. Please contact the hospital.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    EMAIL CHECK
==================================================*/

$patient_email =
    trim((string) $patient['email']);


if ($patient_email === '') {

    $_SESSION['forgot_error'] =
        'No registered email address is available for this account.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    GENERATE OTP
==================================================*/

$otp =
    (string) random_int(
        100000,
        999999
    );


$otp_hash =
    password_hash(
        $otp,
        PASSWORD_DEFAULT
    );


$otp_expiry_time =
    time() + (10 * 60);


/*==================================================
    STORE RESET SESSION
==================================================*/

$_SESSION['forgot_password'] = [

    'account_id' =>
        (int) $patient['account_id'],

    'patient_uuid' =>
        (string) $patient['patient_uuid'],

    'first_name' =>
        (string) $patient['first_name'],

    'last_name' =>
        (string) $patient['last_name'],

    'email' =>
        $patient_email,

    'otp_hash' =>
        $otp_hash,

    'otp_expiry_time' =>
        $otp_expiry_time,

    'otp_attempts' =>
        0

];


/*==================================================
    SEND OTP
==================================================*/

$patient_name =
    trim(
        (string) $patient['first_name']
        . ' ' .
        (string) $patient['last_name']
    );


$sent =
    sendOTP(
        $patient_email,
        $patient_name,
        $otp,
        'Password Reset OTP - Care Your Health'
    );


if (!$sent) {

    unset(
        $_SESSION['forgot_password']
    );

    $_SESSION['forgot_error'] =
        'Unable to send OTP. Please try again later.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    SUCCESS
==================================================*/

$_SESSION['forgot_success'] =
    'A verification code has been sent to your registered email address.';


/*==================================================
    GO TO OTP
==================================================*/

header(
    'Location: forgot_verify_otp.php'
);

exit;

?>