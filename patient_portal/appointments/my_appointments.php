<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';


/*
|--------------------------------------------------------------------------
| MY APPOINTMENTS
|--------------------------------------------------------------------------
| Shows appointments of the logged-in patient from ALL hospitals
| where the patient is actively registered.
|
| Central DB:
|   patient_hospital_mapping
|   hospital_registration
|
| Hospital DB:
|   appointments
|   doctors
|   departments
|   services
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../includes/auth_check.php';


/*====================================================
    PAGE TITLE
====================================================*/

$page_title = "My Appointments";


/*====================================================
    PATIENT ACCOUNT
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
    FLASH MESSAGES
====================================================*/

$error =
    $_SESSION['error']
    ?? '';

$success =
    $_SESSION['success']
    ?? '';

unset($_SESSION['error']);
unset($_SESSION['success']);


/*====================================================
    GET REGISTERED HOSPITALS
====================================================*/

$query = "
    SELECT
        phm.mapping_id,
        phm.hospital_id,
        phm.hospital_patient_code,
        phm.patient_status,

        hr.hospital_name,
        hr.database_name,
        hr.city,
        hr.state

    FROM patient_hospital_mapping AS phm

    INNER JOIN hospital_registration AS hr
        ON hr.hospital_id = phm.hospital_id

    WHERE phm.account_id = ?
      AND phm.patient_status = 'Active'

    ORDER BY hr.hospital_name ASC
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
    $account_id
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


$registered_hospitals = [];


