<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| SELECT DATE
|--------------------------------------------------------------------------
| Appointment Booking - Step 5
|
| Previous step:
|
| select_doctor.php
|        ↓
| doctor_id
|
| The selected hospital, department and doctor are
| maintained inside appointment_booking session.
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
    "Select Appointment Date";


/*====================================================
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
)
{
    $_SESSION['error'] =
        "Invalid date selection request.";

    header(
        "Location: select_doctor.php"
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
    GET DOCTOR ID
====================================================*/

$doctor_id =
    filter_input(
        INPUT_POST,
        'doctor_id',
        FILTER_VALIDATE_INT
    );


if (
    $doctor_id === false
    ||
    $doctor_id === null
    ||
    $doctor_id <= 0
)
{
    $_SESSION['error'] =
        "Invalid doctor selection.";

    header(
        "Location: select_doctor.php"
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
    VERIFY SELECTED DOCTOR
====================================================*/

$query = "
    SELECT
        doctor_id,
        department_id,
        doctor_name,
        specialization,
        qualification,
        experience_years,
        consultation_fee,
        profile_photo,
        status
    FROM doctors
    WHERE doctor_id = ?
      AND department_id = ?
      AND status = 'Active'
    LIMIT 1
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
    "ii",
    $doctor_id,
    $department_id
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


$doctor =
    null;


if ($result)
{
    $doctor =
        mysqli_fetch_assoc(
            $result
        );

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt );


/*====================================================
    DOCTOR NOT FOUND
====================================================*/

if (!$doctor)
{
    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "Selected doctor is not available.";

    header(
        "Location: select_doctor.php"
    );

    exit;
}


/*====================================================
    SAVE DOCTOR IN BOOKING SESSION
====================================================*/

$_SESSION['appointment_booking'][
    'doctor_id'
] =
    (int)
    $doctor['doctor_id'];


$_SESSION['appointment_booking'][
    'doctor_name'
] =
    (string)
    $doctor['doctor_name'];


$_SESSION['appointment_booking'][
    'specialization'
] =
    (string)
    (
        $doctor['specialization']
        ?? ''
    );


$_SESSION['appointment_booking'][
    'consultation_fee'
] =
    (float)
    (
        $doctor['consultation_fee']
        ?? 0
    );


/*====================================================
    CLOSE HOSPITAL DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    HTML ESCAPE
====================================================*/

function selectDateEscape(
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
    MINIMUM DATE
====================================================*/

$minimum_date =
    date(
        'Y-m-d'
    );


/*====================================================
    FLASH ERROR
====================================================*/

$error =
    $_SESSION['error']
    ?? '';


unset(
    $_SESSION['error']
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
                    Select Appointment Date
                </h1>


                <p>

                    Choose a date for your consultation
                    with the selected doctor.

                </p>

            </div>

        </div>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= selectDateEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            APPOINTMENT SUMMARY
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Appointment Summary
                    </h2>

                    <p>
                        Review your selected hospital,
                        department and doctor.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <!-- Hospital -->

                <div class="information-row">

                    <span>
                        Hospital
                    </span>


                    <strong>

                        <?= selectDateEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <!-- Patient Code -->

                <div class="information-row">

                    <span>
                        Patient Code
                    </span>


                    <strong>

                        <?= selectDateEscape(
                            $hospital_patient_code
                        ); ?>

                    </strong>

                </div>


                <!-- Department -->

                <div class="information-row">

                    <span>
                        Department
                    </span>


                    <strong>

                        <?= selectDateEscape(
                            $department_name
                        ); ?>

                    </strong>

                </div>


                <!-- Doctor -->

                <div class="information-row">

                    <span>
                        Doctor
                    </span>


                    <strong>

                        <?= selectDateEscape(
                            (string)
                            $doctor['doctor_name']
                        ); ?>

                    </strong>

                </div>


                <!-- Specialization -->

                <?php if (
                    !empty(
                        $doctor['specialization']
                    )
                ): ?>

                    <div class="information-row">

                        <span>
                            Specialization
                        </span>


                        <strong>

                            <?= selectDateEscape(
                                (string)
                                $doctor[
                                    'specialization'
                                ]
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Consultation Fee -->

                <div class="information-row">

                    <span>
                        Consultation Fee
                    </span>


                    <strong>

                        ₹<?= number_format(
                            (float)
                            (
                                $doctor[
                                    'consultation_fee'
                                ]
                                ?? 0
                            ),
                            2
                        ); ?>

                    </strong>

                </div>


            </div>

        </div>


        <br>


        <!--================================================
            DATE SELECTION
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Choose Date
                    </h2>


                    <p>
                        Select the date on which you want
                        to visit the doctor.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="select_time.php"
            >


                <div class="profile-form-grid">


                    <div class="profile-field">


                        <label
                            for="appointment_date"
                        >

                            Appointment Date

                        </label>


                        <input
                            type="date"
                            id="appointment_date"
                            name="appointment_date"
                            min="<?= $minimum_date; ?>"
                            required
                        >


                        <small>

                            Please select today or a
                            future date.

                        </small>


                    </div>


                </div>


                <!--================================================
                    CONTINUE
                =================================================-->

                <div class="profile-form-actions">


                    <button
                        type="submit"
                        class="profile-save-button"
                    >

                        Continue

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