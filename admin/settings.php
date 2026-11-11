<?php
// require 'auth.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Settings</title>

    <link rel="stylesheet" href="css/sidebar.css">

    <link rel="stylesheet" href="css/settings.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

</head>

<body>

<div class="dashboard">

    <?php require 'sidebar.php'; ?>


    <main class="main-content">


        <!-- ==========================
             Page Header
        =========================== -->

        <div class="page-header">

            <div>

                <h1>Settings</h1>

                <p>
                    Manage your hospital system settings.
                </p>

            </div>

        </div>


        <!-- ==========================
             Hospital Information
        =========================== -->

        <div class="settings-card">

            <div class="settings-card-header">

                <div>

                    <h2>Hospital Information</h2>

                    <p>
                        Update your hospital's basic information.
                    </p>

                </div>

                <div class="settings-header-icon">

                    <i class="fa-solid fa-hospital"></i>

                </div>

            </div>


            <form id="hospitalForm">

                <div class="form-grid">


                    <div class="form-group">

                        <label for="hospitalName">
                            Hospital Name
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-hospital"></i>

                            <input
                                type="text"
                                id="hospitalName"
                                name="hospital_name"
                                value="City Care Hospital"
                                placeholder="Enter hospital name">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="hospitalEmail">
                            Hospital Email
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-envelope"></i>

                            <input
                                type="email"
                                id="hospitalEmail"
                                name="hospital_email"
                                value="info@citycarehospital.com"
                                placeholder="Enter hospital email">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="hospitalPhone">
                            Phone Number
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-phone"></i>

                            <input
                                type="tel"
                                id="hospitalPhone"
                                name="hospital_phone"
                                value="+91 9876543210"
                                placeholder="Enter phone number">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="hospitalCity">
                            City
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-city"></i>

                            <input
                                type="text"
                                id="hospitalCity"
                                name="hospital_city"
                                value="Ahmedabad"
                                placeholder="Enter city">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="hospitalState">
                            State
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-map"></i>

                            <input
                                type="text"
                                id="hospitalState"
                                name="hospital_state"
                                value="Gujarat"
                                placeholder="Enter state">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="hospitalPincode">
                            Pincode
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-location-dot"></i>

                            <input
                                type="text"
                                id="hospitalPincode"
                                name="hospital_pincode"
                                value="380001"
                                placeholder="Enter pincode">

                        </div>

                    </div>


                    <div class="form-group full-width">

                        <label for="hospitalAddress">
                            Hospital Address
                        </label>

                        <div class="input-box textarea-box">

                            <i class="fa-solid fa-location-dot"></i>

                            <textarea
                                id="hospitalAddress"
                                name="hospital_address"
                                placeholder="Enter hospital address">123 Health Avenue, Ahmedabad</textarea>

                        </div>

                    </div>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="save-btn">

                        <i class="fa-solid fa-check"></i>

                        Save Changes

                    </button>

                </div>

            </form>

        </div>



        <!-- ==========================
             Admin + Password
        =========================== -->

        <div class="settings-grid">


            <!-- Admin Profile -->

            <div class="settings-card">

                <div class="settings-card-header">

                    <div>

                        <h2>Admin Profile</h2>

                        <p>
                            Manage administrator account information.
                        </p>

                    </div>

                    <div class="settings-header-icon">

                        <i class="fa-solid fa-user-shield"></i>

                    </div>

                </div>


                <form id="adminProfileForm">


                    <div class="form-group">

                        <label for="adminName">
                            Full Name
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-user"></i>

                            <input
                                type="text"
                                id="adminName"
                                name="admin_name"
                                value="Administrator">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="adminEmail">
                            Email Address
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-envelope"></i>

                            <input
                                type="email"
                                id="adminEmail"
                                name="admin_email"
                                value="admin@hospital.com">

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="adminRole">
                            Role
                        </label>

                        <div class="input-box">

                            <i class="fa-solid fa-user-shield"></i>

                            <input
                                type="text"
                                id="adminRole"
                                value="Administrator"
                                readonly>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="save-btn">

                            <i class="fa-solid fa-check"></i>

                            Update Profile

                        </button>

                    </div>

                </form>

            </div>



            <!-- Change Password -->

            <div class="settings-card">

                <div class="settings-card-header">

                    <div>

                        <h2>Change Password</h2>

                        <p>
                            Update your administrator password.
                        </p>

                    </div>

                    <div class="settings-header-icon">

                        <i class="fa-solid fa-lock"></i>

                    </div>

                </div>


                <form id="passwordForm">


                    <div class="form-group">

                        <label for="currentPassword">
                            Current Password
                        </label>

                        <div class="input-box password-box">

                            <i class="fa-solid fa-lock"></i>

                            <input
                                type="password"
                                id="currentPassword"
                                name="current_password"
                                placeholder="Enter current password">

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="currentPassword">

                                <i class="fa-solid fa-eye"></i>

                            </button>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="newPassword">
                            New Password
                        </label>

                        <div class="input-box password-box">

                            <i class="fa-solid fa-key"></i>

                            <input
                                type="password"
                                id="newPassword"
                                name="new_password"
                                placeholder="Enter new password">

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="newPassword">

                                <i class="fa-solid fa-eye"></i>

                            </button>

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="confirmPassword">
                            Confirm Password
                        </label>

                        <div class="input-box password-box">

                            <i class="fa-solid fa-key"></i>

                            <input
                                type="password"
                                id="confirmPassword"
                                name="confirm_password"
                                placeholder="Confirm new password">

                            <button
                                type="button"
                                class="password-toggle"
                                data-target="confirmPassword">

                                <i class="fa-solid fa-eye"></i>

                            </button>

                        </div>

                    </div>


                    <div class="form-actions">

                        <button
                            type="submit"
                            class="save-btn">

                            <i class="fa-solid fa-shield-halved"></i>

                            Update Password

                        </button>

                    </div>

                </form>

            </div>

        </div>



        <!-- ==========================
             System Settings
        =========================== -->

        <div class="settings-card">

            <div class="settings-card-header">

                <div>

                    <h2>System Settings</h2>

                    <p>
                        Manage notifications and hospital system preferences.
                    </p>

                </div>

                <div class="settings-header-icon">

                    <i class="fa-solid fa-sliders"></i>

                </div>

            </div>


            <div class="system-settings">


                <div class="setting-item">

                    <div class="setting-info">

                        <div class="setting-icon email">

                            <i class="fa-solid fa-envelope"></i>

                        </div>

                        <div>

                            <h4>Email Notifications</h4>

                            <p>
                                Receive important hospital updates through email.
                            </p>

                        </div>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            id="emailNotifications"
                            checked>

                        <span class="slider"></span>

                    </label>

                </div>


                <div class="setting-item">

                    <div class="setting-info">

                        <div class="setting-icon appointment">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>

                        <div>

                            <h4>Appointment Notifications</h4>

                            <p>
                                Get notified about new and updated appointments.
                            </p>

                        </div>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            id="appointmentNotifications"
                            checked>

                        <span class="slider"></span>

                    </label>

                </div>


                <div class="setting-item">

                    <div class="setting-info">

                        <div class="setting-icon patient">

                            <i class="fa-solid fa-user-injured"></i>

                        </div>

                        <div>

                            <h4>Patient Notifications</h4>

                            <p>
                                Receive notifications about patient activities.
                            </p>

                        </div>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            id="patientNotifications">

                        <span class="slider"></span>

                    </label>

                </div>


                <div class="setting-item">

                    <div class="setting-info">

                        <div class="setting-icon staff">

                            <i class="fa-solid fa-users"></i>

                        </div>

                        <div>

                            <h4>Staff Notifications</h4>

                            <p>
                                Receive updates about hospital staff activities.
                            </p>

                        </div>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            id="staffNotifications"
                            checked>

                        <span class="slider"></span>

                    </label>

                </div>


                <div class="setting-item">

                    <div class="setting-info">

                        <div class="setting-icon backup">

                            <i class="fa-solid fa-database"></i>

                        </div>

                        <div>

                            <h4>Automatic Backup</h4>

                            <p>
                                Automatically backup hospital data regularly.
                            </p>

                        </div>

                    </div>


                    <label class="switch">

                        <input
                            type="checkbox"
                            id="automaticBackup"
                            checked>

                        <span class="slider"></span>

                    </label>

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="save-btn"
                    id="saveSystemSettings">

                    <i class="fa-solid fa-check"></i>

                    Save Settings

                </button>

            </div>

        </div>


    </main>

</div>


<script src="js/settings.js"></script>

</body>

</html>