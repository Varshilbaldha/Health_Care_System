<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>CarePlus | Smart Hospital Management System</title>

    <link rel="stylesheet" href="assets/css/index.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

</head>

<header>

    <nav class="navbar">

        <!-- Logo -->
        <div class="logo">
            <i class="fa-solid fa-heart-pulse"></i>
            <span>CarePlus</span>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav-links">

            <li><a href="#">Home</a></li>

            <li><a href="#about">About</a></li>

            <li><a href="#features">Features</a></li>

            <li><a href="#hospitals">Hospitals</a></li>

            <li><a href="#services">Services</a></li>

            <li><a href="#contact">Contact</a></li>

        </ul>

        <!-- Login Buttons -->
        <div class="nav-buttons">

            <a href="auth/hospital_registration.php" class="hospital-btn">
                Register Your Hospital
            </a>

        </div>

    </nav>

</header>
<body>


    <div class="hero-section">

        <h1>
            Your Healthcare, Connected in One Place
        </h1>

        <p>
            Access healthcare services or manage your hospital
            through one smart platform.
        </p>

    </div>
    

    <div class="portals-container">

        <div class="portal-card">

            <div class="icon">

                <i class="fa-solid fa-user-shield"></i>

            </div>

            <h2>Patient Portal</h2>

            <p>
                Search hospitals, find doctors, book appointments,
                and securely access your medical records through
                one centralized healthcare platform.
            </p>&

            <a href="qr_mobile_app.html"
               class="portal-btn btn-primary">

                Patient Login

            </a>

        </div>


       

        <div class="portal-card">

            <div class="icon">

                <i class="fa-solid fa-hospital-user"></i>

            </div>

            <h2>Hospital Portal</h2>

            <p>
                Manage your hospital, departments, doctors, staff,
                patients, appointments, and healthcare services
                through a smart management dashboard.
            </p>

            <a href="auth/login.php"
               class="portal-btn btn-secondary">

                Hospital Login

            </a>

        </div>


    </div>


    <footer>

        &copy; 2026 CarePlus Smart Hospital Management System.

    </footer>


</body>

</html> 