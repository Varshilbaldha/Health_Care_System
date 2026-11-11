<?php

$current_page = basename($_SERVER['PHP_SELF']);

?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="css/sidebar.css">
<link rel="stylesheet" href="css/admin_common.css">

<aside class="sidebar" id="sidebar">

    <div class="hospital-logo">

        <button type="button" class="sidebar-toggle" id="sidebarToggle">

            <i class="fa-solid fa-hospital"></i>

        </button>

        <h2>
            <?php
            echo htmlspecialchars(
                $_SESSION['hospital_name'] ?? 'Hospital'
            );
            ?>
        </h2>

    </div>


    <div class="admin-profile">

        <div class="admin-icon">

            <i class="fa-solid fa-user-doctor"></i>

        </div>

        <div>

            <h3>
                <?php
                echo htmlspecialchars(
                    $_SESSION['admin_name'] ?? 'Administrator'
                );
                ?>
            </h3>

            <p>Administrator</p>

        </div>

    </div>


    <nav class="menu">
        <a href="admin_dashboard.php" class="<?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-table-columns"></i><span>Dashboard</span></a>
        <div class="menu-section"><span>Hospital Management</span></div>
        <a href="departments.php" class="<?php echo $current_page === 'departments.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-building"></i><span>Departments</span></a>
        <a href="doctors.php" class="<?php echo $current_page === 'doctors.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-user-doctor"></i><span>Doctors</span></a>
        <a href="staff.php" class="<?php echo $current_page === 'staff.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-users"></i><span>Staff</span></a>
        <a href="patients.php" class="<?php echo $current_page === 'patients.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-bed-pulse"></i><span>Patients</span></a>
        <a href="services.php" class="<?php echo $current_page === 'services.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-hand-holding-medical"></i><span>Services</span></a>
        <div class="menu-section"><span>Appointments</span></div>
        <a href="appointments.php" class="<?php echo $current_page === 'appointments.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-calendar-check"></i><span>Appointments</span></a>
        <a href="hospital_module.php?module=availability"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'availability' ? 'active' : ''; ?>"><i
                class="fa-solid fa-clock"></i><span>Doctor Availability</span></a>
        <div class="menu-section"><span>Clinical</span></div>
        <a href="hospital_module.php?module=medical_records"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'medical_records' ? 'active' : ''; ?>"><i
                class="fa-solid fa-file-medical"></i><span>Medical Records</span></a>
        <a href="hospital_module.php?module=prescriptions"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'prescriptions' ? 'active' : ''; ?>"><i
                class="fa-solid fa-prescription-bottle-medical"></i><span>Prescriptions</span></a>
        <a href="hospital_module.php?module=lab_tests"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'lab_tests' ? 'active' : ''; ?>"><i
                class="fa-solid fa-flask"></i><span>Lab Tests</span></a>
        <div class="menu-section"><span>Hospital</span></div>
        <a href="hospital_module.php?module=admissions"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'admissions' ? 'active' : ''; ?>"><i
                class="fa-solid fa-hospital-user"></i><span>Admissions</span></a>
        <a href="hospital_module.php?module=rooms_beds"
            class="<?php echo $current_page === 'hospital_module.php' && ($_GET['module'] ?? '') === 'rooms_beds' ? 'active' : ''; ?>"><i
                class="fa-solid fa-bed"></i><span>Rooms & Beds</span></a>
        <div class="menu-section"><span>Finance</span></div>
        <a href="billing.php"
            class="<?php echo $current_page === 'billing.php' && ($_GET['module'] ?? '') === 'billing' ? 'active' : ''; ?>"><i
                class="fa-solid fa-file-invoice-dollar"></i><span>Billing</span></a>
        <a href="payments.php"
            class="<?php echo $current_page === 'payments.php' && ($_GET['module'] ?? '') === 'payments' ? 'active' : ''; ?>"><i
                class="fa-solid fa-money-bill-wave"></i><span>Payments</span></a>
        <a href="patient_qr.php" class="<?php echo $current_page === 'patient_qr.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-qrcode"></i><span>Patient QR</span></a>
        <a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-chart-line"></i><span>Reports</span></a>
        <a href="settings.php" class="<?php echo $current_page === 'settings.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-gear"></i><span>Settings</span></a>
        <a href="../auth/logout.php" class="<?php echo $current_page === 'logout.php' ? 'active' : ''; ?>"><i
                class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>

</aside>
<script src="js/sidebar.js">   </script>