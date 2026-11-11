<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| PATIENTS
|--------------------------------------------------------------------------
| Authentication:
|   auth.php
|
| Central DB:
|   includes/config.php
|   patient_accounts
|   patient_profiles
|   patient_hospital_mapping
|
| Hospital DB:
|   appointments
|   admissions
|   doctors
|   departments
|--------------------------------------------------------------------------
*/


/* ============================================================
   ERROR DISPLAY
   ============================================================ */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);


/* ============================================================
   AUTHENTICATION
   ============================================================ */

require_once __DIR__ . '/auth.php';


/* ============================================================
   CENTRAL DATABASE
   ============================================================ */

require_once __DIR__ . '/../includes/config.php';


/* ============================================================
   HOSPITAL CONTEXT
   ============================================================ */

$application_no = trim(
    (string)($_SESSION['application_no'] ?? '')
);

if ($application_no === '') {
    die('Hospital application number is missing from session.');
}

if (!preg_match('/^[A-Za-z0-9]+$/', $application_no)) {
    die('Invalid hospital application number.');
}


/* ============================================================
   LOAD HOSPITAL
   ============================================================ */

$context_sql = "
    SELECT
        hospital_id,
        hospital_name,
        database_name
    FROM hospital_registration
    WHERE application_no = ?
    LIMIT 1
";

$context_stmt = mysqli_prepare(
    $conn,
    $context_sql
);

if (!$context_stmt) {
    die(
        'Hospital context query failed: ' .
        mysqli_error($conn)
    );
}

mysqli_stmt_bind_param(
    $context_stmt,
    's',
    $application_no
);

if (!mysqli_stmt_execute($context_stmt)) {
    die(
        'Hospital context execution failed: ' .
        mysqli_stmt_error($context_stmt)
    );
}

$context_result =
    mysqli_stmt_get_result($context_stmt);

$hospital_context =
    $context_result
        ? mysqli_fetch_assoc($context_result)
        : null;

if ($context_result) {
    mysqli_free_result($context_result);
}

mysqli_stmt_close($context_stmt);


if (!$hospital_context) {
    die('Hospital record not found.');
}


/* ============================================================
   HOSPITAL DETAILS
   ============================================================ */

$hospital_id =
    (int)($hospital_context['hospital_id'] ?? 0);

$hospital_name =
    (string)($hospital_context['hospital_name'] ?? '');

$hospital_database =
    (string)($hospital_context['database_name'] ?? '');


if ($hospital_id <= 0) {
    die('Invalid hospital ID.');
}


if (
    $hospital_database === '' ||
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
) {
    die('Invalid hospital database name.');
}


/* ============================================================
   HOSPITAL DATABASE CONNECTION
   ============================================================ */

$hospital_conn = db_connect($hospital_database);

if (!$hospital_conn) {
    die(
        'Hospital database connection failed: ' .
        mysqli_connect_error()
    );
}

mysqli_set_charset(
    $hospital_conn,
    'utf8mb4'
);


/* ============================================================
   HELPERS
   ============================================================ */

function h($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function redirect_patients(array $params = []): never
{
    $url = 'patients.php';

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}


function bind_params_dynamic(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {

    if ($types === '') {
        return;
    }

    $refs = [];

    foreach ($params as $key => &$value) {
        $refs[$key] = &$value;
    }

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$refs
    );

    unset($value);
}


function scalar_value(
    mysqli $db,
    string $sql,
    string $types = '',
    array $params = []
): int {

    $stmt = mysqli_prepare(
        $db,
        $sql
    );

    if (!$stmt) {
        return 0;
    }

    if ($types !== '') {

        bind_params_dynamic(
            $stmt,
            $types,
            $params
        );
    }

    if (!mysqli_stmt_execute($stmt)) {

        mysqli_stmt_close($stmt);

        return 0;
    }

    $result =
        mysqli_stmt_get_result($stmt);

    $value = 0;

    if ($result) {

        $row =
            mysqli_fetch_row($result);

        $value =
            (int)($row[0] ?? 0);

        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    return $value;
}


function make_patient_uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(16384, 20479),
        random_int(32768, 49151),
        random_int(32768, 49151),
        random_int(0, 65535),
        random_int(0, 65535),
        random_int(0, 65535)
    );
}


function make_patient_code(
    int $hospital_id,
    int $account_id
): string {

    return
        'HP-' .
        $hospital_id .
        '-' .
        $account_id .
        '-' .
        strtoupper(
            bin2hex(
                random_bytes(3)
            )
        );
}


