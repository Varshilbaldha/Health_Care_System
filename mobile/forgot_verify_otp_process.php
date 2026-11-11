<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/validation.php';


/*==================================================
    REQUEST METHOD
==================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: forgot_verify_otp.php');
    exit;
}


/*==================================================
    SESSION CHECK
==================================================*/

if (
    !isset($_SESSION['forgot_password']) ||
    !is_array($_SESSION['forgot_password'])
) {

    $_SESSION['forgot_error'] =
        'Password reset session expired. Please start again.';

    header('Location: forgot_password.php');
    exit;
}


$reset =
    &$_SESSION['forgot_password'];


/*==================================================
    EXPIRY
==================================================*/

$expiry =
    (int) (
        $reset['otp_expiry_time']
        ?? 0
    );


if (
    $expiry <= 0 ||
    time() > $expiry
) {

    unset(
        $_SESSION['forgot_password']
    );

    $_SESSION['forgot_error'] =
        'OTP has expired. Please request a new one.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    ATTEMPTS
==================================================*/

$attempts =
    (int) (
        $reset['otp_attempts']
        ?? 0
    );


if ($attempts >= 3) {

    unset(
        $_SESSION['forgot_password']
    );

    $_SESSION['forgot_error'] =
        'Maximum OTP attempts exceeded. Please request a new OTP.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    ENTERED OTP
==================================================*/

$entered_otp =
    trim(
        (string) (
            $_POST['otp']
            ?? ''
        )
    );


/*==================================================
    FORMAT
==================================================*/

if (
    !validateOTP($entered_otp)
) {

    $reset['otp_attempts'] =
        $attempts + 1;

    $_SESSION['forgot_error'] =
        'Please enter a valid 6-digit OTP.';

    header('Location: forgot_verify_otp.php');
    exit;
}


/*==================================================
    OTP HASH
==================================================*/

$stored_hash =
    (string) (
        $reset['otp_hash']
        ?? ''
    );


/*==================================================
    VERIFY
==================================================*/

if (
    $stored_hash === '' ||
    !password_verify(
        $entered_otp,
        $stored_hash
    )
) {

    $new_attempts =
        $attempts + 1;


    if ($new_attempts >= 3) {

        unset(
            $_SESSION['forgot_password']
        );

        $_SESSION['forgot_error'] =
            'Maximum OTP attempts exceeded. Please request a new OTP.';

        header('Location: forgot_password.php');
        exit;
    }


    $reset['otp_attempts'] =
        $new_attempts;


    $_SESSION['forgot_error'] =
        'Incorrect OTP. Please try again.';


    header(
        'Location: forgot_verify_otp.php'
    );

    exit;
}


/*==================================================
    OTP VERIFIED
==================================================*/

$reset['otp_verified'] =
    true;


/*==================================================
    REMOVE OTP
==================================================*/

unset(
    $reset['otp_hash']
);


/*==================================================
    GO TO RESET PASSWORD
==================================================*/

header(
    'Location: reset_password.php'
);

exit;

?>