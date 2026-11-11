<?php



declare(strict_types=1);



session_start();





/*

\|--------------------------------------------------------------------------

\| ERROR REPORTING

\|--------------------------------------------------------------------------

\*/



error_reporting(E_ALL);

ini_set('display_errors', '1');

ini_set('display_startup_errors', '1');





/*

\|--------------------------------------------------------------------------

\| LOGIN CHECK

\|--------------------------------------------------------------------------

\*/



if (

    !isset($_SESSION['patient_auth']) ||

    !is_array($_SESSION['patient_auth']) ||

    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true

) {

    header('Location: login.php');

    exit;

}





/*

\|--------------------------------------------------------------------------

\| PATIENT DATA

\|--------------------------------------------------------------------------

\*/



$patient = $_SESSION['patient_auth'];





$first_name = trim(

    (string)($patient['first_name'] ?? 'Patient')

);





$last_name = trim(

    (string)($patient['last_name'] ?? '')

);





$full_name = trim(

    $first_name . ' ' . $last_name

);





if ($full_name === '') {



    $full_name = 'Patient';



}





$initial = strtoupper(

    substr(

        $first_name !== ''

            ? $first_name

            : 'P',

        0,

        1

    )

);





/*

\|--------------------------------------------------------------------------

\| ACCOUNT ID

\|--------------------------------------------------------------------------

\*/



$account_id = (int)(

    $patient['account_id']

    ?? 0

);





/*

\|--------------------------------------------------------------------------

\| ESCAPE FUNCTION

\|--------------------------------------------------------------------------

\*/



function dashboardEscape(string $value): string

{

    return htmlspecialchars(

        $value,

        ENT_QUOTES,

        'UTF-8'

    );

}





/*

\|--------------------------------------------------------------------------

\| UPCOMING APPOINTMENT

\|--------------------------------------------------------------------------

|

\| Flow:

|

\| patient account

\|       ↓

\| patient_hospital_mapping

\|       ↓

\| hospital_registration

\|       ↓

\| hospital database

\|       ↓

\| appointments

|

\|--------------------------------------------------------------------------

\*/





$upcoming_appointment = null;





$mapping_rows = [];





/*

\|--------------------------------------------------------------------------

\| CHECK ACCOUNT ID

\|--------------------------------------------------------------------------

\*/



