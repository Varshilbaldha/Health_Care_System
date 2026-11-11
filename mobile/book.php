<?php

declare(strict_types=1);

session_start();


/*==================================================
    PATIENT LOGIN CHECK
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
    DATABASE CONFIG
==================================================*/

$config_file =
    dirname(__DIR__) .
    '/includes/config.php';


if (!is_file($config_file)) {

    die('Database configuration file not found.');

}


require_once $config_file;


/*==================================================
    DATABASE CHECK
==================================================*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    die('Database connection is not available.');

}


/*==================================================
    PATIENT ACCOUNT
==================================================*/

$patient =
    $_SESSION['patient_auth'];


$account_id =
    (int)(
        $patient['account_id']
        ?? 0
    );


if ($account_id <= 0) {

    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;

}


/*==================================================
    PATIENT NAME
==================================================*/

$first_name =
    (string)(
        $patient['first_name']
        ?? 'Patient'
    );


$last_name =
    (string)(
        $patient['last_name']
        ?? ''
    );


$full_name =
    trim(
        $first_name .
        ' ' .
        $last_name
    );


/*==================================================
    FLASH MESSAGES
==================================================*/

$error =
    (string)(
        $_SESSION['error']
        ?? ''
    );


$success =
    (string)(
        $_SESSION['success']
        ?? ''
    );


unset($_SESSION['error']);
unset($_SESSION['success']);


/*==================================================
    CITY SEARCH
==================================================*/

$city =
    trim(
        (string)(
            $_GET['city']
            ?? ''
        )
    );


$city =
    mb_substr(
        $city,
        0,
        50
    );


/*==================================================
    GET HOSPITALS
==================================================*/

if ($city !== '') {

    $query = "

        SELECT

            hr.hospital_id,
            hr.application_no,
            hr.hospital_name,
            hr.registration_no,
            hr.hospital_type,
            hr.hospital_email,
            hr.hospital_phone,
            hr.emergency_no,
            hr.website,
            hr.address1,
            hr.address2,
            hr.city,
            hr.state,
            hr.zip,
            hr.database_name,

            phm.mapping_id,
            phm.hospital_patient_code,
            phm.patient_status

        FROM hospital_registration AS hr

        LEFT JOIN patient_hospital_mapping AS phm

            ON phm.hospital_id =
               hr.hospital_id

            AND phm.account_id = ?

            AND phm.patient_status = 'Active'

        WHERE LOWER(
            TRIM(hr.city)
        )
        =
        LOWER(
            TRIM(?)
        )

        ORDER BY
            hr.hospital_name ASC

    ";

} else {

    $query = "

        SELECT

            hr.hospital_id,
            hr.application_no,
            hr.hospital_name,
            hr.registration_no,
            hr.hospital_type,
            hr.hospital_email,
            hr.hospital_phone,
            hr.emergency_no,
            hr.website,
            hr.address1,
            hr.address2,
            hr.city,
            hr.state,
            hr.zip,
            hr.database_name,

            phm.mapping_id,
            phm.hospital_patient_code,
            phm.patient_status

        FROM hospital_registration AS hr

        LEFT JOIN patient_hospital_mapping AS phm

            ON phm.hospital_id =
               hr.hospital_id

            AND phm.account_id = ?

            AND phm.patient_status = 'Active'

        ORDER BY

            hr.city ASC,
            hr.hospital_name ASC

    ";

}


/*==================================================
    PREPARE QUERY
==================================================*/

$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt) {

    die(
        'Database Error: ' .
        htmlspecialchars(
            mysqli_error($conn),
            ENT_QUOTES,
            'UTF-8'
        )
    );

}


/*==================================================
    BIND
==================================================*/

if ($city !== '') {

    mysqli_stmt_bind_param(
        $stmt,
        'is',
        $account_id,
        $city
    );

} else {

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $account_id
    );

}


/*==================================================
    EXECUTE
==================================================*/

