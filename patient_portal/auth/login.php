<?php

declare(strict_types=1);


/*====================================================
    GLOBAL CONFIG
====================================================*/

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


/*====================================================
    FLASH MESSAGES
====================================================*/

$error =
    $_SESSION['error'] ?? '';

$success =
    $_SESSION['success'] ?? '';


unset($_SESSION['error']);

unset($_SESSION['success']);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Patient Login
    </title>

    <link
        rel="stylesheet"
        href="login.css"
    >

</head>

<body>


<div class="login-card">


    <!--================================================
        TITLE
    =================================================-->

    <h2>
        Patient Login
    </h2>


    <p>
        Login using your Email Address or Mobile Number.
    </p>


    <!--================================================
        ERROR MESSAGE
    =================================================-->

    <?php if ($error !== ''): ?>

        <div class="login-error">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <!--================================================
        SUCCESS MESSAGE
    =================================================-->

    <?php if ($success !== ''): ?>

        <div class="login-success">

            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ); ?>

        </div>

    <?php endif; ?>


    <!--================================================
        LOGIN FORM
    =================================================-->

    <form
        action="login_process.php"
        method="POST"
    >


        <!-- Email / Mobile -->

        <div>

            <label for="login_id">
                Email / Mobile:
            </label>

            <input
                type="text"
                id="login_id"
                name="login_id"
                required
                autocomplete="username"
                placeholder="Email or Mobile Number"
                value="<?= htmlspecialchars(
                    $_POST['login_id'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>"
            >

        </div>


        <br>


        <a class="forgot" href="forgot_password.php">


        <!-- Password -->

        <div>

            <label for="password">
                Password:
            </label>

            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Enter Password"
            >

        <a class="newacc" href="register.php">


        </div>


        <br>


        <!-- Remember Me -->

        <div>

            <label>

                <input
                    type="checkbox"
                    name="remember_me"
                    value="1"
                >

                Remember Me

            </label>

        </div>


        <br>


        <!-- Login Button -->

        <button
            type="submit"
        >

            Login

        </button>


    </form>


    <br>


    <!-- Forgot Password -->

    <a href="forgot_password.php">

        Forgot Password?

    </a>


    <br>
    <br>


    <!-- Registration -->

    <a href="patient_registration.php">

        Create New Account

    </a>


</div>


</body>

</html>