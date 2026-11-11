<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';

mysqli_report(MYSQLI_REPORT_OFF);


/* =========================================================
   CENTRAL DATABASE
========================================================= */

$centralConn = db_connect();

if (!$centralConn) {
    die("Central database connection failed: " . mysqli_connect_error());
}


/* =========================================================
   APPLICATION NUMBER
========================================================= */

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found in session.");
}


/* =========================================================
   GET HOSPITAL DATABASE
========================================================= */

$hospitalDb = '';

$stmt = mysqli_prepare(
    $centralConn,
    "SELECT database_name
     FROM hospital_registration
     WHERE application_no = ?
     LIMIT 1"
);

if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $application_no
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {

        $row = mysqli_fetch_assoc($result);

        $hospitalDb = $row['database_name'] ?? '';
    }

    mysqli_stmt_close($stmt);
}


if ($hospitalDb === '') {
    $hospitalDb = 'hospital_' . $application_no;
}


/* =========================================================
   VALIDATE DATABASE NAME
========================================================= */

if (!preg_match('/^[a-zA-Z0-9_]+$/', $hospitalDb)) {
    die("Invalid hospital database name.");
}


/* =========================================================
   HOSPITAL DATABASE
========================================================= */

$hospitalConn = db_connect($hospitalDb);

if (!$hospitalConn) {
    die(
        "Hospital database connection failed: "
        . mysqli_connect_error()
    );
}

mysqli_set_charset($hospitalConn, "utf8mb4");


/* =========================================================
   VARIABLES
========================================================= */

$successMessage = '';
$errorMessage = '';

$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');


