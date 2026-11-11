/*========================================
    PATIENT MOBILE APP
    Smart Hospital Management System
========================================*/

"use strict";


/*========================================
    PAGE NAVIGATION
========================================*/

function goHome() {

    window.location.href = "index.html";

}


function openAppointments() {

    window.location.href =
        "../my_appointments.php";

}


function openBooking() {

    window.location.href =
        "../book.php";

}


function openHospitals() {

    window.location.href =
        "../hospitals.php";

}


function openProfile() {

    window.location.href =
        "../profile.php";

}


function openNotifications() {

    window.location.href =
        "../notifications.php";

}


function openAIChat() {

    window.location.href =
        "../ai_chat.php";

}


/*========================================
    PATIENT DATA
========================================*/

/*
|--------------------------------------------------------------------------
| Temporary patient data
|--------------------------------------------------------------------------
| Later we can connect this with PHP session.
|--------------------------------------------------------------------------
*/

const patient = {

    name: "Patient",

    initial: "P"

};


/*========================================
    SET PATIENT NAME
========================================*/

const patientName =
    document.getElementById(
        "patientName"
    );


const profileAvatar =
    document.getElementById(
        "profileAvatar"
    );


if (patientName) {

    patientName.textContent =
        patient.name;

}


if (profileAvatar) {

    profileAvatar.textContent =
        patient.initial;

}


/*========================================
    HOSPITAL SEARCH
========================================*/

const hospitalSearch =
    document.getElementById(
        "hospitalSearch"
    );


const searchResults =
    document.getElementById(
        "searchResults"
    );


const hospitals = [

    {
        name: "Smart Hospital",
        location: "Ahmedabad, Gujarat"
    },

    {
        name: "City Care Hospital",
        location: "Ahmedabad, Gujarat"
    },

    {
        name: "Life Care Hospital",
        location: "Rajkot, Gujarat"
    },

    {
        name: "Shree Hospital",
        location: "Surat, Gujarat"
    }

];


if (
    hospitalSearch
    &&
    searchResults
) {

    hospitalSearch.addEventListener(
        "input",
        function () {

            const keyword =
                this.value
                    .trim()
                    .toLowerCase();


            /*------------------------------------
                EMPTY SEARCH
            ------------------------------------*/

            if (keyword === "") {

                searchResults.style.display =
                    "none";

                searchResults.innerHTML =
                    "";

                return;

            }


            /*------------------------------------
                FILTER HOSPITALS
            ------------------------------------*/

            const results =
                hospitals.filter(
                    function (hospital) {

                        return (

                            hospital.name
                                .toLowerCase()
                                .includes(keyword)

                            ||

                            hospital.location
                                .toLowerCase()
                                .includes(keyword)

                        );

                    }
                );


            /*------------------------------------
                NO RESULT
            ------------------------------------*/

            if (
                results.length === 0
            ) {

                searchResults.innerHTML = `

                    <div class="search-result">

                        <div class="search-result-icon">
                            🔎
                        </div>

                        <div>

                            <strong>
                                No hospital found
                            </strong>

                            <span>
                                Try another hospital name
                            </span>

                        </div>

                    </div>

                `;

            }


            /*------------------------------------
                SHOW RESULTS
            ------------------------------------*/

            else {

                searchResults.innerHTML =
                    results.map(
                        function (hospital) {

                            return `

                                <div
                                    class="search-result"
                                    data-hospital="${hospital.name}"
                                >

                                    <div
                                        class="search-result-icon"
                                    >
                                        🏥
                                    </div>

                                    <div>

                                        <strong>
                                            ${hospital.name}
                                        </strong>

                                        <span>
                                            ${hospital.location}
                                        </span>

                                    </div>

                                </div>

                            `;

                        }
                    ).join("");


                /*--------------------------------
                    RESULT CLICK
                --------------------------------*/

                const resultItems =
                    searchResults.querySelectorAll(
                        ".search-result"
                    );


                resultItems.forEach(
                    function (item) {

                        item.addEventListener(
                            "click",
                            function () {

                                const hospitalName =
                                    this.dataset.hospital;

                                selectHospital(
                                    hospitalName
                                );

                            }
                        );

                    }
                );

            }


            searchResults.style.display =
                "block";

        }
    );

}


/*========================================
    SELECT HOSPITAL
========================================*/

function selectHospital(
    name
) {

    if (hospitalSearch) {

        hospitalSearch.value =
            name;

    }


    if (searchResults) {

        searchResults.style.display =
            "none";

    }


    /*
    |--------------------------------------------------------------------------
    | Open existing PHP booking system.
    |--------------------------------------------------------------------------
    */

    window.location.href =
        "../book.php";

}


/*========================================
    MENU BUTTON
========================================*/

const menuButton =
    document.getElementById(
        "menuButton"
    );


if (menuButton) {

    menuButton.addEventListener(
        "click",
        function () {

            openProfile();

        }
    );

}


/*========================================
    DOM READY CHECK
========================================*/

document.addEventListener(
    "DOMContentLoaded",
    function () {

        console.log(
            "Smart Hospital Patient Mobile App loaded successfully."
        );

    }
);