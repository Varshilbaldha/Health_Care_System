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
    !is_array($_SESSION['forgot_password']) ||
    empty($_SESSION['forgot_password']['otp_verified'])
) {

    $_SESSION['forgot_error'] =
        'Please verify the OTP before changing your password.';

    header('Location: forgot_password.php');
    exit;
}


/*==================================================
    ERROR
==================================================*/

$error = '';

if (isset($_SESSION['reset_error'])) {

    $error =
        (string) $_SESSION['reset_error'];

    unset($_SESSION['reset_error']);
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
        Create New Password | Care Your Health
    </title>


    <link
        rel="stylesheet"
        href="css/reset_password.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="reset-page">


    <div class="reset-card">


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

        <div class="password-icon">

            <div class="icon-circle">
                🔑
            </div>

        </div>


        <!--==================================================
            HEADING
        ==================================================-->

        <div class="heading-section">

            <h1>
                Create New Password
            </h1>

            <p>
                Your identity has been verified.
                Please create a strong new password
                for your patient account.
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
            action="reset_password_process.php"
            id="resetForm"
        >


            <!--==================================================
                NEW PASSWORD
            ==================================================-->

            <div class="form-group">

                <label for="password">
                    New Password
                </label>


                <div class="input-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>


                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        data-target="password"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>


            <!--==================================================
                CONFIRM PASSWORD
            ==================================================-->

            <div class="form-group">

                <label for="confirm_password">
                    Confirm New Password
                </label>


                <div class="input-wrapper">

                    <span class="input-icon">
                        🔒
                    </span>


                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        required
                    >


                    <button
                        type="button"
                        class="password-toggle"
                        data-target="confirm_password"
                        aria-label="Show password"
                    >
                        👁
                    </button>

                </div>

            </div>


            <!--==================================================
                PASSWORD RULES
            ==================================================-->

            <div class="password-rules">

                <div>
                    <span id="lengthCheck">
                        ○
                    </span>

                    At least 8 characters
                </div>

                <div>
                    <span id="matchCheck">
                        ○
                    </span>

                    Passwords must match
                </div>

            </div>


            <!--==================================================
                UPDATE BUTTON
            ==================================================-->

            <button
                type="submit"
                class="update-button"
                id="updateButton"
            >

                <span>
                    Update Password
                </span>

                <span>
                    →
                </span>

            </button>


        </form>


        <!--==================================================
            SECURITY
        ==================================================-->

        <div class="security-note">

            🔒

            <span>
                Your password is securely encrypted
                before being stored.
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

const password =
    document.getElementById('password');

const confirmPassword =
    document.getElementById(
        'confirm_password'
    );

const lengthCheck =
    document.getElementById(
        'lengthCheck'
    );

const matchCheck =
    document.getElementById(
        'matchCheck'
    );


/*==================================================
    PASSWORD TOGGLE
==================================================*/

document
    .querySelectorAll('.password-toggle')
    .forEach(
        function (button)
        {

            button.addEventListener(
                'click',
                function ()
                {

                    const target =
                        document.getElementById(
                            this.dataset.target
                        );


                    if (
                        target.type === 'password'
                    ) {

                        target.type =
                            'text';

                        this.textContent =
                            '🙈';

                    }
                    else {

                        target.type =
                            'password';

                        this.textContent =
                            '👁';

                    }

                }
            );

        }
    );


/*==================================================
    PASSWORD CHECK
==================================================*/

function checkPassword()
{

    if (
        password.value.length >= 8
    ) {

        lengthCheck.textContent =
            '✓';

        lengthCheck.classList.add(
            'valid'
        );

    }
    else {

        lengthCheck.textContent =
            '○';

        lengthCheck.classList.remove(
            'valid'
        );

    }


    if (
        password.value !== '' &&
        password.value ===
        confirmPassword.value
    ) {

        matchCheck.textContent =
            '✓';

        matchCheck.classList.add(
            'valid'
        );

    }
    else {

        matchCheck.textContent =
            '○';

        matchCheck.classList.remove(
            'valid'
        );

    }

}


password.addEventListener(
    'input',
    checkPassword
);

confirmPassword.addEventListener(
    'input',
    checkPassword
);


/*==================================================
    SUBMIT
==================================================*/

document
    .getElementById('resetForm')
    .addEventListener(
        'submit',
        function (event)
        {

            if (
                password.value.length < 8
            ) {

                event.preventDefault();

                alert(
                    'Password must contain at least 8 characters.'
                );

                return;

            }


            if (
                password.value !==
                confirmPassword.value
            ) {

                event.preventDefault();

                alert(
                    'Passwords do not match.'
                );

                return;

            }


            const button =
                document.getElementById(
                    'updateButton'
                );


            button.disabled = true;


            button.querySelector(
                'span:first-child'
            ).textContent =
                'Updating Password...';

        }
    );

</script>


</body>

</html>