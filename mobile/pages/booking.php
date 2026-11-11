<?php

declare(strict_types=1);

session_start();

date_default_timezone_set('Asia/Kolkata');


/*====================================================
    CENTRAL CONFIG
====================================================*/

$config_file =
    __DIR__ .
    '/../../includes/config.php';

if (!is_file($config_file)) {
    die('Central database configuration not found.');
}

require_once $config_file;


/*====================================================
    LOGIN
====================================================*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {
    header(
        'Location: ../../patient_portal/auth/login.php'
    );

    exit;
}


$account_id =
    (int)(
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if ($account_id <= 0) {
    die('Invalid patient session.');
}


/*====================================================
    GET REGISTERED HOSPITALS
====================================================*/

$sql = "

    SELECT

        phm.mapping_id,
        phm.hospital_id,
        phm.hospital_patient_code,

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
        $sql
    );


$hospitals = [];


if ($stmt) {

    mysqli_stmt_bind_param(
        $stmt,
        'i',
        $account_id
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    if ($result) {

        while (
            $row =
            mysqli_fetch_assoc($result)
        ) {

            $hospitals[] =
                $row;
        }

        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);
}


/*====================================================
    ESCAPE
====================================================*/

function mobileBookingEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    <meta
        name="theme-color"
        content="#2563eb"
    >

    <title>
        Book Appointment
    </title>

    <link
        rel="stylesheet"
        href="../style.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;

            background: #f5f7fb;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #172033;
        }


        .booking-page {
            min-height: 100vh;

            padding-bottom: 30px;
        }


        /* HEADER */

        .booking-header {

            background:
                linear-gradient(
                    135deg,
                    #6355f4,
                    #5143e8
                );

            color: white;

            padding: 18px;

            border-radius:
                0 0 25px 25px;
        }


        .booking-top {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .back-button {

            width: 40px;

            height: 40px;

            border: 0;

            border-radius: 50%;

            background:
                rgba(255,255,255,.16);

            color: white;

            font-size: 21px;

            cursor: pointer;
        }


        .booking-header h1 {

            margin: 0;

            font-size: 23px;
        }


        .booking-header p {

            margin:
                14px 0 0;

            font-size: 12px;

            opacity: .85;
        }


        /* STEPS */

        .booking-steps {

            display: flex;

            justify-content:
                space-between;

            padding: 18px 20px 5px;
        }


        .step {

            display: flex;

            flex-direction: column;

            align-items: center;

            gap: 5px;

            font-size: 9px;

            color: #9aa3b2;
        }


        .step-number {

            width: 30px;

            height: 30px;

            border-radius: 50%;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #e5e9f0;

            font-weight: 700;
        }


        .step.active {

            color: #6355f4;
        }


        .step.active .step-number {

            background: #6355f4;

            color: white;
        }


        /* CONTENT */

        .booking-content {

            padding: 15px;
        }


        .booking-card {

            background: white;

            border-radius: 20px;

            padding: 18px;

            margin-bottom: 15px;

            box-shadow:
                0 7px 22px
                rgba(25,55,100,.07);
        }


        .booking-card h2 {

            margin:
                0 0 5px;

            font-size: 17px;
        }


        .booking-card-description {

            margin:
                0 0 15px;

            color: #7d8797;

            font-size: 11px;
        }


        /* HOSPITAL */

        .hospital-option {

            border:
                1.5px solid #e1e6ef;

            border-radius: 15px;

            padding: 13px;

            margin-bottom: 10px;

            cursor: pointer;

            transition: .2s;
        }


        .hospital-option:hover {

            border-color:
                #6355f4;
        }


        .hospital-option.selected {

            border-color:
                #6355f4;

            background:
                #eeedff;
        }


        .hospital-option-content {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .hospital-icon {

            width: 43px;

            height: 43px;

            border-radius: 13px;

            background: #eaf2ff;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 21px;
        }


        .hospital-info {

            flex: 1;
        }


        .hospital-info strong {

            display: block;

            font-size: 13px;

            margin-bottom: 4px;
        }


        .hospital-info span {

            color: #8992a3;

            font-size: 10px;
        }


        .radio {

            width: 20px;

            height: 20px;

            border:
                2px solid #cdd4df;

            border-radius: 50%;
        }


        .selected .radio {

            border:
                6px solid #6355f4;
        }


        /* FORM */

        .field {

            margin-bottom: 15px;
        }


        .field label {

            display: block;

            font-size: 11px;

            font-weight: 700;

            margin-bottom: 7px;
        }


        .field select,
        .field input,
        .field textarea {

            width: 100%;

            border:
                1px solid #dce2eb;

            border-radius: 12px;

            padding: 12px;

            background: white;

            font-size: 12px;

            outline: none;
        }


        .field select:focus,
        .field input:focus,
        .field textarea:focus {

            border-color:
                #6355f4;
        }


        .field textarea {

            resize: vertical;

            min-height: 85px;
        }


        /* MODE */

        .mode-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;
        }


        .mode-option {

            border:
                1px solid #dce2eb;

            border-radius: 13px;

            padding: 13px;

            text-align: center;

            cursor: pointer;

            font-size: 11px;
        }


        .mode-option.selected {

            background: #eeedff;

            border-color: #6355f4;

            color: #6355f4;

            font-weight: 700;
        }


        /* BUTTON */

        .continue-button {

            width: 100%;

            border: 0;

            background:
                linear-gradient(
                    135deg,
                    #6355f4,
                    #5143e8
                );

            color: white;

            padding: 14px;

            border-radius: 14px;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 8px 18px
                rgba(99,85,244,.22);
        }


        .continue-button:active {

            transform: scale(.98);
        }


        .empty {

            text-align: center;

            padding: 35px 15px;

            color: #7d8797;

            font-size: 12px;
        }

    </style>

<link rel="stylesheet" href="../css/common.css">
</head>


<body>


<div class="booking-page">


    <!-- HEADER -->

    <header class="booking-header">

        <div class="booking-top">

            <button
                class="back-button"
                onclick="history.back()"
            >
                ←
            </button>

            <h1>
                Book Appointment
            </h1>

        </div>


        <p>
            Choose your hospital and appointment details
        </p>

    </header>


    <!-- STEPS -->

    <div class="booking-steps">

        <div class="step active">

            <div class="step-number">
                1
            </div>

            Hospital

        </div>


        <div class="step">

            <div class="step-number">
                2
            </div>

            Doctor

        </div>


        <div class="step">

            <div class="step-number">
                3
            </div>

            Date

        </div>


        <div class="step">

            <div class="step-number">
                4
            </div>

            Confirm

        </div>

    </div>


    <main class="booking-content">


        <!-- HOSPITAL -->

        <div class="booking-card">

            <h2>
                Select Hospital
            </h2>

            <p class="booking-card-description">
                Choose one of your registered hospitals.
            </p>


            <?php if (
                empty($hospitals)
            ): ?>

                <div class="empty">

                    🏥

                    <br><br>

                    No registered hospital found.

                </div>

            <?php else: ?>


                <?php foreach (
                    $hospitals as $index => $hospital
                ): ?>


                    <div
                        class="hospital-option"
                        onclick="selectHospital(
                            this,
                            <?= (int)$hospital['mapping_id']; ?>
                        )"
                    >


                        <div
                            class="hospital-option-content"
                        >

                            <div class="hospital-icon">
                                🏥
                            </div>


                            <div class="hospital-info">

                                <strong>

                                    <?= mobileBookingEscape(
                                        (string)
                                        $hospital[
                                            'hospital_name'
                                        ]
                                    ); ?>

                                </strong>


                                <span>

                                    <?= mobileBookingEscape(
                                        trim(
                                            (string)(
                                                $hospital[
                                                    'city'
                                                ] ?? ''
                                            )
                                            .
                                            (
                                                !empty(
                                                    $hospital['state']
                                                )
                                                ? ', ' .
                                                $hospital['state']
                                                : ''
                                            )
                                        )
                                    ); ?>

                                </span>

                            </div>


                            <div class="radio"></div>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php endif; ?>

        </div>


        <!-- APPOINTMENT DETAILS -->

        <div
            class="booking-card"
            id="appointmentDetails"
            style="display:none;"
        >

            <h2>
                Appointment Details
            </h2>

            <p class="booking-card-description">
                Select your doctor and preferred time.
            </p>


            <div class="field">

                <label>
                    Department
                </label>

                <select id="department">

                    <option value="">
                        Select Department
                    </option>

                </select>

            </div>


            <div class="field">

                <label>
                    Doctor
                </label>

                <select id="doctor">

                    <option value="">
                        Select Doctor
                    </option>

                </select>

            </div>


            <div class="field">

                <label>
                    Appointment Date
                </label>

                <input
                    type="date"
                    id="appointmentDate"
                    min="<?= date('Y-m-d'); ?>"
                >

            </div>


            <div class="field">

                <label>
                    Appointment Time
                </label>

                <input
                    type="time"
                    id="appointmentTime"
                >

            </div>


            <div class="field">

                <label>
                    Consultation Mode
                </label>


                <div class="mode-grid">


                    <div
                        class="mode-option selected"
                        onclick="selectMode(
                            this,
                            'In-Person'
                        )"
                    >

                        🏥

                        <br>

                        In-Person

                    </div>


                    <div
                        class="mode-option"
                        onclick="selectMode(
                            this,
                            'Video'
                        )"
                    >

                        📹

                        <br>

                        Video

                    </div>


                </div>

            </div>


            <div class="field">

                <label>
                    Symptoms
                </label>

                <textarea
                    id="symptoms"
                    maxlength="2000"
                    placeholder="Describe your symptoms..."
                ></textarea>

            </div>


            <div class="field">

                <label>
                    Notes
                </label>

                <textarea
                    id="notes"
                    maxlength="2000"
                    placeholder="Additional information..."
                ></textarea>

            </div>


            <button
                class="continue-button"
                onclick="continueBooking()"
            >

                Continue to Confirmation →

            </button>

        </div>


    </main>


