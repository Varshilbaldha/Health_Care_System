<?php

declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';




require 'auth.php';

mysqli_report(MYSQLI_REPORT_OFF);


/*==================================================
    CENTRAL DATABASE
==================================================*/

require_once __DIR__ . '/../includes/config.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

mysqli_set_charset($conn, 'utf8mb4');


/*==================================================
    HELPER
==================================================*/

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirectStaff(array $params = []): never
{
    $url = 'staff.php';

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    header('Location: ' . $url);
    exit;
}


/*==================================================
    HOSPITAL DATABASE
==================================================*/

$application_no = (string)($_SESSION['application_no'] ?? '');

if ($application_no === '') {
    die('Hospital session information is missing.');
}

$hospital_sql = "
    SELECT hospital_id, hospital_name, database_name
    FROM hospital_registration
    WHERE application_no = ?
    LIMIT 1
";

$hospital_stmt = mysqli_prepare($conn, $hospital_sql);

if (!$hospital_stmt) {
    die('Unable to load hospital information.');
}

mysqli_stmt_bind_param($hospital_stmt, 's', $application_no);
mysqli_stmt_execute($hospital_stmt);

$hospital_result = mysqli_stmt_get_result($hospital_stmt);
$hospital = $hospital_result ? mysqli_fetch_assoc($hospital_result) : null;

if ($hospital_result) {
    mysqli_free_result($hospital_result);
}

mysqli_stmt_close($hospital_stmt);

if (!$hospital) {
    die('Hospital information not found.');
}

$database_name = trim((string)($hospital['database_name'] ?? ''));

if (
    $database_name === '' ||
    !preg_match('/^[A-Za-z0-9_]+$/', $database_name)
) {
    die('Invalid hospital database.');
}


/*==================================================
    HOSPITAL DATABASE CONNECTION
==================================================*/

$hospital_conn = db_connect($database_name);

if (!$hospital_conn) {
    die('Unable to connect to hospital database.');
}

mysqli_set_charset($hospital_conn, 'utf8mb4');


/*==================================================
    MESSAGE
==================================================*/

$message = '';
$message_type = '';


/*==================================================
    UPLOAD DIRECTORY
==================================================*/

$upload_dir = __DIR__ . '/uploads/staff/';

if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0775, true);
}