if ($account_id > 0) {





    /*

    |--------------------------------------------------------------------------

    | CONNECT CENTRAL DATABASE

    |--------------------------------------------------------------------------

    \*/



    $central_conn = mysqli_connect(

        "localhost",

        "Hospital_management",

        "B@ldh@ V@rshil",

        "hospital_management"

    );





    if ($central_conn) {





        mysqli_set_charset(

            $central_conn,

            "utf8mb4"

        );





        /*

        |--------------------------------------------------------------------------

        | GET PATIENT HOSPITAL MAPPINGS

        |--------------------------------------------------------------------------

        \*/



        $mapping_sql = "



            SELECT



                phm.mapping_id,

                phm.account_id,

                phm.hospital_id,

                phm.hospital_patient_code,



                hr.hospital_name,

                hr.city,

                hr.state,

                hr.database_name



            FROM patient_hospital_mapping AS phm



            INNER JOIN hospital_registration AS hr



                ON hr.hospital_id =

                   phm.hospital_id



            WHERE phm.account_id = ?



              AND phm.patient_status = 'Active'



              AND hr.database_name IS NOT NULL



              AND hr.database_name <> ''



            ORDER BY phm.mapping_id ASC



        ";





        $mapping_stmt = mysqli_prepare(

            $central_conn,

            $mapping_sql

        );





        if ($mapping_stmt) {





            mysqli_stmt_bind_param(

                $mapping_stmt,

                "i",

                $account_id

            );





            if (

                mysqli_stmt_execute(

                    $mapping_stmt

                )

            ) {





                mysqli_stmt_bind_result(



                    $mapping_stmt,



                    $mapping_id,

                    $mapping_account_id,

                    $mapping_hospital_id,

                    $mapping_patient_code,

                    $mapping_hospital_name,

                    $mapping_city,

                    $mapping_state,

                    $mapping_database_name



                );





                while (

                    mysqli_stmt_fetch(

                        $mapping_stmt

                    )

                ) {





                    $mapping_rows[] = [



                        'mapping_id' =>

                            (int)$mapping_id,



                        'account_id' =>

                            (int)$mapping_account_id,



                        'hospital_id' =>

                            (int)$mapping_hospital_id,



                        'hospital_patient_code' =>

                            (string)$mapping_patient_code,



                        'hospital_name' =>

                            (string)$mapping_hospital_name,



                        'city' =>

                            (string)$mapping_city,



                        'state' =>

                            (string)$mapping_state,



                        'database_name' =>

                            (string)$mapping_database_name



                    ];



                }



            }





            mysqli_stmt_close(

                $mapping_stmt

            );



        }





        mysqli_close(

            $central_conn

        );



    }





    /*

    |--------------------------------------------------------------------------

    | SEARCH EACH HOSPITAL DATABASE

    |--------------------------------------------------------------------------

    \*/



    foreach (

        $mapping_rows as $mapping

    ) {





        /*

        |--------------------------------------------------------------------------

        | STOP IF APPOINTMENT ALREADY FOUND

        |--------------------------------------------------------------------------

        \*/



        if (

            $upcoming_appointment !== null

        ) {



            break;



        }





        $hospital_database =

            trim(

                (string)(

                    $mapping['database_name']

                    ?? ''

                )

            );





        /*

        |--------------------------------------------------------------------------

        | VALIDATE DATABASE NAME

        |--------------------------------------------------------------------------

        \*/



        if (

            $hospital_database === ''

            ||

            !preg_match(

                '/^[A-Za-z0-9\_]+$/',

                $hospital_database

            )

        ) {



            continue;



        }





        /*

        |--------------------------------------------------------------------------

        | CONNECT HOSPITAL DATABASE

        |--------------------------------------------------------------------------

        \*/



        $hospital_conn = mysqli_connect(



            "localhost",



            "Hospital_management",



            "B@ldh@ V@rshil",



            $hospital_database



        );





        if (!$hospital_conn) {



            continue;



        }





        mysqli_set_charset(

            $hospital_conn,

            "utf8mb4"

        );





        /*

        |--------------------------------------------------------------------------

        | GET NEXT UPCOMING APPOINTMENT

        |--------------------------------------------------------------------------

        \*/



        $appointment_sql = "



            SELECT



                a.appointment_id,

                a.mapping_id,

                a.doctor_id,

                a.service_id,



                a.appointment_no,

                a.appointment_date,

                a.appointment_time,



                a.appointment_type,

                a.consultation_mode,

                a.token_number,

                a.appointment_status,



                d.doctor_name,

                d.specialization,



                dep.department_name,



                s.service_name



            FROM appointments AS a



            LEFT JOIN doctors AS d



                ON d.doctor_id =

                   a.doctor_id



            LEFT JOIN departments AS dep



                ON dep.department_id =

                   d.department_id



            LEFT JOIN services AS s



                ON s.service_id =

                   a.service_id



            WHERE a.mapping_id = ?



              AND a.appointment_date >= CURDATE()



              AND a.appointment_status = 'Scheduled'



            ORDER BY



                a.appointment_date ASC,



                a.appointment_time ASC,



                a.appointment_id ASC



            LIMIT 1



        ";





        $appointment_stmt =

            mysqli_prepare(

                $hospital_conn,

                $appointment_sql

            );





        if ($appointment_stmt) {





            $current_mapping_id =

                (int)$mapping['mapping_id'];





            mysqli_stmt_bind_param(



                $appointment_stmt,



                "i",



                $current_mapping_id



            );





            if (

                mysqli_stmt_execute(

                    $appointment_stmt

                )

            ) {





                mysqli_stmt_bind_result(



                    $appointment_stmt,



                    $appointment_id,

                    $appointment_mapping_id,

                    $appointment_doctor_id,

                    $appointment_service_id,



                    $appointment_no,

                    $appointment_date,

                    $appointment_time,



                    $appointment_type,

                    $appointment_consultation_mode,

                    $appointment_token_number,

                    $appointment_status,



                    $appointment_doctor_name,

                    $appointment_specialization,



                    $appointment_department_name,



                    $appointment_service_name



                );





                if (

                    mysqli_stmt_fetch(

                        $appointment_stmt

                    )

                ) {





                    $upcoming_appointment = [



                        'appointment_id' =>

                            (int)$appointment_id,



                        'mapping_id' =>

                            (int)$appointment_mapping_id,



                        'doctor_id' =>

                            (int)$appointment_doctor_id,



                        'service_id' =>

                            (int)$appointment_service_id,



                        'appointment_no' =>

                            (string)$appointment_no,



                        'appointment_date' =>

                            (string)$appointment_date,



                        'appointment_time' =>

                            (string)$appointment_time,



                        'appointment_type' =>

                            (string)$appointment_type,



                        'consultation_mode' =>

                            (string)$appointment_consultation_mode,



                        'token_number' =>

                            $appointment_token_number,



                        'appointment_status' =>

                            (string)$appointment_status,



                        'doctor_name' =>

                            (string)$appointment_doctor_name,



                        'specialization' =>

                            (string)$appointment_specialization,



                        'department_name' =>

                            (string)$appointment_department_name,



                        'service_name' =>

                            (string)$appointment_service_name,



                        'hospital_name' =>

                            (string)(

                                $mapping['hospital_name']

                                ?? ''

                            ),



                        'hospital_patient_code' =>

                            (string)(

                                $mapping['hospital_patient_code']

                                ?? ''

                            ),



                        'city' =>

                            (string)(

                                $mapping['city']

                                ?? ''

                            ),



                        'state' =>

                            (string)(

                                $mapping['state']

                                ?? ''

                            ),



                        'hospital_id' =>

                            (int)(

                                $mapping['hospital_id']

                                ?? 0

                            ),



                        'database_name' =>

                            $hospital_database



                    ];



                }



            }





            mysqli_stmt_close(

                $appointment_stmt

            );



        }





        mysqli_close(

            $hospital_conn

        );



    }



}