/* =========================================================
   POST ACTION
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /* =====================================================
       ADD DEPARTMENT
    ===================================================== */

    if ($action === 'add_department') {

        $departmentName =
            trim($_POST['department_name'] ?? '');

        $description =
            trim($_POST['description'] ?? '');

        $location =
            trim($_POST['location'] ?? '');

        $status =
            trim($_POST['status'] ?? 'Active');


        if ($departmentName === '') {

            $errorMessage =
                "Department name is required.";

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $errorMessage =
                "Invalid department status.";

        } else {

            /* Check duplicate */

            $checkStmt = mysqli_prepare(
                $hospitalConn,
                "SELECT department_id
                 FROM departments
                 WHERE department_name = ?
                 LIMIT 1"
            );

            if ($checkStmt) {

                mysqli_stmt_bind_param(
                    $checkStmt,
                    "s",
                    $departmentName
                );

                mysqli_stmt_execute($checkStmt);

                $checkResult =
                    mysqli_stmt_get_result($checkStmt);

                if (
                    $checkResult &&
                    mysqli_num_rows($checkResult) > 0
                ) {

                    $errorMessage =
                        "This department already exists.";
                }

                mysqli_stmt_close($checkStmt);
            }


            if ($errorMessage === '') {

                $insertStmt = mysqli_prepare(
                    $hospitalConn,
                    "INSERT INTO departments
                    (
                        department_name,
                        description,
                        location,
                        status
                    )
                    VALUES (?, ?, ?, ?)"
                );

                if ($insertStmt) {

                    mysqli_stmt_bind_param(
                        $insertStmt,
                        "ssss",
                        $departmentName,
                        $description,
                        $location,
                        $status
                    );

                    if (
                        mysqli_stmt_execute($insertStmt)
                    ) {

                        $successMessage =
                            "Department added successfully.";

                    } else {

                        $errorMessage =
                            "Unable to add department: "
                            . mysqli_stmt_error($insertStmt);
                    }

                    mysqli_stmt_close($insertStmt);

                } else {

                    $errorMessage =
                        "Department insert query failed: "
                        . mysqli_error($hospitalConn);
                }
            }
        }
    }


    /* =====================================================
       EDIT DEPARTMENT
    ===================================================== */

    elseif ($action === 'edit_department') {

        $departmentId =
            (int)($_POST['department_id'] ?? 0);

        $departmentName =
            trim($_POST['department_name'] ?? '');

        $description =
            trim($_POST['description'] ?? '');

        $headDoctorId =
            (int)($_POST['head_doctor_id'] ?? 0);

        $location =
            trim($_POST['location'] ?? '');

        $status =
            trim($_POST['status'] ?? 'Active');


        if ($departmentId <= 0) {

            $errorMessage =
                "Invalid department.";

        } elseif ($departmentName === '') {

            $errorMessage =
                "Department name is required.";

        } elseif (
            !in_array(
                $status,
                ['Active', 'Inactive'],
                true
            )
        ) {

            $errorMessage =
                "Invalid department status.";

        } else {


            /* ---------------------------------------------
               CHECK DUPLICATE NAME
            --------------------------------------------- */

            $checkStmt = mysqli_prepare(
                $hospitalConn,
                "SELECT department_id
                 FROM departments
                 WHERE department_name = ?
                 AND department_id != ?
                 LIMIT 1"
            );

            if ($checkStmt) {

                mysqli_stmt_bind_param(
                    $checkStmt,
                    "si",
                    $departmentName,
                    $departmentId
                );

                mysqli_stmt_execute($checkStmt);

                $checkResult =
                    mysqli_stmt_get_result($checkStmt);

                if (
                    $checkResult &&
                    mysqli_num_rows($checkResult) > 0
                ) {

                    $errorMessage =
                        "Another department with this name already exists.";
                }

                mysqli_stmt_close($checkStmt);
            }


            /* ---------------------------------------------
               VALIDATE HEAD DOCTOR
            --------------------------------------------- */

            if (
                $errorMessage === '' &&
                $headDoctorId > 0
            ) {

                $doctorCheckStmt = mysqli_prepare(
                    $hospitalConn,
                    "SELECT doctor_id
                     FROM doctors
                     WHERE doctor_id = ?
                     AND department_id = ?
                     LIMIT 1"
                );

                if ($doctorCheckStmt) {

                    mysqli_stmt_bind_param(
                        $doctorCheckStmt,
                        "ii",
                        $headDoctorId,
                        $departmentId
                    );

                    mysqli_stmt_execute(
                        $doctorCheckStmt
                    );

                    $doctorCheckResult =
                        mysqli_stmt_get_result(
                            $doctorCheckStmt
                        );

                    if (
                        !$doctorCheckResult ||
                        mysqli_num_rows(
                            $doctorCheckResult
                        ) === 0
                    ) {

                        $errorMessage =
                            "Selected Head Doctor does not belong to this department.";
                    }

                    mysqli_stmt_close(
                        $doctorCheckStmt
                    );
                }
            }


            /* ---------------------------------------------
               UPDATE
            --------------------------------------------- */

         /* ---------------------------------------------
   UPDATE
--------------------------------------------- */

if ($headDoctorId > 0) {

    $updateStmt = mysqli_prepare(
        $hospitalConn,
        "UPDATE departments
         SET
            department_name = ?,
            description = ?,
            head_doctor_id = ?,
            location = ?,
            status = ?
         WHERE department_id = ?"
    );

    if ($updateStmt) {

        mysqli_stmt_bind_param(
            $updateStmt,
            "ssissi",
            $departmentName,
            $description,
            $headDoctorId,
            $location,
            $status,
            $departmentId
        );
    }

} else {

    $updateStmt = mysqli_prepare(
        $hospitalConn,
        "UPDATE departments
         SET
            department_name = ?,
            description = ?,
            head_doctor_id = NULL,
            location = ?,
            status = ?
         WHERE department_id = ?"
    );

    if ($updateStmt) {

        mysqli_stmt_bind_param(
            $updateStmt,
            "ssssi",
            $departmentName,
            $description,
            $location,
            $status,
            $departmentId
        );
    }
}


if (
    isset($updateStmt) &&
    $updateStmt
) {

    if (
        mysqli_stmt_execute($updateStmt)
    ) {

        $successMessage =
            "Department updated successfully.";

    } else {

        $errorMessage =
            "Unable to update department: "
            . mysqli_stmt_error(
                $updateStmt
            );
    }

    mysqli_stmt_close(
        $updateStmt
    );

} else {

    $errorMessage =
        "Department update query failed.";
}}}


    /* =====================================================
       DELETE DEPARTMENT
    ===================================================== */

    elseif ($action === 'delete_department') {

        $departmentId =
            (int)($_POST['department_id'] ?? 0);


        if ($departmentId <= 0) {

            $errorMessage =
                "Invalid department.";

        } else {

            /* ---------------------------------------------
               CHECK DOCTORS
            --------------------------------------------- */

            $doctorCount = 0;

            $stmt = mysqli_prepare(
                $hospitalConn,
                "SELECT COUNT(*) AS total
                 FROM doctors
                 WHERE department_id = ?"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $departmentId
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                if ($result) {

                    $row =
                        mysqli_fetch_assoc($result);

                    $doctorCount =
                        (int)($row['total'] ?? 0);
                }

                mysqli_stmt_close($stmt);
            }


            /* ---------------------------------------------
               CHECK STAFF
            --------------------------------------------- */

            $staffCount = 0;

            $stmt = mysqli_prepare(
                $hospitalConn,
                "SELECT COUNT(*) AS total
                 FROM staff
                 WHERE department_id = ?"
            );

            if ($stmt) {

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $departmentId
                );

                mysqli_stmt_execute($stmt);

                $result =
                    mysqli_stmt_get_result($stmt);

                if ($result) {

                    $row =
                        mysqli_fetch_assoc($result);

                    $staffCount =
                        (int)($row['total'] ?? 0);
                }

                mysqli_stmt_close($stmt);
            }


            if (
                $doctorCount > 0 ||
                $staffCount > 0
            ) {

                $errorMessage =
                    "This department cannot be deleted because it has "
                    . $doctorCount
                    . " doctor(s) and "
                    . $staffCount
                    . " staff member(s).";

            } else {

                $deleteStmt = mysqli_prepare(
                    $hospitalConn,
                    "DELETE FROM departments
                     WHERE department_id = ?"
                );

                if ($deleteStmt) {

                    mysqli_stmt_bind_param(
                        $deleteStmt,
                        "i",
                        $departmentId
                    );

                    if (
                        mysqli_stmt_execute(
                            $deleteStmt
                        )
                    ) {

                        $successMessage =
                            "Department deleted successfully.";

                    } else {

                        $errorMessage =
                            "Unable to delete department: "
                            . mysqli_stmt_error(
                                $deleteStmt
                            );
                    }

                    mysqli_stmt_close(
                        $deleteStmt
                    );

                } else {

                    $errorMessage =
                        "Delete query failed.";
                }
            }
        }
    }
}


