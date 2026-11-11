<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

$conn = db_connect();

if (!$conn) {
    die("Central database connection failed: " . mysqli_connect_error());
}


/*
|--------------------------------------------------------------------------
| Get Current Hospital
|--------------------------------------------------------------------------
*/

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found in session.");
}


/*
|--------------------------------------------------------------------------
| Get Hospital Database
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
    die("Unable to prepare hospital database query.");
}

mysqli_stmt_bind_param($stmt, "s", $application_no);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$hospital = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$hospital || empty($hospital['database_name'])) {
    die("Hospital database not found.");
}

$hospital_db = $hospital['database_name'];


/*
|--------------------------------------------------------------------------
| Hospital DB Connection
|--------------------------------------------------------------------------
*/

$hospital_conn = db_connect($hospital_db);

if (!$hospital_conn) {
    die("Hospital database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($hospital_conn, "utf8mb4");


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


/*
|--------------------------------------------------------------------------
| DOCTOR AVAILABILITY HELPER
|--------------------------------------------------------------------------
*/

function ensureDoctorService(mysqli $db, int $doctor_id, int $department_id, float $doctor_fee): array
{
    if ($doctor_id <= 0 || $department_id <= 0) {
        return [false, 'Invalid doctor or department.'];
    }

    $find = mysqli_prepare(
        $db,
        "SELECT service_id, service_fee
         FROM services
         WHERE department_id = ?
           AND status = 'Active'
         ORDER BY CASE WHEN service_type = 'Consultation' THEN 0 ELSE 1 END, service_id ASC
         LIMIT 1"
    );
    if (!$find) return [false, mysqli_error($db)];

    mysqli_stmt_bind_param($find, 'i', $department_id);
    mysqli_stmt_execute($find);
    $result = mysqli_stmt_get_result($find);
    $service = $result ? mysqli_fetch_assoc($result) : null;
    if ($result) mysqli_free_result($result);
    mysqli_stmt_close($find);

    $service_id = $service ? (int)$service['service_id'] : 0;

    if ($service_id <= 0) {
        $service_name = 'General Consultation';
        $service_code = 'DOC-' . $department_id . '-CONSULT';
        $description = 'Standard doctor consultation service.';
        $service_type = 'Consultation';
        $mode = 'Both';
        $duration = 30;
        $fee = $doctor_fee > 0 ? $doctor_fee : 500.00;

        $insertService = mysqli_prepare(
            $db,
            "INSERT INTO services
             (department_id, service_name, service_code, description, service_type, consultation_mode, duration_minutes, service_fee, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')"
        );
        if (!$insertService) return [false, mysqli_error($db)];

        mysqli_stmt_bind_param(
            $insertService,
            'isssssid',
            $department_id,
            $service_name,
            $service_code,
            $description,
            $service_type,
            $mode,
            $duration,
            $fee
        );

        if (!mysqli_stmt_execute($insertService)) {
            $error = mysqli_stmt_error($insertService);
            mysqli_stmt_close($insertService);

            // A matching service may already exist; fetch it before failing.
            $findAgain = mysqli_prepare(
                $db,
                "SELECT service_id, service_fee FROM services WHERE department_id = ? AND service_name = ? LIMIT 1"
            );
            if ($findAgain) {
                mysqli_stmt_bind_param($findAgain, 'is', $department_id, $service_name);
                mysqli_stmt_execute($findAgain);
                $r = mysqli_stmt_get_result($findAgain);
                $row = $r ? mysqli_fetch_assoc($r) : null;
                if ($r) mysqli_free_result($r);
                mysqli_stmt_close($findAgain);
                if ($row) {
                    $service_id = (int)$row['service_id'];
                    $service = $row;
                }
            }
            if ($service_id <= 0) return [false, $error];
        } else {
            $service_id = (int)mysqli_insert_id($db);
            mysqli_stmt_close($insertService);
            $service = ['service_fee' => $fee];
        }
    }

    $fee = $doctor_fee > 0 ? $doctor_fee : (float)($service['service_fee'] ?? 500);

    $link = mysqli_prepare(
        $db,
        "INSERT INTO doctor_services
         (doctor_id, service_id, consultation_fee, consultation_duration, status)
         VALUES (?, ?, ?, 30, 'Active')
         ON DUPLICATE KEY UPDATE
            consultation_fee = VALUES(consultation_fee),
            consultation_duration = VALUES(consultation_duration),
            status = 'Active'"
    );
    if (!$link) return [false, mysqli_error($db)];

    mysqli_stmt_bind_param($link, 'iid', $doctor_id, $service_id, $fee);
    $ok = mysqli_stmt_execute($link);
    $error = $ok ? '' : mysqli_stmt_error($link);
    mysqli_stmt_close($link);

    return [$ok, $error];
}

function saveDoctorAvailability(mysqli $db, int $doctor_id): array
{
    $valid_days = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ];

    $enabled_days = $_POST['availability_enabled'] ?? [];
    $start_times = $_POST['availability_start'] ?? [];
    $end_times = $_POST['availability_end'] ?? [];

    $slot_duration = (int)($_POST['slot_duration_minutes'] ?? 15);
    $max_patients = (int)($_POST['max_patients'] ?? 1);
    $consultation_mode = trim(
        $_POST['availability_consultation_mode'] ?? 'In-Person'
    );

    if ($slot_duration < 1 || $slot_duration > 1440) {
        return [false, 'Slot duration must be between 1 and 1440 minutes.'];
    }

    if ($max_patients < 1) {
        return [false, 'Maximum patients must be at least 1.'];
    }

    if (!in_array(
        $consultation_mode,
        ['In-Person', 'Video', 'Both'],
        true
    )) {
        $consultation_mode = 'In-Person';
    }

    $entries = [];

    foreach ($enabled_days as $day) {

        if (!in_array($day, $valid_days, true)) {
            continue;
        }

        $start = trim((string)($start_times[$day] ?? ''));
        $end = trim((string)($end_times[$day] ?? ''));

        if ($start === '' || $end === '') {
            return [
                false,
                'Please enter both start time and end time for ' . $day . '.'
            ];
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $start) ||
            !preg_match('/^\d{2}:\d{2}$/', $end)) {
            return [false, 'Invalid time format for ' . $day . '.'];
        }

        if ($start >= $end) {
            return [
                false,
                'End time must be later than start time for ' . $day . '.'
            ];
        }

        $entries[] = [
            $day,
            $start,
            $end
        ];
    }

    mysqli_begin_transaction($db);

    try {

        $delete = mysqli_prepare(
            $db,
            "DELETE FROM doctor_availability WHERE doctor_id = ?"
        );

        if (!$delete) {
            throw new Exception(mysqli_error($db));
        }

        mysqli_stmt_bind_param(
            $delete,
            'i',
            $doctor_id
        );

        if (!mysqli_stmt_execute($delete)) {
            $error = mysqli_stmt_error($delete);
            mysqli_stmt_close($delete);
            throw new Exception($error);
        }

        mysqli_stmt_close($delete);

        $insert = mysqli_prepare(
            $db,
            "INSERT INTO doctor_availability
            (
                doctor_id,
                day_of_week,
                start_time,
                end_time,
                slot_duration_minutes,
                max_patients,
                consultation_mode,
                status
            )
            VALUES
            (?, ?, ?, ?, ?, ?, ?, 'Active')"
        );

        if (!$insert) {
            throw new Exception(mysqli_error($db));
        }

        foreach ($entries as $entry) {

            [$day, $start, $end] = $entry;

            mysqli_stmt_bind_param(
                $insert,
                'isssiis',
                $doctor_id,
                $day,
                $start,
                $end,
                $slot_duration,
                $max_patients,
                $consultation_mode
            );

            if (!mysqli_stmt_execute($insert)) {
                $error = mysqli_stmt_error($insert);
                mysqli_stmt_close($insert);
                throw new Exception($error);
            }
        }

        mysqli_stmt_close($insert);

        mysqli_commit($db);

        return [true, ''];

    } catch (Throwable $e) {

        mysqli_rollback($db);

        return [
            false,
            'Unable to save doctor availability: ' . $e->getMessage()
        ];
    }
}


/*
|--------------------------------------------------------------------------
| ADD DOCTOR
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'add_doctor'
) {

    $doctor_name = trim($_POST['doctor_name'] ?? '');
    $department_id = (int)($_POST['department_id'] ?? 0);
    $specialization = trim($_POST['specialization'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $medical_license_no = trim($_POST['medical_license_no'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $consultation_fee = (float)($_POST['consultation_fee'] ?? 0);
    $status = trim($_POST['status'] ?? 'Active');


    /*
    |--------------------------------------------------------------------------
    | Treat 0 license as empty
    |--------------------------------------------------------------------------
    */

    if ($medical_license_no === '0') {
        $medical_license_no = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $doctor_name === '' ||
        $department_id <= 0 ||
        $specialization === '' ||
        $qualification === ''
    ) {

        $_SESSION['doctor_message'] =
            "Please fill all required doctor details.";

        $_SESSION['doctor_message_type'] = "error";

        header("Location: doctors.php");
        exit();
    }


    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }


    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $gender = 'Other';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Email
    |--------------------------------------------------------------------------
    */

    if ($email !== '') {

        $check = mysqli_prepare(
            $hospital_conn,
            "SELECT doctor_id
             FROM doctors
             WHERE email = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $check,
            "s",
            $email
        );

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($check_result) > 0) {

            mysqli_stmt_close($check);

            $_SESSION['doctor_message'] =
                "A doctor with this email already exists.";

            $_SESSION['doctor_message_type'] = "error";

            header("Location: doctors.php");
            exit();
        }

        mysqli_stmt_close($check);
    }


    /*
    |--------------------------------------------------------------------------
    | Check Medical License
    |--------------------------------------------------------------------------
    */

    if ($medical_license_no !== '') {

        $license_check = mysqli_prepare(
            $hospital_conn,
            "SELECT doctor_id
             FROM doctors
             WHERE medical_license_no = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $license_check,
            "s",
            $medical_license_no
        );

        mysqli_stmt_execute($license_check);

        $license_result =
            mysqli_stmt_get_result($license_check);

        if (mysqli_num_rows($license_result) > 0) {

            mysqli_stmt_close($license_check);

            $_SESSION['doctor_message'] =
                "A doctor with this medical license number already exists.";

            $_SESSION['doctor_message_type'] = "error";

            header("Location: doctors.php");
            exit();
        }

        mysqli_stmt_close($license_check);
    }


    /*
    |--------------------------------------------------------------------------
    | Profile Photo
    |--------------------------------------------------------------------------
    */

    $profile_photo = null;

    if (
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK
    ) {

        $upload_dir = __DIR__ . "/uploads/doctors/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        $extension = strtolower(
            pathinfo(
                $_FILES['profile_photo']['name'],
                PATHINFO_EXTENSION
            )
        );

        if (in_array($extension, $allowed_extensions, true)) {

            $file_name =
                'doctor_' .
                time() .
                '_' .
                bin2hex(random_bytes(4)) .
                '.' .
                $extension;

            $target_file =
                $upload_dir . $file_name;

            if (
                move_uploaded_file(
                    $_FILES['profile_photo']['tmp_name'],
                    $target_file
                )
            ) {

                $profile_photo =
                    "uploads/doctors/" . $file_name;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Doctor
    |--------------------------------------------------------------------------
    */

    $insert = mysqli_prepare(
        $hospital_conn,
        "INSERT INTO doctors
        (
            department_id,
            doctor_name,
            gender,
            date_of_birth,
            email,
            phone,
            qualification,
            specialization,
            medical_license_no,
            experience_years,
            consultation_fee,
            profile_photo,
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
            ?,
            ?,
            ?,
            ?
        )"
    );

   mysqli_stmt_bind_param(
    $insert,
    "issssssssidss",
    $department_id,
    $doctor_name,
    $gender,
    $date_of_birth,
    $email,
    $phone,
    $qualification,
    $specialization,
    $medical_license_no,
    $experience_years,
    $consultation_fee,
    $profile_photo,
    $status
);

    /*
    |--------------------------------------------------------------------------
    | Correct Bind Types
    |--------------------------------------------------------------------------
    */

    mysqli_stmt_close($insert);

    $insert = mysqli_prepare(
        $hospital_conn,
        "INSERT INTO doctors
        (
            department_id,
            doctor_name,
            gender,
            date_of_birth,
            email,
            phone,
            qualification,
            specialization,
            medical_license_no,
            experience_years,
            consultation_fee,
            profile_photo,
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
            ?,
            ?,
            ?,
            ?
        )"
    );

   mysqli_stmt_bind_param(
    $insert,
    "issssssssidss",
    $department_id,
    $doctor_name,
    $gender,
    $date_of_birth,
    $email,
    $phone,
    $qualification,
    $specialization,
    $medical_license_no,
    $experience_years,
    $consultation_fee,
    $profile_photo,
    $status
);
    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    if (mysqli_stmt_execute($insert)) {

        $new_doctor_id = mysqli_insert_id($hospital_conn);

        [$availability_saved, $availability_message] =
            saveDoctorAvailability($hospital_conn, $new_doctor_id);

        [$service_saved, $service_message] =
            ensureDoctorService(
                $hospital_conn,
                $new_doctor_id,
                $department_id,
                $consultation_fee
            );

        if ($availability_saved && $service_saved) {

            $_SESSION['doctor_message'] =
                "Doctor added successfully.";

            $_SESSION['doctor_message_type'] =
                "success";

        } else {

            $issues = [];
            if (!$availability_saved) $issues[] = $availability_message;
            if (!$service_saved) $issues[] = $service_message;
            $_SESSION['doctor_message'] =
                "Doctor added successfully, but " . implode(' ', $issues);

            $_SESSION['doctor_message_type'] =
                "error";
        }

    } else {

        $_SESSION['doctor_message'] =
            "Unable to add doctor: " .
            mysqli_error($hospital_conn);

        $_SESSION['doctor_message_type'] =
            "error";
    }

    mysqli_stmt_close($insert);

    header("Location: doctors.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| EDIT DOCTOR
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'edit_doctor'
) {

    $doctor_id =
        (int)($_POST['doctor_id'] ?? 0);

    $doctor_name =
        trim($_POST['doctor_name'] ?? '');

    $department_id =
        (int)($_POST['department_id'] ?? 0);

    $specialization =
        trim($_POST['specialization'] ?? '');

    $qualification =
        trim($_POST['qualification'] ?? '');

    $gender =
        trim($_POST['gender'] ?? '');

    $date_of_birth =
        trim($_POST['date_of_birth'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $medical_license_no =
        trim($_POST['medical_license_no'] ?? '');

    $experience_years =
        (int)($_POST['experience_years'] ?? 0);

    $consultation_fee =
        (float)($_POST['consultation_fee'] ?? 0);

    $status =
        trim($_POST['status'] ?? 'Active');


    /*
    |--------------------------------------------------------------------------
    | Important License Fix
    |--------------------------------------------------------------------------
    |
    | Old records may contain 0.
    | We treat 0 as empty.
    |
    */

    if (
        $medical_license_no === '' ||
        $medical_license_no === '0'
    ) {

        $medical_license_no = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $doctor_id <= 0 ||
        $doctor_name === '' ||
        $department_id <= 0
    ) {

        $_SESSION['doctor_message'] =
            "Invalid doctor information.";

        $_SESSION['doctor_message_type'] =
            "error";

        header("Location: doctors.php");
        exit();
    }


    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }


    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $gender = 'Other';
    }


    /*
    |--------------------------------------------------------------------------
    | Get Existing Doctor
    |--------------------------------------------------------------------------
    */

    $existing_stmt = mysqli_prepare(
        $hospital_conn,
        "SELECT
            medical_license_no,
            profile_photo
         FROM doctors
         WHERE doctor_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $existing_stmt,
        "i",
        $doctor_id
    );

    mysqli_stmt_execute($existing_stmt);

    $existing_result =
        mysqli_stmt_get_result($existing_stmt);

    $existing_doctor =
        mysqli_fetch_assoc($existing_result);

    mysqli_stmt_close($existing_stmt);


    if (!$existing_doctor) {

        $_SESSION['doctor_message'] =
            "Doctor record not found.";

        $_SESSION['doctor_message_type'] =
            "error";

        header("Location: doctors.php");
        exit();
    }


    /*
    |--------------------------------------------------------------------------
    | Existing License
    |--------------------------------------------------------------------------
    */

    $old_license =
        trim((string)(
            $existing_doctor['medical_license_no'] ?? ''
        ));


    /*
    |--------------------------------------------------------------------------
    | If old license is 0 and user leaves field empty,
    | convert it to NULL instead of sending 0.
    |--------------------------------------------------------------------------
    */

    if (
        $old_license === '0' &&
        $medical_license_no === ''
    ) {

        $medical_license_no = '';
    }


    /*
    |--------------------------------------------------------------------------
    | Check Email Against Other Doctors
    |--------------------------------------------------------------------------
    */

    if ($email !== '') {

        $email_check = mysqli_prepare(
            $hospital_conn,
            "SELECT doctor_id
             FROM doctors
             WHERE email = ?
             AND doctor_id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $email_check,
            "si",
            $email,
            $doctor_id
        );

        mysqli_stmt_execute($email_check);

        $email_result =
            mysqli_stmt_get_result($email_check);

        if (mysqli_num_rows($email_result) > 0) {

            mysqli_stmt_close($email_check);

            $_SESSION['doctor_message'] =
                "Another doctor already uses this email.";

            $_SESSION['doctor_message_type'] =
                "error";

            header("Location: doctors.php");
            exit();
        }

        mysqli_stmt_close($email_check);
    }


    /*
    |--------------------------------------------------------------------------
    | Check Medical License Against Other Doctors
    |--------------------------------------------------------------------------
    */

    if ($medical_license_no !== '') {

        $license_check = mysqli_prepare(
            $hospital_conn,
            "SELECT doctor_id
             FROM doctors
             WHERE medical_license_no = ?
             AND doctor_id != ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $license_check,
            "si",
            $medical_license_no,
            $doctor_id
        );

        mysqli_stmt_execute($license_check);

        $license_result =
            mysqli_stmt_get_result($license_check);

        if (mysqli_num_rows($license_result) > 0) {

            mysqli_stmt_close($license_check);

            $_SESSION['doctor_message'] =
                "Another doctor already uses this medical license number.";

            $_SESSION['doctor_message_type'] =
                "error";

            header("Location: doctors.php");
            exit();
        }

        mysqli_stmt_close($license_check);
    }


    /*
    |--------------------------------------------------------------------------
    | Existing Photo
    |--------------------------------------------------------------------------
    */

    $old_photo =
        $existing_doctor['profile_photo'] ?? '';

    $new_photo =
        $old_photo;


    /*
    |--------------------------------------------------------------------------
    | New Photo Upload
    |--------------------------------------------------------------------------
    */

    if (
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK
    ) {

        $upload_dir =
            __DIR__ . "/uploads/doctors/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];

        $extension =
            strtolower(
                pathinfo(
                    $_FILES['profile_photo']['name'],
                    PATHINFO_EXTENSION
                )
            );

        if (
            in_array(
                $extension,
                $allowed_extensions,
                true
            )
        ) {

            $file_name =
                'doctor_' .
                time() .
                '_' .
                bin2hex(random_bytes(4)) .
                '.' .
                $extension;

            $target_file =
                $upload_dir . $file_name;


            if (
                move_uploaded_file(
                    $_FILES['profile_photo']['tmp_name'],
                    $target_file
                )
            ) {

                $new_photo =
                    "uploads/doctors/" . $file_name;


                /*
                | Delete old photo only after
                | successful new upload.
                */

                if (!empty($old_photo)) {

                    $old_file =
                        __DIR__ . "/" . $old_photo;

                    if (file_exists($old_file)) {
                        @unlink($old_file);
                    }
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE DOCTOR
    |--------------------------------------------------------------------------
    |
    | NULLIF prevents empty license from becoming duplicate empty/0 value.
    |
    */

    $update = mysqli_prepare(
        $hospital_conn,
        "UPDATE doctors
         SET
            department_id = ?,
            doctor_name = ?,
            gender = ?,
            date_of_birth = NULLIF(?, ''),
            email = ?,
            phone = ?,
            qualification = ?,
            specialization = ?,
            medical_license_no = NULLIF(?, ''),
            experience_years = ?,
            consultation_fee = ?,
            profile_photo = ?,
            status = ?
         WHERE doctor_id = ?"
    );


   mysqli_stmt_bind_param(
    $update,
    "issssssssidssi",
    $department_id,
    $doctor_name,
    $gender,
    $date_of_birth,
    $email,
    $phone,
    $qualification,
    $specialization,
    $medical_license_no,
    $experience_years,
    $consultation_fee,
    $new_photo,
    $status,
    $doctor_id
);


    if (mysqli_stmt_execute($update)) {

        [$availability_saved, $availability_message] =
            saveDoctorAvailability($hospital_conn, $doctor_id);

        [$service_saved, $service_message] =
            ensureDoctorService(
                $hospital_conn,
                $doctor_id,
                $department_id,
                $consultation_fee
            );

        if ($availability_saved && $service_saved) {

            $_SESSION['doctor_message'] =
                "Doctor updated successfully.";

            $_SESSION['doctor_message_type'] =
                "success";

        } else {

            $issues = [];
            if (!$availability_saved) $issues[] = $availability_message;
            if (!$service_saved) $issues[] = $service_message;
            $_SESSION['doctor_message'] =
                "Doctor updated successfully, but " . implode(' ', $issues);

            $_SESSION['doctor_message_type'] =
                "error";
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | If database still reports duplicate license,
        | show a clear message.
        |--------------------------------------------------------------------------
        */

        $db_error =
            mysqli_error($hospital_conn);

        $_SESSION['doctor_message'] =
            "Unable to update doctor: " . $db_error;

        $_SESSION['doctor_message_type'] =
            "error";
    }


    mysqli_stmt_close($update);

    header("Location: doctors.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| DELETE DOCTOR
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_doctor'
) {

    $doctor_id =
        (int)($_POST['doctor_id'] ?? 0);


    if ($doctor_id > 0) {


        /*
        |--------------------------------------------------------------------------
        | Check Appointments
        |--------------------------------------------------------------------------
        */

        $check = mysqli_prepare(
            $hospital_conn,
            "SELECT COUNT(*) AS total
             FROM appointments
             WHERE doctor_id = ?"
        );

        mysqli_stmt_bind_param(
            $check,
            "i",
            $doctor_id
        );

        mysqli_stmt_execute($check);

        $check_result =
            mysqli_stmt_get_result($check);

        $appointment_data =
            mysqli_fetch_assoc($check_result);

        mysqli_stmt_close($check);


        if (
            ($appointment_data['total'] ?? 0) > 0
        ) {

            $_SESSION['doctor_message'] =
                "This doctor cannot be deleted because appointments are linked to this doctor. You can set the doctor status to Inactive.";

            $_SESSION['doctor_message_type'] =
                "error";

        } else {


            /*
            |--------------------------------------------------------------------------
            | Get Photo
            |--------------------------------------------------------------------------
            */

            $photo_stmt = mysqli_prepare(
                $hospital_conn,
                "SELECT profile_photo
                 FROM doctors
                 WHERE doctor_id = ?
                 LIMIT 1"
            );

            mysqli_stmt_bind_param(
                $photo_stmt,
                "i",
                $doctor_id
            );

            mysqli_stmt_execute($photo_stmt);

            $photo_result =
                mysqli_stmt_get_result($photo_stmt);

            $photo_data =
                mysqli_fetch_assoc($photo_result);

            mysqli_stmt_close($photo_stmt);


            /*
            |--------------------------------------------------------------------------
            | Delete Doctor
            |--------------------------------------------------------------------------
            */

            $delete = mysqli_prepare(
                $hospital_conn,
                "DELETE FROM doctors
                 WHERE doctor_id = ?"
            );

            mysqli_stmt_bind_param(
                $delete,
                "i",
                $doctor_id
            );


            if (mysqli_stmt_execute($delete)) {

                /*
                |--------------------------------------------------------------------------
                | Delete Doctor Availability
                |--------------------------------------------------------------------------
                */

                $availability_delete = mysqli_prepare(
                    $hospital_conn,
                    "DELETE FROM doctor_availability
                     WHERE doctor_id = ?"
                );

                if ($availability_delete) {

                    mysqli_stmt_bind_param(
                        $availability_delete,
                        "i",
                        $doctor_id
                    );

                    mysqli_stmt_execute($availability_delete);
                    mysqli_stmt_close($availability_delete);
                }


                if (
                    !empty(
                        $photo_data['profile_photo']
                    )
                ) {

                    $photo_file =
                        __DIR__ . "/" .
                        $photo_data['profile_photo'];

                    if (file_exists($photo_file)) {
                        @unlink($photo_file);
                    }
                }


                $_SESSION['doctor_message'] =
                    "Doctor deleted successfully.";

                $_SESSION['doctor_message_type'] =
                    "success";

            } else {

                $_SESSION['doctor_message'] =
                    "Unable to delete doctor: " .
                    mysqli_error($hospital_conn);

                $_SESSION['doctor_message_type'] =
                    "error";
            }


            mysqli_stmt_close($delete);
        }
    }


    header("Location: doctors.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| DEPARTMENTS
|--------------------------------------------------------------------------
*/

$departments = [];

$department_result = mysqli_query(
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
        mysqli_fetch_assoc($department_result)
    ) {

        $departments[] =
            $row;
    }
}


/*
|--------------------------------------------------------------------------
| DOCTOR AVAILABILITY
|--------------------------------------------------------------------------
*/

$all_availability = [];

$availability_result = mysqli_query(
    $hospital_conn,
    "SELECT
        availability_id,
        doctor_id,
        day_of_week,
        start_time,
        end_time,
        slot_duration_minutes,
        max_patients,
        consultation_mode,
        status
     FROM doctor_availability
     ORDER BY
        doctor_id ASC,
        FIELD(
            day_of_week,
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ),
        start_time ASC"
);

if ($availability_result) {

    while ($availability = mysqli_fetch_assoc($availability_result)) {

        $availability['start_time'] =
            substr((string)$availability['start_time'], 0, 5);

        $availability['end_time'] =
            substr((string)$availability['end_time'], 0, 5);

        $all_availability[(int)$availability['doctor_id']][] =
            $availability;
    }
}


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search =
    trim($_GET['search'] ?? '');

$department_filter =
    (int)($_GET['department_id'] ?? 0);

$status_filter =
    trim($_GET['status'] ?? '');


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$per_page = 5;

$page =
    max(
        1,
        (int)($_GET['page'] ?? 1)
    );

$offset =
    ($page - 1) * $per_page;


/*
|--------------------------------------------------------------------------
| DOCTOR COUNT
|--------------------------------------------------------------------------
*/

$count_sql = "
    SELECT COUNT(*) AS total
    FROM doctors d
    LEFT JOIN departments dep
        ON dep.department_id = d.department_id
    WHERE 1=1
";

$count_types = "";

$count_params = [];


if ($search !== '') {

    $count_sql .= "
        AND (
            d.doctor_name LIKE ?
            OR d.specialization LIKE ?
            OR d.qualification LIKE ?
            OR d.email LIKE ?
            OR d.phone LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $count_types .= "sssss";

    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;

    $count_params[] =
        $search_value;
}


if ($department_filter > 0) {

    $count_sql .=
        " AND d.department_id = ? ";

    $count_types .= "i";

    $count_params[] =
        $department_filter;
}


if (
    $status_filter !== '' &&
    in_array(
        $status_filter,
        ['Active', 'Inactive'],
        true
    )
) {

    $count_sql .=
        " AND d.status = ? ";

    $count_types .= "s";

    $count_params[] =
        $status_filter;
}


$count_stmt =
    mysqli_prepare(
        $hospital_conn,
        $count_sql
    );


if (!empty($count_params)) {

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

$total_doctors =
    (int)(
        $count_row['total'] ?? 0
    );

mysqli_stmt_close(
    $count_stmt
);


$total_pages =
    max(
        1,
        (int)ceil(
            $total_doctors /
            $per_page
        )
    );


/*
|--------------------------------------------------------------------------
| DOCTOR LIST
|--------------------------------------------------------------------------
*/

$doctor_sql = "
    SELECT
        d.doctor_id,
        d.department_id,
        d.doctor_name,
        d.gender,
        d.date_of_birth,
        d.email,
        d.phone,
        d.qualification,
        d.specialization,
        d.medical_license_no,
        d.experience_years,
        d.consultation_fee,
        d.profile_photo,
        d.status,
        dep.department_name,

        (
            SELECT COUNT(*)
            FROM appointments a
            WHERE a.doctor_id = d.doctor_id
              AND a.appointment_status != 'Cancelled'
        ) AS patient_count

    FROM doctors d

    LEFT JOIN departments dep
        ON dep.department_id = d.department_id

    WHERE 1=1
";


$doctor_types = "";

$doctor_params = [];


if ($search !== '') {

    $doctor_sql .= "
        AND (
            d.doctor_name LIKE ?
            OR d.specialization LIKE ?
            OR d.qualification LIKE ?
            OR d.email LIKE ?
            OR d.phone LIKE ?
        )
    ";

    $search_value =
        "%" . $search . "%";

    $doctor_types .=
        "sssss";

    $doctor_params[] =
        $search_value;

    $doctor_params[] =
        $search_value;

    $doctor_params[] =
        $search_value;

    $doctor_params[] =
        $search_value;

    $doctor_params[] =
        $search_value;
}


if ($department_filter > 0) {

    $doctor_sql .=
        " AND d.department_id = ? ";

    $doctor_types .= "i";

    $doctor_params[] =
        $department_filter;
}


if (
    $status_filter !== '' &&
    in_array(
        $status_filter,
        ['Active', 'Inactive'],
        true
    )
) {

    $doctor_sql .=
        " AND d.status = ? ";

    $doctor_types .= "s";

    $doctor_params[] =
        $status_filter;
}


$doctor_sql .= "
    ORDER BY d.doctor_id DESC
    LIMIT ?, ?
";

$doctor_types .= "ii";

$doctor_params[] =
    $offset;

$doctor_params[] =
    $per_page;


$doctor_stmt =
    mysqli_prepare(
        $hospital_conn,
        $doctor_sql
    );


mysqli_stmt_bind_param(
    $doctor_stmt,
    $doctor_types,
    ...$doctor_params
);


mysqli_stmt_execute(
    $doctor_stmt
);

$doctor_result =
    mysqli_stmt_get_result(
        $doctor_stmt
    );


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$active_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(*) AS total
         FROM doctors
         WHERE status = 'Active'"
    );

$active_doctors =
    (int)(
        mysqli_fetch_assoc(
            $active_result
        )['total'] ?? 0
    );


$department_count_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(DISTINCT department_id) AS total
         FROM doctors
         WHERE department_id IS NOT NULL"
    );

$department_count =
    (int)(
        mysqli_fetch_assoc(
            $department_count_result
        )['total'] ?? 0
    );


$specialization_result =
    mysqli_query(
        $hospital_conn,
        "SELECT COUNT(DISTINCT specialization) AS total
         FROM doctors
         WHERE specialization IS NOT NULL
           AND specialization != ''"
    );

$total_specializations =
    (int)(
        mysqli_fetch_assoc(
            $specialization_result
        )['total'] ?? 0
    );


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

$doctor_message =
    $_SESSION['doctor_message'] ?? '';

$doctor_message_type =
    $_SESSION['doctor_message_type'] ?? '';

unset(
    $_SESSION['doctor_message']
);

unset(
    $_SESSION['doctor_message_type']
);


/*
|--------------------------------------------------------------------------
| PAGE RANGE
|--------------------------------------------------------------------------
*/

$showing_from =
    $total_doctors > 0
        ? $offset + 1
        : 0;

$showing_to =
    min(
        $offset + $per_page,
        $total_doctors
    );

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Doctors</title>

    <link
        rel="stylesheet"
        href="css/doctors.css">

    <link
        rel="stylesheet"
        href="topbar.css">

</head>


<body>


<div class="dashboard">


    <?php require 'sidebar.php'; ?>


    <main class="main-content">


        <div class="page-header">

            <div>

                <h1>Doctors</h1>

                <p>
                    Manage hospital doctors and their information.
                </p>

            </div>


            <button
                type="button"
                class="add-btn"
                id="addDoctorBtn">

                <i class="fa-solid fa-plus"></i>

                Add Doctor

            </button>

        </div>


        <!-- ==========================
             STATISTICS
        =========================== -->

        <div class="stats-container">


            <div class="stat-card">

                <div class="card-icon purple">

                    <i class="fa-solid fa-user-doctor"></i>

                </div>

                <div>

                    <p>Total Doctors</p>

                    <h2>
                        <?php echo $total_doctors; ?>
                    </h2>

                    <span>All Doctors</span>

                </div>

            </div>


            <div class="stat-card">

                <div class="card-icon green">

                    <i class="fa-solid fa-stethoscope"></i>

                </div>

                <div>

                    <p>Active Doctors</p>

                    <h2>
                        <?php echo $active_doctors; ?>
                    </h2>

                    <span>Currently Active</span>

                </div>

            </div>


            <div class="stat-card">

                <div class="card-icon blue">

                    <i class="fa-solid fa-users"></i>

                </div>

                <div>

                    <p>Total Departments</p>

                    <h2>
                        <?php echo $department_count; ?>
                    </h2>

                    <span>With Doctors</span>

                </div>

            </div>


            <div class="stat-card">

                <div class="card-icon orange">

                    <i class="fa-solid fa-heart-pulse"></i>

                </div>

                <div>

                    <p>Total Specializations</p>

                    <h2>
                        <?php echo $total_specializations; ?>
                    </h2>

                    <span>
                        Specializations Available
                    </span>

                </div>

            </div>

        </div>


        <!-- ==========================
             FILTER
        =========================== -->

        <form
            method="GET"
            class="toolbar"
            id="doctorFilterForm">


            <div class="search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    value="<?php echo e($search); ?>"
                    placeholder="Search doctor...">

            </div>


            <div class="filter-box">

                <i class="fa-solid fa-filter"></i>

                <select
                    name="department_id">

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
                                $department['department_id'];
                            ?>"
                            <?php
                            echo
                                $department_filter ==
                                $department['department_id']
                                    ? 'selected'
                                    : '';
                            ?>>

                            <?php
                            echo e(
                                $department['department_name']
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-box">

                <i class="fa-solid fa-filter"></i>

                <select name="status">

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
                        ?>>

                        Active

                    </option>

                    <option
                        value="Inactive"
                        <?php
                        echo
                            $status_filter === 'Inactive'
                                ? 'selected'
                                : '';
                        ?>>

                        Inactive

                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="filter-btn">

                <i class="fa-solid fa-filter"></i>

                Filter

            </button>


            <a
                href="doctors.php"
                class="reset-btn">

                Reset

            </a>

        </form>


        <!-- ==========================
             DOCTORS TABLE
        =========================== -->

        <div class="table-container">


            <table>


                <thead>

                    <tr>

                        <th>Doctor Name</th>

                        <th>Department</th>

                        <th>Specialization</th>

                        <th>Experience</th>

                        <th>Patients</th>

                        <th>Consultation Fee</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    mysqli_num_rows(
                        $doctor_result
                    ) > 0
                ): ?>


                    <?php while (
                        $doctor =
                        mysqli_fetch_assoc(
                            $doctor_result
                        )
                    ): ?>


                        <?php

                        $initials = '';

                        $name_parts =
                            preg_split(
                                '/\s+/',
                                trim(
                                    $doctor['doctor_name']
                                )
                            );


                        if (
                            !empty(
                                $name_parts[0]
                            )
                        ) {

                            $initials .=
                                strtoupper(
                                    substr(
                                        $name_parts[0],
                                        0,
                                        1
                                    )
                                );
                        }


                        if (
                            !empty(
                                $name_parts[1]
                            )
                        ) {

                            $initials .=
                                strtoupper(
                                    substr(
                                        $name_parts[1],
                                        0,
                                        1
                                    )
                                );
                        }


                        $photo =
                            $doctor['profile_photo']
                            ?? '';

                        ?>


                        <tr>


                            <td>

                                <div class="doctor-info">


                                    <?php if (
                                        !empty($photo)
                                    ): ?>

                                        <img
                                            src="<?php
                                                echo e($photo);
                                            ?>"
                                            alt="Doctor">

                                    <?php else: ?>


                                        <div
                                            class="doctor-avatar">

                                            <?php
                                            echo e(
                                                $initials
                                                    ?: 'DR'
                                            );
                                            ?>

                                        </div>


                                    <?php endif; ?>


                                    <div>

                                        <h4>

                                            <?php
                                            echo e(
                                                $doctor[
                                                    'doctor_name'
                                                ]
                                            );
                                            ?>

                                        </h4>


                                        <span>

                                            <?php
                                            echo e(
                                                $doctor[
                                                    'qualification'
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
                                    $doctor[
                                        'department_name'
                                    ]
                                    ?: 'Not Assigned'
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo e(
                                    $doctor[
                                        'specialization'
                                    ]
                                    ?: 'N/A'
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo (int)
                                    $doctor[
                                        'experience_years'
                                    ];
                                ?>

                                Years

                            </td>


                            <td>

                                <?php
                                echo number_format(
                                    (int)
                                    $doctor[
                                        'patient_count'
                                    ]
                                );
                                ?>

                            </td>


                            <td>

                                ₹

                                <?php
                                echo number_format(
                                    (float)
                                    $doctor[
                                        'consultation_fee'
                                    ],
                                    2
                                );
                                ?>

                            </td>


                            <td>

                                <span
                                    class="status <?php
                                        echo strtolower(
                                            e(
                                                $doctor[
                                                    'status'
                                                ]
                                            )
                                        );
                                    ?>">

                                    <?php
                                    echo e(
                                        $doctor['status']
                                    );
                                    ?>

                                </span>

                            </td>


                            <td>


                                <div
                                    class="doctor-actions">


                                    <button
                                        type="button"
                                        class="doctor-action-btn view-doctor-btn"
                                        title="View"
                                        data-id="<?php
                                            echo (int)
                                            $doctor[
                                                'doctor_id'
                                            ];
                                        ?>">

                                        <i class="fa-regular fa-eye"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="doctor-action-btn edit-doctor-btn"
                                        title="Edit"
                                        data-id="<?php
                                            echo (int)
                                            $doctor[
                                                'doctor_id'
                                            ];
                                        ?>">

                                        <i class="fa-regular fa-pen-to-square"></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="doctor-action-btn more-doctor-btn"
                                        title="More"
                                        data-id="<?php
                                            echo (int)
                                            $doctor[
                                                'doctor_id'
                                            ];
                                        ?>">

                                        <i class="fa-solid fa-ellipsis-vertical"></i>

                                    </button>


                                </div>


                                <div
                                    class="doctor-data"
                                    id="doctor-data-<?php
                                        echo (int)
                                        $doctor[
                                            'doctor_id'
                                        ];
                                    ?>"

                                    data-id="<?php
                                        echo (int)
                                        $doctor[
                                            'doctor_id'
                                        ];
                                    ?>"

                                    data-name="<?php
                                        echo e(
                                            $doctor[
                                                'doctor_name'
                                            ]
                                        );
                                    ?>"

                                    data-department-id="<?php
                                        echo (int)
                                        $doctor[
                                            'department_id'
                                        ];
                                    ?>"

                                    data-department="<?php
                                        echo e(
                                            $doctor[
                                                'department_name'
                                            ]
                                        );
                                    ?>"

                                    data-specialization="<?php
                                        echo e(
                                            $doctor[
                                                'specialization'
                                            ]
                                        );
                                    ?>"

                                    data-qualification="<?php
                                        echo e(
                                            $doctor[
                                                'qualification'
                                            ]
                                        );
                                    ?>"

                                    data-gender="<?php
                                        echo e(
                                            $doctor[
                                                'gender'
                                            ]
                                        );
                                    ?>"

                                    data-dob="<?php
                                        echo e(
                                            $doctor[
                                                'date_of_birth'
                                            ]
                                        );
                                    ?>"

                                    data-email="<?php
                                        echo e(
                                            $doctor[
                                                'email'
                                            ]
                                        );
                                    ?>"

                                    data-phone="<?php
                                        echo e(
                                            $doctor[
                                                'phone'
                                            ]
                                        );
                                    ?>"

                                    data-license="<?php
                                        echo e(
                                            $doctor[
                                                'medical_license_no'
                                            ]
                                        );
                                    ?>"

                                    data-experience="<?php
                                        echo (int)
                                        $doctor[
                                            'experience_years'
                                        ];
                                    ?>"

                                    data-fee="<?php
                                        echo e(
                                            $doctor[
                                                'consultation_fee'
                                            ]
                                        );
                                    ?>"

                                    data-photo="<?php
                                        echo e(
                                            $doctor[
                                                'profile_photo'
                                            ]
                                        );
                                    ?>"

                                    data-status="<?php
                                        echo e(
                                            $doctor[
                                                'status'
                                            ]
                                        );
                                    ?>"

                                    data-patients="<?php
                                        echo (int)
                                        $doctor[
                                            'patient_count'
                                        ];
                                    ?>"

                                    data-availability='<?php
                                        echo e(
                                            json_encode(
                                                $all_availability[(int)$doctor['doctor_id']] ?? [],
                                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                            )
                                        );
                                    ?>'>

                                </div>


                            </td>

                        </tr>


                    <?php endwhile; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="8"
                            class="empty-state">

                            <i class="fa-solid fa-user-doctor"></i>

                            <h3>
                                No doctors found
                            </h3>

                            <p>
                                No doctors match the current search or filters.
                            </p>

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>


            <!-- ==========================
                 FOOTER
            =========================== -->

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
                    echo $total_doctors;
                    ?>

                    entries

                </div>


                <div class="pagination">


                    <?php if ($page > 1): ?>

                        <a
                            class="page-btn"
                            href="?<?php
                            echo http_build_query([
                                'search' =>
                                    $search,

                                'department_id' =>
                                    $department_filter,

                                'status' =>
                                    $status_filter,

                                'page' =>
                                    $page - 1
                            ]);
                            ?>">

                            <i class="fa-solid fa-angle-left"></i>

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

                                'status' =>
                                    $status_filter,

                                'page' =>
                                    $i
                            ]);
                            ?>">

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

                                'status' =>
                                    $status_filter,

                                'page' =>
                                    $page + 1
                            ]);
                            ?>">

                            <i class="fa-solid fa-angle-right"></i>

                        </a>

                    <?php endif; ?>


                </div>

            </div>


        </div>


        <!-- =====================================================
             ADD DOCTOR MODAL
        ====================================================== -->

        <div
            class="modal-overlay"
            id="doctorModal">

            <div class="doctor-modal">


                <div class="modal-header">

                    <h2>
                        Add New Doctor
                    </h2>


                    <button
                        type="button"
                        id="closeDoctorModal">

                        <i class="fa-solid fa-xmark"></i>

                    </button>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                    id="doctorForm">


                    <input
                        type="hidden"
                        name="action"
                        value="add_doctor">


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Doctor Name *
                            </label>

                            <input
                                type="text"
                                name="doctor_name"
                                placeholder="Enter Doctor Name"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Department *
                            </label>

                            <select
                                name="department_id"
                                required>

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
                                        ?>">

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
                                Specialization *
                            </label>

                            <input
                                type="text"
                                name="specialization"
                                placeholder="Specialization"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Qualification *
                            </label>

                            <input
                                type="text"
                                name="qualification"
                                placeholder="MBBS, MD"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Gender
                            </label>

                            <select name="gender">

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


                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth">

                        </div>


                        <div class="form-group">

                            <label>
                                Experience
                            </label>

                            <input
                                type="number"
                                name="experience_years"
                                min="0"
                                max="70"
                                placeholder="Years">

                        </div>


                        <div class="form-group">

                            <label>
                                Consultation Fee
                            </label>

                            <input
                                type="number"
                                name="consultation_fee"
                                min="0"
                                step="0.01"
                                placeholder="₹">

                        </div>


                        <div class="form-group">

                            <label>
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="doctor@email.com">

                        </div>


                        <div class="form-group">

                            <label>
                                Mobile Number
                            </label>

                            <input
                                type="text"
                                name="phone"
                                placeholder="+91 XXXXX XXXXX">

                        </div>


                        <div class="form-group">

                            <label>
                                Medical License No.
                            </label>

                            <input
                                type="text"
                                name="medical_license_no"
                                placeholder="Medical License Number">

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select name="status">

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>


                    </div>




                    <!-- =====================================================
                         DOCTOR AVAILABILITY
                    ====================================================== -->

                    <div class="availability-section">

                        <div class="availability-header">

                            <div>
                                <h3 class="availability-title">
                                    Doctor Availability
                                </h3>

                                <p class="availability-subtitle">
                                    Set the days and consultation time when this doctor is available.
                                </p>
                            </div>

                        </div>

                        <div class="availability-settings">

                            <div class="form-group">
                                <label>Slot Duration</label>

                                <select name="slot_duration_minutes" id="addSlotDuration">
                                    <option value="10">10 Minutes</option>
                                    <option value="15" selected>15 Minutes</option>
                                    <option value="20">20 Minutes</option>
                                    <option value="30">30 Minutes</option>
                                    <option value="60">60 Minutes</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Maximum Patients</label>

                                <input
                                    type="number"
                                    name="max_patients"
                                    id="addMaxPatients"
                                    min="1"
                                    value="10">
                            </div>

                            <div class="form-group">
                                <label>Consultation Mode</label>

                                <select
                                    name="availability_consultation_mode"
                                    id="addConsultationMode">

                                    <option value="In-Person">In-Person</option>
                                    <option value="Video">Video</option>
                                    <option value="Both">Both</option>

                                </select>
                            </div>

                        </div>

                        <div class="availability-days">

                            <?php foreach (
                                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
                                as $availability_day
                            ): ?>

                                <div class="availability-day">

                                    <div class="availability-day-name">

                                        <label class="day-checkbox">
                                            <input
                                                type="checkbox"
                                                name="availability_enabled[]"
                                                value="<?php echo e($availability_day); ?>"
                                                id="addDay<?php echo e($availability_day); ?>">

                                            <span><?php echo e($availability_day); ?></span>
                                        </label>

                                    </div>

                                    <div class="availability-times">

                                        <input
                                            type="time"
                                            name="availability_start[<?php echo e($availability_day); ?>]"
                                            id="addStart<?php echo e($availability_day); ?>"
                                            value="09:30">

                                        <span>to</span>

                                        <input
                                            type="time"
                                            name="availability_end[<?php echo e($availability_day); ?>]"
                                            id="addEnd<?php echo e($availability_day); ?>"
                                            value="13:00">

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="form-group">

                        <label>
                            Profile Photo
                        </label>

                        <input
                            type="file"
                            name="profile_photo"
                            accept=".jpg,.jpeg,.png,.webp">

                    </div>


                    <div class="modal-footer">


                        <button
                            type="button"
                            class="cancel-btn">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="save-btn">

                            Save Doctor

                        </button>


                    </div>


                </form>

            </div>

        </div>


        <!-- =====================================================
             VIEW DOCTOR MODAL
        ====================================================== -->

        <div
            class="modal-overlay"
            id="viewDoctorModal">

            <div
                class="doctor-modal small-modal">


                <div class="modal-header">

                    <h2>
                        Doctor Details
                    </h2>


                    <button
                        type="button"
                        class="close-view-modal">

                        <i class="fa-solid fa-xmark"></i>

                    </button>

                </div>


                <div class="doctor-details">


                    <div class="doctor-detail-profile">


                        <div
                            class="view-doctor-avatar"
                            id="viewDoctorAvatar">

                            DR

                        </div>


                        <div>

                            <h2 id="viewDoctorName">
                                -
                            </h2>

                            <p id="viewDoctorQualification">
                                -
                            </p>

                        </div>


                    </div>


                    <div class="details-grid">


                        <div>

                            <label>
                                Department
                            </label>

                            <strong id="viewDepartment">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Specialization
                            </label>

                            <strong id="viewSpecialization">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Experience
                            </label>

                            <strong id="viewExperience">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Patients
                            </label>

                            <strong id="viewPatients">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Consultation Fee
                            </label>

                            <strong id="viewFee">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Status
                            </label>

                            <strong id="viewStatus">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Gender
                            </label>

                            <strong id="viewGender">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Date of Birth
                            </label>

                            <strong id="viewDob">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Email
                            </label>

                            <strong id="viewEmail">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Mobile
                            </label>

                            <strong id="viewPhone">
                                -
                            </strong>

                        </div>


                        <div>

                            <label>
                                Medical License
                            </label>

                            <strong id="viewLicense">
                                -
                            </strong>

                        </div>


                    </div>

                </div>

            </div>

        </div>


        <!-- =====================================================
             EDIT DOCTOR MODAL
        ====================================================== -->

        <div
            class="modal-overlay"
            id="editDoctorModal">

            <div class="doctor-modal">


                <div class="modal-header">

                    <h2>
                        Edit Doctor
                    </h2>


                    <button
                        type="button"
                        class="close-edit-modal">

                        <i class="fa-solid fa-xmark"></i>

                    </button>

                </div>


                <form
                    method="POST"
                    enctype="multipart/form-data"
                    id="editDoctorForm">


                    <input
                        type="hidden"
                        name="action"
                        value="edit_doctor">


                    <input
                        type="hidden"
                        name="doctor_id"
                        id="editDoctorId">


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Doctor Name *
                            </label>

                            <input
                                type="text"
                                name="doctor_name"
                                id="editDoctorName"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Department *
                            </label>

                            <select
                                name="department_id"
                                id="editDepartment"
                                required>

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
                                        ?>">

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
                                Specialization *
                            </label>

                            <input
                                type="text"
                                name="specialization"
                                id="editSpecialization"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Qualification *
                            </label>

                            <input
                                type="text"
                                name="qualification"
                                id="editQualification"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Gender
                            </label>

                            <select
                                name="gender"
                                id="editGender">

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


                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                id="editDob">

                        </div>


                        <div class="form-group">

                            <label>
                                Experience
                            </label>

                            <input
                                type="number"
                                name="experience_years"
                                id="editExperience"
                                min="0"
                                max="70">

                        </div>


                        <div class="form-group">

                            <label>
                                Consultation Fee
                            </label>

                            <input
                                type="number"
                                name="consultation_fee"
                                id="editFee"
                                min="0"
                                step="0.01">

                        </div>


                        <div class="form-group">

                            <label>
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="editEmail">

                        </div>


                        <div class="form-group">

                            <label>
                                Mobile Number
                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="editPhone">

                        </div>


                        <div class="form-group">

                            <label>
                                Medical License No.
                            </label>

                            <input
                                type="text"
                                name="medical_license_no"
                                id="editLicense">

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                id="editStatus">

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>


                    </div>




                    <!-- =====================================================
                         DOCTOR AVAILABILITY
                    ====================================================== -->

                    <div class="availability-section">

                        <div class="availability-header">

                            <div>
                                <h3 class="availability-title">
                                    Doctor Availability
                                </h3>

                                <p class="availability-subtitle">
                                    Set the days and consultation time when this doctor is available.
                                </p>
                            </div>

                        </div>

                        <div class="availability-settings">

                            <div class="form-group">
                                <label>Slot Duration</label>

                                <select name="slot_duration_minutes" id="editSlotDuration">
                                    <option value="10">10 Minutes</option>
                                    <option value="15">15 Minutes</option>
                                    <option value="20">20 Minutes</option>
                                    <option value="30">30 Minutes</option>
                                    <option value="60">60 Minutes</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Maximum Patients</label>

                                <input
                                    type="number"
                                    name="max_patients"
                                    id="editMaxPatients"
                                    min="1"
                                    value="10">
                            </div>

                            <div class="form-group">
                                <label>Consultation Mode</label>

                                <select
                                    name="availability_consultation_mode"
                                    id="editConsultationMode">

                                    <option value="In-Person">In-Person</option>
                                    <option value="Video">Video</option>
                                    <option value="Both">Both</option>

                                </select>
                            </div>

                        </div>

                        <div class="availability-days">

                            <?php foreach (
                                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
                                as $availability_day
                            ): ?>

                                <div class="availability-day">

                                    <div class="availability-day-name">

                                        <label class="day-checkbox">
                                            <input
                                                type="checkbox"
                                                name="availability_enabled[]"
                                                value="<?php echo e($availability_day); ?>"
                                                id="editDay<?php echo e($availability_day); ?>">

                                            <span><?php echo e($availability_day); ?></span>
                                        </label>

                                    </div>

                                    <div class="availability-times">

                                        <input
                                            type="time"
                                            name="availability_start[<?php echo e($availability_day); ?>]"
                                            id="editStart<?php echo e($availability_day); ?>"
                                            value="09:30">

                                        <span>to</span>

                                        <input
                                            type="time"
                                            name="availability_end[<?php echo e($availability_day); ?>]"
                                            id="editEnd<?php echo e($availability_day); ?>"
                                            value="13:00">

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="form-group">

                        <label>
                            Change Profile Photo
                        </label>

                        <input
                            type="file"
                            name="profile_photo"
                            accept=".jpg,.jpeg,.png,.webp">

                    </div>


                    <div class="modal-footer">


                        <button
                            type="button"
                            class="cancel-edit-btn">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="save-btn">

                            Update Doctor

                        </button>


                    </div>


                </form>

            </div>

        </div>


        <!-- =====================================================
             DELETE MODAL
        ====================================================== -->

        <div
            class="modal-overlay"
            id="deleteDoctorModal">

            <div class="delete-modal">


                <div class="delete-icon">

                    <i class="fa-solid fa-trash"></i>

                </div>


                <h2>
                    Delete Doctor?
                </h2>


                <p>

                    Are you sure you want to delete

                    <strong id="deleteDoctorName">
                        this doctor
                    </strong>?

                </p>


                <p class="delete-warning">

                    If appointments are linked to this doctor,
                    deletion will not be allowed.

                </p>


                <form method="POST">


                    <input
                        type="hidden"
                        name="action"
                        value="delete_doctor">


                    <input
                        type="hidden"
                        name="doctor_id"
                        id="deleteDoctorId">


                    <div class="delete-actions">


                        <button
                            type="button"
                            class="cancel-delete-btn">

                            Cancel

                        </button>


                        <button
                            type="submit"
                            class="confirm-delete-btn">

                            Delete Doctor

                        </button>


                    </div>


                </form>

            </div>

        </div>


        <!-- =====================================================
             MORE MENU
        ====================================================== -->

        <div
            class="doctor-more-menu"
            id="doctorMoreMenu">


            <button
                type="button"
                id="moreViewBtn">

                <i class="fa-regular fa-eye"></i>

                View Details

            </button>


            <button
                type="button"
                id="moreEditBtn">

                <i class="fa-regular fa-pen-to-square"></i>

                Edit Doctor

            </button>


            <button
                type="button"
                id="moreDeleteBtn">

                <i class="fa-solid fa-trash"></i>

                Delete Doctor

            </button>


        </div>


    </main>

</div>


<?php if ($doctor_message !== ''): ?>

<script>

    window.doctorMessage = <?php

        echo json_encode(
            $doctor_message,
            JSON_HEX_TAG |
            JSON_HEX_APOS |
            JSON_HEX_QUOT |
            JSON_HEX_AMP
        );

    ?>;


    window.doctorMessageType = <?php

        echo json_encode(
            $doctor_message_type
        );

    ?>;

</script>

<?php endif; ?>


<script src="js/doctors.js"></script>


</body>

</html>


<?php

mysqli_stmt_close($doctor_stmt);

mysqli_close($hospital_conn);

mysqli_close($conn);

?>