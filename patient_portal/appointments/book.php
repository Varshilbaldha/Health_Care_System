<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BOOK APPOINTMENT - HOSPITAL DISCOVERY
|--------------------------------------------------------------------------
| Step 1:
| Show hospitals from the central hospital_registration table.
|
| The patient can search hospitals by city.
|
| If the patient is already mapped with a hospital:
|     -> Continue to appointment booking
|
| If the patient is not mapped:
|     -> Register Hospital
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ . '/../includes/auth_check.php';


/*====================================================
    PAGE TITLE
====================================================*/

$page_title = "Book Appointment";


/*====================================================
    GET LOGGED-IN PATIENT
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
    (string) (
        $_SESSION['error']
        ?? ''
    );

$success =
    (string) (
        $_SESSION['success']
        ?? ''
    );


unset($_SESSION['error']);
unset($_SESSION['success']);


/*====================================================
    CITY SEARCH
====================================================*/

$city =
    trim(
        (string) (
            $_GET['city']
            ?? ''
        )
    );


/*====================================================
    GET HOSPITALS
====================================================*/

/*
    CENTRAL DATABASE:

    hospital_registration
            |
            | hospital_id
            |
            ↓
    patient_hospital_mapping

    LEFT JOIN is used because we want to show:

    1. Hospitals already registered by patient
    2. Hospitals not yet registered by patient
*/


if ($city !== '')
{
    $query = "
        SELECT

            hr.hospital_id,
            hr.application_no,
            hr.hospital_name,
            hr.registration_no,
            hr.hospital_type,
            hr.hospital_email,
            hr.hospital_phone,
            hr.emergency_no,
            hr.website,
            hr.address1,
            hr.address2,
            hr.city,
            hr.state,
            hr.zip,
            hr.database_name,

            phm.mapping_id,
            phm.hospital_patient_code,
            phm.patient_status

        FROM hospital_registration AS hr

        LEFT JOIN patient_hospital_mapping AS phm
            ON phm.hospital_id = hr.hospital_id
            AND phm.account_id = ?
            AND phm.patient_status = 'Active'

        WHERE LOWER(hr.city) = LOWER(?)

        ORDER BY
            hr.hospital_name ASC
    ";
}
else
{
    $query = "
        SELECT

            hr.hospital_id,
            hr.application_no,
            hr.hospital_name,
            hr.registration_no,
            hr.hospital_type,
            hr.hospital_email,
            hr.hospital_phone,
            hr.emergency_no,
            hr.website,
            hr.address1,
            hr.address2,
            hr.city,
            hr.state,
            hr.zip,
            hr.database_name,

            phm.mapping_id,
            phm.hospital_patient_code,
            phm.patient_status

        FROM hospital_registration AS hr

        LEFT JOIN patient_hospital_mapping AS phm
            ON phm.hospital_id = hr.hospital_id
            AND phm.account_id = ?
            AND phm.patient_status = 'Active'

        ORDER BY
            hr.city ASC,
            hr.hospital_name ASC
    ";
}


/*====================================================
    PREPARE QUERY
====================================================*/

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


/*====================================================
    BIND PARAMETERS
====================================================*/

if ($city !== '')
{
    mysqli_stmt_bind_param(
        $stmt,
        "is",
        $account_id,
        $city
    );
}
else
{
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $account_id
    );
}


/*====================================================
    EXECUTE
====================================================*/