function patient_json(array $patient): string
{
    return htmlspecialchars(
        json_encode(
            $patient,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_AMP |
            JSON_HEX_QUOT |
            JSON_UNESCAPED_UNICODE
        ) ?: '{}',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ============================================================
   CENTRAL DB NAME
   ============================================================ */

$central_result =
    mysqli_query(
        $conn,
        "SELECT DATABASE()"
    );

if (!$central_result) {
    die(
        'Unable to determine central database: ' .
        mysqli_error($conn)
    );
}

$central_row =
    mysqli_fetch_row($central_result);

mysqli_free_result(
    $central_result
);

$central_db =
    (string)($central_row[0] ?? '');

if (
    $central_db === '' ||
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $central_db
    )
) {
    die('Invalid central database.');
}

$central_identifier =
    '`' . $central_db . '`';


/* ============================================================
   VERIFY HOSPITAL DATABASE
   ============================================================ */

$hospital_result =
    mysqli_query(
        $hospital_conn,
        "SELECT DATABASE()"
    );

if (!$hospital_result) {
    die(
        'Unable to determine hospital database: ' .
        mysqli_error($hospital_conn)
    );
}

$hospital_row =
    mysqli_fetch_row($hospital_result);

mysqli_free_result(
    $hospital_result
);

$actual_hospital_db =
    (string)($hospital_row[0] ?? '');

if ($actual_hospital_db === '') {
    die('Hospital database connection is empty.');
}


/* ============================================================
   TABLE CHECK
   ============================================================ */

function hospital_table_exists(
    mysqli $db,
    string $table
): bool {

    $table =
        preg_replace(
            '/[^A-Za-z0-9_]/',
            '',
            $table
        );

    if ($table === '') {
        return false;
    }

    $result =
        mysqli_query(
            $db,
            "SHOW TABLES LIKE '" .
            mysqli_real_escape_string(
                $db,
                $table
            ) .
            "'"
        );

    if (!$result) {
        return false;
    }

    $exists =
        mysqli_num_rows($result) > 0;

    mysqli_free_result($result);

    return $exists;
}


/* ============================================================
   MAKE SURE HOSPITAL TABLES EXIST
   ============================================================ */

if (!hospital_table_exists(
    $hospital_conn,
    'appointments'
)) {

    die(
        'Hospital database "' .
        h($actual_hospital_db) .
        '" does not contain the appointments table.'
    );
}


/* ============================================================
   VARIABLES
   ============================================================ */

$message = '';

$message_type = '';


/* ============================================================
   POST ACTIONS
   ============================================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        trim(
            (string)(
                $_POST['action'] ?? ''
            )
        );


    /* ========================================================
       ADD PATIENT
       ======================================================== */

    if ($action === 'add_patient') {

        $name =
            trim(
                (string)(
                    $_POST['patient_name'] ?? ''
                )
            );

        $mobile =
            trim(
                (string)(
                    $_POST['mobile'] ?? ''
                )
            );

        $email =
            trim(
                (string)(
                    $_POST['email'] ?? ''
                )
            );

        $gender =
            trim(
                (string)(
                    $_POST['gender'] ?? ''
                )
            );

        $dob =
            trim(
                (string)(
                    $_POST['date_of_birth'] ?? ''
                )
            );

        $blood_group =
            trim(
                (string)(
                    $_POST['blood_group'] ?? 'Unknown'
                )
            );

        $address =
            trim(
                (string)(
                    $_POST['address_line_1'] ?? ''
                )
            );

        $city =
            trim(
                (string)(
                    $_POST['city'] ?? ''
                )
            );

        $state =
            trim(
                (string)(
                    $_POST['state'] ?? ''
                )
            );

        $emergency_name =
            trim(
                (string)(
                    $_POST['emergency_contact_name'] ?? ''
                )
            );

        $emergency_mobile =
            trim(
                (string)(
                    $_POST['emergency_contact_mobile'] ?? ''
                )
            );

        $emergency_relation =
            trim(
                (string)(
                    $_POST['emergency_relationship'] ?? ''
                )
            );


        if (
            $name === '' ||
            $mobile === '' ||
            !in_array(
                $gender,
                [
                    'Male',
                    'Female',
                    'Other'
                ],
                true
            )
        ) {

            $message =
                'Name, mobile and gender are required.';

            $message_type = 'error';

        } elseif (
            $email !== '' &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $message =
                'Please enter a valid email address.';

            $message_type = 'error';

        } else {

            $name_parts =
                preg_split(
                    '/\s+/',
                    $name,
                    2
                );

            $first_name =
                trim(
                    (string)(
                        $name_parts[0] ?? ''
                    )
                );

            $last_name =
                trim(
                    (string)(
                        $name_parts[1] ?? ''
                    )
                );

            $dob_value =
                $dob !== ''
                    ? $dob
                    : null;


            mysqli_begin_transaction(
                $conn
            );

            try {

                /* Find account */

                $stmt =
                    mysqli_prepare(
                        $conn,

                        "SELECT account_id

                         FROM {$central_identifier}.patient_accounts

                         WHERE mobile = ?

                            OR (
                                ? <> ''
                                AND email = ?
                            )

                         LIMIT 1"
                    );

                if (!$stmt) {
                    throw new Exception(
                        mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    'sss',
                    $mobile,
                    $email,
                    $email
                );

                if (
                    !mysqli_stmt_execute($stmt)
                ) {

                    throw new Exception(
                        mysqli_stmt_error($stmt)
                    );
                }

                $result =
                    mysqli_stmt_get_result(
                        $stmt
                    );

                $existing =
                    $result
                        ? mysqli_fetch_assoc($result)
                        : null;

                if ($result) {
                    mysqli_free_result($result);
                }

                mysqli_stmt_close($stmt);


                /* Existing account */

                if ($existing) {

                    $account_id =
                        (int)(
                            $existing['account_id']
                        );

                }

                /* New account */

                else {

                    $patient_uuid =
                        make_patient_uuid();

                    $password_hash =
                        password_hash(
                            bin2hex(
                                random_bytes(16)
                            ),
                            PASSWORD_DEFAULT
                        );

                    $registration_source =
                        'HOSPITAL';

                    $mobile_verified =
                        1;

                    $account_status =
                        'Active';

                    $created_by_hospital =
                        $hospital_id;


                    $stmt =
                        mysqli_prepare(
                            $conn,

                            "INSERT INTO {$central_identifier}.patient_accounts
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
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?
                            )"
                        );

                    if (!$stmt) {
                        throw new Exception(
                            mysqli_error($conn)
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        'sssssssisi',
                        $patient_uuid,
                        $first_name,
                        $last_name,
                        $email,
                        $mobile,
                        $password_hash,
                        $registration_source,
                        $mobile_verified,
                        $account_status,
                        $created_by_hospital
                    );

                    if (
                        !mysqli_stmt_execute($stmt)
                    ) {

                        throw new Exception(
                            mysqli_stmt_error($stmt)
                        );
                    }

                    $account_id =
                        mysqli_insert_id($conn);

                    mysqli_stmt_close($stmt);


                    /* Profile */

                    $stmt =
                        mysqli_prepare(
                            $conn,

                            "INSERT INTO {$central_identifier}.patient_profiles
                            (
                                account_id,
                                gender,
                                date_of_birth,
                                blood_group,
                                address_line_1,
                                city,
                                state,
                                emergency_contact_name,
                                emergency_contact_mobile,
                                emergency_relationship
                            )
                            VALUES
                            (
                                ?, ?, ?, ?, ?, ?,
                                ?, ?, ?, ?
                            )"
                        );

                    if (!$stmt) {
                        throw new Exception(
                            mysqli_error($conn)
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        'isssssssss',
                        $account_id,
                        $gender,
                        $dob_value,
                        $blood_group,
                        $address,
                        $city,
                        $state,
                        $emergency_name,
                        $emergency_mobile,
                        $emergency_relation
                    );

                    if (
                        !mysqli_stmt_execute($stmt)
                    ) {

                        throw new Exception(
                            mysqli_stmt_error($stmt)
                        );
                    }

                    mysqli_stmt_close($stmt);
                }


                /* Hospital mapping */

                $stmt =
                    mysqli_prepare(
                        $conn,

                        "SELECT mapping_id

                         FROM {$central_identifier}.patient_hospital_mapping

                         WHERE account_id = ?
                           AND hospital_id = ?

                         LIMIT 1"
                    );

                if (!$stmt) {
                    throw new Exception(
                        mysqli_error($conn)
                    );
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    'ii',
                    $account_id,
                    $hospital_id
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                $mapping =
                    $result
                        ? mysqli_fetch_assoc($result)
                        : null;

                if ($result) {
                    mysqli_free_result($result);
                }

                mysqli_stmt_close($stmt);


                if ($mapping) {

                    $mapping_id =
                        (int)$mapping['mapping_id'];

                    $stmt =
                        mysqli_prepare(
                            $conn,

                            "UPDATE {$central_identifier}.patient_hospital_mapping

                             SET patient_status = 'Active'

                             WHERE mapping_id = ?
                               AND hospital_id = ?

                             LIMIT 1"
                        );

                    mysqli_stmt_bind_param(
                        $stmt,
                        'ii',
                        $mapping_id,
                        $hospital_id
                    );

                    mysqli_stmt_execute($stmt);

                    mysqli_stmt_close($stmt);

                } else {

                    $hospital_patient_code =
                        make_patient_code(
                            $hospital_id,
                            $account_id
                        );

                    $registration_source =
                        'Patient';


                    $stmt =
                        mysqli_prepare(
                            $conn,

                            "INSERT INTO {$central_identifier}.patient_hospital_mapping
                            (
                                account_id,
                                hospital_id,
                                hospital_patient_code,
                                registration_source,
                                patient_status,
                                registered_at,
                                total_visits
                            )
                            VALUES
                            (
                                ?, ?, ?, ?, 'Active',
                                CURRENT_TIMESTAMP,
                                0
                            )"
                        );

                    if (!$stmt) {
                        throw new Exception(
                            mysqli_error($conn)
                        );
                    }

                    mysqli_stmt_bind_param(
                        $stmt,
                        'iiss',
                        $account_id,
                        $hospital_id,
                        $hospital_patient_code,
                        $registration_source
                    );

                    if (
                        !mysqli_stmt_execute($stmt)
                    ) {

                        throw new Exception(
                            mysqli_stmt_error($stmt)
                        );
                    }

                    mysqli_stmt_close($stmt);
                }


                mysqli_commit($conn);

                redirect_patients([
                    'success' => 'added'
                ]);

            } catch (Throwable $e) {

                mysqli_rollback($conn);

                $message =
                    'Unable to add patient: ' .
                    $e->getMessage();

                $message_type =
                    'error';
            }
        }
    }


    /* ========================================================
       EDIT PATIENT
       ======================================================== */

    elseif ($action === 'edit_patient') {

        $mapping_id =
            (int)(
                $_POST['mapping_id'] ?? 0
            );

        $account_id =
            (int)(
                $_POST['account_id'] ?? 0
            );

        $name =
            trim(
                (string)(
                    $_POST['patient_name'] ?? ''
                )
            );

        $mobile =
            trim(
                (string)(
                    $_POST['mobile'] ?? ''
                )
            );

        $email =
            trim(
                (string)(
                    $_POST['email'] ?? ''
                )
            );

        $gender =
            trim(
                (string)(
                    $_POST['gender'] ?? ''
                )
            );

        $dob =
            trim(
                (string)(
                    $_POST['date_of_birth'] ?? ''
                )
            );

        $blood_group =
            trim(
                (string)(
                    $_POST['blood_group'] ?? 'Unknown'
                )
            );

        $address =
            trim(
                (string)(
                    $_POST['address_line_1'] ?? ''
                )
            );

        $city =
            trim(
                (string)(
                    $_POST['city'] ?? ''
                )
            );

        $state =
            trim(
                (string)(
                    $_POST['state'] ?? ''
                )
            );

        $emergency_name =
            trim(
                (string)(
                    $_POST['emergency_contact_name'] ?? ''
                )
            );

        $emergency_mobile =
            trim(
                (string)(
                    $_POST['emergency_contact_mobile'] ?? ''
                )
            );

        $emergency_relation =
            trim(
                (string)(
                    $_POST['emergency_relationship'] ?? ''
                )
            );


        if (
            $mapping_id <= 0 ||
            $account_id <= 0
        ) {

            $message =
                'Invalid patient information.';

            $message_type =
                'error';

        } elseif (
            $name === '' ||
            $mobile === '' ||
            !in_array(
                $gender,
                [
                    'Male',
                    'Female',
                    'Other'
                ],
                true
            )
        ) {

            $message =
                'Name, mobile and gender are required.';

            $message_type =
                'error';

        } else {

            $verify =
                mysqli_prepare(
                    $conn,

                    "SELECT mapping_id

                     FROM {$central_identifier}.patient_hospital_mapping

                     WHERE mapping_id = ?
                       AND account_id = ?
                       AND hospital_id = ?

                     LIMIT 1"
                );

            if (!$verify) {

                $message =
                    'Patient verification failed.';

                $message_type =
                    'error';

            } else {

                mysqli_stmt_bind_param(
                    $verify,
                    'iii',
                    $mapping_id,
                    $account_id,
                    $hospital_id
                );

                mysqli_stmt_execute(
                    $verify
                );

                $verify_result =
                    mysqli_stmt_get_result(
                        $verify
                    );

                $valid =
                    $verify_result &&
                    mysqli_num_rows(
                        $verify_result
                    ) > 0;

                if ($verify_result) {
                    mysqli_free_result(
                        $verify_result
                    );
                }

                mysqli_stmt_close(
                    $verify
                );


                if (!$valid) {

                    $message =
                        'Patient does not belong to this hospital.';

                    $message_type =
                        'error';

                } else {

                    $parts =
                        preg_split(
                            '/\s+/',
                            $name,
                            2
                        );

                    $first_name =
                        trim(
                            (string)(
                                $parts[0] ?? ''
                            )
                        );

                    $last_name =
                        trim(
                            (string)(
                                $parts[1] ?? ''
                            )
                        );

                    $dob_value =
                        $dob !== ''
                            ? $dob
                            : null;


                    mysqli_begin_transaction(
                        $conn
                    );

                    try {

                        /* Duplicate check */

                        $stmt =
                            mysqli_prepare(
                                $conn,

                                "SELECT account_id

                                 FROM {$central_identifier}.patient_accounts

                                 WHERE
                                 (
                                    mobile = ?

                                    OR
                                    (
                                        ? <> ''
                                        AND email = ?
                                    )
                                 )

                                 AND account_id <> ?

                                 LIMIT 1"
                            );

                        if (!$stmt) {
                            throw new Exception(
                                mysqli_error($conn)
                            );
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'sssi',
                            $mobile,
                            $email,
                            $email,
                            $account_id
                        );

                        mysqli_stmt_execute(
                            $stmt
                        );

                        $result =
                            mysqli_stmt_get_result(
                                $stmt
                            );

                        $duplicate =
                            $result &&
                            mysqli_num_rows(
                                $result
                            ) > 0;

                        if ($result) {
                            mysqli_free_result(
                                $result
                            );
                        }

                        mysqli_stmt_close($stmt);


                        if ($duplicate) {
                            throw new Exception(
                                'Another patient already uses this mobile number or email.'
                            );
                        }


                        /* Account */

                        $stmt =
                            mysqli_prepare(
                                $conn,

                                "UPDATE {$central_identifier}.patient_accounts

                                 SET
                                    first_name = ?,
                                    last_name = ?,
                                    email = ?,
                                    mobile = ?

                                 WHERE account_id = ?

                                 LIMIT 1"
                            );

                        if (!$stmt) {
                            throw new Exception(
                                mysqli_error($conn)
                            );
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'ssssi',
                            $first_name,
                            $last_name,
                            $email,
                            $mobile,
                            $account_id
                        );

                        if (
                            !mysqli_stmt_execute($stmt)
                        ) {

                            throw new Exception(
                                mysqli_stmt_error($stmt)
                            );
                        }

                        mysqli_stmt_close($stmt);


                        /* Profile */

                        $stmt =
                            mysqli_prepare(
                                $conn,

                                "UPDATE {$central_identifier}.patient_profiles

                                 SET
                                    gender = ?,
                                    date_of_birth = ?,
                                    blood_group = ?,
                                    address_line_1 = ?,
                                    city = ?,
                                    state = ?,
                                    emergency_contact_name = ?,
                                    emergency_contact_mobile = ?,
                                    emergency_relationship = ?

                                 WHERE account_id = ?

                                 LIMIT 1"
                            );

                        if (!$stmt) {
                            throw new Exception(
                                mysqli_error($conn)
                            );
                        }

                        mysqli_stmt_bind_param(
                            $stmt,
                            'sssssssssi',
                            $gender,
                            $dob_value,
                            $blood_group,
                            $address,
                            $city,
                            $state,
                            $emergency_name,
                            $emergency_mobile,
                            $emergency_relation,
                            $account_id
                        );

                        if (
                            !mysqli_stmt_execute($stmt)
                        ) {

                            throw new Exception(
                                mysqli_stmt_error($stmt)
                            );
                        }

                        mysqli_stmt_close($stmt);


                        mysqli_commit(
                            $conn
                        );

                        redirect_patients([
                            'success' => 'updated'
                        ]);

                    } catch (Throwable $e) {

                        mysqli_rollback(
                            $conn
                        );

                        $message =
                            'Unable to update patient: ' .
                            $e->getMessage();

                        $message_type =
                            'error';
                    }
                }
            }
        }
    }


    /* ========================================================
       DEACTIVATE
       ======================================================== */

    elseif (
        $action === 'deactivate_patient'
    ) {

        $mapping_id =
            (int)(
                $_POST['mapping_id'] ?? 0
            );

        $stmt =
            mysqli_prepare(
                $conn,

                "UPDATE {$central_identifier}.patient_hospital_mapping

                 SET patient_status = 'Inactive'

                 WHERE mapping_id = ?
                   AND hospital_id = ?

                 LIMIT 1"
            );

        if (!$stmt) {

            $message =
                mysqli_error($conn);

            $message_type =
                'error';

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                'ii',
                $mapping_id,
                $hospital_id
            );

            mysqli_stmt_execute(
                $stmt
            );

            $changed =
                mysqli_stmt_affected_rows(
                    $stmt
                ) > 0;

            mysqli_stmt_close(
                $stmt
            );

            if ($changed) {

                redirect_patients([
                    'success' => 'deactivated'
                ]);

            } else {

                $message =
                    'Patient not found.';

                $message_type =
                    'error';
            }
        }
    }


    /* ========================================================
       REACTIVATE
       ======================================================== */

    elseif (
        $action === 'reactivate_patient'
    ) {

        $mapping_id =
            (int)(
                $_POST['mapping_id'] ?? 0
            );

        $stmt =
            mysqli_prepare(
                $conn,

                "UPDATE {$central_identifier}.patient_hospital_mapping

                 SET patient_status = 'Active'

                 WHERE mapping_id = ?
                   AND hospital_id = ?

                 LIMIT 1"
            );

        if (!$stmt) {

            $message =
                mysqli_error($conn);

            $message_type =
                'error';

        } else {

            mysqli_stmt_bind_param(
                $stmt,
                'ii',
                $mapping_id,
                $hospital_id
            );

            mysqli_stmt_execute(
                $stmt
            );

            $changed =
                mysqli_stmt_affected_rows(
                    $stmt
                ) > 0;

            mysqli_stmt_close(
                $stmt
            );

            if ($changed) {

                redirect_patients([
                    'success' => 'reactivated'
                ]);

            } else {

                $message =
                    'Patient not found.';

                $message_type =
                    'error';
            }
        }
    }
}


/* ============================================================
   SUCCESS MESSAGES
   ============================================================ */

if (isset($_GET['success'])) {

    $success =
        (string)$_GET['success'];

    if ($success === 'added') {

        $message =
            'Patient added successfully.';

        $message_type =
            'success';

    } elseif ($success === 'updated') {

        $message =
            'Patient updated successfully.';

        $message_type =
            'success';

    } elseif ($success === 'deactivated') {

        $message =
            'Patient deactivated successfully.';

        $message_type =
            'success';

    } elseif ($success === 'reactivated') {

        $message =
            'Patient reactivated successfully.';

        $message_type =
            'success';
    }
}


/* ============================================================
   FILTERS
   ============================================================ */

$search =
    trim(
        (string)(
            $_GET['search'] ?? ''
        )
    );

$gender_filter =
    trim(
        (string)(
            $_GET['gender'] ?? ''
        )
    );

$status_filter =
    trim(
        (string)(
            $_GET['status'] ?? ''
        )
    );


$where = [
    'phm.hospital_id = ?'
];

$types = 'i';

$params = [
    $hospital_id
];


if ($search !== '') {

    $where[] = "
        (
            pa.first_name LIKE ?
            OR pa.last_name LIKE ?
            OR CONCAT(
                pa.first_name,
                ' ',
                pa.last_name
            ) LIKE ?
            OR pa.mobile LIKE ?
            OR phm.hospital_patient_code LIKE ?
        )
    ";

    $search_value =
        '%' . $search . '%';

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= 'sssss';
}


if (
    in_array(
        $gender_filter,
        [
            'Male',
            'Female',
            'Other'
        ],
        true
    )
) {

    $where[] =
        'pp.gender = ?';

    $params[] =
        $gender_filter;

    $types .= 's';
}


if (
    in_array(
        $status_filter,
        [
            'Active',
            'Inactive'
        ],
        true
    )
) {

    $where[] =
        'phm.patient_status = ?';

    $params[] =
        $status_filter;

    $types .= 's';
}


$where_sql =
    implode(
        ' AND ',
        $where
    );


/* ============================================================
   STATISTICS
   ============================================================ */

$total_patients =
    scalar_value(
        $conn,

        "SELECT COUNT(*)

         FROM {$central_identifier}.patient_hospital_mapping

         WHERE hospital_id = ?",

        'i',

        [
            $hospital_id
        ]
    );


$today_registration =
    scalar_value(
        $conn,

        "SELECT COUNT(*)

         FROM {$central_identifier}.patient_hospital_mapping

         WHERE hospital_id = ?

           AND DATE(registered_at) = CURDATE()",

        'i',

        [
            $hospital_id
        ]
    );


$admitted = 0;

if (
    hospital_table_exists(
        $hospital_conn,
        'admissions'
    )
) {

    $admitted =
        scalar_value(
            $hospital_conn,

            "SELECT COUNT(*)

             FROM admissions

             WHERE admission_status = 'Admitted'"
        );
}


$discharged = 0;

if (
    hospital_table_exists(
        $hospital_conn,
        'admissions'
    )
) {

    $discharged =
        scalar_value(
            $hospital_conn,

            "SELECT COUNT(*)

             FROM admissions

             WHERE admission_status = 'Discharged'"
        );
}


/* ============================================================
   LOAD PATIENTS
   ============================================================ */

$sql = "
    SELECT

        phm.mapping_id,
        phm.hospital_patient_code,
        phm.patient_status,
        phm.first_visit_date,
        phm.last_visit_date,
        phm.total_visits,

        pa.account_id,
        pa.first_name,
        pa.last_name,
        pa.email,
        pa.mobile,

        pp.gender,
        pp.date_of_birth,
        pp.blood_group,
        pp.profile_photo,
        pp.address_line_1,
        pp.city,
        pp.state,
        pp.emergency_contact_name,
        pp.emergency_contact_mobile,
        pp.emergency_relationship

    FROM {$central_identifier}.patient_hospital_mapping phm

    INNER JOIN {$central_identifier}.patient_accounts pa
        ON pa.account_id = phm.account_id

    LEFT JOIN {$central_identifier}.patient_profiles pp
        ON pp.account_id = pa.account_id

    WHERE {$where_sql}

    ORDER BY
        pa.first_name ASC,
        pa.last_name ASC
";


$stmt =
    mysqli_prepare(
        $conn,
        $sql
    );

if (!$stmt) {

    die(
        'Patient query prepare error: ' .
        mysqli_error($conn)
    );
}


bind_params_dynamic(
    $stmt,
    $types,
    $params
);


if (!mysqli_stmt_execute($stmt)) {

    die(
        'Patient query execution error: ' .
        mysqli_stmt_error($stmt)
    );
}


$result =
    mysqli_stmt_get_result($stmt);

$patients = [];


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $row['computed_visits'] =
            0;

        $row['computed_last_visit'] =
            null;

        $row['doctor_name'] =
            null;

        $row['department_name'] =
            null;

        $row['last_appointment_status'] =
            null;

        $row['active_admission'] =
            0;

        $patients[] =
            $row;
    }

    mysqli_free_result($result);
}

