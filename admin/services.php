<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';


/*
|--------------------------------------------------------------------------
| CENTRAL DATABASE
|--------------------------------------------------------------------------
*/

$conn = db_connect();

if (!$conn) {
    die(
        "Central database connection failed: " .
        mysqli_connect_error()
    );
}

mysqli_set_charset(
    $conn,
    "utf8mb4"
);


/*
|--------------------------------------------------------------------------
| CURRENT HOSPITAL
|--------------------------------------------------------------------------
*/

$application_no =
    $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die(
        "Hospital application number not found in session."
    );
}


/*
|--------------------------------------------------------------------------
| GET HOSPITAL DATABASE
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare(
    $conn,
    "SELECT database_name
     FROM hospital_registration
     WHERE application_no = ?
     LIMIT 1"
);

if (!$stmt) {
    die(
        "Unable to prepare hospital database query."
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $application_no
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$hospital =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (
    !$hospital ||
    empty($hospital['database_name'])
) {
    die(
        "Hospital database not found."
    );
}


$hospital_db =
    $hospital['database_name'];


/*
|--------------------------------------------------------------------------
| VALIDATE DATABASE NAME
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_db
    )
) {
    die(
        "Invalid hospital database configuration."
    );
}


/*
|--------------------------------------------------------------------------
| HOSPITAL DATABASE
|--------------------------------------------------------------------------
*/

$hospital_conn = db_connect($hospital_db);

if (!$hospital_conn) {
    die(
        "Hospital database connection failed: " .
        mysqli_connect_error()
    );
}

mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE DEPARTMENT PREFIX
|--------------------------------------------------------------------------
*/

function generateDepartmentPrefix(
    string $department_name
): string {

    $department_name =
        trim(
            $department_name
        );


    $clean =
        preg_replace(
            '/[^A-Za-z0-9\s]/',
            ' ',
            $department_name
        );


    $clean =
        preg_replace(
            '/\s+/',
            ' ',
            $clean
        );


    $clean =
        trim(
            $clean
        );


    if ($clean === '') {
        return 'GEN';
    }


    if (
        preg_match(
            '/^[A-Z0-9]{2,5}$/',
            $department_name
        )
    ) {

        return strtoupper(
            $department_name
        );
    }


    $words =
        preg_split(
            '/\s+/',
            $clean
        );


    $ignored_words = [
        'and',
        'of',
        'the',
        'for',
        'department'
    ];


    foreach (
        $words
        as $word
    ) {

        if (
            in_array(
                strtolower($word),
                $ignored_words,
                true
            )
        ) {
            continue;
        }


        $letters =
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $word
            );


        if (
            strlen($letters) >= 3
        ) {

            return strtoupper(
                substr(
                    $letters,
                    0,
                    3
                )
            );
        }
    }


    return strtoupper(
        substr(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                $clean
            ),
            0,
            3
        )
    );
}


/*
|--------------------------------------------------------------------------
| GENERATE SERVICE TYPE PREFIX
|--------------------------------------------------------------------------
*/

function generateServiceTypePrefix(
    string $service_type
): string {

    $type =
        strtolower(
            trim(
                $service_type
            )
        );


    if (
        strpos(
            $type,
            'consult'
        ) !== false
    ) {

        return 'CONSULT';
    }


    if (
        strpos(
            $type,
            'laboratory'
        ) !== false ||
        strpos(
            $type,
            'lab'
        ) !== false
    ) {

        return 'LAB';
    }


    if (
        strpos(
            $type,
            'x-ray'
        ) !== false ||
        strpos(
            $type,
            'xray'
        ) !== false
    ) {

        return 'XRAY';
    }


    if (
        strpos(
            $type,
            'mri'
        ) !== false
    ) {

        return 'MRI';
    }


    if (
        strpos(
            $type,
            'ct'
        ) !== false
    ) {

        return 'CT';
    }


    if (
        strpos(
            $type,
            'ultrasound'
        ) !== false
    ) {

        return 'US';
    }


    if (
        strpos(
            $type,
            'diagnostic'
        ) !== false
    ) {

        return 'DIAG';
    }


    if (
        strpos(
            $type,
            'imaging'
        ) !== false
    ) {

        return 'IMG';
    }


    if (
        strpos(
            $type,
            'pharmacy'
        ) !== false
    ) {

        return 'PHARM';
    }


    if (
        strpos(
            $type,
            'procedure'
        ) !== false
    ) {

        return 'PROC';
    }


    $clean =
        preg_replace(
            '/[^A-Za-z0-9]/',
            '',
            $service_type
        );


    if (
        strlen($clean) >= 3
    ) {

        return strtoupper(
            substr(
                $clean,
                0,
                4
            )
        );
    }


    return 'SERV';
}


/*
|--------------------------------------------------------------------------
| GENERATE UNIQUE SERVICE CODE
|--------------------------------------------------------------------------
*/

