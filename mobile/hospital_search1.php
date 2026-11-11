<?php

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . "/Hospital_Management_System/includes/geoapify.php";

/*====================================================
    VARIABLES
====================================================*/

$error_message = "";

$hospital = null;

$place_id = "";


/*====================================================
    HTML ESCAPE
====================================================*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}


/*====================================================
    SAFE VALUE
====================================================*/

function getValue(
    array $data,
    array $keys,
    string $default = "Not Available"
): string
{
    foreach ($keys as $key) {

        if (
            isset($data[$key])
            &&
            is_scalar($data[$key])
            &&
            trim((string)$data[$key]) !== ""
        ) {

            return trim(
                (string)$data[$key]
            );
        }
    }

    return $default;
}


/*====================================================
    PLACE ID
====================================================*/

$place_id =
    trim(
        (string)(
            $_GET["place_id"]
            ?? ""
        )
    );


/*====================================================
    GET HOSPITAL DETAILS
====================================================*/

if ($place_id === "") {

    $error_message =
        "Hospital information is missing.";

}
else {

    $parameters = [

        "id" =>
            $place_id,

        "features" =>
            "details",

        "lang" =>
            "en",

        "apiKey" =>
            $geoapify_api_key

    ];


    $details_url =
        $geoapify_details_url
        .
        "?"
        .
        http_build_query(
            $parameters
        );


    $ch =
        curl_init(
            $details_url
        );


    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_TIMEOUT =>
                30,

            CURLOPT_HTTPHEADER =>
                [
                    "Accept: application/json"
                ]

        ]
    );


    $response =
        curl_exec($ch);


    if ($response === false) {

        $error_message =
            "Unable to connect to Geoapify.";

        curl_close($ch);

    }
    else {

        $http_code =
            (int)
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        curl_close($ch);


        if (
            $http_code < 200
            ||
            $http_code >= 300
        ) {

            $error_message =
                "Hospital details request failed. "
                .
                "HTTP Code: "
                .
                $http_code;

        }
        else {

            $data =
                json_decode(
                    $response,
                    true
                );


            if (!is_array($data)) {

                $error_message =
                    "Invalid response received from Geoapify.";

            }
            else {

                if (
                    isset($data["features"])
                    &&
                    is_array(
                        $data["features"]
                    )
                ) {

                    foreach (
                        $data["features"]
                        as $feature
                    ) {

                        $properties =
                            $feature["properties"]
                            ??
                            [];


                        if (
                            (
                                $properties["feature_type"]
                                ??
                                ""
                            )
                            ===
                            "details"
                        ) {

                            $hospital =
                                $properties;

                            break;
                        }
                    }
                }


                if (
                    $hospital === null
                ) {

                    $error_message =
                        "Hospital details were not found.";

                }
            }
        }
    }
}


/*====================================================
    HOSPITAL DATA
====================================================*/

