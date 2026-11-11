<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';


error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$page_title = "Confirm Appointment";


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/auth_check.php';


/*====================================================
    ESCAPE FUNCTION
====================================================*/

function confirmAppointmentEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*====================================================
    CHECK EXISTING CONFIRMATION
====================================================*/

$confirmation =
    $_SESSION['appointment_confirmation']
    ?? null;


/*====================================================
    PROCESS APPOINTMENT
====================================================*/

if (
    !is_array($confirmation)
) {


    /*================================================
        ONLY POST REQUEST
    ================================================*/

    if (
        $_SERVER['REQUEST_METHOD'] !== 'POST'
    ) {

        $_SESSION['error'] =
            "Please complete the appointment booking process first.";

        header(
            "Location: select_time.php"
        );

        exit;
    }


    /*================================================
        CHECK BOOKING SESSION
    ================================================*/

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
            "Appointment booking session has expired.";

        header(
            "Location: book.php"
        );

        exit;
    }


    /*================================================
        GET BOOKING
    ================================================*/

    $booking =
        $_SESSION['appointment_booking'];


    /*================================================
        ACCOUNT / HOSPITAL
    ================================================*/

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


    /*================================================
        DEPARTMENT
    ================================================*/

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


    /*================================================
        DOCTOR
    ================================================*/

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


    /*================================================
        SERVICE
    ================================================*/

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


    /*================================================
        DATE / TIME
    ================================================*/

    $appointment_date =
        trim(
            (string) (
                $booking['appointment_date']
                ?? ''
            )
        );


    $appointment_time =
        trim(
            (string) (
                $booking['appointment_time']
                ?? ''
            )
        );


    /*================================================
        VALIDATE SESSION
    ================================================*/

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
        $service_id <= 0
        ||
        $hospital_database === ''
        ||
        $appointment_date === ''
        ||
        $appointment_time === ''
    ) {

        $_SESSION['error'] =
            "Invalid appointment booking information.";

        header(
            "Location: book.php"
        );

        exit;
    }


    /*================================================
        VALIDATE DATABASE NAME
    ================================================*/

    if (
        !preg_match(
            '/^[A-Za-z0-9_]+$/',
            $hospital_database
        )
    ) {

        $_SESSION['error'] =
            "Invalid hospital database configuration.";

        header(
            "Location: book.php"
        );

        exit;
    }


    /*================================================
        VALIDATE DATE
    ================================================*/

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


    /*================================================
        PAST DATE CHECK
    ================================================*/

    if (
        $appointment_date < date('Y-m-d')
    ) {

        $_SESSION['error'] =
            "Past dates cannot be booked.";

        header(
            "Location: select_date.php"
        );

        exit;
    }


    /*================================================
        VALIDATE TIME
    ================================================*/

    $time_object =
        DateTime::createFromFormat(
            '!H:i:s',
            $appointment_time
        );


    $time_errors =
        DateTime::getLastErrors();


    if (
        $time_errors !== false
        &&
        (
            $time_errors['warning_count'] > 0
            ||
            $time_errors['error_count'] > 0
        )
    ) {

        $time_object = false;
    }


    if (!$time_object) {

        $time_object =
            DateTime::createFromFormat(
                '!H:i',
                $appointment_time
            );

        if ($time_object) {

            $appointment_time =
                $time_object->format(
                    'H:i:s'
                );
        }
    }


    if (!$time_object) {

        $_SESSION['error'] =
            "Invalid appointment time.";

        header(
            "Location: select_time.php"
        );

        exit;
    }


    /*================================================
        CONSULTATION MODE
    ================================================*/

    if (
        $consultation_mode !== 'Video'
    ) {

        $consultation_mode =
            'In-Person';
    }


    /*================================================
        APPOINTMENT TYPE
    ================================================*/

    $appointment_type =
        'Online';


    /*================================================
        APPOINTMENT STATUS
    ================================================*/

    $appointment_status =
        'Scheduled';


    /*================================================
        CONNECT HOSPITAL DATABASE
    ================================================*/

    $hospital_conn =
        db_connect($hospital_database);


    if (!$hospital_conn) {

        $_SESSION['error'] =
            "Unable to connect to the selected hospital database.";

        header(
            "Location: select_time.php"
        );

        exit;
    }


    mysqli_set_charset(
        $hospital_conn,
        "utf8mb4"
    );


    /*================================================
        VERIFY DOCTOR
    ================================================*/

    $doctor_sql = "

        SELECT
            doctor_id

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
            "Database Error: " .
            confirmAppointmentEscape(
                $error_message
            )
        );
    }


    mysqli_stmt_bind_param(
        $doctor_stmt,
        "ii",
        $doctor_id,
        $department_id
    );


    mysqli_stmt_execute(
        $doctor_stmt
    );


    mysqli_stmt_store_result(
        $doctor_stmt
    );


    $doctor_exists =
        mysqli_stmt_num_rows(
            $doctor_stmt
        ) > 0;


    mysqli_stmt_close(
        $doctor_stmt
    );


    if (!$doctor_exists) {

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


    /*================================================
        VERIFY SERVICE
    ================================================*/

    $service_sql = "

        SELECT
            ds.service_id

        FROM doctor_services AS ds

        INNER JOIN services AS s

            ON s.service_id =
               ds.service_id

        WHERE ds.doctor_id = ?

          AND ds.service_id = ?

          AND ds.status = 'Active'

          AND s.department_id = ?

          AND s.status = 'Active'

        LIMIT 1

    ";


    $service_stmt =
        mysqli_prepare(
            $hospital_conn,
            $service_sql
        );


    if (!$service_stmt) {

        $error_message =
            mysqli_error(
                $hospital_conn
            );

        mysqli_close(
            $hospital_conn
        );

        die(
            "Database Error: " .
            confirmAppointmentEscape(
                $error_message
            )
        );
    }


    mysqli_stmt_bind_param(
        $service_stmt,
        "iii",
        $doctor_id,
        $service_id,
        $department_id
    );


    mysqli_stmt_execute(
        $service_stmt
    );


    mysqli_stmt_store_result(
        $service_stmt
    );


    $service_exists =
        mysqli_stmt_num_rows(
            $service_stmt
        ) > 0;


    mysqli_stmt_close(
        $service_stmt
    );


    if (!$service_exists) {

        mysqli_close(
            $hospital_conn
        );

        $_SESSION['error'] =
            "Selected service is no longer available.";

        header(
            "Location: select_doctor.php"
        );

        exit;
    }


    /*================================================
        CHECK DUPLICATE SLOT
    ================================================*/

    $duplicate_sql = "

        SELECT
            appointment_id

        FROM appointments

        WHERE doctor_id = ?

          AND appointment_date = ?

          AND appointment_time = ?

          AND appointment_status IN
          (
              'Scheduled',
              'Checked-In',
              'In-Progress'
          )

        LIMIT 1

    ";


    $duplicate_stmt =
        mysqli_prepare(
            $hospital_conn,
            $duplicate_sql
        );


    if (!$duplicate_stmt) {

        $error_message =
            mysqli_error(
                $hospital_conn
            );

        mysqli_close(
            $hospital_conn
        );

        die(
            "Database Error: " .
            confirmAppointmentEscape(
                $error_message
            )
        );
    }


    mysqli_stmt_bind_param(
        $duplicate_stmt,
        "iss",
        $doctor_id,
        $appointment_date,
        $appointment_time
    );


    mysqli_stmt_execute(
        $duplicate_stmt
    );


    mysqli_stmt_store_result(
        $duplicate_stmt
    );


    $duplicate_found =
        mysqli_stmt_num_rows(
            $duplicate_stmt
        ) > 0;


    mysqli_stmt_close(
        $duplicate_stmt
    );


    if ($duplicate_found) {

        mysqli_close(
            $hospital_conn
        );

        $_SESSION['error'] =
            "This appointment slot has already been booked.";

        header(
            "Location: select_date.php"
        );

        exit;
    }


    /*================================================
        GENERATE APPOINTMENT NUMBER
    ================================================*/

    $appointment_no =
        'APT-' .
        date('YmdHis') .
        '-' .
        strtoupper(
            substr(
                bin2hex(
                    random_bytes(3)
                ),
                0,
                6
            )
        );


    /*================================================
        INSERT APPOINTMENT
    ================================================*/

    $insert_sql = "

        INSERT INTO appointments
        (
            mapping_id,
            doctor_id,
            service_id,
            appointment_no,
            appointment_date,
            appointment_time,
            appointment_type,
            consultation_mode,
            appointment_status
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )

    ";


    $insert_stmt =
        mysqli_prepare(
            $hospital_conn,
            $insert_sql
        );


    if (!$insert_stmt) {

        $error_message =
            mysqli_error(
                $hospital_conn
            );

        mysqli_close(
            $hospital_conn
        );

        die(
            "Appointment Insert Error: " .
            confirmAppointmentEscape(
                $error_message
            )
        );
    }


    mysqli_stmt_bind_param(
        $insert_stmt,
        "iiissssss",
        $mapping_id,
        $doctor_id,
        $service_id,
        $appointment_no,
        $appointment_date,
        $appointment_time,
        $appointment_type,
        $consultation_mode,
        $appointment_status
    );


    if (
        !mysqli_stmt_execute(
            $insert_stmt
        )
    ) {

        $error_message =
            mysqli_stmt_error(
                $insert_stmt
            );

        mysqli_stmt_close(
            $insert_stmt
        );

        mysqli_close(
            $hospital_conn
        );

        die(
            "Appointment Insert Error: " .
            confirmAppointmentEscape(
                $error_message
            )
        );
    }


    /*================================================
        GET APPOINTMENT ID
    ================================================*/

    $appointment_id =
        mysqli_insert_id(
            $hospital_conn
        );


    mysqli_stmt_close(
        $insert_stmt
    );


    mysqli_close(
        $hospital_conn
    );


    /*================================================
        SAVE CONFIRMATION
    ================================================*/

    $_SESSION['appointment_confirmation'] = [

        'appointment_id' =>
            $appointment_id,

        'appointment_no' =>
            $appointment_no,

        'hospital_id' =>
            $hospital_id,

        'hospital_name' =>
            $hospital_name,

        'hospital_patient_code' =>
            $hospital_patient_code,

        'city' =>
            $city,

        'state' =>
            $state,

        'department_id' =>
            $department_id,

        'department_name' =>
            $department_name,

        'doctor_id' =>
            $doctor_id,

        'doctor_name' =>
            $doctor_name,

        'specialization' =>
            $specialization,

        'qualification' =>
            $qualification,

        'experience_years' =>
            $experience_years,

        'service_id' =>
            $service_id,

        'service_name' =>
            $service_name,

        'consultation_fee' =>
            $consultation_fee,

        'appointment_date' =>
            $appointment_date,

        'appointment_time' =>
            $appointment_time,

        'appointment_type' =>
            $appointment_type,

        'consultation_mode' =>
            $consultation_mode,

        'appointment_status' =>
            $appointment_status

    ];


    /*================================================
        REMOVE BOOKING SESSION
    ================================================*/

    unset(
        $_SESSION['appointment_booking']
    );


    /*================================================
        REDIRECT
    ================================================*/

    header(
        "Location: confirm_appointment.php"
    );

    exit;
}