mysqli_stmt_close($stmt);


/* ============================================================
   HOSPITAL DATA
   ============================================================ */

foreach ($patients as &$patient) {

    $mapping_id =
        (int)(
            $patient['mapping_id'] ?? 0
        );

    if ($mapping_id <= 0) {
        continue;
    }


    /* --------------------------------------------------------
       COMPLETED VISITS
       -------------------------------------------------------- */

    $stmt =
        mysqli_prepare(
            $hospital_conn,

            "SELECT

                COUNT(*) AS completed_visits,

                MAX(appointment_date)
                    AS last_visit

             FROM appointments

             WHERE mapping_id = ?

               AND appointment_status = 'Completed'"
        );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $mapping_id
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        if ($result) {

            $row =
                mysqli_fetch_assoc($result);

            if ($row) {

                $patient['computed_visits'] =
                    (int)(
                        $row['completed_visits']
                        ?? 0
                    );

                $patient['computed_last_visit'] =
                    $row['last_visit']
                    ?? null;
            }

            mysqli_free_result($result);
        }

        mysqli_stmt_close($stmt);
    }


    /* --------------------------------------------------------
       LATEST APPOINTMENT
       -------------------------------------------------------- */

    $stmt =
        mysqli_prepare(
            $hospital_conn,

            "SELECT

                a.appointment_status,

                d.doctor_name,

                dept.department_name

             FROM appointments a

             LEFT JOIN doctors d
                ON d.doctor_id = a.doctor_id

             LEFT JOIN departments dept
                ON dept.department_id =
                   d.department_id

             WHERE a.mapping_id = ?

             ORDER BY
                a.appointment_date DESC,
                a.appointment_time DESC

             LIMIT 1"
        );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $mapping_id
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        if ($result) {

            $row =
                mysqli_fetch_assoc($result);

            if ($row) {

                $patient['last_appointment_status'] =
                    $row['appointment_status']
                    ?? null;

                $patient['doctor_name'] =
                    $row['doctor_name']
                    ?? null;

                $patient['department_name'] =
                    $row['department_name']
                    ?? null;
            }

            mysqli_free_result($result);
        }

        mysqli_stmt_close($stmt);
    }


    /* --------------------------------------------------------
       ACTIVE ADMISSION
       -------------------------------------------------------- */

    if (
        hospital_table_exists(
            $hospital_conn,
            'admissions'
        )
    ) {

        $stmt =
            mysqli_prepare(
                $hospital_conn,

                "SELECT COUNT(*)

                 FROM admissions

                 WHERE mapping_id = ?

                   AND admission_status = 'Admitted'"
            );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $mapping_id
            );

            mysqli_stmt_execute($stmt);

            $result =
                mysqli_stmt_get_result($stmt);

            if ($result) {

                $row =
                    mysqli_fetch_row($result);

                $patient['active_admission'] =
                    (int)(
                        $row[0] ?? 0
                    );

                mysqli_free_result($result);
            }

            mysqli_stmt_close($stmt);
        }
    }
}

