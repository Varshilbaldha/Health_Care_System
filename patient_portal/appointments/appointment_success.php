<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| APPOINTMENT SUCCESS
|--------------------------------------------------------------------------
| Appointment Booking - Final Success Page
|
| Data comes from:
|
| confirm_appointment_process.php
|        ↓
| $_SESSION['completed_appointment']
|
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
    "Appointment Confirmed";


/*====================================================
    CHECK COMPLETED APPOINTMENT
====================================================*/

if (
    !isset(
        $_SESSION['completed_appointment']
    )
    ||
    !is_array(
        $_SESSION['completed_appointment']
    )
)
{
    $_SESSION['error'] =
        "No completed appointment was found.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET APPOINTMENT DATA
====================================================*/

$appointment =
    $_SESSION[
        'completed_appointment'
    ];


/*====================================================
    GET VALUES
====================================================*/

$appointment_id =
    (int) (
        $appointment[
            'appointment_id'
        ]
        ?? 0
    );


$appointment_no =
    (string) (
        $appointment[
            'appointment_no'
        ]
        ?? ''
    );


$token_number =
    (int) (
        $appointment[
            'token_number'
        ]
        ?? 0
    );


$hospital_name =
    (string) (
        $appointment[
            'hospital_name'
        ]
        ?? ''
    );


$hospital_patient_code =
    (string) (
        $appointment[
            'hospital_patient_code'
        ]
        ?? ''
    );


$department_name =
    (string) (
        $appointment[
            'department_name'
        ]
        ?? ''
    );


$doctor_name =
    (string) (
        $appointment[
            'doctor_name'
        ]
        ?? ''
    );


$service_name =
    (string) (
        $appointment[
            'service_name'
        ]
        ?? ''
    );


$appointment_date =
    (string) (
        $appointment[
            'appointment_date'
        ]
        ?? ''
    );


$appointment_time =
    (string) (
        $appointment[
            'appointment_time'
        ]
        ?? ''
    );


$appointment_type =
    (string) (
        $appointment[
            'appointment_type'
        ]
        ?? 'Online'
    );


$consultation_mode =
    (string) (
        $appointment[
            'consultation_mode'
        ]
        ?? 'In-Person'
    );


$consultation_fee =
    (float) (
        $appointment[
            'consultation_fee'
        ]
        ?? 0
    );


/*====================================================
    VALIDATE SUCCESS DATA
====================================================*/

if (
    $appointment_id <= 0
    ||
    $appointment_no === ''
    ||
    $hospital_name === ''
    ||
    $doctor_name === ''
    ||
    $appointment_date === ''
    ||
    $appointment_time === ''
)
{
    unset(
        $_SESSION[
            'completed_appointment'
        ]
    );

    $_SESSION['error'] =
        "Appointment confirmation information is incomplete.";

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


if ($date_object)
{
    $display_date =
        $date_object->format(
            'd M Y'
        );

    $day_of_week =
        $date_object->format(
            'l'
        );
}
else
{
    $display_date =
        $appointment_date;

    $day_of_week =
        '';
}


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


if ($time_object)
{
    $display_time =
        $time_object->format(
            'h:i A'
        );
}
else
{
    $display_time =
        $appointment_time;
}


/*====================================================
    HTML ESCAPE
====================================================*/

function appointmentSuccessEscape(
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
            SUCCESS HEADER
        =================================================-->

        <div class="dashboard-welcome">


            <div>

                <p class="dashboard-welcome-label">

                    Patient Portal

                </p>


                <h1>

                    Appointment Confirmed

                </h1>


                <p>

                    Your appointment has been successfully
                    booked.

                </p>

            </div>


        </div>


        <!--================================================
            SUCCESS MESSAGE
        =================================================-->

        <div
            class="dashboard-panel"
        >


            <div
                class="dashboard-panel-header"
            >

                <div>

                    <h2>

                        ✓ Appointment Successfully Booked

                    </h2>


                    <p>

                        Please keep your appointment number
                        and token number for your visit.

                    </p>

                </div>


            </div>


            <!--================================================
                APPOINTMENT NUMBER
            =================================================-->

            <div
                class="profile-message"
            >

                <strong>

                    Appointment Number:

                </strong>


                <?= appointmentSuccessEscape(
                    $appointment_no
                ); ?>

            </div>


            <br>


            <!--================================================
                APPOINTMENT INFORMATION
            =================================================-->

            <div
                class="patient-information"
            >


                <!-- Hospital -->

                <div
                    class="information-row"
                >

                    <span>

                        Hospital

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <!-- Patient Code -->

                <?php if (
                    $hospital_patient_code !== ''
                ): ?>

                    <div
                        class="information-row"
                    >

                        <span>

                            Patient Code

                        </span>


                        <strong>

                            <?= appointmentSuccessEscape(
                                $hospital_patient_code
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Department -->

                <?php if (
                    $department_name !== ''
                ): ?>

                    <div
                        class="information-row"
                    >

                        <span>

                            Department

                        </span>


                        <strong>

                            <?= appointmentSuccessEscape(
                                $department_name
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Doctor -->

                <div
                    class="information-row"
                >

                    <span>

                        Doctor

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $doctor_name
                        ); ?>

                    </strong>

                </div>


                <!-- Service -->

                <?php if (
                    $service_name !== ''
                ): ?>

                    <div
                        class="information-row"
                    >

                        <span>

                            Service

                        </span>


                        <strong>

                            <?= appointmentSuccessEscape(
                                $service_name
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


                <!-- Date -->

                <div
                    class="information-row"
                >

                    <span>

                        Appointment Date

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $display_date
                        ); ?>


                        <?php if (
                            $day_of_week !== ''
                        ): ?>

                            (
                            <?= appointmentSuccessEscape(
                                $day_of_week
                            ); ?>
                            )

                        <?php endif; ?>

                    </strong>

                </div>


                <!-- Time -->

                <div
                    class="information-row"
                >

                    <span>

                        Appointment Time

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


                <!-- Token -->

                <div
                    class="information-row"
                >

                    <span>

                        Token Number

                    </span>


                    <strong>

                        #<?= $token_number; ?>

                    </strong>

                </div>


                <!-- Appointment Type -->

                <div
                    class="information-row"
                >

                    <span>

                        Appointment Type

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $appointment_type
                        ); ?>

                    </strong>

                </div>


                <!-- Consultation Mode -->

                <div
                    class="information-row"
                >

                    <span>

                        Consultation Mode

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $consultation_mode
                        ); ?>

                    </strong>

                </div>


                <!-- Fee -->

                <div
                    class="information-row"
                >

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


                <!-- Status -->

                <div
                    class="information-row"
                >

                    <span>

                        Status

                    </span>


                    <strong>

                        Scheduled

                    </strong>

                </div>


            </div>


        </div>


        <br>


        <!--================================================
            QUEUE INFORMATION
        =================================================-->

        <div
            class="dashboard-panel"
        >


            <div
                class="dashboard-panel-header"
            >

                <div>

                    <h2>

                        Your Queue Information

                    </h2>


                    <p>

                        Your appointment is automatically
                        placed in the doctor's queue.

                    </p>

                </div>


            </div>


            <div
                class="patient-information"
            >


                <div
                    class="information-row"
                >

                    <span>

                        Token

                    </span>


                    <strong>

                        #<?= $token_number; ?>

                    </strong>

                </div>


                <div
                    class="information-row"
                >

                    <span>

                        Appointment Time

                    </span>


                    <strong>

                        <?= appointmentSuccessEscape(
                            $display_time
                        ); ?>

                    </strong>

                </div>


                <div
                    class="information-row"
                >

                    <span>

                        Patient Interval

                    </span>


                    <strong>

                        10 Minutes

                    </strong>

                </div>


                <div
                    class="information-row"
                >

                    <span>

                        Queue Status

                    </span>


                    <strong>

                        Scheduled

                    </strong>

                </div>


            </div>


        </div>


        <br>


        <!--================================================
            IMPORTANT MESSAGE
        =================================================-->

        <div
            class="profile-message"
        >

            <strong>

                Important:

            </strong>

            Please arrive at the hospital before your
            appointment time.

            <br><br>

            Keep your appointment number and patient code
            available when you visit the hospital.

        </div>


        <br>


        <!--================================================
            ACTION BUTTONS
        =================================================-->

        <div
            class="profile-form-actions"
        >


            <a
                href="my_appointments.php"
                class="profile-save-button"
            >

                My Appointments

            </a>


            <a
                href="book.php"
                class="profile-save-button"
            >

                Book Another Appointment

            </a>


        </div>


    </section>


</div>


<?php

require_once __DIR__ .
    '/../includes/footer.php';

?>


<?php

/*
|--------------------------------------------------------------------------
| CLEAR COMPLETED APPOINTMENT SESSION
|--------------------------------------------------------------------------
|
| We keep the success data during this page request.
| After rendering the page, remove it so refreshing the
| page does not reuse old appointment information.
|
*/

unset(
    $_SESSION[
        'completed_appointment'
    ]
);

?>