if (!mysqli_stmt_execute($stmt))
{
    mysqli_stmt_close($stmt);

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


/*====================================================
    FETCH RESULTS
====================================================*/

$result =
    mysqli_stmt_get_result($stmt);


$hospitals = [];


if ($result)
{
    while (
        $row =
        mysqli_fetch_assoc($result)
    )
    {
        $hospitals[] = $row;
    }

    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    HTML ESCAPE
====================================================*/

function bookEscape(
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
                    Book Appointment
                </h1>

                <p>
                    Find a hospital and continue
                    to appointment booking.
                </p>

            </div>

        </div>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= bookEscape($error); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            SUCCESS
        =================================================-->

        <?php if ($success !== ''): ?>

            <div class="profile-message profile-success">

                <?= bookEscape($success); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            CITY SEARCH
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Find Hospital
                    </h2>

                    <p>
                        Search hospitals by city.
                    </p>

                </div>

            </div>


            <form
                method="GET"
                action="book.php"
            >

                <div class="profile-form-grid">


                    <div class="profile-field">

                        <label for="city">

                            City

                        </label>


                        <input
                            type="text"
                            id="city"
                            name="city"
                            value="<?= bookEscape($city); ?>"
                            placeholder="Enter city name"
                            maxlength="50"
                        >

                    </div>


                </div>


                <div class="profile-form-actions">


                    <button
                        type="submit"
                        class="profile-save-button"
                    >

                        Search Hospitals

                    </button>


                </div>


            </form>

        </div>


        <br>


        <!--================================================
            HOSPITAL RESULTS
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Available Hospitals
                    </h2>


                    <?php if ($city !== ''): ?>

                        <p>

                            Hospitals found in

                            <strong>
                                <?= bookEscape($city); ?>
                            </strong>

                        </p>

                    <?php else: ?>

                        <p>
                            All hospitals registered
                            on the Smart Hospital portal.
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <?php if (count($hospitals) === 0): ?>


                <!--========================================
                    NO RESULTS
                =========================================-->

                <div class="profile-message profile-error">

                    <?php if ($city !== ''): ?>

                        No hospital was found in
                        <strong>
                            <?= bookEscape($city); ?>
                        </strong>.

                    <?php else: ?>

                        No hospitals are currently
                        registered on the portal.

                    <?php endif; ?>

                </div>


            <?php else: ?>


                <!--========================================
                    HOSPITAL LIST
                =========================================-->

                <div class="profile-form-grid">


                    <?php foreach ($hospitals as $hospital): ?>


                        <?php

                        $is_registered =
                            !empty(
                                $hospital['mapping_id']
                            );

                        ?>


                        <div
                            class="hospital-selection-card"
                        >


                            <!--================================
                                HOSPITAL INFORMATION
                            =================================-->

                            <div
                                class="hospital-selection-content"
                            >


                                <h3>

                                    <?= bookEscape(
                                        (string)
                                        $hospital['hospital_name']
                                    ); ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $hospital['hospital_type']
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Type:
                                        </strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital['hospital_type']
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $hospital['registration_no']
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Registration No:
                                        </strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital['registration_no']
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <p>

                                    <strong>
                                        City:
                                    </strong>

                                    <?= bookEscape(
                                        (string)
                                        $hospital['city']
                                    ); ?>


                                    <?php if (
                                        !empty(
                                            $hospital['state']
                                        )
                                    ): ?>

                                        ,

                                        <?= bookEscape(
                                            (string)
                                            $hospital['state']
                                        ); ?>

                                    <?php endif; ?>

                                </p>


                                <?php if (
                                    !empty(
                                        $hospital['hospital_phone']
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Phone:
                                        </strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital['hospital_phone']
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $hospital['emergency_no']
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Emergency:
                                        </strong>

                                        <?= bookEscape(
                                            (string)
                                            $hospital['emergency_no']
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <!--================================
                                    REGISTERED STATUS
                                =================================-->

                                <?php if ($is_registered): ?>


                                    <p class="status-active">

                                        ✓ Registered Hospital

                                    </p>


                                    <?php if (
                                        !empty(
                                            $hospital[
                                                'hospital_patient_code'
                                            ]
                                        )
                                    ): ?>

                                        <p>

                                            <strong>
                                                Patient Code:
                                            </strong>

                                            <?= bookEscape(
                                                (string)
                                                $hospital[
                                                    'hospital_patient_code'
                                                ]
                                            ); ?>

                                        </p>

                                    <?php endif; ?>


                                    <!--============================
                                        CONTINUE
                                    =============================-->

                                    <form
                                        method="POST"
                                        action="select_hospital.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="hospital_id"
                                            value="<?= (int)
                                                $hospital[
                                                    'hospital_id'
                                                ]; ?>"
                                        >


                                        <input
                                            type="hidden"
                                            name="mapping_id"
                                            value="<?= (int)
                                                $hospital[
                                                    'mapping_id'
                                                ]; ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="profile-save-button"
                                        >

                                            Continue

                                        </button>

                                    </form>


                                <?php else: ?>


                                    <!--============================
                                        NOT REGISTERED
                                    =============================-->

                                    <p class="status-pending">

                                        Not Registered

                                    </p>


                                    <form
                                        method="POST"
                                        action="register_hospital_process.php"
                                    >

                                        <input
                                            type="hidden"
                                            name="hospital_id"
                                            value="<?= (int)
                                                $hospital[
                                                    'hospital_id'
                                                ]; ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="profile-save-button"
                                        >

                                            Register Hospital

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