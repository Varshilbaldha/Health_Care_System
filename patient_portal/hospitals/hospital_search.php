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

$success_message = "";

$hospitals = [];

$searched_country = "";

$searched_state = "";

$searched_city = "";

$city_place_id = "";

$current_page = 1;

$per_page = 100;

$total_found = 0;


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
| GEOAPIFY GET REQUEST
|--------------------------------------------------------------------------
*/

function geoapifyGet(string $url): array
{
    $ch = curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_CONNECTTIMEOUT => 10,

            CURLOPT_TIMEOUT => 40,

            CURLOPT_HTTPHEADER => [
                "Accept: application/json"
            ]
        ]
    );


    $response = curl_exec($ch);


    if ($response === false)
    {
        $error = curl_error($ch);

        curl_close($ch);

        return [
            "success" => false,
            "http_code" => 0,
            "error" => $error,
            "data" => null
        ];
    }


    $http_code =
        (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close($ch);


    $data =
        json_decode(
            $response,
            true
        );


    return [
        "success" =>
            $http_code >= 200 &&
            $http_code < 300,

        "http_code" =>
            $http_code,

        "error" =>
            null,

        "data" =>
            $data
    ];
}


/*
|--------------------------------------------------------------------------
| GET PAGE
|--------------------------------------------------------------------------
*/

if (
    isset($_GET["page"])
)
{
    $current_page =
        max(
            1,
            (int)$_GET["page"]
        );
}


/*
|--------------------------------------------------------------------------
| SEARCH FORM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    ===
    "POST"
)
{
    $searched_country =
        trim(
            (string)(
                $_POST["country"]
                ??
                ""
            )
        );


    $searched_state =
        trim(
            (string)(
                $_POST["state"]
                ??
                ""
            )
        );


    $searched_city =
        trim(
            (string)(
                $_POST["city"]
                ??
                ""
            )
        );


    /*
    |--------------------------------------------------------------------------
    | SAVE SEARCH IN SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION["hospital_search"] = [

        "country" =>
            $searched_country,

        "state" =>
            $searched_state,

        "city" =>
            $searched_city

    ];


    /*
    |--------------------------------------------------------------------------
    | RESET PAGE
    |--------------------------------------------------------------------------
    */

    $current_page = 1;
}


/*
|--------------------------------------------------------------------------
| LOAD SEARCH FROM SESSION
|--------------------------------------------------------------------------
*/

if (
    $searched_country === ""
    &&
    isset(
        $_SESSION["hospital_search"]
    )
)
{
    $searched_country =
        (string)(
            $_SESSION["hospital_search"]["country"]
            ??
            ""
        );


    $searched_state =
        (string)(
            $_SESSION["hospital_search"]["state"]
            ??
            ""
        );


    $searched_city =
        (string)(
            $_SESSION["hospital_search"]["city"]
            ??
            ""
        );
}


/*
|--------------------------------------------------------------------------
| ONLY SEARCH IF LOCATION EXISTS
|--------------------------------------------------------------------------
*/

