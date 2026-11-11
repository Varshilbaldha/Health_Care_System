<?php

declare(strict_types=1);




require_once __DIR__ . '/../includes/auth_check.php';



$page_title = "My Profile";



$account_id =
    (int) (
        $_SESSION['patient_auth']['account_id']
        ?? 0
    );


if ($account_id <= 0)
{
    $_SESSION['error'] =
        "Invalid patient session.";
    redirect(
        "/Hospital_Management_System/patient_portal/auth/login.php"
);
    }



$first_name =
    (string) (
        $_SESSION['patient_auth']['first_name']
        ?? ''
    );

$last_name =
    (string) (
        $_SESSION['patient_auth']['last_name']
        ?? ''
    );

$email =
    (string) (
        $_SESSION['patient_auth']['email']
        ?? ''
    );

$mobile =
    (string) (
        $_SESSION['patient_auth']['mobile']
        ?? ''
    );

$patient_uuid =
    (string) (
        $_SESSION['patient_auth']['patient_uuid']
        ?? ''
    );


/*====================================================
    DEFAULT PROFILE DATA
====================================================*/

$profile = [

    'gender' =>
        '',

    'date_of_birth' =>
        '',

    'blood_group' =>
        '',

    'marital_status' =>
        '',

    'profile_photo' =>
        '',

    'address_line_1' =>
        '',

    'address_line_2' =>
        '',

    'city' =>
        '',

    'state' =>
        '',

    'country' =>
        'India',

    'postal_code' =>
        '',

    'emergency_contact_name' =>
        '',

    'emergency_contact_mobile' =>
        '',

    'emergency_relationship' =>
        '',

    'insurance_provider' =>
        '',

    'insurance_number' =>
        '',

    'allergies' =>
        ''

];

$query = "
    SELECT
        gender,
        date_of_birth,
        blood_group,
        marital_status,
        profile_photo,
        address_line_1,
        address_line_2,
        city,
        state,
        country,
        postal_code,
        emergency_contact_name,
        emergency_contact_mobile,
        emergency_relationship,
        insurance_provider,
        insurance_number,
        allergies
    FROM patient_profiles
    WHERE account_id = ?
    LIMIT 1
";


$stmt =
    mysqli_prepare(
        $conn,
        $query
    );


