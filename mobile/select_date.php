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

$page_title = "Select Appointment Date";


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
        "Please select a doctor first.";

    header(
        "Location: select_doctor.php"
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


/*====================================================
    VALIDATE SESSION
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
    GET DOCTOR ID
====================================================*/

$doctor_id =
    filter_input(
        INPUT_POST,
        'doctor_id',
        FILTER_VALIDATE_INT
    );


if (
    $doctor_id === false
    ||
    $doctor_id === null
    ||
    $doctor_id <= 0
) {

    $_SESSION['error'] =
        "Invalid doctor selection.";

    header(
        "Location: select_doctor.php"
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
        "Location: select_doctor.php"
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

$department_sql = "

    SELECT
        department_id,
        department_name,
        description,
        location,
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
    $verified_department_description,
    $verified_department_location,
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


/*====================================================
    VERIFIED DEPARTMENT NAME
====================================================*/

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
        profile_photo,
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
    $verified_consultation_fee,
    $verified_profile_photo,
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
    DOCTOR INFORMATION
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


$doctor_consultation_fee =
    (float)
    (
        $verified_consultation_fee
        ?? 0
    );


/*====================================================
    VERIFY DOCTOR SERVICE
====================================================*/

$service_sql = "

    SELECT

        ds.doctor_service_id,
        ds.service_id,
        ds.consultation_fee,
        ds.consultation_duration,

        s.service_name,
        s.service_type,
        s.service_fee,
        s.duration_minutes,
        s.consultation_mode

    FROM doctor_services AS ds

    INNER JOIN services AS s

        ON s.service_id =
           ds.service_id

    WHERE ds.doctor_id = ?

      AND ds.status = 'Active'

      AND s.department_id = ?

      AND s.status = 'Active'

    ORDER BY
        ds.doctor_service_id ASC

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
        "Hospital Database Error: " .
        htmlspecialchars(
            $error_message,
            ENT_QUOTES,
            'UTF-8'
        )
    );
}


mysqli_stmt_bind_param(
    $service_stmt,
    "ii",
    $doctor_id,
    $department_id
);


if (
    !mysqli_stmt_execute(
        $service_stmt
    )
) {

    $error_message =
        mysqli_stmt_error(
            $service_stmt
        );

    mysqli_stmt_close(
        $service_stmt
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

    $service_stmt,

    $doctor_service_id,
    $service_id,
    $doctor_service_fee,
    $consultation_duration,

    $service_name,
    $service_type,
    $service_fee,
    $duration_minutes,
    $service_consultation_mode

);


$service_found =
    mysqli_stmt_fetch(
        $service_stmt
    );


mysqli_stmt_close(
    $service_stmt
);


if (!$service_found) {

    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "This doctor currently has no active service.";

    header(
        "Location: select_doctor.php"
    );

    exit;
}


/*====================================================
    CONSULTATION FEE
====================================================*/

if (
    $doctor_service_fee !== null
    &&
    $doctor_service_fee !== ''
) {

    $consultation_fee =
        (float)
        $doctor_service_fee;

}
elseif (
    $service_fee !== null
    &&
    $service_fee !== ''
) {

    $consultation_fee =
        (float)
        $service_fee;

}
else {

    $consultation_fee =
        $doctor_consultation_fee;
}


/*====================================================
    CONSULTATION MODE
====================================================*/

$consultation_mode =
    trim(
        (string)
        (
            $service_consultation_mode
            ?? 'In-Person'
        )
    );


if (
    !in_array(
        $consultation_mode,
        [
            'In-Person',
            'Video',
            'Both'
        ],
        true
    )
) {

    $consultation_mode =
        'In-Person';
}


/*====================================================
    GET DOCTOR AVAILABLE DAYS
====================================================*/

$availability_sql = "

    SELECT DISTINCT

        day_of_week

    FROM doctor_availability

    WHERE doctor_id = ?

      AND status = 'Active'

";


$availability_stmt =
    mysqli_prepare(
        $hospital_conn,
        $availability_sql
    );


$available_days = [];


if ($availability_stmt) {

    mysqli_stmt_bind_param(
        $availability_stmt,
        "i",
        $doctor_id
    );


    if (
        mysqli_stmt_execute(
            $availability_stmt
        )
    ) {

        mysqli_stmt_bind_result(
            $availability_stmt,
            $available_day
        );


        while (
            mysqli_stmt_fetch(
                $availability_stmt
            )
        ) {

            $available_days[] =
                (string)
                $available_day;
        }
    }


    mysqli_stmt_close(
        $availability_stmt
    );
}


/*====================================================
    SORT AVAILABLE DAYS
====================================================*/

$day_order = [

    'Monday' => 1,
    'Tuesday' => 2,
    'Wednesday' => 3,
    'Thursday' => 4,
    'Friday' => 5,
    'Saturday' => 6,
    'Sunday' => 7

];


usort(
    $available_days,
    function (
        string $a,
        string $b
    ) use (
        $day_order
    ): int {

        return
            ($day_order[$a] ?? 99)
            <=>
            ($day_order[$b] ?? 99);
    }
);


/*====================================================
    CLOSE DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    SAVE VERIFIED DATA IN SESSION
====================================================*/

$_SESSION['appointment_booking']['department_id'] =
    $department_id;

$_SESSION['appointment_booking']['department_name'] =
    $department_name;

$_SESSION['appointment_booking']['doctor_id'] =
    $doctor_id;

$_SESSION['appointment_booking']['doctor_name'] =
    $doctor_name;

$_SESSION['appointment_booking']['specialization'] =
    $specialization;

$_SESSION['appointment_booking']['qualification'] =
    $qualification;

$_SESSION['appointment_booking']['experience_years'] =
    $experience_years;

$_SESSION['appointment_booking']['service_id'] =
    (int)
    $service_id;

$_SESSION['appointment_booking']['service_name'] =
    (string)
    $service_name;

$_SESSION['appointment_booking']['consultation_fee'] =
    $consultation_fee;

$_SESSION['appointment_booking']['consultation_mode'] =
    $consultation_mode;

$_SESSION['appointment_booking']['available_days'] =
    $available_days;


/*====================================================
    MINIMUM DATE
====================================================*/

$minimum_date =
    date('Y-m-d');


/*====================================================
    FLASH ERROR
====================================================*/

$error =
    (string)
    (
        $_SESSION['error']
        ?? ''
    );

unset(
    $_SESSION['error']
);


/*====================================================
    HTML ESCAPE
====================================================*/

function selectDateEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*====================================================
    HEADER
====================================================*/

require_once __DIR__ .
    '/../patient_portal/includes/header.php';

?>


<style>

/* =====================================================
   SELECT DATE PAGE UI
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
    font-size: 14px;
    font-weight: 600;
    color: #2563eb;
    letter-spacing: 0.4px;
}

.dashboard-welcome h1 {
    margin: 0 0 8px;
    font-size: 30px;
    font-weight: 700;
    color: #1f2937;
}

.dashboard-welcome > div > p:last-child {
    margin: 0;
    color: #6b7280;
    font-size: 15px;
}


/* =====================================================
   ERROR
===================================================== */

.profile-message {
    width: 100%;
    box-sizing: border-box;
    padding: 14px 16px;
    margin-bottom: 20px;
    border-radius: 9px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    font-size: 14px;
    line-height: 1.5;
}

.profile-error {
    background: #fef2f2;
    border-color: #fecaca;
    color: #b91c1c;
}


/* =====================================================
   DASHBOARD PANEL
===================================================== */

.dashboard-panel {
    width: 100%;
    box-sizing: border-box;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 24px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
}


/* =====================================================
   PANEL HEADER
===================================================== */

.dashboard-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 16px;
    margin-bottom: 5px;
    border-bottom: 1px solid #e5e7eb;
}

.dashboard-panel-header h2 {
    margin: 0 0 6px;
    font-size: 21px;
    font-weight: 700;
    color: #1f2937;
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
   AVAILABLE DAYS
===================================================== */

.dashboard-panel > .profile-message {
    margin-top: 20px;
    margin-bottom: 0;
}


/* =====================================================
   FORM
===================================================== */

.profile-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 10px;
}

.profile-field {
    width: 100%;
    max-width: 500px;
    display: flex;
    flex-direction: column;
}

.profile-field label {
    margin-bottom: 9px;
    color: #374151;
    font-size: 14px;
    font-weight: 600;
}

.profile-field input[type="date"] {
    width: 100%;
    height: 48px;
    box-sizing: border-box;
    padding: 0 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #ffffff;
    color: #1f2937;
    font-size: 15px;
    font-family: inherit;
    outline: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.profile-field input[type="date"]:hover {
    border-color: #93c5fd;
}

.profile-field input[type="date"]:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}

.profile-field small {
    margin-top: 7px;
    color: #6b7280;
    font-size: 12px;
}


/* =====================================================
   ACTION BUTTONS
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

    .profile-field {
        max-width: 100%;
    }

    .profile-form-actions {
        flex-direction: column-reverse;
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
                    Select Appointment Date
                </h1>


                <p>
                    Choose a suitable date for your
                    consultation.
                </p>

            </div>

        </div>


        <!--================================================
            ERROR MESSAGE
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= selectDateEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


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
                        Review your selected hospital,
                        department and doctor.
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

                        <?= selectDateEscape(
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

                            <?= selectDateEscape(
                                $hospital_patient_code
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- City -->

                <?php if ($city !== ''): ?>

                    <div class="information-row">

                        <span>
                            City
                        </span>


                        <strong>

                            <?= selectDateEscape(
                                $city
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- State -->

                <?php if ($state !== ''): ?>

                    <div class="information-row">

                        <span>
                            State
                        </span>


                        <strong>

                            <?= selectDateEscape(
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

                        <?= selectDateEscape(
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

                        <?= selectDateEscape(
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

                            <?= selectDateEscape(
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

                            <?= selectDateEscape(
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

                        <?= selectDateEscape(
                            (string)
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


                <!-- Consultation Mode -->

                <div class="information-row">

                    <span>
                        Consultation Mode
                    </span>


                    <strong>

                        <?= selectDateEscape(
                            $consultation_mode
                        ); ?>

                    </strong>

                </div>


            </div>

        </div>


        <!--================================================
            DATE SELECTION
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Choose Date
                    </h2>


                    <p>
                        Select the date on which you
                        want to meet the doctor.
                    </p>

                </div>

            </div>


            <!--================================================
                AVAILABLE DAYS
            =================================================-->

            <?php if (
                count($available_days) > 0
            ): ?>

                <div class="profile-message">

                    <strong>
                        Doctor Available:
                    </strong>


                    <?= selectDateEscape(
                        implode(
                            ', ',
                            $available_days
                        )
                    ); ?>

                </div>


            <?php else: ?>

                <div class="profile-message profile-error">

                    This doctor currently has no active
                    availability schedule.

                </div>

            <?php endif; ?>


            <!--================================================
                DATE FORM
            =================================================-->

            <form
                method="POST"
                action="select_time.php"
            >


                <div class="profile-form-grid">


                    <div class="profile-field">


                        <label
                            for="appointment_date"
                        >
                            Appointment Date
                        </label>


                        <input
                            type="date"
                            id="appointment_date"
                            name="appointment_date"
                            min="<?= selectDateEscape(
                                $minimum_date
                            ); ?>"
                            required
                        >


                        <small>
                            Please select today or
                            a future date.
                        </small>


                    </div>


                </div>


                <!--================================================
                    ACTION BUTTONS
                =================================================-->

                <div class="profile-form-actions">


                    <a
                        href="select_doctor.php"
                        class="profile-save-button"
                    >
                        ← Back
                    </a>


                    <button
                        type="submit"
                        class="profile-save-button"
                    >
                        Continue →
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