/*====================================================
    GET CONFIRMATION DATA
====================================================*/

$appointment_id =
    (int) (
        $confirmation['appointment_id']
        ?? 0
    );


$appointment_no =
    (string) (
        $confirmation['appointment_no']
        ?? ''
    );


$hospital_name =
    (string) (
        $confirmation['hospital_name']
        ?? ''
    );


$hospital_patient_code =
    (string) (
        $confirmation['hospital_patient_code']
        ?? ''
    );


$city =
    (string) (
        $confirmation['city']
        ?? ''
    );


$state =
    (string) (
        $confirmation['state']
        ?? ''
    );


$department_name =
    (string) (
        $confirmation['department_name']
        ?? ''
    );


$doctor_name =
    (string) (
        $confirmation['doctor_name']
        ?? ''
    );


$specialization =
    (string) (
        $confirmation['specialization']
        ?? ''
    );


$qualification =
    (string) (
        $confirmation['qualification']
        ?? ''
    );


$experience_years =
    (int) (
        $confirmation['experience_years']
        ?? 0
    );


$service_name =
    (string) (
        $confirmation['service_name']
        ?? ''
    );


$consultation_fee =
    (float) (
        $confirmation['consultation_fee']
        ?? 0
    );


$appointment_date =
    (string) (
        $confirmation['appointment_date']
        ?? ''
    );


