<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';


session_start();


/*==================================================
    CHECK PATIENT LOGIN
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
    GET LOGGED-IN PATIENT
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
    CONFIG
==================================================*/

$config_file =
    dirname(__DIR__) .
    '/includes/config.php';


if (!is_file($config_file)) {

    die(
        'Database configuration file not found.'
    );

}


require_once $config_file;


/*==================================================
    DATABASE CHECK
==================================================*/

if (
    !isset($conn) ||
    !($conn instanceof mysqli)
) {

    die(
        'Database connection is not available.'
    );

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
    HOSPITAL DATABASE CREDENTIALS
==================================================*/

$hospital_host =
    DB_HOST;

$hospital_username =
    DB_USER;

$hospital_password =
    DB_PASS;


/*==================================================
    GET PATIENT HOSPITAL MAPPINGS
==================================================*/

$mapping_sql = "

    SELECT

        mapping_id,
        hospital_id,
        hospital_patient_code

    FROM patient_hospital_mapping

    WHERE account_id = ?

      AND patient_status = 'Active'

    ORDER BY mapping_id DESC

";


$mapping_stmt =
    mysqli_prepare(
        $conn,
        $mapping_sql
    );


$mappings = [];


if ($mapping_stmt) {

    mysqli_stmt_bind_param(
        $mapping_stmt,
        'i',
        $account_id
    );

    mysqli_stmt_execute(
        $mapping_stmt
    );


    $result =
        mysqli_stmt_get_result(
            $mapping_stmt
        );


    if ($result) {

        while (
            $row =
            mysqli_fetch_assoc($result)
        ) {

            $mappings[] =
                $row;

        }


        mysqli_free_result(
            $result
        );

    }


    mysqli_stmt_close(
        $mapping_stmt
    );

}


/*==================================================
    MESSAGE
==================================================*/

$message = '';

$message_type = '';



/*==================================================
    CANCEL APPOINTMENT
==================================================*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    (
        $_POST['action'] ?? ''
    ) === 'cancel_appointment'
) {

    $cancel_appointment_id =
        (int)(
            $_POST['appointment_id']
            ?? 0
        );

    $cancel_mapping_id =
        (int)(
            $_POST['mapping_id']
            ?? 0
        );


    if (
        $cancel_appointment_id <= 0 ||
        $cancel_mapping_id <= 0
    ) {

        $message =
            'Invalid appointment information.';

        $message_type =
            'error';

    } else {

        $mapping_found = false;

        $cancel_hospital_id = 0;


        /*
        |--------------------------------------------------------------------------
        | Verify that the mapping belongs to the
        | currently logged-in patient.
        |--------------------------------------------------------------------------
        */

        foreach (
            $mappings
            as $mapping
        ) {

            if (
                (int)$mapping['mapping_id']
                ===
                $cancel_mapping_id
            ) {

                $mapping_found = true;

                $cancel_hospital_id =
                    (int)$mapping['hospital_id'];

                break;

            }

        }


        if (
            !$mapping_found ||
            $cancel_hospital_id <= 0
        ) {

            $message =
                'You are not authorized to cancel this appointment.';

            $message_type =
                'error';

        } else {


            /*================================================
                GET HOSPITAL DATABASE
            ================================================*/

            $hospital_sql = "

                SELECT

                    hospital_name,
                    database_name

                FROM hospital_registration

                WHERE hospital_id = ?

                LIMIT 1

            ";


            $hospital_stmt =
                mysqli_prepare(
                    $conn,
                    $hospital_sql
                );


            if (!$hospital_stmt) {

                $message =
                    'Unable to process the cancellation.';

                $message_type =
                    'error';

            } else {

                mysqli_stmt_bind_param(
                    $hospital_stmt,
                    'i',
                    $cancel_hospital_id
                );


                mysqli_stmt_execute(
                    $hospital_stmt
                );


                $hospital_result =
                    mysqli_stmt_get_result(
                        $hospital_stmt
                    );


                $hospital =
                    null;


                if (
                    $hospital_result &&
                    mysqli_num_rows(
                        $hospital_result
                    ) > 0
                ) {

                    $hospital =
                        mysqli_fetch_assoc(
                            $hospital_result
                        );

                }


                if ($hospital_result) {

                    mysqli_free_result(
                        $hospital_result
                    );

                }


                mysqli_stmt_close(
                    $hospital_stmt
                );


                if (!$hospital) {

                    $message =
                        'Hospital information could not be found.';

                    $message_type =
                        'error';

                } else {


                    $database_name =
                        trim(
                            (string)(
                                $hospital[
                                    'database_name'
                                ]
                                ?? ''
                            )
                        );


                    /*========================================
                        VALID DATABASE NAME
                    ========================================*/

                    if (
                        $database_name === '' ||
                        !preg_match(
                            '/^[A-Za-z0-9_]+$/',
                            $database_name
                        )
                    ) {

                        $message =
                            'Invalid hospital database.';

                        $message_type =
                            'error';

                    } else {


                        /*====================================
                            CONNECT HOSPITAL DATABASE
                        ====================================*/

                        $hospital_conn =
                            db_connect($database_name);


                        if (!$hospital_conn) {

                            $message =
                                'Unable to connect to the hospital database.';

                            $message_type =
                                'error';

                        } else {

                            mysqli_set_charset(
                                $hospital_conn,
                                'utf8mb4'
                            );


                            /*================================
                                GET APPOINTMENT
                            =================================*/

                            $check_sql = "

                                SELECT

                                    appointment_id,
                                    mapping_id,
                                    appointment_date,
                                    appointment_time,
                                    appointment_status

                                FROM appointments

                                WHERE appointment_id = ?

                                  AND mapping_id = ?

                                LIMIT 1

                            ";


                            $check_stmt =
                                mysqli_prepare(
                                    $hospital_conn,
                                    $check_sql
                                );


                            if (!$check_stmt) {

                                $message =
                                    'Unable to verify the appointment.';

                                $message_type =
                                    'error';

                            } else {

                                mysqli_stmt_bind_param(
                                    $check_stmt,
                                    'ii',
                                    $cancel_appointment_id,
                                    $cancel_mapping_id
                                );


                                mysqli_stmt_execute(
                                    $check_stmt
                                );


                                $check_result =
                                    mysqli_stmt_get_result(
                                        $check_stmt
                                    );


                                $appointment_to_cancel =
                                    null;


                                if (
                                    $check_result &&
                                    mysqli_num_rows(
                                        $check_result
                                    ) > 0
                                ) {

                                    $appointment_to_cancel =
                                        mysqli_fetch_assoc(
                                            $check_result
                                        );

                                }


                                if ($check_result) {

                                    mysqli_free_result(
                                        $check_result
                                    );

                                }


                                mysqli_stmt_close(
                                    $check_stmt
                                );


                                if (
                                    !$appointment_to_cancel
                                ) {

                                    $message =
                                        'Appointment not found.';

                                    $message_type =
                                        'error';

                                } else {


                                    $current_status =
                                        (string)(
                                            $appointment_to_cancel[
                                                'appointment_status'
                                            ]
                                            ?? ''
                                        );


                                    /*============================
                                        ONLY SCHEDULED APPOINTMENTS
                                        CAN BE CANCELLED
                                    ============================*/

                                    if (
                                        strcasecmp(
                                            $current_status,
                                            'Scheduled'
                                        ) !== 0
                                    ) {

                                        $message =
                                            'Only scheduled appointments can be cancelled.';

                                        $message_type =
                                            'error';

                                    } else {


                                        /*========================
                                            CHECK PAST APPOINTMENT
                                        ========================*/

                                        $appointment_date =
                                            (string)(
                                                $appointment_to_cancel[
                                                    'appointment_date'
                                                ]
                                                ?? ''
                                            );

                                        $appointment_time =
                                            (string)(
                                                $appointment_to_cancel[
                                                    'appointment_time'
                                                ]
                                                ?? ''
                                            );


                                        $appointment_datetime =
                                            null;


                                        if (
                                            $appointment_date !== '' &&
                                            $appointment_time !== ''
                                        ) {

                                            $appointment_datetime =
                                                DateTime::createFromFormat(
                                                    'Y-m-d H:i:s',
                                                    $appointment_date .
                                                    ' ' .
                                                    $appointment_time
                                                );


                                            if (
                                                !$appointment_datetime
                                            ) {

                                                $appointment_datetime =
                                                    DateTime::createFromFormat(
                                                        'Y-m-d H:i',
                                                        $appointment_date .
                                                        ' ' .
                                                        $appointment_time
                                                    );

                                            }

                                        }


                                        if (
                                            $appointment_datetime instanceof DateTime &&
                                            $appointment_datetime < new DateTime()
                                        ) {

                                            $message =
                                                'This appointment has already passed and cannot be cancelled.';

                                            $message_type =
                                                'error';

                                        } else {


                                            /*====================
                                                CANCEL
                                            ====================*/

                                            $cancel_sql = "

                                                UPDATE appointments

                                                SET appointment_status = 'Cancelled'

                                                WHERE appointment_id = ?

                                                  AND mapping_id = ?

                                                  AND appointment_status = 'Scheduled'

                                            ";


                                            $cancel_stmt =
                                                mysqli_prepare(
                                                    $hospital_conn,
                                                    $cancel_sql
                                                );


                                            if (!$cancel_stmt) {

                                                $message =
                                                    'Unable to cancel the appointment.';

                                                $message_type =
                                                    'error';

                                            } else {

                                                mysqli_stmt_bind_param(
                                                    $cancel_stmt,
                                                    'ii',
                                                    $cancel_appointment_id,
                                                    $cancel_mapping_id
                                                );


                                                if (
                                                    mysqli_stmt_execute(
                                                        $cancel_stmt
                                                    )
                                                ) {

                                                    if (
                                                        mysqli_stmt_affected_rows(
                                                            $cancel_stmt
                                                        ) > 0
                                                    ) {

                                                        $message =
                                                            'Appointment cancelled successfully.';

                                                        $message_type =
                                                            'success';

                                                    } else {

                                                        $message =
                                                            'The appointment could not be cancelled. It may have already been changed.';

                                                        $message_type =
                                                            'error';

                                                    }

                                                } else {

                                                    $message =
                                                        'Unable to cancel the appointment.';

                                                    $message_type =
                                                        'error';

                                                }


                                                mysqli_stmt_close(
                                                    $cancel_stmt
                                                );

                                            }

                                        }

                                    }

                                }

                            }

                            mysqli_close(
                                $hospital_conn
                            );

                        }

                    }

                }

            }

        }

    }

}


