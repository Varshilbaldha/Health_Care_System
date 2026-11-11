<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';



$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found.");
}


/* =========================================================
   CENTRAL DATABASE
========================================================= */

$central_conn = db_connect();

if (!$central_conn) {
    die("Central database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($central_conn, "utf8mb4");


/* =========================================================
   GET HOSPITAL INFORMATION
========================================================= */

$hospital_stmt = mysqli_prepare(
    $central_conn,
    "SELECT hospital_id, hospital_name, database_name
     FROM hospital_registration
     WHERE application_no = ?
     LIMIT 1"
);

if (!$hospital_stmt) {
    die("Hospital query error: " . mysqli_error($central_conn));
}

mysqli_stmt_bind_param(
    $hospital_stmt,
    "s",
    $application_no
);

mysqli_stmt_execute($hospital_stmt);

$hospital_result = mysqli_stmt_get_result($hospital_stmt);

$hospital = mysqli_fetch_assoc($hospital_result);

mysqli_stmt_close($hospital_stmt);


if (!$hospital) {
    die("Hospital record not found.");
}


$hospital_id   = (int)$hospital['hospital_id'];
$hospital_name = $hospital['hospital_name'];
$database_name = $hospital['database_name'];


/* =========================================================
   HOSPITAL DATABASE
========================================================= */

$hospital_conn = db_connect($database_name);

if (!$hospital_conn) {
    die(
        "Hospital database connection failed: "
        . mysqli_connect_error()
    );
}

mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/* =========================================================
   AUTOMATIC NO-SHOW
   A Scheduled appointment becomes No-Show only when its
   appointment date/time has passed and no staff action changed it.
========================================================= */

$auto_no_show_sql = "
    UPDATE appointments
    SET appointment_status = 'No-Show'
    WHERE appointment_status = 'Scheduled'
      AND TIMESTAMP(appointment_date, appointment_time) < NOW()
";

mysqli_query($hospital_conn, $auto_no_show_sql);


/* =========================================================
   HELPER
========================================================= */

function clean($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   MESSAGE
========================================================= */

$message = '';
$message_type = '';


/* =========================================================
   ADD APPOINTMENT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['add_appointment'])
) {

    $mapping_id          = (int)($_POST['mapping_id'] ?? 0);
    $doctor_id           = (int)($_POST['doctor_id'] ?? 0);
    $service_id          = (int)($_POST['service_id'] ?? 0);

    $appointment_date    = trim(
        $_POST['appointment_date'] ?? ''
    );

    $appointment_time    = trim(
        $_POST['appointment_time'] ?? ''
    );

    $appointment_type    = trim(
        $_POST['appointment_type'] ?? ''
    );

    $consultation_mode   = trim(
        $_POST['consultation_mode'] ?? ''
    );

    $appointment_status  = trim(
        $_POST['appointment_status'] ?? 'Scheduled'
    );

    $symptoms            = trim(
        $_POST['symptoms'] ?? ''
    );

    $notes               = trim(
        $_POST['notes'] ?? ''
    );


    $allowed_types = [
        'Online',
        'Walk-In'
    ];

    $allowed_modes = [
        'In-Person',
        'Video'
    ];

    $allowed_statuses = [
        'Scheduled',
        'Checked-In',
        'In-Progress',
        'Completed',
        'Cancelled',
        'No-Show'
    ];


    /* Date validation */

    $today = date('Y-m-d');

    if ($appointment_date < $today) {

        $message = "Appointment date cannot be in the past.";
        $message_type = "error";

    }


    /* Time validation */

    elseif ($appointment_time < '09:30') {

        $message = "Appointment time cannot be earlier than 9:30 AM.";
        $message_type = "error";

    }


    elseif (
        $mapping_id <= 0
        ||
        $doctor_id <= 0
        ||
        $service_id <= 0
        ||
        $appointment_date === ''
        ||
        $appointment_time === ''
        ||
        !in_array(
            $appointment_type,
            $allowed_types,
            true
        )
        ||
        !in_array(
            $consultation_mode,
            $allowed_modes,
            true
        )
        ||
        !in_array(
            $appointment_status,
            $allowed_statuses,
            true
        )
    ) {

        $message = "Please fill all required appointment details.";
        $message_type = "error";

    }


    else {

        /* -------------------------------------------------
           Generate Appointment Number
        ------------------------------------------------- */

        $appointment_no =
            'APT'
            . date('YmdHis')
            . rand(100, 999);


        /* -------------------------------------------------
           Generate Token Number
        ------------------------------------------------- */

        $token_number = 1;


        $token_stmt = mysqli_prepare(
            $hospital_conn,

            "SELECT
                COALESCE(MAX(token_number), 0) + 1 AS next_token
             FROM appointments
             WHERE appointment_date = ?"
        );


        if ($token_stmt) {

            mysqli_stmt_bind_param(
                $token_stmt,
                "s",
                $appointment_date
            );

            mysqli_stmt_execute($token_stmt);

            $token_result =
                mysqli_stmt_get_result(
                    $token_stmt
                );

            $token_row =
                mysqli_fetch_assoc(
                    $token_result
                );

            $token_number =
                (int)(
                    $token_row['next_token'] ?? 1
                );

            mysqli_stmt_close(
                $token_stmt
            );
        }


        /* -------------------------------------------------
           Insert Appointment
        ------------------------------------------------- */

        $insert_stmt = mysqli_prepare(
            $hospital_conn,

            "INSERT INTO appointments
            (
                mapping_id,
                doctor_id,
                service_id,
                appointment_no,
                appointment_date,
                appointment_time,
                appointment_type,
                consultation_mode,
                token_number,
                appointment_status,
                symptoms,
                notes
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )"
        );


        if (!$insert_stmt) {

            $message =
                "Appointment insert error: "
                . mysqli_error($hospital_conn);

            $message_type = "error";

        }

        else {

            mysqli_stmt_bind_param(
                $insert_stmt,
                "iiisssssisss",
                $mapping_id,
                $doctor_id,
                $service_id,
                $appointment_no,
                $appointment_date,
                $appointment_time,
                $appointment_type,
                $consultation_mode,
                $token_number,
                $appointment_status,
                $symptoms,
                $notes
            );


            if (
                mysqli_stmt_execute(
                    $insert_stmt
                )
            ) {

                mysqli_stmt_close(
                    $insert_stmt
                );

                header(
                    "Location: appointments.php?added=1"
                );

                exit();

            }

            else {

                $message =
                    "Appointment could not be added: "
                    . mysqli_stmt_error($insert_stmt);

                $message_type = "error";

                mysqli_stmt_close(
                    $insert_stmt
                );
            }
        }
    }
}


/* =========================================================
   UPDATE APPOINTMENT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['update_appointment'])
) {

    $appointment_id =
        (int)($_POST['appointment_id'] ?? 0);

    $doctor_id =
        (int)($_POST['doctor_id'] ?? 0);

    $service_id =
        (int)($_POST['service_id'] ?? 0);

    $appointment_date =
        trim(
            $_POST['appointment_date'] ?? ''
        );

    $appointment_time =
        trim(
            $_POST['appointment_time'] ?? ''
        );

    $appointment_type =
        trim(
            $_POST['appointment_type'] ?? ''
        );

    $consultation_mode =
        trim(
            $_POST['consultation_mode'] ?? ''
        );

    $appointment_status =
        trim(
            $_POST['appointment_status'] ?? ''
        );

    $symptoms =
        trim(
            $_POST['symptoms'] ?? ''
        );

    $notes =
        trim(
            $_POST['notes'] ?? ''
        );

    $cancellation_reason =
        trim(
            $_POST['cancellation_reason'] ?? ''
        );


    $allowed_types = [
        'Online',
        'Walk-In'
    ];

    $allowed_modes = [
        'In-Person',
        'Video'
    ];

    $allowed_statuses = [
        'Scheduled',
        'Checked-In',
        'In-Progress',
        'Completed',
        'Cancelled',
        'No-Show'
    ];


    if ($appointment_id <= 0) {

        $message = "Invalid appointment.";
        $message_type = "error";

    }

    elseif ($appointment_date < date('Y-m-d')) {

        $message =
            "Appointment date cannot be in the past.";

        $message_type = "error";

    }

    elseif ($appointment_time < '09:30') {

        $message =
            "Appointment time cannot be earlier than 9:30 AM.";

        $message_type = "error";

    }

    elseif (
        $doctor_id <= 0
        ||
        $service_id <= 0
        ||
        !in_array(
            $appointment_type,
            $allowed_types,
            true
        )
        ||
        !in_array(
            $consultation_mode,
            $allowed_modes,
            true
        )
        ||
        !in_array(
            $appointment_status,
            $allowed_statuses,
            true
        )
    ) {

        $message =
            "Invalid appointment information.";

        $message_type = "error";

    }

    else {

        $update_stmt = mysqli_prepare(
            $hospital_conn,

            "UPDATE appointments
             SET
                doctor_id = ?,
                service_id = ?,
                appointment_date = ?,
                appointment_time = ?,
                appointment_type = ?,
                consultation_mode = ?,
                appointment_status = ?,
                symptoms = ?,
                notes = ?,
                cancellation_reason = ?
             WHERE appointment_id = ?
             LIMIT 1"
        );


        if (!$update_stmt) {

            $message =
                "Update query error: "
                . mysqli_error($hospital_conn);

            $message_type = "error";

        }

        else {

            mysqli_stmt_bind_param(
                $update_stmt,
                "iissssssssi",
                $doctor_id,
                $service_id,
                $appointment_date,
                $appointment_time,
                $appointment_type,
                $consultation_mode,
                $appointment_status,
                $symptoms,
                $notes,
                $cancellation_reason,
                $appointment_id
            );


            if (
                mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                mysqli_stmt_close(
                    $update_stmt
                );

                header(
                    "Location: appointments.php?updated=1"
                );

                exit();

            }

            else {

                $message =
                    "Appointment update failed: "
                    . mysqli_stmt_error($update_stmt);

                $message_type = "error";

                mysqli_stmt_close(
                    $update_stmt
                );
            }
        }
    }
}


/* =========================================================
   DELETE APPOINTMENT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['delete_appointment'])
) {

    $appointment_id =
        (int)($_POST['appointment_id'] ?? 0);


    if ($appointment_id > 0) {

        $delete_stmt = mysqli_prepare(
            $hospital_conn,

            "DELETE FROM appointments
             WHERE appointment_id = ?
             LIMIT 1"
        );


        if ($delete_stmt) {

            mysqli_stmt_bind_param(
                $delete_stmt,
                "i",
                $appointment_id
            );


            if (
                mysqli_stmt_execute(
                    $delete_stmt
                )
            ) {

                mysqli_stmt_close(
                    $delete_stmt
                );

                header(
                    "Location: appointments.php?deleted=1"
                );

                exit();

            }

            else {

                $message =
                    "Appointment could not be deleted.";

                $message_type = "error";
            }


            mysqli_stmt_close(
                $delete_stmt
            );
        }

    }

}


/* =========================================================
   URL SUCCESS MESSAGES
========================================================= */

if (isset($_GET['added'])) {

    $message =
        "Appointment added successfully.";

    $message_type = "success";
}

elseif (isset($_GET['updated'])) {

    $message =
        "Appointment updated successfully.";

    $message_type = "success";
}

elseif (isset($_GET['deleted'])) {

    $message =
        "Appointment deleted successfully.";

    $message_type = "success";
}


/* =========================================================
   DEPARTMENTS
========================================================= */

$departments = [];

$department_result = mysqli_query(
    $hospital_conn,

    "SELECT
        department_id,
        department_name
     FROM departments
     ORDER BY department_name ASC"
);


if ($department_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $department_result
        )
    ) {

        $departments[] = $row;
    }
}


