<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    ERROR / SUCCESS
==================================================*/

$error = '';

if (isset($_SESSION['forgot_error'])) {

    $error = (string) $_SESSION['forgot_error'];

    unset($_SESSION['forgot_error']);
}


/*==================================================
    ESCAPE
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
        content="#3157d5"
    >

    <title>
        Forgot Password | Care Your Health
    </title>


    <link
        rel="stylesheet"
        href="css/forgot_password.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="forgot-page">


    <div class="forgot-card">


        <!--==================================================
            BRAND
        ==================================================-->

        <div class="brand-section">

            <img
                src="/Hospital_Management_System/mobile/assets/care_your_health_logo.png"
                alt="Care Your Health"
                class="brand-logo"
            >

        </div>


        <!--==================================================
            ICON
        ==================================================-->

        <div class="security-icon">

            <div class="icon-circle">

                <span>🔐</span>

            </div>

        </div>


        <!--==================================================
            HEADING
        ==================================================-->

        <div class="heading-section">

            <h1>
                Forgot Password?
            </h1>

            <p>
                No worries. Enter your registered
                email or mobile number and we'll
                send a verification code to your email.
            </p>

        </div>


        <!--==================================================
            ERROR
        ==================================================-->

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


        <!--==================================================
            FORM
        ==================================================-->

        <form
            method="POST"
            action="forgot_password_process.php"
            id="forgotForm"
            autocomplete="on"
        >


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
                        placeholder="Enter Email or Mobile Number"
                        autocomplete="username"
                        maxlength="100"
                        required
                    >

                </div>

            </div>


            <!--==================================================
                SEND OTP
            ==================================================-->

            <button
                type="submit"
                class="send-otp-btn"
                id="sendOtpButton"
            >

                <span>
                    Send OTP
                </span>

                <span class="button-arrow">
                    →
                </span>

            </button>


        </form>


        <!--==================================================
            INFO
        ==================================================-->

        <div class="info-box">

            <span class="info-icon">
                ✉
            </span>

            <span>
                The verification code will be sent
                to the email address registered with
                your patient account.
            </span>

        </div>


        <!--==================================================
            BACK LOGIN
        ==================================================-->

        <div class="back-login">

            <a href="login.php">

                <span class="back-arrow">
                    ←
                </span>

                Back to Login

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
                Secure Patient Account Recovery
            </span>

        </div>


        <!--==================================================
            FOOTER
        ==================================================-->

        <div class="footer-text">

            Care Your Health

            <span>•</span>

            Smart Care. Better Health.

        </div>


    </div>

</div>


<script>

document
    .getElementById('forgotForm')
    .addEventListener(
        'submit',
        function ()
        {

            const button =
                document.getElementById(
                    'sendOtpButton'
                );

            button.disabled = true;

            button.querySelector(
                'span:first-child'
            ).textContent =
                'Sending OTP...';

        }
    );

</script>


</body>

</html>