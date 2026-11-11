<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';



/*====================================================
    ERROR REPORTING
====================================================*/

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


/*====================================================
    SESSION
====================================================*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


/*====================================================
    PAGE TITLE
====================================================*/

$page_title = "Appointment Time";


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/auth_check.php';


/*====================================================
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    $_SESSION['error'] =
        "Please select an appointment date first.";

    header(
        "Location: select_date.php"
    );

    exit;
}


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
        "Please start the appointment booking process again.";

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
    BASIC BOOKING DATA
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


$department_id =
    (int) (
        $booking['department_id']
        ?? 0
    );


$department_name =
    trim(
        (string) (
            $booking['department_name']
            ?? ''
        )
    );


$doctor_id =
    (int) (
        $booking['doctor_id']
        ?? 0
    );


$doctor_name =
    trim(
        (string) (
            $booking['doctor_name']
            ?? ''
        )
    );


$specialization =
    trim(
        (string) (
            $booking['specialization']
            ?? ''
        )
    );


$qualification =
    trim(
        (string) (
            $booking['qualification']
            ?? ''
        )
    );


$experience_years =
    (int) (
        $booking['experience_years']
        ?? 0
    );


$service_id =
    (int) (
        $booking['service_id']
        ?? 0
    );


$service_name =
    trim(
        (string) (
            $booking['service_name']
            ?? ''
        )
    );


$consultation_fee =
    (float) (
        $booking['consultation_fee']
        ?? 0
    );


$consultation_mode =
    trim(
        (string) (
            $booking['consultation_mode']
            ?? 'In-Person'
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
    $department_id <= 0
    ||
    $doctor_id <= 0
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
    GET APPOINTMENT DATE
====================================================*/

$appointment_date =
    trim(
        (string) (
            $_POST['appointment_date']
            ?? ''
        )
    );


/*====================================================
    VALIDATE DATE FORMAT
====================================================*/

$date_object =
    DateTime::createFromFormat(
        '!Y-m-d',
        $appointment_date
    );


$date_errors =
    DateTime::getLastErrors();


if (
    $date_errors !== false
    &&
    (
        $date_errors['warning_count'] > 0
        ||
        $date_errors['error_count'] > 0
    )
) {

    $date_object = false;
}