if (!$stmt)
{
    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


mysqli_stmt_bind_param(
    $stmt,
    "i",
    $account_id
);


if (!mysqli_stmt_execute($stmt))
{
    mysqli_stmt_close($stmt);

    die(
        "Database Error: " .
        mysqli_error($conn)
    );
}


$result =
    mysqli_stmt_get_result($stmt);


if (
    $result &&
    mysqli_num_rows($result) > 0
)
{
    $database_profile =
        mysqli_fetch_assoc($result);


    foreach (
        $profile as $key => $value
    )
    {
        if (
            isset($database_profile[$key])
        )
        {
            $profile[$key] =
                $database_profile[$key];
        }
    }
}


if ($result)
{
    mysqli_free_result($result);
}


mysqli_stmt_close($stmt);


/*====================================================
    FLASH MESSAGES
====================================================*/

$error =
    $_SESSION['error'] ?? '';

$success =
    $_SESSION['success'] ?? '';

unset($_SESSION['error']);

unset($_SESSION['success']);


/*====================================================
    HTML ESCAPE
====================================================*/

function profileEscape(
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


<!--====================================================
    PROFILE PAGE
====================================================-->

<div class="patient-dashboard">


    <!--================================================
        PROFILE CSS
    =================================================-->

    <link
        rel="stylesheet"
        href="profile.css"
    >


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
                    My Profile
                </h1>


                <p>
                    Manage your personal and emergency
                    information.
                </p>

            </div>

        </div>


        <!--================================================
            ERROR MESSAGE
        =================================================-->

        <?php if ($error !== ''): ?>

            <div class="profile-message profile-error">

                <?= profileEscape($error); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            SUCCESS MESSAGE
        =================================================-->

        <?php if ($success !== ''): ?>

            <div class="profile-message profile-success">

                <?= profileEscape($success); ?>

            </div>

        <?php endif; ?>


        <!--================================================
            ACCOUNT INFORMATION
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Account Information
                    </h2>

                    <p>
                        These details are connected to
                        your patient account.
                    </p>

                </div>

            </div>


            <div class="profile-account-grid">


                <!-- Patient ID -->

                <div class="profile-field">

                    <label>
                        Patient ID
                    </label>

                    <input
                        type="text"
                        value="<?= profileEscape(
                            $patient_uuid
                        ); ?>"
                        readonly
                    >

                </div>


                <!-- First Name -->

                <div class="profile-field">

                    <label>
                        First Name
                    </label>

                    <input
                        type="text"
                        value="<?= profileEscape(
                            $first_name
                        ); ?>"
                        readonly
                    >

                </div>


                <!-- Last Name -->

                <div class="profile-field">

                    <label>
                        Last Name
                    </label>

                    <input
                        type="text"
                        value="<?= profileEscape(
                            $last_name
                        ); ?>"
                        readonly
                    >

                </div>


                <!-- Email -->

                <div class="profile-field">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        value="<?= profileEscape(
                            $email
                        ); ?>"
                        readonly
                    >

                </div>


                <!-- Mobile -->

                <div class="profile-field">

                    <label>
                        Mobile
                    </label>

                    <input
                        type="text"
                        value="<?= profileEscape(
                            $mobile
                        ); ?>"
                        readonly
                    >

                </div>


            </div>

        </div>


        <br>


        <!--================================================
            PROFILE FORM
        =================================================-->

        <div class="dashboard-panel">


            <div class="dashboard-panel-header">

                <div>

                    <h2>
                        Personal Information
                    </h2>

                    <p>
                        Update your personal and health
                        information.
                    </p>

                </div>

            </div>


            <form
                action="profile_process.php"
                method="POST"
                enctype="multipart/form-data"
            >


                <!--================================================
                    ACCOUNT ID
                =================================================-->

                <input
                    type="hidden"
                    name="account_id"
                    value="<?= $account_id; ?>"
                >


                <!--================================================
                    PROFILE PHOTO
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Profile Photo
                    </h3>

                </div>


                <div class="profile-field">


                    <?php if (
                        !empty(
                            $profile['profile_photo']
                        )
                    ): ?>

                        <div class="profile-photo-preview">

                            <img
                                src="../../<?= profileEscape(
                                    (string)
                                    $profile['profile_photo']
                                ); ?>"
                                alt="Patient Profile Photo"
                                width="120"
                                height="120"
                            >

                        </div>

                    <?php endif; ?>


                    <label for="profile_photo">

                        Upload Profile Photo

                    </label>


                    <input
                        type="file"
                        id="profile_photo"
                        name="profile_photo"
                        accept=".jpg,.jpeg,.png,.webp"
                    >


                    <small>

                        JPG, JPEG, PNG or WEBP.
                        Maximum size: 2 MB.

                    </small>


                </div>


                <!--================================================
                    PERSONAL DETAILS
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Personal Details
                    </h3>

                </div>


                <div class="profile-form-grid">


                    <!-- Gender -->

                    <div class="profile-field">

                        <label for="gender">

                            Gender

                        </label>


                        <select
                            id="gender"
                            name="gender"
                        >

                            <option value="">

                                Select Gender

                            </option>


                            <option
                                value="Male"
                                <?= $profile['gender'] === 'Male'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Male

                            </option>


                            <option
                                value="Female"
                                <?= $profile['gender'] === 'Female'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Female

                            </option>


                            <option
                                value="Other"
                                <?= $profile['gender'] === 'Other'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Other

                            </option>


                        </select>

                    </div>


                    <!-- Date of Birth -->

                    <div class="profile-field">

                        <label for="date_of_birth">

                            Date of Birth

                        </label>


                        <input
                            type="date"
                            id="date_of_birth"
                            name="date_of_birth"
                            value="<?= profileEscape(
                                (string)
                                $profile['date_of_birth']
                            ); ?>"
                        >

                    </div>


                    <!-- Blood Group -->

                    <div class="profile-field">

                        <label for="blood_group">

                            Blood Group

                        </label>


                        <select
                            id="blood_group"
                            name="blood_group"
                        >

                            <option value="">

                                Select Blood Group

                            </option>


                            <?php

                            $blood_groups = [

                                'A+',
                                'A-',
                                'B+',
                                'B-',
                                'AB+',
                                'AB-',
                                'O+',
                                'O-'

                            ];


                            foreach (
                                $blood_groups
                                as $blood_group
                            ):

                            ?>

                                <option
                                    value="<?= profileEscape(
                                        $blood_group
                                    ); ?>"
                                    <?= $profile['blood_group']
                                        === $blood_group
                                        ? 'selected'
                                        : ''; ?>
                                >

                                    <?= profileEscape(
                                        $blood_group
                                    ); ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- Marital Status -->

                    <div class="profile-field">

                        <label for="marital_status">

                            Marital Status

                        </label>


                        <select
                            id="marital_status"
                            name="marital_status"
                        >

                            <option value="">

                                Select Status

                            </option>


                            <option
                                value="Single"
                                <?= $profile['marital_status']
                                    === 'Single'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Single

                            </option>


                            <option
                                value="Married"
                                <?= $profile['marital_status']
                                    === 'Married'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Married

                            </option>


                            <option
                                value="Widowed"
                                <?= $profile['marital_status']
                                    === 'Widowed'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Widowed

                            </option>


                            <option
                                value="Divorced"
                                <?= $profile['marital_status']
                                    === 'Divorced'
                                    ? 'selected'
                                    : ''; ?>
                            >

                                Divorced

                            </option>


                        </select>

                    </div>


                </div>


                <!--================================================
                    ADDRESS
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Address
                    </h3>

                </div>


                <div class="profile-form-grid">


                    <!-- Address Line 1 -->

                    <div class="profile-field profile-field-full">

                        <label for="address_line_1">

                            Address Line 1

                        </label>


                        <input
                            type="text"
                            id="address_line_1"
                            name="address_line_1"
                            maxlength="255"
                            value="<?= profileEscape(
                                (string)
                                $profile['address_line_1']
                            ); ?>"
                            placeholder="House / Flat / Street"
                        >

                    </div>


                    <!-- Address Line 2 -->

                    <div class="profile-field profile-field-full">

                        <label for="address_line_2">

                            Address Line 2

                        </label>


                        <input
                            type="text"
                            id="address_line_2"
                            name="address_line_2"
                            maxlength="255"
                            value="<?= profileEscape(
                                (string)
                                $profile['address_line_2']
                            ); ?>"
                            placeholder="Area / Landmark"
                        >

                    </div>


                    <!-- City -->

                    <div class="profile-field">

                        <label for="city">

                            City

                        </label>


                        <input
                            type="text"
                            id="city"
                            name="city"
                            maxlength="100"
                            value="<?= profileEscape(
                                (string)
                                $profile['city']
                            ); ?>"
                        >

                    </div>


                    <!-- State -->

                    <div class="profile-field">

                        <label for="state">

                            State

                        </label>


                        <input
                            type="text"
                            id="state"
                            name="state"
                            maxlength="100"
                            value="<?= profileEscape(
                                (string)
                                $profile['state']
                            ); ?>"
                        >

                    </div>


                    <!-- Country -->

                    <div class="profile-field">

                        <label for="country">

                            Country

                        </label>


                        <input
                            type="text"
                            id="country"
                            name="country"
                            maxlength="100"
                            value="<?= profileEscape(
                                (string)
                                $profile['country']
                            ); ?>"
                        >

                    </div>


                    <!-- Postal Code -->

                    <div class="profile-field">

                        <label for="postal_code">

                            Postal Code

                        </label>


                        <input
                            type="text"
                            id="postal_code"
                            name="postal_code"
                            maxlength="10"
                            value="<?= profileEscape(
                                (string)
                                $profile['postal_code']
                            ); ?>"
                        >

                    </div>


                </div>


                <!--================================================
                    EMERGENCY CONTACT
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Emergency Contact
                    </h3>

                </div>


                <div class="profile-form-grid">


                    <!-- Emergency Name -->

                    <div class="profile-field">

                        <label for="emergency_contact_name">

                            Contact Name

                        </label>


                        <input
                            type="text"
                            id="emergency_contact_name"
                            name="emergency_contact_name"
                            maxlength="100"
                            value="<?= profileEscape(
                                (string)
                                $profile['emergency_contact_name']
                            ); ?>"
                        >

                    </div>


                    <!-- Emergency Mobile -->

                    <div class="profile-field">

                        <label for="emergency_contact_mobile">

                            Contact Mobile

                        </label>


                        <input
                            type="text"
                            id="emergency_contact_mobile"
                            name="emergency_contact_mobile"
                            maxlength="15"
                            value="<?= profileEscape(
                                (string)
                                $profile['emergency_contact_mobile']
                            ); ?>"
                        >

                    </div>


                    <!-- Relationship -->

                    <div class="profile-field">

                        <label for="emergency_relationship">

                            Relationship

                        </label>


                        <input
                            type="text"
                            id="emergency_relationship"
                            name="emergency_relationship"
                            maxlength="50"
                            value="<?= profileEscape(
                                (string)
                                $profile['emergency_relationship']
                            ); ?>"
                            placeholder="Father, Mother, Spouse..."
                        >

                    </div>


                </div>


                <!--================================================
                    INSURANCE
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Insurance Information
                    </h3>

                </div>


                <div class="profile-form-grid">


                    <!-- Insurance Provider -->

                    <div class="profile-field">

                        <label for="insurance_provider">

                            Insurance Provider

                        </label>


                        <input
                            type="text"
                            id="insurance_provider"
                            name="insurance_provider"
                            maxlength="150"
                            value="<?= profileEscape(
                                (string)
                                $profile['insurance_provider']
                            ); ?>"
                        >

                    </div>


                    <!-- Insurance Number -->

                    <div class="profile-field">

                        <label for="insurance_number">

                            Insurance Number

                        </label>


                        <input
                            type="text"
                            id="insurance_number"
                            name="insurance_number"
                            maxlength="100"
                            value="<?= profileEscape(
                                (string)
                                $profile['insurance_number']
                            ); ?>"
                        >

                    </div>


                </div>


                <!--================================================
                    ALLERGIES
                =================================================-->

                <div class="profile-section-title">

                    <h3>
                        Allergies
                    </h3>

                </div>


                <div class="profile-field profile-field-full">

                    <label for="allergies">

                        Known Allergies

                    </label>


                    <textarea
                        id="allergies"
                        name="allergies"
                        rows="4"
                        placeholder="Enter known allergies, if any..."
                    ><?= profileEscape(
                        (string)
                        $profile['allergies']
                    ); ?></textarea>

                </div>


                <!--================================================
                    SAVE
                =================================================-->

                <div class="profile-form-actions">

                    <button
                        type="submit"
                        class="profile-save-button"
                    >

                        Save Profile

                    </button>

                </div>


            </form>

        </div>


    </section>

</div>


<?php



require_once __DIR__ .
    '/../includes/footer.php';

?>