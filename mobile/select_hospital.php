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
    ONLY POST REQUEST
==================================================*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    $_SESSION['error'] =
        'Please select a hospital first.';

    header('Location: book.php');
    exit;
}


/*==================================================
    HOSPITAL ID
==================================================*/

$hospital_id =
    (int)(
        $_POST['hospital_id']
        ?? 0
    );


if ($hospital_id <= 0) {

    $_SESSION['error'] =
        'Invalid hospital selected.';

    header('Location: book.php');
    exit;
}


/*==================================================
    FIND HOSPITAL + PATIENT MAPPING
==================================================*/

$query = "

    SELECT

        hr.hospital_id,
        hr.hospital_name,
        hr.registration_no,
        hr.hospital_type,
        hr.hospital_phone,
        hr.emergency_no,

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

    INNER JOIN patient_hospital_mapping AS phm

        ON phm.hospital_id =
           hr.hospital_id

    WHERE

        hr.hospital_id = ?

        AND phm.account_id = ?

        AND phm.patient_status = 'Active'

    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt) {

    $_SESSION['error'] =
        'Unable to verify hospital.';

    header('Location: book.php');
    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    'ii',
    $hospital_id,
    $account_id
);


if (
    !mysqli_stmt_execute($stmt)
) {

    mysqli_stmt_close($stmt);

    $_SESSION['error'] =
        'Unable to verify hospital registration.';

    header('Location: book.php');
    exit;
}


/*==================================================
    RESULT
==================================================*/

mysqli_stmt_bind_result(

    $stmt,

    $verified_hospital_id,
    $hospital_name,
    $registration_no,
    $hospital_type,
    $hospital_phone,
    $emergency_no,

    $address1,
    $address2,
    $city,
    $state,
    $zip,

    $database_name,

    $mapping_id,
    $hospital_patient_code,
    $patient_status

);


$found =
    mysqli_stmt_fetch($stmt);


mysqli_stmt_close($stmt);


/*==================================================
    HOSPITAL NOT REGISTERED
==================================================*/

if (!$found) {

    $_SESSION['error'] =
        'You are not registered with this hospital.';

    header('Location: book.php');
    exit;
}


/*==================================================
    DATABASE NAME VALIDATION
==================================================*/

$database_name =
    trim(
        (string)$database_name
    );


if (
    $database_name === '' ||
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $database_name
    )
) {

    $_SESSION['error'] =
        'Hospital database configuration is invalid.';

    header('Location: book.php');
    exit;
}


/*==================================================
    SAVE APPOINTMENT BOOKING SESSION
==================================================*/

$_SESSION['appointment_booking'] = [

    'account_id' =>
        $account_id,

    'mapping_id' =>
        (int)$mapping_id,

    'hospital_id' =>
        (int)$verified_hospital_id,

    'hospital_name' =>
        (string)$hospital_name,

    'hospital_patient_code' =>
        (string)($hospital_patient_code ?? ''),

    'database_name' =>
        $database_name,

    'city' =>
        (string)($city ?? ''),

    'state' =>
        (string)($state ?? ''),

    'department_id' =>
        0,

    'department_name' =>
        '',

    'doctor_id' =>
        0,

    'doctor_name' =>
        '',

    'specialization' =>
        '',

    'service_id' =>
        0,

    'appointment_date' =>
        '',

    'appointment_time' =>
        '',

    'consultation_mode' =>
        'In-Person'

];


/*==================================================
    ESCAPE
==================================================*/

function selectHospitalEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );

}


/*==================================================
    PATIENT INITIAL
==================================================*/

$first_name =
    (string)(
        $patient['first_name']
        ?? 'Patient'
    );


$patient_initial =
    strtoupper(
        substr(
            $first_name,
            0,
            1
        )
    );


/*==================================================
    ADDRESS
==================================================*/

$address_parts = [];


if (
    trim((string)$address1) !== ''
) {
    $address_parts[] =
        trim((string)$address1);
}


if (
    trim((string)$address2) !== ''
) {
    $address_parts[] =
        trim((string)$address2);
}


if (
    trim((string)$city) !== ''
) {
    $address_parts[] =
        trim((string)$city);
}


if (
    trim((string)$state) !== ''
) {
    $address_parts[] =
        trim((string)$state);
}


if (
    trim((string)$zip) !== ''
) {
    $address_parts[] =
        trim((string)$zip);
}