if (
    $hospital !== null
) {


    /*-----------------------------------------------
        BASIC
    -----------------------------------------------*/

    $hospital_name =
        getValue(
            $hospital,
            ["name"],
            "Hospital"
        );


    $description =
        getValue(
            $hospital,
            ["description"]
        );


    $brand =
        getValue(
            $hospital,
            ["brand"]
        );


    $operator =
        getValue(
            $hospital,
            ["operator"]
        );


    /*-----------------------------------------------
        ADDRESS
    -----------------------------------------------*/

    $formatted_address =
        getValue(
            $hospital,
            ["formatted"]
        );


    $street =
        getValue(
            $hospital,
            ["street"]
        );


    $house_number =
        getValue(
            $hospital,
            ["housenumber"]
        );


    $city =
        getValue(
            $hospital,
            ["city"]
        );


    $state =
        getValue(
            $hospital,
            ["state"]
        );


    $postcode =
        getValue(
            $hospital,
            ["postcode"]
        );


    $country =
        getValue(
            $hospital,
            ["country"]
        );


    /*-----------------------------------------------
        CONTACT
    -----------------------------------------------*/

    $contact =
        isset($hospital["contact"])
        &&
        is_array(
            $hospital["contact"]
        )
        ?
        $hospital["contact"]
        :
        [];


    $phone =
        getValue(
            $contact,
            ["phone"]
        );


    $email =
        getValue(
            $contact,
            ["email"]
        );


    $fax =
        getValue(
            $contact,
            ["fax"]
        );


    /*-----------------------------------------------
        WEBSITE
    -----------------------------------------------*/

    $website =
        getValue(
            $hospital,
            ["website"]
        );


    /*-----------------------------------------------
        OPENING HOURS
    -----------------------------------------------*/

    $opening_hours =
        getValue(
            $hospital,
            ["opening_hours"]
        );


    /*-----------------------------------------------
        FACILITIES
    -----------------------------------------------*/

    $wheelchair =
        isset(
            $hospital["wheelchair"]
        )
        ?
        (
            $hospital["wheelchair"]
            ?
            "Available"
            :
            "Not Available"
        )
        :
        "Not Available";


    $internet =
        isset(
            $hospital["internet_access"]
        )
        ?
        (
            $hospital["internet_access"]
            ?
            "Available"
            :
            "Not Available"
        )
        :
        "Not Available";


    /*-----------------------------------------------
        COORDINATES
    -----------------------------------------------*/

    $latitude =
        isset(
            $hospital["lat"]
        )
        ?
        (float)$hospital["lat"]
        :
        null;


    $longitude =
        isset(
            $hospital["lon"]
        )
        ?
        (float)$hospital["lon"]
        :
        null;


    /*-----------------------------------------------
        GOOGLE MAPS
    -----------------------------------------------*/

    $google_maps_link = "";


    if (
        $latitude !== null
        &&
        $longitude !== null
    ) {

        $google_maps_link =
            "https://www.google.com/maps/search/?api=1&query="
            .
            urlencode(
                $latitude
                .
                ","
                .
                $longitude
            );
    }


    /*-----------------------------------------------
        WIKIPEDIA
    -----------------------------------------------*/

    $wikipedia_link = "";


    if (
        isset(
            $hospital["wiki_and_media"]
        )
        &&
        is_array(
            $hospital["wiki_and_media"]
        )
    ) {

        $wiki =
            $hospital["wiki_and_media"];


        if (
            isset(
                $wiki["wikipedia"]
            )
            &&
            is_string(
                $wiki["wikipedia"]
            )
        ) {

            $wikipedia_link =
                trim(
                    $wiki["wikipedia"]
                );
        }
    }


    /*-----------------------------------------------
        IMAGE
    -----------------------------------------------*/

    $image_url = "";


    if (
        isset(
            $hospital["wiki_and_media"]
        )
        &&
        is_array(
            $hospital["wiki_and_media"]
        )
    ) {

        $media =
            $hospital["wiki_and_media"];


        if (
            isset(
                $media["image"]
            )
            &&
            is_string(
                $media["image"]
            )
        ) {

            $image_url =
                trim(
                    $media["image"]
                );
        }
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


    <title>
        <?= e(
            $hospital_name
            ??
            "Hospital Details"
        ); ?>
    </title>


    <style>


        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f5f7fb;

            color:
                #172033;

        }


        /*========================================
            APP
        ========================================*/

        .mobile-hospital-app {

            width: 100%;

            max-width: 520px;

            min-height: 100vh;

            margin: 0 auto;

            background: #f5f7fb;

            padding-bottom: 30px;

        }


        /*========================================
            TOP BAR
        ========================================*/

        .hospital-topbar {

            position: sticky;

            top: 0;

            z-index: 20;

            display: flex;

            align-items: center;

            gap: 12px;

            padding:
                14px 16px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .96
                );

            backdrop-filter:
                blur(10px);

            border-bottom:
                1px solid #edf0f5;

        }


        .back-button {

            width: 40px;

            height: 40px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            background: #f1f3f8;

            color: #20283a;

            text-decoration: none;

            font-size: 20px;

            flex-shrink: 0;

        }


        .topbar-title {

            flex: 1;

            min-width: 0;

        }


        .topbar-title span {

            display: block;

            color: #8a92a5;

            font-size: 10px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .5px;

        }


        .topbar-title strong {

            display: block;

            margin-top: 2px;

            color: #20283a;

            font-size: 15px;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        /*========================================
            HERO
        ========================================*/

        .hospital-hero {

            position: relative;

            background:
                linear-gradient(
                    135deg,
                    #111936,
                    #6355f4
                );

            color: #ffffff;

            padding: 26px 20px;

        }


        .hospital-icon {

            width: 58px;

            height: 58px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 15px;

            border-radius: 18px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .15
                );

            font-size: 29px;

        }


        .hospital-hero h1 {

            margin: 0 0 8px;

            font-size: 24px;

            line-height: 1.25;

            word-break: break-word;

        }


        .hospital-hero p {

            margin: 0;

            color:
                rgba(
                    255,
                    255,
                    255,
                    .82
                );

            font-size: 12px;

            line-height: 1.5;

        }


        /*========================================
            QUICK ACTIONS
        ========================================*/

        .quick-actions {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 9px;

            padding: 14px 16px 4px;

        }


        .quick-action {

            min-height: 76px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            gap: 6px;

            border-radius: 14px;

            background: #ffffff;

            box-shadow:
                0 4px 15px
                rgba(
                    0,
                    0,
                    0,
                    .04
                );

            text-decoration: none;

            color: #283147;

            font-size: 10px;

            font-weight: 700;

            text-align: center;

        }


        .quick-action-icon {

            font-size: 20px;

        }


        /*========================================
            CONTENT
        ========================================*/

        .content {

            padding:
                12px 16px;

        }


        .section {

            margin-top: 14px;

        }


        .section-title {

            margin:
                0 0 10px;

            font-size: 17px;

            color: #20283a;

        }


        .section-subtitle {

            margin:
                -5px 0 12px;

            color: #7c8598;

            font-size: 11px;

            line-height: 1.5;

        }


        /*========================================
            IMAGE
        ========================================*/

        .hospital-image-card {

            overflow: hidden;

            border-radius: 17px;

            background: #ffffff;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .05
                );

        }


        .hospital-image {

            width: 100%;

            height: 220px;

            display: block;

            object-fit: cover;

        }


        /*========================================
            INFORMATION CARD
        ========================================*/

        .info-card {

            padding: 16px;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .045
                );

        }


        .info-row {

            display: flex;

            align-items: flex-start;

            gap: 11px;

            padding: 12px 0;

            border-bottom:
                1px solid #edf0f5;

        }


        .info-row:first-child {

            padding-top: 0;

        }


        .info-row:last-child {

            padding-bottom: 0;

            border-bottom: none;

        }


        .info-icon {

            width: 34px;

            height: 34px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 10px;

            background: #f1f2ff;

            font-size: 16px;

        }


        .info-content {

            min-width: 0;

            flex: 1;

        }


        .info-label {

            display: block;

            margin-bottom: 3px;

            color: #8a92a5;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .4px;

        }


        .info-value {

            color: #283147;

            font-size: 12px;

            line-height: 1.55;

            word-break: break-word;

        }


        .info-value a {

            color: #6355f4;

            text-decoration: none;

        }


        /*========================================
            DESCRIPTION
        ========================================*/

        .description-card {

            padding: 16px;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .045
                );

        }


        .description-card p {

            margin: 0;

            color: #687287;

            font-size: 12px;

            line-height: 1.7;

        }


        /*========================================
            LOCATION
        ========================================*/

        .location-card {

            padding: 16px;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .045
                );

        }


        .coordinates {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 9px;

            margin-bottom: 12px;

        }


        .coordinate-box {

            padding: 11px;

            border-radius: 11px;

            background: #f8f9fd;

            border:
                1px solid #edf0f5;

        }


        .coordinate-box span {

            display: block;

            margin-bottom: 4px;

            color: #8a92a5;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

        }


        .coordinate-box strong {

            font-size: 11px;

            color: #283147;

            word-break: break-all;

        }


        /*========================================
            BUTTONS
        ========================================*/

        .action-buttons {

            display: flex;

            flex-direction: column;

            gap: 9px;

        }


        .action-button {

            width: 100%;

            min-height: 46px;

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding:
                10px 14px;

            border-radius: 12px;

            text-decoration: none;

            font-size: 12px;

            font-weight: 700;

        }


        .primary-button {

            background: #6355f4;

            color: #ffffff;

        }


        .green-button {

            background: #e9f8f1;

            color: #16855b;

        }


        .secondary-button {

            background: #eef0ff;

            color: #5143e8;

        }


        .gray-button {

            background: #eef0f4;

            color: #40485c;

        }


        /*========================================
            ERROR
        ========================================*/

        .error-container {

            padding: 20px 16px;

        }


        .error-card {

            padding: 20px;

            border-radius: 16px;

            background: #ffffff;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    .05
                );

            text-align: center;

        }


        .error-icon {

            font-size: 42px;

            margin-bottom: 10px;

        }


        .error-card h2 {

            margin:
                0 0 8px;

            font-size: 18px;

            color: #30384b;

        }


        .error-card p {

            margin:
                0 0 18px;

            color: #c62828;

            font-size: 12px;

            line-height: 1.5;

        }


        /*========================================
            DESKTOP
        ========================================*/

        @media (min-width: 700px) {

            body {

                padding: 20px;

            }


            .mobile-hospital-app {

                border-radius: 22px;

                overflow: hidden;

                box-shadow:
                    0 10px 40px
                    rgba(
                        0,
                        0,
                        0,
                        .08
                    );

            }

        }


        /*========================================
            SMALL MOBILE
        ========================================*/

        @media (max-width: 360px) {

            .hospital-hero h1 {

                font-size: 21px;

            }


            .quick-actions {

                gap: 6px;

            }


            .quick-action {

                min-height: 70px;

                font-size: 9px;

            }


            .content {

                padding-left: 12px;

                padding-right: 12px;

            }

        }

    </style>


