<?php

declare(strict_types=1);


/*====================================================
    GLOBAL CONFIG
====================================================*/

require_once dirname(__DIR__, 2)
    . DIRECTORY_SEPARATOR
    . 'includes'
    . DIRECTORY_SEPARATOR
    . 'config.php';


/*====================================================
    CHECK LOGIN SESSION
====================================================*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {

    $_SESSION['error'] =
        "Please login first.";

    header(
        "Location: /Hospital_Management_System/mobile/login.php"
    );

    exit;
}


/*====================================================
    SESSION TIMEOUT
====================================================*/

$timeout = 1800; // 30 minutes


$last_activity =
    (int) (
        $_SESSION['patient_auth']['last_activity']
        ?? 0
    );


if (
    $last_activity <= 0 ||
    (time() - $last_activity) > $timeout
) {

    session_unset();

    session_destroy();

    header(
        "Location: /Hospital_Management_System/patient_portal/auth/login.php"
    );

    exit;
}


/*====================================================
    UPDATE LAST ACTIVITY
====================================================*/

$_SESSION['patient_auth']['last_activity'] =
    time();


/*====================================================
    MAKE PATIENT DATA AVAILABLE
====================================================*/

$patient =
    $_SESSION['patient_auth'];

?>