$appointment_time =
    (string) (
        $confirmation['appointment_time']
        ?? ''
    );


$appointment_type =
    (string) (
        $confirmation['appointment_type']
        ?? 'Online'
    );


$consultation_mode =
    (string) (
        $confirmation['consultation_mode']
        ?? 'In-Person'
    );


$appointment_status =
    (string) (
        $confirmation['appointment_status']
        ?? 'Scheduled'
    );


/*====================================================
    DISPLAY DATE
====================================================*/

$display_date =
    $appointment_date !== ''
    ?
    date(
        'd M Y',
        strtotime(
            $appointment_date
        )
    )
    :
    '';


/*====================================================
    DISPLAY TIME
====================================================*/

$display_time =
    $appointment_time !== ''
    ?
    date(
        'h:i A',
        strtotime(
            $appointment_time
        )
    )
    :
    '';


/*====================================================
    HEADER
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/header.php';

?>


<style>

/*====================================================
    CONFIRM APPOINTMENT PAGE
====================================================*/

.confirm-appointment-page {
    width: 100%;
}


/*====================================================
    SUCCESS HERO
====================================================*/

.confirm-success-card {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 30px;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    text-align: center;
}


.confirm-success-icon {
    width: 72px;
    height: 72px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: #eaf8ef;
    color: #1f9d55;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 38px;
    font-weight: 700;
}


