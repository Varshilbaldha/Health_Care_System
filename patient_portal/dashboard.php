<?php

declare(strict_types=1);


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ . '/includes/auth_check.php';


/*====================================================
    PAGE TITLE
====================================================*/

$page_title = "Patient Dashboard";


/*====================================================
    HTML ESCAPE FUNCTION
====================================================*/

if (!function_exists('patientEscape'))
{
    function patientEscape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*====================================================
    BASIC PATIENT DATA
====================================================*/

$account_id =
    (int) (
        $patient['account_id'] ?? 0
    );


$first_name =
    (string) (
        $patient['first_name'] ?? ''
    );


$last_name =
    (string) (
        $patient['last_name'] ?? ''
    );


$patient_full_name =
    trim(
        $first_name . ' ' . $last_name
    );


if ($patient_full_name === '')
{
    $patient_full_name = 'Patient';
}


/*====================================================
    DEFAULT DASHBOARD VALUES
====================================================*/

$registered_hospitals = 0;

$total_documents = 0;

$profile_exists = false;

$profile = [];


/*====================================================
    VALID ACCOUNT CHECK
====================================================*/

if ($account_id <= 0)
{
    session_unset();

    $_SESSION['error'] =
        "Invalid patient session.";

    header(
        "Location: /Hospital_Management_System/patient_portal/auth/login.php"
    );

    exit;
}


/*====================================================
    GET PATIENT PROFILE
====================================================*/

$query = "
    SELECT
        profile_id,
        gender,
        date_of_birth,
        blood_group,
        city,
        state,
        profile_photo
    FROM patient_profiles
    WHERE account_id = ?
    LIMIT 1
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if ($stmt)
{
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $account_id
    );


    if (mysqli_stmt_execute($stmt))
    {
        $result =
            mysqli_stmt_get_result($stmt);


        if (
            $result &&
            mysqli_num_rows($result) > 0
        )
        {
            $profile_exists = true;

            $profile =
                mysqli_fetch_assoc($result);
        }


        if ($result)
        {
            mysqli_free_result($result);
        }
    }


    mysqli_stmt_close($stmt);
}


/*====================================================
    COUNT REGISTERED HOSPITALS
====================================================*/

$query = "
    SELECT
        COUNT(*) AS total_hospitals
    FROM patient_hospital_mapping
    WHERE account_id = ?
      AND patient_status = 'Active'
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if ($stmt)
{
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $account_id
    );


    if (mysqli_stmt_execute($stmt))
    {
        $result =
            mysqli_stmt_get_result($stmt);


        if ($result)
        {
            $row =
                mysqli_fetch_assoc($result);


            $registered_hospitals =
                (int) (
                    $row['total_hospitals'] ?? 0
                );


            mysqli_free_result($result);
        }
    }


    mysqli_stmt_close($stmt);
}


/*====================================================
    COUNT PATIENT DOCUMENTS
====================================================*/

$query = "
    SELECT
        COUNT(*) AS total_documents
    FROM patient_documents
    WHERE account_id = ?
";


$stmt = mysqli_prepare(
    $conn,
    $query
);


if ($stmt)
{
    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $account_id
    );


    if (mysqli_stmt_execute($stmt))
    {
        $result =
            mysqli_stmt_get_result($stmt);


        if ($result)
        {
            $row =
                mysqli_fetch_assoc($result);


            $total_documents =
                (int) (
                    $row['total_documents'] ?? 0
                );


            mysqli_free_result($result);
        }
    }


    mysqli_stmt_close($stmt);
}


/*====================================================
    PROFILE COMPLETION
====================================================*/

$profile_completion = 0;


if ($profile_exists)
{
    $profile_fields = [

        'gender' =>
            $profile['gender'] ?? null,

        'date_of_birth' =>
            $profile['date_of_birth'] ?? null,

        'blood_group' =>
            $profile['blood_group'] ?? null,

        'city' =>
            $profile['city'] ?? null,

        'state' =>
            $profile['state'] ?? null,

        'profile_photo' =>
            $profile['profile_photo'] ?? null

    ];


    $completed_fields = 0;


    $total_profile_fields =
        count($profile_fields);


    foreach (
        $profile_fields
        as $value
    )
    {
        if (
            $value !== null &&
            $value !== ''
        )
        {
            $completed_fields++;
        }
    }


    if ($total_profile_fields > 0)
    {
        $profile_completion =
            (int) round(
                (
                    $completed_fields /
                    $total_profile_fields
                ) * 100
            );
    }
}


