<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . "/../includes/geoapify.php";

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


/* =========================================================
   ESCAPE
========================================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? "",
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   GEOAPIFY GET
========================================================= */

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

    if ($response === false) {

        $error = curl_error($ch);

        curl_close($ch);

        return [
            "success" => false,
            "http_code" => 0,
            "error" => $error,
            "data" => null
        ];
    }

    $http_code = (int)curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    $data = json_decode(
        $response,
        true
    );

    return [
        "success" =>
            $http_code >= 200 &&
            $http_code < 300,

        "http_code" => $http_code,

        "error" => null,

        "data" => $data
    ];
}


/* =========================================================
   PAGE
========================================================= */

if (isset($_GET["page"])) {

    $current_page = max(
        1,
        (int)$_GET["page"]
    );
}


/* =========================================================
   POST SEARCH
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $searched_country = trim(
        (string)(
            $_POST["country"] ?? ""
        )
    );

    $searched_state = trim(
        (string)(
            $_POST["state"] ?? ""
        )
    );

    $searched_city = trim(
        (string)(
            $_POST["city"] ?? ""
        )
    );

    $_SESSION["mobile_hospital_search"] = [
        "country" => $searched_country,
        "state" => $searched_state,
        "city" => $searched_city
    ];

    $current_page = 1;
}


/* =========================================================
   LOAD SESSION SEARCH
========================================================= */

if (
    $searched_country === "" &&
    isset($_SESSION["mobile_hospital_search"])
) {

    $searched_country =
        (string)(
            $_SESSION["mobile_hospital_search"]["country"]
            ?? ""
        );

    $searched_state =
        (string)(
            $_SESSION["mobile_hospital_search"]["state"]
            ?? ""
        );

    $searched_city =
        (string)(
            $_SESSION["mobile_hospital_search"]["city"]
            ?? ""
        );
}


/* =========================================================
   SEARCH
========================================================= */

if (
    $searched_country !== "" &&
    $searched_state !== "" &&
    $searched_city !== ""
) {

    /* -----------------------------------------------------
       STEP 1: GEOCODING
    ----------------------------------------------------- */

    $location_text =
        $searched_city .
        ", " .
        $searched_state .
        ", " .
        $searched_country;

    $geocode_parameters = [

        "text" => $location_text,

        "format" => "json",

        "limit" => 1,

        "apiKey" => $geoapify_api_key

    ];

    $geocode_url =
        $geoapify_geocode_url .
        "?" .
        http_build_query(
            $geocode_parameters
        );

    $geocode_result =
        geoapifyGet(
            $geocode_url
        );


    if (!$geocode_result["success"]) {

        $error_message =
            "Location search failed. HTTP Code: " .
            $geocode_result["http_code"];

    }

    elseif (
        empty(
            $geocode_result["data"]["results"]
        )
    ) {

        $error_message =
            "Location not found for " .
            $location_text .
            ".";

    }

    else {

        $location =
            $geocode_result["data"]["results"][0];

        $city_place_id =
            (string)(
                $location["place_id"] ?? ""
            );


        if ($city_place_id === "") {

            $error_message =
                "City boundary information was not found.";

        }

        else {

            /* -------------------------------------------------
               STEP 2: HOSPITAL SEARCH
            ------------------------------------------------- */

            $offset =
                (
                    $current_page - 1
                ) *
                $per_page;


            $places_parameters = [

                "categories" =>
                    "healthcare.hospital",

                "filter" =>
                    "place:" .
                    $city_place_id,

                "limit" =>
                    $per_page,

                "offset" =>
                    $offset,

                "lang" =>
                    "en",

                "apiKey" =>
                    $geoapify_api_key

            ];


            $places_url =
                $geoapify_places_url .
                "?" .
                http_build_query(
                    $places_parameters
                );


            $places_result =
                geoapifyGet(
                    $places_url
                );


            if (
                !$places_result["success"]
            ) {

                $error_message =
                    "Hospital search failed. HTTP Code: " .
                    $places_result["http_code"];

            }

            else {

                $features =
                    $places_result["data"]["features"]
                    ?? [];

                $total_found =
                    count($features);


                foreach (
                    $features as $feature
                ) {

                    $properties =
                        $feature["properties"]
                        ?? [];


                    if (
                        empty($properties)
                    ) {
                        continue;
                    }


                    $name =
                        trim(
                            (string)(
                                $properties["name"]
                                ?? ""
                            )
                        );


                    if ($name === "") {

                        $name =
                            "Unnamed Hospital";
                    }


                    $place_id =
                        (string)(
                            $properties["place_id"]
                            ?? ""
                        );


                    $address =
                        trim(
                            (string)(
                                $properties["formatted"]
                                ?? ""
                            )
                        );


                    $hospital_lat =
                        isset(
                            $properties["lat"]
                        )
                        ?
                        (float)$properties["lat"]
                        :
                        null;


                    $hospital_lon =
                        isset(
                            $properties["lon"]
                        )
                        ?
                        (float)$properties["lon"]
                        :
                        null;


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


                    $map_link = "";


                    if (
                        $hospital_lat !== null &&
                        $hospital_lon !== null
                    ) {

                        $map_link =
                            "https://www.google.com/maps/search/?api=1&query=" .
                            urlencode(
                                $hospital_lat .
                                "," .
                                $hospital_lon
                            );
                    }


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


                if (
                    !empty($hospitals)
                ) {

                    $success_message =
                        count($hospitals) .
                        " hospitals found in " .
                        $searched_city .
                        ".";

                }

                else {

                    $error_message =
                        "No hospitals found in " .
                        $searched_city .
                        ".";
                }
            }
        }
    }
}


/* =========================================================
   PAGINATION
========================================================= */

$has_next_page =
    count($hospitals) >= $per_page;

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

<title>Find Hospitals</title>

<style>

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Arial,
        sans-serif;

    background:
        #f4f7fc;

    color:
        #172033;

}

