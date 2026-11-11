<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Patient Sidebar
|--------------------------------------------------------------------------
| This file is included from different patient portal pages.
| Therefore, all links use absolute project paths.
|--------------------------------------------------------------------------
*/

$patient_name = 'Patient';

if (
    isset($_SESSION['patient_auth']['first_name'])
    &&
    $_SESSION['patient_auth']['first_name'] !== ''
) {
    $patient_name =
        (string) $_SESSION['patient_auth']['first_name'];
}

if (
    isset($_SESSION['patient_auth']['last_name'])
    &&
    $_SESSION['patient_auth']['last_name'] !== ''
) {
    $patient_name .=
        ' ' .
        (string) $_SESSION['patient_auth']['last_name'];
}

$patient_name = htmlspecialchars(
    $patient_name,
    ENT_QUOTES,
    'UTF-8'
);

?>

<!--====================================================
    PATIENT SIDEBAR
====================================================-->

<aside class="patient-sidebar">


    <!--================================================
        BRAND
    =================================================-->

    <div class="sidebar-brand">

        <a href="/Hospital_Management_System/patient_portal/dashboard.php" class="sidebar-brand-link">

            Smart Hospital

        </a>

    </div>


    <!--================================================
        PATIENT INFORMATION
    =================================================-->

    <div class="sidebar-patient-info">

        <div class="sidebar-patient-name">

            <?= $patient_name; ?>

        </div>


        <div class="sidebar-patient-role">

            Patient

        </div>

    </div>


    <!--================================================
        NAVIGATION
    =================================================-->

    <nav class="patient-sidebar-navigation">


        <ul class="patient-sidebar-menu">


            <!-- Dashboard -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/dashboard.php">

                    🏠

                    <span>
                        Dashboard
                    </span>

                </a>

            </li>


            <!-- My Profile -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/profile/profile.php">

                    👤

                    <span>
                        My Profile
                    </span>

                </a>

            </li>


            <!-- Appointments -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/appointments/my_appointments.php">

                    📅

                    <span>
                        Appointments
                    </span>

                </a>

            </li>


            <!-- Book Appointment -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/appointments/book.php">

                    ➕

                    <span>
                        Book Appointment
                    </span>

                </a>

            </li>

            <!-- Hospital Search -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/hospitals/hospital_search.php">

                    🏥

                    <span>
                        Hospital Search
                    </span>

                </a>

            </li>


            <!-- Medical Records -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/medical/records.php">

                    📋

                    <span>
                        Medical Records
                    </span>

                </a>

            </li>


            <!-- Prescriptions -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/medical/prescriptions.php">

                    💊

                    <span>
                        Prescriptions
                    </span>

                </a>

            </li>


            <!-- Lab Reports -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/medical/lab_reports.php">

                    🧪

                    <span>
                        Lab Reports
                    </span>

                </a>

            </li>


            <!-- Bills & Payments -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/billing/bills.php">

                    💳

                    <span>
                        Bills & Payments
                    </span>

                </a>

            </li>


            <!-- My Documents -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/profile/documents.php">

                    📄

                    <span>
                        My Documents
                    </span>

                </a>

            </li>


            <!-- AI Health Assistant -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/ai/assistant.php">

                    🤖

                    <span>
                        AI Health Assistant
                    </span>

                </a>

            </li>


            <!-- Video Consultation -->

            <li>

                <a href="/Hospital_Management_System/patient_portal/video/consultation.php">

                    🎥

                    <span>
                        Video Consultation
                    </span>

                </a>

            </li>


        </ul>


    </nav>


    <!--================================================
        LOGOUT
    =================================================-->

    <div class="sidebar-logout">

        <a href="/Hospital_Management_System/patient_portal/logout.php">

            ↪

            <span>
                Logout
            </span>

        </a>

    </div>


</aside>