<?php

declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';



/*
|--------------------------------------------------------------------------
| SELECT DOCTOR
|--------------------------------------------------------------------------
| Appointment Booking - Step 4
|
| Previous step:
|
| select_department.php
|        ↓
| department_id
|
| The selected hospital database is already stored in:
|
| $_SESSION['appointment_booking']
|
| We connect to that hospital database and load doctors
| belonging to the selected department.
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
    "Select Doctor";


/*====================================================
    ONLY POST REQUEST
====================================================*/

if (
    $_SERVER['REQUEST_METHOD']
    !== 'POST'
)
{
    $_SESSION['error'] =
        "Invalid doctor selection request.";

    header(
        "Location: select_department.php"
    );

    exit;
}


/*====================================================
    CHECK APPOINTMENT BOOKING SESSION
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


$hospital_database =
    trim(
        (string) (
            $booking['database_name']
            ?? ''
        )
    );


/*====================================================
    VALIDATE BOOKING SESSION
====================================================*/

if (
    $account_id <= 0
    ||
    $mapping_id <= 0
    ||
    $hospital_id <= 0
    ||
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
    GET DEPARTMENT ID
====================================================*/

$department_id =
    filter_input(
        INPUT_POST,
        'department_id',
        FILTER_VALIDATE_INT
    );


if (
    $department_id === false
    ||
    $department_id === null
    ||
    $department_id <= 0
)
{
    $_SESSION['error'] =
        "Invalid department selection.";

    header(
        "Location: select_department.php"
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
    VERIFY DEPARTMENT
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
    WHERE department_id = ?
      AND status = 'Active'
    LIMIT 1
";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt)
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


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $department_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$department =
    null;


if ($result)
{
    $department =
        mysqli_fetch_assoc(
            $result
        );

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    DEPARTMENT NOT FOUND
====================================================*/

if (!$department)
{
    mysqli_close(
        $hospital_conn
    );

    $_SESSION['error'] =
        "Selected department is not available.";

    header(
        "Location: select_department.php"
    );

    exit;
}


/*====================================================
    SAVE DEPARTMENT IN BOOKING SESSION
====================================================*/

$_SESSION['appointment_booking'][
    'department_id'
] =
    (int)
    $department[
        'department_id'
    ];


$_SESSION['appointment_booking'][
    'department_name'
] =
    (string)
    $department[
        'department_name'
    ];


/*====================================================
    GET ACTIVE DOCTORS
====================================================*/

$query = "
    SELECT
        doctor_id,
        department_id,
        doctor_name,
        gender,
        date_of_birth,
        email,
        phone,
        qualification,
        specialization,
        medical_license_no,
        experience_years,
        consultation_fee,
        profile_photo,
        status
    FROM doctors
    WHERE department_id = ?
      AND status = 'Active'
    ORDER BY doctor_name ASC
";


$stmt =
    mysqli_prepare(
        $hospital_conn,
        $query
    );


if (!$stmt)
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


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $department_id
);


if (
    !mysqli_stmt_execute(
        $stmt
    )
)
{
    $database_error =
        mysqli_error(
            $hospital_conn
        );

    mysqli_stmt_close(
        $stmt
    );

    mysqli_close(
        $hospital_conn
    );

    die(
        "Hospital Database Error: " .
        $database_error
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$doctors = [];


if ($result)
{
    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    )
    {
        $doctors[] =
            $row;
    }

    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    CLOSE HOSPITAL DATABASE
====================================================*/

mysqli_close(
    $hospital_conn
);


/*====================================================
    HTML ESCAPE
====================================================*/

function selectDoctorEscape(
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
    FLASH ERROR
====================================================*/

$error =
    $_SESSION['error']
    ?? '';


unset(
    $_SESSION['error']
);


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
                    Select Doctor
                </h1>


                <p>

                    Choose a doctor from

                    <strong>

                        <?= selectDoctorEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                    for your consultation.

                </p>

            </div>

        </div>


        <!--================================================
            ERROR
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= selectDoctorEscape(
                    $error
                ); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            SELECTED INFORMATION
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Appointment Details
                    </h2>

                    <p>
                        Your selected hospital and department.
                    </p>

                </div>

            </div>


            <div class="patient-information">


                <div class="information-row">

                    <span>
                        Hospital
                    </span>


                    <strong>

                        <?= selectDoctorEscape(
                            $hospital_name
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Patient Code
                    </span>


                    <strong>

                        <?= selectDoctorEscape(
                            $hospital_patient_code
                        ); ?>

                    </strong>

                </div>


                <div class="information-row">

                    <span>
                        Department
                    </span>


                    <strong>

                        <?= selectDoctorEscape(
                            (string)
                            $department[
                                'department_name'
                            ]
                        ); ?>

                    </strong>

                </div>


                <?php if (
                    !empty(
                        $department[
                            'location'
                        ]
                    )
                ): ?>

                    <div class="information-row">

                        <span>
                            Location
                        </span>


                        <strong>

                            <?= selectDoctorEscape(
                                (string)
                                $department[
                                    'location'
                                ]
                            ); ?>

                        </strong>

                    </div>

                <?php endif; ?>


            </div>

        </div>


        <br>


        <!--================================================
            DOCTORS
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Available Doctors
                    </h2>

                    <p>
                        Select a doctor for your consultation.
                    </p>

                </div>

            </div>


            <?php if (
                count($doctors) === 0
            ): ?>


                <!--========================================
                    NO DOCTORS
                =========================================-->

                <div class="profile-message profile-error">

                    No active doctors are currently
                    available in this department.

                </div>


            <?php else: ?>


                <!--========================================
                    DOCTOR LIST
                =========================================-->

                <div class="profile-form-grid">


                    <?php foreach (
                        $doctors
                        as $doctor
                    ): ?>


                        <div
                            class="hospital-selection-card"
                        >


                            <div
                                class="hospital-selection-content"
                            >


                                <!-- Doctor Photo -->

                                <?php if (
                                    !empty(
                                        $doctor[
                                            'profile_photo'
                                        ]
                                    )
                                ): ?>

                                    <div
                                        class="profile-photo-preview"
                                    >

                                        <img
                                            src="../../<?= selectDoctorEscape(
                                                (string)
                                                $doctor[
                                                    'profile_photo'
                                                ]
                                            ); ?>"
                                            alt="Doctor Profile Photo"
                                            width="100"
                                            height="100"
                                        >

                                    </div>

                                <?php endif; ?>


                                <!-- Doctor Name -->

                                <h3>

                                    <?= selectDoctorEscape(
                                        (string)
                                        $doctor[
                                            'doctor_name'
                                        ]
                                    ); ?>

                                </h3>


                                <!-- Specialization -->

                                <?php if (
                                    !empty(
                                        $doctor[
                                            'specialization'
                                        ]
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Specialization:
                                        </strong>

                                        <?= selectDoctorEscape(
                                            (string)
                                            $doctor[
                                                'specialization'
                                            ]
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <!-- Qualification -->

                                <?php if (
                                    !empty(
                                        $doctor[
                                            'qualification'
                                        ]
                                    )
                                ): ?>

                                    <p>

                                        <strong>
                                            Qualification:
                                        </strong>

                                        <?= selectDoctorEscape(
                                            (string)
                                            $doctor[
                                                'qualification'
                                            ]
                                        ); ?>

                                    </p>

                                <?php endif; ?>


                                <!-- Experience -->

                                <p>

                                    <strong>
                                        Experience:
                                    </strong>

                                    <?= (int)
                                        (
                                            $doctor[
                                                'experience_years'
                                            ]
                                            ?? 0
                                        ); ?>

                                    years

                                </p>


                                <!-- Consultation Fee -->

                                <p>

                                    <strong>
                                        Consultation Fee:
                                    </strong>

                                    ₹<?= number_format(
                                        (float)
                                        (
                                            $doctor[
                                                'consultation_fee'
                                            ]
                                            ?? 0
                                        ),
                                        2
                                    ); ?>

                                </p>


                                <!-- Select Doctor -->

                                <form
                                    method="POST"
                                    action="select_date.php"
                                >


                                    <input
                                        type="hidden"
                                        name="doctor_id"
                                        value="<?= (int)
                                            $doctor[
                                                'doctor_id'
                                            ]; ?>"
                                    >


                                    <button
                                        type="submit"
                                        class="profile-save-button"
                                    >

                                        Select Doctor

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