if (
    !mysqli_stmt_execute($stmt)
) {

    $error_message =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    die(
        'Database Error: ' .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );

}


/*==================================================
    RESULT VARIABLES
==================================================*/

mysqli_stmt_bind_result(

    $stmt,

    $hospital_id,
    $application_no,
    $hospital_name,
    $registration_no,
    $hospital_type,
    $hospital_email,
    $hospital_phone,
    $emergency_no,
    $website,
    $address1,
    $address2,
    $hospital_city,
    $state,
    $zip,
    $database_name,

    $mapping_id,
    $hospital_patient_code,
    $patient_status

);


/*==================================================
    HOSPITAL ARRAY
==================================================*/

$hospitals = [];


while (
    mysqli_stmt_fetch($stmt)
) {

    $hospitals[] = [

        'hospital_id' =>
            (int)$hospital_id,

        'application_no' =>
            (string)($application_no ?? ''),

        'hospital_name' =>
            (string)($hospital_name ?? ''),

        'registration_no' =>
            (string)($registration_no ?? ''),

        'hospital_type' =>
            (string)($hospital_type ?? ''),

        'hospital_email' =>
            (string)($hospital_email ?? ''),

        'hospital_phone' =>
            (string)($hospital_phone ?? ''),

        'emergency_no' =>
            (string)($emergency_no ?? ''),

        'website' =>
            (string)($website ?? ''),

        'address1' =>
            (string)($address1 ?? ''),

        'address2' =>
            (string)($address2 ?? ''),

        'city' =>
            (string)($hospital_city ?? ''),

        'state' =>
            (string)($state ?? ''),

        'zip' =>
            (string)($zip ?? ''),

        'database_name' =>
            (string)($database_name ?? ''),

        'mapping_id' =>
            $mapping_id !== null
                ? (int)$mapping_id
                : 0,

        'hospital_patient_code' =>
            $hospital_patient_code !== null
                ? (string)$hospital_patient_code
                : '',

        'patient_status' =>
            $patient_status !== null
                ? (string)$patient_status
                : ''

    ];

}


mysqli_stmt_close($stmt);


/*==================================================
    ESCAPE FUNCTION
==================================================*/

function bookEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );

}


/*==================================================
    INITIAL
==================================================*/

