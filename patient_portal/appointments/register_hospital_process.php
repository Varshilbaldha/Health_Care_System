<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| REGISTER PATIENT WITH HOSPITAL
|--------------------------------------------------------------------------
| This file creates the relationship between:
|
| patient_accounts
|       +
| hospital_registration
|       ↓
| patient_hospital_mapping
|
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../includes/auth_check.php';


/*====================================================
    GET PATIENT ACCOUNT
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
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
)
{
    $_SESSION['error'] =
        "Invalid request.";

    header(
        "Location: book.php"
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
        "Invalid hospital.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    CHECK HOSPITAL
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


if (!$hospital)
{
    $_SESSION['error'] =
        "Selected hospital was not found.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    CHECK EXISTING MAPPING
====================================================*/

$query = "
    SELECT
        mapping_id,
        hospital_patient_code,
        patient_status
    FROM patient_hospital_mapping
    WHERE account_id = ?
      AND hospital_id = ?
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


$existing_mapping =
    null;


if ($result)
{
    $existing_mapping =
        mysqli_fetch_assoc($result);

    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    EXISTING ACTIVE MAPPING
====================================================*/

if (
    $existing_mapping &&
    $existing_mapping['patient_status']
        === 'Active'
)
{
    $_SESSION['success'] =
        "You are already registered with " .
        $hospital['hospital_name'] .
        ".";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GENERATE PATIENT HOSPITAL CODE
====================================================*/

function generateHospitalPatientCode(
    int $account_id,
    int $hospital_id
): string
{
    return
        'HP-' .
        $hospital_id .
        '-' .
        $account_id .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(4)
            )
        );
}


/*====================================================
    CREATE PATIENT CODE
====================================================*/

$hospital_patient_code =
    generateHospitalPatientCode(
        $account_id,
        $hospital_id
    );


/*====================================================
    IF OLD INACTIVE MAPPING EXISTS
    REACTIVATE IT
====================================================*/

if ($existing_mapping)
{
    $mapping_id =
        (int)
        $existing_mapping['mapping_id'];


    $query = "
        UPDATE patient_hospital_mapping

        SET
            hospital_patient_code = ?,
            registration_source = 'Patient',
            patient_status = 'Active',
            registered_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP

        WHERE mapping_id = ?
          AND account_id = ?
          AND hospital_id = ?
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
        "siii",
        $hospital_patient_code,
        $mapping_id,
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


    mysqli_stmt_close($stmt);
}


/*====================================================
    CREATE NEW MAPPING
====================================================*/

else
{
    $query = "
        INSERT INTO patient_hospital_mapping
        (
            account_id,
            hospital_id,
            hospital_patient_code,
            registration_source,
            patient_status,
            registered_at,
            first_visit_date,
            last_visit_date,
            total_visits,
            remarks
        )
        VALUES
        (
            ?,
            ?,
            ?,
            'Patient',
            'Active',
            CURRENT_TIMESTAMP,
            NULL,
            NULL,
            0,
            NULL
        )
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
        "iis",
        $account_id,
        $hospital_id,
        $hospital_patient_code
    );


    if (!mysqli_stmt_execute($stmt))
    {
        mysqli_stmt_close($stmt);

        die(
            "Database Error: " .
            mysqli_error($conn)
        );
    }


    mysqli_stmt_close($stmt);
}


/*====================================================
    SUCCESS
====================================================*/

$_SESSION['success'] =
    "Successfully registered with " .
    $hospital['hospital_name'] .
    ".";


/*====================================================
    REDIRECT
====================================================*/

header(
    "Location: book.php"
);

exit;