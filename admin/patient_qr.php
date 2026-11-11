<?php declare(strict_types=1);
require_once __DIR__ . '/hospital_context.php';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <title>App Download QR</title>

    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/hospital_modules.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
    >

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>

<body>

<div class="dashboard">

    <?php require 'sidebar.php'; ?>

    <main class="main-content">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <h1>Care Your Health App</h1>

                <p>
                    Scan this QR code to download the Care Your Health Android application.
                </p>

            </div>

        </div>


        <!-- QR CARD -->

        <div class="qr-card">

            <!-- QR CODE -->

            <div id="qrcode">
                 <img class="qr-code"src="../qr_mobile.png" alt="Care Your Health QR Code"
    >


            </div>


            <!-- HOSPITAL NAME -->

          


            <p>
                Scan with a phone to download the application.
            </p>


            <!-- COPY LINK BUTTON -->

            <button
                class="primary-btn"
                onclick="copyAppLink()"
            >
                <i class="fa-solid fa-copy"></i>
                Copy Link
            </button>


            <!-- DOWNLOAD LINK -->

            <input
                id="qrUrl"
                readonly
                value=" http://10.201.107.110/Hospital_Management_System/CareYourHealth.apk"
            >


            <!-- DIRECT DOWNLOAD BUTTON -->

            <br><br>

            <a
                href="../CareYourHealth.apk"
                class="primary-btn"
                download
            >
                <i class="fa-solid fa-download"></i>
                Download App
            </a>

        </div>

    </main>

</div>


<script>

    /*
     * Build the complete APK URL automatically.
     *
     

    const appPath = "CareYourHealth.apk";

    const appUrl =
        window.location.origin + appPath;


    /*
     * Put complete URL into input
     */

    document.getElementById("qrUrl").value = appUrl;


    /*
     * Generate QR Code
     */

    new QRCode(
        document.getElementById("qrcode"),
        {
            text: appUrl,

            width: 220,

            height: 220
        }
    );


    /*
     * Copy APK link
     */

    function copyAppLink() {

        const input =
            document.getElementById("qrUrl");

        navigator.clipboard
            .writeText(input.value)
            .then(function () {

                alert("Download link copied!");

            })
            .catch(function () {

                input.select();

                document.execCommand("copy");

                alert("Download link copied!");

            });

    }

</script>

</body>

</html>