/* =========================================================
   DOCTORS
========================================================= */

$doctors = [];

$doctor_result = mysqli_query(
    $hospital_conn,

    "SELECT
        doctor_id,
        department_id,
        doctor_name,
        status
     FROM doctors
     ORDER BY doctor_name ASC"
);


if ($doctor_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $doctor_result
        )
    ) {

        $doctors[] = $row;
    }
}


/* =========================================================
   SERVICES
========================================================= */

$services = [];

$service_result = mysqli_query(
    $hospital_conn,

    "SELECT
        service_id,
        service_name,
        department_id
     FROM services
     ORDER BY service_name ASC"
);


if ($service_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $service_result
        )
    ) {

        $services[] = $row;
    }
}


/* =========================================================
   PATIENTS
========================================================= */

$patients = [];


$patient_query = "

    SELECT
        phm.mapping_id,
        phm.hospital_patient_code,

        pa.account_id,
        pa.first_name,
        pa.last_name,
        pa.mobile

    FROM hospital_management.patient_hospital_mapping phm

    INNER JOIN hospital_management.patient_accounts pa

        ON phm.account_id = pa.account_id

    WHERE phm.hospital_id = ?

    ORDER BY
        pa.first_name ASC,
        pa.last_name ASC

";


$patient_stmt = mysqli_prepare(
    $central_conn,
    $patient_query
);


if ($patient_stmt) {

    mysqli_stmt_bind_param(
        $patient_stmt,
        "i",
        $hospital_id
    );

    mysqli_stmt_execute(
        $patient_stmt
    );

    $patient_result =
        mysqli_stmt_get_result(
            $patient_stmt
        );


    while (
        $row =
        mysqli_fetch_assoc(
            $patient_result
        )
    ) {

        $patients[] = $row;
    }


    mysqli_stmt_close(
        $patient_stmt
    );
}