/* =========================================================
   GET DOCTORS
========================================================= */

$allDoctors = [];

$doctorQuery = mysqli_query(
    $hospitalConn,
    "SELECT
        doctor_id,
        department_id,
        doctor_name,
        specialization,
        status
     FROM doctors
     ORDER BY doctor_name ASC"
);

if ($doctorQuery) {

    while ($doctor = mysqli_fetch_assoc(
        $doctorQuery
    )) {

        $allDoctors[] = $doctor;
    }
}


/* =========================================================
   STATISTICS
========================================================= */

$totalDepartments = 0;

$result = mysqli_query(
    $hospitalConn,
    "SELECT COUNT(*) AS total
     FROM departments"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalDepartments =
        (int)($row['total'] ?? 0);
}


$totalDoctors = 0;

$result = mysqli_query(
    $hospitalConn,
    "SELECT COUNT(*) AS total
     FROM doctors"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalDoctors =
        (int)($row['total'] ?? 0);
}


$totalStaff = 0;

$result = mysqli_query(
    $hospitalConn,
    "SELECT COUNT(*) AS total
     FROM staff"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalStaff =
        (int)($row['total'] ?? 0);
}


$activeDepartments = 0;

$result = mysqli_query(
    $hospitalConn,
    "SELECT COUNT(*) AS total
     FROM departments
     WHERE status = 'Active'"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $activeDepartments =
        (int)($row['total'] ?? 0);
}


/* =========================================================
   DEPARTMENT LIST
========================================================= */

$departments = [];

