<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=UTF-8');

date_default_timezone_set('Asia/Kolkata');


/*
|--------------------------------------------------------------------------
| DATABASE CONFIG
|--------------------------------------------------------------------------
*/

$config_file = __DIR__ . '/../../includes/config.php';

if (!file_exists($config_file)) {

    echo json_encode([
        'success' => false,
        'message' => 'Database configuration not found.',
        'data' => [
            'total' => 0,
            'appointments' => []
        ]
    ]);

    exit;
}

require_once $config_file;


/*
|--------------------------------------------------------------------------
| GET DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| Supports common connection variable names.
|
*/

$db = null;

if (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
}

elseif (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
}

elseif (isset($hospital_conn) && $hospital_conn instanceof mysqli) {
    $db = $hospital_conn;
}


/*
|--------------------------------------------------------------------------
| DATABASE ERROR
|--------------------------------------------------------------------------
*/

if (!$db) {

    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available.',
        'data' => [
            'total' => 0,
            'appointments' => []
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK LOGIN SESSION
|--------------------------------------------------------------------------
*/

$account_id = 0;


/*
| Main patient session
*/

if (
    isset($_SESSION['account_id']) &&
    (int)$_SESSION['account_id'] > 0
) {

    $account_id =
        (int)$_SESSION['account_id'];

}


/*
| Alternative session names
*/

if ($account_id <= 0 && isset($_SESSION['patient_account_id'])) {

    $account_id =
        (int)$_SESSION['patient_account_id'];

}

if ($account_id <= 0 && isset($_SESSION['patient_id'])) {

    $account_id =
        (int)$_SESSION['patient_id'];

}


/*
|--------------------------------------------------------------------------
| LOGIN REQUIRED
|--------------------------------------------------------------------------
*/

if ($account_id <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Patient login required.',
        'login_required' => true,
        'data' => [
            'total' => 0,
            'appointments' => []
        ]
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| APPOINTMENT QUERY
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| appointments.mapping_id
|          ↓
| patient_hospital_mapping.mapping_id
|          ↓
| patient_hospital_mapping.account_id
|
| Therefore only the logged-in patient's
| appointments are returned.
|
*/

$sql = "
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

        a.consultation_fee,

        a.symptoms,
        a.notes,

        a.created_at,
        a.updated_at,

        pm.account_id,
        pm.hospital_id,
        pm.hospital_patient_code

    FROM appointments a

    INNER JOIN patient_hospital_mapping pm
        ON pm.mapping_id = a.mapping_id

    WHERE pm.account_id = ?

    ORDER BY
        a.appointment_date ASC,
        a.appointment_time ASC
";


$stmt = mysqli_prepare(
    $db,
    $sql
);


if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare appointment query.',
        'data' => [
            'total' => 0,
            'appointments' => []
        ]
    ]);

    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    'i',
    $account_id
);


if (!mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load appointments.',
        'data' => [
            'total' => 0,
            'appointments' => []
        ]
    ]);

    exit;
}


$result =
    mysqli_stmt_get_result($stmt);


$appointments = [];


while ($row = mysqli_fetch_assoc($result)) {

    /*
    |--------------------------------------------------------------------------
    | CLEAN DATA
    |--------------------------------------------------------------------------
    */

    $appointments[] = [

        'appointment_id' =>
            (int)$row['appointment_id'],

        'mapping_id' =>
            (int)$row['mapping_id'],

        'doctor_id' =>
            (int)$row['doctor_id'],

        'service_id' =>
            (int)$row['service_id'],

        'hospital_id' =>
            (int)$row['hospital_id'],

        'hospital_patient_code' =>
            (string)$row['hospital_patient_code'],

        'appointment_no' =>
            (string)$row['appointment_no'],

        'appointment_date' =>
            (string)$row['appointment_date'],

        'appointment_time' =>
            (string)$row['appointment_time'],

        'appointment_type' =>
            (string)$row['appointment_type'],

        'consultation_mode' =>
            (string)$row['consultation_mode'],

        'token_number' =>
            $row['token_number'] !== null
                ? (int)$row['token_number']
                : null,

        'appointment_status' =>
            (string)$row['appointment_status'],

        'consultation_fee' =>
            (float)$row['consultation_fee'],

        'symptoms' =>
            (string)($row['symptoms'] ?? ''),

        'notes' =>
            (string)($row['notes'] ?? ''),

        'created_at' =>
            (string)$row['created_at'],

        'updated_at' =>
            (string)$row['updated_at']

    ];

}


mysqli_free_result($result);

mysqli_stmt_close($stmt);


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode(
    [
        'success' => true,

        'message' => '',

        'data' => [

            'account_id' =>
                $account_id,

            'total' =>
                count($appointments),

            'appointments' =>
                $appointments

        ]
    ],
    JSON_UNESCAPED_UNICODE
);

exit;