function generateServiceCode(
    mysqli $hospital_conn,
    int $department_id,
    string $service_type
): string {

    $department_stmt =
        mysqli_prepare(
            $hospital_conn,
            "SELECT department_name
             FROM departments
             WHERE department_id = ?
             LIMIT 1"
        );


    if (!$department_stmt) {

        throw new Exception(
            "Unable to prepare department query."
        );
    }


    mysqli_stmt_bind_param(
        $department_stmt,
        "i",
        $department_id
    );


    mysqli_stmt_execute(
        $department_stmt
    );


    $department_result =
        mysqli_stmt_get_result(
            $department_stmt
        );


    $department =
        mysqli_fetch_assoc(
            $department_result
        );


    mysqli_stmt_close(
        $department_stmt
    );


    if (!$department) {

        throw new Exception(
            "Selected department was not found."
        );
    }


    $department_prefix =
        generateDepartmentPrefix(
            $department['department_name']
        );


    $type_prefix =
        generateServiceTypePrefix(
            $service_type
        );


    $base_code =
        $department_prefix .
        '-' .
        $type_prefix;


    $number = 1;


    $pattern =
        $base_code .
        '-%';


    $code_stmt =
        mysqli_prepare(
            $hospital_conn,
            "SELECT service_code
             FROM services
             WHERE service_code LIKE ?
             ORDER BY service_id DESC"
        );


    if (!$code_stmt) {

        throw new Exception(
            "Unable to prepare service code query."
        );
    }


    mysqli_stmt_bind_param(
        $code_stmt,
        "s",
        $pattern
    );


    mysqli_stmt_execute(
        $code_stmt
    );


    $code_result =
        mysqli_stmt_get_result(
            $code_stmt
        );


    while (
        $code_row =
        mysqli_fetch_assoc(
            $code_result
        )
    ) {

        $existing_code =
            $code_row['service_code'];


        $prefix_length =
            strlen(
                $base_code . '-'
            );


        $existing_number =
            substr(
                $existing_code,
                $prefix_length
            );


        if (
            ctype_digit(
                $existing_number
            )
        ) {

            $existing_number =
                (int)$existing_number;


            if (
                $existing_number >=
                $number
            ) {

                $number =
                    $existing_number + 1;
            }
        }
    }


    mysqli_stmt_close(
        $code_stmt
    );


    return sprintf(
        "%s-%03d",
        $base_code,
        $number
    );
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $action =
        $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | DELETE / ARCHIVE SERVICE
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'delete_service'
    ) {

        $service_id =
            (int)(
                $_POST['service_id']
                ?? 0
            );


        if (
            $service_id <= 0
        ) {

            $_SESSION['service_message'] =
                "Invalid service selected.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        mysqli_begin_transaction(
            $hospital_conn
        );


        try {

            /*
            |--------------------------------------------------------------------------
            | GET SERVICE
            |--------------------------------------------------------------------------
            */

            $service_check =
                mysqli_prepare(
                    $hospital_conn,
                    "SELECT
                        service_id,
                        service_name,
                        service_code,
                        status
                     FROM services
                     WHERE service_id = ?
                     LIMIT 1"
                );


            if (!$service_check) {

                throw new Exception(
                    "Unable to check service."
                );
            }


            mysqli_stmt_bind_param(
                $service_check,
                "i",
                $service_id
            );


            mysqli_stmt_execute(
                $service_check
            );


            $service_result =
                mysqli_stmt_get_result(
                    $service_check
                );


            $service =
                mysqli_fetch_assoc(
                    $service_result
                );


            mysqli_stmt_close(
                $service_check
            );


            if (!$service) {

                throw new Exception(
                    "Service was not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | CHECK APPOINTMENTS
            |--------------------------------------------------------------------------
            */

            $appointment_check =
                mysqli_prepare(
                    $hospital_conn,
                    "SELECT COUNT(*) AS total
                     FROM appointments
                     WHERE service_id = ?"
                );


            if (!$appointment_check) {

                throw new Exception(
                    "Unable to check service appointments."
                );
            }


            mysqli_stmt_bind_param(
                $appointment_check,
                "i",
                $service_id
            );


            mysqli_stmt_execute(
                $appointment_check
            );


            $appointment_result =
                mysqli_stmt_get_result(
                    $appointment_check
                );


            $appointment_row =
                mysqli_fetch_assoc(
                    $appointment_result
                );


            $appointment_count =
                (int)(
                    $appointment_row['total']
                    ?? 0
                );


            mysqli_stmt_close(
                $appointment_check
            );


            /*
            |--------------------------------------------------------------------------
            | DELETE DOCTOR-SERVICE MAPPINGS
            |--------------------------------------------------------------------------
            */

            $delete_mappings =
                mysqli_prepare(
                    $hospital_conn,
                    "DELETE FROM doctor_services
                     WHERE service_id = ?"
                );


            if (!$delete_mappings) {

                throw new Exception(
                    "Unable to prepare doctor-service deletion."
                );
            }


            mysqli_stmt_bind_param(
                $delete_mappings,
                "i",
                $service_id
            );


            if (
                !mysqli_stmt_execute(
                    $delete_mappings
                )
            ) {

                $mapping_error =
                    mysqli_stmt_error(
                        $delete_mappings
                    );


                mysqli_stmt_close(
                    $delete_mappings
                );


                throw new Exception(
                    "Unable to delete doctor-service mappings: " .
                    $mapping_error
                );
            }


            mysqli_stmt_close(
                $delete_mappings
            );


            /*
            |--------------------------------------------------------------------------
            | IF APPOINTMENTS EXIST
            |--------------------------------------------------------------------------
            |
            | Do NOT physically delete the service.
            |
            | Existing appointments must continue to reference
            | this service for historical records.
            |
            */

            if (
                $appointment_count > 0
            ) {

                $archive_service =
                    mysqli_prepare(
                        $hospital_conn,
                        "UPDATE services
                         SET status = 'Inactive',
                             updated_at = CURRENT_TIMESTAMP
                         WHERE service_id = ?"
                    );


                if (!$archive_service) {

                    throw new Exception(
                        "Unable to prepare service archive."
                    );
                }


                mysqli_stmt_bind_param(
                    $archive_service,
                    "i",
                    $service_id
                );


                if (
                    !mysqli_stmt_execute(
                        $archive_service
                    )
                ) {

                    $archive_error =
                        mysqli_stmt_error(
                            $archive_service
                        );


                    mysqli_stmt_close(
                        $archive_service
                    );


                    throw new Exception(
                        "Unable to archive service: " .
                        $archive_error
                    );
                }


                mysqli_stmt_close(
                    $archive_service
                );


                mysqli_commit(
                    $hospital_conn
                );


                $_SESSION['service_message'] =
                    "This service has existing appointments, so it was archived instead of permanently deleted. Existing appointment history is preserved.";

                $_SESSION['service_message_type'] =
                    "success";


            } else {

                /*
                |--------------------------------------------------------------------------
                | NO APPOINTMENTS
                |--------------------------------------------------------------------------
                |
                | Safe to permanently delete.
                |
                */

                $delete_service =
                    mysqli_prepare(
                        $hospital_conn,
                        "DELETE FROM services
                         WHERE service_id = ?"
                    );


                if (!$delete_service) {

                    throw new Exception(
                        "Unable to prepare service deletion."
                    );
                }


                mysqli_stmt_bind_param(
                    $delete_service,
                    "i",
                    $service_id
                );


                if (
                    !mysqli_stmt_execute(
                        $delete_service
                    )
                ) {

                    $delete_error =
                        mysqli_stmt_error(
                            $delete_service
                        );


                    mysqli_stmt_close(
                        $delete_service
                    );


                    throw new Exception(
                        "Unable to delete service: " .
                        $delete_error
                    );
                }


                if (
                    mysqli_stmt_affected_rows(
                        $delete_service
                    ) <= 0
                ) {

                    mysqli_stmt_close(
                        $delete_service
                    );


                    throw new Exception(
                        "Service could not be deleted."
                    );
                }


                mysqli_stmt_close(
                    $delete_service
                );


                mysqli_commit(
                    $hospital_conn
                );


                $_SESSION['service_message'] =
                    "Service and its doctor-service mappings were deleted successfully.";

                $_SESSION['service_message_type'] =
                    "success";
            }


        } catch (
            Throwable $exception
        ) {

            mysqli_rollback(
                $hospital_conn
            );


            $_SESSION['service_message'] =
                $exception->getMessage();

            $_SESSION['service_message_type'] =
                "error";
        }


        header(
            "Location: services.php"
        );

        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | ADD / EDIT SERVICE
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'add_service' ||
        $action === 'edit_service'
    ) {

        $service_id =
            (int)(
                $_POST['service_id']
                ?? 0
            );


        $department_id =
            (int)(
                $_POST['department_id']
                ?? 0
            );


        $service_name =
            trim(
                $_POST['service_name']
                ?? ''
            );


        $description =
            trim(
                $_POST['description']
                ?? ''
            );


        $service_type =
            trim(
                $_POST['service_type']
                ?? ''
            );


        $consultation_mode =
            trim(
                $_POST['consultation_mode']
                ?? 'In-Person'
            );


        $duration_minutes =
            (int)(
                $_POST['duration_minutes']
                ?? 30
            );


        $service_fee =
            (float)(
                $_POST['service_fee']
                ?? 0
            );


        $preparation_instructions =
            trim(
                $_POST['preparation_instructions']
                ?? ''
            );


        $status =
            trim(
                $_POST['status']
                ?? 'Active'
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $department_id <= 0 ||
            $service_name === '' ||
            $service_type === ''
        ) {

            $_SESSION['service_message'] =
                "Please fill all required service details.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        if (
            $action === 'edit_service' &&
            $service_id <= 0
        ) {

            $_SESSION['service_message'] =
                "Invalid service selected.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


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


        if (
            $duration_minutes <= 0
        ) {

            $duration_minutes = 30;
        }


        if (
            $service_fee < 0
        ) {

            $service_fee = 0;
        }


        if (
            !in_array(
                $status,
                [
                    'Active',
                    'Inactive'
                ],
                true
            )
        ) {

            $status = 'Active';
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK DEPARTMENT
        |--------------------------------------------------------------------------
        */

        $department_check =
            mysqli_prepare(
                $hospital_conn,
                "SELECT department_id
                 FROM departments
                 WHERE department_id = ?
                 LIMIT 1"
            );


        if (!$department_check) {

            $_SESSION['service_message'] =
                "Unable to check department.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        mysqli_stmt_bind_param(
            $department_check,
            "i",
            $department_id
        );


        mysqli_stmt_execute(
            $department_check
        );


        $department_result =
            mysqli_stmt_get_result(
                $department_check
            );


        $department_exists =
            mysqli_fetch_assoc(
                $department_result
            );


        mysqli_stmt_close(
            $department_check
        );


        if (!$department_exists) {

            $_SESSION['service_message'] =
                "Selected department does not exist.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | DUPLICATE SERVICE NAME
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'add_service'
        ) {

            $name_check_sql = "
                SELECT service_id
                FROM services
                WHERE department_id = ?
                AND service_name = ?
                LIMIT 1
            ";

        } else {

            $name_check_sql = "
                SELECT service_id
                FROM services
                WHERE department_id = ?
                AND service_name = ?
                AND service_id != ?
                LIMIT 1
            ";
        }


        $name_check =
            mysqli_prepare(
                $hospital_conn,
                $name_check_sql
            );


        if (!$name_check) {

            $_SESSION['service_message'] =
                "Unable to check service name.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        if (
            $action === 'add_service'
        ) {

            mysqli_stmt_bind_param(
                $name_check,
                "is",
                $department_id,
                $service_name
            );

        } else {

            mysqli_stmt_bind_param(
                $name_check,
                "isi",
                $department_id,
                $service_name,
                $service_id
            );
        }


        mysqli_stmt_execute(
            $name_check
        );


        $name_result =
            mysqli_stmt_get_result(
                $name_check
            );


        $name_exists =
            mysqli_num_rows(
                $name_result
            ) > 0;


        mysqli_stmt_close(
            $name_check
        );


        if ($name_exists) {

            $_SESSION['service_message'] =
                "This service already exists in the selected department.";

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | GENERATE CODE
        |--------------------------------------------------------------------------
        */

        try {

            $service_code =
                generateServiceCode(
                    $hospital_conn,
                    $department_id,
                    $service_type
                );

        } catch (
            Throwable $exception
        ) {

            $_SESSION['service_message'] =
                $exception->getMessage();

            $_SESSION['service_message_type'] =
                "error";

            header(
                "Location: services.php"
            );

            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | ADD SERVICE
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'add_service'
        ) {

            $insert =
                mysqli_prepare(
                    $hospital_conn,
                    "INSERT INTO services
                    (
                        department_id,
                        service_name,
                        service_code,
                        description,
                        service_type,
                        consultation_mode,
                        duration_minutes,
                        service_fee,
                        preparation_instructions,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        NULLIF(?, ''),
                        ?,
                        ?,
                        ?,
                        ?,
                        NULLIF(?, ''),
                        ?
                    )"
                );


            if (!$insert) {

                $_SESSION['service_message'] =
                    "Unable to prepare service insert: " .
                    mysqli_error(
                        $hospital_conn
                    );

                $_SESSION['service_message_type'] =
                    "error";

                header(
                    "Location: services.php"
                );

                exit();
            }


            mysqli_stmt_bind_param(
                $insert,
                "isssssidss",
                $department_id,
                $service_name,
                $service_code,
                $description,
                $service_type,
                $consultation_mode,
                $duration_minutes,
                $service_fee,
                $preparation_instructions,
                $status
            );


            if (
                !mysqli_stmt_execute(
                    $insert
                )
            ) {

                $_SESSION['service_message'] =
                    "Unable to add service: " .
                    mysqli_stmt_error(
                        $insert
                    );

                $_SESSION['service_message_type'] =
                    "error";

                mysqli_stmt_close(
                    $insert
                );

                header(
                    "Location: services.php"
                );

                exit();
            }


            $new_service_id =
                (int)mysqli_insert_id(
                    $hospital_conn
                );


            mysqli_stmt_close(
                $insert
            );


            /*
            |--------------------------------------------------------------------------
            | AUTOMATIC DOCTOR MAPPING
            |--------------------------------------------------------------------------
            */

            $doctor_mapping_error =
                '';


            if (
                $status === 'Active' &&
                stripos(
                    $service_type,
                    'consult'
                ) !== false
            ) {

                $map_sql = "
                    INSERT INTO doctor_services
                    (
                        doctor_id,
                        service_id,
                        consultation_fee,
                        consultation_duration,
                        status
                    )
                    SELECT
                        d.doctor_id,
                        ?,
                        ?,
                        ?,
                        'Active'
                    FROM doctors d
                    WHERE d.department_id = ?
                    AND d.status = 'Active'
                    AND NOT EXISTS
                    (
                        SELECT 1
                        FROM doctor_services existing_ds
                        WHERE existing_ds.doctor_id =
                              d.doctor_id
                        AND existing_ds.service_id = ?
                    )
                ";


                $map_stmt =
                    mysqli_prepare(
                        $hospital_conn,
                        $map_sql
                    );


                if ($map_stmt) {

                    mysqli_stmt_bind_param(
                        $map_stmt,
                        "idiii",
                        $new_service_id,
                        $service_fee,
                        $duration_minutes,
                        $department_id,
                        $new_service_id
                    );


                    if (
                        !mysqli_stmt_execute(
                            $map_stmt
                        )
                    ) {

                        $doctor_mapping_error =
                            mysqli_stmt_error(
                                $map_stmt
                            );
                    }


                    mysqli_stmt_close(
                        $map_stmt
                    );

                } else {

                    $doctor_mapping_error =
                        mysqli_error(
                            $hospital_conn
                        );
                }
            }


            if (
                $doctor_mapping_error !== ''
            ) {

                $_SESSION['service_message'] =
                    "Service added successfully, but doctor-service mapping failed: " .
                    $doctor_mapping_error;

                $_SESSION['service_message_type'] =
                    "error";

            } else {

                $_SESSION['service_message'] =
                    "Service added successfully. Code: " .
                    $service_code;

                $_SESSION['service_message_type'] =
                    "success";
            }


            header(
                "Location: services.php"
            );

            exit();
        }


        /*
        |--------------------------------------------------------------------------
        | EDIT SERVICE
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'edit_service'
        ) {

            $existing_check =
                mysqli_prepare(
                    $hospital_conn,
                    "SELECT service_id
                     FROM services
                     WHERE service_id = ?
                     LIMIT 1"
                );


            if (!$existing_check) {

                $_SESSION['service_message'] =
                    "Unable to check selected service.";

                $_SESSION['service_message_type'] =
                    "error";

                header(
                    "Location: services.php"
                );

                exit();
            }


            mysqli_stmt_bind_param(
                $existing_check,
                "i",
                $service_id
            );


            mysqli_stmt_execute(
                $existing_check
            );


            $existing_result =
                mysqli_stmt_get_result(
                    $existing_check
                );


            $existing_service =
                mysqli_fetch_assoc(
                    $existing_result
                );


            mysqli_stmt_close(
                $existing_check
            );


            if (!$existing_service) {

                $_SESSION['service_message'] =
                    "Selected service was not found.";

                $_SESSION['service_message_type'] =
                    "error";

                header(
                    "Location: services.php"
                );

                exit();
            }


            $update =
                mysqli_prepare(
                    $hospital_conn,
                    "UPDATE services
                     SET
                        department_id = ?,
                        service_name = ?,
                        service_code = ?,
                        description = NULLIF(?, ''),
                        service_type = ?,
                        consultation_mode = ?,
                        duration_minutes = ?,
                        service_fee = ?,
                        preparation_instructions = NULLIF(?, ''),
                        status = ?,
                        updated_at = CURRENT_TIMESTAMP
                     WHERE service_id = ?"
                );


            if (!$update) {

                $_SESSION['service_message'] =
                    "Unable to prepare service update: " .
                    mysqli_error(
                        $hospital_conn
                    );

                $_SESSION['service_message_type'] =
                    "error";

                header(
                    "Location: services.php"
                );

                exit();
            }


            mysqli_stmt_bind_param(
                $update,
                "isssssidssi",
                $department_id,
                $service_name,
                $service_code,
                $description,
                $service_type,
                $consultation_mode,
                $duration_minutes,
                $service_fee,
                $preparation_instructions,
                $status,
                $service_id
            );


            if (
                !mysqli_stmt_execute(
                    $update
                )
            ) {

                $_SESSION['service_message'] =
                    "Unable to update service: " .
                    mysqli_stmt_error(
                        $update
                    );

                $_SESSION['service_message_type'] =
                    "error";

                mysqli_stmt_close(
                    $update
                );

                header(
                    "Location: services.php"
                );

                exit();
            }


            mysqli_stmt_close(
                $update
            );


            /*
            |--------------------------------------------------------------------------
            | REBUILD DOCTOR MAPPINGS
            |--------------------------------------------------------------------------
            */

            $delete_mapping =
                mysqli_prepare(
                    $hospital_conn,
                    "DELETE FROM doctor_services
                     WHERE service_id = ?"
                );


            if ($delete_mapping) {

                mysqli_stmt_bind_param(
                    $delete_mapping,
                    "i",
                    $service_id
                );


                mysqli_stmt_execute(
                    $delete_mapping
                );


                mysqli_stmt_close(
                    $delete_mapping
                );
            }


            $doctor_mapping_error =
                '';


            if (
                $status === 'Active' &&
                stripos(
                    $service_type,
                    'consult'
                ) !== false
            ) {

                $map_sql = "
                    INSERT INTO doctor_services
                    (
                        doctor_id,
                        service_id,
                        consultation_fee,
                        consultation_duration,
                        status
                    )
                    SELECT
                        d.doctor_id,
                        ?,
                        ?,
                        ?,
                        'Active'
                    FROM doctors d
                    WHERE d.department_id = ?
                    AND d.status = 'Active'
                    AND NOT EXISTS
                    (
                        SELECT 1
                        FROM doctor_services existing_ds
                        WHERE existing_ds.doctor_id =
                              d.doctor_id
                        AND existing_ds.service_id = ?
                    )
                ";


                $map_stmt =
                    mysqli_prepare(
                        $hospital_conn,
                        $map_sql
                    );


                if ($map_stmt) {

                    mysqli_stmt_bind_param(
                        $map_stmt,
                        "idiii",
                        $service_id,
                        $service_fee,
                        $duration_minutes,
                        $department_id,
                        $service_id
                    );


                    if (
                        !mysqli_stmt_execute(
                            $map_stmt
                        )
                    ) {

                        $doctor_mapping_error =
                            mysqli_stmt_error(
                                $map_stmt
                            );
                    }


                    mysqli_stmt_close(
                        $map_stmt
                    );

                } else {

                    $doctor_mapping_error =
                        mysqli_error(
                            $hospital_conn
                        );
                }
            }


            if (
                $doctor_mapping_error !== ''
            ) {

                $_SESSION['service_message'] =
                    "Service updated successfully, but doctor-service mapping failed: " .
                    $doctor_mapping_error;

                $_SESSION['service_message_type'] =
                    "error";

            } else {

                $_SESSION['service_message'] =
                    "Service updated successfully. Code: " .
                    $service_code;

                $_SESSION['service_message_type'] =
                    "success";
            }


            header(
                "Location: services.php"
            );

            exit();
        }
    }
}


/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = [];

$department_result =
    mysqli_query(
        $hospital_conn,
        "SELECT
            department_id,
            department_name
         FROM departments
         ORDER BY department_name ASC"
    );


if ($department_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $department_result
        )
    ) {

        $departments[] =
            $row;
    }
}


/*
|--------------------------------------------------------------------------
| SERVICE TYPES
|--------------------------------------------------------------------------
*/

$service_types = [];

$type_result =
    mysqli_query(
        $hospital_conn,
        "SELECT DISTINCT
            service_type
         FROM services
         WHERE service_type IS NOT NULL
         AND service_type != ''
         ORDER BY service_type ASC"
    );


if ($type_result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $type_result
        )
    ) {

        $service_types[] =
            $row['service_type'];
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
    trim(
        $_GET['search'] ?? ''
    );


$department_filter =
    (int)(
        $_GET['department_id']
        ?? 0
    );


$type_filter =
    trim(
        $_GET['service_type']
        ?? ''
    );


$status_filter =
    trim(
        $_GET['status']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 10;

$page =
    max(
        1,
        (int)(
            $_GET['page']
            ?? 1
        )
    );


$offset =
    ($page - 1) *
    $per_page;


/*
|--------------------------------------------------------------------------
| COUNT SERVICES
|--------------------------------------------------------------------------
*/

$count_sql = "
    SELECT COUNT(*) AS total
    FROM services s
    LEFT JOIN departments d
        ON d.department_id =
           s.department_id
    WHERE 1 = 1
";


$count_types = '';

$count_params = [];


if (
    $search !== ''
) {

    $count_sql .= "
        AND
        (
            s.service_name LIKE ?
            OR s.service_code LIKE ?
            OR s.description LIKE ?
        )
    ";


    $search_value =
        "%" .
        $search .
        "%";


    $count_types .=
        "sss";


    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;
}


if (
    $department_filter > 0
) {

    $count_sql .=
        " AND s.department_id = ? ";

    $count_types .=
        "i";

    $count_params[] =
        $department_filter;
}


if (
    $type_filter !== ''
) {

    $count_sql .=
        " AND s.service_type = ? ";

    $count_types .=
        "s";

    $count_params[] =
        $type_filter;
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

    $count_sql .=
        " AND s.status = ? ";

    $count_types .=
        "s";

    $count_params[] =
        $status_filter;
}


$count_stmt =
    mysqli_prepare(
        $hospital_conn,
        $count_sql
    );


if (!$count_stmt) {

    die(
        "Unable to prepare service count query: " .
        mysqli_error(
            $hospital_conn
        )
    );
}


if (
    !empty($count_params)
) {

    mysqli_stmt_bind_param(
        $count_stmt,
        $count_types,
        ...$count_params
    );
}


mysqli_stmt_execute(
    $count_stmt
);


$count_result =
    mysqli_stmt_get_result(
        $count_stmt
    );


$count_row =
    mysqli_fetch_assoc(
        $count_result
    );


$total_services =
    (int)(
        $count_row['total']
        ?? 0
    );


mysqli_stmt_close(
    $count_stmt
);


$total_pages =
    max(
        1,
        (int)ceil(
            $total_services /
            $per_page
        )
    );


if (
    $page > $total_pages
) {

    $page =
        $total_pages;

    $offset =
        ($page - 1) *
        $per_page;
}


/*
|--------------------------------------------------------------------------
| SERVICE LIST
|--------------------------------------------------------------------------
*/

$service_sql = "
    SELECT
        s.service_id,
        s.department_id,
        s.service_name,
        s.service_code,
        s.description,
        s.service_type,
        s.consultation_mode,
        s.duration_minutes,
        s.service_fee,
        s.preparation_instructions,
        s.status,
        s.created_at,
        s.updated_at,

        d.department_name,

        (
            SELECT COUNT(DISTINCT ds.doctor_id)
            FROM doctor_services ds
            INNER JOIN doctors doc
                ON doc.doctor_id =
                   ds.doctor_id
            WHERE ds.service_id =
                  s.service_id
            AND ds.status = 'Active'
            AND doc.status = 'Active'
        ) AS doctor_count

    FROM services s

    LEFT JOIN departments d
        ON d.department_id =
           s.department_id

    WHERE 1 = 1
";


$service_types_bind = '';

$service_params = [];


if (
    $search !== ''
) {

    $service_sql .= "
        AND
        (
            s.service_name LIKE ?
            OR s.service_code LIKE ?
            OR s.description LIKE ?
        )
    ";


    $search_value =
        "%" .
        $search .
        "%";


    $service_types_bind .=
        "sss";


    $service_params[] =
        $search_value;

    $service_params[] =
        $search_value;

    $service_params[] =
        $search_value;
}


if (
    $department_filter > 0
) {

    $service_sql .=
        " AND s.department_id = ? ";

    $service_types_bind .=
        "i";

    $service_params[] =
        $department_filter;
}


if (
    $type_filter !== ''
) {

    $service_sql .=
        " AND s.service_type = ? ";

    $service_types_bind .=
        "s";

    $service_params[] =
        $type_filter;
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

    $service_sql .=
        " AND s.status = ? ";

    $service_types_bind .=
        "s";

    $service_params[] =
        $status_filter;
}


$service_sql .= "
    ORDER BY s.service_id DESC
    LIMIT ?, ?
";


$service_types_bind .=
    "ii";


$service_params[] =
    $offset;

$service_params[] =
    $per_page;


$service_stmt =
    mysqli_prepare(
        $hospital_conn,
        $service_sql
    );


if (!$service_stmt) {

    die(
        "Unable to prepare service list query: " .
        mysqli_error(
            $hospital_conn
        )
    );
}


mysqli_stmt_bind_param(
    $service_stmt,
    $service_types_bind,
    ...$service_params
);


mysqli_stmt_execute(
    $service_stmt
);


$service_result =
    mysqli_stmt_get_result(
        $service_stmt
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$total_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(*) AS total
         FROM services"
    );


$total_services_all =
    (int)(
        mysqli_fetch_assoc(
            $total_result
        )['total']
        ?? 0
    );


$active_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(*) AS total
         FROM services
         WHERE status = 'Active'"
    );


$active_services =
    (int)(
        mysqli_fetch_assoc(
            $active_result
        )['total']
        ?? 0
    );


$category_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(DISTINCT service_type) AS total
         FROM services
         WHERE service_type IS NOT NULL
         AND service_type != ''"
    );


