<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/includes/config.php';


/*==================================================
    SESSION CHECK
==================================================*/

if (
    !isset($_SESSION['forgot_password']) ||
    !is_array($_SESSION['forgot_password'])
) {

    $_SESSION['forgot_error'] =
        'Password reset session expired. Please try again.';

    header('Location: forgot_password.php');
    exit;
}


$reset =
    $_SESSION['forgot_password'];


/*==================================================
    EXPIRY
==================================================*/

$expiry =
    (int) (
        $reset['otp_expiry_time']
        ?? 0
    );


$remaining_time = 0;


if ($expiry > time()) {

    $remaining_time =
        $expiry - time();

}


/*==================================================
    EMAIL
==================================================*/

$email =
    (string) (
        $reset['email']
        ?? ''
    );


/*==================================================
    MESSAGES
==================================================*/

$error = '';

$success = '';


if (isset($_SESSION['forgot_error'])) {

    $error =
        (string) $_SESSION['forgot_error'];

    unset($_SESSION['forgot_error']);
}


if (isset($_SESSION['forgot_success'])) {

    $success =
        (string) $_SESSION['forgot_success'];

    unset($_SESSION['forgot_success']);
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
        Verify OTP | Care Your Health
    </title>

    <link
        rel="stylesheet"
        href="css/forgot_verify_otp.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="otp-page">


    <div class="otp-card">


        <!--==================================================
            LOGO
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

        <div class="verification-icon">

            <div class="icon-circle">
                ✉
            </div>

        </div>


        <!--==================================================
            HEADING
        ==================================================-->

        <div class="heading-section">

            <h1>
                Verify Your Email
            </h1>

            <p>
                Enter the 6-digit verification code
                sent to your registered email.
            </p>

        </div>


        <!--==================================================
            EMAIL
        ==================================================-->

        <div class="email-box">

            <span>
                ✉
            </span>

            <strong>
                <?= e($email) ?>
            </strong>

        </div>


        <!--==================================================
            ERROR
        ==================================================-->

        <?php if ($error !== ''): ?>

            <div class="message error-message">

                <span class="message-icon">
                    !
                </span>

                <span>
                    <?= e($error) ?>
                </span>

            </div>

        <?php endif; ?>


        <!--==================================================
            SUCCESS
        ==================================================-->

        <?php if ($success !== ''): ?>

            <div class="message success-message">

                <span class="message-icon">
                    ✓
                </span>

                <span>
                    <?= e($success) ?>
                </span>

            </div>

        <?php endif; ?>


        <!--==================================================
            FORM
        ==================================================-->

        <form
            method="POST"
            action="forgot_verify_otp_process.php"
            id="otpForm"
        >


            <label class="otp-label">
                Verification Code
            </label>


            <div class="otp-input-container">


                <?php for ($i = 0; $i < 6; $i++): ?>

                    <input
                        type="text"
                        class="otp-input"
                        maxlength="1"
                        inputmode="numeric"
                        autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>"
                        required
                    >

                <?php endfor; ?>


            </div>


            <input
                type="hidden"
                name="otp"
                id="otp"
            >


            <button
                type="submit"
                class="verify-button"
                id="verifyButton"
            >

                <span>
                    Verify OTP
                </span>

                <span>
                    →
                </span>

            </button>


        </form>


        <!--==================================================
            TIMER
        ==================================================-->

        <div class="timer-section">

            <span class="timer-icon">
                ◷
            </span>

            <span>
                Code expires in
            </span>

            <strong id="timer">
                <?= $remaining_time ?>
            </strong>

            <span>
                seconds
            </span>

        </div>


        <!--==================================================
            RESEND
        ==================================================-->

        <div class="resend-section">

            <span>
                Didn't receive the code?
            </span>

            <a href="forgot_password.php">
                Send Again
            </a>

        </div>


        <!--==================================================
            SECURITY
        ==================================================-->

        <div class="security-note">

            🔒

            <span>
                Never share your verification code
                with anyone.
            </span>

        </div>


        <div class="footer-text">

            Care Your Health
            <span>•</span>
            Smart Care. Better Health.

        </div>


    </div>

</div>


<script>

const inputs =
    document.querySelectorAll('.otp-input');

const hiddenOtp =
    document.getElementById('otp');

const form =
    document.getElementById('otpForm');

const button =
    document.getElementById('verifyButton');


/*==================================================
    INPUT
==================================================*/

inputs.forEach(
    function (input, index)
    {

        input.addEventListener(
            'input',
            function ()
            {

                this.value =
                    this.value.replace(
                        /[^0-9]/g,
                        ''
                    );


                if (
                    this.value &&
                    index < inputs.length - 1
                ) {

                    inputs[index + 1].focus();

                }


                updateOTP();

            }
        );


        input.addEventListener(
            'keydown',
            function (event)
            {

                if (
                    event.key === 'Backspace' &&
                    this.value === '' &&
                    index > 0
                ) {

                    inputs[index - 1].focus();

                }

            }
        );

    }
);


/*==================================================
    PASTE
==================================================*/

inputs[0].addEventListener(
    'paste',
    function (event)
    {

        event.preventDefault();


        const value =
            (
                event.clipboardData ||
                window.clipboardData
            )
            .getData('text')
            .replace(
                /[^0-9]/g,
                ''
            )
            .substring(0, 6);


        value
            .split('')
            .forEach(
                function (digit, index)
                {

                    if (inputs[index]) {

                        inputs[index].value =
                            digit;

                    }

                }
            );


        updateOTP();

    }
);


/*==================================================
    UPDATE
==================================================*/

function updateOTP()
{

    let value = '';


    inputs.forEach(
        function (input)
        {

            value += input.value;

        }
    );


    hiddenOtp.value = value;


    if (value.length === 6) {

        button.classList.add('active');

    }
    else {

        button.classList.remove('active');

    }

}


/*==================================================
    SUBMIT
==================================================*/

form.addEventListener(
    'submit',
    function (event)
    {

        updateOTP();


        if (
            hiddenOtp.value.length !== 6
        ) {

            event.preventDefault();

            alert(
                'Please enter the complete 6-digit OTP.'
            );

            return;

        }


        button.disabled = true;

        button.querySelector(
            'span:first-child'
        ).textContent =
            'Verifying...';

    }
);


/*==================================================
    TIMER
==================================================*/

let remainingTime =
    <?= (int) $remaining_time ?>;


const timerElement =
    document.getElementById('timer');


const timer =
    setInterval(
        function ()
        {

            if (remainingTime <= 0) {

                clearInterval(timer);

                timerElement.textContent =
                    'Expired';

                return;

            }


            timerElement.textContent =
                remainingTime;


            remainingTime--;

        },
        1000
    );


</script>


</body>

</html>