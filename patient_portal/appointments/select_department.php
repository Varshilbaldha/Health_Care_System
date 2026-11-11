<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| SELECT DEPARTMENT
|--------------------------------------------------------------------------
| Appointment Booking - Step 3
|
| Hospital is already selected in select_hospital.php.
|
| The selected hospital's separate database is opened
| using the database_name stored in appointment session.
|--------------------------------------------------------------------------
*/


/*====================================================
    PATIENT AUTHENTICATION
====================================================*/

require_once __DIR__ .
    '/../includes/auth_check.php';


/*====================================================
    PAGE TITLE
====================================================*/

$page_title =
    "Select Department";


/*====================================================
    CHECK BOOKING SESSION
====================================================*/

if (
    !isset(
        $_SESSION['appointment_booking']
    )
    ||
    !is_array(
        $_SESSION['appointment_booking']
    )
)
{
    $_SESSION['error'] =
        "Please select a hospital first.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    GET BOOKING SESSION
====================================================*/

$booking =
    $_SESSION['appointment_booking'];


$account_id =
    (int) (
        $booking['account_id']
        ?? 0
    );


$mapping_id =
    (int) (
        $booking['mapping_id']
        ?? 0
    );


$hospital_id =
    (int) (
        $booking['hospital_id']
        ?? 0
    );


$hospital_name =
    (string) (
        $booking['hospital_name']
        ?? ''
    );


$hospital_patient_code =
    (string) (
        $booking['hospital_patient_code']
        ?? ''
    );


$city =
    (string) (
        $booking['city']
        ?? ''
    );


$state =
    (string) (
        $booking['state']
        ?? ''
    );


$hospital_database =
    trim(
        (string) (
            $booking['database_name']
            ?? ''
        )
    );


/*====================================================
    VALIDATE SESSION DATA
====================================================*/

if (
    $account_id <= 0 ||
    $mapping_id <= 0 ||
    $hospital_id <= 0 ||
    $hospital_database === ''
)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid appointment booking session.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    VALIDATE DATABASE NAME
====================================================*/

if (
    !preg_match(
        '/^[A-Za-z0-9_]+$/',
        $hospital_database
    )
)
{
    unset(
        $_SESSION['appointment_booking']
    );

    $_SESSION['error'] =
        "Invalid hospital database configuration.";

    header(
        "Location: book.php"
    );

    exit;
}


/*====================================================
    CONNECT TO HOSPITAL DATABASE
====================================================*/

$hospital_conn =
    db_connect($hospital_database);


if (!$hospital_conn)
{
    die(
        "Hospital Database Connection Failed: " .
        mysqli_connect_error()
    );
}


mysqli_set_charset(
    $hospital_conn,
    "utf8mb4"
);


/*====================================================
    GET ACTIVE DEPARTMENTS
====================================================*/

$query = "
    SELECT
        department_id,
        department_name,
        description,
        location,
        head_doctor_id,
        status
    FROM departments
    WHERE status = 'Active'
    ORDER BY department_name ASC
";


$result =
    mysqli_query(
        $hospital_conn,
        $query
    );


if (!$result)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


/*====================================================
    STORE DEPARTMENTS
====================================================*/

$departments = [];


while (
    $row =
    mysqli_fetch_assoc($result)
)
{
    $departments[] =
        $row;
}


mysqli_free_result(
    $result
);


mysqli_close(
    $hospital_conn
);


/*====================================================
    FLASH ERROR
====================================================*/

$error =
    $_SESSION['error']
    ?? '';


unset(
    $_SESSION['error']
);


/*====================================================
    HTML ESCAPE
====================================================*/

function selectDepartmentEscape(
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
                    Select Department
                </h1>


                <p>

                    Choose a department at

                    <strong>

                        <?= selectDepartmentEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                    to continue your appointment.

                </p>

            </div>

        </div>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= selectDepartmentEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            SELECTED HOSPITAL
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Selected Hospital
                    </h2>

                    <p>
                        Your appointment will be created
                        in this hospital's system.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <div class="information-row">

                    <span>
                        Hospital
                    </span>

                    <strong>

                        <?= selectDepartmentEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Patient Code
                    </span>

                    <strong>

                        <?= selectDepartmentEscape(
                            $hospital_patient_code
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        City
                    </span>

                    <strong>

                        <?= selectDepartmentEscape(
                            $city
                        ); ?>

                    </strong>

                </div>


                <?php if ($state !== ''): ?>

                    <div class="information-row">

                        <span>
                            State
                        </span>

                        <strong>

                            <?= selectDepartmentEscape(
                                $state
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>

        </div>


        <br>


        <!--================================================
            DEPARTMENTS
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Select Department
                    </h2>

                    <p>
                        Choose the department for your
                        consultation.
                    </p>

                </div>

            </div>


            <?php if (
                count($departments) === 0
            ): ?>


                <div class="profile-message profile-error">

                    No active departments are currently
                    available at this hospital.

                </div>


            <?php else: ?>


                <div class="profile-form-grid">


                    <?php foreach (
                        $departments
                        as $department
                    ): ?>


                        <div
                            class="hospital-selection-card"
                        >


                            <div
                                class="hospital-selection-content"
                            >


                                <h3>

                                    <?= selectDepartmentEscape(
                                        (string)
                                        $department[
                                            'department_name'
                                        ]
                                    ); ?>

                                </h3>


                                <?php if (
                                    !empty(
                                        $department[
                                            'description'
                                        ]
                                    )
                                ): ?>

                                    <p>

                                        <?= selectDepartmentEscape(
                                            (string)
                                            $department[
                                                'description'
                                            ]
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <?php if (
                                    !empty(
                                        $department[
                                            'location'
                                        ]
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Location:
                                        </strong>

                                        <?= selectDepartmentEscape(
                                            (string)
                                            $department[
                                                'location'
                                            ]
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <!--================================
                                    SELECT DEPARTMENT
                                =================================-->

                                <form
                                    method="POST"
                                    action="select_doctor.php"
                                >


                                    <input
                                        type="hidden"
                                        name="department_id"
                                        value="<?= (int)
                                            $department[
                                                'department_id'
                                            ]; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="profile-save-button"
                                    >

                                        Select Department

                                    </button>


                                </form>


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