unset($patient);


/* ============================================================
   HTML
   ============================================================ */

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
    Patients | <?= h($hospital_name) ?>
</title>


<link
    rel="stylesheet"
    href="css/sidebar.css"
>

<link
    rel="stylesheet"
    href="css/patients.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
>


<style>

/* =========================================================
   MESSAGE
========================================================= */

.page-message {
    margin-bottom:20px;
    padding:13px 16px;
    border-radius:10px;
    font-size:14px;
}

.page-message.success {
    background:#ecfdf5;
    color:#047857;
    border:1px solid #a7f3d0;
}

.page-message.error {
    background:#fef2f2;
    color:#b91c1c;
    border:1px solid #fecaca;
}


/* =========================================================
   ACTIONS
========================================================= */

.patient-actions {
    display:flex;
    align-items:center;
    gap:7px;
    white-space:nowrap;
}

.patient-action-btn {
    width:36px;
    height:36px;
    border:none;
    border-radius:9px;
    background:#f3f4f6;
    color:#4b5563;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:.2s;
}

.patient-action-btn:hover {
    transform:translateY(-1px);
}

.patient-action-btn.view:hover {
    background:#6c63ff;
    color:white;
}

.patient-action-btn.edit:hover {
    background:#3b82f6;
    color:white;
}

