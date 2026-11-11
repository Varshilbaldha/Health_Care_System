<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| CONFIRM APPOINTMENT PROCESS
|--------------------------------------------------------------------------
| Appointment Booking - Final Step
|
| This file:
|
| 1. Validates patient session
| 2. Gets doctor/service
| 3. Verifies appointment time
| 4. Prevents duplicate booking
| 5. Generates appointment number
| 6. Generates token number
| 7. Saves symptoms + notes
| 8. Creates appointment
| 9. Redirects to success page
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../includes/auth_check.php';


/*====================================================
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
) {
    $_SESSION['error'] =
        "Invalid appointment confirmation request.";

    header(
        "Location: confirm_appointment.php"
    );

    exit;
}


/*====================================================
    CHECK BOOKING SESSION
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
        "Your appointment session has expired. Please start again.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET BOOKING DATA
====================================================*/

$booking =
    $_SESSION['appointment_booking'];


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
    (string) (
        $booking['hospital_name']
        ?? ''
    );


$hospital_patient_code =
    (string) (
        $booking['hospital_patient_code']
        ?? ''
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
    (string) (
        $booking['department_name']
        ?? ''
    );


$doctor_id =
    (int) (
        $booking['doctor_id']
        ?? 0
    );


$doctor_name =
    (string) (
        $booking['doctor_name']
        ?? ''
    );


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


$consultation_mode =
    (string) (
        $booking['consultation_mode']
        ?? 'In-Person'
    );


/*====================================================
    GET PATIENT INPUT
====================================================*/

$symptoms =
    trim(
        (string) (
            $_POST['symptoms']
            ?? ''
        )
    );


$notes =
    trim(
        (string) (
            $_POST['notes']
            ?? ''
        )
    );


/*====================================================
    LIMIT INPUT
====================================================*/

$symptoms =
    mb_substr(
        $symptoms,
        0,
        2000
    );


$notes =
    mb_substr(
        $notes,
        0,
        2000
    );


/*====================================================
    VALIDATE BOOKING DATA
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
    ||
    $appointment_date === ''
    ||
    $appointment_time === ''
) {
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Your appointment information is incomplete.";

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
    $_SESSION['error'] =
        "Invalid hospital database configuration.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    VALIDATE DATE
====================================================*/

$date_object =
    DateTime::createFromFormat(
        'Y-m-d',
        $appointment_date
    );


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
    VALIDATE TIME
====================================================*/

$time_object =
    DateTime::createFromFormat(
        'H:i:s',
        $appointment_time
    );


if (!$time_object) {
    $time_object =
        DateTime::createFromFormat(
            'H:i',
            $appointment_time
        );
}


if (!$time_object) {
    $_SESSION['error'] =
        "Invalid appointment time.";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn) {
    die(
        "Hospital Database Connection Failed: " .
        mysqli_connect_error()
    );
}


mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*====================================================
    START TRANSACTION
====================================================*/

mysqli_begin_transaction(
    $hospital_conn
);


try {

    /*================================================
        VERIFY DOCTOR
    ================================================*/

    $query = "
        SELECT
            doctor_id,
            department_id,
            doctor_name,
            specialization,
            status
        FROM doctors
        WHERE doctor_id = ?
          AND department_id = ?
          AND status = 'Active'
        LIMIT 1
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $doctor_id,
        $department_id
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $result =
        mysqli_stmt_get_result(
            $stmt
        );


    $doctor =
        $result
        ? mysqli_fetch_assoc($result)
        : null;


    if ($result) {
        mysqli_free_result(
            $result
        );
    }


    mysqli_stmt_close(
        $stmt
    );


    if (!$doctor) {
        throw new Exception(
            "Selected doctor is no longer available."
        );
    }


    /*================================================
        FIND DOCTOR SERVICE
    ================================================*/

    $query = "
        SELECT
            ds.doctor_service_id,
            ds.service_id,
            ds.consultation_fee,
            ds.consultation_duration,
            ds.status AS doctor_service_status,

            s.service_name,
            s.service_code,
            s.service_type,
            s.consultation_mode AS service_consultation_mode,
            s.duration_minutes,
            s.service_fee,
            s.status AS service_status

        FROM doctor_services ds

        INNER JOIN services s
            ON s.service_id = ds.service_id

        WHERE ds.doctor_id = ?
          AND ds.status = 'Active'
          AND s.department_id = ?
          AND s.status = 'Active'

        ORDER BY ds.doctor_service_id ASC

        LIMIT 1
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $doctor_id,
        $department_id
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $result =
        mysqli_stmt_get_result(
            $stmt
        );


    $service =
        $result
        ? mysqli_fetch_assoc($result)
        : null;


    if ($result) {
        mysqli_free_result(
            $result
        );
    }


    mysqli_stmt_close(
        $stmt
    );


    /*================================================
        SERVICE NOT AVAILABLE
    ================================================*/

    if (!$service) {
        throw new Exception(
            "This service is currently not available for the selected doctor. Please select another doctor."
        );
    }


    /*================================================
        GET SERVICE ID
    ================================================*/

    $service_id =
        (int) 
        $service['service_id'];


    if ($service_id <= 0) {
        throw new Exception(
            "The selected doctor's service configuration is invalid."
        );
    }


    /*================================================
        CHECK APPOINTMENT TIME AGAIN
    ================================================*/

    /*
        Another patient may have booked the same
        time after select_time.php.

        Therefore we verify again before INSERT.
    */

    $query = "
        SELECT
            appointment_id,
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
        ORDER BY appointment_time ASC
        FOR UPDATE
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $doctor_id,
        $appointment_date
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $result =
        mysqli_stmt_get_result(
            $stmt
        );


    $existing_appointments = [];


    if ($result) {
        while (
            $row =
            mysqli_fetch_assoc(
                $result
            )
        ) {
            $existing_appointments[] =
                $row;
        }


        mysqli_free_result(
            $result
        );
    }


    mysqli_stmt_close(
        $stmt
    );


    /*================================================
        CHECK 10 MINUTE GAP
    ================================================*/

    $requested_datetime =
        DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $appointment_date .
            ' ' .
            $appointment_time
        );


    if (!$requested_datetime) {
        throw new Exception(
            "Invalid appointment date or time."
        );
    }


    foreach (
        $existing_appointments
        as $existing
    ) {

        $existing_time =
            (string) 
            $existing['appointment_time'];


        $existing_datetime =
            DateTime::createFromFormat(
                'Y-m-d H:i:s',
                $appointment_date .
                ' ' .
                $existing_time
            );


        if (!$existing_datetime) {
            continue;
        }


        $difference =
            abs(
                $requested_datetime->getTimestamp()
                -
                $existing_datetime->getTimestamp()
            );


        /*
            Minimum gap = 10 minutes.
        */

        if (
            $difference < 600
        ) {
            throw new Exception(
                "This appointment time was just booked or is too close to another appointment. Please select the date again."
            );
        }
    }


    /*================================================
        GENERATE TOKEN NUMBER
    ================================================*/

    $query = "
        SELECT
            COALESCE(
                MAX(token_number),
                0
            ) + 1 AS next_token

        FROM appointments

        WHERE doctor_id = ?
          AND appointment_date = ?

          AND appointment_status IN
          (
              'Scheduled',
              'Checked-In',
              'In-Progress'
          )
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $doctor_id,
        $appointment_date
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $result =
        mysqli_stmt_get_result(
            $stmt
        );


    $token_number = 1;


    if ($result) {
        $token_row =
            mysqli_fetch_assoc(
                $result
            );


        if ($token_row) {
            $token_number =
                (int) 
                (
                    $token_row[
                        'next_token'
                    ]
                    ?? 1
                );
        }


        mysqli_free_result(
            $result
        );
    }


    mysqli_stmt_close(
        $stmt
    );


    /*================================================
        GENERATE APPOINTMENT NUMBER
    ================================================*/

    $appointment_no =
        'APT-' .
        date(
            'Ymd',
            strtotime(
                $appointment_date
            )
        ) .
        '-' .
        $doctor_id .
        '-' .
        str_pad(
            (string) 
            $token_number,
            3,
            '0',
            STR_PAD_LEFT
        ) .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(3)
            )
        );


    /*================================================
        APPOINTMENT TYPE
    ================================================*/

    /*
        Online = appointment booked through
        Patient Portal.

        Consultation mode can still be In-Person.
    */

    $appointment_type =
        'Online';


    /*================================================
        CONSULTATION MODE
    ================================================*/

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


    /*================================================
        INSERT APPOINTMENT
    ================================================*/

    $query = "
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
            token_number,
            appointment_status,
            symptoms,
            notes
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
            ?,
            'Scheduled',
            ?,
            ?
        )
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "iiisssssiss",
        $mapping_id,
        $doctor_id,
        $service_id,
        $appointment_no,
        $appointment_date,
        $appointment_time,
        $appointment_type,
        $consultation_mode,
        $token_number,
        $symptoms,
        $notes
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    ) {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $appointment_id =
        mysqli_insert_id(
            $hospital_conn
        );


    mysqli_stmt_close(
        $stmt
    );


    if ($appointment_id <= 0) {
        throw new Exception(
            "Appointment could not be created."
        );
    }


    /*================================================
        COMMIT
    ================================================*/

    mysqli_commit(
        $hospital_conn
    );


    /*================================================
        SAVE SUCCESS DATA
    ================================================*/

    $_SESSION[
        'completed_appointment'
    ] =
        [
            'appointment_id'
            => $appointment_id,

            'appointment_no'
            => $appointment_no,

            'token_number'
            => $token_number,

            'hospital_name'
            => $hospital_name,

            'hospital_patient_code'
            => $hospital_patient_code,

            'department_name'
            => $department_name,

            'doctor_name'
            => $doctor_name,

            'service_name'
            => (string) 
                $service[
                    'service_name'
                ],

            'appointment_date'
            => $appointment_date,

            'appointment_time'
            => $appointment_time,

            'appointment_type'
            => $appointment_type,

            'consultation_mode'
            => $consultation_mode,

            'consultation_fee'
            => (float) 
                (
                    $service[
                        'consultation_fee'
                    ]
                    ??
                    $service[
                        'service_fee'
                    ]
                    ??
                    0
                )
        ];


    /*================================================
        CLEAR BOOKING SESSION
    ================================================*/

    unset(
        $_SESSION[
            'appointment_booking'
        ]
    );


    mysqli_close(
        $hospital_conn
    );


    /*================================================
        SUCCESS PAGE
    ================================================*/

    header(
        "Location: appointment_success.php"
    );

    exit;

} catch (
    Throwable $e
) {

    /*================================================
        ROLLBACK
    ================================================*/

    mysqli_rollback(
        $hospital_conn
    );


    mysqli_close(
        $hospital_conn
    );


    $_SESSION['error'] =
        $e->getMessage();


    header(
        "Location: confirm_appointment.php"
    );

    exit;
}   