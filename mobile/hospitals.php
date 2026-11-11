<?php
declare(strict_types=1);

$page_title = "Find Hospitals";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Find Hospitals</title>

    <link
        rel="stylesheet"
        href="css/hospitals.css"
    >
<link rel="stylesheet" href="css/common.css">
</head>

<body>

<div class="mobile-app">

    <!-- HEADER -->
    <header class="mobile-header">

        <button
            class="back-btn"
            onclick="history.back()"
        >
            ←
        </button>

        <div>
            <span class="header-small">
                SMART HOSPITAL
            </span>

            <h1>
                Find Hospitals
            </h1>
        </div>

    </header>


    <!-- SEARCH -->
    <section class="search-section">

        <div class="search-box">

            <span>🔎</span>

            <input
                type="text"
                id="hospitalSearch"
                placeholder="Search hospital or city..."
                autocomplete="off"
            >

        </div>


        <button
            id="locationBtn"
            class="location-btn"
        >
            📍 Find Hospitals Near Me
        </button>

    </section>


    <!-- STATUS -->
    <div
        id="searchStatus"
        class="search-status"
    >
        Search for a hospital or use your location.
    </div>


    <!-- RESULTS -->
    <main id="hospitalResults">

        <div class="empty-state">

            <div class="empty-icon">
                🏥
            </div>

            <h2>
                Find a Hospital
            </h2>

            <p>
                Search by hospital name or city,
                or find hospitals near your location.
            </p>

        </div>

    </main>


    <!-- BOTTOM NAV -->
    <nav class="bottom-navigation">


        <a
            href="dashboard.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <path d="m3 11 9-8 9 8"/>
                <path d="M5 10v10h14V10"/>
            </svg>

            <small>Home</small>

        </a>


        <a
            href="my_appointments.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <rect x="4" y="5" width="16" height="15" rx="3"/>
                <path d="M8 3v4"/>
                <path d="M16 3v4"/>
                <path d="M4 10h16"/>
            </svg>

            <small>Appointments</small>

        </a>


        <a
            href="book.php"
            class="nav-link book-link"
        >

            <span class="book-button">
                +
            </span>

            <small>Book</small>

        </a>


        <a
            href="hospital_search.php"
            class="nav-link active"
        >

            <svg viewBox="0 0 24 24">
                <path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/>
                <circle cx="12" cy="9" r="2.5"/>
            </svg>

            <small>Hospitals</small>

        </a>


        <a
            href="profile.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>

            <small>Profile</small>

        </a>


    </nav>

</div>

<script src="js/hospitals.js"></script>

</body>
</html>