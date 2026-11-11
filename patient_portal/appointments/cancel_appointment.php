<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| CANCEL APPOINTMENT
|--------------------------------------------------------------------------
| Patient cancels his/her own scheduled appointment.
|
| Central Database:
|   patient_hospital_mapping
|   hospital_registration
|
| Hospital Database:
|   appointments
|
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
)
{
    $_SESSION['error'] =
        "Invalid cancellation request.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*====================================================
    GET ACCOUNT ID
====================================================*/

$account_id =
    (int) (
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if ($account_id <= 0)
{
    $_SESSION['error'] =
        "Invalid patient session.";

    header(
        "Location: /Hospital_Management_System/patient_portal/auth/login.php"
    );

    exit;
}


/*====================================================
    GET APPOINTMENT ID
====================================================*/

$appointment_id =
    filter_input(
        INPUT_POST,
        'appointment_id',
        FILTER_VALIDATE_INT
    );


if (
    $appointment_id === false
    ||
    $appointment_id === null
    ||
    $appointment_id <= 0
)
{
    $_SESSION['error'] =
        "Invalid appointment.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*====================================================
    GET HOSPITAL ID
====================================================*/

$hospital_id =
    filter_input(
        INPUT_POST,
        'hospital_id',
        FILTER_VALIDATE_INT
    );


if (
    $hospital_id === false
    ||
    $hospital_id === null
    ||
    $hospital_id <= 0
)
{
    $_SESSION['error'] =
        "Invalid hospital.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*====================================================
    GET CANCELLATION REASON
====================================================*/

$cancellation_reason =
    trim(
        (string) (
            $_POST[
                'cancellation_reason'
            ]
            ?? ''
        )
    );


if (
    $cancellation_reason === ''
)
{
    $_SESSION['error'] =
        "Please enter a cancellation reason.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*
    Maximum 500 characters.
*/

$cancellation_reason =
    mb_substr(
        $cancellation_reason,
        0,
        500
    );


/*====================================================
    FIND PATIENT HOSPITAL MAPPING
====================================================*/

$query = "
    SELECT
        mapping_id,
        hospital_id,
        hospital_patient_code,
        patient_status

    FROM patient_hospital_mapping

    WHERE account_id = ?
      AND hospital_id = ?
      AND patient_status = 'Active'

    LIMIT 1
";


$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt)
{
    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $account_id,
    $hospital_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    mysqli_stmt_close(
        $stmt
    );

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$mapping =
    null;


if ($result)
{
    $mapping =
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
    MAPPING NOT FOUND
====================================================*/

if (!$mapping)
{
    $_SESSION['error'] =
        "You are not registered with this hospital.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


$mapping_id =
    (int)
    $mapping['mapping_id'];


/*====================================================
    GET HOSPITAL DATABASE
====================================================*/

$query = "
    SELECT
        hospital_id,
        hospital_name,
        database_name

    FROM hospital_registration

    WHERE hospital_id = ?

    LIMIT 1
";


$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt)
{
    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $hospital_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    mysqli_stmt_close(
        $stmt
    );

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$hospital =
    null;


if ($result)
{
    $hospital =
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
    HOSPITAL NOT FOUND
====================================================*/

if (!$hospital)
{
    $_SESSION['error'] =
        "Hospital configuration was not found.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*====================================================
    GET DATABASE NAME
====================================================*/

$hospital_database =
    trim(
        (string) (
            $hospital[
                'database_name'
            ]
            ?? ''
        )
    );


/*====================================================
    VALIDATE DATABASE NAME
====================================================*/

if (
    $hospital_database === ''
    ||
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
)
{
    $_SESSION['error'] =
        "Invalid hospital database configuration.";

    header(
        "Location: my_appointments.php"
    );

    exit;
}


/*====================================================
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn)
{
    $_SESSION['error'] =
        "Unable to connect to the hospital database.";

    header(
        "Location: my_appointments.php"
    );

    exit;
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


try
{

    /*================================================
        GET APPOINTMENT
    ================================================*/

    $query = "
        SELECT
            appointment_id,
            mapping_id,
            doctor_id,
            appointment_date,
            appointment_time,
            appointment_status

        FROM appointments

        WHERE appointment_id = ?
          AND mapping_id = ?

        LIMIT 1

        FOR UPDATE
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt)
    {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $appointment_id,
        $mapping_id
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    )
    {
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


    $appointment =
        null;


    if ($result)
    {
        $appointment =
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


    /*================================================
        APPOINTMENT NOT FOUND
    ================================================*/

    if (!$appointment)
    {
        throw new Exception(
            "Appointment was not found."
        );
    }


    /*================================================
        ONLY SCHEDULED APPOINTMENT
    ================================================*/

    if (
        (string)
        $appointment[
            'appointment_status'
        ]
        !== 'Scheduled'
    )
    {
        throw new Exception(
            "Only scheduled appointments can be cancelled."
        );
    }


    /*================================================
        CHECK DATE AND TIME
    ================================================*/

    $appointment_datetime =
        DateTime::createFromFormat(
            'Y-m-d H:i:s',
            (
                (string)
                $appointment[
                    'appointment_date'
                ]
            )
            .
            ' '
            .
            (
                (string)
                $appointment[
                    'appointment_time'
                ]
            )
        );


    if (
        $appointment_datetime
        &&
        $appointment_datetime <=
        new DateTime()
    )
    {
        throw new Exception(
            "Past or already started appointments cannot be cancelled."
        );
    }


    /*================================================
        CANCEL APPOINTMENT
    ================================================*/

    $query = "
        UPDATE appointments

        SET
            appointment_status = 'Cancelled',
            cancelled_by = 'Patient',
            cancellation_reason = ?,
            updated_at = NOW()

        WHERE appointment_id = ?
          AND mapping_id = ?
          AND appointment_status = 'Scheduled'
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt)
    {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "sii",
        $cancellation_reason,
        $appointment_id,
        $mapping_id
    );


    if (
        !mysqli_stmt_execute(
            $stmt
        )
    )
    {
        throw new Exception(
            mysqli_error(
                $hospital_conn
            )
        );
    }


    $affected_rows =
        mysqli_stmt_affected_rows(
            $stmt
        );


    mysqli_stmt_close(
        $stmt
    );


    /*================================================
        VERIFY UPDATE
    ================================================*/

    if ($affected_rows !== 1)
    {
        throw new Exception(
            "Appointment could not be cancelled."
        );
    }


    /*================================================
        COMMIT
    ================================================*/

    mysqli_commit(
        $hospital_conn
    );


    mysqli_close(
        $hospital_conn
    );


    /*================================================
        SUCCESS MESSAGE
    ================================================*/

    $_SESSION['success'] =
        "Appointment cancelled successfully.";


    header(
        "Location: my_appointments.php"
    );

    exit;

}
catch (
    Throwable $e
)
{

    /*================================================
        ROLLBACK
    ================================================*/

    mysqli_rollback(
        $hospital_conn
    );


    mysqli_close(
        $hospital_conn
    );


    /*================================================
        ERROR MESSAGE
    ================================================*/

    $_SESSION['error'] =
        $e->getMessage();


    header(
        "Location: my_appointments.php"
    );

    exit;
}

?>