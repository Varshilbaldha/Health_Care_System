<?php

declare(strict_types=1);

session_start();


/*==================================================
    LOAD EXISTING WEBSITE CONFIG
==================================================*/

require_once dirname(__DIR__) . '/includes/config.php';


/*==================================================
    ONLY POST REQUEST
==================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: login.php');

    exit;
}


/*==================================================
    GET LOGIN DATA
==================================================*/

$login_id = trim(
    (string)($_POST['login_id'] ?? '')
);

$password = (string)(
    $_POST['password'] ?? ''
);


/*==================================================
    EMPTY CHECK
==================================================*/

if (
    $login_id === '' ||
    $password === ''
) {

    $_SESSION['login_error'] =
        'Please enter Email/Mobile and Password.';

    header('Location: login.php');

    exit;
}


/*==================================================
    DATABASE CONNECTION CHECK
==================================================*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    $_SESSION['login_error'] =
        'Database connection failed.';

    header('Location: login.php');

    exit;
}


/*==================================================
    FIND EXISTING PATIENT
==================================================*/

$sql = "

    SELECT

        account_id,
        patient_uuid,
        first_name,
        last_name,
        email,
        mobile,
        password,
        account_status,
        failed_login_attempts,
        account_locked_until

    FROM patient_accounts

    WHERE
        email = ?
        OR
        mobile = ?

    LIMIT 1

";


$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {

    $_SESSION['login_error'] =
        'Unable to process login.';

    header('Location: login.php');

    exit;
}


/*==================================================
    BIND EMAIL / MOBILE
==================================================*/

mysqli_stmt_bind_param(
    $stmt,
    'ss',
    $login_id,
    $login_id
);


/*==================================================
    EXECUTE DATABASE QUERY
==================================================*/

if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    $_SESSION['login_error'] =
        'Unable to check patient account.';

    header('Location: login.php');

    exit;
}


/*==================================================
    GET PATIENT
==================================================*/

$result = mysqli_stmt_get_result($stmt);


if (
    !$result ||
    mysqli_num_rows($result) === 0
) {

    if ($result) {

        mysqli_free_result($result);

    }

    mysqli_stmt_close($stmt);

    $_SESSION['login_error'] =
        'Email/Mobile or Password is incorrect.';

    header('Location: login.php');

    exit;
}


$patient = mysqli_fetch_assoc($result);


mysqli_free_result($result);

mysqli_stmt_close($stmt);


/*==================================================
    ACCOUNT LOCK CHECK
==================================================*/

if (
    !empty($patient['account_locked_until'])
) {

    $locked_until = strtotime(
        (string)$patient['account_locked_until']
    );


    if (
        $locked_until !== false &&
        $locked_until > time()
    ) {

        $_SESSION['login_error'] =
            'Your account is temporarily locked. Please try again later.';

        header('Location: login.php');

        exit;
    }
}


/*==================================================
    PASSWORD CHECK
==================================================*/

$stored_password = (string)(
    $patient['password'] ?? ''
);


if (
    $stored_password === '' ||
    !password_verify(
        $password,
        $stored_password
    )
) {

    $_SESSION['login_error'] =
        'Email/Mobile or Password is incorrect.';

    header('Location: login.php');

    exit;
}


/*==================================================
    ACCOUNT STATUS
==================================================*/

if (
    (string)$patient['account_status']
    !==
    'Active'
) {

    $_SESSION['login_error'] =
        'Your patient account is not active.';

    header('Location: login.php');

    exit;
}


/*==================================================
    SUCCESSFUL LOGIN
==================================================*/

session_regenerate_id(true);


/*==================================================
    CREATE SAME PATIENT AUTH SESSION
==================================================*/

$_SESSION['patient_auth'] = [

    'logged_in' => true,

    'account_id' =>
        (int)$patient['account_id'],

    'patient_uuid' =>
        (string)$patient['patient_uuid'],

    'first_name' =>
        (string)$patient['first_name'],

    'last_name' =>
        (string)$patient['last_name'],

    'email' =>
        (string)$patient['email'],

    'mobile' =>
        (string)$patient['mobile'],

    'login_time' =>
        time(),

    'last_activity' =>
        time()

];


/*==================================================
    UPDATE LAST LOGIN
==================================================*/

$update_sql = "

    UPDATE patient_accounts

    SET

        failed_login_attempts = 0,

        account_locked_until = NULL,

        last_login = NOW()

    WHERE
        account_id = ?

";


$update_stmt = mysqli_prepare(
    $conn,
    $update_sql
);


if ($update_stmt) {

    $account_id =
        (int)$patient['account_id'];


    mysqli_stmt_bind_param(
        $update_stmt,
        'i',
        $account_id
    );


    mysqli_stmt_execute(
        $update_stmt
    );


    mysqli_stmt_close(
        $update_stmt
    );
}


/*==================================================
    GO TO MOBILE DASHBOARD
==================================================*/

header(
    'Location: dashboard.php'
);

exit;

?>