/*==================================================
    APPOINTMENTS ARRAY
==================================================*/

$appointments = [];


/*==================================================
    LOAD HOSPITAL REGISTRATIONS
==================================================*/

foreach (
    $mappings
    as $mapping
) {


    $mapping_id =
        (int)$mapping['mapping_id'];

    $hospital_id =
        (int)$mapping['hospital_id'];

    $patient_code =
        (string)(
            $mapping['hospital_patient_code']
            ?? ''
        );


    /*================================================
        GET HOSPITAL DATABASE
    ================================================*/

    $hospital_sql = "

        SELECT

            hospital_name,
            database_name

        FROM hospital_registration

        WHERE hospital_id = ?

        LIMIT 1

    ";


    $hospital_stmt =
        mysqli_prepare(
            $conn,
            $hospital_sql
        );


    if (!$hospital_stmt) {
        continue;
    }


    mysqli_stmt_bind_param(
        $hospital_stmt,
        'i',
        $hospital_id
    );


    mysqli_stmt_execute(
        $hospital_stmt
    );


    $hospital_result =
        mysqli_stmt_get_result(
            $hospital_stmt
        );


    if (
        !$hospital_result ||
        mysqli_num_rows(
            $hospital_result
        ) === 0
    ) {

        if ($hospital_result) {

            mysqli_free_result(
                $hospital_result
            );

        }

        mysqli_stmt_close(
            $hospital_stmt
        );

        continue;
    }


    $hospital =
        mysqli_fetch_assoc(
            $hospital_result
        );


    mysqli_free_result(
        $hospital_result
    );

    mysqli_stmt_close(
        $hospital_stmt
    );


    $hospital_name =
        (string)(
            $hospital['hospital_name']
            ?? ''
        );


    $database_name =
        trim(
            (string)(
                $hospital['database_name']
                ?? ''
            )
        );


    /*================================================
        VALID DATABASE NAME
    ================================================*/

    if (
        $database_name === '' ||
        !preg_match(
            '/^[A-Za-z0-9_]+$/',
            $database_name
        )
    ) {

        continue;
    }


    /*================================================
        CONNECT HOSPITAL DATABASE
    ================================================*/

    $hospital_conn =
        db_connect($database_name);


    if (!$hospital_conn) {
        continue;
    }


    mysqli_set_charset(
        $hospital_conn,
        'utf8mb4'
    );


    /*================================================
        AUTO MARK MISSED APPOINTMENTS AS NO-SHOW
    ================================================*/

    /*
    |--------------------------------------------------------------------------
    | A scheduled appointment becomes No-Show automatically after its
    | appointment date/time has passed.
    |
    | Completed and Cancelled appointments are never changed here.
    |--------------------------------------------------------------------------
    */

    $noshow_sql = "
        UPDATE appointments
        SET appointment_status = 'No-Show'
        WHERE mapping_id = ?
          AND appointment_status = 'Scheduled'
          AND TIMESTAMP(appointment_date, appointment_time) < NOW()
    ";

    $noshow_stmt = mysqli_prepare(
        $hospital_conn,
        $noshow_sql
    );

    if ($noshow_stmt) {
        mysqli_stmt_bind_param(
            $noshow_stmt,
            'i',
            $mapping_id
        );
        mysqli_stmt_execute($noshow_stmt);
        mysqli_stmt_close($noshow_stmt);
    }


    /*================================================
        GET APPOINTMENTS
    ================================================*/

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

            a.symptoms,

            a.notes,

            d.doctor_name,

            d.specialization,

            s.service_name

        FROM appointments AS a

        LEFT JOIN doctors AS d

            ON d.doctor_id =
               a.doctor_id

        LEFT JOIN services AS s

            ON s.service_id =
               a.service_id

        WHERE

            a.mapping_id = ?

        ORDER BY

            a.appointment_date DESC,

            a.appointment_time DESC

    ";


    $appointment_stmt =
        mysqli_prepare(
            $hospital_conn,
            $appointment_sql
        );


    if (!$appointment_stmt) {

        mysqli_close(
            $hospital_conn
        );

        continue;
    }


    mysqli_stmt_bind_param(
        $appointment_stmt,
        'i',
        $mapping_id
    );


    if (
        !mysqli_stmt_execute(
            $appointment_stmt
        )
    ) {

        mysqli_stmt_close(
            $appointment_stmt
        );

        mysqli_close(
            $hospital_conn
        );

        continue;
    }


    $appointment_result =
        mysqli_stmt_get_result(
            $appointment_stmt
        );


    if ($appointment_result) {

        while (
            $row =
            mysqli_fetch_assoc(
                $appointment_result
            )
        ) {

            $row['hospital_name'] =
                $hospital_name;

            $row['hospital_patient_code'] =
                $patient_code;

            $appointments[] =
                $row;

        }


        mysqli_free_result(
            $appointment_result
        );

    }


    mysqli_stmt_close(
        $appointment_stmt
    );


    mysqli_close(
        $hospital_conn
    );

}


