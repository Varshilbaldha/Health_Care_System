<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| SELECT HOSPITAL
|--------------------------------------------------------------------------
| Appointment Booking - Step 2
|
| Central database:
|
| hospital_management
|       ↓
| hospital_registration
|       ↓
| database_name
|
| Selected hospital information is stored in session.
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ . '/../includes/auth_check.php';


/*====================================================
    ONLY POST REQUEST
====================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    $_SESSION['error'] =
        "Invalid appointment request.";

    header(
        "Location: /Hospital_Management_System/patient_portal/appointments/book.php"
    );

    exit;
}


/*====================================================
    GET LOGGED-IN PATIENT
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
    GET HOSPITAL ID
====================================================*/

$hospital_id =
    filter_input(
        INPUT_POST,
        'hospital_id',
        FILTER_VALIDATE_INT
    );


if (
    $hospital_id === false ||
    $hospital_id === null ||
    $hospital_id <= 0
)
{
    $_SESSION['error'] =
        "Invalid hospital selection.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    VERIFY PATIENT HOSPITAL MAPPING
====================================================*/

$query = "
    SELECT
        mapping_id,
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


if (!mysqli_stmt_execute($stmt))
{
    mysqli_stmt_close($stmt);

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$result =
    mysqli_stmt_get_result($stmt);


$mapping =
    null;


if ($result)
{
    $mapping =
        mysqli_fetch_assoc($result);

    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    MAPPING NOT FOUND
====================================================*/

if (!$mapping)
{
    $_SESSION['error'] =
        "You are not registered with the selected hospital.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET HOSPITAL INFORMATION
====================================================*/

$query = "
    SELECT
        hospital_id,
        hospital_name,
        city,
        state,
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


if (!mysqli_stmt_execute($stmt))
{
    mysqli_stmt_close($stmt);

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$result =
    mysqli_stmt_get_result($stmt);


$hospital =
    null;


if ($result)
{
    $hospital =
        mysqli_fetch_assoc($result);

    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    HOSPITAL NOT FOUND
====================================================*/

if (!$hospital)
{
    $_SESSION['error'] =
        "Hospital not found.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    DATABASE NAME
====================================================*/

$hospital_database =
    trim(
        (string) (
            $hospital['database_name']
            ?? ''
        )
    );


/*====================================================
    VALIDATE DATABASE NAME
====================================================*/

if (
    $hospital_database === '' ||
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
)
{
    $_SESSION['error'] =
        "Hospital database configuration is invalid.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    VERIFY HOSPITAL DATABASE EXISTS
====================================================*/

$database_check =
    db_connect($hospital_database);


if (!$database_check)
{
    $_SESSION['error'] =
        "Hospital database is not available: " .
        $hospital_database;

    header(
        "Location: book.php"
    );

    exit;
}


mysqli_set_charset(
    $database_check,
    "utf8mb4"
);


mysqli_close(
    $database_check
);


/*====================================================
    CREATE APPOINTMENT BOOKING SESSION
====================================================*/

$_SESSION['appointment_booking'] = [

    'account_id' =>
        $account_id,

    'mapping_id' =>
        (int) $mapping['mapping_id'],

    'hospital_id' =>
        (int) $hospital['hospital_id'],

    'hospital_patient_code' =>
        (string) $mapping[
            'hospital_patient_code'
        ],

    'hospital_name' =>
        (string) $hospital['hospital_name'],

    'city' =>
        (string) $hospital['city'],

    'state' =>
        (string) $hospital['state'],

    'database_name' =>
        $hospital_database

];


/*====================================================
    GO TO DEPARTMENT
====================================================*/

header(
    "Location: select_department.php"
);

exit;

?>