$sql = "
    SELECT

        d.department_id,
        d.department_name,
        d.description,
        d.head_doctor_id,
        d.location,
        d.status,

        COALESCE(
            (
                SELECT COUNT(*)
                FROM doctors dc
                WHERE dc.department_id = d.department_id
            ),
            0
        ) AS doctor_count,

        COALESCE(
            (
                SELECT COUNT(*)
                FROM staff st
                WHERE st.department_id = d.department_id
            ),
            0
        ) AS staff_count,

        hd.doctor_name AS head_doctor_name,
        hd.specialization AS head_doctor_specialization

    FROM departments d

    LEFT JOIN doctors hd
        ON hd.doctor_id = d.head_doctor_id
";


$where = [];

$params = [];

$types = '';


/* =========================================================
   SEARCH
========================================================= */

if ($search !== '') {

    $where[] = "
        (
            d.department_name LIKE ?
            OR d.description LIKE ?
            OR d.location LIKE ?
            OR hd.doctor_name LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= 'ssss';
}


/* =========================================================
   STATUS
========================================================= */

if (
    in_array(
        $statusFilter,
        ['Active', 'Inactive'],
        true
    )
) {

    $where[] =
        "d.status = ?";

    $params[] =
        $statusFilter;

    $types .= 's';
}


/* =========================================================
   WHERE
========================================================= */

if (!empty($where)) {

    $sql .=
        " WHERE " .
        implode(
            " AND ",
            $where
        );
}


/* =========================================================
   ORDER
========================================================= */

$sql .= "
    ORDER BY d.department_name ASC
";


$stmt = mysqli_prepare(
    $hospitalConn,
    $sql
);

if (!$stmt) {

    die(
        "Department query failed: "
        . mysqli_error($hospitalConn)
    );
}


if (!empty($params)) {

    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );
}


mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);


if ($result) {

    while ($row =
        mysqli_fetch_assoc($result)
    ) {

        $departments[] =
            $row;
    }
}

mysqli_stmt_close($stmt);


/* =========================================================
   FUNCTIONS
========================================================= */

function getDepartmentIcon($name)
{
    $name =
        strtolower(trim($name));


    if (
        strpos($name, 'cardio') !== false ||
        strpos($name, 'heart') !== false
    ) {
        return 'fa-heart-pulse';
    }


    if (
        strpos($name, 'neuro') !== false ||
        strpos($name, 'brain') !== false
    ) {
        return 'fa-brain';
    }


    if (
        strpos($name, 'ortho') !== false ||
        strpos($name, 'bone') !== false
    ) {
        return 'fa-bone';
    }


    if (
        strpos($name, 'pediatric') !== false ||
        strpos($name, 'child') !== false
    ) {
        return 'fa-baby';
    }


    if (
        strpos($name, 'emergency') !== false
    ) {
        return 'fa-truck-medical';
    }


    if (
        strpos($name, 'gyne') !== false ||
        strpos($name, 'women') !== false
    ) {
        return 'fa-person-pregnant';
    }


    if (
        strpos($name, 'dental') !== false
    ) {
        return 'fa-tooth';
    }


    if (
        strpos($name, 'eye') !== false ||
        strpos($name, 'ophthal') !== false
    ) {
        return 'fa-eye';
    }


    return 'fa-building';
}


function getDepartmentIconClass($name)
{
    $name =
        strtolower(trim($name));


    if (
        strpos($name, 'neuro') !== false
    ) {
        return 'neuro';
    }


    if (
        strpos($name, 'ortho') !== false ||
        strpos($name, 'bone') !== false
    ) {
        return 'bone';
    }


    if (
        strpos($name, 'pediatric') !== false ||
        strpos($name, 'child') !== false
    ) {
        return 'child';
    }


    if (
        strpos($name, 'emergency') !== false
    ) {
        return 'emergency';
    }


    return '';
}


function displayDoctorName($name)
{
    if (!$name) {
        return 'Not Assigned';
    }


    $name =
        trim($name);


    if (
        stripos($name, 'dr.') === 0 ||
        stripos($name, 'dr ') === 0
    ) {
        return $name;
    }


    return 'Dr. ' . $name;
}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Departments</title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="css/departments.css"
    >

</head>


<body>


