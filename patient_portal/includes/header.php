<?php

declare(strict_types=1);


$page_title = $page_title ?? "Patient Dashboard";


/*====================================================
    PATIENT NAME
====================================================*/

$patient_name = "Patient";

if (
    isset($patient)
    &&
    is_array($patient)
    &&
    !empty($patient['first_name'])
)
{
    $patient_name = $patient['first_name'];

    if (!empty($patient['last_name']))
    {
        $patient_name .= " " . $patient['last_name'];
    }
}


/*====================================================
    HTML ESCAPE HELPER
====================================================*/

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
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
        name="description"
        content="Smart Hospital Management System - Patient Portal"
    >

    <title>
        <?= e($page_title); ?> | Smart Hospital
    </title>



    <link
        rel="stylesheet"
        href="assets/css/patient_portal.css"
    >

</head>


<body>


<header class="patient-header">

    <div class="patient-header-container">


        <!-- Logo / Hospital System Name -->

        <div class="patient-logo">

            <a href="dashboard.php">

                Smart Hospital

            </a>

        </div>


        <!-- Patient Information -->

        <div class="patient-header-right">

            <span class="patient-welcome">

                Welcome,
                <?= e($patient_name); ?>

            </span>


            <a
                href="logout.php"
                class="logout-button"
            >
                Logout
            </a>

        </div>


    </div>

</header>