if (
    $searched_country !== ""
    &&
    $searched_state !== ""
    &&
    $searched_city !== ""
)
{

    /*
    |--------------------------------------------------------------------------
    | STEP 1
    | GEOCODING
    |--------------------------------------------------------------------------
    */

    $location_text =
        $searched_city
        . ", "
        .
        $searched_state
        . ", "
        .
        $searched_country;


    $geocode_parameters = [

        "text" =>
            $location_text,

        "format" =>
            "json",

        "limit" =>
            1,

        "apiKey" =>
            $geoapify_api_key

    ];


    $geocode_url =
        $geoapify_geocode_url
        . "?"
        .
        http_build_query(
            $geocode_parameters
        );


    $geocode_result =
        geoapifyGet(
            $geocode_url
        );


    /*
    |--------------------------------------------------------------------------
    | GEOCODING ERROR
    |--------------------------------------------------------------------------
    */

    if (
        !$geocode_result["success"]
    )
    {

        $error_message =
            "Location search failed. "
            .
            "HTTP Code: "
            .
            $geocode_result["http_code"];

    }


    elseif (
        empty(
            $geocode_result["data"]["results"]
        )
    )
    {

        $error_message =
            "Location not found for "
            .
            $location_text
            .
            ".";

    }


    else
    {

        /*
        |--------------------------------------------------------------------------
        | GET GEOCODING RESULT
        |--------------------------------------------------------------------------
        */

        $location =
            $geocode_result["data"]["results"][0];


        /*
        |--------------------------------------------------------------------------
        | GET CITY PLACE ID
        |--------------------------------------------------------------------------
        */

        $city_place_id =
            (string)(
                $location["place_id"]
                ??
                ""
            );


        /*
        |--------------------------------------------------------------------------
        | CHECK PLACE ID
        |--------------------------------------------------------------------------
        */

        if (
            $city_place_id === ""
        )
        {

            $error_message =
                "City boundary information was not found.";

        }


        else
        {

            /*
            |--------------------------------------------------------------------------
            | STEP 2
            | SEARCH HOSPITALS INSIDE CITY BOUNDARY
            |--------------------------------------------------------------------------
            */

            $offset =
                (
                    $current_page
                    -
                    1
                )
                *
                $per_page;


            $places_parameters = [

                /*
                |--------------------------------------------------------------------------
                | Hospital category
                |--------------------------------------------------------------------------
                */

                "categories" =>
                    "healthcare.hospital",


                /*
                |--------------------------------------------------------------------------
                | CITY BOUNDARY
                |--------------------------------------------------------------------------
                */

                "filter" =>
                    "place:"
                    .
                    $city_place_id,


                /*
                |--------------------------------------------------------------------------
                | RESULTS PER PAGE
                |--------------------------------------------------------------------------
                */

                "limit" =>
                    $per_page,


                /*
                |--------------------------------------------------------------------------
                | PAGINATION OFFSET
                |--------------------------------------------------------------------------
                */

                "offset" =>
                    $offset,


                /*
                |--------------------------------------------------------------------------
                | LANGUAGE
                |--------------------------------------------------------------------------
                */

                "lang" =>
                    "en",


                /*
                |--------------------------------------------------------------------------
                | API KEY
                |--------------------------------------------------------------------------
                */

                "apiKey" =>
                    $geoapify_api_key

            ];


            $places_url =
                $geoapify_places_url
                . "?"
                .
                http_build_query(
                    $places_parameters
                );


            /*
            |--------------------------------------------------------------------------
            | CALL PLACES API
            |--------------------------------------------------------------------------
            */

            $places_result =
                geoapifyGet(
                    $places_url
                );


            /*
            |--------------------------------------------------------------------------
            | PLACES ERROR
            |--------------------------------------------------------------------------
            */

            if (
                !$places_result["success"]
            )
            {

                $error_message =
                    "Hospital search failed. "
                    .
                    "HTTP Code: "
                    .
                    $places_result["http_code"];

            }


            else
            {

                /*
                |--------------------------------------------------------------------------
                | GET FEATURES
                |--------------------------------------------------------------------------
                */

                $features =
                    $places_result["data"]["features"]
                    ??
                    [];


                /*
                |--------------------------------------------------------------------------
                | TOTAL RESULT COUNT
                |--------------------------------------------------------------------------
                */

                $total_found =
                    count($features);


                /*
                |--------------------------------------------------------------------------
                | PROCESS HOSPITALS
                |--------------------------------------------------------------------------
                */

                foreach (
                    $features
                    as $feature
                )
                {

                    $properties =
                        $feature["properties"]
                        ??
                        [];


                    if (
                        empty($properties)
                    )
                    {
                        continue;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | HOSPITAL NAME
                    |--------------------------------------------------------------------------
                    */

                    $name =
                        trim(
                            (string)(
                                $properties["name"]
                                ??
                                ""
                            )
                        );


                    if (
                        $name === ""
                    )
                    {
                        $name =
                            "Unnamed Hospital";
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | PLACE ID
                    |--------------------------------------------------------------------------
                    */

                    $place_id =
                        (string)(
                            $properties["place_id"]
                            ??
                            ""
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | ADDRESS
                    |--------------------------------------------------------------------------
                    */

                    $address =
                        trim(
                            (string)(
                                $properties["formatted"]
                                ??
                                ""
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | LATITUDE
                    |--------------------------------------------------------------------------
                    */

                    $hospital_lat =
                        isset(
                            $properties["lat"]
                        )
                        ?
                        (float)
                        $properties["lat"]
                        :
                        null;


                    /*
                    |--------------------------------------------------------------------------
                    | LONGITUDE
                    |--------------------------------------------------------------------------
                    */

                    $hospital_lon =
                        isset(
                            $properties["lon"]
                        )
                        ?
                        (float)
                        $properties["lon"]
                        :
                        null;


                    /*
                    |--------------------------------------------------------------------------
                    | PHONE
                    |--------------------------------------------------------------------------
                    */

                    $phone =
                        trim(
                            (string)(
                                $properties["phone"]
                                ??
                                $properties["contact:phone"]
                                ??
                                ""
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | WEBSITE
                    |--------------------------------------------------------------------------
                    */

                    $website =
                        trim(
                            (string)(
                                $properties["website"]
                                ??
                                $properties["contact:website"]
                                ??
                                ""
                            )
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | GOOGLE MAP LINK
                    |--------------------------------------------------------------------------
                    */

                    $map_link = "";


                    if (
                        $hospital_lat !== null
                        &&
                        $hospital_lon !== null
                    )
                    {

                        $map_link =
                            "https://www.google.com/maps/search/?api=1&query="
                            .
                            urlencode(
                                $hospital_lat
                                .
                                ","
                                .
                                $hospital_lon
                            );

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE HOSPITAL
                    |--------------------------------------------------------------------------
                    */

                    $hospitals[] = [

                        "place_id" =>
                            $place_id,

                        "name" =>
                            $name,

                        "address" =>
                            $address,

                        "latitude" =>
                            $hospital_lat,

                        "longitude" =>
                            $hospital_lon,

                        "phone" =>
                            $phone,

                        "website" =>
                            $website,

                        "map_link" =>
                            $map_link

                    ];

                }


                /*
                |--------------------------------------------------------------------------
                | SUCCESS MESSAGE
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($hospitals)
                )
                {

                    $success_message =
                        "Hospitals found in "
                        .
                        $searched_city
                        .
                        ".";

                }


                else
                {

                    $error_message =
                        "No hospitals found in "
                        .
                        $searched_city
                        .
                        ".";

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
|
| Because Geoapify returns only the requested page,
| we determine whether a next page probably exists
| from the number of results returned.
|
*/

$has_next_page =
    count($hospitals)
    >=
    $per_page;


$has_previous_page =
    $current_page > 1;

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
        Hospital Search
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

            max-width: 1200px;

            margin: 0 auto;

        }


        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .page-header {

            margin-bottom: 25px;

        }


        .page-header h1 {

            margin: 0 0 8px;

            font-size: 30px;

            color:
                #172033;

        }


        .page-header p {

            margin: 0;

            color:
                #7b8497;

            font-size: 14px;

        }


        /* =====================================================
           SEARCH CARD
        ====================================================== */

        .search-card {

            background:
                #ffffff;

            padding: 25px;

            border-radius: 15px;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    0.05
                );

            margin-bottom: 20px;

        }


        .search-form {

            display: grid;

            grid-template-columns:
                1fr
                1fr
                1fr
                auto;

            gap: 15px;

            align-items: end;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 600;

            color:
                #31394d;

        }


        .form-group input {

            width: 100%;

            padding: 12px 13px;

            border:
                1px solid #dce2ed;

            border-radius: 8px;

            font-size: 14px;

            outline: none;

            background:
                #ffffff;

        }


        .form-group input:focus {

            border-color:
                #635bff;

            box-shadow:
                0 0 0 3px
                rgba(
                    99,
                    91,
                    255,
                    0.08
                );

        }


        .search-btn {

            border: none;

            padding:
                12px 22px;

            border-radius: 8px;

            background:
                #635bff;

            color:
                #ffffff;

            font-size: 14px;

            font-weight: 600;

            cursor: pointer;

            white-space:
                nowrap;

        }


        .search-btn:hover {

            background:
                #5148e8;

        }


        /* =====================================================
           MESSAGES
        ====================================================== */

        .message {

            background:
                #ffffff;

            padding:
                15px 18px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-size: 14px;

        }


        .success {

            color:
                #16855b;

        }


        .error {

            color:
                #c62828;

        }


        /* =====================================================
           HOSPITAL CARD
        ====================================================== */

        .hospital-card {

            background:
                #ffffff;

            border-radius: 15px;

            box-shadow:
                0 5px 20px
                rgba(
                    0,
                    0,
                    0,
                    0.05
                );

            overflow:
                hidden;

        }


        .hospital-header {

            padding:
                22px 25px;

            border-bottom:
                1px solid #edf0f5;

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                15px;

        }


        .hospital-header h2 {

            margin: 0;

            font-size: 20px;

        }


        .hospital-count {

            background:
                #eef0ff;

            color:
                #635bff;

            padding:
                7px 13px;

            border-radius:
                20px;

            font-size: 12px;

            font-weight:
                600;

            white-space:
                nowrap;

        }


        /* =====================================================
           TABLE
        ====================================================== */

        .table-wrapper {

            overflow-x:
                auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

        }


        thead th {

            text-align:
                left;

            padding:
                13px 15px;

            background:
                #f7f8fc;

            color:
                #737c90;

            font-size:
                11px;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                .3px;

            white-space:
                nowrap;

        }


        tbody td {

            padding:
                16px 15px;

            border-bottom:
                1px solid #edf0f5;

            font-size:
                13px;

            vertical-align:
                middle;

        }


        tbody tr:last-child td {

            border-bottom:
                none;

        }


        tbody tr:hover td {

            background:
                #fafaff;

        }


        .hospital-name {

            font-weight:
                700;

            color:
                #172033;

            min-width:
                190px;

        }


        .hospital-address {

            color:
                #697389;

            line-height:
                1.45;

            min-width:
                350px;

            max-width:
                550px;

        }


        .actions {

            display:
                flex;

            gap:
                7px;

            white-space:
                nowrap;

        }


        .btn {

            display:
                inline-block;

            padding:
                8px 12px;

            border-radius:
                7px;

            text-decoration:
                none;

            font-size:
                12px;

            font-weight:
                600;

        }


        .details-btn {

            background:
                #635bff;

            color:
                #ffffff;

        }


        .details-btn:hover {

            background:
                #5148e8;

        }


        .map-btn {

            background:
                #eef0ff;

            color:
                #5148e8;

        }


        .map-btn:hover {

            background:
                #e1e4ff;

        }


        /* =====================================================
           PAGINATION
        ====================================================== */

        .pagination {

            padding:
                20px 25px;

            border-top:
                1px solid #edf0f5;

            display:
                flex;

            justify-content:
                center;

            align-items:
                center;

            gap:
                8px;

        }


        .page-btn {

            min-width:
                38px;

            height:
                38px;

            display:
                inline-flex;

            align-items:
                center;

            justify-content:
                center;

            padding:
                0 12px;

            border-radius:
                8px;

            text-decoration:
                none;

            background:
                #f1f3f8;

            color:
                #40485c;

            font-size:
                13px;

            font-weight:
                600;

        }


        .page-btn:hover {

            background:
                #e6e8f5;

        }


        .page-btn.active {

            background:
                #635bff;

            color:
                #ffffff;

        }


        .page-info {

            margin:
                0 10px;

            color:
                #777f92;

            font-size:
                13px;

        }


        /* =====================================================
           EMPTY STATE
        ====================================================== */

        .empty-state {

            padding:
                50px 25px;

            text-align:
                center;

            color:
                #7b8497;

        }


        .empty-state h3 {

            margin:
                0 0 8px;

            color:
                #30384b;

        }


        .empty-state p {

            margin:
                0;

            font-size:
                13px;

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (
            max-width: 850px
        )
        {

            body {

                padding:
                    15px;

            }


            .search-form {

                grid-template-columns:
                    1fr;

            }


            .search-btn {

                width:
                    100%;

            }


            .hospital-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <h1>
            Find Hospitals
        </h1>

        <p>
            Search hospitals available in your selected city.
        </p>

    </div>


    <!-- =====================================================
         SEARCH FORM
    ====================================================== -->

    <div class="search-card">

        <form
            method="POST"
            class="search-form"
        >


            <!-- COUNTRY -->

            <div class="form-group">

                <label>
                    Country
                </label>

                <input
                    type="text"
                    name="country"
                    value="<?= e(
                        $searched_country
                        ?: "India"
                    ) ?>"
                    placeholder="Enter country"
                    required
                >

            </div>


            <!-- STATE -->

            <div class="form-group">

                <label>
                    State
                </label>

                <input
                    type="text"
                    name="state"
                    value="<?= e(
                        $searched_state
                        ?: "Gujarat"
                    ) ?>"
                    placeholder="Enter state"
                    required
                >

            </div>


            <!-- CITY -->

            <div class="form-group">

                <label>
                    City
                </label>

                <input
                    type="text"
                    name="city"
                    value="<?= e(
                        $searched_city
                        ?: "Gandhinagar"
                    ) ?>"
                    placeholder="Enter city"
                    required
                >

            </div>


            <!-- BUTTON -->

            <button
                type="submit"
                class="search-btn"
            >

                Search Hospitals

            </button>


        </form>

    </div>


    <!-- =====================================================
         SUCCESS MESSAGE
    ====================================================== -->

    <?php if (
        $success_message !== ""
    ): ?>

        <div class="message success">

            <?= e(
                $success_message
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if (
        $error_message !== ""
    ): ?>

        <div class="message error">

            <?= e(
                $error_message
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         HOSPITAL RESULTS
    ====================================================== -->

    <?php if (
        !empty($hospitals)
    ): ?>


        <div class="hospital-card">


            <!-- HEADER -->

            <div class="hospital-header">

                <h2>

                    Hospitals in
                    <?= e(
                        $searched_city
                    ) ?>

                </h2>


                <span class="hospital-count">

                    <?= count(
                        $hospitals
                    ) ?>

                    Shown

                </span>

            </div>


            <!-- TABLE -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Hospital Name
                            </th>

                            <th>
                                Address
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $hospitals
                        as $hospital
                    ): ?>


                        <tr>


                            <!-- NAME -->

                            <td>

                                <div
                                    class="hospital-name"
                                >

                                    <?= e(
                                        $hospital["name"]
                                    ) ?>

                                </div>

                            </td>


                            <!-- ADDRESS -->

                            <td>

                                <div
                                    class="hospital-address"
                                >

                                    <?= e(
                                        $hospital["address"]
                                    ) ?>

                                </div>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <div
                                    class="actions"
                                >


                                    <?php if (
                                        $hospital["place_id"]
                                        !==
                                        ""
                                    ): ?>

                                        <a
                                            href="hospital_search1.php?place_id=<?= urlencode(
                                                $hospital["place_id"]
                                            ) ?>"
                                            class="btn details-btn"
                                        >

                                            View Details

                                        </a>

                                    <?php endif; ?>


                                    <?php if (
                                        $hospital["map_link"]
                                        !==
                                        ""
                                    ): ?>

                                        <a
                                            href="<?= e(
                                                $hospital["map_link"]
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn map-btn"
                                        >

                                            Map

                                        </a>

                                    <?php endif; ?>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 PAGINATION
            ================================================== -->

            <?php if (
                $has_previous_page
                ||
                $has_next_page
            ): ?>


                <div class="pagination">


                    <?php if (
                        $has_previous_page
                    ): ?>


                        <a
                            href="?page=<?= $current_page - 1 ?>"
                            class="page-btn"
                        >

                            ← Previous

                        </a>


                    <?php endif; ?>


                    <span
                        class="page-info"
                    >

                        Page
                        <?= $current_page ?>

                    </span>


                    <?php if (
                        $has_next_page
                    ): ?>


                        <a
                            href="?page=<?= $current_page + 1 ?>"
                            class="page-btn"
                        >

                            Next →

                        </a>


                    <?php endif; ?>


                </div>


            <?php endif; ?>


        </div>


    <?php elseif (
        $error_message === ""
        &&
        $searched_city !== ""
    ): ?>


        <!-- =================================================
             EMPTY STATE
        ================================================== -->

        <div class="hospital-card">

            <div class="empty-state">

                <h3>
                    No Hospitals Found
                </h3>

                <p>

                    No hospitals were found
                    in
                    <?= e(
                        $searched_city
                    ) ?>.

                </p>

            </div>

        </div>


    <?php endif; ?>


</div>


</body>

</html>