.mobile-page {

    width: 100%;

    max-width: 700px;

    margin: 0 auto;

    min-height: 100vh;

    padding:
        18px 15px 100px;

}


/* HEADER */

.topbar {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 20px;

}

.back {

    width: 42px;

    height: 42px;

    border-radius: 13px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #ffffff;

    color: #6355f4;

    text-decoration: none;

    font-size: 22px;

    box-shadow:
        0 5px 18px rgba(0,0,0,.06);

}

.topbar h1 {

    margin: 0;

    font-size: 23px;

}

.topbar p {

    margin: 3px 0 0;

    font-size: 12px;

    color: #7c8598;

}


/* SEARCH CARD */

.search-card {

    background: #ffffff;

    padding: 17px;

    border-radius: 18px;

    box-shadow:
        0 8px 25px rgba(0,0,0,.06);

    margin-bottom: 15px;

}

.form-group {

    margin-bottom: 12px;

}

.form-group:last-of-type {

    margin-bottom: 14px;

}

.form-group label {

    display: block;

    font-size: 11px;

    font-weight: 700;

    margin-bottom: 6px;

    color: #454d61;

}

.form-group input {

    width: 100%;

    height: 46px;

    border: 1px solid #e0e4ee;

    border-radius: 11px;

    padding: 0 13px;

    outline: none;

    font-size: 14px;

}

.form-group input:focus {

    border-color: #6355f4;

    box-shadow:
        0 0 0 3px rgba(99,91,255,.08);

}

.search-btn {

    width: 100%;

    height: 47px;

    border: 0;

    border-radius: 12px;

    background:
        linear-gradient(
            135deg,
            #6355f4,
            #5143e8
        );

    color: #ffffff;

    font-size: 14px;

    font-weight: 700;

    cursor: pointer;

}


/* MESSAGE */

.message {

    background: #ffffff;

    padding: 13px 15px;

    border-radius: 12px;

    margin-bottom: 14px;

    font-size: 13px;

}

.success {

    color: #16855b;

}

.error {

    color: #c62828;

}


/* RESULT HEADER */

.result-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin: 20px 2px 12px;

}

.result-header h2 {

    margin: 0;

    font-size: 17px;

}

.count {

    background: #eeeeff;

    color: #6355f4;

    padding: 6px 10px;

    border-radius: 20px;

    font-size: 10px;

    font-weight: 700;

}


/* HOSPITAL CARD */

.hospital-list {

    display: flex;

    flex-direction: column;

    gap: 12px;

}

.hospital-item {

    background: #ffffff;

    border-radius: 17px;

    padding: 15px;

    box-shadow:
        0 6px 22px rgba(0,0,0,.05);

}

.hospital-icon {

    width: 44px;

    height: 44px;

    border-radius: 13px;

    background: #eeeeff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 21px;

    margin-bottom: 11px;

}

.hospital-name {

    font-size: 16px;

    font-weight: 750;

    line-height: 1.35;

    margin-bottom: 7px;

}