$total_categories =
    (int)(
        mysqli_fetch_assoc(
            $category_result
        )['total']
        ?? 0
    );


$available_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(*) AS total
         FROM services
         WHERE status = 'Active'
         AND department_id IS NOT NULL"
    );


$available_services =
    (int)(
        mysqli_fetch_assoc(
            $available_result
        )['total']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$service_message =
    $_SESSION['service_message']
    ?? '';


$service_message_type =
    $_SESSION['service_message_type']
    ?? '';


unset(
    $_SESSION['service_message'],
    $_SESSION['service_message_type']
);


/*
|--------------------------------------------------------------------------
| DISPLAY RANGE
|--------------------------------------------------------------------------
*/

$showing_from =
    $total_services > 0
        ? $offset + 1
        : 0;


$showing_to =
    min(
        $offset + $per_page,
        $total_services
    );

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Services</title>

    <link
        rel="stylesheet"
        href="css/services.css"
    >

    <link
        rel="stylesheet"
        href="topbar.css"
    >

</head>


<body>


<div class="dashboard">


    <?php require 'sidebar.php'; ?>


    <main class="main-content">


        <div class="page-header">

            <div>

                <h1>
                    Services
                </h1>

                <p>
                    Manage hospital services and consultation details.
                </p>

            </div>


            <button
                type="button"
                class="add-btn"
            >

                <i class="fa-solid fa-plus"></i>

                Add Service

            </button>

        </div>


        <?php if (
            $service_message !== ''
        ): ?>

            <div
                class="service-message <?php
                    echo
                        $service_message_type === 'success'
                            ? 'success'
                            : 'error';
                ?>"
            >

                <?php
                echo e(
                    $service_message
                );
                ?>

            </div>

        <?php endif; ?>


        <div class="stats-container">


            <div class="stat-card">

                <div class="stat-icon total">

                    <i class="fa-solid fa-list-check"></i>

                </div>

                <div class="stat-info">

                    <h4>
                        Total Services
                    </h4>

                    <h2>
                        <?php
                        echo $total_services_all;
                        ?>
                    </h2>

                    <p>
                        All Services
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon active">

                    <i class="fa-solid fa-circle-check"></i>

                </div>

                <div class="stat-info">

                    <h4>
                        Active Services
                    </h4>

                    <h2>
                        <?php
                        echo $active_services;
                        ?>
                    </h2>

                    <p>
                        Currently Active
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon category">

                    <i class="fa-solid fa-layer-group"></i>

                </div>

                <div class="stat-info">

                    <h4>
                        Categories
                    </h4>

                    <h2>
                        <?php
                        echo $total_categories;
                        ?>
                    </h2>

                    <p>
                        Service Types
                    </p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon available">

                    <i class="fa-solid fa-hospital"></i>

                </div>

                <div class="stat-info">

                    <h4>
                        Available
                    </h4>

                    <h2>
                        <?php
                        echo $available_services;
                        ?>
                    </h2>

                    <p>
                        Hospital Services
                    </p>

                </div>

            </div>


        </div>


        <form
            method="GET"
            class="toolbar"
        >


            <div class="service-search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    value="<?php
                        echo e($search);
                    ?>"
                    placeholder="Search service..."
                >

            </div>


            <div class="service-filter-box">

                <i class="fa-solid fa-building"></i>

                <select
                    name="department_id"
                >

                    <option value="">
                        All Departments
                    </option>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>

                        <option
                            value="<?php
                                echo (int)
                                    $department[
                                        'department_id'
                                    ];
                            ?>"
                            <?php
                            echo
                                $department_filter ==
                                $department[
                                    'department_id'
                                ]
                                    ? 'selected'
                                    : '';
                            ?>
                        >

                            <?php
                            echo e(
                                $department[
                                    'department_name'
                                ]
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="service-filter-box">

                <i class="fa-solid fa-layer-group"></i>

                <select
                    name="service_type"
                >

                    <option value="">
                        All Types
                    </option>


                    <?php foreach (
                        $service_types
                        as $type
                    ): ?>

                        <option
                            value="<?php
                                echo e($type);
                            ?>"
                            <?php
                            echo
                                $type_filter === $type
                                    ? 'selected'
                                    : '';
                            ?>
                        >

                            <?php
                            echo e($type);
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="service-filter-box">

                <i class="fa-solid fa-circle-check"></i>

                <select
                    name="status"
                >

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="Active"
                        <?php
                        echo
                            $status_filter === 'Active'
                                ? 'selected'
                                : '';
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?php
                        echo
                            $status_filter === 'Inactive'
                                ? 'selected'
                                : '';
                        ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="service-filter-btn"
            >

                <i class="fa-solid fa-filter"></i>

                Filter

            </button>


            <a
                href="services.php"
                class="reset-btn"
            >
                Reset
            </a>


        </form>


        <div class="table-container">


            <table>

                <thead>

                    <tr>

                        <th>Service</th>

                        <th>Department</th>

                        <th>Type</th>

                        <th>Mode</th>

                        <th>Duration</th>

                        <th>Fee</th>

                        <th>Doctors</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    mysqli_num_rows(
                        $service_result
                    ) > 0
                ): ?>


                    <?php while (
                        $service =
                        mysqli_fetch_assoc(
                            $service_result
                        )
                    ): ?>


                        <tr>


                            <td>

                                <div class="service-info">

                                    <div class="service-icon">

                                        <i
                                            class="fa-solid fa-stethoscope"
                                        ></i>

                                    </div>


                                    <div>

                                        <h4>

                                            <?php
                                            echo e(
                                                $service[
                                                    'service_name'
                                                ]
                                            );
                                            ?>

                                        </h4>


                                        <span>

                                            Code:
                                            <?php
                                            echo e(
                                                $service[
                                                    'service_code'
                                                ]
                                            );
                                            ?>

                                        </span>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?php
                                echo e(
                                    $service[
                                        'department_name'
                                    ]
                                    ?: 'Not Assigned'
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo e(
                                    $service[
                                        'service_type'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo e(
                                    $service[
                                        'consultation_mode'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo (int)
                                    $service[
                                        'duration_minutes'
                                    ];
                                ?>

                                min

                            </td>


                            <td>

                                ₹<?php
                                echo number_format(
                                    (float)
                                    $service[
                                        'service_fee'
                                    ],
                                    2
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo number_format(
                                    (int)
                                    $service[
                                        'doctor_count'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                <span
                                    class="status <?php
                                        echo strtolower(
                                            e(
                                                $service[
                                                    'status'
                                                ]
                                            )
                                        );
                                    ?>"
                                >

                                    <?php
                                    echo e(
                                        $service[
                                            'status'
                                        ]
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>

                                <div class="service-actions">


                                    <button
                                        type="button"
                                        class="action-btn view-service-btn"
                                        title="View"
                                        data-id="<?php
                                            echo (int)
                                                $service[
                                                    'service_id'
                                                ];
                                        ?>"
                                    >

                                        <i
                                            class="fa-regular fa-eye"
                                        ></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="action-btn edit-service-btn"
                                        title="Edit"
                                        data-id="<?php
                                            echo (int)
                                                $service[
                                                    'service_id'
                                                ];
                                        ?>"
                                    >

                                        <i
                                            class="fa-regular fa-pen-to-square"
                                        ></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="action-btn delete-service-btn"
                                        title="Delete"
                                        data-id="<?php
                                            echo (int)
                                                $service[
                                                    'service_id'
                                                ];
                                        ?>"
                                    >

                                        <i
                                            class="fa-solid fa-trash"
                                        ></i>

                                    </button>


                                </div>


                                <div
                                    class="service-data"
                                    id="service-data-<?php
                                        echo (int)
                                            $service[
                                                'service_id'
                                            ];
                                    ?>"
                                    data-id="<?php
                                        echo (int)
                                            $service[
                                                'service_id'
                                            ];
                                    ?>"
                                    data-name="<?php
                                        echo e(
                                            $service[
                                                'service_name'
                                            ]
                                        );
                                    ?>"
                                    data-code="<?php
                                        echo e(
                                            $service[
                                                'service_code'
                                            ]
                                        );
                                    ?>"
                                    data-department-id="<?php
                                        echo (int)
                                            $service[
                                                'department_id'
                                            ];
                                    ?>"
                                    data-department="<?php
                                        echo e(
                                            $service[
                                                'department_name'
                                            ]
                                        );
                                    ?>"
                                    data-type="<?php
                                        echo e(
                                            $service[
                                                'service_type'
                                            ]
                                        );
                                    ?>"
                                    data-mode="<?php
                                        echo e(
                                            $service[
                                                'consultation_mode'
                                            ]
                                        );
                                    ?>"
                                    data-duration="<?php
                                        echo (int)
                                            $service[
                                                'duration_minutes'
                                            ];
                                    ?>"
                                    data-fee="<?php
                                        echo e(
                                            $service[
                                                'service_fee'
                                            ]
                                        );
                                    ?>"
                                    data-description="<?php
                                        echo e(
                                            $service[
                                                'description'
                                            ]
                                        );
                                    ?>"
                                    data-preparation="<?php
                                        echo e(
                                            $service[
                                                'preparation_instructions'
                                            ]
                                        );
                                    ?>"
                                    data-status="<?php
                                        echo e(
                                            $service[
                                                'status'
                                            ]
                                        );
                                    ?>"
                                    data-doctors="<?php
                                        echo (int)
                                            $service[
                                                'doctor_count'
                                            ];
                                    ?>"
                                ></div>


                            </td>


                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="9"
                            class="empty-state"
                        >

                            <i
                                class="fa-solid fa-stethoscope"
                            ></i>

                            <h3>
                                No services found
                            </h3>

                            <p>
                                No services match the current search or filters.
                            </p>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>


            <div class="table-footer">


                <div class="table-info">

                    Showing

                    <?php
                    echo $showing_from;
                    ?>

                    to

                    <?php
                    echo $showing_to;
                    ?>

                    of

                    <?php
                    echo $total_services;
                    ?>

                    entries

                </div>


                <div class="pagination">


                    <?php if (
                        $page > 1
                    ): ?>

                        <a
                            class="page-btn"
                            href="?<?php
                                echo http_build_query([
                                    'search' =>
                                        $search,
                                    'department_id' =>
                                        $department_filter,
                                    'service_type' =>
                                        $type_filter,
                                    'status' =>
                                        $status_filter,
                                    'page' =>
                                        $page - 1
                                ]);
                            ?>"
                        >

                            <i
                                class="fa-solid fa-angle-left"
                            ></i>

                        </a>

                    <?php endif; ?>


                    <?php

                    $start_page =
                        max(
                            1,
                            $page - 2
                        );

                    $end_page =
                        min(
                            $total_pages,
                            $page + 2
                        );


                    for (
                        $i = $start_page;
                        $i <= $end_page;
                        $i++
                    ):

                    ?>

                        <a
                            class="page-btn <?php
                                echo
                                    $i === $page
                                        ? 'active'
                                        : '';
                            ?>"
                            href="?<?php
                                echo http_build_query([
                                    'search' =>
                                        $search,
                                    'department_id' =>
                                        $department_filter,
                                    'service_type' =>
                                        $type_filter,
                                    'status' =>
                                        $status_filter,
                                    'page' =>
                                        $i
                                ]);
                            ?>"
                        >

                            <?php
                            echo $i;
                            ?>

                        </a>

                    <?php endfor; ?>


                    <?php if (
                        $page <
                        $total_pages
                    ): ?>

                        <a
                            class="page-btn"
                            href="?<?php
                                echo http_build_query([
                                    'search' =>
                                        $search,
                                    'department_id' =>
                                        $department_filter,
                                    'service_type' =>
                                        $type_filter,
                                    'status' =>
                                        $status_filter,
                                    'page' =>
                                        $page + 1
                                ]);
                            ?>"
                        >

                            <i
                                class="fa-solid fa-angle-right"
                            ></i>

                        </a>

                    <?php endif; ?>


                </div>


            </div>


        </div>


        <!-- =================================================
             ADD / EDIT SERVICE MODAL
        ================================================== -->

        <div
            class="modal"
            id="serviceModal"
        >


            <div class="modal-content">


                <div class="modal-header">


                    <h2 id="serviceModalTitle">
                        Add New Service
                    </h2>


                    <button
                        type="button"
                        class="close-btn"
                    >
                        &times;
                    </button>


                </div>


                <form
                    method="POST"
                    id="serviceForm"
                >


                    <input
                        type="hidden"
                        name="action"
                        id="serviceAction"
                        value="add_service"
                    >


                    <input
                        type="hidden"
                        name="service_id"
                        id="serviceId"
                        value=""
                    >


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Service Name *
                            </label>

                            <input
                                type="text"
                                name="service_name"
                                id="serviceName"
                                placeholder="e.g. Pediatrics Consultation"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Service Code
                            </label>

                            <input
                                type="text"
                                id="serviceCode"
                                value="Auto-generated"
                                readonly
                                class="readonly-field"
                            >

                            <small
                                class="field-help"
                            >
                                Service code is generated automatically.
                            </small>

                        </div>


                        <div class="form-group">

                            <label>
                                Department *
                            </label>

                            <select
                                name="department_id"
                                id="serviceDepartment"
                                required
                            >

                                <option value="">
                                    Select Department
                                </option>


                                <?php foreach (
                                    $departments
                                    as $department
                                ): ?>

                                    <option
                                        value="<?php
                                            echo (int)
                                                $department[
                                                    'department_id'
                                                ];
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            $department[
                                                'department_name'
                                            ]
                                        );
                                        ?>

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Service Type *
                            </label>

                            <input
                                type="text"
                                name="service_type"
                                id="serviceType"
                                list="serviceTypeList"
                                placeholder="e.g. Consultation / Laboratory / X-Ray"
                                required
                            >


                            <datalist
                                id="serviceTypeList"
                            >

                                <?php foreach (
                                    $service_types
                                    as $type
                                ): ?>

                                    <option
                                        value="<?php
                                            echo e($type);
                                        ?>"
                                    ></option>

                                <?php endforeach; ?>

                            </datalist>


                        </div>


                        <div class="form-group">

                            <label>
                                Consultation Mode
                            </label>

                            <select
                                name="consultation_mode"
                                id="consultationMode"
                            >

                                <option value="In-Person">
                                    In-Person
                                </option>

                                <option value="Video">
                                    Video
                                </option>

                                <option value="Both">
                                    Both
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Duration (Minutes)
                            </label>

                            <input
                                type="number"
                                name="duration_minutes"
                                id="durationMinutes"
                                min="1"
                                value="30"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Service Fee
                            </label>

                            <input
                                type="number"
                                name="service_fee"
                                id="serviceFee"
                                min="0"
                                step="0.01"
                                value="0"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                id="serviceStatus"
                            >

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>


                    </div>


                    <div
                        class="form-group service-textarea-group"
                    >

                        <label>
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="serviceDescription"
                            rows="4"
                            placeholder="Enter service description..."
                        ></textarea>

                    </div>


                    <div
                        class="form-group service-textarea-group"
                    >

                        <label>
                            Preparation Instructions
                        </label>

                        <textarea
                            name="preparation_instructions"
                            id="preparationInstructions"
                            rows="3"
                            placeholder="Enter preparation instructions if required..."
                        ></textarea>

                    </div>


                    <div class="form-actions">


                        <button
                            type="button"
                            class="cancel-btn"
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            class="save-btn"
                            id="saveServiceBtn"
                        >
                            Save Service
                        </button>


                    </div>


                </form>


            </div>

        </div>


    </main>

</div>


<script
    src="js/services.js"
></script>


</body>

</html>


<?php

mysqli_stmt_close(
    $service_stmt
);

mysqli_close(
    $hospital_conn
);

mysqli_close(
    $conn
);

?>