/*====================================================
    HEADER
====================================================*/

require_once __DIR__ . '/includes/header.php';

?>


<div class="patient-dashboard">


    <!--================================================
        SIDEBAR
    =================================================-->

    <?php

    require_once __DIR__ .
        '/includes/sidebar.php';

    ?>


    <!--================================================
        MAIN DASHBOARD CONTENT
    =================================================-->

    <section class="patient-dashboard-content">


        <!--================================================
            WELCOME
        =================================================-->

        <div class="dashboard-welcome">

            <div>

                <p class="dashboard-welcome-label">
                    Patient Portal
                </p>


                <h1>

                    Welcome,
                    <?= patientEscape(
                        $patient_full_name
                    ); ?>

                    👋

                </h1>


                <p>

                    Manage your health information,
                    appointments and medical records
                    from one place.

                </p>

            </div>

        </div>


        <!--================================================
            SUMMARY CARDS
        =================================================-->

        <div class="dashboard-cards">


            <!-- Patient ID -->

            <div class="dashboard-card">

                <div class="dashboard-card-icon">
                    🆔
                </div>


                <div>

                    <span>
                        Patient ID
                    </span>


                    <strong>

                        <?= patientEscape(
                            (string) (
                                $patient['patient_uuid']
                                ?? '-'
                            )
                        ); ?>

                    </strong>

                </div>

            </div>


            <!-- Hospitals -->

            <div class="dashboard-card">

                <div class="dashboard-card-icon">
                    🏥
                </div>


                <div>

                    <span>
                        Registered Hospitals
                    </span>


                    <strong>
                        <?= $registered_hospitals; ?>
                    </strong>

                </div>

            </div>


            <!-- Documents -->

            <div class="dashboard-card">

                <div class="dashboard-card-icon">
                    📄
                </div>


                <div>

                    <span>
                        My Documents
                    </span>


                    <strong>
                        <?= $total_documents; ?>
                    </strong>

                </div>

            </div>


            <!-- Profile -->

            <div class="dashboard-card">

                <div class="dashboard-card-icon">
                    👤
                </div>


                <div>

                    <span>
                        Profile Completion
                    </span>


                    <strong>
                        <?= $profile_completion; ?>%
                    </strong>

                </div>

            </div>


        </div>


        <!--================================================
            TWO COLUMN AREA
        =================================================-->

        <div class="dashboard-grid">


            <!--================================================
                PATIENT INFORMATION
            =================================================-->

            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>

                        <h2>
                            My Information
                        </h2>


                        <p>
                            Your registered account details
                        </p>

                    </div>


                    <a
                        href="profile/profile.php"
                        class="dashboard-panel-link"
                    >
                        View Profile
                    </a>

                </div>


                <div class="patient-information">


                    <div class="information-row">

                        <span>
                            Full Name
                        </span>


                        <strong>

                            <?= patientEscape(
                                $patient_full_name
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Email
                        </span>


                        <strong>

                            <?= patientEscape(
                                (string) (
                                    $patient['email']
                                    ?? '-'
                                )
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Mobile
                        </span>


                        <strong>

                            <?= patientEscape(
                                (string) (
                                    $patient['mobile']
                                    ?? '-'
                                )
                            ); ?>

                        </strong>

                    </div>


                    <div class="information-row">

                        <span>
                            Profile Status
                        </span>


                        <strong>

                            <?php if (
                                $profile_completion >= 100
                            ): ?>

                                <span class="status-active">
                                    Complete
                                </span>

                            <?php elseif (
                                $profile_exists
                            ): ?>

                                <span class="status-pending">
                                    Incomplete
                                </span>

                            <?php else: ?>

                                <span class="status-pending">
                                    Not Created
                                </span>

                            <?php endif; ?>

                        </strong>

                    </div>


                </div>

            </div>


            <!--================================================
                QUICK ACTIONS
            =================================================-->

            <div class="dashboard-panel">

                <div class="dashboard-panel-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>


                        <p>
                            Frequently used patient services
                        </p>

                    </div>

                </div>


                <div class="quick-actions">


                    <a
                        href="profile/profile.php"
                        class="quick-action"
                    >

                        <span>
                            👤
                        </span>


                        <div>

                            <strong>
                                My Profile
                            </strong>


                            <small>
                                Update your information
                            </small>

                        </div>

                    </a>


                    <a
                        href="appointments/book.php"
                        class="quick-action"
                    >

                        <span>
                            📅
                        </span>


                        <div>

                            <strong>
                                Book Appointment
                            </strong>


                            <small>
                                Find a doctor and service
                            </small>

                        </div>

                    </a>


                    <a
                        href="medical/prescriptions.php"
                        class="quick-action"
                    >

                        <span>
                            💊
                        </span>


                        <div>

                            <strong>
                                Prescriptions
                            </strong>


                            <small>
                                View your prescriptions
                            </small>

                        </div>

                    </a>


                    <a
                        href="medical/lab_reports.php"
                        class="quick-action"
                    >

                        <span>
                            🧪
                        </span>


                        <div>

                            <strong>
                                Lab Reports
                            </strong>


                            <small>
                                View your test reports
                            </small>

                        </div>

                    </a>


                    <a
                        href="billing/bills.php"
                        class="quick-action"
                    >

                        <span>
                            💳
                        </span>


                        <div>

                            <strong>
                                Bills & Payments
                            </strong>


                            <small>
                                Check your billing details
                            </small>

                        </div>

                    </a>


                    <a
                        href="profile/documents.php"
                        class="quick-action"
                    >

                        <span>
                            📄
                        </span>


                        <div>

                            <strong>
                                My Documents
                            </strong>


                            <small>
                                Manage uploaded documents
                            </small>

                        </div>

                    </a>


                </div>

            </div>


        </div>


        <!--================================================
            PROFILE COMPLETION
        =================================================-->

        <?php if (
            $profile_completion < 100
        ): ?>

            <div class="dashboard-panel profile-completion-panel">


                <div class="dashboard-panel-header">

                    <div>

                        <h2>
                            Complete Your Profile
                        </h2>


                        <p>

                            Complete your profile to get
                            the best experience from the
                            patient portal.

                        </p>

                    </div>


                    <a
                        href="profile/profile.php"
                        class="dashboard-panel-link"
                    >
                        Complete Now
                    </a>

                </div>


                <div class="profile-progress">


                    <div class="profile-progress-bar">

                        <div
                            class="profile-progress-value"
                            style="width: <?= $profile_completion; ?>%;"
                        ></div>

                    </div>


                    <span>

                        <?= $profile_completion; ?>%
                        Complete

                    </span>

                </div>

            </div>

        <?php endif; ?>


        <!--================================================
            HEALTH SERVICES
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Health Services
                    </h2>


                    <p>
                        Your hospital services will appear here.
                    </p>

                </div>

            </div>


            <div class="service-placeholder-grid">


                <div class="service-placeholder">

                    <span>
                        📅
                    </span>


                    <strong>
                        Appointments
                    </strong>


                    <small>
                        Coming with hospital integration
                    </small>

                </div>


                <div class="service-placeholder">

                    <span>
                        🏥
                    </span>


                    <strong>
                        My Hospitals
                    </strong>


                    <small>

                        <?= $registered_hospitals; ?>

                        active registration(s)

                    </small>

                </div>


                <div class="service-placeholder">

                    <span>
                        🤖
                    </span>


                    <strong>
                        AI Health Assistant
                    </strong>


                    <small>
                        Coming soon
                    </small>

                </div>


                <div class="service-placeholder">

                    <span>
                        🎥
                    </span>


                    <strong>
                        Video Consultation
                    </strong>


                    <small>
                        Coming soon
                    </small>

                </div>


            </div>

        </div>


    </section>

</div>


<?php

/*====================================================
    FOOTER
====================================================*/

require_once __DIR__ .
    '/includes/footer.php';

?>