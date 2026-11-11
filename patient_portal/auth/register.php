<?php

session_start();

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
        content="#18245f"
    >

    <title>
        Care Your Health - Registration
    </title>

    <link
        rel="stylesheet"
        href="register.css"
    >

</head>


<body>


<div class="register-page">


    <div class="register-card">


        <!--==================================================
            BRAND HEADER
        ==================================================-->

        <div class="register-header">


            <div class="header-glow glow-one"></div>

            <div class="header-glow glow-two"></div>


            <!-- LOGO -->

            <div class="logo-wrap">

                <img
                    src="/Hospital_Management_System/mobile/assets/care_your_health_logo.png"
                    alt="Care Your Health"
                    class="care-logo"
                >

            </div>


            <div class="welcome-label">
                PATIENT PORTAL
            </div>


            <h1>
                Create Your Account
            </h1>


            <p>
                Start your smarter healthcare journey with Care Your Health.
            </p>


        </div>


        <!--==================================================
            FORM BODY
        ==================================================-->

        <div class="register-body">


            <form
                action="patient_registration.php"
                method="POST"
                id="registerForm"
                autocomplete="on"
            >


                <!--==================================================
                    PERSONAL INFORMATION
                ==================================================-->

                <div class="section-heading">

                    <div class="section-icon">
                        01
                    </div>

                    <div>

                        <h2>
                            Personal Information
                        </h2>

                        <p>
                            Enter your basic details
                        </p>

                    </div>

                </div>


                <!-- FIRST NAME -->

                <div class="input-group">

                    <label for="first_name">
                        First Name
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            ◉
                        </span>

                        <input
                            type="text"
                            name="first_name"
                            id="first_name"
                            placeholder="Enter your first name"
                            autocomplete="given-name"
                            required
                        >

                    </div>

                </div>


                <!-- LAST NAME -->

                <div class="input-group">

                    <label for="last_name">
                        Last Name
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            ◉
                        </span>

                        <input
                            type="text"
                            name="last_name"
                            id="last_name"
                            placeholder="Enter your last name"
                            autocomplete="family-name"
                            required
                        >

                    </div>

                </div>


                <!--==================================================
                    CONTACT INFORMATION
                ==================================================-->

                <div class="section-heading section-gap">

                    <div class="section-icon">
                        02
                    </div>

                    <div>

                        <h2>
                            Contact Information
                        </h2>

                        <p>
                            Your contact details
                        </p>

                    </div>

                </div>


                <!-- MOBILE -->

                <div class="input-group">

                    <label for="mobile">
                        Mobile Number
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            ☎
                        </span>

                        <input
                            type="tel"
                            name="mobile"
                            id="mobile"
                            placeholder="Enter your mobile number"
                            inputmode="numeric"
                            autocomplete="tel"
                            required
                        >

                    </div>

                </div>


                <!-- EMAIL -->

                <div class="input-group">

                    <label for="email">
                        Email Address
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            @
                        </span>

                        <input
                            type="email"
                            name="email"
                            id="email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>


                <!--==================================================
                    SECURITY
                ==================================================-->

                <div class="section-heading section-gap">

                    <div class="section-icon">
                        03
                    </div>

                    <div>

                        <h2>
                            Account Security
                        </h2>

                        <p>
                            Create a secure password
                        </p>

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="input-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            ●
                        </span>

                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Create your password"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <!-- CONFIRM PASSWORD -->

                <div class="input-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <div class="input-box">

                        <span class="input-symbol">
                            ●
                        </span>

                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            placeholder="Confirm your password"
                            autocomplete="new-password"
                            required
                        >

                    </div>

                </div>


                <!--==================================================
                    SECURITY INFORMATION
                ==================================================-->

                <div class="secure-box">

                    <div class="secure-check">
                        ✓
                    </div>

                    <div>

                        <strong>
                            Secure Registration
                        </strong>

                        <p>
                            Your email will be verified with a secure OTP
                            before your account is activated.
                        </p>

                    </div>

                </div>


                <!--==================================================
                    REGISTER BUTTON
                ==================================================-->

                <button
                    type="submit"
                    class="register-btn"
                    id="registerButton"
                >

                    <span>
                        Create Patient Account
                    </span>

                    <span class="arrow">
                        →
                    </span>

                </button>


            </form>


            <!--==================================================
                LOGIN
            ==================================================-->

            <div class="login-link">

                <span>
                    Already have an account?
                </span>

                <a href="../../mobile/login.php">
                    Login to Care Your Health
                </a>

            </div>


            <!--==================================================
                FOOTER
            ==================================================-->

            <div class="footer-line">

                <span>
                    Secure Patient Portal
                </span>

                <span class="footer-dot">
                    •
                </span>

                <span>
                    Care Your Health
                </span>

            </div>


        </div>


    </div>


</div>


<script>


/*==================================================
    INPUT ANIMATION
==================================================*/

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const inputs =
            document.querySelectorAll(
                ".input-box input"
            );


        inputs.forEach(
            function (input) {


                input.addEventListener(
                    "focus",
                    function () {

                        const box =
                            this.closest(
                                ".input-box"
                            );

                        if (box) {

                            box.classList.add(
                                "active"
                            );

                        }

                    }
                );


                input.addEventListener(
                    "blur",
                    function () {

                        const box =
                            this.closest(
                                ".input-box"
                            );

                        if (box) {

                            box.classList.remove(
                                "active"
                            );

                        }

                    }
                );


            }
        );


        /*==========================================
            SUBMIT ANIMATION
        ==========================================*/

        const form =
            document.getElementById(
                "registerForm"
            );


        const button =
            document.getElementById(
                "registerButton"
            );


        if (
            form &&
            button
        ) {


            form.addEventListener(
                "submit",
                function () {


                    button.classList.add(
                        "loading"
                    );


                    const buttonText =
                        button.querySelector(
                            "span:first-child"
                        );


                    if (buttonText) {

                        buttonText.textContent =
                            "Creating Account...";

                    }


                }
            );


        }


    }
);


</script>


</body>

</html>