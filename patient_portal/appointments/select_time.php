<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| SELECT TIME
|--------------------------------------------------------------------------
| Appointment Booking - Step 6
|--------------------------------------------------------------------------
|
| Patient date select karta hai.
|
| Hospital doctor ki availability provide karta hai:
|
|   start_time
|   end_time
|
| System automatically appointment time assign karta hai.
|
| Patient interval:
|
|   10 minutes
|
| Example:
|
|   Patient 1 → 09:30 AM
|   Patient 2 → 09:40 AM
|   Patient 3 → 09:50 AM
|   Patient 4 → 10:00 AM
|
| Patient manually time select nahi karta.
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

$page_title =
    "Appointment Time";


/*====================================================
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
)
{
    $_SESSION['error'] =
        "Invalid appointment request.";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    CHECK APPOINTMENT SESSION
====================================================*/

if (
    !isset(
        $_SESSION['appointment_booking']
    )
    ||
    !is_array(
        $_SESSION['appointment_booking']
    )
)
{
    $_SESSION['error'] =
        "Please start the appointment booking process again.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET BOOKING DATA
====================================================*/

$booking =
    $_SESSION['appointment_booking'];


$account_id =
    (int) (
        $booking['account_id']
        ?? 0
    );


$mapping_id =
    (int) (
        $booking['mapping_id']
        ?? 0
    );


$hospital_id =
    (int) (
        $booking['hospital_id']
        ?? 0
    );


$hospital_name =
    (string) (
        $booking['hospital_name']
        ?? ''
    );


$hospital_patient_code =
    (string) (
        $booking['hospital_patient_code']
        ?? ''
    );


$department_id =
    (int) (
        $booking['department_id']
        ?? 0
    );


$department_name =
    (string) (
        $booking['department_name']
        ?? ''
    );


$doctor_id =
    (int) (
        $booking['doctor_id']
        ?? 0
    );


$doctor_name =
    (string) (
        $booking['doctor_name']
        ?? ''
    );


$specialization =
    (string) (
        $booking['specialization']
        ?? ''
    );


$hospital_database =
    trim(
        (string) (
            $booking['database_name']
            ?? ''
        )
    );


/*====================================================
    VALIDATE BOOKING SESSION
====================================================*/

if (
    $account_id <= 0
    ||
    $mapping_id <= 0
    ||
    $hospital_id <= 0
    ||
    $department_id <= 0
    ||
    $doctor_id <= 0
    ||
    $hospital_database === ''
)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid appointment booking session.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET SELECTED DATE
====================================================*/

$appointment_date =
    trim(
        (string) (
            $_POST['appointment_date']
            ?? ''
        )
    );


/*====================================================
    VALIDATE DATE FORMAT
====================================================*/

$date_object =
    DateTime::createFromFormat(
        'Y-m-d',
        $appointment_date
    );


if (
    !$date_object
    ||
    $date_object->format('Y-m-d')
        !== $appointment_date
)
{
    $_SESSION['error'] =
        "Invalid appointment date.";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    PREVENT PAST DATE
====================================================*/

$today =
    date('Y-m-d');


if (
    $appointment_date < $today
)
{
    $_SESSION['error'] =
        "Past dates cannot be selected.";

    header(
        "Location: select_date.php"
    );

    exit;
}


/*====================================================
    VALIDATE DATABASE NAME
====================================================*/

if (
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid hospital database configuration.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn)
{
    die(
        "Hospital Database Connection Failed: " .
        mysqli_connect_error()
    );
}


mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*====================================================
    GET DAY OF WEEK
====================================================*/

$day_of_week =
    $date_object->format('l');


/*====================================================
    GET DOCTOR AVAILABILITY
====================================================*/

$query = "
    SELECT
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
    WHERE doctor_id = ?
      AND day_of_week = ?
      AND status = 'Active'
    ORDER BY start_time ASC
";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "is",
    $doctor_id,
    $day_of_week
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$availability = [];


if ($result)
{
    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    )
    {
        $availability[] =
            $row;
    }

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    NO HOSPITAL AVAILABILITY
====================================================*/

if (
    count($availability) === 0
)
{
    mysqli_close(
        $hospital_conn
    );


    function appointmentTimeErrorEscape(
        string $value
    ): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }


    require_once __DIR__ .
        '/../includes/header.php';

    ?>


    <div class="patient-dashboard">


        <?php

        require_once __DIR__ .
            '/../includes/sidebar.php';

        ?>


        <section class="patient-dashboard-content">


            <!--========================================
                PAGE HEADER
            ========================================-->

            <div class="dashboard-welcome">

                <div>

                    <p class="dashboard-welcome-label">
                        Patient Portal
                    </p>


                    <h1>
                        Appointment Not Available
                    </h1>


                    <p>
                        We could not find an appointment
                        schedule for the selected doctor
                        on the selected day.
                    </p>

                </div>

            </div>


            <!--========================================
                AVAILABILITY MESSAGE
            ========================================-->

            <div class="dashboard-panel">


                <div class="dashboard-panel-header">

                    <div>

                        <h2>
                            Hospital Availability
                        </h2>


                        <p>
                            The hospital has not configured
                            availability for this doctor on
                            the selected day.
                        </p>

                    </div>

                </div>


                <div class="patient-information">


                    <div class="information-row">

                        <span>
                            Hospital
                        </span>


                        <strong>

                            <?= appointmentTimeErrorEscape(
                                $hospital_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Department
                        </span>


                        <strong>

                            <?= appointmentTimeErrorEscape(
                                $department_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Doctor
                        </span>


                        <strong>

                            <?= appointmentTimeErrorEscape(
                                $doctor_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Selected Day
                        </span>


                        <strong>

                            <?= appointmentTimeErrorEscape(
                                $day_of_week
                            ); ?>

                        </strong>

                    </div>


                </div>


                <br>


                <div
                    class="profile-message profile-error"
                >

                    Appointment availability for this
                    doctor has not been configured by the
                    hospital for the selected day.

                    <br><br>

                    Please select another date or contact
                    the hospital for more information.

                </div>


                <div
                    class="profile-form-actions"
                >


                    <a
                        href="select_date.php"
                        class="profile-save-button"
                    >

                        Choose Another Date

                    </a>


                    <a
                        href="select_doctor.php"
                        class="profile-save-button"
                    >

                        Choose Another Doctor

                    </a>


                </div>


            </div>


        </section>


    </div>


    <?php

    require_once __DIR__ .
        '/../includes/footer.php';

    exit;
}


/*====================================================
    GET EXISTING APPOINTMENTS
====================================================*/

$query = "
    SELECT
        appointment_time
    FROM appointments
    WHERE doctor_id = ?
      AND appointment_date = ?
      AND appointment_status IN
      (
          'Scheduled',
          'Checked-In',
          'In-Progress'
      )
    ORDER BY appointment_time ASC
";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "is",
    $doctor_id,
    $appointment_date
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$booked_times = [];


if ($result)
{
    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    )
    {
        $booked_times[] =
            substr(
                (string)
                $row['appointment_time'],
                0,
                5
            );
    }

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    AUTOMATIC PATIENT INTERVAL
====================================================*/

/*
    Our project requirement:

    Every patient gets a 10-minute interval.

    Example:

    09:30
    09:40
    09:50
    10:00
*/

$patient_gap_minutes =
    10;


$next_slot =
    null;


$selected_mode =
    'In-Person';


/*====================================================
    FIND NEXT AVAILABLE SLOT
====================================================*/

foreach (
    $availability
    as $schedule
)
{

    /*===============================================
        START TIME
    ===============================================*/

    $start =
        DateTime::createFromFormat(
            'Y-m-d H:i',
            $appointment_date .
            ' ' .
            substr(
                (string)
                $schedule['start_time'],
                0,
                5
            )
        );


    /*===============================================
        END TIME
    ===============================================*/

    $end =
        DateTime::createFromFormat(
            'Y-m-d H:i',
            $appointment_date .
            ' ' .
            substr(
                (string)
                $schedule['end_time'],
                0,
                5
            )
        );


    if (
        !$start
        ||
        !$end
        ||
        $start >= $end
    )
    {
        continue;
    }


    /*===============================================
        MAX PATIENTS
    ===============================================*/

    $max_patients =
        (int) (
            $schedule['max_patients']
            ?? 0
        );


    /*
        If max_patients is greater than zero,
        enforce the limit.
    */

    if (
        $max_patients > 0
        &&
        count($booked_times)
            >= $max_patients
    )
    {
        continue;
    }


    /*===============================================
        CONSULTATION MODE
    ===============================================*/

    $selected_mode =
        (string) (
            $schedule['consultation_mode']
            ?? 'In-Person'
        );


    /*===============================================
        START CANDIDATE
    ===============================================*/

    $candidate =
        clone $start;


    /*===============================================
        GENERATE 10-MINUTE SLOTS
    ===============================================*/

    while (
        $candidate < $end
    )
    {

        /*-------------------------------------------
            TODAY PAST TIME CHECK
        -------------------------------------------*/

        if (
            $appointment_date === $today
            &&
            $candidate->format('H:i')
                <= date('H:i')
        )
        {
            $candidate->modify(
                "+{$patient_gap_minutes} minutes"
            );

            continue;
        }


        /*-------------------------------------------
            CANDIDATE TIME
        -------------------------------------------*/

        $candidate_time =
            $candidate->format(
                'H:i'
            );


        /*-------------------------------------------
            CHECK BOOKED TIME
        -------------------------------------------*/

        if (
            !in_array(
                $candidate_time,
                $booked_times,
                true
            )
        )
        {

            $next_slot =
                $candidate->format(
                    'H:i:s'
                );

            break 2;
        }


        /*-------------------------------------------
            NEXT 10-MINUTE SLOT
        -------------------------------------------*/

        $candidate->modify(
            "+{$patient_gap_minutes} minutes"
        );
    }
}


/*====================================================
    NO SLOT AVAILABLE
====================================================*/

if (
    $next_slot === null
)
{
    mysqli_close(
        $hospital_conn
    );


    function appointmentSlotErrorEscape(
        string $value
    ): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }


    require_once __DIR__ .
        '/../includes/header.php';

    ?>


    <div class="patient-dashboard">


        <?php

        require_once __DIR__ .
            '/../includes/sidebar.php';

        ?>


        <section class="patient-dashboard-content">


            <div class="dashboard-welcome">

                <div>

                    <p class="dashboard-welcome-label">
                        Patient Portal
                    </p>


                    <h1>
                        No Appointment Slot Available
                    </h1>


                    <p>
                        All available appointment slots
                        are currently occupied.
                    </p>

                </div>

            </div>


            <div class="dashboard-panel">


                <div class="dashboard-panel-header">

                    <div>

                        <h2>
                            Appointment Queue Full
                        </h2>


                        <p>
                            There is no free appointment
                            slot remaining for the selected
                            date.
                        </p>

                    </div>

                </div>


                <div class="patient-information">


                    <div class="information-row">

                        <span>
                            Hospital
                        </span>


                        <strong>

                            <?= appointmentSlotErrorEscape(
                                $hospital_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Doctor
                        </span>


                        <strong>

                            <?= appointmentSlotErrorEscape(
                                $doctor_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Date
                        </span>


                        <strong>

                            <?= appointmentSlotErrorEscape(
                                $appointment_date
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Patient Interval
                        </span>


                        <strong>
                            10 Minutes
                        </strong>

                    </div>


                </div>


                <br>


                <div
                    class="profile-message profile-error"
                >

                    No appointment slot is currently
                    available for this doctor on the
                    selected date.

                    <br><br>

                    Please choose another date.

                </div>


                <div
                    class="profile-form-actions"
                >

                    <a
                        href="select_date.php"
                        class="profile-save-button"
                    >

                        Choose Another Date

                    </a>


                    <a
                        href="select_doctor.php"
                        class="profile-save-button"
                    >

                        Choose Another Doctor

                    </a>

                </div>


            </div>


        </section>


    </div>


    <?php

    require_once __DIR__ .
        '/../includes/footer.php';

    exit;
}


/*====================================================
    SAVE DATE AND TIME IN SESSION
====================================================*/

$_SESSION['appointment_booking'][
    'appointment_date'
] =
    $appointment_date;


$_SESSION['appointment_booking'][
    'appointment_time'
] =
    $next_slot;


$_SESSION['appointment_booking'][
    'consultation_mode'
] =
    $selected_mode;


/*====================================================
    CLOSE DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    HTML ESCAPE
====================================================*/

function appointmentTimeEscape(
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
    DISPLAY DATE
====================================================*/

$display_date =
    date(
        'd M Y',
        strtotime(
            $appointment_date
        )
    );


/*====================================================
    DISPLAY TIME
====================================================*/

$display_time =
    date(
        'h:i A',
        strtotime(
            $next_slot
        )
    );


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
                    Appointment Time
                </h1>


                <p>
                    Your appointment time has been
                    automatically assigned.
                </p>

            </div>

        </div>


        <!--================================================
            APPOINTMENT SUMMARY
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Your Appointment
                    </h2>


                    <p>
                        The next available appointment
                        time has been automatically assigned.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <div class="information-row">

                    <span>
                        Hospital
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Patient Code
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $hospital_patient_code
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Department
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $department_name
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Doctor
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $doctor_name
                        ); ?>

                    </strong>

                </div>


                <?php if (
                    $specialization !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Specialization
                        </span>


                        <strong>

                            <?= appointmentTimeEscape(
                                $specialization
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <div class="information-row">

                    <span>
                        Appointment Date
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $display_date
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Appointment Time
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Consultation Mode
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $selected_mode
                        ); ?>

                    </strong>

                </div>


            </div>

        </div>


        <br>


        <!--================================================
            QUEUE INFORMATION
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Automatic Queue
                    </h2>


                    <p>
                        Appointment time is automatically
                        assigned according to the hospital
                        schedule.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <div class="information-row">

                    <span>
                        Queue System
                    </span>


                    <strong>
                        Automatic
                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Patient Interval
                    </span>


                    <strong>
                        10 Minutes
                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Assigned Time
                    </span>


                    <strong>

                        <?= appointmentTimeEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


            </div>


            <!--================================================
                CONTINUE
            =================================================-->

            <form
                method="POST"
                action="confirm_appointment.php"
            >

                <div class="profile-form-actions">

                    <button
                        type="submit"
                        class="profile-save-button"
                    >

                        Continue to Confirmation

                    </button>

                </div>

            </form>


        </div>


    </section>


</div>


<?php

require_once __DIR__ .
    '/../includes/footer.php';

?>