$full_address =
    implode(
        ', ',
        $address_parts
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
        Select Hospital
    </title>

    <link
        rel="stylesheet"
        href="css/select_hospital.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="mobile-app">


    <!--================================================
        HEADER
    =================================================-->

    <header class="page-header">

        <a
            href="book.php"
            class="back-button"
        >
            ←
        </a>


        <div class="header-content">

            <span>
                BOOK APPOINTMENT
            </span>

            <h1>
                Select Hospital
            </h1>

        </div>


        <div class="header-icon">
            🏥
        </div>

    </header>


    <!--================================================
        STEP INDICATOR
    =================================================-->

    <section class="booking-progress">

        <div class="progress-step active">

            <div>
                1
            </div>

            <span>
                Hospital
            </span>

        </div>


        <div class="progress-line"></div>


        <div class="progress-step">

            <div>
                2
            </div>

            <span>
                Department
            </span>

        </div>


        <div class="progress-line"></div>


        <div class="progress-step">

            <div>
                3
            </div>

            <span>
                Doctor
            </span>

        </div>


        <div class="progress-line"></div>


        <div class="progress-step">

            <div>
                4
            </div>

            <span>
                Time
            </span>

        </div>

    </section>


    <!--================================================
        MAIN
    =================================================-->

    <main class="page-container">


        <!--================================================
            SELECTED HOSPITAL
        =================================================-->

        <section class="selected-card">


            <div class="selected-heading">

                <div class="hospital-icon">
                    🏥
                </div>


                <div>

                    <span>
                        SELECTED HOSPITAL
                    </span>

                    <h2>
                        <?= selectHospitalEscape(
                            (string)$hospital_name
                        ); ?>
                    </h2>

                </div>

            </div>


            <!--================================================
                STATUS
            =================================================-->

            <div class="registered-status">

                <span class="status-check">
                    ✓
                </span>

                <span>
                    You are registered with this hospital
                </span>

            </div>


            <!--================================================
                DETAILS
            =================================================-->

            <div class="hospital-details">


                <?php if (
                    trim((string)$hospital_type) !== ''
                ): ?>

                    <div class="detail-box">

                        <span>
                            TYPE
                        </span>

                        <strong>

                            <?= selectHospitalEscape(
                                (string)$hospital_type
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    trim((string)$city) !== ''
                ): ?>

                    <div class="detail-box">

                        <span>
                            CITY
                        </span>

                        <strong>

                            <?= selectHospitalEscape(
                                (string)$city
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    trim((string)$hospital_phone) !== ''
                ): ?>

                    <div class="detail-box">

                        <span>
                            PHONE
                        </span>

                        <strong>

                            <?= selectHospitalEscape(
                                (string)$hospital_phone
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <?php if (
                    trim((string)$emergency_no) !== ''
                ): ?>

                    <div class="detail-box">

                        <span>
                            EMERGENCY
                        </span>

                        <strong>

                            <?= selectHospitalEscape(
                                (string)$emergency_no
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>


            <!--================================================
                ADDRESS
            =================================================-->

            <?php if ($full_address !== ''): ?>

                <div class="address-box">

                    <span class="address-icon">
                        📍
                    </span>

                    <div>

                        <span>
                            HOSPITAL ADDRESS
                        </span>

                        <p>

                            <?= selectHospitalEscape(
                                $full_address
                            ); ?>

                        </p>

                    </div>

                </div>

            <?php endif; ?>


            <!--================================================
                PATIENT CODE
            =================================================-->

            <?php if (
                trim(
                    (string)$hospital_patient_code
                ) !== ''
            ): ?>

                <div class="patient-code-box">

                    <div>

                        <span>
                            YOUR HOSPITAL PATIENT CODE
                        </span>

                        <strong>

                            <?= selectHospitalEscape(
                                (string)
                                $hospital_patient_code
                            ); ?>

                        </strong>

                    </div>

                    <span class="code-icon">
                        ID
                    </span>

                </div>

            <?php endif; ?>


        </section>


        <!--================================================
            NEXT STEP
        =================================================-->

        <section class="next-card">


            <div class="next-icon">
                🩺
            </div>


            <div class="next-content">

                <span>
                    NEXT STEP
                </span>

                <h2>
                    Choose Department
                </h2>

                <p>

                    Select the department where
                    you want to consult a doctor.

                </p>

            </div>


        </section>


        <!--================================================
            CONTINUE
        =================================================-->

        <a
            href="select_department.php"
            class="continue-button"
        >

            <span>
                Continue to Departments
            </span>

            <strong>
                →
            </strong>

        </a>


        <!--================================================
            CHANGE HOSPITAL
        =================================================-->

        <a
            href="book.php"
            class="change-button"
        >

            ← Change Hospital

        </a>


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