/*==================================================
    PROCESS POST ACTIONS
==================================================*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string)($_POST['action'] ?? '');


    /*==================================================
        ADD STAFF
    ==================================================*/

    if ($action === 'add_staff') {

        $staff_name = trim((string)($_POST['staff_name'] ?? ''));
        $department_id = (int)($_POST['department_id'] ?? 0);
        $gender = trim((string)($_POST['gender'] ?? ''));
        $date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $joining_date = trim((string)($_POST['joining_date'] ?? ''));
        $salary = (float)($_POST['salary'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'Active'));

        if (
            $staff_name === '' ||
            $department_id <= 0 ||
            $gender === '' ||
            $email === '' ||
            $phone === '' ||
            $designation === '' ||
            $joining_date === ''
        ) {
            $message = 'Please fill all required staff details.';
            $message_type = 'error';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';

        } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $message = 'Invalid gender selected.';
            $message_type = 'error';

        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $message = 'Invalid status selected.';
            $message_type = 'error';

        } elseif ($salary < 0) {
            $message = 'Salary cannot be negative.';
            $message_type = 'error';

        } else {

            $check_sql = "
                SELECT staff_id
                FROM staff
                WHERE email = ?
                LIMIT 1
            ";

            $check_stmt = mysqli_prepare($hospital_conn, $check_sql);

            if (!$check_stmt) {
                $message = 'Unable to validate staff information.';
                $message_type = 'error';
            } else {

                mysqli_stmt_bind_param($check_stmt, 's', $email);
                mysqli_stmt_execute($check_stmt);

                $check_result = mysqli_stmt_get_result($check_stmt);
                $email_exists = $check_result && mysqli_num_rows($check_result) > 0;

                if ($check_result) {
                    mysqli_free_result($check_result);
                }

                mysqli_stmt_close($check_stmt);

                if ($email_exists) {

                    $message = 'A staff member with this email already exists.';
                    $message_type = 'error';

                } else {

                    $profile_photo = null;

                    if (
                        isset($_FILES['profile_photo']) &&
                        $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {

                            $message = 'Unable to upload the profile photo.';
                            $message_type = 'error';

                        } else {

                            $allowed = [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/webp' => 'webp'
                            ];

                            $mime = '';

                            if (function_exists('finfo_open')) {
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                if ($finfo) {
                                    $mime = (string)finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
                                    finfo_close($finfo);
                                }
                            }

                            if (!isset($allowed[$mime])) {

                                $message = 'Only JPG, PNG and WEBP images are allowed.';
                                $message_type = 'error';

                            } elseif ((int)$_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {

                                $message = 'Profile photo must be 5 MB or smaller.';
                                $message_type = 'error';

                            } else {

                                $filename = 'staff_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                                $destination = $upload_dir . $filename;

                                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                                    $profile_photo = 'uploads/staff/' . $filename;
                                } else {
                                    $message = 'Unable to save the profile photo.';
                                    $message_type = 'error';
                                }
                            }
                        }
                    }


                    if ($message_type !== 'error') {

                        $insert_sql = "
                            INSERT INTO staff
                            (
                                department_id,
                                staff_name,
                                gender,
                                date_of_birth,
                                email,
                                phone,
                                designation,
                                joining_date,
                                salary,
                                profile_photo,
                                status
                            )
                            VALUES (?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?)
                        ";

                        $insert_stmt = mysqli_prepare($hospital_conn, $insert_sql);

                        if (!$insert_stmt) {

                            $message = 'Unable to add staff.';
                            $message_type = 'error';

                        } else {

                            mysqli_stmt_bind_param(
                                $insert_stmt,
                                'isssssssdss',
                                $department_id,
                                $staff_name,
                                $gender,
                                $date_of_birth,
                                $email,
                                $phone,
                                $designation,
                                $joining_date,
                                $salary,
                                $profile_photo,
                                $status
                            );

                            if (mysqli_stmt_execute($insert_stmt)) {
                                $message = 'Staff added successfully.';
                                $message_type = 'success';
                            } else {
                                $message = 'Unable to add staff. Please check the entered information.';
                                $message_type = 'error';
                            }

                            mysqli_stmt_close($insert_stmt);
                        }
                    }
                }
            }
        }
    }


    /*==================================================
        EDIT STAFF
    ==================================================*/

    elseif ($action === 'edit_staff') {

        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $staff_name = trim((string)($_POST['staff_name'] ?? ''));
        $department_id = (int)($_POST['department_id'] ?? 0);
        $gender = trim((string)($_POST['gender'] ?? ''));
        $date_of_birth = trim((string)($_POST['date_of_birth'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $designation = trim((string)($_POST['designation'] ?? ''));
        $joining_date = trim((string)($_POST['joining_date'] ?? ''));
        $salary = (float)($_POST['salary'] ?? 0);
        $status = trim((string)($_POST['status'] ?? 'Active'));

        if (
            $staff_id <= 0 ||
            $staff_name === '' ||
            $department_id <= 0 ||
            $gender === '' ||
            $email === '' ||
            $phone === '' ||
            $designation === '' ||
            $joining_date === ''
        ) {
            $message = 'Please fill all required staff details.';
            $message_type = 'error';

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';

        } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
            $message = 'Invalid gender selected.';
            $message_type = 'error';

        } elseif (!in_array($status, ['Active', 'Inactive'], true)) {
            $message = 'Invalid status selected.';
            $message_type = 'error';

        } elseif ($salary < 0) {
            $message = 'Salary cannot be negative.';
            $message_type = 'error';

        } else {

            $existing_sql = "
                SELECT profile_photo
                FROM staff
                WHERE staff_id = ?
                LIMIT 1
            ";

            $existing_stmt = mysqli_prepare($hospital_conn, $existing_sql);

            $existing_photo = null;

            if ($existing_stmt) {

                mysqli_stmt_bind_param($existing_stmt, 'i', $staff_id);
                mysqli_stmt_execute($existing_stmt);

                $existing_result = mysqli_stmt_get_result($existing_stmt);

                if ($existing_result && mysqli_num_rows($existing_result) > 0) {
                    $existing_row = mysqli_fetch_assoc($existing_result);
                    $existing_photo = $existing_row['profile_photo'] ?? null;
                }

                if ($existing_result) {
                    mysqli_free_result($existing_result);
                }

                mysqli_stmt_close($existing_stmt);
            }


            $check_sql = "
                SELECT staff_id
                FROM staff
                WHERE email = ?
                  AND staff_id <> ?
                LIMIT 1
            ";

            $check_stmt = mysqli_prepare($hospital_conn, $check_sql);

            if (!$check_stmt) {

                $message = 'Unable to validate staff information.';
                $message_type = 'error';

            } else {

                mysqli_stmt_bind_param($check_stmt, 'si', $email, $staff_id);
                mysqli_stmt_execute($check_stmt);

                $check_result = mysqli_stmt_get_result($check_stmt);
                $email_exists = $check_result && mysqli_num_rows($check_result) > 0;

                if ($check_result) {
                    mysqli_free_result($check_result);
                }

                mysqli_stmt_close($check_stmt);

                if ($email_exists) {

                    $message = 'Another staff member already uses this email.';
                    $message_type = 'error';

                } else {

                    $profile_photo = $existing_photo;
                    $new_photo_uploaded = false;


                    if (
                        isset($_FILES['profile_photo']) &&
                        $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE
                    ) {

                        if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {

                            $message = 'Unable to upload the profile photo.';
                            $message_type = 'error';

                        } else {

                            $allowed = [
                                'image/jpeg' => 'jpg',
                                'image/png' => 'png',
                                'image/webp' => 'webp'
                            ];

                            $mime = '';

                            if (function_exists('finfo_open')) {
                                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                if ($finfo) {
                                    $mime = (string)finfo_file($finfo, $_FILES['profile_photo']['tmp_name']);
                                    finfo_close($finfo);
                                }
                            }

                            if (!isset($allowed[$mime])) {

                                $message = 'Only JPG, PNG and WEBP images are allowed.';
                                $message_type = 'error';

                            } elseif ((int)$_FILES['profile_photo']['size'] > 5 * 1024 * 1024) {

                                $message = 'Profile photo must be 5 MB or smaller.';
                                $message_type = 'error';

                            } else {

                                $filename = 'staff_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                                $destination = $upload_dir . $filename;

                                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destination)) {
                                    $profile_photo = 'uploads/staff/' . $filename;
                                    $new_photo_uploaded = true;
                                } else {
                                    $message = 'Unable to save the profile photo.';
                                    $message_type = 'error';
                                }
                            }
                        }
                    }


                    if ($message_type !== 'error') {

                        $update_sql = "
                            UPDATE staff
                            SET
                                department_id = ?,
                                staff_name = ?,
                                gender = ?,
                                date_of_birth = NULLIF(?, ''),
                                email = ?,
                                phone = ?,
                                designation = ?,
                                joining_date = ?,
                                salary = ?,
                                profile_photo = ?,
                                status = ?
                            WHERE staff_id = ?
                        ";

                        $update_stmt = mysqli_prepare($hospital_conn, $update_sql);

                        if (!$update_stmt) {

                            $message = 'Unable to update staff.';
                            $message_type = 'error';

                        } else {

                            mysqli_stmt_bind_param(
                                $update_stmt,
                                'isssssssdssi',
                                $department_id,
                                $staff_name,
                                $gender,
                                $date_of_birth,
                                $email,
                                $phone,
                                $designation,
                                $joining_date,
                                $salary,
                                $profile_photo,
                                $status,
                                $staff_id
                            );

                            if (mysqli_stmt_execute($update_stmt)) {

                                if (
                                    $new_photo_uploaded &&
                                    !empty($existing_photo)
                                ) {
                                    $old_file = __DIR__ . '/' . ltrim((string)$existing_photo, '/');
                                    if (is_file($old_file)) {
                                        @unlink($old_file);
                                    }
                                }

                                $message = 'Staff updated successfully.';
                                $message_type = 'success';

                            } else {

                                if (
                                    $new_photo_uploaded &&
                                    !empty($profile_photo)
                                ) {
                                    $new_file = __DIR__ . '/' . ltrim((string)$profile_photo, '/');
                                    if (is_file($new_file)) {
                                        @unlink($new_file);
                                    }
                                }

                                $message = 'Unable to update staff.';
                                $message_type = 'error';
                            }

                            mysqli_stmt_close($update_stmt);
                        }
                    }
                }
            }
        }
    }


    /*==================================================
        DELETE STAFF
    ==================================================*/

    elseif ($action === 'delete_staff') {

        $staff_id = (int)($_POST['staff_id'] ?? 0);

        if ($staff_id <= 0) {

            $message = 'Invalid staff selected.';
            $message_type = 'error';

        } else {

            $existing_sql = "
                SELECT profile_photo
                FROM staff
                WHERE staff_id = ?
                LIMIT 1
            ";

            $existing_stmt = mysqli_prepare($hospital_conn, $existing_sql);
            $existing_photo = null;

            if ($existing_stmt) {

                mysqli_stmt_bind_param($existing_stmt, 'i', $staff_id);
                mysqli_stmt_execute($existing_stmt);

                $existing_result = mysqli_stmt_get_result($existing_stmt);

                if ($existing_result && mysqli_num_rows($existing_result) > 0) {
                    $existing_row = mysqli_fetch_assoc($existing_result);
                    $existing_photo = $existing_row['profile_photo'] ?? null;
                }

                if ($existing_result) {
                    mysqli_free_result($existing_result);
                }

                mysqli_stmt_close($existing_stmt);
            }


            $delete_sql = "
                DELETE FROM staff
                WHERE staff_id = ?
            ";

            $delete_stmt = mysqli_prepare($hospital_conn, $delete_sql);

            if (!$delete_stmt) {

                $message = 'Unable to delete staff.';
                $message_type = 'error';

            } else {

                mysqli_stmt_bind_param($delete_stmt, 'i', $staff_id);

                if (mysqli_stmt_execute($delete_stmt)) {

                    if (mysqli_stmt_affected_rows($delete_stmt) > 0) {

                        if (!empty($existing_photo)) {
                            $old_file = __DIR__ . '/' . ltrim((string)$existing_photo, '/');
                            if (is_file($old_file)) {
                                @unlink($old_file);
                            }
                        }

                        $message = 'Staff deleted successfully.';
                        $message_type = 'success';

                    } else {

                        $message = 'Staff member not found.';
                        $message_type = 'error';

                    }

                } else {

                    $message = 'Unable to delete staff. The record may be linked to another table.';
                    $message_type = 'error';

                }

                mysqli_stmt_close($delete_stmt);
            }
        }
    }
}