.confirm-success-card h2 {
    margin: 0 0 8px;
    font-size: 28px;
    color: #1f2937;
}


.confirm-success-card p {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
    line-height: 1.6;
}


.confirm-number-box {
    margin-top: 22px;
    padding: 16px 20px;
    background: #f5f7fb;
    border-radius: 12px;
    border: 1px dashed #cbd5e1;
}


.confirm-number-label {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 5px;
}


.confirm-number {
    display: block;
    font-size: 22px;
    font-weight: 700;
    color: #2563eb;
    letter-spacing: 0.5px;
}


/*====================================================
    SUMMARY GRID
====================================================*/

.confirm-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}


.confirm-summary-item {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 5px 18px rgba(0,0,0,0.04);
}


.confirm-summary-label {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 7px;
}


.confirm-summary-value {
    display: block;
    color: #111827;
    font-size: 18px;
    font-weight: 700;
}


/*====================================================
    APPOINTMENT HIGHLIGHT
====================================================*/

.confirm-appointment-highlight {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}


.confirm-highlight-box {
    padding: 20px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    text-align: center;
}


.confirm-highlight-box span {
    display: block;
    color: #6b7280;
    font-size: 13px;
    margin-bottom: 7px;
}


.confirm-highlight-box strong {
    display: block;
    color: #111827;
    font-size: 18px;
}


/*====================================================
    STATUS
====================================================*/

.confirm-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 14px;
    border-radius: 30px;
    background: #eaf8ef;
    color: #16803c;
    font-size: 13px;
    font-weight: 700;
}


/*====================================================
    BUTTONS
====================================================*/

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
}


.confirm-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 46px;
    padding: 0 22px;
    border-radius: 9px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: 0.2s ease;
}


.confirm-primary-button {
    background: #2563eb;
    color: #ffffff;
}


.confirm-primary-button:hover {
    background: #1d4ed8;
    color: #ffffff;
}


.confirm-secondary-button {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}


.confirm-secondary-button:hover {
    background: #e5e7eb;
    color: #111827;
}


/*====================================================
    NOTE
====================================================*/

.confirm-note {
    margin-top: 20px;
    padding: 15px 18px;
    border-radius: 10px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 14px;
    line-height: 1.6;
}


/*====================================================
    MOBILE
====================================================*/

@media (max-width: 768px) {

    .confirm-summary-grid {
        grid-template-columns: 1fr;
    }


    .confirm-appointment-highlight {
        grid-template-columns: 1fr;
    }


    .confirm-success-card {
        padding: 22px;
    }


    .confirm-success-card h2 {
        font-size: 23px;
    }


    .confirm-number {
        font-size: 18px;
    }


    .confirm-actions {
        justify-content: stretch;
    }


    .confirm-action-button {
        width: 100%;
    }

}

</style>


