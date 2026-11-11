<?php
session_start();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <link rel="stylesheet" href="css/login1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

</head>
<header>
    <nav class="navbar">
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CarePlus</span>
        </div>
        <h1 style="text-align: center;">HOSPITAL LOGIN</h1>
    </nav>
    
    
</header>

<body>
    <script>
        function validation(event) {
            let username = document.getElementById("username").value.trim();
            let password = document.getElementById("password").value.trim();

            let usernamePattern = /^[a-zA-Z0-9_]{3,15}$/;
            let passwordPattern = /^(?=.[a-z])(?=.[A-Z])(?=.\d)(?=.[!@#$%^&])[A-Za-z0-9!@#$%^&]{8,20}$/;

            if (!usernamePattern.test(username)) {
                document.getElementById("msg1").innerHTML = "Enter Valid Username";
                event.preventDefault();
                return;
            }
            if (!passwordPattern.test(password)) {
                document.getElementById("msg").innerHTML = "* Password must contain at least 8 characters, one uppercase letter, one lowercase letter, one number, and one special character.";
                event.preventDefault();
                return;
            }
        }
    </script>

    <div class="info-card glass"><br><br><br><br><br>
        <span class="eyebrow">Onboarding · Hospital</span>
        <h2 class="info-title">Register your hospital with a trusted health network.</h2>
        <p class="info-desc">Complete a quick 5-step verification to activate your institutional dashboard, manage
            staff, OPD, IPD, billing and patient records — all from one secure place.</p>

        <ul class="info-list">
            <li><i class="fa-solid fa-shield-halved"></i><span>HIPAA-aligned data handling</span></li>
            <li><i class="fa-solid fa-clock-rotate-left"></i><span>24×7 real-time sync across departments</span></li>
            <li><i class="fa-solid fa-user-doctor"></i><span>Role-based access for doctors & admins</span></li>
            <li><i class="fa-solid fa-chart-line"></i><span>Live analytics for occupancy & revenue</span></li>
        </ul>
    </div>

    <form method="post" onsubmit="validation(event)" action="login_check.php">
        <div class="main">

            <div class="box">


                <h2 style="text-align: center;">Login</h2><br>      


                <h3>Application No : <input type="text" name="app" id="app" class="input"><br><Br>
                    <p style="color:red;font-size:16px;margin-top:-2px;" id="msg1"></p>



                    Password :
                    <input type="password" name="password" id="password" class="input">
                    <p style="color:red;font-size:16px;margin-top:-2px;" id="msg"></p><br>

                    <div class="login">
                        <input type="submit" value="Login" class="btn">
                    </div><br>

                    <?php
                    if (isset($_SESSION['login_error'])) {
                        ?>
                        <p class="login-error">
                            <?= htmlspecialchars($_SESSION['login_error']) ?>
                        </p>
                         <?php
                        unset($_SESSION['login_error']);
                    }
                    ?>

                    <p class="forgotForm">

                        <a href="forgot_password.php">Forgot Password?</a><br><br>

                    <p class="new-register">
                        New User?
                        <a href="hospital_registration.php">Create Account</a>
                    </p>

            </div>
    </form>


</body>

</html>