if (
    !$date_object
    ||
    $date_object->format('Y-m-d')
        !== $appointment_date
) {

    $_SESSION['error'] =
        "Invalid appointment date.";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    PREVENT PAST DATE
====================================================*/

$today =
    date('Y-m-d');


if (
    $appointment_date < $today
) {

    $_SESSION['error'] =
        "Past dates cannot be selected.";

    header(
        "Location: select_date.php"
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
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn) {

    $_SESSION['error'] =
        "Unable to connect to the selected hospital database.";

    header(
        "Location: select_date.php"
    );

    exit;
}


mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*====================================================
    DAY OF WEEK
====================================================*/

$day_of_week =
    $date_object->format('l');


/*====================================================
    VERIFY DEPARTMENT
====================================================*/

$department_sql = "

    SELECT
        department_id,
        department_name,
        status

    FROM departments

    WHERE department_id = ?

      AND status = 'Active'

    LIMIT 1

";


$department_stmt =
    mysqli_prepare(
        $hospital_conn,
        $department_sql
    );


if (!$department_stmt) {

    $error_message =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $department_stmt,
    "i",
    $department_id
);


if (
    !mysqli_stmt_execute(
        $department_stmt
    )
) {

    $error_message =
        mysqli_stmt_error(
            $department_stmt
        );

    mysqli_stmt_close(
        $department_stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_result(

    $department_stmt,

    $verified_department_id,
    $verified_department_name,
    $verified_department_status

);


$department_found =
    mysqli_stmt_fetch(
        $department_stmt
    );


mysqli_stmt_close(
    $department_stmt
);


if (!$department_found) {

    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "Selected department is no longer available.";

    header(
        "Location: select_doctor.php"
    );

    exit;
}


$department_name =
    (string)
    $verified_department_name;


/*====================================================
    VERIFY DOCTOR
====================================================*/

$doctor_sql = "

    SELECT

        doctor_id,
        department_id,
        doctor_name,
        specialization,
        qualification,
        experience_years,
        consultation_fee,
        status

    FROM doctors

    WHERE doctor_id = ?

      AND department_id = ?

      AND status = 'Active'

    LIMIT 1

";


$doctor_stmt =
    mysqli_prepare(
        $hospital_conn,
        $doctor_sql
    );


if (!$doctor_stmt) {

    $error_message =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $doctor_stmt,
    "ii",
    $doctor_id,
    $department_id
);


if (
    !mysqli_stmt_execute(
        $doctor_stmt
    )
) {

    $error_message =
        mysqli_stmt_error(
            $doctor_stmt
        );

    mysqli_stmt_close(
        $doctor_stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_result(

    $doctor_stmt,

    $verified_doctor_id,
    $verified_doctor_department_id,
    $verified_doctor_name,
    $verified_specialization,
    $verified_qualification,
    $verified_experience_years,
    $verified_doctor_fee,
    $verified_doctor_status

);


$doctor_found =
    mysqli_stmt_fetch(
        $doctor_stmt
    );


mysqli_stmt_close(
    $doctor_stmt
);


if (!$doctor_found) {

    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "Selected doctor is no longer available.";

    header(
        "Location: select_doctor.php"
    );

    exit;
}


/*====================================================
    USE VERIFIED DOCTOR DATA
====================================================*/

$doctor_name =
    (string)
    $verified_doctor_name;


$specialization =
    (string)
    (
        $verified_specialization
        ?? ''
    );


$qualification =
    (string)
    (
        $verified_qualification
        ?? ''
    );


$experience_years =
    (int)
    (
        $verified_experience_years
        ?? 0
    );


if (
    $consultation_fee <= 0
) {

    $consultation_fee =
        (float)
        (
            $verified_doctor_fee
            ?? 0
        );
}


/*====================================================
    GET DOCTOR AVAILABILITY
====================================================*/

$availability_sql = "

    SELECT

        availability_id,
        doctor_id,
        day_of_week,
        start_time,
        end_time,
        slot_duration_minutes,
        max_patients,
        consultation_mode,
        status

    FROM doctor_availability

    WHERE doctor_id = ?

      AND day_of_week = ?

      AND status = 'Active'

    ORDER BY
        start_time ASC

";


$availability_stmt =
    mysqli_prepare(
        $hospital_conn,
        $availability_sql
    );


if (!$availability_stmt) {

    $error_message =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $availability_stmt,
    "is",
    $doctor_id,
    $day_of_week
);


if (
    !mysqli_stmt_execute(
        $availability_stmt
    )
) {

    $error_message =
        mysqli_stmt_error(
            $availability_stmt
        );

    mysqli_stmt_close(
        $availability_stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*====================================================
    READ AVAILABILITY
====================================================*/

mysqli_stmt_bind_result(

    $availability_stmt,

    $availability_id,
    $availability_doctor_id,
    $availability_day,
    $start_time,
    $end_time,
    $slot_duration_minutes,
    $max_patients,
    $availability_consultation_mode,
    $availability_status

);


$availability = [];


while (
    mysqli_stmt_fetch(
        $availability_stmt
    )
) {

    $availability[] = [

        'availability_id' =>
            (int)
            $availability_id,

        'doctor_id' =>
            (int)
            $availability_doctor_id,

        'day_of_week' =>
            (string)
            $availability_day,

        'start_time' =>
            (string)
            $start_time,

        'end_time' =>
            (string)
            $end_time,

        'slot_duration_minutes' =>
            (int)
            (
                $slot_duration_minutes
                ?? 10
            ),

        'max_patients' =>
            (int)
            (
                $max_patients
                ?? 0
            ),

        'consultation_mode' =>
            (string)
            (
                $availability_consultation_mode
                ?? 'In-Person'
            ),

        'status' =>
            (string)
            (
                $availability_status
                ?? ''
            )

    ];
}


mysqli_stmt_close(
    $availability_stmt
);


/*====================================================
    NO AVAILABILITY
====================================================*/

if (
    count($availability) === 0
) {

    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "No appointment availability is configured for "
        . $doctor_name
        . " on "
        . $day_of_week
        . ".";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    GET EXISTING APPOINTMENTS
====================================================*/

$appointment_sql = "

    SELECT

        appointment_time

    FROM appointments

    WHERE doctor_id = ?

      AND appointment_date = ?

      AND appointment_status IN
      (
          'Scheduled',
          'Checked-In',
          'In-Progress'
      )

    ORDER BY
        appointment_time ASC

";


$appointment_stmt =
    mysqli_prepare(
        $hospital_conn,
        $appointment_sql
    );


if (!$appointment_stmt) {

    $error_message =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $appointment_stmt,
    "is",
    $doctor_id,
    $appointment_date
);


if (
    !mysqli_stmt_execute(
        $appointment_stmt
    )
) {

    $error_message =
        mysqli_stmt_error(
            $appointment_stmt
        );

    mysqli_stmt_close(
        $appointment_stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


/*====================================================
    READ BOOKED TIMES
====================================================*/

mysqli_stmt_bind_result(
    $appointment_stmt,
    $existing_appointment_time
);


$booked_times = [];


while (
    mysqli_stmt_fetch(
        $appointment_stmt
    )
) {

    $booked_times[] =
        substr(
            (string)
            $existing_appointment_time,
            0,
            8
        );
}


mysqli_stmt_close(
    $appointment_stmt
);


/*====================================================
    AUTOMATIC SLOT SETTINGS
====================================================*/

/*
    Hospital appointment queue:

    Every next appointment gets
    a minimum 10 minute gap.
*/

$patient_gap_minutes =
    10;


$next_slot =
    null;


$selected_mode =
    $consultation_mode;


$selected_availability_id =
    0;


/*====================================================
    FIND NEXT AVAILABLE SLOT
====================================================*/

foreach (
    $availability
    as $schedule
) {

    /*================================================
        START TIME
    =================================================*/

    $start_time_value =
        substr(
            (string)
            $schedule['start_time'],
            0,
            8
        );


    /*================================================
        END TIME
    =================================================*/

    $end_time_value =
        substr(
            (string)
            $schedule['end_time'],
            0,
            8
        );


    /*================================================
        CREATE START DATETIME
    =================================================*/

    $start =
        DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $appointment_date .
            ' ' .
            $start_time_value
        );


    /*================================================
        CREATE END DATETIME
    =================================================*/

    $end =
        DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $appointment_date .
            ' ' .
            $end_time_value
        );


    if (
        !$start
        ||
        !$end
        ||
        $start >= $end
    ) {

        continue;
    }


    /*================================================
        AVAILABILITY MODE
    =================================================*/

    $schedule_mode =
        trim(
            (string)
            (
                $schedule['consultation_mode']
                ?? ''
            )
        );


    if (
        $schedule_mode !== ''
    ) {

        $selected_mode =
            $schedule_mode;
    }


    /*================================================
        START CANDIDATE
    =================================================*/

    $candidate =
        clone $start;


    /*================================================
        GENERATE 10 MINUTE SLOTS
    =================================================*/

    while (
        $candidate < $end
    ) {

        /*============================================
            TODAY PAST TIME
        ============================================*/

        if (
            $appointment_date === $today
        ) {

            $current_time =
                new DateTime();


            if (
                $candidate <= $current_time
            ) {

                $candidate->modify(
                    "+{$patient_gap_minutes} minutes"
                );

                continue;
            }
        }


        /*============================================
            CHECK BOOKED TIMES
        ============================================*/

        $slot_available =
            true;


        foreach (
            $booked_times
            as $booked_time
        ) {

            $booked_datetime =
                DateTime::createFromFormat(
                    'Y-m-d H:i:s',
                    $appointment_date .
                    ' ' .
                    $booked_time
                );


            if (
                !$booked_datetime
            ) {

                continue;
            }


            /*
                Existing appointment ke saath
                minimum 10 minute gap.
            */

            $difference =
                abs(
                    $candidate->getTimestamp()
                    -
                    $booked_datetime->getTimestamp()
                );


            if (
                $difference
                <
                (
                    $patient_gap_minutes
                    *
                    60
                )
            ) {

                $slot_available =
                    false;

                break;
            }
        }


        /*============================================
            SLOT FOUND
        ============================================*/

        if (
            $slot_available
            &&
            $candidate < $end
        ) {

            $next_slot =
                $candidate->format(
                    'H:i:s'
                );


            $selected_availability_id =
                (int)
                $schedule[
                    'availability_id'
                ];


            break 2;
        }


        /*============================================
            NEXT SLOT
        ============================================*/

        $candidate->modify(
            "+{$patient_gap_minutes} minutes"
        );
    }
}


/*====================================================
    NO SLOT AVAILABLE
====================================================*/

if (
    $next_slot === null
) {

    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "No appointment slot is available for "
        . $doctor_name
        . " on "
        . date(
            'd M Y',
            strtotime(
                $appointment_date
            )
        )
        . ".";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    SAVE APPOINTMENT DATA
====================================================*/

$_SESSION['appointment_booking'][

    'appointment_date'

] =
    $appointment_date;


$_SESSION['appointment_booking'][

    'appointment_time'

] =
    $next_slot;


$_SESSION['appointment_booking'][

    'consultation_mode'

] =
    $selected_mode;


$_SESSION['appointment_booking'][

    'availability_id'

] =
    $selected_availability_id;


/*====================================================
    CLOSE DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    ESCAPE FUNCTION
====================================================*/

function appointmentTimeEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*====================================================
    DISPLAY DATE
====================================================*/

$display_date =
    date(
        'd M Y',
        strtotime(
            $appointment_date
        )
    );


/*====================================================
    DISPLAY TIME
====================================================*/

$display_time =
    date(
        'h:i A',
        strtotime(
            $next_slot
        )
    );


/*====================================================
    HEADER
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/header.php';

?>


<style>

/* =====================================================
   SELECT TIME PAGE
===================================================== */

.patient-dashboard-content {
    padding: 30px;
    max-width: 1200px;
    margin: 0 auto;
}


/* =====================================================
   PAGE HEADER
===================================================== */

.dashboard-welcome {
    margin-bottom: 25px;
}

.dashboard-welcome-label {
    margin: 0 0 7px;
    color: #2563eb;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.4px;
}

.dashboard-welcome h1 {
    margin: 0 0 8px;
    color: #1f2937;
    font-size: 30px;
    font-weight: 700;
}

.dashboard-welcome > div > p:last-child {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}


/* =====================================================
   PANEL
===================================================== */

.dashboard-panel {
    width: 100%;
    box-sizing: border-box;
    padding: 25px;
    margin-bottom: 24px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}


/* =====================================================
   PANEL HEADER
===================================================== */

.dashboard-panel-header {
    padding-bottom: 16px;
    margin-bottom: 5px;
    border-bottom: 1px solid #e5e7eb;
}

.dashboard-panel-header h2 {
    margin: 0 0 6px;
    color: #1f2937;
    font-size: 21px;
    font-weight: 700;
}

.dashboard-panel-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}


/* =====================================================
   INFORMATION
===================================================== */

.patient-information {
    width: 100%;
}

.information-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 25px;
    padding: 14px 4px;
    border-bottom: 1px solid #f1f5f9;
}

.information-row:last-child {
    border-bottom: none;
}

.information-row span {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
}

.information-row strong {
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
    text-align: right;
}


/* =====================================================
   HIGHLIGHT APPOINTMENT
===================================================== */

.appointment-highlight {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-top: 20px;
}

.appointment-box {
    padding: 22px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    text-align: center;
}

.appointment-box-label {
    display: block;
    margin-bottom: 8px;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.appointment-box-value {
    display: block;
    color: #2563eb;
    font-size: 24px;
    font-weight: 700;
}


/* =====================================================
   QUEUE
===================================================== */

.queue-card {
    margin-top: 20px;
    padding: 18px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 10px;
}

.queue-card-title {
    margin: 0 0 5px;
    color: #1e40af;
    font-size: 15px;
    font-weight: 700;
}

.queue-card-text {
    margin: 0;
    color: #475569;
    font-size: 13px;
    line-height: 1.5;
}


/* =====================================================
   ACTIONS
===================================================== */

.profile-form-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #f1f5f9;
}

.profile-save-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    min-height: 44px;
    padding: 10px 20px;
    box-sizing: border-box;
    border: none;
    border-radius: 8px;
    background: #2563eb;
    color: #ffffff !important;
    text-decoration: none !important;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s ease;
}

.profile-save-button:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.profile-save-button:active {
    transform: translateY(0);
}


/* =====================================================
   BACK BUTTON
===================================================== */

.profile-back-button {
    background: #64748b;
}

.profile-back-button:hover {
    background: #475569;
}


/* =====================================================
   SUCCESS MESSAGE
===================================================== */

.time-success {
    padding: 16px 18px;
    margin-bottom: 20px;
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 10px;
    color: #166534;
    font-size: 14px;
    line-height: 1.5;
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media (max-width: 768px) {

    .patient-dashboard-content {
        padding: 20px 15px;
    }

    .dashboard-welcome h1 {
        font-size: 25px;
    }

    .dashboard-panel {
        padding: 20px 17px;
    }

    .dashboard-panel-header h2 {
        font-size: 19px;
    }

    .information-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .information-row strong {
        text-align: left;
    }

    .appointment-highlight {
        grid-template-columns: 1fr;
    }

    .profile-form-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .profile-save-button {
        width: 100%;
    }

}

</style>


<div class="patient-dashboard">


    <!--================================================
        MAIN CONTENT
    =================================================-->

    <section class="patient-dashboard-content">


        <!--================================================
            PAGE HEADER
        =================================================-->

        <div class="dashboard-welcome">

            <div>

                <p class="dashboard-welcome-label">
                    Patient Portal
                </p>


                <h1>
                    Appointment Time
                </h1>


                <p>
                    Your next available appointment time
                    has been automatically assigned.
                </p>

            </div>

        </div>


        <!--================================================
            APPOINTMENT DETAILS
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Appointment Details
                    </h2>


                    <p>
                        Review your selected appointment
                        information.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <!-- Hospital -->

                <div class="information-row">

                    <span>
                        Hospital
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <!-- Patient Code -->

                <?php if (
                    $hospital_patient_code !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Patient Code
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $hospital_patient_code
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- City -->

                <?php if (
                    $city !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            City
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $city
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- State -->

                <?php if (
                    $state !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            State
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $state
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Department -->

                <div class="information-row">

                    <span>
                        Department
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $department_name
                        ); ?>

                    </strong>

                </div>


                <!-- Doctor -->

                <div class="information-row">

                    <span>
                        Doctor
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $doctor_name
                        ); ?>

                    </strong>

                </div>


                <!-- Specialization -->

                <?php if (
                    $specialization !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Specialization
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $specialization
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Qualification -->

                <?php if (
                    $qualification !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Qualification
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $qualification
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Experience -->

                <div class="information-row">

                    <span>
                        Experience
                    </span>


                    <strong>

                        <?= $experience_years; ?>

                        Years

                    </strong>

                </div>


                <!-- Service -->

                <?php if (
                    $service_name !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Service
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $service_name
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Consultation Fee -->

                <div class="information-row">

                    <span>
                        Consultation Fee
                    </span>


                    <strong>

                        ₹<?= number_format(
                            $consultation_fee,
                            2
                        ); ?>

                    </strong>

                </div>


                <!-- Consultation Mode -->

                <div class="information-row">

                    <span>
                        Consultation Mode
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $selected_mode
                        ); ?>

                    </strong>

                </div>


            </div>


            <!--================================================
                DATE AND TIME HIGHLIGHT
            =================================================-->

            <div class="appointment-highlight">


                <div class="appointment-box">

                    <span class="appointment-box-label">
                        Appointment Date
                    </span>


                    <span class="appointment-box-value">

                        <?= appointmentTimeEscape(
                            $display_date
                        ); ?>

                    </span>

                </div>


                <div class="appointment-box">

                    <span class="appointment-box-label">
                        Appointment Time
                    </span>


                    <span class="appointment-box-value">

                        <?= appointmentTimeEscape(
                            $display_time
                        ); ?>

                    </span>

                </div>


            </div>


        </div>


        <!--================================================
            AUTOMATIC QUEUE
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Automatic Queue
                    </h2>


                    <p>
                        Your appointment time is assigned
                        automatically according to the
                        doctor's availability.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <!-- Queue System -->

                <div class="information-row">

                    <span>
                        Queue System
                    </span>


                    <strong>
                        Automatic
                    </strong>

                </div>


                <!-- Patient Interval -->

                <div class="information-row">

                    <span>
                        Patient Interval
                    </span>


                    <strong>
                        10 Minutes
                    </strong>

                </div>


                <!-- Appointment Date -->

                <div class="information-row">

                    <span>
                        Appointment Date
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $display_date
                        ); ?>

                    </strong>

                </div>


                <!-- Assigned Time -->

                <div class="information-row">

                    <span>
                        Assigned Time
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


            </div>


            <!--================================================
                INFORMATION MESSAGE
            =================================================-->

            <div class="queue-card">

                <p class="queue-card-title">
                    Appointment Time Assigned
                </p>


                <p class="queue-card-text">

                    The system has automatically selected
                    the next available 10-minute appointment
                    slot for you.

                </p>

            </div>


            <!--================================================
                ACTIONS
            =================================================-->

            <form
                method="POST"
                action="confirm_appointment.php"
            >


                <div class="profile-form-actions">


                    <a
                        href="select_date.php"
                        class="profile-save-button profile-back-button"
                    >
                        ← Back
                    </a>


                    <button
                        type="submit"
                        class="profile-save-button"
                    >
                        Continue to Confirmation →
                    </button>


                </div>


            </form>


        </div>


    </section>


</div>


<?php

require_once __DIR__ .
    '/../patient_portal/includes/footer.php';

?>