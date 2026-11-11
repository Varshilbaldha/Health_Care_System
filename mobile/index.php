<?php

declare(strict_types=1);

session_start();

/*
|--------------------------------------------------------------------------
| IF ALREADY LOGGED IN
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['patient_auth']) &&
    is_array($_SESSION['patient_auth']) &&
    ($_SESSION['patient_auth']['logged_in'] ?? false) === true
) {

    header('Location: dashboard.php');
    exit;

}

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
        Smart Hospital
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f3f6fc;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }

        .app-card {

            width: 100%;

            max-width: 430px;

            background: #ffffff;

            border-radius: 28px;

            overflow: hidden;

            box-shadow:
                0 20px 60px
                rgba(30, 45, 90, .12);

        }

        .hero {

            padding: 45px 28px 40px;

            background:
                linear-gradient(
                    135deg,
                    #18234f,
                    #6355f4
                );

            color: white;

            text-align: center;

        }

        .logo {

            width: 85px;

            height: 85px;

            margin: 0 auto 22px;

            border-radius: 24px;

            background:
                rgba(255,255,255,.16);

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 42px;

        }

        .brand {

            font-size: 13px;

            font-weight: 700;

            letter-spacing: 1.5px;

            margin-bottom: 10px;

        }

        h1 {

            margin: 0 0 12px;

            font-size: 30px;

        }

        .hero p {

            margin: 0;

            font-size: 14px;

            line-height: 1.6;

            opacity: .9;

        }

        .content {

            padding: 28px;

        }

        .welcome {

            text-align: center;

            margin-bottom: 25px;

        }

        .welcome h2 {

            margin: 0 0 8px;

            font-size: 21px;

            color: #172033;

        }

        .welcome p {

            margin: 0;

            color: #7b8497;

            font-size: 14px;

            line-height: 1.5;

        }

        .login-button {

            width: 100%;

            border: none;

            border-radius: 14px;

            padding: 15px;

            background:
                linear-gradient(
                    135deg,
                    #6355f4,
                    #5145dc
                );

            color: white;

            font-size: 16px;

            font-weight: 700;

            cursor: pointer;

            text-decoration: none;

            display: block;

            text-align: center;

            box-shadow:
                0 10px 25px
                rgba(98,84,244,.25);

        }

        .register-button {

            width: 100%;

            margin-top: 12px;

            padding: 14px;

            border-radius: 14px;

            background: #f0efff;

            color: #5549df;

            font-size: 15px;

            font-weight: 700;

            text-decoration: none;

            display: block;

            text-align: center;

        }

        .features {

            margin-top: 28px;

            display: grid;

            gap: 12px;

        }

        .feature {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 13px;

            border-radius: 13px;

            background: #f7f8fc;

        }

        .feature-icon {

            width: 42px;

            height: 42px;

            border-radius: 12px;

            background: #e9e7ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;

        }

        .feature strong {

            display: block;

            font-size: 14px;

            color: #252d42;

            margin-bottom: 3px;

        }

        .feature span {

            font-size: 12px;

            color: #7d8698;

        }

        .footer {

            text-align: center;

            margin-top: 25px;

            color: #98a0b0;

            font-size: 11px;

        }

        @media (max-width: 420px) {

            body {
                padding: 0;
            }

            .app-card {
                min-height: 100vh;
                border-radius: 0;
            }

            .hero {
                padding-top: 50px;
            }

        }

    </style>

<link rel="stylesheet" href="css/common.css">
</head>

<body>


<div class="app-card">


    <!-- HERO -->

    <section class="hero">

        <div class="logo">
            🏥
        </div>

        <div class="brand">
            SMART HOSPITAL
        </div>

        <h1>
            Patient Portal
        </h1>

        <p>
            Your healthcare journey,
            simplified in one place.
        </p>

    </section>


    <!-- CONTENT -->

    <section class="content">


        <div class="welcome">

            <h2>
                Welcome Patient 👋
            </h2>

            <p>
                Login to manage appointments,
                find hospitals and access
                your healthcare services.
            </p>

        </div>


        <!-- LOGIN -->

        <a
            href="login.php"
            class="login-button"
        >
            Login to Patient Portal
        </a>


        <!-- REGISTER -->

        <a
            href="../patient_portal/auth/patient_registration.php"
            class="register-button"
        >
            Create New Account
        </a>


        <!-- FEATURES -->

        <div class="features">


            <div class="feature">

                <div class="feature-icon">
                    📅
                </div>

                <div>

                    <strong>
                        Book Appointments
                    </strong>

                    <span>
                        Schedule your hospital visit
                    </span>

                </div>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    🏥
                </div>

                <div>

                    <strong>
                        Find Hospitals
                    </strong>

                    <span>
                        Discover hospitals in your city
                    </span>

                </div>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    🤖
                </div>

                <div>

                    <strong>
                        AI Health Assistant
                    </strong>

                    <span>
                        Get smart healthcare assistance
                    </span>

                </div>

            </div>


        </div>


        <div class="footer">

            Smart Hospital Management System

        </div>


    </section>


</div>


</body>

</html>