<div class="dashboard">


    <?php require 'sidebar.php'; ?>


    <main class="main-content">


        <!-- =========================================
             HEADER
        ========================================== -->

        <div class="page-header">

            <div>

                <h1>Departments</h1>

                <p>
                    Manage hospital departments and their information.
                </p>

            </div>


            <button
                type="button"
                class="add-btn"
                id="addDepartmentBtn"
            >

                <i class="fa-solid fa-plus"></i>

                Add Department

            </button>

        </div>


        <!-- =========================================
             MESSAGES
        ========================================== -->

        <?php if ($successMessage !== ''): ?>

            <div class="department-message success-message">

                <i class="fa-solid fa-circle-check"></i>

                <?php
                echo htmlspecialchars(
                    $successMessage
                );
                ?>

            </div>

        <?php endif; ?>


        <?php if ($errorMessage !== ''): ?>

            <div class="department-message error-message">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?php
                echo htmlspecialchars(
                    $errorMessage
                );
                ?>

            </div>

        <?php endif; ?>


        <!-- =========================================
             STATISTICS
        ========================================== -->

        <div class="stats-container">


            <div class="stat-card">

                <div class="stat-icon purple">

                    <i class="fa-solid fa-building"></i>

                </div>

                <div>

                    <h4>Total Departments</h4>

                    <h2>
                        <?php
                        echo $totalDepartments;
                        ?>
                    </h2>

                    <span>All Departments</span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon green">

                    <i class="fa-solid fa-user-doctor"></i>

                </div>

                <div>

                    <h4>Total Doctors</h4>

                    <h2>
                        <?php
                        echo $totalDoctors;
                        ?>
                    </h2>

                    <span>
                        Across All Departments
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon blue">

                    <i class="fa-solid fa-users"></i>

                </div>

                <div>

                    <h4>Total Staff</h4>

                    <h2>
                        <?php
                        echo $totalStaff;
                        ?>
                    </h2>

                    <span>
                        Across All Departments
                    </span>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-icon orange">

                    <i class="fa-solid fa-heart-pulse"></i>

                </div>

                <div>

                    <h4>Active Departments</h4>

                    <h2>
                        <?php
                        echo $activeDepartments;
                        ?>
                    </h2>

                    <span>
                        Currently Active
                    </span>

                </div>

            </div>


        </div>


        <!-- =========================================
             TOOLBAR
        ========================================== -->

        <form
            method="GET"
            class="department-toolbar"
        >


            <div class="search-box">

                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="text"
                    name="search"
                    value="<?php
                    echo htmlspecialchars($search);
                    ?>"
                    placeholder="Search department..."
                >

            </div>


            <select
                class="status-filter"
                name="status"
            >

                <option value="">
                    All Status
                </option>

                <option
                    value="Active"
                    <?php
                    echo $statusFilter === 'Active'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Active
                </option>

                <option
                    value="Inactive"
                    <?php
                    echo $statusFilter === 'Inactive'
                        ? 'selected'
                        : '';
                    ?>
                >
                    Inactive
                </option>

            </select>


            <button
                type="submit"
                class="filter-btn"
            >

                <i class="fa-solid fa-filter"></i>

                Filter

            </button>


            <a
                href="departments.php"
                class="reset-btn"
            >
                <i class="fa-solid fa-rotate-left"></i>
                Reset
            </a>


        </form>


        <!-- =========================================
             TABLE
        ========================================== -->

        <div class="table-container">


            <table class="department-table">


                <thead>

                    <tr>

                        <th>Department Name</th>

                        <th>Head Doctor</th>

                        <th>Doctors</th>

                        <th>Staff</th>

                        <th>Location</th>

                        <th>Status</th>

                        <th>Actions</th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($departments)): ?>


                    <tr>

                        <td
                            colspan="7"
                            class="empty-departments"
                        >

                            <i
                                class="fa-solid fa-building"
                            ></i>

                            <p>
                                No departments found.
                            </p>

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>


                        <?php

                        $departmentId =
                            (int)$department[
                                'department_id'
                            ];

                        $departmentName =
                            $department[
                                'department_name'
                            ] ?? '';

                        $description =
                            $department[
                                'description'
                            ] ?? '';

                        $headDoctor =
                            $department[
                                'head_doctor_name'
                            ] ?? '';

                        $headSpecialization =
                            $department[
                                'head_doctor_specialization'
                            ] ?? '';

                        $headDoctorId =
                            (int)(
                                $department[
                                    'head_doctor_id'
                                ] ?? 0
                            );

                        $location =
                            $department[
                                'location'
                            ] ?? '';

                        $departmentStatus =
                            $department[
                                'status'
                            ] ?? 'Inactive';

                        $doctorCount =
                            (int)(
                                $department[
                                    'doctor_count'
                                ] ?? 0
                            );

                        $staffCount =
                            (int)(
                                $department[
                                    'staff_count'
                                ] ?? 0
                            );

                        $icon =
                            getDepartmentIcon(
                                $departmentName
                            );

                        $iconClass =
                            getDepartmentIconClass(
                                $departmentName
                            );

                        ?>


                        <tr>


                            <!-- Department -->

                            <td>

                                <div class="department-info">

                                    <div
                                        class="department-icon <?php
                                        echo htmlspecialchars(
                                            $iconClass
                                        );
                                        ?>"
                                    >

                                        <i
                                            class="fa-solid <?php
                                            echo htmlspecialchars(
                                                $icon
                                            );
                                            ?>"
                                        ></i>

                                    </div>


                                    <div>

                                        <h4>

                                            <?php
                                            echo htmlspecialchars(
                                                $departmentName
                                            );
                                            ?>

                                        </h4>

                                        <p>

                                            <?php
                                            echo htmlspecialchars(
                                                $description !== ''
                                                    ? $description
                                                    : 'Hospital Department'
                                            );
                                            ?>

                                        </p>

                                    </div>

                                </div>

                            </td>


                            <!-- Head Doctor -->

                            <td>

                                <?php if (
                                    $headDoctor !== ''
                                ): ?>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            displayDoctorName(
                                                $headDoctor
                                            )
                                        );
                                        ?>

                                    </strong>


                                    <?php if (
                                        $headSpecialization !== ''
                                    ): ?>

                                        <p>

                                            <?php
                                            echo htmlspecialchars(
                                                $headSpecialization
                                            );
                                            ?>

                                        </p>

                                    <?php endif; ?>


                                <?php else: ?>

                                    <strong>
                                        Not Assigned
                                    </strong>

                                    <p>
                                        No Head Doctor
                                    </p>

                                <?php endif; ?>

                            </td>


                            <!-- Doctors -->

                            <td>

                                <span class="count doctor-count">

                                    <?php
                                    echo $doctorCount;
                                    ?>

                                </span>

                            </td>


                            <!-- Staff -->

                            <td>

                                <span class="count staff-count">

                                    <?php
                                    echo $staffCount;
                                    ?>

                                </span>

                            </td>


                            <!-- Location -->

                            <td>

                                <?php
                                echo $location !== ''
                                    ? htmlspecialchars(
                                        $location
                                    )
                                    : 'Not specified';
                                ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <span
                                    class="status <?php
                                    echo strtolower(
                                        $departmentStatus
                                    ) === 'active'
                                        ? 'active'
                                        : 'inactive';
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $departmentStatus
                                    );
                                    ?>

                                </span>

                            </td>


                            <!-- Actions -->

                            <td>

                                <div class="action-buttons">


                                    <button
                                        type="button"
                                        class="view-department-btn"
                                        data-id="<?php
                                        echo $departmentId;
                                        ?>"
                                        title="View"
                                    >

                                        <i
                                            class="fa-solid fa-eye"
                                        ></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="edit-department-btn"
                                        data-id="<?php
                                        echo $departmentId;
                                        ?>"
                                        title="Edit"
                                    >

                                        <i
                                            class="fa-solid fa-pen"
                                        ></i>

                                    </button>


                                    <button
                                        type="button"
                                        class="more-department-btn"
                                        data-id="<?php
                                        echo $departmentId;
                                        ?>"
                                        title="More"
                                    >

                                        <i
                                            class="fa-solid fa-ellipsis"
                                        ></i>

                                    </button>


                                </div>


                                <!-- Hidden department data -->

                                <div
                                    class="department-data"
                                    id="department-<?php
                                    echo $departmentId;
                                    ?>"
                                    data-id="<?php
                                    echo $departmentId;
                                    ?>"
                                    data-name="<?php
                                    echo htmlspecialchars(
                                        $departmentName,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-description="<?php
                                    echo htmlspecialchars(
                                        $description,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-head-doctor-id="<?php
                                    echo $headDoctorId;
                                    ?>"
                                    data-head-doctor="<?php
                                    echo htmlspecialchars(
                                        $headDoctor,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-head-specialization="<?php
                                    echo htmlspecialchars(
                                        $headSpecialization,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-location="<?php
                                    echo htmlspecialchars(
                                        $location,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-status="<?php
                                    echo htmlspecialchars(
                                        $departmentStatus,
                                        ENT_QUOTES
                                    );
                                    ?>"
                                    data-doctors="<?php
                                    echo $doctorCount;
                                    ?>"
                                    data-staff="<?php
                                    echo $staffCount;
                                    ?>"
                                ></div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>


            </table>


            <!-- =========================================
                 FOOTER
            ========================================== -->

            <div class="table-footer">

                <p>

                    Showing
                    <?php
                    echo count($departments);
                    ?>
                    Department<?php
                    echo count($departments) === 1
                        ? ''
                        : 's';
                    ?>

                </p>


                <div class="pagination">

                    <button
                        type="button"
                        disabled
                    >

                        <i
                            class="fa-solid fa-angle-left"
                        ></i>

                    </button>


                    <button
                        type="button"
                        class="active-page"
                    >
                        1
                    </button>


                    <button
                        type="button"
                        disabled
                    >

                        <i
                            class="fa-solid fa-angle-right"
                        ></i>

                    </button>

                </div>

            </div>


        </div>


        <!-- =================================================
             ADD DEPARTMENT MODAL
        ================================================== -->

        <div
            class="modal-overlay"
            id="departmentModal"
        >

            <div class="modal">

                <div class="modal-header">

                    <h2>
                        Add Department
                    </h2>

                    <button
                        type="button"
                        class="close-modal"
                        id="closeModal"
                    >

                        <i
                            class="fa-solid fa-xmark"
                        ></i>

                    </button>

                </div>


                <form
                    id="departmentForm"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add_department"
                    >


                    <div class="form-group">

                        <label>
                            Department Name
                        </label>

                        <input
                            type="text"
                            name="department_name"
                            placeholder="Enter Department Name"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Description
                        </label>

                        <textarea
                            name="description"
                            placeholder="Department Description"
                        ></textarea>

                    </div>


                    <div class="form-row">

                        <div class="form-group">

                            <label>
                                Location
                            </label>

                            <input
                                type="text"
                                name="location"
                                placeholder="Building A"
                                maxlength="150"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
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


                    <div class="modal-buttons">

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
                            Save Department
                        </button>

                    </div>


                </form>

            </div>

        </div>


        <!-- =================================================
             VIEW DEPARTMENT MODAL
        ================================================== -->

        <div
            class="modal-overlay"
            id="viewDepartmentModal"
        >

            <div class="modal department-view-modal">

                <div class="modal-header">

                    <h2>
                        Department Details
                    </h2>

                    <button
                        type="button"
                        class="close-modal"
                        data-close="viewDepartmentModal"
                    >

                        <i
                            class="fa-solid fa-xmark"
                        ></i>

                    </button>

                </div>


                <div class="department-details">


                    <div class="detail-row">

                        <span>
                            Department
                        </span>

                        <strong
                            id="viewDepartmentName"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Description
                        </span>

                        <strong
                            id="viewDepartmentDescription"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Head Doctor
                        </span>

                        <strong
                            id="viewDepartmentHeadDoctor"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Specialization
                        </span>

                        <strong
                            id="viewDepartmentSpecialization"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Doctors
                        </span>

                        <strong
                            id="viewDepartmentDoctors"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Staff
                        </span>

                        <strong
                            id="viewDepartmentStaff"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Location
                        </span>

                        <strong
                            id="viewDepartmentLocation"
                        >
                            -
                        </strong>

                    </div>


                    <div class="detail-row">

                        <span>
                            Status
                        </span>

                        <strong
                            id="viewDepartmentStatus"
                        >
                            -
                        </strong>

                    </div>


                </div>

            </div>

        </div>


        <!-- =================================================
             EDIT DEPARTMENT MODAL
        ================================================== -->

        <div
            class="modal-overlay"
            id="editDepartmentModal"
        >

            <div class="modal">

                <div class="modal-header">

                    <h2>
                        Edit Department
                    </h2>

                    <button
                        type="button"
                        class="close-modal"
                        data-close="editDepartmentModal"
                    >

                        <i
                            class="fa-solid fa-xmark"
                        ></i>

                    </button>

                </div>


                <form
                    id="editDepartmentForm"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="edit_department"
                    >

                    <input
                        type="hidden"
                        name="department_id"
                        id="editDepartmentId"
                    >


                    <div class="form-group">

                        <label>
                            Department Name
                        </label>

                        <input
                            type="text"
                            name="department_name"
                            id="editDepartmentName"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Description
                        </label>

                        <textarea
                            name="description"
                            id="editDepartmentDescription"
                        ></textarea>

                    </div>


                    <!-- HEAD DOCTOR -->

                    <div class="form-group">

                        <label>
                            Head Doctor
                        </label>

                        <select
                            name="head_doctor_id"
                            id="editHeadDoctor"
                        >

                            <option value="">
                                No Head Doctor
                            </option>

                            <?php foreach (
                                $allDoctors
                                as $doctor
                            ): ?>

                                <option
                                    value="<?php
                                    echo (int)$doctor[
                                        'doctor_id'
                                    ];
                                    ?>"
                                    data-department="<?php
                                    echo (int)$doctor[
                                        'department_id'
                                    ];
                                    ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        displayDoctorName(
                                            $doctor[
                                                'doctor_name'
                                            ]
                                        )
                                    );

                                    if (
                                        !empty(
                                            $doctor[
                                                'specialization'
                                            ]
                                        )
                                    ) {

                                        echo ' - ' .
                                            htmlspecialchars(
                                                $doctor[
                                                    'specialization'
                                                ]
                                            );
                                    }
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-row">


                        <div class="form-group">

                            <label>
                                Location
                            </label>

                            <input
                                type="text"
                                name="location"
                                id="editDepartmentLocation"
                                maxlength="150"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                id="editDepartmentStatus"
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


                    <div class="modal-buttons">

                        <button
                            type="button"
                            class="cancel-btn"
                            data-close="editDepartmentModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="save-btn"
                        >
                            Update Department
                        </button>

                    </div>


                </form>

            </div>

        </div>


        <!-- =================================================
             DELETE DEPARTMENT MODAL
        ================================================== -->

        <div
            class="modal-overlay"
            id="deleteDepartmentModal"
        >

            <div class="modal delete-modal">

                <div class="delete-icon">

                    <i
                        class="fa-solid fa-trash"
                    ></i>

                </div>


                <h2>
                    Delete Department?
                </h2>


                <p>

                    Are you sure you want to delete

                    <strong
                        id="deleteDepartmentName"
                    >
                        this department
                    </strong>

                    ?

                </p>


                <form
                    method="POST"
                    id="deleteDepartmentForm"
                >

                    <input
                        type="hidden"
                        name="action"
                        value="delete_department"
                    >

                    <input
                        type="hidden"
                        name="department_id"
                        id="deleteDepartmentId"
                    >


                    <div class="modal-buttons">

                        <button
                            type="button"
                            class="cancel-btn"
                            data-close="deleteDepartmentModal"
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            class="delete-confirm-btn"
                        >
                            Delete Department
                        </button>

                    </div>

                </form>

            </div>

        </div>


        <!-- =================================================
             MORE ACTION MENU
        ================================================== -->

        <div
            class="department-action-menu"
            id="departmentActionMenu"
        >

            <button
                type="button"
                id="menuViewBtn"
            >

                <i class="fa-solid fa-eye"></i>

                View

            </button>


            <button
                type="button"
                id="menuEditBtn"
            >

                <i class="fa-solid fa-pen"></i>

                Edit

            </button>


            <button
                type="button"
                id="menuDeleteBtn"
                class="danger-action"
            >

                <i class="fa-solid fa-trash"></i>

                Delete

            </button>

        </div>


    </main>

</div>


<script src="js/departments.js"></script>


</body>

</html>