<?php

declare(strict_types=1);


/*====================================================
    LOAD GLOBAL CONFIG
====================================================*/

require_once dirname(__DIR__, 2) . '/includes/config.php';


/*====================================================
    CHECK REGISTRATION SESSION
====================================================*/

if (
    !isset($_SESSION['patient_registration']) ||
    !is_array($_SESSION['patient_registration'])
)
{
    $_SESSION['error'] =
        "Registration session expired. Please register again.";

    redirect("patient_registration.php");
}


/*====================================================
    CHECK OTP EXPIRY TIME
====================================================*/

$otp_expiry_time =
    (int) (
        $_SESSION['patient_registration']['otp_expiry_time']
        ?? 0
    );


$remaining_time = 0;


if ($otp_expiry_time > time())
{
    $remaining_time =
        $otp_expiry_time - time();
}


/*====================================================
    IF OTP ALREADY EXPIRED
====================================================*/

if ($remaining_time <= 0)
{
    $_SESSION['error'] =
        "Your OTP has expired. Please request a new OTP.";
}


/*====================================================
    PATIENT EMAIL
====================================================*/

$patient_email =
    $_SESSION['patient_registration']['email']
    ?? '';

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#3157d5"
    >

    <title>
        Verify OTP | Care Your Health
    </title>


    <!-- Google Font -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- OTP CSS -->

    <link
        rel="stylesheet"
        href="verify_otp.css"
    >

</head>


<body>


