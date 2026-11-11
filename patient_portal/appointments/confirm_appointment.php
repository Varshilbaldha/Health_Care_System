<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CONFIRM APPOINTMENT
|--------------------------------------------------------------------------
| Appointment Booking - Step 7
|
| Previous step:
|
| select_time.php
|        ↓
| appointment date
| appointment time
|
| This page shows the final appointment summary.
|
| Actual database INSERT will be performed by:
|
| confirm_appointment_process.php
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
    "Confirm Appointment";


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


$hospital_database =
    trim(
        (string) (
            $booking['database_name']
            ?? ''
        )
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


$consultation_fee =
    (float) (
        $booking['consultation_fee']
        ?? 0
    );


$appointment_date =
    (string) (
        $booking['appointment_date']
        ?? ''
    );


$appointment_time =
    (string) (
        $booking['appointment_time']
        ?? ''
    );


$consultation_mode =
    (string) (
        $booking['consultation_mode']
        ?? 'In-Person'
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
    ||
    $appointment_date === ''
    ||
    $appointment_time === ''
)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Your appointment session is incomplete.";

    header(
        "Location: book.php"
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
    FORMAT DATE
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
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid appointment date.";

    header(
        "Location: select_date.php"
    );

    exit;
}


$display_date =
    $date_object->format(
        'd M Y'
    );


$day_of_week =
    $date_object->format(
        'l'
    );


/*====================================================
    FORMAT TIME
====================================================*/

$time_object =
    DateTime::createFromFormat(
        'H:i:s',
        $appointment_time
    );


if (!$time_object)
{
    $time_object =
        DateTime::createFromFormat(
            'H:i',
            $appointment_time
        );
}


if (!$time_object)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid appointment time.";

    header(
        "Location: select_date.php"
    );

    exit;
}


$display_time =
    $time_object->format(
        'h:i A'
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
    HTML ESCAPE
====================================================*/

function confirmAppointmentEscape(
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
                    Confirm Appointment
                </h1>


                <p>
                    Please review your appointment details
                    before confirming.
                </p>

            </div>

        </div>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= confirmAppointmentEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            APPOINTMENT DETAILS
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Appointment Details
                    </h2>


                    <p>
                        Please verify all information carefully.
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

                        <?= confirmAppointmentEscape(
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

                        <?= confirmAppointmentEscape(
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

                        <?= confirmAppointmentEscape(
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

                        <?= confirmAppointmentEscape(
                            $doctor_name
                        ); ?>

                    </strong>

                </div>


                <!-- Specialization -->

                <?php if (
                    $specialization !== ''
                ): ?>

                    <div class="information-row">

                        <span>
                            Specialization
                        </span>


                        <strong>

                            <?= confirmAppointmentEscape(
                                $specialization
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Date -->

                <div class="information-row">

                    <span>
                        Appointment Date
                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $display_date
                        ); ?>

                        (
                        <?= confirmAppointmentEscape(
                            $day_of_week
                        ); ?>
                        )

                    </strong>

                </div>


                <!-- Time -->

                <div class="information-row">

                    <span>
                        Appointment Time
                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


                <!-- Consultation Mode -->

                <div class="information-row">

                    <span>
                        Consultation Mode
                    </span>


                    <strong>

                        <?= confirmAppointmentEscape(
                            $consultation_mode
                        ); ?>

                    </strong>

                </div>


                <!-- Fee -->

                <div class="information-row">

                    <span>
                        Consultation Fee
                    </span>


                    <strong>

                        ₹<?= number_format(
                            $consultation_fee,
                            2
                        ); ?>

                    </strong>

                </div>


            </div>

        </div>


        <br>


        <!--================================================
            PATIENT INPUT
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Consultation Details
                    </h2>


                    <p>
                        Tell the doctor briefly about your problem.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="confirm_appointment_process.php"
            >


                <!--========================================
                    SYMPTOMS
                =========================================-->

                <div class="profile-field profile-field-full">

                    <label for="symptoms">

                        Symptoms / Reason for Visit

                    </label>


                    <textarea
                        id="symptoms"
                        name="symptoms"
                        rows="5"
                        maxlength="2000"
                        placeholder="Describe your symptoms or reason for consultation..."
                    ></textarea>


                    <small>

                        Maximum 2000 characters.

                    </small>

                </div>


                <br>


                <!--========================================
                    NOTES
                =========================================-->

                <div class="profile-field profile-field-full">

                    <label for="notes">

                        Additional Notes

                    </label>


                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        maxlength="2000"
                        placeholder="Any additional information for the doctor..."
                    ></textarea>


                    <small>

                        Maximum 2000 characters.

                    </small>

                </div>


                <br>


                <!--========================================
                    CONFIRMATION WARNING
                =========================================-->

                <div class="profile-message">

                    Your appointment time is assigned
                    automatically according to the hospital's
                    doctor availability and the existing
                    appointment queue.

                    <br><br>

                    Please verify the date and time before
                    confirming your appointment.

                </div>


                <!--========================================
                    CONFIRM BUTTON
                =========================================-->

                <div class="profile-form-actions">


                    <a
                        href="select_date.php"
                        class="profile-save-button"
                    >
                        Change Date
                    </a>


                    <button
                        type="submit"
                        class="profile-save-button"
                    >

                        Confirm Appointment

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