/* =========================================================
   FILTERS
========================================================= */

$search =
    trim(
        $_GET['search'] ?? ''
    );

$department_filter =
    (int)(
        $_GET['department'] ?? 0
    );

$doctor_filter =
    (int)(
        $_GET['doctor'] ?? 0
    );

$status_filter =
    trim(
        $_GET['status'] ?? ''
    );

$date_filter =
    trim(
        $_GET['appointment_date']
        ?? $_GET['date']
        ?? ''
    );

$booking_date_filter =
    trim(
        $_GET['booking_date'] ?? ''
    );


/* =========================================================
   APPOINTMENT WHERE
========================================================= */

$where = [];

$params = [];

$types = '';

$where[] = "1=1";


/* Search */

if ($search !== '') {

    $where[] = "

        (
            a.appointment_no LIKE ?

            OR pa.first_name LIKE ?

            OR pa.last_name LIKE ?

            OR CONCAT(
                pa.first_name,
                ' ',
                pa.last_name
            ) LIKE ?

            OR d.doctor_name LIKE ?

            OR phm.hospital_patient_code LIKE ?

        )

    ";


    $search_value =
        "%" . $search . "%";


    for ($i = 0; $i < 6; $i++) {

        $params[] =
            $search_value;

        $types .= 's';
    }
}


/* Department */

if ($department_filter > 0) {

    $where[] =
        "d.department_id = ?";

    $params[] =
        $department_filter;

    $types .= 'i';
}


/* Doctor */

if ($doctor_filter > 0) {

    $where[] =
        "a.doctor_id = ?";

    $params[] =
        $doctor_filter;

    $types .= 'i';
}


/* Status */

if ($status_filter !== '') {

    $where[] =
        "a.appointment_status = ?";

    $params[] =
        $status_filter;

    $types .= 's';
}


/* Date */

if ($date_filter !== '') {

    $where[] =
        "a.appointment_date = ?";

    $params[] =
        $date_filter;

    $types .= 's';
}

/* Booking date */

if ($booking_date_filter !== '') {

    $where[] =
        "DATE(a.created_at) = ?";

    $params[] =
        $booking_date_filter;

    $types .= 's';
}


$where_sql =
    implode(
        " AND ",
        $where
    );


/* =========================================================
   COUNT
========================================================= */

$count_query = "

    SELECT COUNT(*) AS total

    FROM appointments a

    INNER JOIN doctors d
        ON a.doctor_id = d.doctor_id

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON a.mapping_id = phm.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON phm.account_id = pa.account_id

    WHERE $where_sql

";


$count_stmt = mysqli_prepare(
    $hospital_conn,
    $count_query
);


if (!$count_stmt) {

    die(
        "Count query error: "
        . mysqli_error($hospital_conn)
    );
}


if (!empty($params)) {

    mysqli_stmt_bind_param(
        $count_stmt,
        $types,
        ...$params
    );
}


mysqli_stmt_execute(
    $count_stmt
);


$count_result =
    mysqli_stmt_get_result(
        $count_stmt
    );


$count_row =
    mysqli_fetch_assoc(
        $count_result
    );


$total_records =
    (int)(
        $count_row['total'] ?? 0
    );


mysqli_stmt_close(
    $count_stmt
);


/* =========================================================
   PAGINATION
========================================================= */

$per_page = 10;

$page =
    max(
        1,
        (int)(
            $_GET['page'] ?? 1
        )
    );


$total_pages =
    max(
        1,
        (int)ceil(
            $total_records / $per_page
        )
    );


if ($page > $total_pages) {

    $page =
        $total_pages;
}


$offset =
    ($page - 1)
    * $per_page;


/* =========================================================
   GET APPOINTMENTS
========================================================= */

$appointments = [];


$appointment_query = "

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
        a.created_at,

        a.symptoms,
        a.notes,
        a.cancellation_reason,

        d.doctor_name,

        d.department_id,

        dept.department_name,

        s.service_name,

        phm.hospital_patient_code,

        pa.first_name AS patient_first_name,
        pa.last_name AS patient_last_name,
        pa.mobile AS patient_mobile

    FROM appointments a

    INNER JOIN doctors d
        ON a.doctor_id = d.doctor_id

    LEFT JOIN departments dept
        ON d.department_id =
           dept.department_id

    LEFT JOIN services s
        ON a.service_id =
           s.service_id

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON a.mapping_id =
           phm.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON phm.account_id =
           pa.account_id

    WHERE $where_sql

    ORDER BY
        /*
           QUEUE RULE 1:
           Upcoming appointments always come first.
           Cancelled/Completed/No-Show records do not jump ahead of
           the actual upcoming appointment queue.
        */
        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
            THEN 0
            ELSE 1
        END ASC,

        /*
           QUEUE RULE 2:
           Within the upcoming queue, the earliest appointment date
           and earliest appointment time come first.
        */
        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
            THEN a.appointment_date
        END ASC,

        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
            THEN a.appointment_time
        END ASC,

        /*
           QUEUE RULE 3:
           If two patients have exactly the same appointment slot,
           the patient who booked first gets the earlier queue position.
        */
        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
            THEN a.created_at
        END ASC,

        /*
           Historical records stay below the live queue.
           Newest historical appointment is shown first.
        */
        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) < NOW()
            THEN a.appointment_date
        END DESC,

        CASE
            WHEN TIMESTAMP(a.appointment_date, a.appointment_time) < NOW()
            THEN a.appointment_time
        END DESC,

        a.created_at DESC

    LIMIT ?, ?

";


$appointment_params =
    $params;

$appointment_types =
    $types . 'ii';


$appointment_params[] =
    $offset;

$appointment_params[] =
    $per_page;


$appointment_stmt =
    mysqli_prepare(
        $hospital_conn,
        $appointment_query
    );


if (!$appointment_stmt) {

    die(
        "Appointment query error: "
        . mysqli_error($hospital_conn)
    );
}


mysqli_stmt_bind_param(
    $appointment_stmt,
    $appointment_types,
    ...$appointment_params
);


mysqli_stmt_execute(
    $appointment_stmt
);


$appointment_result =
    mysqli_stmt_get_result(
        $appointment_stmt
    );


while (
    $row =
    mysqli_fetch_assoc(
        $appointment_result
    )
) {

    $appointments[] =
        $row;
}


mysqli_stmt_close(
    $appointment_stmt
);


/* =========================================================
   STATISTICS
========================================================= */

$stats = [

    'total' => 0,

    'today' => 0,

    'completed' => 0,

    'pending' => 0,

    'no_show' => 0

];


