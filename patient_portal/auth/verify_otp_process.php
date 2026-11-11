<?php

declare(strict_types=1);


/*====================================================
    LOAD GLOBAL FILES
====================================================*/

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/validation.php';


/*====================================================
    REQUEST METHOD CHECK
====================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    redirect('verify_otp.php');
}


/*====================================================
    REGISTRATION SESSION CHECK
====================================================*/

if (
    !isset($_SESSION['patient_registration']) ||
    !is_array($_SESSION['patient_registration'])
)
{
    $_SESSION['error'] =
        'Registration session expired. Please register again.';

    redirect('patient_registration.php');
}


$patient =
    $_SESSION['patient_registration'];


/*====================================================
    GET ENTERED OTP
====================================================*/

$entered_otp =
    cleanInput($_POST['otp'] ?? '');


/*====================================================
    OTP FORMAT VALIDATION
====================================================*/

if (!validateOTP($entered_otp))
{
    $_SESSION['error'] =
        'Please enter a valid 6-digit OTP.';

    redirect('verify_otp.php');
}


/*====================================================
    OTP EXPIRY CHECK
====================================================*/

$otp_expiry_time =
    (int) (
        $patient['otp_expiry_time'] ?? 0
    );


if (
    $otp_expiry_time <= 0 ||
    time() > $otp_expiry_time
)
{
    unset(
        $_SESSION['patient_registration']
    );

    $_SESSION['error'] =
        'OTP has expired. Please register again.';

    redirect('patient_registration.php');
}


/*====================================================
    OTP ATTEMPT LIMIT
====================================================*/

$otp_attempts =
    (int) (
        $patient['otp_attempts'] ?? 0
    );


if ($otp_attempts >= 3)
{
    unset(
        $_SESSION['patient_registration']
    );

    $_SESSION['error'] =
        'Maximum OTP attempts exceeded. Please register again.';

    redirect('patient_registration.php');
}


/*====================================================
    OTP MATCH
====================================================*/

$stored_otp =
    (string) (
        $patient['otp'] ?? ''
    );


if (
    !hash_equals(
        $stored_otp,
        $entered_otp
    )
)
{
    $_SESSION['patient_registration']['otp_attempts'] =
        $otp_attempts + 1;

    $_SESSION['error'] =
        'Incorrect OTP. Please try again.';

    redirect('verify_otp.php');
}


/*====================================================
    PATIENT ACCOUNT STATUS
====================================================*/

/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
|
| OTP verification successfully proves the registration.
|
| Therefore:
|
| mobile_verified = 1
| account_status  = Active
|
*/

$mobile_verified = 1;

$account_status = 'Active';


/*====================================================
    INSERT PATIENT ACCOUNT
====================================================*/

$query = "
    INSERT INTO patient_accounts
    (
        patient_uuid,
        first_name,
        last_name,
        email,
        mobile,
        password,
        registration_source,
        mobile_verified,
        account_status,
        created_by_hospital
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if (!$stmt)
{
    die(
        'Database Error: ' .
        mysqli_error($conn)
    );
}


/*====================================================
    BIND PARAMETERS
====================================================*/

mysqli_stmt_bind_param(
    $stmt,
    "sssssssiss",
    $patient['patient_uuid'],
    $patient['first_name'],
    $patient['last_name'],
    $patient['email'],
    $patient['mobile'],
    $patient['password'],
    $patient['registration_source'],
    $mobile_verified,
    $account_status,
    $patient['created_by_hospital']
);


/*====================================================
    EXECUTE INSERT
====================================================*/

if (!mysqli_stmt_execute($stmt))
{
    $error_message =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    die(
        'Unable to complete registration: ' .
        $error_message
    );
}


/*====================================================
    GET CREATED ACCOUNT ID
====================================================*/

$account_id =
    mysqli_insert_id($conn);


/*====================================================
    CLOSE STATEMENT
====================================================*/

mysqli_stmt_close($stmt);


/*====================================================
    CREATE EMPTY PATIENT PROFILE
====================================================*/

/*
|--------------------------------------------------------------------------
| Patient account and patient profile are separate.
|
| The account is created during registration.
| Additional personal information will be completed
| later from the Patient Profile page.
|
*/

$profile_query = "
    INSERT INTO patient_profiles
    (
        account_id
    )
    VALUES
    (
        ?
    )
";


$profile_stmt = mysqli_prepare(
    $conn,
    $profile_query
);


if ($profile_stmt)
{
    mysqli_stmt_bind_param(
        $profile_stmt,
        "i",
        $account_id
    );

    mysqli_stmt_execute(
        $profile_stmt
    );

    mysqli_stmt_close(
        $profile_stmt
    );
}


/*====================================================
    REMOVE REGISTRATION SESSION
====================================================*/

unset(
    $_SESSION['patient_registration']
);


/*====================================================
    SUCCESS MESSAGE
====================================================*/

$_SESSION['success'] =
    'Registration completed successfully. You can now login.';


/*====================================================
    REDIRECT TO LOGIN
====================================================*/

redirect('../../mobile/login.php');

?>