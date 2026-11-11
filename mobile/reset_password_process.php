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

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    RESET SESSION CHECK
==================================================*/

if (
    !isset($_SESSION['forgot_password']) ||
    !is_array($_SESSION['forgot_password']) ||
    empty($_SESSION['forgot_password']['otp_verified'])
) {

    $_SESSION['forgot_error'] =
        'Please verify the OTP before changing your password.';

    header('Location: forgot_password.php');
    exit;
}


$reset =
    $_SESSION['forgot_password'];


/*==================================================
    PASSWORD
==================================================*/

$password =
    (string) (
        $_POST['password']
        ?? ''
    );


$confirm_password =
    (string) (
        $_POST['confirm_password']
        ?? ''
    );


/*==================================================
    REQUIRED
==================================================*/

if (
    $password === '' ||
    $confirm_password === ''
) {

    $_SESSION['reset_error'] =
        'Please enter both password fields.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    LENGTH
==================================================*/

if (
    strlen($password) < 8
) {

    $_SESSION['reset_error'] =
        'Password must contain at least 8 characters.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    MAX LENGTH
==================================================*/

if (
    strlen($password) > 72
) {

    $_SESSION['reset_error'] =
        'Password is too long. Please use a shorter password.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    MATCH
==================================================*/

if (
    !hash_equals(
        $password,
        $confirm_password
    )
) {

    $_SESSION['reset_error'] =
        'Passwords do not match.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    ACCOUNT ID
==================================================*/

$account_id =
    (int) (
        $reset['account_id']
        ?? 0
    );


if ($account_id <= 0) {

    unset(
        $_SESSION['forgot_password']
    );

    $_SESSION['forgot_error'] =
        'Password reset session is invalid. Please try again.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    HASH PASSWORD
==================================================*/

$password_hash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


if ($password_hash === false) {

    $_SESSION['reset_error'] =
        'Unable to secure your new password. Please try again.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    UPDATE PASSWORD
==================================================*/

$query = "
    UPDATE patient_accounts
    SET
        password = ?,
        failed_login_attempts = 0,
        account_locked_until = NULL
    WHERE account_id = ?
    LIMIT 1
";


$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt) {

    $_SESSION['reset_error'] =
        'Unable to update password. Please try again.';

    header('Location: reset_password.php');
    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    'si',
    $password_hash,
    $account_id
);


if (
    !mysqli_stmt_execute($stmt)
) {

    mysqli_stmt_close($stmt);

    $_SESSION['reset_error'] =
        'Unable to update password. Please try again.';

    header('Location: reset_password.php');
    exit;
}


$affected_rows =
    mysqli_stmt_affected_rows($stmt);


mysqli_stmt_close($stmt);


/*==================================================
    CHECK ACCOUNT
==================================================*/

if ($affected_rows < 0) {

    $_SESSION['reset_error'] =
        'Password update failed. Please try again.';

    header('Location: reset_password.php');
    exit;
}


/*==================================================
    REMOVE RESET SESSION
==================================================*/

unset(
    $_SESSION['forgot_password']
);


/*==================================================
    SUCCESS
==================================================*/

$_SESSION['login_success'] =
    'Password changed successfully. Please login with your new password.';


/*==================================================
    MOBILE LOGIN
==================================================*/

header(
    'Location: login.php'
);

exit;

?>