<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';


error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


/*
|--------------------------------------------------------------------------
| SELECT DOCTOR
|--------------------------------------------------------------------------
| Mobile Appointment Booking - Step 4
|--------------------------------------------------------------------------
|
| Previous:
| select_department.php
|
| Current:
| select_doctor.php
|
| Next:
| select_date.php
|
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/auth_check.php';


/*====================================================
    PAGE TITLE
====================================================*/

$page_title =
    "Select Doctor";


/*====================================================
    CHECK APPOINTMENT SESSION
====================================================*/

if (
    !isset(
        $_SESSION['appointment_booking']
    )
    ||
    !is_array(
        $_SESSION['appointment_booking']
    )
) {

    $_SESSION['error'] =
        "Please select a hospital first.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET BOOKING SESSION
====================================================*/

$booking =
    $_SESSION['appointment_booking'];


/*====================================================
    BOOKING DATA
====================================================*/

$account_id =
    (int) (
        $booking['account_id']
        ?? 0
    );


$mapping_id =
    (int) (
        $booking['mapping_id']
        ?? 0
    );


$hospital_id =
    (int) (
        $booking['hospital_id']
        ?? 0
    );


$hospital_name =
    trim(
        (string) (
            $booking['hospital_name']
            ?? ''
        )
    );


$hospital_patient_code =
    trim(
        (string) (
            $booking['hospital_patient_code']
            ?? ''
        )
    );


$city =
    trim(
        (string) (
            $booking['city']
            ?? ''
        )
    );


$state =
    trim(
        (string) (
            $booking['state']
            ?? ''
        )
    );


$hospital_database =
    trim(
        (string) (
            $booking['database_name']
            ?? ''
        )
    );


/*====================================================
    VALIDATE BOOKING SESSION
====================================================*/

if (
    $account_id <= 0
    ||
    $mapping_id <= 0
    ||
    $hospital_id <= 0
    ||
    $hospital_database === ''
) {

    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid appointment booking session.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    VALIDATE DATABASE NAME
====================================================*/

if (
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
) {

    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid hospital database configuration.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET DEPARTMENT ID
====================================================*/

/*
    IMPORTANT:

    First priority:
        POST department_id

    Second priority:
        Existing session department_id

    This prevents the page from breaking when
    refreshed or reopened from browser history.
*/


$department_id = 0;


/*----------------------------------------------------
    POST DEPARTMENT
----------------------------------------------------*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset(
        $_POST['department_id']
    )
) {

    $postedDepartmentId =
        filter_var(
            $_POST['department_id'],
            FILTER_VALIDATE_INT
        );


    if (
        $postedDepartmentId !== false
        &&
        $postedDepartmentId !== null
        &&
        $postedDepartmentId > 0
    ) {

        $department_id =
            (int)
            $postedDepartmentId;

    }

}


/*----------------------------------------------------
    SESSION DEPARTMENT
----------------------------------------------------*/

if (
    $department_id <= 0
    &&
    isset(
        $booking['department_id']
    )
) {

    $department_id =
        (int)
        $booking['department_id'];

}


/*====================================================
    VALIDATE DEPARTMENT ID
====================================================*/

if (
    $department_id <= 0
) {

    $_SESSION['error'] =
        "Please select a department first.";

    header(
        "Location: select_department.php"
    );

    exit;
}


/*====================================================
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn) {

    $_SESSION['error'] =
        "Unable to connect to the selected hospital database.";

    header(
        "Location: select_department.php"
    );

    exit;
}


mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*====================================================
    VERIFY DEPARTMENT
====================================================*/

$query = "

    SELECT

        department_id,
        department_name,
        description,
        location,
        head_doctor_id,
        status

    FROM departments

    WHERE department_id = ?

      AND status = 'Active'

    LIMIT 1

";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt) {

    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $database_error,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $department_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
) {

    $database_error =
        mysqli_stmt_error(
            $stmt
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $database_error,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$department =
    null;


if ($result) {

    $department =
        mysqli_fetch_assoc(
            $result
        );

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    DEPARTMENT NOT FOUND
====================================================*/

if (!$department) {

    mysqli_close(
        $hospital_conn
    );

    unset(
        $_SESSION['appointment_booking']['department_id'],
        $_SESSION['appointment_booking']['department_name']
    );

    $_SESSION['error'] =
        "Selected department is not available.";

    header(
        "Location: select_department.php"
    );

    exit;
}


/*====================================================
    SAVE DEPARTMENT IN SESSION
====================================================*/

$_SESSION['appointment_booking'][
    'department_id'
] =
    (int)
    $department['department_id'];


$_SESSION['appointment_booking'][
    'department_name'
] =
    trim(
        (string)
        $department['department_name']
    );


/*====================================================
    GET ACTIVE DOCTORS
====================================================*/

$query = "

    SELECT

        doctor_id,
        department_id,
        doctor_name,
        gender,
        date_of_birth,
        email,
        phone,
        qualification,
        specialization,
        medical_license_no,
        experience_years,
        consultation_fee,
        profile_photo,
        status

    FROM doctors

    WHERE department_id = ?

      AND status = 'Active'

    ORDER BY doctor_name ASC

";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt) {

    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $database_error,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $department_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
) {

    $database_error =
        mysqli_stmt_error(
            $stmt
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $database_error,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$doctors = [];


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    ) {

        $doctors[] = [

            'doctor_id' =>
                (int) (
                    $row['doctor_id']
                    ?? 0
                ),

            'doctor_name' =>
                trim(
                    (string) (
                        $row['doctor_name']
                        ?? ''
                    )
                ),

            'qualification' =>
                trim(
                    (string) (
                        $row['qualification']
                        ?? ''
                    )
                ),

            'specialization' =>
                trim(
                    (string) (
                        $row['specialization']
                        ?? ''
                    )
                ),

            'experience_years' =>
                (int) (
                    $row['experience_years']
                    ?? 0
                ),

            'consultation_fee' =>
                (float) (
                    $row['consultation_fee']
                    ?? 0
                ),

            'profile_photo' =>
                trim(
                    (string) (
                        $row['profile_photo']
                        ?? ''
                    )
                ),

            'status' =>
                trim(
                    (string) (
                        $row['status']
                        ?? ''
                    )
                )

        ];

    }

    mysqli_free_result(
        $result
    );

}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    CLOSE DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    FLASH ERROR
====================================================*/

$error =
    (string) (
        $_SESSION['error']
        ?? ''
    );


unset(
    $_SESSION['error']
);


/*====================================================
    ESCAPE FUNCTION
====================================================*/

function doctorEscape(
    string $value
): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*====================================================
    DOCTOR PHOTO PATH
====================================================*/

function doctorPhotoPath(
    string $photo
): string
{

    if (
        $photo === ''
    ) {

        return '';

    }


    /*
        Full URL
    */

    if (
        filter_var(
            $photo,
            FILTER_VALIDATE_URL
        )
    ) {

        return $photo;

    }


    /*
        Relative project path
    */

    return '../../' .
        ltrim(
            $photo,
            '/\\'
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
        Select Doctor | Smart Hospital
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5f8fc;

            color: #1f2937;
        }


        /*================================================
            HEADER
        =================================================*/

        .mobile-header {

            width: 100%;

            background: #ffffff;

            border-bottom:
                1px solid #e5e7eb;

            padding:
                15px 18px;

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .mobile-header-inner {

            max-width: 700px;

            margin: auto;

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .back-button {

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            border-radius: 10px;

            background: #f1f5f9;

            color: #172033;

            font-size: 22px;

            font-weight: bold;
        }


        .header-title {

            flex: 1;
        }


        .header-title h1 {

            margin: 0;

            font-size: 19px;

            color: #111827;
        }


        .header-title p {

            margin: 3px 0 0;

            font-size: 12px;

            color: #6b7280;
        }


        /*================================================
            PAGE
        =================================================*/

        .mobile-page {

            width: 100%;

            max-width: 700px;

            margin: auto;

            padding:
                20px 16px 40px;
        }


        /*================================================
            INTRO
        =================================================*/

        .page-intro {

            margin-bottom: 18px;
        }


        .small-title {

            margin:
                0 0 5px;

            color: #6355f4;

            font-size: 12px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .7px;
        }


        .page-intro h2 {

            margin: 0;

            font-size: 26px;

            color: #111827;
        }


        .page-intro p:last-child {

            margin:
                7px 0 0;

            color: #6b7280;

            font-size: 14px;

            line-height: 1.5;
        }


        /*================================================
            ERROR
        =================================================*/

        .error-box {

            padding:
                13px 15px;

            margin-bottom:
                18px;

            border-radius:
                12px;

            background:
                #fef2f2;

            border:
                1px solid #fecaca;

            color:
                #b91c1c;

            font-size:
                14px;

            line-height:
                1.5;
        }


        /*================================================
            SUMMARY
        =================================================*/

        .summary-card {

            background:
                #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius:
                16px;

            padding:
                16px;

            margin-bottom:
                20px;

            box-shadow:
                0 4px 14px
                rgba(15,23,42,.04);
        }


        .summary-row {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

            padding:
                11px 0;

            border-bottom:
                1px solid #eef2f7;
        }


        .summary-row:first-child {

            padding-top:
                0;
        }


        .summary-row:last-child {

            border-bottom:
                none;

            padding-bottom:
                0;
        }


        .summary-label {

            color:
                #6b7280;

            font-size:
                13px;
        }


        .summary-value {

            color:
                #111827;

            font-size:
                14px;

            font-weight:
                700;

            text-align:
                right;
        }


        /*================================================
            SECTION HEADER
        =================================================*/

        .section-header {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                10px;

            margin-bottom:
                13px;
        }


        .section-header h2 {

            margin:
                0;

            font-size:
                19px;

            color:
                #111827;
        }


        .count-badge {

            padding:
                6px 10px;

            border-radius:
                20px;

            background:
                #eeedff;

            color:
                #6355f4;

            font-size:
                12px;

            font-weight:
                700;

            white-space:
                nowrap;
        }


        /*================================================
            DOCTOR LIST
        =================================================*/

        .doctor-list {

            display:
                flex;

            flex-direction:
                column;

            gap:
                13px;
        }


        /*================================================
            DOCTOR CARD
        =================================================*/

        .doctor-card {

            background:
                #ffffff;

            border:
                1px solid #e5e7eb;

            border-radius:
                16px;

            padding:
                16px;

            box-shadow:
                0 4px 14px
                rgba(15,23,42,.035);

            transition:
                .2s ease;
        }


        .doctor-card:hover {

            border-color:
                #bfdbfe;

            transform:
                translateY(-1px);

            box-shadow:
                0 8px 22px
                rgba(15,23,42,.07);
        }


        .doctor-top {

            display:
                flex;

            gap:
                13px;

            align-items:
                flex-start;
        }


        /*================================================
            PHOTO
        =================================================*/

        .doctor-photo {

            width:
                72px;

            height:
                72px;

            flex-shrink:
                0;

            border-radius:
                14px;

            overflow:
                hidden;

            background:
                #eeedff;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            color:
                #6355f4;

            font-size:
                28px;

            font-weight:
                bold;

            border:
                1px solid #dbeafe;
        }


        .doctor-photo img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;
        }


        .doctor-main {

            flex:
                1;

            min-width:
                0;
        }


        .doctor-main h3 {

            margin:
                0;

            font-size:
                18px;

            color:
                #111827;

            line-height:
                1.3;
        }


        .doctor-specialization {

            margin:
                5px 0 0;

            color:
                #6355f4;

            font-size:
                13px;

            font-weight:
                600;
        }


        .active-status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            margin-top:
                6px;

            color:
                #15803d;

            font-size:
                12px;

            font-weight:
                600;
        }


        .active-dot {

            width:
                7px;

            height:
                7px;

            border-radius:
                50%;

            background:
                #22c55e;
        }


        /*================================================
            DETAILS
        =================================================*/

        .doctor-details {

            margin-top:
                15px;

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(
                        0,
                        1fr
                    )
                );

            gap:
                9px;
        }


        .detail-box {

            padding:
                10px 11px;

            background:
                #f8fafc;

            border-radius:
                10px;

            border:
                1px solid #eef2f7;
        }


        .detail-box span {

            display:
                block;

            color:
                #6b7280;

            font-size:
                11px;

            margin-bottom:
                3px;
        }


        .detail-box strong {

            display:
                block;

            color:
                #111827;

            font-size:
                13px;
        }


        /*================================================
            FEE
        =================================================*/

        .fee-box {

            margin-top:
                11px;

            padding:
                11px 12px;

            border-radius:
                10px;

            background:
                #f0fdf4;

            border:
                1px solid #dcfce7;

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            gap:
                10px;
        }


        .fee-box span {

            color:
                #166534;

            font-size:
                13px;

            font-weight:
                600;
        }


        .fee-box strong {

            color:
                #15803d;

            font-size:
                16px;
        }


        /*================================================
            BUTTON
        =================================================*/

        .doctor-form {

            margin-top:
                14px;
        }


        .select-button {

            width:
                100%;

            height:
                45px;

            border:
                none;

            border-radius:
                10px;

            background:
                #6355f4;

            color:
                #ffffff;

            font-size:
                14px;

            font-weight:
                700;

            cursor:
                pointer;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            gap:
                8px;

            transition:
                .2s ease;
        }


        .select-button:hover {

            background:
                #5143e8;
        }


        .select-button:active {

            transform:
                scale(.98);
        }


        .select-arrow {

            font-size:
                17px;
        }


        /*================================================
            EMPTY
        =================================================*/

        .empty-box {

            background:
                #ffffff;

            border:
                1px solid #fecaca;

            border-radius:
                14px;

            padding:
                22px 18px;

            text-align:
                center;

            color:
                #b91c1c;

            font-size:
                14px;

            line-height:
                1.5;
        }


        /*================================================
            MOBILE
        =================================================*/

        @media (
            max-width: 480px
        ) {

            .mobile-page {

                padding:
                    17px 13px 35px;
            }


            .mobile-header {

                padding:
                    13px;
            }


            .header-title h1 {

                font-size:
                    17px;
            }


            .page-intro h2 {

                font-size:
                    23px;
            }


            .doctor-card {

                padding:
                    14px;
            }


            .doctor-photo {

                width:
                    62px;

                height:
                    62px;
            }


            .doctor-main h3 {

                font-size:
                    16px;
            }


            .doctor-details {

                grid-template-columns:
                    1fr;
            }


            .summary-value {

                max-width:
                    60%;

                word-break:
                    break-word;
            }

        }

    </style>

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<!--====================================================
    HEADER
=====================================================-->

<header class="mobile-header">

    <div class="mobile-header-inner">


        <a
            href="select_department.php"
            class="back-button"
            aria-label="Back"
        >

            ←

        </a>


        <div class="header-title">

            <h1>
                Book Appointment
            </h1>

            <p>
                Step 4 of appointment booking
            </p>

        </div>


    </div>

</header>



<!--====================================================
    PAGE
=====================================================-->

<main class="mobile-page">


    <!--================================================
        INTRO
    =================================================-->

    <div class="page-intro">


        <p class="small-title">
            Patient Portal
        </p>


        <h2>
            Select Doctor
        </h2>


        <p>
            Choose a doctor from the selected
            department for your consultation.
        </p>


    </div>



    <!--================================================
        ERROR
    =================================================-->

    <?php if (
        $error !== ''
    ): ?>

        <div class="error-box">

            <?= doctorEscape(
                $error
            ); ?>

        </div>

    <?php endif; ?>



    <!--================================================
        APPOINTMENT SUMMARY
    =================================================-->

    <div class="summary-card">


        <div class="summary-row">

            <span class="summary-label">
                Hospital
            </span>


            <strong class="summary-value">

                <?= doctorEscape(
                    $hospital_name
                ); ?>

            </strong>

        </div>



        <?php if (
            $hospital_patient_code !== ''
        ): ?>

            <div class="summary-row">

                <span class="summary-label">
                    Patient Code
                </span>


                <strong class="summary-value">

                    <?= doctorEscape(
                        $hospital_patient_code
                    ); ?>

                </strong>

            </div>

        <?php endif; ?>



        <div class="summary-row">

            <span class="summary-label">
                Department
            </span>


            <strong class="summary-value">

                <?= doctorEscape(
                    (string)
                    $department[
                        'department_name'
                    ]
                ); ?>

            </strong>

        </div>



        <?php if (
            !empty(
                $department[
                    'location'
                ]
            )
        ): ?>

            <div class="summary-row">

                <span class="summary-label">
                    Location
                </span>


                <strong class="summary-value">

                    <?= doctorEscape(
                        (string)
                        $department[
                            'location'
                        ]
                    ); ?>

                </strong>

            </div>

        <?php endif; ?>


    </div>



    <!--================================================
        DOCTOR SECTION HEADER
    =================================================-->

    <div class="section-header">


        <h2>
            Available Doctors
        </h2>


        <span class="count-badge">

            <?= count(
                $doctors
            ); ?>

            Available

        </span>


    </div>



    <!--================================================
        DOCTORS
    =================================================-->

    <?php if (
        count($doctors) === 0
    ): ?>


        <div class="empty-box">

            No active doctors are currently
            available in this department.

        </div>


    <?php else: ?>


        <div class="doctor-list">


            <?php foreach (
                $doctors
                as $doctor
            ): ?>


                <div class="doctor-card">


                    <!--================================
                        DOCTOR TOP
                    =================================-->

                    <div class="doctor-top">


                        <!-- PHOTO -->

                        <div class="doctor-photo">


                            <?php

                            $photo =
                                doctorPhotoPath(
                                    $doctor[
                                        'profile_photo'
                                    ]
                                );

                            ?>


                            <?php if (
                                $photo !== ''
                            ): ?>


                                <img
                                    src="<?= doctorEscape(
                                        $photo
                                    ); ?>"
                                    alt="Doctor"
                                    loading="lazy"
                                    onerror="
                                        this.style.display='none';
                                        this.parentElement.innerHTML='Dr';
                                    "
                                >


                            <?php else: ?>


                                Dr


                            <?php endif; ?>


                        </div>



                        <!-- DOCTOR INFORMATION -->

                        <div class="doctor-main">


                            <h3>

                                <?= doctorEscape(
                                    $doctor[
                                        'doctor_name'
                                    ]
                                ); ?>

                            </h3>



                            <?php if (
                                $doctor[
                                    'specialization'
                                ] !== ''
                            ): ?>


                                <p
                                    class="doctor-specialization"
                                >

                                    <?= doctorEscape(
                                        $doctor[
                                            'specialization'
                                        ]
                                    ); ?>

                                </p>


                            <?php endif; ?>



                            <div
                                class="active-status"
                            >

                                <span
                                    class="active-dot"
                                ></span>

                                Available

                            </div>


                        </div>


                    </div>



                    <!--================================
                        DOCTOR DETAILS
                    =================================-->

                    <div class="doctor-details">


                        <?php if (
                            $doctor[
                                'qualification'
                            ] !== ''
                        ): ?>


                            <div
                                class="detail-box"
                            >

                                <span>
                                    Qualification
                                </span>


                                <strong>

                                    <?= doctorEscape(
                                        $doctor[
                                            'qualification'
                                        ]
                                    ); ?>

                                </strong>

                            </div>


                        <?php endif; ?>



                        <div
                            class="detail-box"
                        >

                            <span>
                                Experience
                            </span>


                            <strong>

                                <?= (int)
                                    $doctor[
                                        'experience_years'
                                    ]; ?>

                                Years

                            </strong>

                        </div>


                    </div>



                    <!--================================
                        CONSULTATION FEE
                    =================================-->

                    <div class="fee-box">


                        <span>
                            Consultation Fee
                        </span>


                        <strong>

                            ₹<?= number_format(
                                $doctor[
                                    'consultation_fee'
                                ],
                                2
                            ); ?>

                        </strong>


                    </div>



                    <!--================================
                        SELECT DOCTOR
                    =================================-->

                    <form
                        method="POST"
                        action="select_date.php"
                        class="doctor-form"
                    >


                        <input
                            type="hidden"
                            name="doctor_id"
                            value="<?= (int)
                                $doctor[
                                    'doctor_id'
                                ]; ?>"
                        >


                        <button
                            type="submit"
                            class="select-button"
                        >

                            Select Doctor

                            <span
                                class="select-arrow"
                            >
                                →
                            </span>

                        </button>


                    </form>


                </div>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</main>


</body>

</html>