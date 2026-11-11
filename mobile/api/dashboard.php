<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';


session_start();

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Kolkata');


/*====================================================
    JSON RESPONSE
====================================================*/

function dashboardResponse(
    bool $success,
    array $data = [],
    string $message = ''
): void {

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*====================================================
    PATIENT SESSION
====================================================*/

if (
    !isset($_SESSION['patient_auth'])
    ||
    !is_array($_SESSION['patient_auth'])
    ||
    (
        $_SESSION['patient_auth']['logged_in']
        ?? false
    ) !== true
) {

    dashboardResponse(
        false,
        [],
        'Patient login required.'
    );
}


$account_id =
    (int)(
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if ($account_id <= 0) {

    dashboardResponse(
        false,
        [],
        'Invalid patient session.'
    );
}


/*====================================================
    CONFIG
====================================================*/

$config_file =
    __DIR__ .
    '/../../includes/config.php';


if (!is_file($config_file)) {

    dashboardResponse(
        false,
        [],
        'Database configuration not found.'
    );
}


require_once $config_file;


if (
    !isset($conn)
    ||
    !($conn instanceof mysqli)
) {

    dashboardResponse(
        false,
        [],
        'Database connection unavailable.'
    );
}


mysqli_set_charset(
    $conn,
    'utf8mb4'
);


/*====================================================
    PATIENT INFORMATION
====================================================*/

$patient = [

    'account_id' => $account_id,

    'name' =>
        (string)(
            $_SESSION['patient_auth']['name']
            ??
            $_SESSION['patient_auth']['patient_name']
            ??
            'Patient'
        )

];


/*====================================================
    GET REGISTERED HOSPITALS
====================================================*/

$sql = "

    SELECT

        phm.mapping_id,
        phm.hospital_id,
        phm.hospital_patient_code,

        hr.hospital_name,
        hr.database_name,
        hr.city,
        hr.state

    FROM patient_hospital_mapping AS phm

    INNER JOIN hospital_registration AS hr

        ON hr.hospital_id =
           phm.hospital_id

    WHERE phm.account_id = ?

      AND phm.patient_status = 'Active'

    ORDER BY
        hr.hospital_name ASC

";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );


if (!$stmt) {

    dashboardResponse(
        false,
        [],
        'Unable to load hospitals.'
    );
}


mysqli_stmt_bind_param(
    $stmt,
    'i',
    $account_id
);


mysqli_stmt_execute(
    $stmt
);


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$hospitals = [];


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    ) {

        $hospitals[] =
            $row;
    }

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    HOSPITAL DATABASE CREDENTIALS
====================================================*/

/*
    IMPORTANT:

    Use the same database configuration
    already used by your project.

    Do NOT change existing website files.
*/

$hospital_host =
    $host;

$hospital_username =
    $username;

$hospital_password =
    $password;


/*====================================================
    UPCOMING APPOINTMENT
====================================================*/

$upcoming =
    null;


$current_datetime =
    date('Y-m-d H:i:s');


foreach (
    $hospitals
    as $hospital
) {

    $mapping_id =
        (int)(
            $hospital['mapping_id']
            ?? 0
        );


    $database_name =
        trim(
            (string)(
                $hospital['database_name']
                ?? ''
            )
        );


    if (
        $mapping_id <= 0
        ||
        $database_name === ''
    ) {
        continue;
    }


    if (
        !preg_match(
            '/^[A-Za-z0-9_]+$/',
            $database_name
        )
    ) {
        continue;
    }


    $hospital_conn =
        db_connect($database_name);


    if (!$hospital_conn) {
        continue;
    }


    mysqli_set_charset(
        $hospital_conn,
        'utf8mb4'
    );


    $appointment_sql = "

        SELECT

            a.appointment_id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.token_number,
            a.appointment_status,
            a.consultation_mode,

            d.doctor_name,
            d.specialization,

            dep.department_name,

            s.service_name,

            COALESCE(
                ds.consultation_fee,
                s.service_fee,
                0
            ) AS consultation_fee

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

        LEFT JOIN doctor_services AS ds

            ON ds.doctor_id =
               a.doctor_id

            AND ds.service_id =
                a.service_id

            AND ds.status = 'Active'

        WHERE a.mapping_id = ?

          AND a.appointment_status IN
          (
              'Scheduled',
              'Checked-In',
              'In-Progress'
          )

          AND CONCAT(
              a.appointment_date,
              ' ',
              a.appointment_time
          ) >= ?

        ORDER BY
            a.appointment_date ASC,
            a.appointment_time ASC

        LIMIT 1

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
        'is',
        $mapping_id,
        $current_datetime
    );


    mysqli_stmt_execute(
        $appointment_stmt
    );


    $appointment_result =
        mysqli_stmt_get_result(
            $appointment_stmt
        );


    if ($appointment_result) {

        $row =
            mysqli_fetch_assoc(
                $appointment_result
            );


        if ($row) {

            $row['hospital_id'] =
                (int)(
                    $hospital['hospital_id']
                    ?? 0
                );


            $row['hospital_name'] =
                (string)(
                    $hospital['hospital_name']
                    ?? ''
                );


            $row['city'] =
                (string)(
                    $hospital['city']
                    ?? ''
                );


            $row['state'] =
                (string)(
                    $hospital['state']
                    ?? ''
                );


            $upcoming =
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


    if ($upcoming !== null) {
        break;
    }
}


/*====================================================
    RESPONSE
====================================================*/

dashboardResponse(
    true,
    [

        'patient' =>
            $patient,

        'hospital_count' =>
            count($hospitals),

        'upcoming_appointment' =>
            $upcoming

    ]
);