if (

    $upcoming_appointment !== null

) {





    $formatted_appointment_date =

        date(

            'd M Y',

            strtotime(

                $upcoming_appointment[

                    'appointment_date'

                ]

            )

        );





    $formatted_appointment_time =

        date(

            'h:i A',

            strtotime(

                $upcoming_appointment[

                    'appointment_time'

                ]

            )

        );



}

else {



    $formatted_appointment_date = '';

    $formatted_appointment_time = '';



}



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

        content="#4f46e5"

    >



    <title>Care Your Health</title>



    <link

        rel="stylesheet"

        href="css/dashboard.css"

    >



</head>





<body>



<div class="mobile-app">





    <!-- =====================================================

         HEADER

    ====================================================== -->



    <header class="app-header">



        <div class="header-content">



            <div class="brand-area">



                <div class="brand-mark">

                    <span>+</span>

                </div>



                <div class="brand-text">

                    <strong>Care Your Health</strong>

                    <span>Smart healthcare, made simple</span>

                </div>



            </div>





            <button

                type="button"

                class="notification-button"

                onclick="openNotifications()"

                aria-label="Notifications"

            >



                <svg viewBox="0 0 24 24" aria-hidden="true">

                    <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>

                    <path d="M10 21h4"/>

                </svg>



                <span class="notification-dot"></span>



            </button>



        </div>





        <div class="welcome-area">



            <span class="welcome-small">

                Good morning

            </span>



            <h1>

                <?= dashboardEscape($full_name); ?>

            </h1>



            <p>

                How can we help you today?

            </p>



        </div>



    </header>







    <!-- =====================================================

         MAIN

    ====================================================== -->



    <main class="dashboard-content">





        <!-- =================================================

             HERO

        ================================================== -->



        <section class="welcome-card">



            <div class="welcome-card-content">



                <span class="hero-label">

                    YOUR HEALTH MATTERS

                </span>



                <h2>

                    Better care.

                    <br>

                    Better health.

                </h2>



                <p>

                    Find doctors, manage appointments

                    and access smarter healthcare tools.

                </p>





                <div class="hero-actions">



                    <button

                        type="button"

                        class="hero-primary"

                        onclick="openBooking()"

                    >



                        <span>Book Appointment</span>



                        <svg viewBox="0 0 24 24">

                            <path d="M5 12h14"/>

                            <path d="m13 6 6 6-6 6"/>

                        </svg>



                    </button>





                    <button

                        type="button"

                        class="hero-secondary"

                        onclick="window.location.href='hospital_search.php'"

                    >



                        <svg viewBox="0 0 24 24">

                            <path d="M3 21h18"/>

                            <path d="M5 21V5l7-3 7 3v16"/>

                            <path d="M9 9h1"/>

                            <path d="M14 9h1"/>

                            <path d="M9 13h1"/>

                            <path d="M14 13h1"/>

                        </svg>



                        <span>Hospitals</span>



                    </button>



                </div>



            </div>





            <div class="hero-decoration">



                <div class="hero-circle circle-one"></div>

                <div class="hero-circle circle-two"></div>



                <div class="hero-heart">

                    <svg viewBox="0 0 24 24">

                        <path d="M20.8 8.7c0 5.5-8.8 10.2-8.8 10.2S3.2 14.2 3.2 8.7A4.7 4.7 0 0 1 12 6.3a4.7 4.7 0 0 1 8.8 2.4Z"/>

                    </svg>

                </div>



            </div>



        </section>







        <!-- =================================================

             QUICK ACTIONS

        ================================================== -->



        <section class="dashboard-section">



            <div class="section-heading">



                <div>

                    <span class="section-label">

                        QUICK ACCESS

                    </span>



                    <h2>What do you need?</h2>

                </div>



            </div>





            <div class="quick-actions">





                <button

                    type="button"

                    class="quick-card"

                    onclick="openBooking()"

                >



                    <div class="quick-icon blue">



                        <svg viewBox="0 0 24 24">

                            <rect x="4" y="5" width="16" height="16" rx="3"/>

                            <path d="M8 3v4"/>

                            <path d="M16 3v4"/>

                            <path d="M4 10h16"/>

                            <path d="M9 14h1"/>

                            <path d="M14 14h1"/>

                        </svg>



                    </div>



                    <strong>Book Appointment</strong>



                    <span>Find a doctor</span>



                    <b>→</b>



                </button>







                <button

                    type="button"

                    class="quick-card"

                    onclick="window.location.href='hospital_search.php'"

                >



                    <div class="quick-icon green">



                        <svg viewBox="0 0 24 24">

                            <path d="M4 21V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v15"/>

                            <path d="M2 21h20"/>

                            <path d="M9 8h6"/>

                            <path d="M12 5v6"/>

                            <path d="M8 14h1"/>

                            <path d="M15 14h1"/>

                            <path d="M8 18h1"/>

                            <path d="M15 18h1"/>

                        </svg>



                    </div>



                    <strong>Find Hospitals</strong>



                    <span>Nearby healthcare</span>



                    <b>→</b>



                </button>







                <button

                    type="button"

                    class="quick-card"

                    onclick="openAppointments()"

                >



                    <div class="quick-icon orange">



                        <svg viewBox="0 0 24 24">

                            <rect x="5" y="3" width="14" height="18" rx="2"/>

                            <path d="M8 7h8"/>

                            <path d="M8 11h8"/>

                            <path d="M8 15h5"/>

                        </svg>



                    </div>



                    <strong>My Appointments</strong>



                    <span>View your visits</span>



                    <b>→</b>



                </button>







                <button

                    type="button"

                    class="quick-card"

                    onclick="openProfile()"

                >



                    <div class="quick-icon purple">



                        <svg viewBox="0 0 24 24">

                            <circle cx="12" cy="8" r="4"/>

                            <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>

                        </svg>



                    </div>



                    <strong>My Profile</strong>



                    <span>Manage your account</span>



                    <b>→</b>



                </button>





            </div>



        </section>







        <!-- =================================================

             AI ASSISTANT

        ================================================== -->



        <section class="ai-card">



            <div class="ai-card-top">



                <div class="ai-icon">



                    <svg viewBox="0 0 24 24">

                        <path d="M12 3v3"/>

                        <path d="M12 18v3"/>

                        <path d="M3 12h3"/>

                        <path d="M18 12h3"/>

                        <path d="m5.6 5.6 2.1 2.1"/>

                        <path d="m16.3 16.3 2.1 2.1"/>

                        <path d="m18.4 5.6-2.1 2.1"/>

                        <path d="m7.7 16.3-2.1 2.1"/>

                        <circle cx="12" cy="12" r="4"/>

                    </svg>



                </div>



                <span class="ai-badge">

                    AI ASSISTANT

                </span>



            </div>





            <div class="ai-card-body">



                <h2>

                    Your personal

                    <br>

                    health companion.

                </h2>



                <p>

                    Get quick answers about healthcare,

                    appointments and hospitals.

                </p>



                <button

                    type="button"

                    onclick="openAIChat()"

                >



                    <span>Start a conversation</span>



                    <svg viewBox="0 0 24 24">

                        <path d="M5 12h14"/>

                        <path d="m13 6 6 6-6 6"/>

                    </svg>



                </button>



            </div>



        </section>







        <!-- =================================================

             MEDICAL VISION

        ================================================== -->



        <section class="vision-card">



            <div class="vision-header">



                <div class="vision-icon">



                    <svg viewBox="0 0 24 24">

                        <rect x="4" y="4" width="16" height="16" rx="3"/>

                        <path d="M8 8h8"/>

                        <path d="M8 12h8"/>

                        <path d="M8 16h5"/>

                    </svg>



                </div>





                <div>



                    <span class="vision-label">

                        SMART MEDICAL VISION

                    </span>



                    <span class="vision-status">

                        READY

                    </span>



                </div>



            </div>





            <h2>

                Understand your

                medical images.

            </h2>





            <p>

                Upload supported medical images and

                get an easy-to-understand AI analysis.

            </p>





            <div class="vision-tags">



                <span>X-RAY</span>

                <span>CT</span>

                <span>MRI</span>

                <span>ULTRASOUND</span>



            </div>





            <button

                type="button"

                class="vision-button"

                onclick="openVisionAnalysis()"

            >



                <span>Analyze Medical Image</span>



                <svg viewBox="0 0 24 24">

                    <path d="M5 12h14"/>

                    <path d="m13 6 6 6-6 6"/>

                </svg>



            </button>





            <small>

                Secure AI-assisted image processing

            </small>



        </section>







        <!-- =================================================

             UPCOMING APPOINTMENT

        ================================================== -->



        <section class="dashboard-section appointment-section">



            <div class="section-heading">



                <div>



                    <span class="section-label">

                        YOUR CARE

                    </span>



                    <h2>Upcoming appointment</h2>



                </div>





                <button

                    type="button"

                    class="view-all"

                    onclick="openAppointments()"

                >

                    View all

                </button>



            </div>





            <?php if ($upcoming_appointment !== null): ?>



                <div class="appointment-card">





                    <div class="appointment-date-box">



                        <span>

                            <?= dashboardEscape(

                                date(

                                    'M',

                                    strtotime(

                                        $upcoming_appointment['appointment_date']

                                    )

                                )

                            ); ?>

                        </span>



                        <strong>

                            <?= dashboardEscape(

                                date(

                                    'd',

                                    strtotime(

                                        $upcoming_appointment['appointment_date']

                                    )

                                )

                            ); ?>

                        </strong>



                    </div>





                    <div class="appointment-main">



                        <div class="appointment-status">

                            Scheduled

                        </div>





                        <h3>

                            <?= dashboardEscape(

                                $upcoming_appointment['doctor_name']

                            ); ?>

                        </h3>





                        <?php if (

                            $upcoming_appointment['specialization'] !== ''

                        ): ?>



                            <p>

                                <?= dashboardEscape(

                                    $upcoming_appointment['specialization']

                                ); ?>

                            </p>



                        <?php endif; ?>





                        <div class="appointment-meta">



                            <span>



                                <svg viewBox="0 0 24 24">

                                    <circle cx="12" cy="12" r="8"/>

                                    <path d="M12 8v4l3 2"/>

                                </svg>



                                <?= dashboardEscape(

                                    $formatted_appointment_time

                                ); ?>



                            </span>





                            <?php if (

                                $upcoming_appointment['hospital_name'] !== ''

                            ): ?>



                                <span>



                                    <svg viewBox="0 0 24 24">

                                        <path d="M4 21V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v15"/>

                                        <path d="M2 21h20"/>

                                        <path d="M9 8h6"/>

                                        <path d="M12 5v6"/>

                                    </svg>



                                    <?= dashboardEscape(

                                        $upcoming_appointment['hospital_name']

                                    ); ?>



                                </span>



                            <?php endif; ?>



                        </div>





                        <button

                            type="button"

                            class="appointment-view-button"

                            onclick="openAppointments()"

                        >

                            View appointment

                        </button>



                    </div>



                </div>





            <?php else: ?>



                <div class="appointment-card empty">



                    <div class="empty-icon">



                        <svg viewBox="0 0 24 24">

                            <rect x="4" y="5" width="16" height="15" rx="3"/>

                            <path d="M8 3v4"/>

                            <path d="M16 3v4"/>

                            <path d="M4 10h16"/>

                        </svg>



                    </div>





                    <div>



                        <span class="empty-label">

                            YOUR CARE

                        </span>



                        <h3>

                            No upcoming appointment

                        </h3>



                        <p>

                            Book your next appointment with a doctor.

                        </p>





                        <button

                            type="button"

                            onclick="openBooking()"

                        >

                            Book appointment →

                        </button>



                    </div>



                </div>



            <?php endif; ?>



        </section>







        <!-- =================================================

             SERVICES

        ================================================== -->



        <section class="dashboard-section services-section">



            <div class="section-heading">



                <div>



                    <span class="section-label">

                        ACCOUNT

                    </span>



                    <h2>More from Care Your Health</h2>



                </div>



            </div>





            <div class="services-grid">





                <button

                    type="button"

                    onclick="openProfile()"

                >



                    <div class="service-icon purple">



                        <svg viewBox="0 0 24 24">

                            <circle cx="12" cy="8" r="4"/>

                            <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>

                        </svg>



                    </div>



                    <span>

                        Profile

                    </span>



                    <small>

                        Account

                    </small>



                </button>







                <button

                    type="button"

                    onclick="openNotifications()"

                >



                    <div class="service-icon blue">



                        <svg viewBox="0 0 24 24">

                            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>

                            <path d="M10 21h4"/>

                        </svg>



                    </div>



                    <span>

                        Notifications

                    </span>



                    <small>

                        Updates

                    </small>



                </button>







                <button

                    type="button"

                    onclick="openAppointments()"

                >



                    <div class="service-icon orange">



                        <svg viewBox="0 0 24 24">

                            <rect x="5" y="3" width="14" height="18" rx="2"/>

                            <path d="M8 8h8"/>

                            <path d="M8 12h8"/>

                            <path d="M8 16h5"/>

                        </svg>



                    </div>



                    <span>

                        History

                    </span>



                    <small>

                        Appointments

                    </small>



                </button>







                <button

                    type="button"

                    onclick="window.location.href='hospital_search.php'"

                >



                    <div class="service-icon green">



                        <svg viewBox="0 0 24 24">

                            <path d="M4 21V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v15"/>

                            <path d="M2 21h20"/>

                            <path d="M9 8h6"/>

                            <path d="M12 5v6"/>

                        </svg>



                    </div>



                    <span>

                        Hospitals

                    </span>



                    <small>

                        Find care

                    </small>



                </button>





            </div>



        </section>





        <div class="bottom-padding"></div>



    </main>







    <!-- =====================================================

         BOTTOM NAVIGATION

    ====================================================== -->



    <nav class="bottom-navigation">





        <a

            href="dashboard.php"

            class="nav-link active"

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