/*==================================================
    SORT APPOINTMENTS
==================================================*/

usort(
    $appointments,
    function (
        array $a,
        array $b
    ): int {

        $date_a =
            ($a['appointment_date'] ?? '')
            . ' '
            .
            ($a['appointment_time'] ?? '');

        $date_b =
            ($b['appointment_date'] ?? '')
            . ' '
            .
            ($b['appointment_time'] ?? '');

        return strcmp(
            $date_b,
            $date_a
        );

    }
);


/*==================================================
    ESCAPE FUNCTION
==================================================*/

function e(
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
        My Appointments
    </title>

    <link
        rel="stylesheet"
        href="css/my_appointments.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="mobile-app">


    <!-- HEADER -->

    <header class="page-header">

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
                My Appointments
            </h1>

        </div>

    </header>


    <!-- PATIENT -->

    <section class="patient-banner">

        <div class="patient-avatar">

            <?= e(
                strtoupper(
                    substr(
                        $first_name,
                        0,
                        1
                    )
                )
            ); ?>

        </div>


        <div>

            <span>
                Logged in as
            </span>

            <strong>
                <?= e($full_name); ?>
            </strong>

        </div>

    </section>


    <!-- MESSAGE -->

    <?php if (
        $message !== ''
    ): ?>

        <div
            class="appointment-message <?= e($message_type); ?>"
            style="
                margin: 10px 0 16px;
                padding: 12px 14px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                text-align: center;
                <?= $message_type === 'success'
                    ? 'background:#e9f9f0;color:#16834b;border:1px solid #c9efd9;'
                    : 'background:#fff0f0;color:#c0392b;border:1px solid #f3cccc;'; ?>
            "
        >

            <?= e($message); ?>

        </div>

    <?php endif; ?>


    <!-- APPOINTMENTS -->

    <main class="appointments-container">


        <?php if (
            empty($appointments)
        ): ?>


            <div class="empty-card">

                <div class="empty-icon">
                    📅
                </div>

                <h2>
                    No Appointments
                </h2>

                <p>
                    You don't have any appointments yet.
                </p>


                <a
                    href="hospitals.php"
                    class="book-button"
                >
                    Book Appointment
                </a>

            </div>


        <?php else: ?>


            <div class="appointment-count">

                <?= count($appointments); ?>

                Appointment(s)

            </div>


            <?php foreach (
                $appointments
                as $appointment
            ): ?>


                <?php

                $date =
                    (string)(
                        $appointment[
                            'appointment_date'
                        ] ?? ''
                    );


                $time =
                    (string)(
                        $appointment[
                            'appointment_time'
                        ] ?? ''
                    );


                $date_object =
                    DateTime::createFromFormat(
                        'Y-m-d',
                        $date
                    );


                $display_date =
                    $date_object
                    ?
                    $date_object->format(
                        'd M Y'
                    )
                    :
                    $date;


                $time_object =
                    DateTime::createFromFormat(
                        'H:i:s',
                        $time
                    );


                if (!$time_object) {

                    $time_object =
                        DateTime::createFromFormat(
                            'H:i',
                            $time
                        );

                }


                $display_time =
                    $time_object
                    ?
                    $time_object->format(
                        'h:i A'
                    )
                    :
                    $time;


                $status =
                    (string)(
                        $appointment[
                            'appointment_status'
                        ] ?? 'Scheduled'
                    );


                /*==================================================
                    CHECK PAST APPOINTMENT
                ==================================================*/

                $appointment_datetime =
                    null;


                if (
                    $date !== '' &&
                    $time !== ''
                ) {

                    $appointment_datetime =
                        DateTime::createFromFormat(
                            'Y-m-d H:i:s',
                            $date . ' ' . $time
                        );


                    if (
                        !$appointment_datetime
                    ) {

                        $appointment_datetime =
                            DateTime::createFromFormat(
                                'Y-m-d H:i',
                                $date . ' ' . $time
                            );

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | The database is now the source of truth.
                | Past scheduled appointments are converted to No-Show by the
                | automatic update above.
                |--------------------------------------------------------------------------
                */

                $is_past_appointment =
                    strcasecmp(
                        $status,
                        'No-Show'
                    ) === 0;


                /*
                |--------------------------------------------------------------------------
                | Can this appointment be cancelled?
                |--------------------------------------------------------------------------
                */

                $can_cancel =
                    strcasecmp(
                        $status,
                        'Scheduled'
                    ) === 0
                    &&
                    !$is_past_appointment;


                ?>


                <article
                    class="appointment-card <?= $is_past_appointment ? 'past-appointment' : ''; ?>"
                >


                    <div class="appointment-card-top">


                        <div class="doctor-icon">
                            👨‍⚕️
                        </div>


                        <div class="doctor-info">

                            <span>
                                DOCTOR
                            </span>

                            <h2>

                                <?= e(
                                    (string)(
                                        $appointment[
                                            'doctor_name'
                                        ] ?? 'Doctor'
                                    )
                                ); ?>

                            </h2>

                            <?php if (
                                !empty(
                                    $appointment[
                                        'specialization'
                                    ]
                                )
                            ): ?>

                                <p>

                                    <?= e(
                                        (string)(
                                            $appointment[
                                                'specialization'
                                            ]
                                        )
                                    ); ?>

                                </p>

                            <?php endif; ?>

                        </div>


                        <div
                            class="status <?= $is_past_appointment ? 'past-status' : ''; ?>"
                        >

                            <?= e($status); ?>

                        </div>


                    </div>


                    <div class="hospital-name">

                        🏥

                        <?= e(
                            (string)(
                                $appointment[
                                    'hospital_name'
                                ] ?? ''
                            )
                        ); ?>

                    </div>


                    <div class="appointment-details">


                        <div>

                            <span>
                                DATE
                            </span>

                            <strong>
                                <?= e(
                                    $display_date
                                ); ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                TIME
                            </span>

                            <strong>
                                <?= e(
                                    $display_time
                                ); ?>
                            </strong>

                        </div>


                        <div>

                            <span>
                                TOKEN
                            </span>

                            <strong>

                                #<?= (int)(
                                    $appointment[
                                        'token_number'
                                    ] ?? 0
                                ); ?>

                            </strong>

                        </div>

                    </div>


                    <div class="appointment-footer">


                        <span>

                            Appointment No:

                            <strong>

                                <?= e(
                                    (string)(
                                        $appointment[
                                            'appointment_no'
                                        ] ?? ''
                                    )
                                ); ?>

                            </strong>

                        </span>


                        <?php if (
                            !empty(
                                $appointment[
                                    'service_name'
                                ]
                            )
                        ): ?>

                            <span>

                                <?= e(
                                    (string)(
                                        $appointment[
                                            'service_name'
                                        ]
                                    )
                                ); ?>

                            </span>

                        <?php endif; ?>


                    </div>


                    <!-- CANCEL BUTTON -->

                    <?php if (
                        $can_cancel
                    ): ?>

                        <div
                            style="
                                margin-top:12px;
                                padding-top:12px;
                                border-top:1px solid #edf0f6;
                            "
                        >

                            <form
                                method="POST"
                                onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="cancel_appointment"
                                >

                                <input
                                    type="hidden"
                                    name="appointment_id"
                                    value="<?= (int)(
                                        $appointment[
                                            'appointment_id'
                                        ] ?? 0
                                    ); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="mapping_id"
                                    value="<?= (int)(
                                        $appointment[
                                            'mapping_id'
                                        ] ?? 0
                                    ); ?>"
                                >

                                <button
                                    type="submit"
                                    style="
                                        width:100%;
                                        border:none;
                                        border-radius:10px;
                                        padding:10px 12px;
                                        background:#fff0f0;
                                        color:#d33b3b;
                                        font-size:12px;
                                        font-weight:700;
                                        cursor:pointer;
                                    "
                                >
                                    Cancel Appointment
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>


                </article>


            <?php endforeach; ?>


        <?php endif; ?>


    </main>


    <!-- BOTTOM NAV -->

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
            class="nav-link active"
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


</body>

</html>