.hospital-address {

    font-size: 12px;

    line-height: 1.55;

    color: #737d91;

    margin-bottom: 13px;

}

.actions {

    display: flex;

    gap: 8px;

}

.btn {

    flex: 1;

    min-height: 40px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 10px;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

}

.details-btn {

    background: #6355f4;

    color: #ffffff;

}

.map-btn {

    background: #eef0ff;

    color: #5143e8;

}


/* EMPTY */

.empty {

    background: #ffffff;

    padding: 35px 20px;

    border-radius: 17px;

    text-align: center;

    color: #7b8497;

}

.empty-icon {

    font-size: 38px;

    margin-bottom: 10px;

}

.empty h3 {

    margin: 0 0 6px;

    color: #30384b;

}

.empty p {

    margin: 0;

    font-size: 13px;

}


/* PAGINATION */

.pagination {

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 8px;

    margin-top: 18px;

}

.page-btn {

    padding: 10px 13px;

    border-radius: 9px;

    background: #ffffff;

    color: #6355f4;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

}

.page-info {

    font-size: 12px;

    color: #737d91;

}


/* DESKTOP */

@media (min-width: 701px) {

    .mobile-page {

        max-width: 1100px;

    }

    .search-form {

        display: grid;

        grid-template-columns:
            1fr 1fr 1fr auto;

        gap: 12px;

        align-items: end;

    }

    .form-group {

        margin: 0;

    }

    .search-btn {

        width: auto;

        padding: 0 25px;

    }

    .hospital-list {

        display: grid;

        grid-template-columns:
            repeat(2, 1fr);

    }

}

</style>

<link rel="stylesheet" href="css/common.css">
</head>

<body>

<div class="mobile-page">

    <div class="topbar">

        <a
            href="index.php"
            class="back"
        >
            ‹
        </a>

        <div>

            <h1>
                Find Hospitals
            </h1>

            <p>
                Find hospitals near your selected city
            </p>

        </div>

    </div>


    <div class="search-card">

        <form
            method="POST"
            class="search-form"
        >

            <div class="form-group">

                <label>
                    COUNTRY
                </label>

                <input
                    type="text"
                    name="country"
                    value="<?= e(
                        $searched_country ?: "India"
                    ) ?>"
                    placeholder="Enter country"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    STATE
                </label>

                <input
                    type="text"
                    name="state"
                    value="<?= e(
                        $searched_state ?: "Gujarat"
                    ) ?>"
                    placeholder="Enter state"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    CITY
                </label>

                <input
                    type="text"
                    name="city"
                    value="<?= e(
                        $searched_city ?: "Gandhinagar"
                    ) ?>"
                    placeholder="Enter city"
                    required
                >

            </div>


            <button
                type="submit"
                class="search-btn"
            >
                🔍 Search Hospitals
            </button>

        </form>

    </div>


    <?php if ($success_message !== ""): ?>

        <div class="message success">
            ✓ <?= e($success_message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error_message !== ""): ?>

        <div class="message error">
            ⚠ <?= e($error_message) ?>
        </div>

    <?php endif; ?>


    <?php if (!empty($hospitals)): ?>

        <div class="result-header">

            <h2>
                Hospitals in
                <?= e($searched_city) ?>
            </h2>

            <span class="count">
                <?= count($hospitals) ?> Found
            </span>

        </div>


        <div class="hospital-list">

            <?php foreach (
                $hospitals as $hospital
            ): ?>

                <div class="hospital-item">

                    <div class="hospital-icon">
                        🏥
                    </div>

                    <div class="hospital-name">

                        <?= e(
                            $hospital["name"]
                        ) ?>

                    </div>

                    <div class="hospital-address">

                        📍
                        <?= e(
                            $hospital["address"]
                        ) ?>

                    </div>


                    <div class="actions">

                        <?php if (
                            $hospital["place_id"] !== ""
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
                            $hospital["map_link"] !== ""
                        ): ?>

                            <a
                                href="<?= e(
                                    $hospital["map_link"]
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn map-btn"
                            >
                                📍 Map
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <?php if (
            $has_previous_page ||
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


                <span class="page-info">

                    Page <?= $current_page ?>

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


    <?php elseif (
        $error_message === "" &&
        $searched_city !== ""
    ): ?>

        <div class="empty">

            <div class="empty-icon">
                🏥
            </div>

            <h3>
                No Hospitals Found
            </h3>

            <p>
                No hospitals were found in
                <?= e($searched_city) ?>.
            </p>

        </div>

    <?php endif; ?>

</div>

</body>

</html>