$patient_initial =
    strtoupper(
        substr(
            $first_name,
            0,
            1
        )
    );

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
        Book Appointment
    </title>

    <link
        rel="stylesheet"
        href="css/book.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="mobile-app">


    <!--================================================
        HEADER
    =================================================-->

    <header class="book-header">

        <a
            href="index.php"
            class="back-button"
        >
            ←
        </a>


        <div class="header-content">

            <span>
                PATIENT PORTAL
            </span>

            <h1>
                Book Appointment
            </h1>

        </div>


        <div class="header-icon">
            📅
        </div>

    </header>


    <!--================================================
        PATIENT BANNER
    =================================================-->

    <section class="patient-banner">

        <div class="patient-avatar">

            <?= bookEscape(
                $patient_initial
            ); ?>

        </div>


        <div class="patient-info">

            <span>
                BOOKING FOR
            </span>

            <strong>
                <?= bookEscape(
                    $full_name
                ); ?>
            </strong>

        </div>

    </section>


    <!--================================================
        MAIN CONTENT
    =================================================-->

    <main class="book-container">


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="message error-message">

                <span class="message-icon">
                    ⚠️
                </span>

                <span>
                    <?= bookEscape($error); ?>
                </span>

            </div>

        <?php endif; ?>


        <!--================================================
            SUCCESS
        =================================================-->

        <?php if ($success !== ''): ?>

            <div class="message success-message">

                <span class="message-icon">
                    ✓
                </span>

                <span>
                    <?= bookEscape($success); ?>
                </span>

            </div>

        <?php endif; ?>


        <!--================================================
            SEARCH SECTION
        =================================================-->

        <section class="search-card">

            <div class="section-title">

                <div class="section-icon">
                    🔎
                </div>

                <div>

                    <span>
                        FIND CARE
                    </span>

                    <h2>
                        Find a Hospital
                    </h2>

                </div>

            </div>


            <p class="section-description">

                Search hospitals by city and
                choose where you want to book
                your appointment.

            </p>


            <form
                method="GET"
                action="book.php"
                class="hospital-search-form"
            >

                <div class="search-input-wrapper">

                    <span>
                        🔍
                    </span>

                    <input
                        type="text"
                        id="city"
                        name="city"
                        value="<?= bookEscape(
                            $city
                        ); ?>"
                        placeholder="Enter city name"
                        maxlength="50"
                        autocomplete="off"
                    >

                </div>


                <button
                    type="submit"
                    class="search-button"
                >

                    Search Hospitals

                </button>


                <?php if ($city !== ''): ?>

                    <a
                        href="book.php"
                        class="show-all-button"
                    >

                        Show All Hospitals

                    </a>

                <?php endif; ?>

            </form>

        </section>


        <!--================================================
            RESULTS HEADER
        =================================================-->

        <div class="results-header">

            <div>

                <span>
                    HOSPITALS
                </span>

                <h2>
                    Available Hospitals
                </h2>

            </div>


            <div class="hospital-count">

                <?= count($hospitals); ?>

            </div>

        </div>


        <?php if ($city !== ''): ?>

            <p class="search-location">

                📍 Showing hospitals in

                <strong>
                    <?= bookEscape($city); ?>
                </strong>

            </p>

        <?php else: ?>

            <p class="search-location">

                🏥 Hospitals available on
                Smart Hospital Portal

            </p>

        <?php endif; ?>


        <!--================================================
            NO HOSPITALS
        =================================================-->

        <?php if (
            count($hospitals) === 0
        ): ?>


            <section class="empty-card">

                <div class="empty-icon">
                    🏥
                </div>

                <h2>
                    No Hospitals Found
                </h2>

                <p>

                    <?php if ($city !== ''): ?>

                        We couldn't find a hospital
                        in
                        <strong>
                            <?= bookEscape($city); ?>
                        </strong>.

                    <?php else: ?>

                        No hospitals are currently
                        registered on the portal.

                    <?php endif; ?>

                </p>


                <?php if ($city !== ''): ?>

                    <a
                        href="book.php"
                        class="empty-button"
                    >

                        View All Hospitals

                    </a>

                <?php endif; ?>

            </section>


        <?php else: ?>


            <!--================================================
                HOSPITAL LIST
            =================================================-->

            <div class="hospital-list">


                <?php foreach (
                    $hospitals
                    as $hospital
                ): ?>


                    <?php

                    $is_registered =
                        (
                            (int)
                            $hospital[
                                'mapping_id'
                            ] > 0
                        );

                    ?>


                    <article
                        class="hospital-card"
                    >


                        <!--====================================
                            CARD TOP
                        ====================================-->

                        <div class="hospital-card-top">


                            <div class="hospital-logo">

                                🏥

                            </div>


                            <div class="hospital-title">

                                <span>
                                    HOSPITAL
                                </span>

                                <h3>

                                    <?= bookEscape(
                                        (string)
                                        $hospital[
                                            'hospital_name'
                                        ]
                                    ); ?>

                                </h3>

                            </div>


                            <?php if (
                                $is_registered
                            ): ?>

                                <div class="registered-badge">

                                    ✓ Registered

                                </div>

                            <?php else: ?>

                                <div class="not-registered-badge">

                                    Not Registered

                                </div>

                            <?php endif; ?>


                        </div>


                        <!--====================================
                            HOSPITAL DETAILS
                        ====================================-->

                        <div class="hospital-details">


                            <?php if (
                                $hospital[
                                    'hospital_type'
                                ] !== ''
                            ): ?>

                                <div class="detail-item">

                                    <span>
                                        TYPE
                                    </span>

                                    <strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital[
                                                'hospital_type'
                                            ]
                                        ); ?>

                                    </strong>

                                </div>

                            <?php endif; ?>


                            <div class="detail-item">

                                <span>
                                    LOCATION
                                </span>

                                <strong>

                                    <?= bookEscape(
                                        (string)
                                        $hospital[
                                            'city'
                                        ]
                                    ); ?>

                                    <?php if (
                                        $hospital[
                                            'state'
                                        ] !== ''
                                    ): ?>

                                        ,
                                        <?= bookEscape(
                                            (string)
                                            $hospital[
                                                'state'
                                            ]
                                        ); ?>

                                    <?php endif; ?>

                                </strong>

                            </div>


                            <?php if (
                                $hospital[
                                    'hospital_phone'
                                ] !== ''
                            ): ?>

                                <div class="detail-item">

                                    <span>
                                        PHONE
                                    </span>

                                    <strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital[
                                                'hospital_phone'
                                            ]
                                        ); ?>

                                    </strong>

                                </div>

                            <?php endif; ?>


                        </div>


                        <!--====================================
                            ADDRESS
                        ====================================-->

                        <?php

                        $address_parts = [];

                        if (
                            $hospital['address1'] !== ''
                        ) {
                            $address_parts[] =
                                $hospital['address1'];
                        }

                        if (
                            $hospital['address2'] !== ''
                        ) {
                            $address_parts[] =
                                $hospital['address2'];
                        }

                        if (
                            $hospital['city'] !== ''
                        ) {
                            $address_parts[] =
                                $hospital['city'];
                        }

                        if (
                            $hospital['state'] !== ''
                        ) {
                            $address_parts[] =
                                $hospital['state'];
                        }

                        if (
                            $hospital['zip'] !== ''
                        ) {
                            $address_parts[] =
                                $hospital['zip'];
                        }

                        ?>


                        <?php if (
                            !empty($address_parts)
                        ): ?>

                            <div class="hospital-address">

                                📍

                                <span>

                                    <?= bookEscape(
                                        implode(
                                            ', ',
                                            $address_parts
                                        )
                                    ); ?>

                                </span>

                            </div>

                        <?php endif; ?>


                        <!--====================================
                            PATIENT CODE
                        ====================================-->

                        <?php if (
                            $is_registered &&
                            $hospital[
                                'hospital_patient_code'
                            ] !== ''
                        ): ?>

                            <div class="patient-code">

                                <span>
                                    YOUR PATIENT CODE
                                </span>

                                <strong>

                                    <?= bookEscape(
                                        (string)
                                        $hospital[
                                            'hospital_patient_code'
                                        ]
                                    ); ?>

                                </strong>

                            </div>

                        <?php endif; ?>


                        <!--====================================
                            ACTION
                        ====================================-->

                        <div class="hospital-action">


                            <?php if (
                                $is_registered
                            ): ?>


                                <form
                                    method="POST"
                                    action="select_hospital.php"
                                >

                                    <input
                                        type="hidden"
                                        name="hospital_id"
                                        value="<?= (int)
                                            $hospital[
                                                'hospital_id'
                                            ]; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="continue-button"
                                    >

                                        Continue Booking

                                        <span>
                                            →
                                        </span>

                                    </button>

                                </form>


                            <?php else: ?>


                                <form
                                    method="POST"
                                    action="register_hospital_process.php"
                                >

                                    <input
                                        type="hidden"
                                        name="hospital_id"
                                        value="<?= (int)
                                            $hospital[
                                                'hospital_id'
                                            ]; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="register-button"
                                    >

                                        Register Hospital

                                        <span>
                                            →
                                        </span>

                                    </button>

                                </form>


                            <?php endif; ?>


                        </div>


                    </article>


                <?php endforeach; ?>


            </div>


        <?php endif; ?>


    </main>


    <!--================================================
        BOTTOM NAV
    =================================================-->

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
            class="nav-link active book-link"
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
            class="nav-link"
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