/*==================================================
    LOAD DEPARTMENTS
==================================================*/

$departments = [];

$department_sql = "
    SELECT department_id, department_name
    FROM departments
    ORDER BY department_name ASC
";

$department_result = mysqli_query($hospital_conn, $department_sql);

if ($department_result) {

    while ($row = mysqli_fetch_assoc($department_result)) {
        $departments[] = $row;
    }

    mysqli_free_result($department_result);
}


/*==================================================
    FILTERS
==================================================*/

$search = trim((string)($_GET['search'] ?? ''));
$department_filter = (int)($_GET['department'] ?? 0);
$role_filter = trim((string)($_GET['role'] ?? ''));
$status_filter = trim((string)($_GET['status'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$per_page = 10;


/*==================================================
    ROLE LIST
==================================================*/

$roles = [];

$role_sql = "
    SELECT DISTINCT designation
    FROM staff
    WHERE designation IS NOT NULL
      AND designation <> ''
    ORDER BY designation ASC
";

$role_result = mysqli_query($hospital_conn, $role_sql);

if ($role_result) {

    while ($row = mysqli_fetch_assoc($role_result)) {
        $roles[] = (string)$row['designation'];
    }

    mysqli_free_result($role_result);
}


/*==================================================
    STATISTICS
==================================================*/

$total_staff = 0;
$active_staff = 0;
$total_departments = count($departments);
$total_roles = count($roles);


$count_result = mysqli_query(
    $hospital_conn,
    "SELECT COUNT(*) AS total_staff,
            SUM(status = 'Active') AS active_staff
     FROM staff"
);

if ($count_result) {

    $count_row = mysqli_fetch_assoc($count_result);

    $total_staff =
        (int)($count_row['total_staff'] ?? 0);

    $active_staff =
        (int)($count_row['active_staff'] ?? 0);

    mysqli_free_result($count_result);
}


/*==================================================
    STAFF WHERE CLAUSE
==================================================*/

$where = [];
$types = '';
$params = [];


if ($search !== '') {

    $where[] = "
        (
            s.staff_name LIKE ?
            OR s.email LIKE ?
            OR s.phone LIKE ?
            OR s.designation LIKE ?
        )
    ";

    $search_like = '%' . $search . '%';

    $types .= 'ssss';

    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}


if ($department_filter > 0) {

    $where[] = 's.department_id = ?';

    $types .= 'i';

    $params[] = $department_filter;
}


if ($role_filter !== '') {

    $where[] = 's.designation = ?';

    $types .= 's';

    $params[] = $role_filter;
}


if (
    $status_filter !== '' &&
    in_array($status_filter, ['Active', 'Inactive'], true)
) {

    $where[] = 's.status = ?';

    $types .= 's';

    $params[] = $status_filter;
}


$where_sql = '';

if (!empty($where)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}


/*==================================================
    TOTAL FILTERED STAFF
==================================================*/

$count_sql = "
    SELECT COUNT(*) AS total
    FROM staff AS s
    LEFT JOIN departments AS d
        ON d.department_id = s.department_id
    $where_sql
";

$count_stmt = mysqli_prepare($hospital_conn, $count_sql);

$filtered_total = 0;

if ($count_stmt) {

    if ($types !== '') {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }

    mysqli_stmt_execute($count_stmt);

    $count_result = mysqli_stmt_get_result($count_stmt);

    if ($count_result) {
        $count_row = mysqli_fetch_assoc($count_result);
        $filtered_total = (int)($count_row['total'] ?? 0);
        mysqli_free_result($count_result);
    }

    mysqli_stmt_close($count_stmt);
}


$total_pages = max(1, (int)ceil($filtered_total / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;


/*==================================================
    LOAD STAFF
==================================================*/

$staff_members = [];

$staff_sql = "
    SELECT
        s.staff_id,
        s.department_id,
        s.staff_name,
        s.gender,
        s.date_of_birth,
        s.email,
        s.phone,
        s.designation,
        s.joining_date,
        s.salary,
        s.profile_photo,
        s.status,
        s.created_at,
        s.updated_at,
        d.department_name
    FROM staff AS s
    LEFT JOIN departments AS d
        ON d.department_id = s.department_id
    $where_sql
    ORDER BY s.staff_id DESC
    LIMIT ? OFFSET ?
";

$staff_stmt = mysqli_prepare($hospital_conn, $staff_sql);

if ($staff_stmt) {

    $list_types = $types . 'ii';
    $list_params = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;

    mysqli_stmt_bind_param(
        $staff_stmt,
        $list_types,
        ...$list_params
    );

    mysqli_stmt_execute($staff_stmt);

    $staff_result = mysqli_stmt_get_result($staff_stmt);

    if ($staff_result) {

        while ($row = mysqli_fetch_assoc($staff_result)) {
            $staff_members[] = $row;
        }

        mysqli_free_result($staff_result);
    }

    mysqli_stmt_close($staff_stmt);
}


/*==================================================
    PAGINATION QUERY
==================================================*/

$pagination_query = [];

if ($search !== '') {
    $pagination_query['search'] = $search;
}

if ($department_filter > 0) {
    $pagination_query['department'] = $department_filter;
}

if ($role_filter !== '') {
    $pagination_query['role'] = $role_filter;
}

if ($status_filter !== '') {
    $pagination_query['status'] = $status_filter;
}


/*==================================================
    CLOSE DATABASE
==================================================*/

mysqli_close($hospital_conn);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Staff</title>

    <link
        rel="stylesheet"
        href="css/staff.css"
    >

</head>

<body>

<div class="dashboard">

    <?php require 'sidebar.php'; ?>

    <main class="main-content">

        <div class="page-header">

            <div>

                <h1>Staff</h1>

                <p>
                    Manage hospital staff and their information.
                </p>

            </div>

            <button
                type="button"
                class="add-btn"
            >

                <i class="fa-solid fa-plus"></i>

                Add Staff

            </button>

        </div>


        <!-- ==========================
             MESSAGE
        ========================== -->

        <?php if ($message !== ''): ?>

            <div class="page-message <?= e($message_type); ?>">

                <?= e($message); ?>

            </div>

        <?php endif; ?>


        <!-- ==========================
             Statistics Cards
        ========================== -->

        <div class="stats-container">

            <div class="stat-card">

                <div class="stat-icon total">

                    <i class="fa-solid fa-users"></i>

                </div>

                <div class="stat-info">

                    <h4>Total Staff</h4>

                    <h2>
                        <?= number_format($total_staff); ?>
                    </h2>

                    <p>All Staff Members</p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon active">

                    <i class="fa-solid fa-user-check"></i>

                </div>

                <div class="stat-info">

                    <h4>Active Staff</h4>

                    <h2>
                        <?= number_format($active_staff); ?>
                    </h2>

                    <p>Currently Active</p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon department">

                    <i class="fa-solid fa-hospital"></i>

                </div>

                <div class="stat-info">

                    <h4>Total Departments</h4>

                    <h2>
                        <?= number_format($total_departments); ?>
                    </h2>

                    <p>Departments</p>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon role">

                    <i class="fa-solid fa-user-tag"></i>

                </div>

                <div class="stat-info">

                    <h4>Total Roles</h4>

                    <h2>
                        <?= number_format($total_roles); ?>
                    </h2>

                    <p>Different Roles</p>

                </div>

            </div>

        </div>


        <!-- ==========================
             Search & Filter
        ========================== -->

        <form
            method="GET"
            class="toolbar"
            id="staffFilterForm"
        >

            <div class="search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    value="<?= e($search); ?>"
                    placeholder="Search staff..."
                >

            </div>


            <div class="filter-box">

                <i class="fa-solid fa-building"></i>

                <select name="department">

                    <option value="0">
                        All Departments
                    </option>

                    <?php foreach ($departments as $department): ?>

                        <option
                            value="<?= (int)$department['department_id']; ?>"
                            <?= $department_filter === (int)$department['department_id'] ? 'selected' : ''; ?>
                        >
                            <?= e((string)$department['department_name']); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-box">

                <i class="fa-solid fa-user-tag"></i>

                <select name="role">

                    <option value="">
                        All Roles
                    </option>

                    <?php foreach ($roles as $role): ?>

                        <option
                            value="<?= e($role); ?>"
                            <?= $role_filter === $role ? 'selected' : ''; ?>
                        >
                            <?= e($role); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="filter-box">

                <i class="fa-solid fa-circle-check"></i>

                <select name="status">

                    <option value="">
                        All Status
                    </option>

                    <option
                        value="Active"
                        <?= $status_filter === 'Active' ? 'selected' : ''; ?>
                    >
                        Active
                    </option>

                    <option
                        value="Inactive"
                        <?= $status_filter === 'Inactive' ? 'selected' : ''; ?>
                    >
                        Inactive
                    </option>

                </select>

            </div>


            <button
                type="submit"
                class="filter-btn"
            >

                <i class="fa-solid fa-filter"></i>

                Filter

            </button>

            <a
                href="staff.php"
                class="reset-btn"
            >
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </a>

        </form>


        <!-- ==========================
             Staff Table
        ========================== -->

        <div class="table-container">

            <table>

                <thead>

                    <tr>

                        <th>Staff</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody>

                <?php if (empty($staff_members)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="no-records"
                        >
                            No staff records found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($staff_members as $staff): ?>

                        <?php

                        $joining_date = (string)($staff['joining_date'] ?? '');

                        $experience_text = '0 Years';

                        if ($joining_date !== '') {

                            try {

                                $joining = new DateTime($joining_date);
                                $today = new DateTime();

                                if ($joining <= $today) {

                                    $diff = $joining->diff($today);

                                    $years = $diff->y;
                                    $months = $diff->m;

                                    if ($years > 0) {
                                        $experience_text =
                                            $years . ' ' .
                                            ($years === 1 ? 'Year' : 'Years');

                                        if ($months > 0) {
                                            $experience_text .=
                                                ' ' .
                                                $months . ' ' .
                                                ($months === 1 ? 'Month' : 'Months');
                                        }

                                    } else {

                                        $experience_text =
                                            $months . ' ' .
                                            ($months === 1 ? 'Month' : 'Months');

                                    }

                                }

                            } catch (Throwable $e) {
                                $experience_text = '0 Years';
                            }
                        }

                        $photo = trim((string)($staff['profile_photo'] ?? ''));

                        $initials = '';

                        foreach (
                            preg_split(
                                '/\s+/',
                                trim((string)$staff['staff_name'])
                            ) as $part
                        ) {
                            if ($part !== '') {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }

                            if (strlen($initials) >= 2) {
                                break;
                            }
                        }

                        if ($initials === '') {
                            $initials = 'ST';
                        }

                        ?>

                        <tr
                            data-staff-id="<?= (int)$staff['staff_id']; ?>"
                            data-name="<?= e((string)$staff['staff_name']); ?>"
                            data-email="<?= e((string)$staff['email']); ?>"
                            data-phone="<?= e((string)$staff['phone']); ?>"
                            data-department-id="<?= (int)$staff['department_id']; ?>"
                            data-designation="<?= e((string)$staff['designation']); ?>"
                            data-gender="<?= e((string)$staff['gender']); ?>"
                            data-dob="<?= e((string)($staff['date_of_birth'] ?? '')); ?>"
                            data-joining-date="<?= e((string)$staff['joining_date']); ?>"
                            data-salary="<?= e((string)$staff['salary']); ?>"
                            data-status="<?= e((string)$staff['status']); ?>"
                        >

                            <td>

                                <div class="staff-info">

                                    <?php if ($photo !== ''): ?>

                                        <img
                                            src="<?= e($photo); ?>"
                                            alt="<?= e((string)$staff['staff_name']); ?>"
                                        >

                                    <?php else: ?>

                                        <div class="staff-avatar-placeholder">
                                            <?= e($initials); ?>
                                        </div>

                                    <?php endif; ?>


                                    <div>

                                        <h4>
                                            <?= e((string)$staff['staff_name']); ?>
                                        </h4>

                                        <span>
                                            EMP-<?= str_pad(
                                                (string)$staff['staff_id'],
                                                4,
                                                '0',
                                                STR_PAD_LEFT
                                            ); ?>
                                        </span>

                                    </div>

                                </div>

                            </td>


                            <td>
                                <?= e((string)($staff['department_name'] ?? 'N/A')); ?>
                            </td>


                            <td>
                                <?= e((string)$staff['designation']); ?>
                            </td>


                            <td>
                                <?= e((string)$staff['email']); ?>
                            </td>


                            <td>
                                <?= e((string)$staff['phone']); ?>
                            </td>


                            <td>
                                <?= e($experience_text); ?>
                            </td>


                            <td>

                                <span
                                    class="status <?= strtolower((string)$staff['status']) === 'active' ? 'active' : 'inactive'; ?>"
                                >
                                    <?= e((string)$staff['status']); ?>
                                </span>

                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="action-btn view-staff-btn"
                                    data-id="<?= (int)$staff['staff_id']; ?>"
                                    title="View"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>


                                <button
                                    type="button"
                                    class="action-btn edit-staff-btn"
                                    data-id="<?= (int)$staff['staff_id']; ?>"
                                    title="Edit"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>


                                <button
                                    type="button"
                                    class="action-btn delete delete-staff-btn"
                                    data-id="<?= (int)$staff['staff_id']; ?>"
                                    data-name="<?= e((string)$staff['staff_name']); ?>"
                                    title="Delete"
                                >
                                    <i class="fa-solid fa-trash"></i>
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- ==========================
             Pagination
        ========================== -->

        <?php if ($total_pages > 1): ?>

            <div class="pagination">

                <?php

                $previous_query = $pagination_query;
                $previous_query['page'] = max(1, $page - 1);

                ?>

                <a
                    href="staff.php?<?= e(http_build_query($previous_query)); ?>"
                    class="<?= $page <= 1 ? 'disabled' : ''; ?>"
                >
                    <i class="fa-solid fa-angle-left"></i>
                </a>


                <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                    <?php

                    $page_query = $pagination_query;
                    $page_query['page'] = $i;

                    ?>

                    <a
                        href="staff.php?<?= e(http_build_query($page_query)); ?>"
                        class="<?= $i === $page ? 'active' : ''; ?>"
                    >
                        <?= $i; ?>
                    </a>

                <?php endfor; ?>


                <?php

                $next_query = $pagination_query;
                $next_query['page'] = min($total_pages, $page + 1);

                ?>

                <a
                    href="staff.php?<?= e(http_build_query($next_query)); ?>"
                    class="<?= $page >= $total_pages ? 'disabled' : ''; ?>"
                >
                    <i class="fa-solid fa-angle-right"></i>
                </a>

            </div>

        <?php endif; ?>


        <!-- ==========================
             Add Staff Modal
        ========================== -->

        <div
            class="modal"
            id="staffModal"
        >

            <div class="modal-content">

                <div class="modal-header">

                    <h2>Add New Staff</h2>

                    <button
                        type="button"
                        class="close-btn"
                    >
                        &times;
                    </button>

                </div>


                <form
                    id="staffForm"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add_staff"
                    >


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Full Name *
                            </label>

                            <input
                                type="text"
                                name="staff_name"
                                placeholder="Enter full name"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Email *
                            </label>

                            <input
                                type="email"
                                name="email"
                                placeholder="Enter email"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Phone *
                            </label>

                            <input
                                type="text"
                                name="phone"
                                placeholder="Enter phone number"
                                maxlength="20"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Department *
                            </label>

                            <select
                                name="department_id"
                                required
                            >

                                <option value="">
                                    Select Department
                                </option>

                                <?php foreach ($departments as $department): ?>

                                    <option
                                        value="<?= (int)$department['department_id']; ?>"
                                    >
                                        <?= e((string)$department['department_name']); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Role / Designation *
                            </label>

                            <input
                                type="text"
                                name="designation"
                                placeholder="Example: Receptionist"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

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


                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Joining Date *
                            </label>

                            <input
                                type="date"
                                name="joining_date"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Salary
                            </label>

                            <input
                                type="number"
                                name="salary"
                                placeholder="Enter salary"
                                min="0"
                                step="0.01"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status *
                            </label>

                            <select
                                name="status"
                                required
                            >

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Profile Photo
                            </label>

                            <input
                                type="file"
                                name="profile_photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <small>
                                JPG, PNG or WEBP. Maximum 5 MB.
                            </small>

                        </div>


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
                        >
                            Save Staff
                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- ==========================
             View Staff Modal
        ========================== -->

        <div
            class="modal"
            id="viewStaffModal"
        >

            <div class="modal-content view-modal-content">

                <div class="modal-header">

                    <h2>Staff Details</h2>

                    <button
                        type="button"
                        class="view-close-btn"
                    >
                        &times;
                    </button>

                </div>


                <div class="staff-view">

                    <div class="staff-view-profile">

                        <div
                            class="view-avatar"
                            id="viewStaffAvatar"
                        >
                            ST
                        </div>

                        <div>

                            <h2 id="viewStaffName">
                                Staff
                            </h2>

                            <p id="viewStaffDesignation">
                                -
                            </p>

                        </div>

                    </div>


                    <div class="view-grid">

                        <div>
                            <span>Employee ID</span>
                            <strong id="viewStaffId">-</strong>
                        </div>

                        <div>
                            <span>Department</span>
                            <strong id="viewDepartment">-</strong>
                        </div>

                        <div>
                            <span>Email</span>
                            <strong id="viewEmail">-</strong>
                        </div>

                        <div>
                            <span>Phone</span>
                            <strong id="viewPhone">-</strong>
                        </div>

                        <div>
                            <span>Gender</span>
                            <strong id="viewGender">-</strong>
                        </div>

                        <div>
                            <span>Date of Birth</span>
                            <strong id="viewDob">-</strong>
                        </div>

                        <div>
                            <span>Joining Date</span>
                            <strong id="viewJoiningDate">-</strong>
                        </div>

                        <div>
                            <span>Experience</span>
                            <strong id="viewExperience">-</strong>
                        </div>

                        <div>
                            <span>Salary</span>
                            <strong id="viewSalary">-</strong>
                        </div>

                        <div>
                            <span>Status</span>
                            <strong id="viewStatus">-</strong>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ==========================
             Edit Staff Modal
        ========================== -->

        <div
            class="modal"
            id="editStaffModal"
        >

            <div class="modal-content">

                <div class="modal-header">

                    <h2>Edit Staff</h2>

                    <button
                        type="button"
                        class="edit-close-btn"
                    >
                        &times;
                    </button>

                </div>


                <form
                    id="editStaffForm"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="edit_staff"
                    >

                    <input
                        type="hidden"
                        name="staff_id"
                        id="editStaffId"
                        value=""
                    >


                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Full Name *
                            </label>

                            <input
                                type="text"
                                name="staff_name"
                                id="editStaffName"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Email *
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="editEmail"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Phone *
                            </label>

                            <input
                                type="text"
                                name="phone"
                                id="editPhone"
                                maxlength="20"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Department *
                            </label>

                            <select
                                name="department_id"
                                id="editDepartment"
                                required
                            >

                                <option value="">
                                    Select Department
                                </option>

                                <?php foreach ($departments as $department): ?>

                                    <option
                                        value="<?= (int)$department['department_id']; ?>"
                                    >
                                        <?= e((string)$department['department_name']); ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Role / Designation *
                            </label>

                            <input
                                type="text"
                                name="designation"
                                id="editDesignation"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

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


                        <div class="form-group">

                            <label>
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                name="date_of_birth"
                                id="editDob"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Joining Date *
                            </label>

                            <input
                                type="date"
                                name="joining_date"
                                id="editJoiningDate"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Salary
                            </label>

                            <input
                                type="number"
                                name="salary"
                                id="editSalary"
                                min="0"
                                step="0.01"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status *
                            </label>

                            <select
                                name="status"
                                id="editStatus"
                                required
                            >

                                <option value="Active">
                                    Active
                                </option>

                                <option value="Inactive">
                                    Inactive
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label>
                                Change Profile Photo
                            </label>

                            <input
                                type="file"
                                name="profile_photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <small>
                                Leave empty to keep the current photo.
                            </small>

                        </div>


                    </div>


                    <div class="form-actions">

                        <button
                            type="button"
                            class="edit-cancel-btn cancel-btn"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Update Staff
                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- ==========================
             Delete Staff Modal
        ========================== -->

        <div
            class="modal"
            id="deleteStaffModal"
        >

            <div class="modal-content delete-modal-content">

                <div class="modal-header">

                    <h2>Delete Staff</h2>

                    <button
                        type="button"
                        class="delete-close-btn"
                    >
                        &times;
                    </button>

                </div>


                <div class="delete-body">

                    <div class="delete-icon">
                        <i class="fa-solid fa-trash"></i>
                    </div>

                    <h3>
                        Delete Staff Member?
                    </h3>

                    <p>
                        Are you sure you want to delete
                        <strong id="deleteStaffName">
                            this staff member
                        </strong>?
                    </p>

                    <p class="delete-warning">
                        This action cannot be undone.
                    </p>

                </div>


                <form
                    method="POST"
                    class="delete-form"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="delete_staff"
                    >

                    <input
                        type="hidden"
                        name="staff_id"
                        id="deleteStaffId"
                        value=""
                    >


                    <button
                        type="button"
                        class="cancel-delete-btn cancel-btn"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="delete-confirm-btn"
                    >
                        Delete Staff
                    </button>

                </form>

            </div>

        </div>

    </main>

</div>

<script src="js/staff.js"></script>

</body>

</html>