<div class="page-wrapper">


    <div class="otp-card">


        <!--================================================
            LOGO
        =================================================-->

        <div class="brand-section">

            <img
                src="/Hospital_Management_System/mobile/assets/care_your_health_logo.png"
                alt="Care Your Health"
                class="brand-logo"
            >

        </div>


        <!--================================================
            ICON
        =================================================-->

        <div class="verification-icon">

            <div class="icon-circle">

                <span>✉</span>

            </div>

        </div>


        <!--================================================
            TITLE
        =================================================-->

        <div class="heading-section">

            <h1>
                Verify Your Email
            </h1>

            <p>
                Enter the 6-digit verification code
                sent to your email address.
            </p>

        </div>


        <!--================================================
            EMAIL
        =================================================-->

        <div class="email-box">

            <span class="email-icon">
                ✉
            </span>

            <span class="email-text">

                <?= htmlspecialchars(
                    $patient_email,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>

            </span>

        </div>


        <!--================================================
            ERROR MESSAGE
        =================================================-->

        <?php if (isset($_SESSION['error'])): ?>

            <div class="message error-message">

                <span class="message-icon">
                    !
                </span>

                <span>

                    <?= htmlspecialchars(
                        $_SESSION['error'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </span>

            </div>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <!--================================================
            SUCCESS MESSAGE
        =================================================-->

        <?php if (isset($_SESSION['success'])): ?>

            <div class="message success-message">

                <span class="message-icon">
                    ✓
                </span>

                <span>

                    <?= htmlspecialchars(
                        $_SESSION['success'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </span>

            </div>

            <?php unset($_SESSION['success']); ?>

        <?php endif; ?>


        <!--================================================
            OTP FORM
        =================================================-->

        <form
            action="verify_otp_process.php"
            method="POST"
            id="otpForm"
        >


            <label class="otp-label">

                Verification Code

            </label>


            <div class="otp-input-container">


                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                >

                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    required
                >

                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    required
                >

                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    required
                >

                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    required
                >

                <input
                    type="text"
                    class="otp-input"
                    maxlength="1"
                    inputmode="numeric"
                    required
                >


            </div>


            <!-- Hidden actual OTP -->

            <input
                type="hidden"
                name="otp"
                id="otp"
            >


            <!--================================================
                VERIFY BUTTON
            =================================================-->

            <button
                type="submit"
                class="verify-button"
                id="verifyButton"
            >

                <span>
                    Verify OTP
                </span>

                <span class="button-arrow">
                    →
                </span>

            </button>


        </form>


        <!--================================================
            TIMER
        =================================================-->

        <div class="timer-section">

            <div class="timer-icon">
                ◷
            </div>

            <div>

                <span>
                    Code expires in
                </span>

                <strong id="timer">
                    <?= $remaining_time; ?>
                </strong>

                <span>
                    seconds
                </span>

            </div>

        </div>


        <!--================================================
            RESEND
        =================================================-->

        <div class="resend-section">

            <span>
                Didn't receive the code?
            </span>

            <a
                href="resend_otp.php"
                id="resendLink"
            >
                Resend OTP
            </a>

        </div>


        <!--================================================
            SECURITY NOTE
        =================================================-->

        <div class="security-note">

            <span class="lock-icon">
                🔒
            </span>

            <span>
                Your verification code is secure
                and should not be shared with anyone.
            </span>

        </div>


        <!--================================================
            BRAND
        =================================================-->

        <div class="footer-brand">

            Care Your Health

            <span>
                •
            </span>

            Smart Care. Better Health.

        </div>


    </div>

</div>


<!--====================================================
    OTP SCRIPT
=====================================================-->

<script>

const otpInputs =
    document.querySelectorAll(".otp-input");

const hiddenOtp =
    document.getElementById("otp");

const otpForm =
    document.getElementById("otpForm");

const verifyButton =
    document.getElementById("verifyButton");


/*====================================================
    OTP INPUT
====================================================*/

otpInputs.forEach((input, index) =>
{

    input.addEventListener(
        "input",
        function ()
        {

            this.value =
                this.value.replace(
                    /[^0-9]/g,
                    ""
                );


            if (
                this.value &&
                index < otpInputs.length - 1
            )
            {
                otpInputs[index + 1].focus();
            }


            updateOTP();

        }
    );


    input.addEventListener(
        "keydown",
        function (event)
        {

            if (
                event.key === "Backspace" &&
                !this.value &&
                index > 0
            )
            {
                otpInputs[index - 1].focus();
            }

        }
    );


    input.addEventListener(
        "focus",
        function ()
        {
            this.select();
        }
    );

});


/*====================================================
    OTP PASTE
====================================================*/

otpInputs[0].addEventListener(
    "paste",
    function (event)
    {

        event.preventDefault();

        const pastedData =
            (
                event.clipboardData ||
                window.clipboardData
            )
            .getData("text")
            .replace(
                /[^0-9]/g,
                ""
            )
            .substring(0, 6);


        if (!pastedData)
        {
            return;
        }


        pastedData
            .split("")
            .forEach(
                (digit, index) =>
                {

                    if (
                        otpInputs[index]
                    )
                    {
                        otpInputs[index].value =
                            digit;
                    }

                }
            );


        updateOTP();


        const lastIndex =
            Math.min(
                pastedData.length,
                6
            ) - 1;


        if (lastIndex >= 0)
        {
            otpInputs[lastIndex].focus();
        }

    }
);


/*====================================================
    UPDATE OTP
====================================================*/

function updateOTP()
{

    let otp = "";

    otpInputs.forEach(
        input =>
        {
            otp += input.value;
        }
    );


    hiddenOtp.value = otp;


    if (otp.length === 6)
    {
        verifyButton.classList.add(
            "active"
        );
    }
    else
    {
        verifyButton.classList.remove(
            "active"
        );
    }

}


/*====================================================
    FORM SUBMIT
====================================================*/

otpForm.addEventListener(
    "submit",
    function (event)
    {

        updateOTP();


        if (
            hiddenOtp.value.length !== 6
        )
        {
            event.preventDefault();

            alert(
                "Please enter the complete 6-digit OTP."
            );

            otpInputs[0].focus();

            return;
        }


        verifyButton.disabled = true;

        verifyButton.querySelector(
            "span"
        ).textContent = "Verifying...";

    }
);


/*====================================================
    TIMER
====================================================*/

let remainingTime =
    <?= (int) $remaining_time; ?>;


const timerElement =
    document.getElementById("timer");


const timer =
    setInterval(
        function ()
        {

            if (remainingTime <= 0)
            {

                clearInterval(timer);

                timerElement.textContent =
                    "Expired";

                document
                    .getElementById(
                        "resendLink"
                    )
                    .classList.add(
                        "expired"
                    );

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