$stats_result = mysqli_query(

    $hospital_conn,

    "

    SELECT

        COUNT(*) AS total,

        SUM(
            CASE
                WHEN appointment_date =
                     CURDATE()
                THEN 1
                ELSE 0
            END
        ) AS today_count,

        SUM(
            CASE
                WHEN appointment_status =
                     'Completed'
                THEN 1
                ELSE 0
            END
        ) AS completed_count,

        SUM(
            CASE
                WHEN appointment_status IN
                (
                    'Scheduled',
                    'Checked-In',
                    'In-Progress'
                )
                THEN 1
                ELSE 0
            END
        ) AS pending_count,

        SUM(
            CASE
                WHEN appointment_status = 'No-Show'
                THEN 1
                ELSE 0
            END
        ) AS no_show_count

    FROM appointments

    "
);


if ($stats_result) {

    $stats_row =
        mysqli_fetch_assoc(
            $stats_result
        );


    $stats['total'] =
        (int)(
            $stats_row['total'] ?? 0
        );


    $stats['today'] =
        (int)(
            $stats_row['today_count'] ?? 0
        );


    $stats['completed'] =
        (int)(
            $stats_row['completed_count'] ?? 0
        );


    $stats['pending'] =
        (int)(
            $stats_row['pending_count'] ?? 0
        );

    $stats['no_show'] =
        (int)(
            $stats_row['no_show_count'] ?? 0
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
        Appointments |
        <?php echo clean($hospital_name); ?>
    </title>


    <link
        rel="stylesheet"
        href="css/sidebar.css"
    >

    <link
        rel="stylesheet"
        href="css/appointments.css"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>
        /* =====================================================
           ACTION COLUMN VISIBILITY FIX
           Keeps View / Edit / Delete visible without changing
           the existing appointment page design.
        ====================================================== */

        .table-container {
            overflow-x: auto !important;
            overflow-y: visible;
            position: relative;
        }

        .appointments-table {
            min-width: 1180px;
        }

        .appointments-table th:last-child,
        .appointments-table td:last-child {
            min-width: 120px;
            width: 120px;
            text-align: center;
            position: static;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            white-space: nowrap;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            min-width: 34px;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            background: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .view-btn {
            color: #64748b;
        }

        .edit-btn {
            color: #6366f1;
        }

        .delete-btn {
            color: #ef4444;
        }

        @media (max-width: 900px) {
            .appointments-table {
                min-width: 1100px;
            }

            .appointments-table th:last-child,
            .appointments-table td:last-child {
                min-width: 112px;
                width: 112px;
            }
        }
    </style>

</head>


<body>


<?php include 'sidebar.php'; ?>


<main class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Appointments
            </h1>

            <p>
                Manage hospital appointments and patient schedules
            </p>

        </div>


        <button
            class="add-btn"
            id="addAppointmentBtn"
            type="button"
        >

            <i class="fa-solid fa-plus"></i>

            Add Appointment

        </button>

    </div>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== ''): ?>

        <div
            class="<?php
                echo $message_type === 'success'
                    ? 'success-message'
                    : 'error-message';
            ?>"
        >

            <i class="fa-solid
                <?php
                    echo $message_type === 'success'
                        ? 'fa-circle-check'
                        : 'fa-circle-exclamation';
                ?>"
            ></i>

            <?php echo clean($message); ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         STATS
    ====================================================== -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-icon total">

                <i class="fa-solid fa-calendar-days"></i>

            </div>


            <div class="stat-info">

                <span>
                    Total Appointments
                </span>

                <h2>
                    <?php
                    echo number_format(
                        $stats['total']
                    );
                    ?>
                </h2>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon today">

                <i class="fa-solid fa-calendar-day"></i>

            </div>


            <div class="stat-info">

                <span>
                    Today's Appointments
                </span>

                <h2>
                    <?php
                    echo number_format(
                        $stats['today']
                    );
                    ?>
                </h2>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon completed">

                <i class="fa-solid fa-circle-check"></i>

            </div>


            <div class="stat-info">

                <span>
                    Completed
                </span>

                <h2>
                    <?php
                    echo number_format(
                        $stats['completed']
                    );
                    ?>
                </h2>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon pending">

                <i class="fa-solid fa-clock"></i>

            </div>


            <div class="stat-info">

                <span>
                    Pending
                </span>

                <h2>
                    <?php
                    echo number_format(
                        $stats['pending']
                    );
                    ?>
                </h2>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon no-show">

                <i class="fa-solid fa-user-xmark"></i>

            </div>


            <div class="stat-info">

                <span>
                    No-Show
                </span>

                <h2>
                    <?php
                    echo number_format(
                        $stats['no_show']
                    );
                    ?>
                </h2>

            </div>

        </div>

    </div>


    <!-- =====================================================
         FILTER
    ====================================================== -->

    <form
        method="GET"
        class="toolbar"
    >


        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                name="search"
                placeholder="Search patient, doctor or appointment..."
                value="<?php echo clean($search); ?>"
            >

        </div>


        <select
            name="department"
            id="departmentFilter"
        >

            <option value="">
                All Departments
            </option>


            <?php foreach ($departments as $department): ?>

                <option
                    value="<?php
                        echo (int)$department['department_id'];
                    ?>"
                    <?php
                        echo
                        $department_filter ==
                        $department['department_id']
                        ? 'selected'
                        : '';
                    ?>
                >

                    <?php
                    echo clean(
                        $department['department_name']
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>


        <select
            name="doctor"
            id="doctorFilter"
        >

            <option value="">
                All Doctors
            </option>


            <?php foreach ($doctors as $doctor): ?>

                <option
                    value="<?php
                        echo (int)$doctor['doctor_id'];
                    ?>"
                    data-department="<?php
                        echo (int)$doctor['department_id'];
                    ?>"
                    <?php
                        echo
                        $doctor_filter ==
                        $doctor['doctor_id']
                        ? 'selected'
                        : '';
                    ?>
                >

                    Dr.
                    <?php
                    echo clean(
                        $doctor['doctor_name']
                    );
                    ?>

                </option>

            <?php endforeach; ?>

        </select>


        <select name="status">

            <option value="">
                All Status
            </option>


            <option
                value="Scheduled"
                <?php
                    echo $status_filter === 'Scheduled'
                        ? 'selected'
                        : '';
                ?>
            >
                Scheduled
            </option>


            <option
                value="Checked-In"
                <?php
                    echo $status_filter === 'Checked-In'
                        ? 'selected'
                        : '';
                ?>
            >
                Checked-In
            </option>


            <option
                value="In-Progress"
                <?php
                    echo $status_filter === 'In-Progress'
                        ? 'selected'
                        : '';
                ?>
            >
                In-Progress
            </option>


            <option
                value="Completed"
                <?php
                    echo $status_filter === 'Completed'
                        ? 'selected'
                        : '';
                ?>
            >
                Completed
            </option>


            <option
                value="Cancelled"
                <?php
                    echo $status_filter === 'Cancelled'
                        ? 'selected'
                        : '';
                ?>
            >
                Cancelled
            </option>


            <option
                value="No-Show"
                <?php
                    echo $status_filter === 'No-Show'
                        ? 'selected'
                        : '';
                ?>
            >
                No-Show
            </option>

        </select>


        <div class="date-filter-group">

            <label for="appointmentDateFilter">Appointment Date</label>

            <input
                type="date"
                id="appointmentDateFilter"
                name="appointment_date"
                value="<?php echo clean($date_filter); ?>"
                title="Filter by the date the patient will be seen"
            >

        </div>


        <div class="date-filter-group">

            <label for="bookingDateFilter">Booked Date</label>

            <input
                type="date"
                id="bookingDateFilter"
                name="booking_date"
                value="<?php echo clean($booking_date_filter); ?>"
                title="Filter by the date the appointment was booked"
            >

        </div>


        <button
            type="submit"
            class="filter-btn"
        >

            <i class="fa-solid fa-filter"></i>

            Filter

        </button>


        <a
            href="appointments.php"
            class="reset-btn"
        >

            Reset

        </a>

    </form>


    <!-- =====================================================
         APPOINTMENT TABLE
    ====================================================== -->

   


    <div class="table-container">

        <table class="appointments-table">


            <thead>

                <tr>

                    <th>
                        Appointment
                    </th>

                    <th>
                        Patient
                    </th>

                    <th>
                        Doctor
                    </th>

                    <th>
                        Department
                    </th>

                    <th>
                        Booked On
                    </th>

                    <th>
                        Appointment Date & Time
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($appointments)): ?>

                <tr>

                    <td
                        colspan="9"
                        style="
                            text-align:center;
                            padding:40px;
                        "
                    >

                        No appointments found.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $appointments
                    as $appointment
                ): ?>


                    <?php

                    $patient_name =
                        trim(
                            ($appointment['patient_first_name'] ?? '')
                            . ' '
                            .
                            ($appointment['patient_last_name'] ?? '')
                        );


                    $doctor_name =
                        $appointment['doctor_name'] ?? '';


                    $initials = '';


                    if (
                        !empty(
                            $appointment['patient_first_name']
                        )
                    ) {

                        $initials .=
                            strtoupper(
                                substr(
                                    $appointment[
                                        'patient_first_name'
                                    ],
                                    0,
                                    1
                                )
                            );
                    }


                    if (
                        !empty(
                            $appointment['patient_last_name']
                        )
                    ) {

                        $initials .=
                            strtoupper(
                                substr(
                                    $appointment[
                                        'patient_last_name'
                                    ],
                                    0,
                                    1
                                )
                            );
                    }


                    if ($initials === '') {
                        $initials = 'P';
                    }

                    ?>


                    <tr>


                        <!-- Appointment -->

                        <td>

                            <strong>

                                <?php
                                echo clean(
                                    $appointment[
                                        'appointment_no'
                                    ]
                                );
                                ?>

                            </strong>


                            <?php
                            if (
                                !empty(
                                    $appointment[
                                        'token_number'
                                    ]
                                )
                            ):
                            ?>

                                <small>

                                    Token #
                                    <?php
                                    echo (int)
                                        $appointment[
                                            'token_number'
                                        ];
                                    ?>

                                </small>

                            <?php endif; ?>

                        </td>


                        <!-- Patient -->

                        <td>

                            <div class="patient-info">


                                <div class="patient-avatar">

                                    <?php
                                    echo clean(
                                        $initials
                                    );
                                    ?>

                                </div>


                                <div>

                                    <strong>

                                        <?php
                                        echo clean(
                                            $patient_name
                                        );
                                        ?>

                                    </strong>


                                    <small>

                                        <?php
                                        echo clean(
                                            $appointment[
                                                'hospital_patient_code'
                                            ]
                                        );
                                        ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <!-- Doctor -->

                        <td>

                            Dr.
                            <?php
                            echo clean(
                                $doctor_name
                            );
                            ?>

                        </td>


                        <!-- Department -->

                        <td>

                            <?php
                            echo clean(
                                $appointment[
                                    'department_name'
                                ]
                                ??
                                'N/A'
                            );
                            ?>

                        </td>


                        <!-- Booked On -->

                        <td class="booked-on-cell">

                            <strong>

                                <?php
                                echo date(
                                    'd M Y',
                                    strtotime(
                                        $appointment['created_at']
                                    )
                                );
                                ?>

                            </strong>

                            <small>

                                <?php
                                echo date(
                                    'h:i A',
                                    strtotime(
                                        $appointment['created_at']
                                    )
                                );
                                ?>

                            </small>

                        </td>


                        <!-- Appointment Date & Time -->

                        <td class="appointment-date-cell">

                            <strong>

                                <?php

                                echo date(
                                    'd M Y',
                                    strtotime(
                                        $appointment[
                                            'appointment_date'
                                        ]
                                    )
                                );

                                ?>

                            </strong>


                            <small>

                                <?php

                                echo date(
                                    'h:i A',
                                    strtotime(
                                        $appointment[
                                            'appointment_time'
                                        ]
                                    )
                                );

                                ?>

                            </small>

                        </td>


                        <!-- Type -->

                        <td>

                            <span
                                class="type
                                <?php

                                echo strtolower(
                                    str_replace(
                                        ' ',
                                        '-',
                                        $appointment[
                                            'appointment_type'
                                        ]
                                    )
                                );

                                ?>"
                            >

                                <?php
                                echo clean(
                                    $appointment[
                                        'appointment_type'
                                    ]
                                );
                                ?>

                            </span>

                        </td>


                        <!-- Status -->

                        <td>

                            <span
                                class="status
                                <?php

                                echo strtolower(
                                    str_replace(
                                        [
                                            ' ',
                                            '-'
                                        ],
                                        [
                                            '-',
                                            ''
                                        ],
                                        $appointment[
                                            'appointment_status'
                                        ]
                                    )
                                );

                                ?>"
                            >

                                <?php
                                echo clean(
                                    $appointment[
                                        'appointment_status'
                                    ]
                                );
                                ?>

                            </span>

                        </td>


                        <!-- Actions -->

                        <td>

                            <div class="action-buttons">


                                <button
                                    type="button"
                                    class="action-btn view-btn"
                                    title="View"
                                    data-id="<?php
                                        echo (int)
                                            $appointment[
                                                'appointment_id'
                                            ];
                                    ?>"
                                >

                                    <i
                                        class="fa-solid fa-eye"
                                    ></i>

                                </button>


                                <button
                                    type="button"
                                    class="action-btn edit-btn"
                                    title="Edit"
                                    data-id="<?php
                                        echo (int)
                                            $appointment[
                                                'appointment_id'
                                            ];
                                    ?>"
                                >

                                    <i
                                        class="fa-solid fa-pen"
                                    ></i>

                                </button>


                                <button
                                    type="button"
                                    class="action-btn delete-btn"
                                    title="Delete"
                                    data-id="<?php
                                        echo (int)
                                            $appointment[
                                                'appointment_id'
                                            ];
                                    ?>"
                                    data-name="<?php
                                        echo clean(
                                            $patient_name
                                        );
                                    ?>"
                                >

                                    <i
                                        class="fa-solid fa-trash"
                                    ></i>

                                </button>


                            </div>


                            <!-- Hidden Data -->

                            <div
                                class="appointment-data"
                                id="appointment-<?php
                                    echo (int)
                                        $appointment[
                                            'appointment_id'
                                        ];
                                ?>"
                                data-id="<?php
                                    echo (int)
                                        $appointment[
                                            'appointment_id'
                                        ];
                                ?>"
                                data-appointment-no="<?php
                                    echo clean(
                                        $appointment[
                                            'appointment_no'
                                        ]
                                    );
                                ?>"
                                data-patient="<?php
                                    echo clean(
                                        $patient_name
                                    );
                                ?>"
                                data-patient-code="<?php
                                    echo clean(
                                        $appointment[
                                            'hospital_patient_code'
                                        ]
                                    );
                                ?>"
                                data-mobile="<?php
                                    echo clean(
                                        $appointment[
                                            'patient_mobile'
                                        ]
                                    );
                                ?>"
                                data-doctor-id="<?php
                                    echo (int)
                                        $appointment[
                                            'doctor_id'
                                        ];
                                ?>"
                                data-doctor="<?php
                                    echo clean(
                                        $doctor_name
                                    );
                                ?>"
                                data-department-id="<?php
                                    echo (int)
                                        $appointment[
                                            'department_id'
                                        ];
                                ?>"
                                data-department="<?php
                                    echo clean(
                                        $appointment[
                                            'department_name'
                                        ] ?? ''
                                    );
                                ?>"
                                data-service-id="<?php
                                    echo (int)
                                        $appointment[
                                            'service_id'
                                        ];
                                ?>"
                                data-service="<?php
                                    echo clean(
                                        $appointment[
                                            'service_name'
                                        ] ?? ''
                                    );
                                ?>"
                                data-date="<?php
                                    echo clean(
                                        $appointment[
                                            'appointment_date'
                                        ]
                                    );
                                ?>"
                                data-created-at="<?php
                                    echo clean(
                                        $appointment['created_at']
                                    );
                                ?>"
                                data-time="<?php
                                    echo clean(
                                        substr(
                                            $appointment[
                                                'appointment_time'
                                            ],
                                            0,
                                            5
                                        )
                                    );
                                ?>"
                                data-type="<?php
                                    echo clean(
                                        $appointment[
                                            'appointment_type'
                                        ]
                                    );
                                ?>"
                                data-mode="<?php
                                    echo clean(
                                        $appointment[
                                            'consultation_mode'
                                        ]
                                    );
                                ?>"
                                data-token="<?php
                                    echo (int)
                                        $appointment[
                                            'token_number'
                                        ];
                                ?>"
                                data-status="<?php
                                    echo clean(
                                        $appointment[
                                            'appointment_status'
                                        ]
                                    );
                                ?>"
                                data-symptoms="<?php
                                    echo clean(
                                        $appointment[
                                            'symptoms'
                                        ] ?? ''
                                    );
                                ?>"
                                data-notes="<?php
                                    echo clean(
                                        $appointment[
                                            'notes'
                                        ] ?? ''
                                    );
                                ?>"
                                data-cancellation="<?php
                                    echo clean(
                                        $appointment[
                                            'cancellation_reason'
                                        ] ?? ''
                                    );
                                ?>"
                            ></div>


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endif; ?>


            </tbody>

        </table>

    </div>


    <!-- =====================================================
         PAGINATION
    ====================================================== -->

    <?php if ($total_pages > 1): ?>

        <div class="pagination">


            <?php if ($page > 1): ?>

                <a
                    href="?<?php

                    $query = $_GET;

                    $query['page'] =
                        $page - 1;

                    echo http_build_query(
                        $query
                    );

                    ?>"
                >

                    <i
                        class="fa-solid
                        fa-chevron-left"
                    ></i>

                </a>

            <?php endif; ?>


            <?php

            $start_page =
                max(
                    1,
                    $page - 2
                );

            $end_page =
                min(
                    $total_pages,
                    $page + 2
                );

            ?>


            <?php for (
                $i = $start_page;
                $i <= $end_page;
                $i++
            ): ?>


                <a
                    href="?<?php

                    $query = $_GET;

                    $query['page'] =
                        $i;

                    echo http_build_query(
                        $query
                    );

                    ?>"
                    class="<?php
                        echo $i === $page
                            ? 'active'
                            : '';
                    ?>"
                >

                    <?php echo $i; ?>

                </a>


            <?php endfor; ?>


            <?php if ($page < $total_pages): ?>

                <a
                    href="?<?php

                    $query = $_GET;

                    $query['page'] =
                        $page + 1;

                    echo http_build_query(
                        $query
                    );

                    ?>"
                >

                    <i
                        class="fa-solid
                        fa-chevron-right"
                    ></i>

                </a>

            <?php endif; ?>


        </div>

    <?php endif; ?>


</main>


<!-- =========================================================
     ADD APPOINTMENT MODAL
========================================================= -->

<div
    class="modal"
    id="appointmentModal"
>


    <div class="modal-content">


        <div class="modal-header">

            <h2>
                Add Appointment
            </h2>


            <button
                class="close-btn"
                type="button"
                data-close="appointmentModal"
            >
                &times;
            </button>

        </div>


        <form
            method="POST"
            id="appointmentForm"
        >


            <input
                type="hidden"
                name="add_appointment"
                value="1"
            >


            <div class="form-grid">


                <!-- Patient -->

                <div class="form-group">

                    <label>
                        Patient
                    </label>


                    <select
                        name="mapping_id"
                        required
                    >

                        <option value="">
                            Select Patient
                        </option>


                        <?php foreach (
                            $patients
                            as $patient
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $patient[
                                            'mapping_id'
                                        ];
                                ?>"
                            >

                                <?php

                                echo clean(
                                    trim(
                                        $patient[
                                            'first_name'
                                        ]
                                        . ' '
                                        .
                                        $patient[
                                            'last_name'
                                        ]
                                    )
                                );

                                ?>

                                -
                                <?php
                                echo clean(
                                    $patient[
                                        'hospital_patient_code'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- Department -->

                <div class="form-group">

                    <label>
                        Department
                    </label>


                    <select
                        id="appointmentDepartment"
                    >

                        <option value="">
                            Select Department
                        </option>


                        <?php foreach (
                            $departments
                            as $department
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $department[
                                            'department_id'
                                        ];
                                ?>"
                            >

                                <?php
                                echo clean(
                                    $department[
                                        'department_name'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- Doctor -->

                <div class="form-group">

                    <label>
                        Doctor
                    </label>


                    <select
                        name="doctor_id"
                        id="appointmentDoctor"
                        required
                    >

                        <option value="">
                            Select Doctor
                        </option>


                        <?php foreach (
                            $doctors
                            as $doctor
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $doctor[
                                            'doctor_id'
                                        ];
                                ?>"
                                data-department="<?php
                                    echo (int)
                                        $doctor[
                                            'department_id'
                                        ];
                                ?>"
                            >

                                Dr.
                                <?php
                                echo clean(
                                    $doctor[
                                        'doctor_name'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- Service -->

                <div class="form-group">

                    <label>
                        Service
                    </label>


                    <select
                        name="service_id"
                        id="appointmentService"
                        required
                    >

                        <option value="">
                            Select Service
                        </option>


                        <?php foreach (
                            $services
                            as $service
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $service[
                                            'service_id'
                                        ];
                                ?>"
                                data-department="<?php
                                    echo (int)
                                        $service[
                                            'department_id'
                                        ];
                                ?>"
                            >

                                <?php
                                echo clean(
                                    $service[
                                        'service_name'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <!-- Date -->

                <div class="form-group">

                    <label>
                        Appointment Date
                    </label>


                    <input
                        type="date"
                        name="appointment_date"
                        required
                    >

                </div>


                <!-- Time -->

                <div class="form-group">

                    <label>
                        Appointment Time
                    </label>


                    <input
                        type="time"
                        name="appointment_time"
                        min="09:30"
                        value="09:30"
                        required
                    >

                </div>


                <!-- Type -->

                <div class="form-group">

                    <label>
                        Appointment Type
                    </label>


                    <select
                        name="appointment_type"
                        required
                    >

                        <option value="Walk-In">
                            Walk-In
                        </option>

                        <option value="Online">
                            Online
                        </option>

                    </select>

                </div>


                <!-- Consultation -->

                <div class="form-group">

                    <label>
                        Consultation Mode
                    </label>


                    <select
                        name="consultation_mode"
                        required
                    >

                        <option value="In-Person">
                            In-Person
                        </option>

                        <option value="Video">
                            Video
                        </option>

                    </select>

                </div>


                <!-- Status -->

                <div class="form-group">

                    <label>
                        Status
                    </label>


                    <select
                        name="appointment_status"
                        required
                    >

                        <option value="Scheduled">
                            Scheduled
                        </option>

                        <option value="Checked-In">
                            Checked-In
                        </option>

                        <option value="In-Progress">
                            In-Progress
                        </option>

                        <option value="Completed">
                            Completed
                        </option>

                        <option value="Cancelled">
                            Cancelled
                        </option>

                        <option value="No-Show">
                            No-Show
                        </option>

                    </select>

                </div>


                <!-- Symptoms -->

                <div class="form-group full-width">

                    <label>
                        Symptoms
                    </label>


                    <textarea
                        name="symptoms"
                        rows="3"
                        placeholder="Enter patient symptoms..."
                    ></textarea>

                </div>


                <!-- Notes -->

                <div class="form-group full-width">

                    <label>
                        Notes
                    </label>


                    <textarea
                        name="notes"
                        rows="3"
                        placeholder="Additional appointment notes..."
                    ></textarea>

                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="cancel-btn"
                    data-close="appointmentModal"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="save-btn"
                >

                    <i
                        class="fa-solid fa-calendar-plus"
                    ></i>

                    Add Appointment

                </button>


            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     VIEW APPOINTMENT MODAL
========================================================= -->

<div
    class="modal"
    id="viewAppointmentModal"
>


    <div class="modal-content">


        <div class="modal-header">

            <h2>
                Appointment Details
            </h2>


            <button
                class="close-btn"
                type="button"
                data-close="viewAppointmentModal"
            >
                &times;
            </button>

        </div>


        <div class="appointment-details">


            <div class="detail-row">

                <span>
                    Appointment No.
                </span>

                <strong
                    id="viewAppointmentNo"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Patient
                </span>

                <strong
                    id="viewPatient"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Patient Code
                </span>

                <strong
                    id="viewPatientCode"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Mobile
                </span>

                <strong
                    id="viewMobile"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Doctor
                </span>

                <strong
                    id="viewDoctor"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Department
                </span>

                <strong
                    id="viewDepartment"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Service
                </span>

                <strong
                    id="viewService"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Booked On
                </span>

                <strong
                    id="viewBookedOn"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Appointment Date
                </span>

                <strong
                    id="viewDate"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Time
                </span>

                <strong
                    id="viewTime"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Type
                </span>

                <strong
                    id="viewType"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Consultation
                </span>

                <strong
                    id="viewMode"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Token
                </span>

                <strong
                    id="viewToken"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Status
                </span>

                <strong
                    id="viewStatus"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Symptoms
                </span>

                <strong
                    id="viewSymptoms"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Notes
                </span>

                <strong
                    id="viewNotes"
                ></strong>

            </div>


            <div class="detail-row">

                <span>
                    Cancellation Reason
                </span>

                <strong
                    id="viewCancellation"
                ></strong>

            </div>


        </div>

    </div>

</div>


<!-- =========================================================
     EDIT APPOINTMENT MODAL
========================================================= -->

<div
    class="modal"
    id="editAppointmentModal"
>


    <div class="modal-content">


        <div class="modal-header">

            <h2>
                Edit Appointment
            </h2>


            <button
                class="close-btn"
                type="button"
                data-close="editAppointmentModal"
            >
                &times;
            </button>

        </div>


        <form method="POST">


            <input
                type="hidden"
                name="update_appointment"
                value="1"
            >


            <input
                type="hidden"
                name="appointment_id"
                id="editAppointmentId"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Patient
                    </label>


                    <input
                        type="text"
                        id="editPatientDisplay"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>
                        Doctor
                    </label>


                    <select
                        name="doctor_id"
                        id="editDoctor"
                        required
                    >

                        <option value="">
                            Select Doctor
                        </option>


                        <?php foreach (
                            $doctors
                            as $doctor
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $doctor[
                                            'doctor_id'
                                        ];
                                ?>"
                                data-department="<?php
                                    echo (int)
                                        $doctor[
                                            'department_id'
                                        ];
                                ?>"
                            >

                                Dr.
                                <?php
                                echo clean(
                                    $doctor[
                                        'doctor_name'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Service
                    </label>


                    <select
                        name="service_id"
                        id="editService"
                        required
                    >

                        <option value="">
                            Select Service
                        </option>


                        <?php foreach (
                            $services
                            as $service
                        ): ?>

                            <option
                                value="<?php
                                    echo (int)
                                        $service[
                                            'service_id'
                                        ];
                                ?>"
                                data-department="<?php
                                    echo (int)
                                        $service[
                                            'department_id'
                                        ];
                                ?>"
                            >

                                <?php
                                echo clean(
                                    $service[
                                        'service_name'
                                    ]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Appointment Date
                    </label>


                    <input
                        type="date"
                        name="appointment_date"
                        id="editDate"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Appointment Time
                    </label>


                    <input
                        type="time"
                        name="appointment_time"
                        id="editTime"
                        min="09:30"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Appointment Type
                    </label>


                    <select
                        name="appointment_type"
                        id="editType"
                        required
                    >

                        <option value="Walk-In">
                            Walk-In
                        </option>

                        <option value="Online">
                            Online
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Consultation Mode
                    </label>


                    <select
                        name="consultation_mode"
                        id="editMode"
                        required
                    >

                        <option value="In-Person">
                            In-Person
                        </option>

                        <option value="Video">
                            Video
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>


                    <select
                        name="appointment_status"
                        id="editStatus"
                        required
                    >

                        <option value="Scheduled">
                            Scheduled
                        </option>

                        <option value="Checked-In">
                            Checked-In
                        </option>

                        <option value="In-Progress">
                            In-Progress
                        </option>

                        <option value="Completed">
                            Completed
                        </option>

                        <option value="Cancelled">
                            Cancelled
                        </option>

                        <option value="No-Show">
                            No-Show
                        </option>

                    </select>

                </div>


                <div class="form-group full-width">

                    <label>
                        Symptoms
                    </label>


                    <textarea
                        name="symptoms"
                        id="editSymptoms"
                        rows="3"
                    ></textarea>

                </div>


                <div class="form-group full-width">

                    <label>
                        Notes
                    </label>


                    <textarea
                        name="notes"
                        id="editNotes"
                        rows="3"
                    ></textarea>

                </div>


                <div class="form-group full-width">

                    <label>
                        Cancellation Reason
                    </label>


                    <textarea
                        name="cancellation_reason"
                        id="editCancellationReason"
                        rows="2"
                        placeholder="Required/used when appointment is cancelled..."
                    ></textarea>

                </div>


            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="cancel-btn"
                    data-close="editAppointmentModal"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="save-btn"
                >

                    <i
                        class="fa-solid fa-save"
                    ></i>

                    Update Appointment

                </button>


            </div>


        </form>

    </div>

</div>


<!-- =========================================================
     DELETE MODAL
========================================================= -->

<div
    class="modal"
    id="deleteAppointmentModal"
>


    <div class="modal-content delete-modal">


        <div class="modal-header">

            <h2>
                Delete Appointment
            </h2>


            <button
                class="close-btn"
                type="button"
                data-close="deleteAppointmentModal"
            >
                &times;
            </button>

        </div>


        <div class="delete-content">


            <i
                class="fa-solid
                fa-triangle-exclamation"
            ></i>


            <p>
                Are you sure you want to delete this appointment?
            </p>


            <strong
                id="deletePatientName"
            ></strong>


        </div>


        <form method="POST">


            <input
                type="hidden"
                name="delete_appointment"
                value="1"
            >


            <input
                type="hidden"
                name="appointment_id"
                id="deleteAppointmentId"
            >


            <div class="modal-footer">


                <button
                    type="button"
                    class="cancel-btn"
                    data-close="deleteAppointmentModal"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="delete-confirm-btn"
                >

                    <i
                        class="fa-solid fa-trash"
                    ></i>

                    Delete

                </button>


            </div>


        </form>

    </div>

</div>


<script src="js/appointments.js"></script>


<script>

/* =========================================================
   ADD APPOINTMENT DEPARTMENT FILTER
========================================================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const department =
            document.getElementById(
                "appointmentDepartment"
            );

        const doctor =
            document.getElementById(
                "appointmentDoctor"
            );

        const service =
            document.getElementById(
                "appointmentService"
            );


        if (department && doctor) {

            department.addEventListener(
                "change",
                function () {

                    const departmentId =
                        this.value;


                    Array.from(
                        doctor.options
                    ).forEach(
                        function (option, index) {

                            if (index === 0) {

                                option.hidden =
                                    false;

                                return;
                            }


                            if (
                                departmentId === ''
                                ||
                                option.dataset.department
                                ===
                                departmentId
                            ) {

                                option.hidden =
                                    false;

                            }

                            else {

                                option.hidden =
                                    true;

                            }

                        }
                    );


                    doctor.value = "";

                }
            );

        }


        if (department && service) {

            department.addEventListener(
                "change",
                function () {

                    const departmentId =
                        this.value;


                    Array.from(
                        service.options
                    ).forEach(
                        function (option, index) {

                            if (index === 0) {

                                option.hidden =
                                    false;

                                return;
                            }


                            if (
                                departmentId === ''
                                ||
                                option.dataset.department
                                ===
                                departmentId
                            ) {

                                option.hidden =
                                    false;

                            }

                            else {

                                option.hidden =
                                    true;

                            }

                        }
                    );


                    service.value = "";

                }
            );

        }


        /* -------------------------------------------------
           Doctor → Department
        ------------------------------------------------- */

        if (doctor && department) {

            doctor.addEventListener(
                "change",
                function () {

                    const selected =
                        doctor.options[
                            doctor.selectedIndex
                        ];


                    if (
                        selected &&
                        selected.dataset.department
                    ) {

                        department.value =
                            selected.dataset.department;


                        department.dispatchEvent(
                            new Event("change")
                        );


                        doctor.value =
                            selected.value;

                    }

                }
            );

        }


        /* -------------------------------------------------
           Add Modal Default Date
        ------------------------------------------------- */

        const modal =
            document.getElementById(
                "appointmentModal"
            );


        if (modal) {

            const dateInput =
                modal.querySelector(
                    'input[name="appointment_date"]'
                );


            const timeInput =
                modal.querySelector(
                    'input[name="appointment_time"]'
                );


            const today =
                new Date()
                    .toISOString()
                    .split("T")[0];


            if (dateInput) {

                dateInput.min =
                    today;

            }


            if (timeInput) {

                timeInput.min =
                    "09:30";

            }

        }

    }
);

</script>


</body>

</html>