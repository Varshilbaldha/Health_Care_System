<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// OTP Verification
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_otp =
        $_POST['otp1'] .
        $_POST['otp2'] .
        $_POST['otp3'] .
        $_POST['otp4'] .
        $_POST['otp5'] .
        $_POST['otp6'];

    // Check OTP exists
    if (!isset($_SESSION['otp'])) {
        die("OTP not found. Please register again.");
    }

    // Check expiry time exists
    if (!isset($_SESSION['otp_expiry_time'])) {
        die("OTP session expired.");
    }

    // Check OTP expiry
    if (time() > $_SESSION['otp_expiry_time']) {
        echo "<script>
        alert('OTP has expired. Please request a new OTP.');
        window.location='verify_otp.php';
        </script>";
        exit();
    }

    // Verify OTP
    if ($user_otp == $_SESSION['otp']) {

        // Remove OTP after successful verification
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry_time']);

        echo "<script>
        alert('OTP Verified Successfully. You can now login Using Application No. Send On Admin E-Mail After Registration.');
        window.location='hospital_database.php';
        </script>";
        exit();

    } else {

        echo "<script>
        alert('Invalid OTP. Please try again.');
        </script>";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>OTP Verification</title>

<link rel="stylesheet" href="css/verify_otp.css">

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<body>

<div class="main">

    <div class="otp-card">

        <div class="icon">
            <i class="fa-solid fa-shield-heart"></i>
        </div>

        <h1>OTP Verification</h1>

        <p class="msg">
            We have sent a <b>6-digit verification code</b><br>
            to your registered administrator email address.
        </p>

        <form method="POST" action="">

            <label>Enter OTP</label>

            <div class="otp-box">

                <input type="text" maxlength="1" class="number" name="otp1" required>
                <input type="text" maxlength="1" class="number" name="otp2" required>
                <input type="text" maxlength="1" class="number" name="otp3" required>
                <input type="text" maxlength="1" class="number" name="otp4" required>
                <input type="text" maxlength="1" class="number" name="otp5" required>
                <input type="text" maxlength="1" class="number" name="otp6" required>

            </div>

            <p class="timer">
                Didn't receive the OTP?
                <a href="resend_otp.php">Resend OTP</a>
            </p>

            <button type="submit">
                <i class="fa-solid fa-circle-check"></i>
                Verify OTP
            </button>

        </form>

    </div>

</div>

<script>

const boxes = document.querySelectorAll(".number");

boxes.forEach((box, index) => {

    box.addEventListener("input", function () {

        this.value = this.value.replace(/[^0-9]/g, '');

        if (this.value.length == 1 && index < boxes.length - 1) {
            boxes[index + 1].focus();
        }

    });

    box.addEventListener("keydown", function (e) {

        if (e.key === "Backspace" && this.value == "" && index > 0) {
            boxes[index - 1].focus();
        }

    });

});

</script>

</body>
</html>