.patient-action-btn.deactivate:hover {
    background:#ef4444;
    color:white;
}

.patient-action-btn.activate:hover {
    background:#10b981;
    color:white;
}

.inline-form {
    display:inline;
    margin:0;
}


/* =========================================================
   AVATAR
========================================================= */

.patient-avatar-placeholder {
    width:50px;
    height:50px;
    min-width:50px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eeeaff;
    color:#6c63ff;
    font-weight:700;
}


/* =========================================================
   MODAL
========================================================= */

.patient-modal {
    position:fixed;
    inset:0;
    z-index:99999;
    display:none;
    align-items:center;
    justify-content:center;
    padding:20px;
    background:rgba(15,23,42,.55);
}

.patient-modal.active {
    display:flex;
}

.patient-modal-content {
    width:850px;
    max-width:96vw;
    max-height:92vh;
    overflow-y:auto;
    background:white;
    border-radius:18px;
    box-shadow:0 25px 70px rgba(0,0,0,.22);
}

.patient-modal-header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:22px 26px;
    border-bottom:1px solid #e5e7eb;
}

.patient-modal-header h2 {
    margin:0;
    font-size:21px;
    color:#1f2937;
}

.patient-modal-close {
    width:38px;
    height:38px;
    border:none;
    border-radius:9px;
    background:#f3f4f6;
    color:#6b7280;
    font-size:23px;
    cursor:pointer;
}


/* =========================================================
   FORM
========================================================= */

.patient-form {
    padding:26px;
}

.patient-form-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px;
}

.patient-form-group {
    display:flex;
    flex-direction:column;
}

.patient-form-group label {
    margin-bottom:7px;
    font-size:13px;
    font-weight:600;
    color:#374151;
}

.patient-form-group input,
.patient-form-group select {
    width:100%;
    height:46px;
    padding:0 13px;
    border:1px solid #d1d5db;
    border-radius:9px;
    outline:none;
    background:white;
    color:#374151;
    font-size:14px;
    box-sizing:border-box;
}

.patient-form-group input:focus,
.patient-form-group select:focus {
    border-color:#6c63ff;
    box-shadow:0 0 0 3px rgba(108,99,255,.08);
}

.patient-modal-footer {
    display:flex;
    justify-content:flex-end;
    gap:10px;
    padding:18px 26px;
    border-top:1px solid #e5e7eb;
}

.patient-cancel-btn,
.patient-save-btn {
    border:none;
    border-radius:9px;
    padding:11px 20px;
    font-size:14px;
    cursor:pointer;
}

.patient-cancel-btn {
    background:#e5e7eb;
    color:#374151;
}

.patient-save-btn {
    background:#6c63ff;
    color:white;
}


/* =========================================================
   VIEW
========================================================= */

.patient-view-wrapper {
    padding:26px;
}

.patient-view-top {
    display:flex;
    align-items:center;
    gap:17px;
    padding:18px;
    margin-bottom:20px;
    border-radius:14px;
    background:#f8fafc;
}

.patient-view-avatar {
    width:65px;
    height:65px;
    min-width:65px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eeeaff;
    color:#6c63ff;
    font-weight:700;
    font-size:20px;
}

.patient-view-top h3 {
    margin:0 0 5px;
    color:#111827;
}

.patient-view-top p {
    margin:0;
    color:#6b7280;
    font-size:13px;
}

.patient-view-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:1px;
    overflow:hidden;
    border:1px solid #e5e7eb;
    border-radius:12px;
    background:#e5e7eb;
}

.patient-view-item {
    padding:15px;
    background:white;
}

.patient-view-item span {
    display:block;
    margin-bottom:5px;
    color:#9ca3af;
    font-size:11px;
}

