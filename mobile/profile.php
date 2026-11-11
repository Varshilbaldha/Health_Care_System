<?php

declare(strict_types=1);

session_start();


/*==================================================
    LOGIN CHECK
==================================================*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {

    header('Location: login.php');

    exit;
}


/*==================================================
    PATIENT SESSION
==================================================*/

$patient = $_SESSION['patient_auth'];


$account_id = (int)(
    $patient['account_id'] ?? 0
);


$patient_uuid = (string)(
    $patient['patient_uuid'] ?? ''
);


$first_name = (string)(
    $patient['first_name'] ?? ''
);


$last_name = (string)(
    $patient['last_name'] ?? ''
);


$email = (string)(
    $patient['email'] ?? ''
);


$mobile = (string)(
    $patient['mobile'] ?? ''
);


$full_name = trim(
    $first_name . ' ' . $last_name
);


if ($full_name === '') {

    $full_name = 'Patient';

}


$avatar = strtoupper(
    substr(
        $first_name !== ''
            ? $first_name
            : 'P',
        0,
        1
    )
);


/*==================================================
    HTML ESCAPE
==================================================*/

function profileEscape(
    string $value
): string {

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
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        My Profile
    </title>

    <link
        rel="stylesheet"
        href="css/profile.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="mobile-app">


    <!--==================================================
        HEADER
    ==================================================-->

    <header class="profile-header">


        <a
            href="dashboard.php"
            class="back-button"
        >

            ←

        </a>


        <div>

            <span>
                PATIENT PORTAL
            </span>


            <h1>
                My Profile
            </h1>

        </div>


    </header>



    <!--==================================================
        PROFILE HERO
    ==================================================-->

    <section class="profile-card">


        <div class="profile-avatar">

            <?= profileEscape(
                $avatar
            ); ?>

        </div>


        <h2>

            <?= profileEscape(
                $full_name
            ); ?>

        </h2>


        <p>
            Patient Account
        </p>


        <?php if (
            $patient_uuid !== ''
        ): ?>

            <div class="patient-id">

                Patient ID:

                <?= profileEscape(
                    $patient_uuid
                ); ?>

            </div>

        <?php endif; ?>


    </section>



    <!--==================================================
        ACCOUNT INFORMATION
    ==================================================-->

    <section class="section">


        <h2 class="section-title">

            Account Information

        </h2>


        <div class="info-list">


            <!-- FULL NAME -->

            <div class="info-item">


                <div class="info-icon">
                    👤
                </div>


                <div>

                    <span>
                        Full Name
                    </span>


                    <strong>

                        <?= profileEscape(
                            $full_name
                        ); ?>

                    </strong>

                </div>


            </div>



            <!-- EMAIL -->

            <div class="info-item">


                <div class="info-icon">
                    ✉️
                </div>


                <div>

                    <span>
                        Email Address
                    </span>


                    <strong>

                        <?= profileEscape(
                            $email
                        ); ?>

                    </strong>

                </div>


            </div>



            <!-- MOBILE -->

            <div class="info-item">


                <div class="info-icon">
                    📱
                </div>


                <div>

                    <span>
                        Mobile Number
                    </span>


                    <strong>

                        <?= profileEscape(
                            $mobile
                        ); ?>

                    </strong>

                </div>


            </div>



            <!-- ACCOUNT ID -->

            <div class="info-item">


                <div class="info-icon">
                    🆔
                </div>


                <div>

                    <span>
                        Account ID
                    </span>


                    <strong>

                        #<?= $account_id; ?>

                    </strong>

                </div>


            </div>


        </div>


    </section>



    <!--==================================================
        QUICK OPTIONS
    ==================================================-->

    <section class="section">


        <h2 class="section-title">

            Quick Options

        </h2>


        <div class="options">


            <!-- APPOINTMENTS -->

            <a
                href="my_appointments.php"
                class="option"
            >


                <div class="option-icon">
                    📅
                </div>


                <div>

                    <strong>
                        My Appointments
                    </strong>


                    <small>
                        View your appointments
                    </small>

                </div>


                <b>
                    →
                </b>


            </a>



            <!-- HOSPITALS -->

            <a
                href="hospitals.php"
                class="option"
            >


                <div class="option-icon">
                    🏥
                </div>


                <div>

                    <strong>
                        Find Hospitals
                    </strong>


                    <small>
                        Search hospitals
                    </small>

                </div>


                <b>
                    →
                </b>


            </a>



            <!-- AI ASSISTANT -->

            <a
                href="ai_chat.php"
                class="option"
            >


                <div class="option-icon">
                    🤖
                </div>


                <div>

                    <strong>
                        AI Assistant
                    </strong>


                    <small>
                        Get smart health assistance
                    </small>

                </div>


                <b>
                    →
                </b>


            </a>


        </div>


    </section>



    <!--==================================================
        LOGOUT
    ==================================================-->

    <section class="logout-section">


        <form
            method="POST"
            action="logout.php"
        >


            <button
                type="submit"
                class="logout-button"
            >

                🚪


                <span>

                    Log Out

                </span>


            </button>


        </form>


    </section>



    <!--==================================================
        BOTTOM NAVIGATION
    ==================================================-->

    <nav class="bottom-navigation">


        <a
            href="dashboard.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <path d="m3 11 9-8 9 8"/>
                <path d="M5 10v10h14V10"/>
            </svg>

            <small>Home</small>

        </a>


        <a
            href="my_appointments.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <rect x="4" y="5" width="16" height="15" rx="3"/>
                <path d="M8 3v4"/>
                <path d="M16 3v4"/>
                <path d="M4 10h16"/>
            </svg>

            <small>Appointments</small>

        </a>


        <a
            href="book.php"
            class="nav-link book-link"
        >

            <span class="book-button">
                +
            </span>

            <small>Book</small>

        </a>


        <a
            href="hospital_search.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/>
                <circle cx="12" cy="9" r="2.5"/>
            </svg>

            <small>Hospitals</small>

        </a>


        <a
            href="profile.php"
            class="nav-link active"
        >

            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>

            <small>Profile</small>

        </a>


    </nav>


</div>


</body>

</html>