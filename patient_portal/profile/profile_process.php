<?php

declare(strict_types=1);


/*====================================================
    GLOBAL INCLUDES
====================================================*/

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';


/*====================================================
    ALLOW ONLY POST REQUEST
====================================================*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    redirect('profile.php');
}


/*====================================================
    AUTHENTICATION CHECK
====================================================*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    !isset($_SESSION['patient_auth']['logged_in']) ||
    $_SESSION['patient_auth']['logged_in'] !== true
)
{
    $_SESSION['error'] =
        'Please login to continue.';

    redirect('../auth/login.php');
}


/*====================================================
    GET ACCOUNT ID FROM SESSION
====================================================*/

$account_id =
    (int) (
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if ($account_id <= 0)
{
    $_SESSION['error'] =
        'Invalid patient session.';

    redirect('../auth/login.php');
}


/*====================================================
    GET FORM DATA
====================================================*/

$gender =
    trim($_POST['gender'] ?? '');

$date_of_birth =
    trim($_POST['date_of_birth'] ?? '');

$blood_group =
    trim($_POST['blood_group'] ?? '');

$marital_status =
    trim($_POST['marital_status'] ?? '');

$address_line_1 =
    trim($_POST['address_line_1'] ?? '');

$address_line_2 =
    trim($_POST['address_line_2'] ?? '');

$city =
    trim($_POST['city'] ?? '');

$state =
    trim($_POST['state'] ?? '');

$country =
    trim($_POST['country'] ?? '');

$postal_code =
    trim($_POST['postal_code'] ?? '');

$emergency_contact_name =
    trim($_POST['emergency_contact_name'] ?? '');

$emergency_contact_mobile =
    trim($_POST['emergency_contact_mobile'] ?? '');

$emergency_relationship =
    trim($_POST['emergency_relationship'] ?? '');

$insurance_provider =
    trim($_POST['insurance_provider'] ?? '');

$insurance_number =
    trim($_POST['insurance_number'] ?? '');

$allergies =
    trim($_POST['allergies'] ?? '');


/*====================================================
    ALLOWED VALUES
====================================================*/

$allowed_genders = [
    'Male',
    'Female',
    'Other'
];

$allowed_blood_groups = [
    'A+',
    'A-',
    'B+',
    'B-',
    'AB+',
    'AB-',
    'O+',
    'O-'
];

$allowed_marital_statuses = [
    'Single',
    'Married',
    'Widowed',
    'Divorced'
];


/*====================================================
    GENDER VALIDATION
====================================================*/

if (
    $gender !== '' &&
    !in_array(
        $gender,
        $allowed_genders,
        true
    )
)
{
    $_SESSION['error'] =
        'Invalid gender selected.';

    redirect('profile.php');
}


/*====================================================
    BLOOD GROUP VALIDATION
====================================================*/

if (
    $blood_group !== '' &&
    !in_array(
        $blood_group,
        $allowed_blood_groups,
        true
    )
)
{
    $_SESSION['error'] =
        'Invalid blood group selected.';

    redirect('profile.php');
}


/*====================================================
    MARITAL STATUS VALIDATION
====================================================*/

if (
    $marital_status !== '' &&
    !in_array(
        $marital_status,
        $allowed_marital_statuses,
        true
    )
)
{
    $_SESSION['error'] =
        'Invalid marital status selected.';

    redirect('profile.php');
}


/*====================================================
    DATE OF BIRTH VALIDATION
====================================================*/

if ($date_of_birth !== '')
{
    $date_object =
        DateTime::createFromFormat(
            'Y-m-d',
            $date_of_birth
        );


    $date_errors =
        DateTime::getLastErrors();


    if (
        $date_object === false ||
        (
            is_array($date_errors) &&
            (
                $date_errors['warning_count'] > 0 ||
                $date_errors['error_count'] > 0
            )
        )
    )
    {
        $_SESSION['error'] =
            'Please enter a valid date of birth.';

        redirect('profile.php');
    }


    /*----------------------------------------------
        DOB cannot be in the future
    ----------------------------------------------*/

    if (
        $date_object > new DateTime('today')
    )
    {
        $_SESSION['error'] =
            'Date of birth cannot be in the future.';

        redirect('profile.php');
    }
}


/*====================================================
    POSTAL CODE VALIDATION
====================================================*/

if (
    $postal_code !== '' &&
    !preg_match(
        '/^[0-9A-Za-z\s\-]{3,10}$/',
        $postal_code
    )
)
{
    $_SESSION['error'] =
        'Please enter a valid postal code.';

    redirect('profile.php');
}


/*====================================================
    EMERGENCY MOBILE VALIDATION
====================================================*/

if (
    $emergency_contact_mobile !== '' &&
    !preg_match(
        '/^[0-9+\-\s()]{7,15}$/',
        $emergency_contact_mobile
    )
)
{
    $_SESSION['error'] =
        'Please enter a valid emergency contact number.';

    redirect('profile.php');
}


/*====================================================
    LENGTH VALIDATION
====================================================*/

if (mb_strlen($address_line_1) > 255)
{
    $_SESSION['error'] =
        'Address Line 1 is too long.';

    redirect('profile.php');
}


if (mb_strlen($address_line_2) > 255)
{
    $_SESSION['error'] =
        'Address Line 2 is too long.';

    redirect('profile.php');
}


if (mb_strlen($city) > 100)
{
    $_SESSION['error'] =
        'City name is too long.';

    redirect('profile.php');
}


if (mb_strlen($state) > 100)
{
    $_SESSION['error'] =
        'State name is too long.';

    redirect('profile.php');
}


if (mb_strlen($country) > 100)
{
    $_SESSION['error'] =
        'Country name is too long.';

    redirect('profile.php');
}


if (mb_strlen($emergency_contact_name) > 100)
{
    $_SESSION['error'] =
        'Emergency contact name is too long.';

    redirect('profile.php');
}


if (mb_strlen($emergency_relationship) > 50)
{
    $_SESSION['error'] =
        'Emergency relationship is too long.';

    redirect('profile.php');
}


if (mb_strlen($insurance_provider) > 150)
{
    $_SESSION['error'] =
        'Insurance provider name is too long.';

    redirect('profile.php');
}


if (mb_strlen($insurance_number) > 100)
{
    $_SESSION['error'] =
        'Insurance number is too long.';

    redirect('profile.php');
}


/*====================================================
    UPDATE PATIENT PROFILE
====================================================*/

$query = "
    UPDATE patient_profiles
    SET
        gender = NULLIF(?, ''),
        date_of_birth = NULLIF(?, ''),
        blood_group = NULLIF(?, ''),
        marital_status = NULLIF(?, ''),
        address_line_1 = NULLIF(?, ''),
        address_line_2 = NULLIF(?, ''),
        city = NULLIF(?, ''),
        state = NULLIF(?, ''),
        country = NULLIF(?, ''),
        postal_code = NULLIF(?, ''),
        emergency_contact_name = NULLIF(?, ''),
        emergency_contact_mobile = NULLIF(?, ''),
        emergency_relationship = NULLIF(?, ''),
        insurance_provider = NULLIF(?, ''),
        insurance_number = NULLIF(?, ''),
        allergies = NULLIF(?, '')
    WHERE account_id = ?
";


$stmt =
    mysqli_prepare(
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


mysqli_stmt_bind_param(
    $stmt,
    'ssssssssssssssssi',
    $gender,
    $date_of_birth,
    $blood_group,
    $marital_status,
    $address_line_1,
    $address_line_2,
    $city,
    $state,
    $country,
    $postal_code,
    $emergency_contact_name,
    $emergency_contact_mobile,
    $emergency_relationship,
    $insurance_provider,
    $insurance_number,
    $allergies,
    $account_id
);


/*====================================================
    EXECUTE UPDATE
====================================================*/

if (
    !mysqli_stmt_execute($stmt)
)
{
    $error_message =
        mysqli_stmt_error($stmt);

    mysqli_stmt_close($stmt);

    die(
        'Unable to update profile: ' .
        $error_message
    );
}


mysqli_stmt_close($stmt);


/*====================================================
    SUCCESS
====================================================*/

$_SESSION['success'] =
    'Your profile has been updated successfully.';


/*====================================================
    RETURN TO PROFILE
====================================================*/

redirect('profile.php');

?>