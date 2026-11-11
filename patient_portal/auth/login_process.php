<?php


declare(strict_types=1);
session_start();

/*==================================================
    GLOBAL INCLUDES
==================================================*/

require_once dirname(__DIR__, 2) . '/includes/config.php';

require_once dirname(__DIR__, 2) . '/includes/functions.php';

require_once dirname(__DIR__, 2) . '/includes/validation.php';


/*==================================================
    ALLOW ONLY POST REQUEST
==================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    redirect('login.php');
}


/*==================================================
    GET FORM DATA
==================================================*/

$login_id = cleanInput(
    trim($_POST['login_id'] ?? '')
);

$password = $_POST['password'] ?? '';


/*==================================================
    REQUIRED VALIDATION
==================================================*/

if (
    $login_id === '' ||
    $password === ''
)
{
    $_SESSION['error'] =
        'Please enter Email/Mobile and Password.';

    redirect('login.php');
}


/*==================================================
    VALIDATE LOGIN ID
==================================================*/

if (
    !validateEmail($login_id) &&
    !validateMobile($login_id)
)
{
    $_SESSION['error'] =
        'Please enter a valid Email or Mobile Number.';

    redirect('login.php');
}


/*==================================================
    FIND PATIENT ACCOUNT
==================================================*/

$query = "
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
    WHERE email = ?
       OR mobile = ?
    LIMIT 1
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if (!$stmt)
{
    die(
        'Database Error: ' .
        mysqli_error($conn)
    );
}


mysqli_stmt_bind_param(
    $stmt,
    'ss',
    $login_id,
    $login_id
);


if (!mysqli_stmt_execute($stmt))
{
    mysqli_stmt_close($stmt);

    die(
        'Database Error: ' .
        mysqli_error($conn)
    );
}


$result = mysqli_stmt_get_result($stmt);


if (
    !$result ||
    mysqli_num_rows($result) === 0
)
{
    if ($result)
    {
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    $_SESSION['error'] =
        'Invalid Email/Mobile or Password.';

    redirect('login.php');
}


$patient =
    mysqli_fetch_assoc($result);


mysqli_free_result($result);

mysqli_stmt_close($stmt);


/*==================================================
    ACCOUNT LOCK CHECK
==================================================*/

if (
    !empty($patient['account_locked_until']) &&
    strtotime(
        $patient['account_locked_until']
    ) > time()
)
{
    $_SESSION['error'] =
        'Your account has been locked for 15 minutes because of multiple failed login attempts.';

    redirect('login.php');
}


/*==================================================
    PASSWORD EXISTS CHECK
==================================================*/

if (
    empty($patient['password'])
)
{
    $_SESSION['error'] =
        'Password not found for this account.';

    redirect('login.php');
}


/*==================================================
    VERIFY PASSWORD
==================================================*/

if (
    !password_verify(
        $password,
        $patient['password']
    )
)
{
    $attempts =
        min(
            ((int) $patient['failed_login_attempts']) + 1,
            5
        );


    /*==============================================
        LOCK ACCOUNT AFTER 5 FAILED ATTEMPTS
    ==============================================*/

    if ($attempts >= 5)
    {
        $locked_until =
            date(
                'Y-m-d H:i:s',
                strtotime('+15 minutes')
            );


        $update = "
            UPDATE patient_accounts
            SET
                failed_login_attempts = ?,
                account_locked_until = ?
            WHERE account_id = ?
        ";


        $update_stmt =
            mysqli_prepare(
                $conn,
                $update
            );


        if ($update_stmt)
        {
            mysqli_stmt_bind_param(
                $update_stmt,
                'isi',
                $attempts,
                $locked_until,
                $patient['account_id']
            );

            mysqli_stmt_execute(
                $update_stmt
            );

            mysqli_stmt_close(
                $update_stmt
            );
        }


        $_SESSION['error'] =
            'Too many failed attempts. Your account has been locked for 15 minutes.';

        redirect('login.php');
    }


    /*==============================================
        UPDATE FAILED ATTEMPTS
    ==============================================*/

    $update = "
        UPDATE patient_accounts
        SET
            failed_login_attempts = ?
        WHERE account_id = ?
    ";


    $update_stmt =
        mysqli_prepare(
            $conn,
            $update
        );


    if ($update_stmt)
    {
        mysqli_stmt_bind_param(
            $update_stmt,
            'ii',
            $attempts,
            $patient['account_id']
        );

        mysqli_stmt_execute(
            $update_stmt
        );

        mysqli_stmt_close(
            $update_stmt
        );
    }


    $_SESSION['error'] =
        'Invalid Email/Mobile or Password.';

    redirect('login.php');
}


/*==================================================
    ACCOUNT STATUS CHECK
==================================================*/

if (
    $patient['account_status'] !== 'Active'
)
{
    $_SESSION['error'] =
        'Your account is not active.';

    redirect('login.php');
}


/*==================================================
    RESET LOGIN ATTEMPTS
==================================================*/

$update = "
    UPDATE patient_accounts
    SET
        failed_login_attempts = 0,
        account_locked_until = NULL,
        last_login = NOW()
    WHERE account_id = ?
";


$update_stmt =
    mysqli_prepare(
        $conn,
        $update
    );


if ($update_stmt)
{
    mysqli_stmt_bind_param(
        $update_stmt,
        'i',
        $patient['account_id']
    );

    mysqli_stmt_execute(
        $update_stmt
    );

    mysqli_stmt_close(
        $update_stmt
    );
}


/*==================================================
    REGENERATE SESSION ID
==================================================*/

session_regenerate_id(true);


/*==================================================
    CREATE PATIENT AUTH SESSION
==================================================*/

$_SESSION['patient_auth'] = [

    'logged_in' =>
        true,

    'account_id' =>
        (int) $patient['account_id'],

    'patient_uuid' =>
        $patient['patient_uuid'],

    'first_name' =>
        $patient['first_name'],

    'last_name' =>
        $patient['last_name'],

    'email' =>
        $patient['email'],

    'mobile' =>
        $patient['mobile'],

    'login_time' =>
        time(),

    'last_activity' =>
        time()

];


/*==================================================
    SUCCESS MESSAGE
==================================================*/

$_SESSION['success'] =
    'Login Successful.';


/*==================================================
    REDIRECT TO PATIENT DASHBOARD
==================================================*/

header(
    "Location: /Hospital_Management_System/patient_portal/dashboard.php"
);

?>