<link rel="stylesheet" href="css/common.css">
</head>


<body>


<div class="mobile-hospital-app">


    <!--================================================
        TOP BAR
    =================================================-->

    <div class="hospital-topbar">


        <a
            href="hospital_search.php"
            class="back-button"
            aria-label="Back"
        >

            ←

        </a>


        <div class="topbar-title">

            <span>
                FIND CARE
            </span>


            <strong>
                Hospital Details
            </strong>

        </div>


    </div>



    <?php if (
        $hospital !== null
    ): ?>


        <!--================================================
            HERO
        =================================================-->

        <div class="hospital-hero">


            <div class="hospital-icon">
                🏥
            </div>


            <h1>

                <?= e(
                    $hospital_name
                ); ?>

            </h1>


            <p>
                Hospital information and contact details
            </p>


        </div>



        <!--================================================
            QUICK ACTIONS
        =================================================-->

        <div class="quick-actions">


            <?php if (
                $google_maps_link !== ""
            ): ?>

                <a
                    href="<?= e(
                        $google_maps_link
                    ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="quick-action"
                >

                    <span class="quick-action-icon">
                        📍
                    </span>

                    <span>
                        Directions
                    </span>

                </a>

            <?php endif; ?>



            <?php if (
                $phone !==
                "Not Available"
            ): ?>

                <a
                    href="tel:<?= e(
                        $phone
                    ); ?>"
                    class="quick-action"
                >

                    <span class="quick-action-icon">
                        📞
                    </span>

                    <span>
                        Call
                    </span>

                </a>

            <?php endif; ?>



            <?php if (
                $website !==
                "Not Available"
            ): ?>

                <a
                    href="<?= e(
                        $website
                    ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="quick-action"
                >

                    <span class="quick-action-icon">
                        🌐
                    </span>

                    <span>
                        Website
                    </span>

                </a>

            <?php endif; ?>


        </div>



        <div class="content">


            <!--================================================
                IMAGE
            =================================================-->

            <?php if (
                $image_url !== ""
            ): ?>


                <section class="section">


                    <div class="hospital-image-card">


                        <img
                            src="<?= e(
                                $image_url
                            ); ?>"
                            alt="<?= e(
                                $hospital_name
                            ); ?>"
                            class="hospital-image"
                        >


                    </div>


                </section>


            <?php endif; ?>



            <!--================================================
                ABOUT
            =================================================-->

            <?php if (
                $description !==
                "Not Available"
            ): ?>


                <section class="section">


                    <h2 class="section-title">
                        About Hospital
                    </h2>


                    <div class="description-card">


                        <p>

                            <?= e(
                                $description
                            ); ?>

                        </p>


                    </div>


                </section>


            <?php endif; ?>



            <!--================================================
                CONTACT
            =================================================-->

            <section class="section">


                <h2 class="section-title">
                    Contact Information
                </h2>


                <div class="info-card">


                    <!-- PHONE -->

                    <div class="info-row">


                        <div class="info-icon">
                            📞
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Phone
                            </span>


                            <div class="info-value">


                                <?php if (
                                    $phone !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="tel:<?= e(
                                            $phone
                                        ); ?>"
                                    >

                                        <?= e(
                                            $phone
                                        ); ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>


                            </div>


                        </div>


                    </div>



                    <!-- EMAIL -->

                    <div class="info-row">


                        <div class="info-icon">
                            ✉️
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Email
                            </span>


                            <div class="info-value">


                                <?php if (
                                    $email !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="mailto:<?= e(
                                            $email
                                        ); ?>"
                                    >

                                        <?= e(
                                            $email
                                        ); ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>


                            </div>


                        </div>


                    </div>



                    <!-- WEBSITE -->

                    <div class="info-row">


                        <div class="info-icon">
                            🌐
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Website
                            </span>


                            <div class="info-value">


                                <?php if (
                                    $website !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="<?= e(
                                            $website
                                        ); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >

                                        <?= e(
                                            $website
                                        ); ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>


                            </div>


                        </div>


                    </div>



                    <!-- HOURS -->

                    <div class="info-row">


                        <div class="info-icon">
                            🕐
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Opening Hours
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $opening_hours
                                ); ?>

                            </div>


                        </div>


                    </div>


                </div>


            </section>



            <!--================================================
                ADDRESS
            =================================================-->

            <section class="section">


                <h2 class="section-title">
                    Address
                </h2>


                <div class="info-card">


                    <!-- FULL ADDRESS -->

                    <div class="info-row">


                        <div class="info-icon">
                            📍
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Full Address
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $formatted_address
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- STREET -->

                    <div class="info-row">


                        <div class="info-icon">
                            🛣️
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Street
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $street
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- CITY -->

                    <div class="info-row">


                        <div class="info-icon">
                            🏙️
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                City
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $city
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- STATE -->

                    <div class="info-row">


                        <div class="info-icon">
                            🗺️
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                State
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $state
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- POSTCODE -->

                    <div class="info-row">


                        <div class="info-icon">
                            📮
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Postal Code
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $postcode
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- COUNTRY -->

                    <div class="info-row">


                        <div class="info-icon">
                            🌍
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Country
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $country
                                ); ?>

                            </div>


                        </div>


                    </div>


                </div>


            </section>



            <!--================================================
                HOSPITAL INFORMATION
            =================================================-->

            <section class="section">


                <h2 class="section-title">
                    Hospital Information
                </h2>


                <div class="info-card">


                    <!-- BRAND -->

                    <div class="info-row">


                        <div class="info-icon">
                            🏷️
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Brand
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $brand
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- OPERATOR -->

                    <div class="info-row">


                        <div class="info-icon">
                            👨‍💼
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Operator
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $operator
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- WHEELCHAIR -->

                    <div class="info-row">


                        <div class="info-icon">
                            ♿
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Wheelchair Access
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $wheelchair
                                ); ?>

                            </div>


                        </div>


                    </div>



                    <!-- INTERNET -->

                    <div class="info-row">


                        <div class="info-icon">
                            📶
                        </div>


                        <div class="info-content">


                            <span class="info-label">
                                Internet Access
                            </span>


                            <div class="info-value">

                                <?= e(
                                    $internet
                                ); ?>

                            </div>


                        </div>


                    </div>


                </div>


            </section>



            <!--================================================
                LOCATION
            =================================================-->

            <section class="section">


                <h2 class="section-title">
                    Location
                </h2>


                <div class="location-card">


                    <div class="coordinates">


                        <div class="coordinate-box">


                            <span>
                                Latitude
                            </span>


                            <strong>

                                <?= e(
                                    $latitude !== null
                                    ?
                                    (string)$latitude
                                    :
                                    "Not Available"
                                ); ?>

                            </strong>


                        </div>



                        <div class="coordinate-box">


                            <span>
                                Longitude
                            </span>


                            <strong>

                                <?= e(
                                    $longitude !== null
                                    ?
                                    (string)$longitude
                                    :
                                    "Not Available"
                                ); ?>

                            </strong>


                        </div>


                    </div>



                    <!-- ACTIONS -->

                    <div class="action-buttons">


                        <?php if (
                            $google_maps_link !== ""
                        ): ?>


                            <a
                                href="<?= e(
                                    $google_maps_link
                                ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="action-button primary-button"
                            >

                                📍
                                Open in Google Maps

                            </a>


                        <?php endif; ?>



                        <?php if (
                            $phone !==
                            "Not Available"
                        ): ?>


                            <a
                                href="tel:<?= e(
                                    $phone
                                ); ?>"
                                class="action-button green-button"
                            >

                                📞
                                Call Hospital

                            </a>


                        <?php endif; ?>



                        <?php if (
                            $website !==
                            "Not Available"
                        ): ?>


                            <a
                                href="<?= e(
                                    $website
                                ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="action-button secondary-button"
                            >

                                🌐
                                Visit Hospital Website

                            </a>


                        <?php endif; ?>



                        <?php if (
                            $wikipedia_link !== ""
                        ): ?>


                            <a
                                href="<?= e(
                                    $wikipedia_link
                                ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="action-button gray-button"
                            >

                                📖
                                Wikipedia

                            </a>


                        <?php endif; ?>


                    </div>


                </div>


            </section>


        </div>


    <?php else: ?>


        <!--================================================
            ERROR
        =================================================-->

        <div class="error-container">


            <div class="error-card">


                <div class="error-icon">
                    🏥
                </div>


                <h2>
                    Hospital Not Found
                </h2>


                <p>

                    <?= e(
                        $error_message
                    ); ?>

                </p>


                <a
                    href="hospital_search.php"
                    class="action-button secondary-button"
                >

                    ←
                    Back to Hospital Search

                </a>


            </div>


        </div>


    <?php endif; ?>


</div>


</body>

</html>