</div>


<script>

let selectedMappingId = 0;

let selectedMode =
    'In-Person';


function selectHospital(
    element,
    mappingId
) {

    document
        .querySelectorAll(
            '.hospital-option'
        )
        .forEach(
            function(item) {

                item.classList.remove(
                    'selected'
                );

            }
        );


    element.classList.add(
        'selected'
    );


    selectedMappingId =
        mappingId;


    document.getElementById(
        'appointmentDetails'
    ).style.display =
        'block';


    document.getElementById(
        'appointmentDetails'
    ).scrollIntoView({
        behavior: 'smooth'
    });

}


function selectMode(
    element,
    mode
) {

    document
        .querySelectorAll(
            '.mode-option'
        )
        .forEach(
            function(item) {

                item.classList.remove(
                    'selected'
                );

            }
        );


    element.classList.add(
        'selected'
    );


    selectedMode =
        mode;
}


function continueBooking() {

    if (
        selectedMappingId <= 0
    ) {

        alert(
            'Please select a hospital.'
        );

        return;
    }


    const department =
        document.getElementById(
            'department'
        ).value;


    const doctor =
        document.getElementById(
            'doctor'
        ).value;


    const date =
        document.getElementById(
            'appointmentDate'
        ).value;


    const time =
        document.getElementById(
            'appointmentTime'
        ).value;


    if (!department) {

        alert(
            'Please select a department.'
        );

        return;
    }


    if (!doctor) {

        alert(
            'Please select a doctor.'
        );

        return;
    }


    if (!date) {

        alert(
            'Please select appointment date.'
        );

        return;
    }


    if (!time) {

        alert(
            'Please select appointment time.'
        );

        return;
    }


    /*
     * Temporary next-step navigation.
     *
     * Next step mein hum isi screen ko
     * real hospital database se connect
     * karenge.
     */

    const params =
        new URLSearchParams({

            mapping_id:
                selectedMappingId,

            department_id:
                department,

            doctor_id:
                doctor,

            appointment_date:
                date,

            appointment_time:
                time,

            consultation_mode:
                selectedMode,

            symptoms:
                document.getElementById(
                    'symptoms'
                ).value,

            notes:
                document.getElementById(
                    'notes'
                ).value

        });


    window.location.href =
        'booking_confirm.php?' +
        params.toString();

}

</script>


</body>

</html>