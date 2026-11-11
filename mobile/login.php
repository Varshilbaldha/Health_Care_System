<?php

declare(strict_types=1);

session_start();

/*==================================================
    MOBILE LOGIN - GLOBAL INCLUDES
==================================================*/

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/validation.php';


/*==================================================
    IF ALREADY LOGGED IN
==================================================*/

if (
    isset($_SESSION['patient_auth']) &&
    !empty($_SESSION['patient_auth']['logged_in'])
) {
    header('Location: index.php');
    exit;
}


/*==================================================
    VARIABLES
==================================================*/

$error = '';

$login_id = '';


/*==================================================
    GET ERROR FROM SESSION
==================================================*/

if (isset($_SESSION['login_error'])) {

    $error = (string) $_SESSION['login_error'];

    unset($_SESSION['login_error']);
}


/*==================================================
    LOGIN PROCESS
==================================================*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login_id = trim(
        (string) ($_POST['login_id'] ?? '')
    );

    $password = (string) (
        $_POST['password'] ?? ''
    );


    /*==============================================
        REQUIRED VALIDATION
    ==============================================*/

    if (
        $login_id === '' ||
        $password === ''
    ) {

        $error =
            'Please enter Email/Mobile and Password.';

    }


    /*==============================================
        EMAIL / MOBILE VALIDATION
    ==============================================*/

    elseif (
        !validateEmail($login_id) &&
        !validateMobile($login_id)
    ) {

        $error =
            'Please enter a valid Email or Mobile Number.';

    }


    /*==============================================
        FIND PATIENT ACCOUNT
    ==============================================*/

    else {

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


        if (!$stmt) {

            $error =
                'Database connection error.';

        }
        else {

            mysqli_stmt_bind_param(
                $stmt,
                'ss',
                $login_id,
                $login_id
            );


            if (
                !mysqli_stmt_execute($stmt)
            ) {

                $error =
                    'Unable to process login.';

                mysqli_stmt_close($stmt);

            }
            else {

                $result =
                    mysqli_stmt_get_result($stmt);


                /*==================================
                    PATIENT NOT FOUND
                ==================================*/

                if (
                    !$result ||
                    mysqli_num_rows($result) === 0
                ) {

                    if ($result) {
                        mysqli_free_result($result);
                    }

                    mysqli_stmt_close($stmt);

                    $error =
                        'Invalid Email/Mobile or Password.';

                }
                else {

                    $patient =
                        mysqli_fetch_assoc($result);


                    mysqli_free_result($result);

                    mysqli_stmt_close($stmt);


                    /*==============================
                        ACCOUNT LOCK CHECK
                    ==============================*/

                    if (
                        !empty(
                            $patient['account_locked_until']
                        ) &&
                        strtotime(
                            (string)
                            $patient['account_locked_until']
                        ) > time()
                    ) {

                        $error =
                            'Your account is temporarily locked. Please try again later.';

                    }


                    /*==============================
                        PASSWORD CHECK
                    ==============================*/

                    elseif (
                        empty($patient['password'])
                    ) {

                        $error =
                            'Password not found for this account.';

                    }


                    elseif (
                        !password_verify(
                            $password,
                            (string)
                            $patient['password']
                        )
                    ) {

                        $attempts =
                            min(
                                (
                                    (int)
                                    $patient['failed_login_attempts']
                                ) + 1,
                                5
                            );


                        /*==========================
                            LOCK AFTER 5 ATTEMPTS
                        ==========================*/

                        if ($attempts >= 5) {

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


                            if ($update_stmt) {

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


                            $error =
                                'Too many failed attempts. Account locked for 15 minutes.';

                        }
                        else {

                            /*======================
                                UPDATE ATTEMPTS
                            ======================*/

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


                            if ($update_stmt) {

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


                            $error =
                                'Invalid Email/Mobile or Password.';
                        }

                    }


                    /*==============================
                        ACCOUNT STATUS
                    ==============================*/

                    elseif (
                        (string)
                        $patient['account_status']
                        !==
                        'Active'
                    ) {

                        $error =
                            'Your account is not active.';

                    }


                    /*==============================
                        LOGIN SUCCESS
                    ==============================*/

                    else {

                        /*==========================
                            RESET LOGIN ATTEMPTS
                        ==========================*/

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


                        if ($update_stmt) {

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


                        /*==========================
                            REGENERATE SESSION
                        ==========================*/

                        session_regenerate_id(true);


                        /*==========================
                            CREATE PATIENT SESSION
                        ==========================*/

                        $_SESSION['patient_auth'] = [

                            'logged_in' =>
                                true,

                            'account_id' =>
                                (int)
                                $patient['account_id'],

                            'patient_uuid' =>
                                (string)
                                $patient['patient_uuid'],

                            'first_name' =>
                                (string)
                                $patient['first_name'],

                            'last_name' =>
                                (string)
                                $patient['last_name'],

                            'email' =>
                                (string)
                                $patient['email'],

                            'mobile' =>
                                (string)
                                $patient['mobile'],

                            'login_time' =>
                                time(),

                            'last_activity' =>
                                time()

                        ];


                        $_SESSION['login_success'] =
                            'Login Successful.';


                        /*==========================
                            GO TO MOBILE DASHBOARD
                        ==========================*/

                        header(
                            'Location: index.php'
                        );

                        exit;
                    }
                }
            }
        }
    }
}


/*==================================================
    ESCAPE FUNCTION
==================================================*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"
    >

    <meta
        name="theme-color"
        content="#17204f"
    >

    <title>
        Care Your Health - Patient Login
    </title>

    <link
        rel="stylesheet"
        href="css/login.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="login-page">


    <div class="login-card">


        <!--==================================================
            PREMIUM BRAND HEADER
        ==================================================-->

        <div class="login-header">


            <div class="brand-glow"></div>


            <div class="brand-logo">

                <img
                    src="/Hospital_Management_System/mobile/assets/care_your_health_logo.png"
                    alt="Care Your Health"
                >

            </div>


            <div class="brand-name">
                CARE YOUR HEALTH
            </div>


            <div class="brand-tagline">
                Smart Care. Better Health.
            </div>


            <div class="header-divider"></div>


            <div class="welcome-label">
                WELCOME BACK
            </div>


            <h1>
                Patient Login
            </h1>


            <p>
                Sign in to continue your care journey.
            </p>


        </div>


        <!--==================================================
            LOGIN BODY
        ==================================================-->

        <div class="login-body">


            <?php if ($error !== ''): ?>

                <div class="error-message">

                    <span class="error-icon">
                        !
                    </span>

                    <span>
                        <?= e($error) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action=""
                autocomplete="on"
            >


                <!--==================================================
                    EMAIL / MOBILE
                ==================================================-->

                <div class="form-group">

                    <label for="login_id">
                        Email Address / Mobile Number
                    </label>


                    <div class="input-wrapper">

                        <span class="input-icon">
                            ✉
                        </span>


                        <input
                            type="text"
                            id="login_id"
                            name="login_id"
                            value="<?= e($login_id) ?>"
                            placeholder="Enter Email or Mobile Number"
                            autocomplete="username"
                            required
                        >

                    </div>

                </div>


                <!--==================================================
                    PASSWORD
                ==================================================-->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <span class="input-icon">
                            🔒
                        </span>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter Password"
                            autocomplete="current-password"
                            required
                        >

                    </div>

                </div>


                <!--==================================================
                    OPTIONS
                ==================================================-->

                <div class="login-options">


                    <label class="remember">

                        <input
                            type="checkbox"
                            name="remember_me"
                            value="1"
                        >

                        <span>
                            Remember Me
                        </span>

                    </label>


                    <a
                        href="forgot_password.php"
                    >
                        Forgot Password?
                    </a>


                </div>


                <!--==================================================
                    LOGIN BUTTON
                ==================================================-->

                <button
                    type="submit"
                    class="login-btn"
                >

                    <span>
                        Login to Patient Portal
                    </span>

                    <span class="button-arrow">
                        →
                    </span>

                </button>


            </form>


            <!--==================================================
                REGISTER
            ==================================================-->

            <div class="register-text">

                <span>
                    Don't have an account?
                </span>


                <a
                    href="../patient_portal/auth/patient_registration.php"
                >
                    Create New Account
                </a>

            </div>


            <!--==================================================
                SECURITY
            ==================================================-->

            <div class="security-line">

                <span>
                    🔒
                </span>

                <span>
                    Secure Patient Portal
                </span>

            </div>


            <!--==================================================
                FOOTER
            ==================================================-->

            <div class="footer-text">

                Care Your Health
                <span>•</span>
                Smart Hospital Management System

            </div>


        </div>


    </div>


</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const inputs =
            document.querySelectorAll(
                '.input-wrapper input'
            );

        inputs.forEach(
            function (input) {

                input.addEventListener(
                    'focus',
                    function () {

                        this
                            .closest('.input-wrapper')
                            .classList.add(
                                'focused'
                            );

                    }
                );

                input.addEventListener(
                    'blur',
                    function () {

                        this
                            .closest('.input-wrapper')
                            .classList.remove(
                                'focused'
                            );

                    }
                );

            }
        );


        const form =
            document.querySelector('form');

        if (form) {

            form.addEventListener(
                'submit',
                function () {

                    const button =
                        document.querySelector(
                            '.login-btn'
                        );

                    if (button) {

                        button.classList.add(
                            'loading'
                        );

                        button.querySelector(
                            'span:first-child'
                        ).textContent =
                            'Signing In...';

                    }

                }
            );

        }

    }
);

</script>


</body>

</html>