<script src="js/index.js"></script>




<!-- Floating AI Assistant: added without changing the existing dashboard layout -->
<a href="ai/assistant.php" class="floating-ai-assistant" aria-label="Open AI Assistant" title="AI Assistant">
    <span class="floating-ai-orbit"></span>
    <span class="floating-ai-sparkle" aria-hidden="true">✦</span>
    <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 3.2 13.8 8l4.9 1.8-4.9 1.8-1.8 4.9-1.8-4.9-4.9-1.8L10.2 8 12 3.2Z"/>
        <path d="m19 14 .9 2.1L22 17l-2.1.9L19 20l-.9-2.1L16 17l2.1-.9L19 14Z"/>
        <path d="m5 2 .7 1.6L7.3 4.3l-1.6.7L5 6.6l-.7-1.6-1.6-.7 1.6-.7L5 2Z"/>
    </svg>
    <span class="floating-ai-tooltip">AI Assistant</span>
</a>

<style>
.floating-ai-assistant {
    position: fixed;
    right: 22px;
    bottom: 100px;
    z-index: 9999;
    width: 62px;
    height: 62px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 22px;
    color: #fff;
    text-decoration: none;
    background: linear-gradient(145deg, #7c3aed 0%, #4f46e5 48%, #2563eb 100%);
    box-shadow: 0 8px 24px rgba(79, 70, 229, .42), inset 0 1px 0 rgba(255,255,255,.45);
    animation: floatingAiBob 2.8s ease-in-out infinite, floatingAiGlow 2.8s ease-in-out infinite;
    -webkit-tap-highlight-color: transparent;
}
.floating-ai-assistant svg {
    width: 31px;
    height: 31px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.7;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,.15));
    animation: floatingAiTwinkle 4s ease-in-out infinite;
}
.floating-ai-orbit {
    position: absolute;
    inset: -6px;
    border: 1px solid rgba(99, 102, 241, .42);
    border-radius: 26px;
    animation: floatingAiOrbit 3s ease-out infinite;
    pointer-events: none;
}
.floating-ai-sparkle {
    position: absolute;
    top: 5px;
    right: 7px;
    font-size: 13px;
    line-height: 1;
    color: #fef08a;
    text-shadow: 0 0 8px rgba(255,255,255,.8);
    animation: floatingAiSparkle 1.8s ease-in-out infinite;
}
.floating-ai-tooltip {
    position: absolute;
    right: 72px;
    white-space: nowrap;
    padding: 8px 12px;
    border-radius: 10px;
    color: #fff;
    background: #24205f;
    font: 600 12px/1.2 Arial, sans-serif;
    box-shadow: 0 5px 18px rgba(25, 25, 80, .2);
    opacity: 0;
    transform: translateX(6px);
    transition: opacity .2s ease, transform .2s ease;
    pointer-events: none;
}
.floating-ai-assistant:hover .floating-ai-tooltip,
.floating-ai-assistant:focus-visible .floating-ai-tooltip {
    opacity: 1;
    transform: translateX(0);
}
.floating-ai-assistant:active { transform: scale(.94); }
@keyframes floatingAiBob {
    0%, 100% { translate: 0 0; }
    50% { translate: 0 -9px; }
}
@keyframes floatingAiGlow {
    0%, 100% { box-shadow: 0 8px 24px rgba(79,70,229,.38), 0 0 0 0 rgba(124,58,237,.24); }
    50% { box-shadow: 0 11px 30px rgba(79,70,229,.55), 0 0 0 8px rgba(124,58,237,0); }
}
@keyframes floatingAiOrbit {
    0% { transform: scale(.92); opacity: .85; }
    100% { transform: scale(1.24); opacity: 0; }
}
@keyframes floatingAiTwinkle {
    0%, 100% { transform: rotate(0deg) scale(1); }
    50% { transform: rotate(8deg) scale(1.08); }
}
@keyframes floatingAiSparkle {
    0%, 100% { opacity: .45; transform: scale(.8) rotate(0); }
    50% { opacity: 1; transform: scale(1.2) rotate(20deg); }
}
@media (max-width: 480px) {
    .floating-ai-assistant { right: 16px; bottom: 88px; width: 56px; height: 56px; border-radius: 19px; }
    .floating-ai-orbit { border-radius: 23px; }
}
@media (prefers-reduced-motion: reduce) {
    .floating-ai-assistant, .floating-ai-assistant * { animation: none !important; transition: none !important; }
}
</style>

</body>



</html>