.patient-view-item strong {
    color:#1f2937;
    font-size:14px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:800px) {

    .patient-form-grid {
        grid-template-columns:1fr;
    }

    .patient-view-grid {
        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<div class="dashboard">


<?php require __DIR__ . '/sidebar.php'; ?>


<main class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

    <div>

        <h1>Patients</h1>

        <p>
            Manage hospital patients and their hospital-specific history.
        </p>

    </div>


    <button
        type="button"
        class="add-btn"
        id="addPatientBtn"
    >

        <i class="fa-solid fa-plus"></i>

        Add Patient

    </button>

</div>


<!-- =====================================================
     MESSAGE
===================================================== -->

<?php if ($message !== ''): ?>

<div class="page-message <?= h($message_type) ?>">

    <?= h($message) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="stats-container">


<div class="stat-card">

    <div class="stat-icon total">

        <i class="fa-solid fa-bed"></i>

    </div>

    <div class="stat-info">

        <h4>Total Patients</h4>

        <h2>
            <?= number_format($total_patients) ?>
        </h2>

        <p>
            Registered at this hospital
        </p>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon admitted">

        <i class="fa-solid fa-hospital-user"></i>

    </div>

    <div class="stat-info">

        <h4>Admitted Patients</h4>

        <h2>
            <?= number_format($admitted) ?>
        </h2>

        <p>
            Currently admitted
        </p>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon discharged">

        <i class="fa-solid fa-user-check"></i>

    </div>

    <div class="stat-info">

        <h4>Discharged</h4>

        <h2>
            <?= number_format($discharged) ?>
        </h2>

        <p>
            Discharged admissions
        </p>

    </div>

</div>


<div class="stat-card">

    <div class="stat-icon today">

        <i class="fa-solid fa-user-plus"></i>

    </div>

    <div class="stat-info">

        <h4>Today's Registration</h4>

        <h2>
            <?= number_format($today_registration) ?>
        </h2>

        <p>
            New hospital registrations
        </p>

    </div>

</div>


</div>


<!-- =====================================================
     FILTER
===================================================== -->

<form
    method="get"
    class="toolbar"
>


<div class="patient-search-box">

    <i class="fa-solid fa-magnifying-glass"></i>

    <input
        type="text"
        name="search"
        value="<?= h($search) ?>"
        placeholder="Search patient..."
    >

</div>


<div class="patient-filter-box">

    <i class="fa-solid fa-venus-mars"></i>

    <select name="gender">

        <option value="">
            All Gender
        </option>

        <option
            value="Male"
            <?= $gender_filter === 'Male'
                ? 'selected'
                : ''
            ?>
        >
            Male
        </option>

        <option
            value="Female"
            <?= $gender_filter === 'Female'
                ? 'selected'
                : ''
            ?>
        >
            Female
        </option>

        <option
            value="Other"
            <?= $gender_filter === 'Other'
                ? 'selected'
                : ''
            ?>
        >
            Other
        </option>

    </select>

</div>


<div class="patient-filter-box">

    <i class="fa-solid fa-filter"></i>

    <select name="status">

        <option value="">
            All Status
        </option>

        <option
            value="Active"
            <?= $status_filter === 'Active'
                ? 'selected'
                : ''
            ?>
        >
            Active
        </option>

        <option
            value="Inactive"
            <?= $status_filter === 'Inactive'
                ? 'selected'
                : ''
            ?>
        >
            Inactive
        </option>

    </select>

</div>


<button
    type="submit"
    class="patient-filter-btn"
>

    <i class="fa-solid fa-filter"></i>

    Filter

</button>


<a
    href="patients.php"
    class="patient-reset-btn"
>
    Reset
</a>


</form>


<!-- =====================================================
     TABLE
===================================================== -->

<div class="table-container">

<table>

<thead>

<tr>

<th>Patient</th>

<th>Patient ID</th>

<th>Age</th>

<th>Gender</th>

<th>Department</th>

<th>Doctor</th>

<th>Contact</th>

<th>Visits</th>

<th>Status</th>

<th>Actions</th>

</tr>

</thead>


<tbody>


<?php if (empty($patients)): ?>

<tr>

<td
    colspan="10"
    class="no-records"
>
    No patients found.
</td>

</tr>

<?php else: ?>


<?php foreach ($patients as $patient): ?>


<?php

$age = '—';

if (
    !empty(
        $patient['date_of_birth']
    )
) {

    try {

        $dob =
            new DateTime(
                $patient['date_of_birth']
            );

        $today =
            new DateTime();

        $age =
            $dob
                ->diff($today)
                ->y;

    } catch (Throwable $e) {

        $age = '—';
    }
}


if (
    (int)(
        $patient['active_admission']
        ?? 0
    ) > 0
) {

    $display_status =
        'Admitted';

} elseif (
    (
        $patient['patient_status']
        ?? ''
    ) === 'Active'
) {

    $display_status =
        'Registered';

} else {

    $display_status =
        'Inactive';
}


$first =
    (string)(
        $patient['first_name']
        ?? ''
    );

$last =
    (string)(
        $patient['last_name']
        ?? ''
    );

$full_name =
    trim(
        $first .
        ' ' .
        $last
    );


$initials =
    strtoupper(
        substr($first,0,1) .
        substr($last,0,1)
    );

if ($initials === '') {
    $initials = 'PT';
}


$visits =
    (int)(
        $patient['computed_visits']
        ?? 0
    );


$data_patient =
    patient_json($patient);

?>


<tr>


<td>

<div class="patient-info">


<?php if (
    !empty(
        $patient['profile_photo']
    )
): ?>

<img
    src="<?= h(
        $patient['profile_photo']
    ) ?>"
    alt="Patient"
>


<?php else: ?>

<div class="patient-avatar-placeholder">

    <?= h($initials) ?>

</div>

<?php endif; ?>


<div>

<h4>

<?= h($full_name) ?>

</h4>

<span>

Blood Group :
<?= h(
    $patient['blood_group']
    ?? 'Unknown'
) ?>

</span>

</div>

</div>

</td>


<td>

<?= h(
    $patient[
        'hospital_patient_code'
    ] ?? '—'
) ?>

</td>


<td>

<?= h($age) ?>

</td>


<td>

<?= h(
    $patient['gender']
    ?? '—'
) ?>

</td>


<td>

<?= h(
    $patient['department_name']
    ?? '—'
) ?>

</td>


<td>

<?= h(
    $patient['doctor_name']
    ?? '—'
) ?>

</td>


<td>

<?= h(
    $patient['mobile']
    ?? '—'
) ?>

</td>


<td>

<?= number_format($visits) ?>

</td>


<td>

<span
    class="status
    <?=
        $display_status === 'Admitted'
            ? 'admitted'
            : (
                $display_status === 'Inactive'
                    ? 'discharged'
                    : 'registered'
            )
    ?>"
>

<?= h($display_status) ?>

</span>

</td>


<td>

<div class="patient-actions">


<!-- VIEW -->

<button
    type="button"
    class="patient-action-btn view"
    title="View Patient"
    data-patient="<?= $data_patient ?>"
>

<i class="fa-solid fa-eye"></i>

</button>


<!-- EDIT -->

<button
    type="button"
    class="patient-action-btn edit"
    title="Edit Patient"
    data-patient="<?= $data_patient ?>"
>

<i class="fa-solid fa-pen"></i>

</button>


<!-- DEACTIVATE -->

<?php if (
    (
        $patient['patient_status']
        ?? ''
    ) === 'Active'
): ?>

<form
    method="post"
    class="inline-form"
    onsubmit="
        return confirm(
            'Deactivate this patient from this hospital?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="deactivate_patient"
>

<input
    type="hidden"
    name="mapping_id"
    value="<?= (int)(
        $patient['mapping_id']
    ) ?>"
>


<button
    type="submit"
    class="patient-action-btn deactivate"
    title="Deactivate"
>

<i class="fa-solid fa-user-minus"></i>

</button>

</form>


<?php else: ?>


<form
    method="post"
    class="inline-form"
>

<input
    type="hidden"
    name="action"
    value="reactivate_patient"
>

<input
    type="hidden"
    name="mapping_id"
    value="<?= (int)(
        $patient['mapping_id']
    ) ?>"
>


<button
    type="submit"
    class="patient-action-btn activate"
    title="Reactivate"
>

<i class="fa-solid fa-user-check"></i>

