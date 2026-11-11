<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . "/../../includes/geoapify.php";


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error_message = "";

$hospital = null;

$place_id = "";


/*
|--------------------------------------------------------------------------
| HTML ESCAPE FUNCTION
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| GET VALUE SAFELY
|--------------------------------------------------------------------------
*/

function getValue(
    array $data,
    array $keys,
    string $default = "Not Available"
): string
{
    foreach ($keys as $key)
    {
        if (
            isset($data[$key])
            &&
            is_scalar($data[$key])
            &&
            trim((string)$data[$key]) !== ""
        )
        {
            return trim((string)$data[$key]);
        }
    }

    return $default;
}


/*
|--------------------------------------------------------------------------
| PLACE ID FROM URL
|--------------------------------------------------------------------------
*/

$place_id =
    trim(
        (string)(
            $_GET["place_id"]
            ??
            ""
        )
    );


/*
|--------------------------------------------------------------------------
| VALIDATE PLACE ID
|--------------------------------------------------------------------------
*/

if ($place_id === "")
{
    $error_message =
        "Hospital information is missing.";
}
else
{

    /*
    |--------------------------------------------------------------------------
    | GEOAPIFY PLACE DETAILS REQUEST
    |--------------------------------------------------------------------------
    */

    $parameters = [

        /*
        | Place ID received from Places API
        */

        "id" =>
            $place_id,


        /*
        | Only details feature
        | This keeps the request simple and low-cost
        */

        "features" =>
            "details",


        /*
        | Language
        */

        "lang" =>
            "en",


        /*
        | API KEY
        */

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


    /*
    |--------------------------------------------------------------------------
    | CURL REQUEST
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | CURL ERROR
    |--------------------------------------------------------------------------
    */

    if ($response === false)
    {

        $error_message =
            "Unable to connect to Geoapify.";

        curl_close($ch);

    }
    else
    {

        /*
        |--------------------------------------------------------------------------
        | HTTP STATUS
        |--------------------------------------------------------------------------
        */

        $http_code =
            (int)
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );


        curl_close($ch);


        /*
        |--------------------------------------------------------------------------
        | HTTP ERROR
        |--------------------------------------------------------------------------
        */

        if (
            $http_code < 200
            ||
            $http_code >= 300
        )
        {

            $error_message =
                "Hospital details request failed. "
                .
                "HTTP Code: "
                .
                $http_code;

        }
        else
        {

            /*
            |--------------------------------------------------------------------------
            | DECODE JSON
            |--------------------------------------------------------------------------
            */

            $data =
                json_decode(
                    $response,
                    true
                );


            if (
                !is_array($data)
            )
            {

                $error_message =
                    "Invalid response received from Geoapify.";

            }
            else
            {

                /*
                |--------------------------------------------------------------------------
                | FIND DETAILS FEATURE
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $data["features"]
                    )
                    &&
                    is_array(
                        $data["features"]
                    )
                )
                {

                    foreach (
                        $data["features"]
                        as $feature
                    )
                    {

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
                        )
                        {

                            $hospital =
                                $properties;

                            break;

                        }

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | DETAILS NOT FOUND
                |--------------------------------------------------------------------------
                */

                if (
                    $hospital === null
                )
                {

                    $error_message =
                        "Hospital details were not found.";

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| HOSPITAL INFORMATION
|--------------------------------------------------------------------------
*/

if (
    $hospital !== null
)
{

    /*
    |--------------------------------------------------------------------------
    | BASIC INFORMATION
    |--------------------------------------------------------------------------
    */

    $hospital_name =
        getValue(
            $hospital,
            [
                "name"
            ],
            "Hospital"
        );


    $description =
        getValue(
            $hospital,
            [
                "description"
            ]
        );


    $brand =
        getValue(
            $hospital,
            [
                "brand"
            ]
        );


    $operator =
        getValue(
            $hospital,
            [
                "operator"
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | ADDRESS
    |--------------------------------------------------------------------------
    */

    $formatted_address =
        getValue(
            $hospital,
            [
                "formatted"
            ]
        );


    $address_line1 =
        getValue(
            $hospital,
            [
                "address_line1"
            ]
        );


    $address_line2 =
        getValue(
            $hospital,
            [
                "address_line2"
            ]
        );


    $street =
        getValue(
            $hospital,
            [
                "street"
            ]
        );


    $house_number =
        getValue(
            $hospital,
            [
                "housenumber"
            ]
        );


    $city =
        getValue(
            $hospital,
            [
                "city"
            ]
        );


    $state =
        getValue(
            $hospital,
            [
                "state"
            ]
        );


    $postcode =
        getValue(
            $hospital,
            [
                "postcode"
            ]
        );


    $country =
        getValue(
            $hospital,
            [
                "country"
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | CONTACT INFORMATION
    |--------------------------------------------------------------------------
    */

    $contact =
        isset($hospital["contact"])
        &&
        is_array($hospital["contact"])
        ?
        $hospital["contact"]
        :
        [];


    $phone =
        getValue(
            $contact,
            [
                "phone"
            ]
        );


    $email =
        getValue(
            $contact,
            [
                "email"
            ]
        );


    $fax =
        getValue(
            $contact,
            [
                "fax"
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | WEBSITE
    |--------------------------------------------------------------------------
    */

    $website =
        getValue(
            $hospital,
            [
                "website"
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | OPENING HOURS
    |--------------------------------------------------------------------------
    */

    $opening_hours =
        getValue(
            $hospital,
            [
                "opening_hours"
            ]
        );


    /*
    |--------------------------------------------------------------------------
    | FACILITIES
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | COORDINATES
    |--------------------------------------------------------------------------
    */

    $latitude =
        isset(
            $hospital["lat"]
        )
        ?
        (float)
        $hospital["lat"]
        :
        null;


    $longitude =
        isset(
            $hospital["lon"]
        )
        ?
        (float)
        $hospital["lon"]
        :
        null;


    /*
    |--------------------------------------------------------------------------
    | GOOGLE MAPS LINK
    |--------------------------------------------------------------------------
    */

    $google_maps_link = "";


    if (
        $latitude !== null
        &&
        $longitude !== null
    )
    {

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


    /*
    |--------------------------------------------------------------------------
    | WIKIPEDIA
    |--------------------------------------------------------------------------
    */

    $wikipedia_link = "";


    if (
        isset(
            $hospital["wiki_and_media"]
        )
        &&
        is_array(
            $hospital["wiki_and_media"]
        )
    )
    {

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
        )
        {

            $wikipedia_link =
                trim(
                    $wiki["wikipedia"]
                );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | IMAGE
    |--------------------------------------------------------------------------
    */

    $image_url = "";


    if (
        isset(
            $hospital["wiki_and_media"]
        )
        &&
        is_array(
            $hospital["wiki_and_media"]
        )
    )
    {

        $image =
            $hospital["wiki_and_media"];


        if (
            isset(
                $image["image"]
            )
            &&
            is_string(
                $image["image"]
            )
        )
        {

            $image_url =
                trim(
                    $image["image"]
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
        Hospital Details
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 30px;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                #f5f7fb;

            color:
                #172033;

        }


        .container {

            max-width: 1050px;

            margin: 0 auto;

        }


        /* =====================================================
           BACK BUTTON
        ====================================================== */

        .back-link {

            display:
                inline-block;

            margin-bottom:
                20px;

            color:
                #635bff;

            text-decoration:
                none;

            font-size:
                14px;

            font-weight:
                600;

        }


        .back-link:hover {

            text-decoration:
                underline;

        }


        /* =====================================================
           MAIN CARD
        ====================================================== */

        .hospital-card {

            background:
                #ffffff;

            border-radius:
                18px;

            overflow:
                hidden;

            box-shadow:
                0 8px 30px
                rgba(
                    0,
                    0,
                    0,
                    0.07
                );

        }


        /* =====================================================
           HEADER
        ====================================================== */

        .hospital-header {

            padding:
                30px;

            background:
                linear-gradient(
                    135deg,
                    #111936,
                    #635bff
                );

            color:
                #ffffff;

        }


        .hospital-header h1 {

            margin:
                0 0 10px;

            font-size:
                29px;

            line-height:
                1.3;

        }


        .hospital-header p {

            margin:
                0;

            opacity:
                .85;

            font-size:
                14px;

        }


        /* =====================================================
           IMAGE
        ====================================================== */

        .hospital-image {

            width:
                100%;

            max-height:
                350px;

            object-fit:
                cover;

            display:
                block;

        }


        /* =====================================================
           CONTENT
        ====================================================== */

        .content {

            padding:
                30px;

        }


        .section {

            margin-bottom:
                30px;

        }


        .section:last-child {

            margin-bottom:
                0;

        }


        .section-title {

            margin:
                0 0 15px;

            font-size:
                18px;

            color:
                #172033;

        }


        /* =====================================================
           DESCRIPTION
        ====================================================== */

        .description {

            color:
                #687287;

            line-height:
                1.7;

            font-size:
                14px;

        }


        /* =====================================================
           INFORMATION GRID
        ====================================================== */

        .info-grid {

            display:
                grid;

            grid-template-columns:
                repeat(
                    2,
                    1fr
                );

            gap:
                12px;

        }


        .info-box {

            padding:
                17px;

            background:
                #f8f9fd;

            border:
                1px solid #edf0f5;

            border-radius:
                10px;

        }


        .info-label {

            margin-bottom:
                7px;

            color:
                #858da0;

            font-size:
                11px;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                .4px;

        }


        .info-value {

            color:
                #283147;

            font-size:
                14px;

            line-height:
                1.5;

            word-break:
                break-word;

        }


        /* =====================================================
           LINKS
        ====================================================== */

        .info-value a {

            color:
                #635bff;

            text-decoration:
                none;

        }


        .info-value a:hover {

            text-decoration:
                underline;

        }


        /* =====================================================
           ACTIONS
        ====================================================== */

        .actions {

            display:
                flex;

            gap:
                10px;

            flex-wrap:
                wrap;

            margin-top:
                25px;

        }


        .btn {

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                11px 17px;

            border-radius:
                8px;

            text-decoration:
                none;

            font-size:
                13px;

            font-weight:
                600;

        }


        .primary-btn {

            background:
                #635bff;

            color:
                #ffffff;

        }


        .primary-btn:hover {

            background:
                #5148e8;

        }


        .secondary-btn {

            background:
                #eef0ff;

            color:
                #5148e8;

        }


        .green-btn {

            background:
                #e9f8f1;

            color:
                #16855b;

        }


        .gray-btn {

            background:
                #f0f2f6;

            color:
                #40485c;

        }


        /* =====================================================
           ERROR
        ====================================================== */

        .error-card {

            background:
                #ffffff;

            border-radius:
                15px;

            padding:
                30px;

            color:
                #c62828;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    0.05
                );

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (
            max-width: 700px
        )
        {

            body {

                padding:
                    15px;

            }


            .info-grid {

                grid-template-columns:
                    1fr;

            }


            .hospital-header h1 {

                font-size:
                    23px;

            }


            .content,
            .hospital-header {

                padding:
                    20px;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         BACK
    ====================================================== -->

    <a
        href="hospital_search.php"
        class="back-link"
    >

        ← Back to Hospital Search

    </a>


    <?php if (
        $hospital !== null
    ): ?>


        <div class="hospital-card">


            <!-- =================================================
                 HEADER
            ================================================== -->

            <div class="hospital-header">

                <h1>

                    <?= e(
                        $hospital_name
                    ) ?>

                </h1>


                <p>
                    Hospital Information
                </p>

            </div>


            <!-- =================================================
                 IMAGE
            ================================================== -->

            <?php if (
                $image_url !== ""
            ): ?>

                <img
                    src="<?= e(
                        $image_url
                    ) ?>"
                    alt="<?= e(
                        $hospital_name
                    ) ?>"
                    class="hospital-image"
                >

            <?php endif; ?>


            <div class="content">


                <!-- =================================================
                     DESCRIPTION
                ================================================== -->

                <?php if (
                    $description !==
                    "Not Available"
                ): ?>


                    <div class="section">

                        <h2 class="section-title">

                            About Hospital

                        </h2>


                        <div class="description">

                            <?= e(
                                $description
                            ) ?>

                        </div>

                    </div>


                <?php endif; ?>


                <!-- =================================================
                     CONTACT INFORMATION
                ================================================== -->

                <div class="section">

                    <h2 class="section-title">

                        Contact Information

                    </h2>


                    <div class="info-grid">


                        <div class="info-box">

                            <div class="info-label">

                                Phone

                            </div>

                            <div class="info-value">

                                <?php if (
                                    $phone !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="tel:<?= e(
                                            $phone
                                        ) ?>"
                                    >

                                        <?= e(
                                            $phone
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Email

                            </div>

                            <div class="info-value">

                                <?php if (
                                    $email !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="mailto:<?= e(
                                            $email
                                        ) ?>"
                                    >

                                        <?= e(
                                            $email
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Website

                            </div>

                            <div class="info-value">

                                <?php if (
                                    $website !==
                                    "Not Available"
                                ): ?>

                                    <a
                                        href="<?= e(
                                            $website
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >

                                        <?= e(
                                            $website
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    Not Available

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Opening Hours

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $opening_hours
                                ) ?>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- =================================================
                     ADDRESS
                ================================================== -->

                <div class="section">

                    <h2 class="section-title">

                        Address

                    </h2>


                    <div class="info-grid">


                        <div class="info-box">

                            <div class="info-label">

                                Full Address

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $formatted_address
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Street

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $street
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                House Number

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $house_number
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                City

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $city
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                State

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $state
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Postal Code

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $postcode
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Country

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $country
                                ) ?>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- =================================================
                     HOSPITAL INFORMATION
                ================================================== -->

                <div class="section">

                    <h2 class="section-title">

                        Hospital Information

                    </h2>


                    <div class="info-grid">


                        <div class="info-box">

                            <div class="info-label">

                                Brand

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $brand
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Operator

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $operator
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Wheelchair Access

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $wheelchair
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Internet Access

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $internet
                                ) ?>

                            </div>

                        </div>


                    </div>

                </div>


                <!-- =================================================
                     LOCATION
                ================================================== -->

                <div class="section">

                    <h2 class="section-title">

                        Location

                    </h2>


                    <div class="info-grid">


                        <div class="info-box">

                            <div class="info-label">

                                Latitude

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $latitude !== null
                                    ?
                                    (string)$latitude
                                    :
                                    "Not Available"
                                ) ?>

                            </div>

                        </div>


                        <div class="info-box">

                            <div class="info-label">

                                Longitude

                            </div>

                            <div class="info-value">

                                <?= e(
                                    $longitude !== null
                                    ?
                                    (string)$longitude
                                    :
                                    "Not Available"
                                ) ?>

                            </div>

                        </div>


                    </div>


                    <!-- ACTIONS -->

                    <div class="actions">


                        <?php if (
                            $google_maps_link !== ""
                        ): ?>

                            <a
                                href="<?= e(
                                    $google_maps_link
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn primary-btn"
                            >

                                📍 View on Google Maps

                            </a>

                        <?php endif; ?>


                        <?php if (
                            $phone !==
                            "Not Available"
                        ): ?>

                            <a
                                href="tel:<?= e(
                                    $phone
                                ) ?>"
                                class="btn green-btn"
                            >

                                📞 Call Hospital

                            </a>

                        <?php endif; ?>


                        <?php if (
                            $website !==
                            "Not Available"
                        ): ?>

                            <a
                                href="<?= e(
                                    $website
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn secondary-btn"
                            >

                                🌐 Hospital Website

                            </a>

                        <?php endif; ?>


                        <?php if (
                            $wikipedia_link !== ""
                        ): ?>

                            <a
                                href="<?= e(
                                    $wikipedia_link
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn gray-btn"
                            >

                                Wikipedia

                            </a>

                        <?php endif; ?>


                    </div>

                </div>


            </div>

        </div>


    <?php else: ?>


        <!-- =================================================
             ERROR
        ================================================== -->

        <div class="error-card">

            <?= e(
                $error_message
            ) ?>

            <br>
            <br>

            <a
                href="hospital_search.php"
                class="btn secondary-btn"
            >

                ← Back to Hospital Search

            </a>

        </div>


    <?php endif; ?>


</div>


</body>

</html>