<div class="patient-dashboard confirm-appointment-page">


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

                    Appointment Confirmation

                </h1>


                <p>

                    Your appointment booking has been
                    successfully completed.

                </p>

            </div>

        </div>


        <!--================================================
            SUCCESS CARD
        =================================================-->

        <div class="confirm-success-card">


            <div class="confirm-success-icon">

                ✓

            </div>


            <h2>

                Appointment Confirmed

            </h2>


            <p>

                Your appointment has been successfully
                booked with the selected doctor.

            </p>


            <div class="confirm-number-box">


                <span class="confirm-number-label">

                    Appointment Number

                </span>


                <span class="confirm-number">

                    <?= confirmAppointmentEscape(
                        $appointment_no
                    ); ?>

                </span>


            </div>


        </div>


        <!--================================================
            QUICK APPOINTMENT INFO
        =================================================-->

        <div class="confirm-appointment-highlight">


            <div class="confirm-highlight-box">

                <span>

                    Appointment Date

                </span>


                <strong>

                    <?= confirmAppointmentEscape(
                        $display_date
                    ); ?>

                </strong>

            </div>


            <div class="confirm-highlight-box">

                <span>

                    Appointment Time

                </span>


                <strong>

                    <?= confirmAppointmentEscape(
                        $display_time
                    ); ?>

                </strong>

            </div>


            <div class="confirm-highlight-box">

                <span>

                    Status

                </span>


                <strong>

                    <span class="confirm-status">

                        <?= confirmAppointmentEscape(
                            $appointment_status
                        ); ?>

                    </span>

                </strong>

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

                        Please check all appointment
                        information below.

                    </p>

                </div>

            </div>


            <div class="patient-information">


                <!-- Appointment Number -->

                <div class="information-row">

                    <span>

                        Appointment Number

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $appointment_no
                        ); ?>

                    </strong>

                </div>


                <!-- Appointment ID -->

                <div class="information-row">

                    <span>

                        Appointment ID

                    </span>


                    <strong>

                        #<?= $appointment_id; ?>

                    </strong>

                </div>


                <!-- Hospital -->

                <div class="information-row">

                    <span>

                        Hospital

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
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

                            <?= confirmAppointmentEscape(
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

                            <?= confirmAppointmentEscape(
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

                            <?= confirmAppointmentEscape(
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

                        <?= confirmAppointmentEscape(
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

                        <?= confirmAppointmentEscape(
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

                            <?= confirmAppointmentEscape(
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

                            <?= confirmAppointmentEscape(
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

                <div class="information-row">

                    <span>

                        Service

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $service_name
                        ); ?>

                    </strong>

                </div>


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


                <!-- Date -->

                <div class="information-row">

                    <span>

                        Appointment Date

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $display_date
                        ); ?>

                    </strong>

                </div>


                <!-- Time -->

                <div class="information-row">

                    <span>

                        Appointment Time

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


                <!-- Appointment Type -->

                <div class="information-row">

                    <span>

                        Appointment Type

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $appointment_type
                        ); ?>

                    </strong>

                </div>


                <!-- Consultation Mode -->

                <div class="information-row">

                    <span>

                        Consultation Mode

                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $consultation_mode
                        ); ?>

                    </strong>

                </div>


                <!-- Status -->

                <div class="information-row">

                    <span>

                        Appointment Status

                    </span>


                    <strong>

                        <span class="confirm-status">

                            <?= confirmAppointmentEscape(
                                $appointment_status
                            ); ?>

                        </span>

                    </strong>

                </div>


            </div>


        </div>


        <br>


        <!--================================================
            FINAL INFORMATION PANEL
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>

                        What's Next?

                    </h2>


                    <p>

                        Please keep your appointment
                        number safe.

                    </p>

                </div>

            </div>


            <div class="confirm-note">

                <strong>

                    Important:

                </strong>

                Please arrive at the hospital according
                to your appointment schedule. Keep your
                patient code and appointment number
                available when you visit the hospital.

            </div>


            <!--================================================
                ACTION BUTTONS
            =================================================-->

            <div class="confirm-actions">


                <a
                    href="book.php"
                    class="confirm-action-button confirm-secondary-button"
                >

                    Book Another Appointment

                </a>


                <a
                    href="dashboard.php"
                    class="confirm-action-button confirm-primary-button"
                >

                    Go to Dashboard

                </a>


            </div>


        </div>


    </section>


</div>


<?php

require_once __DIR__ .
    '/../patient_portal/includes/footer.php';

?>