</button>

</form>


<?php endif; ?>


</div>

</td>

</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>

</table>

</div>


</main>

</div>


<!-- ============================================================
     ADD PATIENT MODAL
============================================================ -->

<div
    class="patient-modal"
    id="addPatientModal"
>

<div class="patient-modal-content">


<div class="patient-modal-header">

<h2>
    Add New Patient
</h2>

<button
    type="button"
    class="patient-modal-close"
    data-close="addPatientModal"
>
    &times;
</button>

</div>


<form
    method="post"
    class="patient-form"
>


<input
    type="hidden"
    name="action"
    value="add_patient"
>


<div class="patient-form-grid">


<div class="patient-form-group">

<label>
    Full Name *
</label>

<input
    type="text"
    name="patient_name"
    required
>

</div>


<div class="patient-form-group">

<label>
    Mobile *
</label>

<input
    type="text"
    name="mobile"
    required
>

</div>


<div class="patient-form-group">

<label>
    Email
</label>

<input
    type="email"
    name="email"
>

</div>


<div class="patient-form-group">

<label>
    Gender *
</label>

<select
    name="gender"
    required
>

<option value="">
    Select Gender
</option>

<option value="Male">
    Male
</option>

<option value="Female">
    Female
</option>

<option value="Other">
    Other
</option>

</select>

</div>


<div class="patient-form-group">

<label>
    Date of Birth
</label>

<input
    type="date"
    name="date_of_birth"
>

</div>


<div class="patient-form-group">

<label>
    Blood Group
</label>

<select name="blood_group">

<option value="Unknown">
    Unknown
</option>

<option value="A+">A+</option>
<option value="A-">A-</option>
<option value="B+">B+</option>
<option value="B-">B-</option>
<option value="AB+">AB+</option>
<option value="AB-">AB-</option>
<option value="O+">O+</option>
<option value="O-">O-</option>

</select>

</div>


<div class="patient-form-group">

<label>
    Address
</label>

<input
    type="text"
    name="address_line_1"
>

</div>


<div class="patient-form-group">

<label>
    City
</label>

<input
    type="text"
    name="city"
>

</div>


<div class="patient-form-group">

<label>
    State
</label>

<input
    type="text"
    name="state"
>

</div>


<div class="patient-form-group">

<label>
    Emergency Contact
</label>

<input
    type="text"
    name="emergency_contact_name"
>

</div>


<div class="patient-form-group">

<label>
    Emergency Mobile
</label>

<input
    type="text"
    name="emergency_contact_mobile"
>

</div>


<div class="patient-form-group">

<label>
    Relationship
</label>

<input
    type="text"
    name="emergency_relationship"
>

</div>


</div>


<div class="patient-modal-footer">

<button
    type="button"
    class="patient-cancel-btn"
    data-close="addPatientModal"
>
    Cancel
</button>


<button
    type="submit"
    class="patient-save-btn"
>

<i class="fa-solid fa-user-plus"></i>

Save Patient

</button>

</div>


</form>

</div>

</div>


<!-- ============================================================
     EDIT PATIENT MODAL
============================================================ -->

<div
    class="patient-modal"
    id="editPatientModal"
>

<div class="patient-modal-content">


<div class="patient-modal-header">

<h2>
    Edit Patient
</h2>

<button
    type="button"
    class="patient-modal-close"
    data-close="editPatientModal"
>
    &times;
</button>

</div>


<form
    method="post"
    class="patient-form"
>


<input
    type="hidden"
    name="action"
    value="edit_patient"
>

<input
    type="hidden"
    name="mapping_id"
    id="editMappingId"
>

<input
    type="hidden"
    name="account_id"
    id="editAccountId"
>


<div class="patient-form-grid">


<div class="patient-form-group">

<label>
    Full Name *
</label>

<input
    type="text"
    name="patient_name"
    id="editPatientName"
    required
>

</div>


<div class="patient-form-group">

<label>
    Mobile *
</label>

<input
    type="text"
    name="mobile"
    id="editMobile"
    required
>

</div>


<div class="patient-form-group">

<label>
    Email
</label>

<input
    type="email"
    name="email"
    id="editEmail"
>

</div>


<div class="patient-form-group">

<label>
    Gender *
</label>

<select
    name="gender"
    id="editGender"
    required
>

<option value="">
    Select Gender
</option>

<option value="Male">
    Male
</option>

<option value="Female">
    Female
</option>

<option value="Other">
    Other
</option>

</select>

</div>


<div class="patient-form-group">

<label>
    Date of Birth
</label>

<input
    type="date"
    name="date_of_birth"
    id="editDob"
>

</div>


<div class="patient-form-group">

<label>
    Blood Group
</label>

<select
    name="blood_group"
    id="editBloodGroup"
>

<option value="Unknown">
    Unknown
</option>

<option value="A+">A+</option>
<option value="A-">A-</option>
<option value="B+">B+</option>
<option value="B-">B-</option>
<option value="AB+">AB+</option>
<option value="AB-">AB-</option>
<option value="O+">O+</option>
<option value="O-">O-</option>

</select>

</div>


<div class="patient-form-group">

<label>
    Address
</label>

<input
    type="text"
    name="address_line_1"
    id="editAddress"
>

</div>


<div class="patient-form-group">

<label>
    City
</label>

<input
    type="text"
    name="city"
    id="editCity"
>

</div>


<div class="patient-form-group">

<label>
    State
</label>

<input
    type="text"
    name="state"
    id="editState"
>

</div>


<div class="patient-form-group">

<label>
    Emergency Contact
</label>

<input
    type="text"
    name="emergency_contact_name"
    id="editEmergencyName"
>

</div>


<div class="patient-form-group">

<label>
    Emergency Mobile
</label>

<input
    type="text"
    name="emergency_contact_mobile"
    id="editEmergencyMobile"
>

</div>


<div class="patient-form-group">

<label>
    Relationship
</label>

<input
    type="text"
    name="emergency_relationship"
    id="editEmergencyRelation"
>

</div>


</div>


<div class="patient-modal-footer">

<button
    type="button"
    class="patient-cancel-btn"
    data-close="editPatientModal"
>
    Cancel
</button>


<button
    type="submit"
    class="patient-save-btn"
>

<i class="fa-solid fa-save"></i>

Update Patient

</button>

</div>


</form>

</div>

</div>


<!-- ============================================================
     VIEW PATIENT MODAL
============================================================ -->

<div
    class="patient-modal"
    id="viewPatientModal"
>

<div class="patient-modal-content">


<div class="patient-modal-header">

<h2>
    Patient Details
</h2>

<button
    type="button"
    class="patient-modal-close"
    data-close="viewPatientModal"
>
    &times;
</button>

</div>


<div
    class="patient-view-wrapper"
    id="patientViewContent"
>
</div>


</div>

