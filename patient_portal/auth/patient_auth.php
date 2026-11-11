<?php

declare(strict_types=1);


/*====================================================
    LOAD GLOBAL CONFIG
====================================================*/

require_once dirname(__DIR__, 2) . '/includes/config.php';


/*====================================================
    LOAD GLOBAL FUNCTIONS
====================================================*/

require_once dirname(__DIR__, 2) . '/includes/functions.php';


/*====================================================
    PATIENT LOGIN CHECK
====================================================*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    !isset($_SESSION['patient_auth']['logged_in']) ||
    $_SESSION['patient_auth']['logged_in'] !== true
)
{
    $_SESSION['error'] =
        "Please login first.";

    redirect("../auth/login.php");
}


/*====================================================
    PATIENT ACCOUNT ID CHECK
====================================================*/

if (
    !isset($_SESSION['patient_auth']['account_id']) ||
    (int) $_SESSION['patient_auth']['account_id'] <= 0
)
{
    $_SESSION = [];

    session_destroy();

    header(
        "Location: ../auth/login.php"
    );

    exit;
}


/*====================================================
    SESSION TIMEOUT
====================================================*/

/*
|--------------------------------------------------------------------------
| 30 Minutes
|--------------------------------------------------------------------------
*/

$timeout = 1800;


/*====================================================
    LAST ACTIVITY CHECK
====================================================*/

$last_activity =
    (int) (
        $_SESSION['patient_auth']['last_activity']
        ?? $_SESSION['patient_auth']['login_time']
        ?? time()
    );


if (
    time() - $last_activity > $timeout
)
{
    $_SESSION = [];

    if (ini_get("session.use_cookies"))
    {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    /*
    |--------------------------------------------------------------------------
    | Start a new session for error message
    |--------------------------------------------------------------------------
    */

    session_start();

    $_SESSION['error'] =
        "Your session has expired. Please login again.";

    header(
        "Location: ../auth/login.php"
    );

    exit;
}


/*====================================================
    UPDATE LAST ACTIVITY
====================================================*/

$_SESSION['patient_auth']['last_activity'] =
    time();


/*====================================================
    CURRENT PATIENT
====================================================*/

$patient =
    $_SESSION['patient_auth'];

?>