if ($result)
{
    while (
        $row =
        mysqli_fetch_assoc($result)
    )
    {
        $registered_hospitals[] =
            $row;
    }

    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    LOAD APPOINTMENTS FROM ALL HOSPITAL DATABASES
====================================================*/

$appointments = [];


foreach (
    $registered_hospitals
    as $hospital
)
{

    $mapping_id =
        (int)
        $hospital['mapping_id'];


    $hospital_id =
        (int)
        $hospital['hospital_id'];


    $hospital_database =
        trim(
            (string)
            (
                $hospital['database_name']
                ?? ''
            )
        );


    /*-----------------------------------------------
        Validate database name
    -----------------------------------------------*/

    if (
        $hospital_database === ''
        ||
        !preg_match(
            '/^[A-Za-z0-9_]+$/',
            $hospital_database
        )
    )
    {
        continue;
    }


    /*-----------------------------------------------
        Connect to hospital database
    -----------------------------------------------*/

    $hospital_conn =
        db_connect($hospital_database);


    if (!$hospital_conn)
    {
        continue;
    }


    mysqli_set_charset(
        $hospital_conn,
        "utf8mb4"
    );


    /*-----------------------------------------------
        Get appointments
    -----------------------------------------------*/

    $query = "
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
            a.symptoms,
            a.notes,
            a.created_at,

            d.doctor_name,
            d.specialization,

            dep.department_name,

            s.service_name,
            s.service_code,

            COALESCE(
                ds.consultation_fee,
                s.service_fee,
                0
            ) AS consultation_fee

        FROM appointments AS a

        LEFT JOIN doctors AS d
            ON d.doctor_id = a.doctor_id

        LEFT JOIN departments AS dep
            ON dep.department_id = d.department_id

        LEFT JOIN services AS s
            ON s.service_id = a.service_id

        LEFT JOIN doctor_services AS ds
            ON ds.doctor_id = a.doctor_id
            AND ds.service_id = a.service_id
            AND ds.status = 'Active'

        WHERE a.mapping_id = ?

        ORDER BY
            a.appointment_date DESC,
            a.appointment_time DESC
    ";


    $stmt =
        mysqli_prepare(
            $hospital_conn,
            $query
        );


    if (!$stmt)
    {
        mysqli_close($hospital_conn);

        continue;
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $mapping_id
    );


    if (!mysqli_stmt_execute($stmt))
    {
        mysqli_stmt_close($stmt);

        mysqli_close($hospital_conn);

        continue;
    }


    $result =
        mysqli_stmt_get_result($stmt);


    if ($result)
    {
        while (
            $row =
            mysqli_fetch_assoc($result)
        )
        {

            /*
                Add hospital information because
                each hospital has a separate database.
            */

            $row['hospital_id'] =
                $hospital_id;


            $row['hospital_name'] =
                (string)
                $hospital['hospital_name'];


            $row['hospital_patient_code'] =
                (string)
                (
                    $hospital[
                        'hospital_patient_code'
                    ]
                    ?? ''
                );


            $row['hospital_city'] =
                (string)
                (
                    $hospital['city']
                    ?? ''
                );


            $row['hospital_state'] =
                (string)
                (
                    $hospital['state']
                    ?? ''
                );


            $appointments[] =
                $row;
        }


        mysqli_free_result($result);
    }


    mysqli_stmt_close($stmt);


    mysqli_close($hospital_conn);
}


/*====================================================
    SORT ALL APPOINTMENTS
====================================================*/

usort(
    $appointments,
    function (
        array $a,
        array $b
    ): int
    {

        $date_time_a =
            (
                (string)
                $a['appointment_date']
            )
            . ' '
            .
            (
                (string)
                $a['appointment_time']
            );


        $date_time_b =
            (
                (string)
                $b['appointment_date']
            )
            . ' '
            .
            (
                (string)
                $b['appointment_time']
            );


        return
            strcmp(
                $date_time_b,
                $date_time_a
            );
    }
);


/*====================================================
    HTML ESCAPE
====================================================*/

function myAppointmentsEscape(
    string $value
): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


/*====================================================
    HEADER
====================================================*/

require_once __DIR__ .
    '/../includes/header.php';

?>


<div class="patient-dashboard">


    <!--================================================
        SIDEBAR
    =================================================-->

    <?php

    require_once __DIR__ .
        '/../includes/sidebar.php';

    ?>


    <!--================================================
        MAIN CONTENT
    =================================================-->

    <section class="patient-dashboard-content">


        <!--================================================
            PAGE HEADER
        =================================================-->

        <div class="dashboard-welcome">

            <div>

                <p class="dashboard-welcome-label">
                    Patient Portal
                </p>


                <h1>
                    My Appointments
                </h1>


                <p>
                    View and manage your appointments
                    from all registered hospitals.
                </p>

            </div>

        </div>


        <!--================================================
            SUCCESS
        =================================================-->

        <?php if ($success !== ''): ?>

            <div class="profile-message profile-success">

                <?= myAppointmentsEscape(
                    $success
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= myAppointmentsEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            APPOINTMENT COUNT
        =================================================-->

        <div class="dashboard-panel">

            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Appointment History
                    </h2>

                    <p>

                        Total Appointments:

                        <strong>
                            <?= count($appointments); ?>
                        </strong>

                    </p>

                </div>

            </div>


            <?php if (
                count($appointments) === 0
            ): ?>


                <!--========================================
                    NO APPOINTMENTS
                =========================================-->

                <div class="profile-message">

                    You do not have any appointments yet.

                    <br><br>

                    <a
                        href="book.php"
                        class="profile-save-button"
                    >
                        Book Appointment
                    </a>

                </div>


            <?php else: ?>


                <!--========================================
                    APPOINTMENTS
                =========================================-->

                <div class="profile-form-grid">


                    <?php foreach (
                        $appointments
                        as $appointment
                    ): ?>


                        <div
                            class="hospital-selection-card"
                        >


                            <div
                                class="hospital-selection-content"
                            >


                                <!-- Hospital -->

                                <h3>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        $appointment[
                                            'hospital_name'
                                        ]
                                    ); ?>

                                </h3>


                                <!-- Appointment Number -->

                                <p>

                                    <strong>
                                        Appointment No:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        $appointment[
                                            'appointment_no'
                                        ]
                                    ); ?>

                                </p>


                                <!-- Doctor -->

                                <p>

                                    <strong>
                                        Doctor:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        (
                                            $appointment[
                                                'doctor_name'
                                            ]
                                            ?? 'N/A'
                                        )
                                    ); ?>

                                </p>


                                <!-- Department -->

                                <p>

                                    <strong>
                                        Department:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        (
                                            $appointment[
                                                'department_name'
                                            ]
                                            ?? 'N/A'
                                        )
                                    ); ?>

                                </p>


                                <!-- Service -->

                                <p>

                                    <strong>
                                        Service:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        (
                                            $appointment[
                                                'service_name'
                                            ]
                                            ?? 'N/A'
                                        )
                                    ); ?>

                                </p>


                                <!-- Date -->

                                <p>

                                    <strong>
                                        Date:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        date(
                                            'd M Y',
                                            strtotime(
                                                (string)
                                                $appointment[
                                                    'appointment_date'
                                                ]
                                            )
                                        )
                                    ); ?>

                                </p>


                                <!-- Time -->

                                <p>

                                    <strong>
                                        Time:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        date(
                                            'h:i A',
                                            strtotime(
                                                (string)
                                                $appointment[
                                                    'appointment_time'
                                                ]
                                            )
                                        )
                                    ); ?>

                                </p>


                                <!-- Token -->

                                <p>

                                    <strong>
                                        Token:
                                    </strong>

                                    #<?= (int)
                                        (
                                            $appointment[
                                                'token_number'
                                            ]
                                            ?? 0
                                        ); ?>

                                </p>


                                <!-- Consultation Mode -->

                                <p>

                                    <strong>
                                        Mode:
                                    </strong>

                                    <?= myAppointmentsEscape(
                                        (string)
                                        (
                                            $appointment[
                                                'consultation_mode'
                                            ]
                                            ?? 'In-Person'
                                        )
                                    ); ?>

                                </p>


                                <!-- Fee -->

                                <p>

                                    <strong>
                                        Fee:
                                    </strong>

                                    ₹<?= number_format(
                                        (float)
                                        (
                                            $appointment[
                                                'consultation_fee'
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    ); ?>

                                </p>


                                <!-- Status -->

                                <p>

                                    <strong>
                                        Status:
                                    </strong>


                                    <?php
                                    $status =
                                        (string)
                                        (
                                            $appointment[
                                                'appointment_status'
                                            ]
                                            ?? ''
                                        );
                                    ?>


                                    <?php if (
                                        $status === 'Scheduled'
                                    ): ?>

                                        <span
                                            class="status-active"
                                        >
                                            Scheduled
                                        </span>

                                    <?php elseif (
                                        $status === 'Cancelled'
                                    ): ?>

                                        <span
                                            class="status-pending"
                                        >
                                            Cancelled
                                        </span>

                                    <?php elseif (
                                        $status === 'Completed'
                                    ): ?>

                                        <span
                                            class="status-active"
                                        >
                                            Completed
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="status-pending"
                                        >

                                            <?= myAppointmentsEscape(
                                                $status
                                            ); ?>

                                        </span>

                                    <?php endif; ?>


                                </p>


                                <!--================================
                                    CANCEL
                                =================================-->

                                <?php if (
                                    $status === 'Scheduled'
                                ): ?>


                                    <hr>


                                    <form
                                        method="POST"
                                        action="cancel_appointment.php"
                                    >


                                        <input
                                            type="hidden"
                                            name="appointment_id"
                                            value="<?= (int)
                                                $appointment[
                                                    'appointment_id'
                                                ]; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="hospital_id"
                                            value="<?= (int)
                                                $appointment[
                                                    'hospital_id'
                                                ]; ?>"
                                        >


                                        <div
                                            class="profile-field"
                                        >

                                            <label
                                                for="reason_<?= (int)
                                                    $appointment[
                                                        'appointment_id'
                                                    ]; ?>"
                                            >

                                                Cancellation Reason

                                            </label>


                                            <textarea
                                                id="reason_<?= (int)
                                                    $appointment[
                                                        'appointment_id'
                                                    ]; ?>"
                                                name="cancellation_reason"
                                                rows="3"
                                                maxlength="500"
                                                required
                                                placeholder="Enter reason for cancellation..."
                                            ></textarea>

                                        </div>


                                        <br>


                                        <button
                                            type="submit"
                                            class="profile-save-button"
                                            onclick="return confirm('Are you sure you want to cancel this appointment?');"
                                        >

                                            Cancel Appointment

                                        </button>


                                    </form>


                                <?php endif; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </div>


    </section>


</div>


<?php

require_once __DIR__ .
    '/../includes/footer.php';

?>