</div>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {


        function openModal(id) {

            const modal =
                document.getElementById(id);

            if (!modal) {
                return;
            }

            modal.classList.add('active');

            document.body.style.overflow =
                'hidden';
        }


        function closeModal(id) {

            const modal =
                document.getElementById(id);

            if (!modal) {
                return;
            }

            modal.classList.remove('active');

            document.body.style.overflow =
                '';
        }


        /* ADD */

        const addButton =
            document.getElementById(
                'addPatientBtn'
            );

        if (addButton) {

            addButton.addEventListener(
                'click',
                function () {

                    openModal(
                        'addPatientModal'
                    );

                }
            );
        }


        /* CLOSE */

        document
            .querySelectorAll(
                '[data-close]'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            closeModal(
                                this.dataset.close
                            );

                        }
                    );

                }
            );


        /* OUTSIDE CLICK */

        document
            .querySelectorAll(
                '.patient-modal'
            )
            .forEach(
                function (modal) {

                    modal.addEventListener(
                        'click',
                        function (event) {

                            if (
                                event.target ===
                                modal
                            ) {

                                modal.classList.remove(
                                    'active'
                                );

                                document.body.style.overflow =
                                    '';

                            }

                        }
                    );

                }
            );


        /* ESC */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                ) {

                    document
                        .querySelectorAll(
                            '.patient-modal.active'
                        )
                        .forEach(
                            function (modal) {

                                modal.classList.remove(
                                    'active'
                                );

                            }
                        );

                    document.body.style.overflow =
                        '';

                }

            }
        );


        /* =====================================================
           EDIT
        ===================================================== */

        document
            .querySelectorAll(
                '.patient-action-btn.edit'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            let patient;

                            try {

                                patient =
                                    JSON.parse(
                                        this.dataset.patient
                                    );

                            } catch (error) {

                                alert(
                                    'Unable to load patient details.'
                                );

                                return;
                            }


                            document.getElementById(
                                'editMappingId'
                            ).value =
                                patient.mapping_id || '';


                            document.getElementById(
                                'editAccountId'
                            ).value =
                                patient.account_id || '';


                            document.getElementById(
                                'editPatientName'
                            ).value =
                                (
                                    patient.first_name || ''
                                ) +
                                (
                                    patient.last_name
                                        ? ' ' +
                                          patient.last_name
                                        : ''
                                );


                            document.getElementById(
                                'editMobile'
                            ).value =
                                patient.mobile || '';


                            document.getElementById(
                                'editEmail'
                            ).value =
                                patient.email || '';


                            document.getElementById(
                                'editGender'
                            ).value =
                                patient.gender || '';


                            document.getElementById(
                                'editDob'
                            ).value =
                                patient.date_of_birth || '';


                            document.getElementById(
                                'editBloodGroup'
                            ).value =
                                patient.blood_group ||
                                'Unknown';


                            document.getElementById(
                                'editAddress'
                            ).value =
                                patient.address_line_1 ||
                                '';


                            document.getElementById(
                                'editCity'
                            ).value =
                                patient.city ||
                                '';


                            document.getElementById(
                                'editState'
                            ).value =
                                patient.state ||
                                '';


                            document.getElementById(
                                'editEmergencyName'
                            ).value =
                                patient.emergency_contact_name ||
                                '';


                            document.getElementById(
                                'editEmergencyMobile'
                            ).value =
                                patient.emergency_contact_mobile ||
                                '';


                            document.getElementById(
                                'editEmergencyRelation'
                            ).value =
                                patient.emergency_relationship ||
                                '';


                            openModal(
                                'editPatientModal'
                            );

                        }
                    );

                }
            );


        /* =====================================================
           VIEW
        ===================================================== */

        document
            .querySelectorAll(
                '.patient-action-btn.view'
            )
            .forEach(
                function (button) {

                    button.addEventListener(
                        'click',
                        function () {

                            let patient;

                            try {

                                patient =
                                    JSON.parse(
                                        this.dataset.patient
                                    );

                            } catch (error) {

                                alert(
                                    'Unable to load patient details.'
                                );

                                return;
                            }


                            const first =
                                patient.first_name || '';

                            const last =
                                patient.last_name || '';

                            const fullName =
                                (
                                    first +
                                    ' ' +
                                    last
                                ).trim();


                            let initials =
                                (
                                    first.charAt(0) +
                                    last.charAt(0)
                                ).toUpperCase();


                            if (!initials) {
                                initials = 'PT';
                            }


                            let age = '—';


                            if (
                                patient.date_of_birth
                            ) {

                                const dob =
                                    new Date(
                                        patient.date_of_birth +
                                        'T00:00:00'
                                    );

                                if (
                                    !isNaN(
                                        dob.getTime()
                                    )
                                ) {

                                    const today =
                                        new Date();

                                    age =
                                        today.getFullYear()
                                        -
                                        dob.getFullYear();

                                    const month =
                                        today.getMonth()
                                        -
                                        dob.getMonth();

                                    if (
                                        month < 0 ||
                                        (
                                            month === 0 &&
                                            today.getDate()
                                            <
                                            dob.getDate()
                                        )
                                    ) {

                                        age--;

                                    }
                                }
                            }


                            let status =
                                'Inactive';


                            if (
                                Number(
                                    patient.active_admission ||
                                    0
                                ) > 0
                            ) {

                                status =
                                    'Admitted';

                            } else if (
                                patient.patient_status ===
                                'Active'
                            ) {

                                status =
                                    'Registered';
                            }


                            const visits =
                                patient.computed_visits ??
                                patient.total_visits ??
                                0;


                            const content =
                                document.getElementById(
                                    'patientViewContent'
                                );


                            content.innerHTML = `

                                <div class="patient-view-top">

                                    <div class="patient-view-avatar">

                                        ${escapeHtml(
                                            initials
                                        )}

                                    </div>

                                    <div>

                                        <h3>

                                            ${escapeHtml(
                                                fullName ||
                                                'Patient'
                                            )}

                                        </h3>

                                        <p>

                                            ${escapeHtml(
                                                patient.hospital_patient_code ||
                                                ''
                                            )}

                                        </p>

                                    </div>

                                </div>


                                <div class="patient-view-grid">

                                    ${detail(
                                        'Mobile',
                                        patient.mobile
                                    )}

                                    ${detail(
                                        'Email',
                                        patient.email
                                    )}

                                    ${detail(
                                        'Age',
                                        age
                                    )}

                                    ${detail(
                                        'Gender',
                                        patient.gender
                                    )}

                                    ${detail(
                                        'Blood Group',
                                        patient.blood_group
                                    )}

                                    ${detail(
                                        'Department',
                                        patient.department_name
                                    )}

                                    ${detail(
                                        'Doctor',
                                        patient.doctor_name
                                    )}

                                    ${detail(
                                        'Completed Visits',
                                        visits
                                    )}

                                    ${detail(
                                        'Last Visit',
                                        patient.computed_last_visit ||
                                        patient.last_visit_date ||
                                        '—'
                                    )}

                                    ${detail(
                                        'Status',
                                        status
                                    )}

                                    ${detail(
                                        'Address',
                                        patient.address_line_1
                                    )}

                                    ${detail(
                                        'City',
                                        patient.city
                                    )}

                                    ${detail(
                                        'State',
                                        patient.state
                                    )}

                                    ${detail(
                                        'Emergency Contact',
                                        patient.emergency_contact_name
                                    )}

                                    ${detail(
                                        'Emergency Mobile',
                                        patient.emergency_contact_mobile
                                    )}

                                    ${detail(
                                        'Relationship',
                                        patient.emergency_relationship
                                    )}

                                </div>

                            `;


                            openModal(
                                'viewPatientModal'
                            );

                        }
                    );

                }
            );


        function detail(
            label,
            value
        ) {

            return `

                <div class="patient-view-item">

                    <span>
                        ${escapeHtml(label)}
                    </span>

                    <strong>
                        ${escapeHtml(value)}
                    </strong>

                </div>

            `;
        }


        function escapeHtml(value) {

            if (
                value === null ||
                value === undefined ||
                value === ''
            ) {
                return '—';
            }

            return String(value)
                .replace(
                    /&/g,
                    '&amp;'
                )
                .replace(
                    /</g,
                    '&lt;'
                )
                .replace(
                    />/g,
                    '&gt;'
                )
                .replace(
                    /"/g,
                    '&quot;'
                )
                .replace(
                    /'/g,
                    '&#039;'
                );
        }

    }
);

</script>


</body>

</html>