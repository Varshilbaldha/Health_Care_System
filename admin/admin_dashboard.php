<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';
require '../includes/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found in session.");
}

if (!preg_match('/^[a-zA-Z0-9]+$/', $application_no)) {
    die("Invalid hospital application number.");
}

$hospital_database = "hospital_" . $application_no;

$hospital_conn = db_connect($hospital_database);

if (!$hospital_conn) {
    die("Hospital Database Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($hospital_conn, "utf8mb4");


/*
|--------------------------------------------------------------------------
| Hospital ID
|--------------------------------------------------------------------------
| Get hospital_id from central hospital_registration table.
|--------------------------------------------------------------------------
*/

$hospital_id = 0;

$stmt = mysqli_prepare(
    $conn,
    "SELECT hospital_id
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

    mysqli_stmt_bind_result(
        $stmt,
        $hospital_id
    );

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);
}


/*
|--------------------------------------------------------------------------
| Total Doctors
|--------------------------------------------------------------------------
*/

$total_doctors = 0;

$result = mysqli_query(
    $hospital_conn,
    "SELECT COUNT(*) AS total FROM doctors"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $total_doctors = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| Total Staff
|--------------------------------------------------------------------------
*/

$total_staff = 0;

$result = mysqli_query(
    $hospital_conn,
    "SELECT COUNT(*) AS total FROM staff"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $total_staff = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| Today's Appointments
|--------------------------------------------------------------------------
*/

$today_appointments = 0;

$result = mysqli_query(
    $hospital_conn,
    "SELECT COUNT(*) AS total
     FROM appointments
     WHERE appointment_date = CURDATE()"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $today_appointments = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| Total Patients
|--------------------------------------------------------------------------
| Patients are stored centrally and connected to hospital through
| patient_hospital_mapping.
|--------------------------------------------------------------------------
*/

$total_patients = 0;

if ($hospital_id > 0) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS total
         FROM patient_hospital_mapping
         WHERE hospital_id = ?"
    );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $hospital_id
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_bind_result(
            $stmt,
            $total_patients
        );

        mysqli_stmt_fetch($stmt);

        mysqli_stmt_close($stmt);
    }
}


/*
|--------------------------------------------------------------------------
| Recent Appointments
|--------------------------------------------------------------------------
| Actual patient name comes from:
|
| appointments.mapping_id
|      ↓
| patient_hospital_mapping.mapping_id
|      ↓
| patient_hospital_mapping.account_id
|      ↓
| patient_accounts.account_id
|--------------------------------------------------------------------------
*/

$recent_appointments = [];

$query = "
    SELECT
        a.appointment_id,
        a.mapping_id,
        a.appointment_no,
        a.appointment_date,
        a.appointment_time,
        a.appointment_status,

        d.doctor_name,

        s.service_name,

        dep.department_name,

        CONCAT(
            pa.first_name,
            CASE
                WHEN pa.last_name IS NOT NULL
                     AND pa.last_name <> ''
                THEN CONCAT(' ', pa.last_name)
                ELSE ''
            END
        ) AS patient_name

    FROM appointments a

    INNER JOIN doctors d
        ON a.doctor_id = d.doctor_id

    INNER JOIN services s
        ON a.service_id = s.service_id

    INNER JOIN departments dep
        ON s.department_id = dep.department_id

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON a.mapping_id = phm.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON phm.account_id = pa.account_id

    ORDER BY
        a.appointment_date DESC,
        a.appointment_time DESC

    LIMIT 5
";

$result = mysqli_query(
    $hospital_conn,
    $query
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $recent_appointments[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Appointment Overview - Last 7 Days
|--------------------------------------------------------------------------
*/

$appointment_chart_labels = [];
$appointment_chart_data = [];

for ($i = 6; $i >= 0; $i--) {

    $date = date(
        'Y-m-d',
        strtotime("-$i days")
    );

    $appointment_chart_labels[] = date(
        'D',
        strtotime($date)
    );

    $appointment_chart_data[] = 0;

    $stmt = mysqli_prepare(
        $hospital_conn,
        "SELECT COUNT(*) AS total
         FROM appointments
         WHERE appointment_date = ?"
    );

    if ($stmt) {

        mysqli_stmt_bind_param(
            $stmt,
            "s",
            $date
        );

        mysqli_stmt_execute($stmt);

        mysqli_stmt_bind_result(
            $stmt,
            $appointment_total
        );

        if (mysqli_stmt_fetch($stmt)) {

            $appointment_chart_data[
                count($appointment_chart_data) - 1
            ] = (int)$appointment_total;
        }

        mysqli_stmt_close($stmt);
    }
}


/*
|--------------------------------------------------------------------------
| Patients by Department
|--------------------------------------------------------------------------
*/

$department_chart_labels = [];
$department_chart_data = [];

$query = "
    SELECT
        dep.department_name,
        COUNT(DISTINCT a.mapping_id) AS patient_count

    FROM appointments a

    INNER JOIN services s
        ON a.service_id = s.service_id

    INNER JOIN departments dep
        ON s.department_id = dep.department_id

    GROUP BY
        dep.department_id,
        dep.department_name

    ORDER BY
        patient_count DESC

    LIMIT 5
";

$result = mysqli_query(
    $hospital_conn,
    $query
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $department_chart_labels[] =
            $row['department_name'];

        $department_chart_data[] =
            (int)$row['patient_count'];
    }
}


/*
|--------------------------------------------------------------------------
| Appointment Status Counts
|--------------------------------------------------------------------------
*/

$scheduled_count = 0;
$checked_in_count = 0;
$in_progress_count = 0;
$completed_count = 0;
$cancelled_count = 0;
$no_show_count = 0;

$query = "
    SELECT
        appointment_status,
        COUNT(*) AS total

    FROM appointments

    GROUP BY appointment_status
";

$result = mysqli_query(
    $hospital_conn,
    $query
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        switch ($row['appointment_status']) {

            case 'Scheduled':
                $scheduled_count = (int)$row['total'];
                break;

            case 'Checked-In':
                $checked_in_count = (int)$row['total'];
                break;

            case 'In-Progress':
                $in_progress_count = (int)$row['total'];
                break;

            case 'Completed':
                $completed_count = (int)$row['total'];
                break;

            case 'Cancelled':
                $cancelled_count = (int)$row['total'];
                break;

            case 'No-Show':
                $no_show_count = (int)$row['total'];
                break;
        }
    }
}


/*
|--------------------------------------------------------------------------
| Hospital Activity
|--------------------------------------------------------------------------
*/

$activity_list = [];

$query = "
    SELECT
        'Doctor added' AS activity,
        created_at
    FROM doctors

    UNION ALL

    SELECT
        'Staff added' AS activity,
        created_at
    FROM staff

    UNION ALL

    SELECT
        'Service added' AS activity,
        created_at
    FROM services

    UNION ALL

    SELECT
        'Appointment booked' AS activity,
        created_at
    FROM appointments

    UNION ALL

    SELECT
        'Medical record created' AS activity,
        created_at
    FROM medical_records

    ORDER BY created_at DESC

    LIMIT 5
";

$result = mysqli_query(
    $hospital_conn,
    $query
);

if ($result) {

    while ($row = mysqli_fetch_assoc($result)) {

        $activity_list[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Time Ago Helper
|--------------------------------------------------------------------------
*/

function dashboardTimeAgo($datetime)
{
    $timestamp = strtotime($datetime);

    if (!$timestamp) {
        return '';
    }

    $difference = time() - $timestamp;

    if ($difference < 60) {
        return 'Just now';
    }

    if ($difference < 3600) {

        $minutes = floor($difference / 60);

        return $minutes .
            ' min ago';
    }

    if ($difference < 86400) {

        $hours = floor($difference / 3600);

        return $hours .
            ' hour' .
            ($hours > 1 ? 's' : '') .
            ' ago';
    }

    $days = floor($difference / 86400);

    return $days .
        ' day' .
        ($days > 1 ? 's' : '') .
        ' ago';
}


/*
|--------------------------------------------------------------------------
| Appointment Status Class
|--------------------------------------------------------------------------
*/

function appointmentStatusClass($status)
{
    switch ($status) {

        case 'Scheduled':
            return 'confirmed';

        case 'Checked-In':
            return 'confirmed';

        case 'In-Progress':
            return 'confirmed';

        case 'Completed':
            return 'completed';

        case 'Cancelled':
            return 'cancelled';

        case 'No-Show':
            return 'pending';

        default:
            return 'pending';
    }
}


/*
|--------------------------------------------------------------------------
| Patient Initials
|--------------------------------------------------------------------------
*/

function patientInitials($name)
{
    $name = trim($name);

    if ($name === '') {
        return 'PT';
    }

    $parts = preg_split(
        '/\s+/',
        $name
    );

    if (count($parts) >= 2) {

        return strtoupper(
            substr($parts[0], 0, 1) .
            substr($parts[1], 0, 1)
        );
    }

    return strtoupper(
        substr($name, 0, 2)
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Hospital Admin Dashboard</title>

    <link rel="stylesheet"
          href="css/admin_dashboard.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>


<body>


<div class="dashboard">


    <?php require 'sidebar.php'; ?>


    <main class="main-content">


        <section class="welcome">

            <h1>Dashboard</h1>

            <p>

                Welcome back,

                <b>

                    <?php

                    echo htmlspecialchars(
                        $_SESSION['admin_name'] ??
                        'Administrator'
                    );

                    ?>

                </b>

            </p>

        </section>


        <section class="stats">


            <div class="stat-card">

                <i class="fa-solid fa-user-doctor"></i>

                <div>

                    <p>Total Doctors</p>

                    <h2>

                        <?php

                        echo number_format(
                            $total_doctors
                        );

                        ?>

                    </h2>

                </div>

            </div>


            <div class="stat-card">

                <i class="fa-solid fa-users"></i>

                <div>

                    <p>Total Staff</p>

                    <h2>

                        <?php

                        echo number_format(
                            $total_staff
                        );

                        ?>

                    </h2>

                </div>

            </div>


            <div class="stat-card">

                <i class="fa-solid fa-calendar-check"></i>

                <div>

                    <p>Today's Appointments</p>

                    <h2>

                        <?php

                        echo number_format(
                            $today_appointments
                        );

                        ?>

                    </h2>

                </div>

            </div>


            <div class="stat-card">

                <i class="fa-solid fa-bed-pulse"></i>

                <div>

                    <p>Total Patients</p>

                    <h2>

                        <?php

                        echo number_format(
                            $total_patients
                        );

                        ?>

                    </h2>

                </div>

            </div>


        </section>


        <section class="analytics">


            <div class="chart-card appointments-chart">

                <div class="card-header">

                    <h3>Appointments Overview</h3>

                    <i class="fa-solid fa-ellipsis"></i>

                </div>

                <div class="chart-placeholder">

                    <canvas id="appointmentChart"></canvas>

                </div>

            </div>


            <div class="chart-card department-chart">

                <div class="card-header">

                    <h3>Patients by Department</h3>

                    <i class="fa-solid fa-ellipsis"></i>

                </div>

                <div class="department-content">

                    <div class="donut-container">

                        <canvas id="departmentChart"></canvas>

                    </div>

                </div>

            </div>


        </section>


        <section class="dashboard-bottom">


            <div class="bottom-card">


                <div class="card-header">

                    <h3>Recent Appointments</h3>

                    <i class="fa-solid fa-ellipsis"></i>

                </div>


                <div class="appointment-list">


                    <?php if (empty($recent_appointments)): ?>


                        <div class="appointment-item">

                            <div class="patient-info">

                                <h4>
                                    No appointments found
                                </h4>

                                <p>
                                    No appointment records available.
                                </p>

                            </div>

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $recent_appointments
                            as $appointment
                        ): ?>


                            <?php

                            $patient_name =
                                trim(
                                    $appointment['patient_name']
                                );

                            if (
                                $patient_name === ''
                            ) {
                                $patient_name =
                                    'Unknown Patient';
                            }

                            $initials =
                                patientInitials(
                                    $patient_name
                                );

                            $status_class =
                                appointmentStatusClass(
                                    $appointment[
                                        'appointment_status'
                                    ]
                                );

                            ?>


                            <div class="appointment-item">


                                <div class="patient-avatar">

                                    <?php

                                    echo htmlspecialchars(
                                        $initials
                                    );

                                    ?>

                                </div>


                                <div class="patient-info">

                                    <h4>

                                        <?php

                                        echo htmlspecialchars(
                                            $patient_name
                                        );

                                        ?>

                                    </h4>

                                    <p>

                                        <?php

                                        echo htmlspecialchars(
                                            $appointment[
                                                'department_name'
                                            ]
                                        );

                                        ?>

                                    </p>

                                </div>


                                <div class="appointment-time">

                                    <?php

                                    echo date(
                                        'h:i A',
                                        strtotime(
                                            $appointment[
                                                'appointment_time'
                                            ]
                                        )
                                    );

                                    ?>

                                </div>


                                <span
                                    class="status <?php echo $status_class; ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $appointment[
                                            'appointment_status'
                                        ]
                                    );

                                    ?>

                                </span>


                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>

            </div>


            <div class="bottom-card">


                <div class="card-header">

                    <h3>Hospital Activity</h3>

                    <i class="fa-solid fa-ellipsis"></i>

                </div>


                <div class="activity-list">


                    <?php if (empty($activity_list)): ?>


                        <div class="activity-item">

                            <div class="activity-dot"></div>

                            <div class="activity-info">

                                <p>
                                    No activity available
                                </p>

                                <span>
                                    Waiting for hospital activity
                                </span>

                            </div>

                        </div>


                    <?php else: ?>


                        <?php foreach (
                            $activity_list
                            as $activity
                        ): ?>


                            <div class="activity-item">

                                <div class="activity-dot"></div>

                                <div class="activity-info">

                                    <p>

                                        <?php

                                        echo htmlspecialchars(
                                            $activity[
                                                'activity'
                                            ]
                                        );

                                        ?>

                                    </p>

                                    <span>

                                        <?php

                                        echo htmlspecialchars(
                                            dashboardTimeAgo(
                                                $activity[
                                                    'created_at'
                                                ]
                                            )
                                        );

                                        ?>

                                    </span>

                                </div>

                            </div>


                        <?php endforeach; ?>


                    <?php endif; ?>


                </div>


            </div>


        </section>


    </main>


</div>


<script>

window.dashboardAppointmentLabels =
<?php

echo json_encode(
    $appointment_chart_labels,
    JSON_UNESCAPED_UNICODE
);

?>;


window.dashboardAppointmentData =
<?php

echo json_encode(
    $appointment_chart_data
);

?>;


window.dashboardDepartmentLabels =
<?php

echo json_encode(
    $department_chart_labels,
    JSON_UNESCAPED_UNICODE
);

?>;


window.dashboardDepartmentData =
<?php

echo json_encode(
    $department_chart_data
);

?>;


window.dashboardAppointmentStatus = {

    scheduled:
        <?php echo $scheduled_count; ?>,

    checkedIn:
        <?php echo $checked_in_count; ?>,

    inProgress:
        <?php echo $in_progress_count; ?>,

    completed:
        <?php echo $completed_count; ?>,

    cancelled:
        <?php echo $cancelled_count; ?>,

    noShow:
        <?php echo $no_show_count; ?>

};

</script>


<script src="js/admin_dashboard.js"></script>


</body>

</html>


<?php

mysqli_close($hospital_conn);

?>