<?php

declare(strict_types=1);

session_start();


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['patient_auth']) ||
    !is_array($_SESSION['patient_auth']) ||
    ($_SESSION['patient_auth']['logged_in'] ?? false) !== true
) {

    header('Location: login.php');

    exit;

}


/*
|--------------------------------------------------------------------------
| PATIENT DATA
|--------------------------------------------------------------------------
*/

$patient =
    $_SESSION['patient_auth'];


$first_name =
    trim(
        (string)(
            $patient['first_name']
            ?? 'Patient'
        )
    );


$last_name =
    trim(
        (string)(
            $patient['last_name']
            ?? ''
        )
    );


$full_name =
    trim(
        $first_name
        . ' '
        . $last_name
    );


if (
    $full_name === ''
) {

    $full_name =
        'Patient';

}


/*
|--------------------------------------------------------------------------
| ACCOUNT ID
|--------------------------------------------------------------------------
*/

$account_id =
    (int)(
        $patient['account_id']
        ?? 0
    );


/*
|--------------------------------------------------------------------------
| ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function notificationEscape(
    string $value
): string {

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );

}


/*
|--------------------------------------------------------------------------
| INITIALIZE READ NOTIFICATIONS SESSION
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION['read_notifications']
    )
    ||
    !is_array(
        $_SESSION['read_notifications']
    )
) {

    $_SESSION['read_notifications'] =
        [];

}


/*
|--------------------------------------------------------------------------
| MARK SINGLE NOTIFICATION AS READ
|--------------------------------------------------------------------------
|
| AJAX request from the same page.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset(
        $_POST['action']
    )
) {


    header(
        'Content-Type: application/json; charset=UTF-8'
    );


    $action =
        (string)(
            $_POST['action']
            ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | SINGLE NOTIFICATION
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'mark_read'
    ) {


        $notificationId =
            (int)(
                $_POST['notification_id']
                ?? 0
            );


        if (
            $notificationId <= 0
        ) {

            echo json_encode(
                [
                    'success' => false,
                    'message' =>
                        'Invalid notification.'
                ],
                JSON_UNESCAPED_UNICODE
            );

            exit;

        }


        /*
        |--------------------------------------------------------------------------
        | SAVE READ STATUS
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $notificationId,
                $_SESSION['read_notifications'],
                true
            )
        ) {

            $_SESSION['read_notifications'][] =
                $notificationId;

        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        echo json_encode(
            [
                'success' => true,
                'notification_id' =>
                    $notificationId
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | MARK ALL AS READ
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'mark_all_read'
    ) {


        /*
        |--------------------------------------------------------------------------
        | SPECIAL SESSION FLAG
        |--------------------------------------------------------------------------
        */

        $_SESSION['all_notifications_read'] =
            true;


        echo json_encode(
            [
                'success' => true
            ],
            JSON_UNESCAPED_UNICODE
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | UNKNOWN ACTION
    |--------------------------------------------------------------------------
    */

    echo json_encode(
        [
            'success' => false,
            'message' =>
                'Unknown notification action.'
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;

}


/*
|--------------------------------------------------------------------------
| DEMO / DEFAULT NOTIFICATIONS
|--------------------------------------------------------------------------
|
| These can later be replaced by database notifications.
|
|--------------------------------------------------------------------------
*/

$notifications = [

    [

        'id' => 1,

        'type' =>
            'appointment',

        'icon' =>
            '📅',

        'title' =>
            'Appointment Reminder',

        'message' =>
            'Remember to check your upcoming appointments and arrive on time.',

        'time' =>
            'Today',

        'date' =>
            'Today',

        'unread' =>
            true

    ],


    [

        'id' => 2,

        'type' =>
            'ai',

        'icon' =>
            '🤖',

        'title' =>
            'AI Health Assistant',

        'message' =>
            'Your Smart Hospital AI Assistant is available to help with health-related questions.',

        'time' =>
            'Today',

        'date' =>
            'Today',

        'unread' =>
            true

    ],


    [

        'id' => 3,

        'type' =>
            'hospital',

        'icon' =>
            '🏥',

        'title' =>
            'Hospital Services',

        'message' =>
            'You can search hospitals, doctors and available healthcare services from your dashboard.',

        'time' =>
            'Yesterday',

        'date' =>
            'Yesterday',

        'unread' =>
            false

    ],


    [

        'id' => 4,

        'type' =>
            'profile',

        'icon' =>
            '👤',

        'title' =>
            'Profile Information',

        'message' =>
            'Keep your patient profile information up to date for a better hospital experience.',

        'time' =>
            '2 days ago',

        'date' =>
            '2 days ago',

        'unread' =>
            false

    ],


    [

        'id' => 5,

        'type' =>
            'health',

        'icon' =>
            '🩺',

        'title' =>
            'Health Reminder',

        'message' =>
            'Use the AI Medical Image Analyzer when you need informational assistance with supported medical images.',

        'time' =>
            '3 days ago',

        'date' =>
            '3 days ago',

        'unread' =>
            false

    ]

];


/*
|--------------------------------------------------------------------------
| CHECK ALL READ FLAG
|--------------------------------------------------------------------------
*/

$allNotificationsRead =
    (
        $_SESSION['all_notifications_read']
        ?? false
    ) === true;


/*
|--------------------------------------------------------------------------
| APPLY SAVED READ STATUS
|--------------------------------------------------------------------------
*/

foreach (
    $notifications as &$notification
) {


    $notificationId =
        (int)$notification['id'];


    /*
    |--------------------------------------------------------------------------
    | IF ALL ARE READ
    |--------------------------------------------------------------------------
    */

    if (
        $allNotificationsRead
    ) {

        $notification['unread'] =
            false;

    }


    /*
    |--------------------------------------------------------------------------
    | IF THIS SPECIFIC NOTIFICATION IS READ
    |--------------------------------------------------------------------------
    */

    elseif (
        in_array(
            $notificationId,
            $_SESSION['read_notifications'],
            true
        )
    ) {

        $notification['unread'] =
            false;

    }

}

unset(
    $notification
);


/*
|--------------------------------------------------------------------------
| COUNT UNREAD
|--------------------------------------------------------------------------
*/

$unreadCount =
    0;


foreach (
    $notifications as $notification
) {


    if (
        (
            $notification['unread']
            ?? false
        ) === true
    ) {

        $unreadCount++;

    }

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
        content="#6355f4"
    >


    <title>
        Notifications
    </title>


    <link
        rel="stylesheet"
        href="css/notifications.css"
    >

<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="notification-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="notification-header">


        <div class="header-left">


            <button
                type="button"
                class="back-button"
                onclick="goBackDashboard()"
                aria-label="Back to Dashboard"
            >

                ←

            </button>


            <div class="header-title-area">


                <span class="header-small-title">

                    SMART HOSPITAL

                </span>


                <h1>

                    Notifications

                </h1>


            </div>


        </div>


        <div class="header-bell">


            🔔


            <?php if (
                $unreadCount > 0
            ): ?>

                <span
                    class="header-badge"
                >

                    <?= $unreadCount; ?>

                </span>

            <?php endif; ?>


        </div>


    </header>



    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="notification-content">


        <!-- =================================================
             SUMMARY
        ================================================== -->

        <section class="notification-summary">


            <div class="summary-icon">

                🔔

            </div>


            <div class="summary-content">


                <small>

                    YOUR UPDATES

                </small>


                <h2>

                    Stay Updated

                </h2>


                <p>

                    Important updates about your
                    appointments, hospital services
                    and Smart Hospital features.

                </p>


            </div>


        </section>



        <!-- =================================================
             ACTION BAR
        ================================================== -->

        <section class="notification-actions">


            <div>


                <span class="notification-count">

                    <?= count($notifications); ?>

                    Notifications

                </span>


                <?php if (
                    $unreadCount > 0
                ): ?>


                    <span class="unread-count">

                        <?= $unreadCount; ?>

                        Unread

                    </span>


                <?php endif; ?>


            </div>


            <button
                type="button"
                id="markAllButton"
                class="mark-all-button"
                onclick="markAllNotifications()"
                <?= $unreadCount === 0
                    ? 'disabled'
                    : ''; ?>
            >

                <?php if (
                    $unreadCount === 0
                ): ?>

                    ✓ All notifications read

                <?php else: ?>

                    ✓ Mark all as read

                <?php endif; ?>

            </button>


        </section>



        <!-- =================================================
             NOTIFICATION LIST
        ================================================== -->

        <section
            class="notification-list"
            id="notificationList"
        >


            <?php if (
                empty($notifications)
            ): ?>


                <!-- =================================================
                     EMPTY STATE
                ================================================== -->

                <div class="empty-notifications">


                    <div class="empty-icon">

                        🔔

                    </div>


                    <h2>

                        No Notifications

                    </h2>


                    <p>

                        You don't have any notifications
                        right now.

                    </p>


                    <button
                        type="button"
                        onclick="goBackDashboard()"
                    >

                        ← Back to Dashboard

                    </button>


                </div>


            <?php else: ?>


                <?php foreach (
                    $notifications
                    as $notification
                ): ?>


                    <article
                        class="
                            notification-card
                            <?= notificationEscape(
                                (string)$notification['type']
                            ); ?>

                            <?=
                                (
                                    (
                                        $notification['unread']
                                        ?? false
                                    )
                                    === true
                                )
                                ? ' unread'
                                : '';
                            ?>
                        "
                        data-notification-id="
                            <?= (int)$notification['id']; ?>
                        "
                    >


                        <!-- =================================================
                             ICON
                        ================================================== -->

                        <div class="notification-icon">


                            <span>

                                <?= notificationEscape(
                                    (string)$notification['icon']
                                ); ?>

                            </span>


                        </div>



                        <!-- =================================================
                             CONTENT
                        ================================================== -->

                        <div class="notification-main">


                            <div class="notification-top">


                                <div>


                                    <h3>

                                        <?= notificationEscape(
                                            (string)$notification['title']
                                        ); ?>

                                    </h3>


                                    <?php if (
                                        (
                                            $notification['unread']
                                            ?? false
                                        )
                                        === true
                                    ): ?>


                                        <span
                                            class="unread-dot"
                                        ></span>


                                    <?php endif; ?>


                                </div>


                                <span class="notification-time">

                                    <?= notificationEscape(
                                        (string)$notification['time']
                                    ); ?>

                                </span>


                            </div>


                            <p class="notification-message">

                                <?= notificationEscape(
                                    (string)$notification['message']
                                ); ?>

                            </p>


                            <div class="notification-footer">


                                <span
                                    class="notification-category"
                                >

                                    <?php


                                    $categoryName =
                                        match (
                                            $notification['type']
                                        ) {


                                            'appointment'
                                                =>
                                                'Appointment',


                                            'ai'
                                                =>
                                                'AI Assistant',


                                            'hospital'
                                                =>
                                                'Hospital',


                                            'profile'
                                                =>
                                                'Profile',


                                            'health'
                                                =>
                                                'Health',


                                            default
                                                =>
                                                'General'

                                        };


                                    ?>


                                    <?= notificationEscape(
                                        $categoryName
                                    ); ?>


                                </span>



                                <?php if (
                                    (
                                        $notification['unread']
                                        ?? false
                                    )
                                    === true
                                ): ?>


                                    <button
                                        type="button"
                                        class="read-button"
                                        onclick="markNotificationRead(this)"
                                    >

                                        ✓ Mark as read

                                    </button>


                                <?php else: ?>


                                    <span class="read-label">

                                        ✓ Read

                                    </span>


                                <?php endif; ?>


                            </div>


                        </div>


                    </article>


                <?php endforeach; ?>


            <?php endif; ?>


        </section>



        <!-- =================================================
             INFORMATION CARD
        ================================================== -->

        <section class="notification-info">


            <div class="info-icon">

                💡

            </div>


            <div>


                <strong>

                    Notification Center

                </strong>


                <p>

                    You'll find important updates here
                    about your Smart Hospital account,
                    appointments and healthcare services.

                </p>


            </div>


        </section>



        <!-- =================================================
             SAFETY CARD
        ================================================== -->

        <section class="notification-safety">


            <div class="safety-icon">

                🛡️

            </div>


            <div>


                <strong>

                    Health Information Notice

                </strong>


                <p>

                    Notifications are for informational
                    purposes. For urgent medical concerns,
                    contact an appropriate healthcare
                    professional or emergency service.

                </p>


            </div>


        </section>


        <div class="bottom-space"></div>


    </main>



    <!-- =====================================================
         BOTTOM NAVIGATION
    ====================================================== -->

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
            class="nav-link"
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



<script>


/*
|--------------------------------------------------------------------------
| BACK TO DASHBOARD
|--------------------------------------------------------------------------
*/

function goBackDashboard()
{

    window.location.href =
        "dashboard.php";

}


/*
|--------------------------------------------------------------------------
| MARK SINGLE NOTIFICATION AS READ
|--------------------------------------------------------------------------
*/

async function markNotificationRead(
    button
)
{

    const card =
        button.closest(
            ".notification-card"
        );


    if (!card)
    {
        return;
    }


    const notificationId =
        card.dataset.notificationId;


    if (!notificationId)
    {
        return;
    }


    button.disabled =
        true;


    try
    {

        const formData =
            new FormData();


        formData.append(
            "action",
            "mark_read"
        );


        formData.append(
            "notification_id",
            notificationId
        );


        const response =
            await fetch(
                "notifications.php",
                {
                    method: "POST",
                    body: formData,
                    headers: {
                        "Accept":
                            "application/json"
                    }
                }
            );


        const data =
            await response.json();


        if (
            data.success === true
        )
        {


            /*
            |--------------------------------------------------------------------------
            | REMOVE UNREAD STYLE
            |--------------------------------------------------------------------------
            */

            card.classList.remove(
                "unread"
            );


            /*
            |--------------------------------------------------------------------------
            | REMOVE DOT
            |--------------------------------------------------------------------------
            */

            const unreadDot =
                card.querySelector(
                    ".unread-dot"
                );


            if (unreadDot)
            {
                unreadDot.remove();
            }


            /*
            |--------------------------------------------------------------------------
            | CHANGE BUTTON TO READ
            |--------------------------------------------------------------------------
            */

            button.outerHTML =
                '<span class="read-label">✓ Read</span>';


            /*
            |--------------------------------------------------------------------------
            | UPDATE COUNTS
            |--------------------------------------------------------------------------
            */

            updateUnreadCount();

        }
        else
        {

            alert(
                data.message
                ||
                "Unable to mark notification as read."
            );


            button.disabled =
                false;

        }

    }
    catch (error)
    {

        console.error(
            "Notification Error:",
            error
        );


        alert(
            "Unable to update notification."
        );


        button.disabled =
            false;

    }

}


/*
|--------------------------------------------------------------------------
| MARK ALL AS READ
|--------------------------------------------------------------------------
*/

async function markAllNotifications()
{

    const markAllButton =
        document.getElementById(
            "markAllButton"
        );


    if (!markAllButton)
    {
        return;
    }


    markAllButton.disabled =
        true;


    try
    {

        const formData =
            new FormData();


        formData.append(
            "action",
            "mark_all_read"
        );


        const response =
            await fetch(
                "notifications.php",
                {
                    method: "POST",
                    body: formData,
                    headers: {
                        "Accept":
                            "application/json"
                    }
                }
            );


        const data =
            await response.json();


        if (
            data.success === true
        )
        {


            /*
            |--------------------------------------------------------------------------
            | REMOVE UNREAD FROM ALL CARDS
            |--------------------------------------------------------------------------
            */

            const cards =
                document.querySelectorAll(
                    ".notification-card.unread"
                );


            cards.forEach(
                function(card)
                {


                    card.classList.remove(
                        "unread"
                    );


                    const dot =
                        card.querySelector(
                            ".unread-dot"
                        );


                    if (dot)
                    {
                        dot.remove();
                    }


                    const button =
                        card.querySelector(
                            ".read-button"
                        );


                    if (button)
                    {

                        button.outerHTML =
                            '<span class="read-label">✓ Read</span>';

                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE COUNTS
            |--------------------------------------------------------------------------
            */

            updateUnreadCount();

        }
        else
        {

            alert(
                data.message
                ||
                "Unable to mark notifications as read."
            );


            markAllButton.disabled =
                false;

        }

    }
    catch (error)
    {

        console.error(
            "Notification Error:",
            error
        );


        alert(
            "Unable to update notifications."
        );


        markAllButton.disabled =
            false;

    }

}


/*
|--------------------------------------------------------------------------
| UPDATE UNREAD COUNT
|--------------------------------------------------------------------------
*/

function updateUnreadCount()
{

    const unreadCards =
        document.querySelectorAll(
            ".notification-card.unread"
        );


    const count =
        unreadCards.length;


    /*
    |--------------------------------------------------------------------------
    | ACTION BAR UNREAD COUNT
    |--------------------------------------------------------------------------
    */

    let unreadCount =
        document.querySelector(
            ".unread-count"
        );


    if (
        count > 0
    )
    {


        if (!unreadCount)
        {


            const actionArea =
                document.querySelector(
                    ".notification-actions > div"
                );


            if (actionArea)
            {

                unreadCount =
                    document.createElement(
                        "span"
                    );


                unreadCount.className =
                    "unread-count";


                actionArea.appendChild(
                    unreadCount
                );

            }

        }


        if (unreadCount)
        {

            unreadCount.textContent =
                count + " Unread";

        }

    }
    else
    {


        if (unreadCount)
        {
            unreadCount.remove();
        }

    }


    /*
    |--------------------------------------------------------------------------
    | HEADER BADGE
    |--------------------------------------------------------------------------
    */

    let headerBadge =
        document.querySelector(
            ".header-badge"
        );


    if (
        count > 0
    )
    {


        if (!headerBadge)
        {


            const bell =
                document.querySelector(
                    ".header-bell"
                );


            if (bell)
            {

                headerBadge =
                    document.createElement(
                        "span"
                    );


                headerBadge.className =
                    "header-badge";


                bell.appendChild(
                    headerBadge
                );

            }

        }


        if (headerBadge)
        {

            headerBadge.textContent =
                count;

        }

    }
    else
    {


        if (headerBadge)
        {
            headerBadge.remove();
        }

    }


    /*
    |--------------------------------------------------------------------------
    | MARK ALL BUTTON
    |--------------------------------------------------------------------------
    */

    const markAllButton =
        document.getElementById(
            "markAllButton"
        );


    if (markAllButton)
    {


        if (
            count === 0
        )
        {

            markAllButton.disabled =
                true;


            markAllButton.textContent =
                "✓ All notifications read";

        }
        else
        {

            markAllButton.disabled =
                false;


            markAllButton.textContent =
                "✓ Mark all as read";

        }

    }

}

</script>


</body>

</html>