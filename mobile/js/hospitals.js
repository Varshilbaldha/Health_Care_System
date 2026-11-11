const searchInput =
    document.getElementById("hospitalSearch");

const locationBtn =
    document.getElementById("locationBtn");

const results =
    document.getElementById("hospitalResults");

const statusBox =
    document.getElementById("searchStatus");


/*
|--------------------------------------------------------------------------
| SEARCH HOSPITALS
|--------------------------------------------------------------------------
|
| OpenStreetMap Nominatim is used for public hospital/location search.
|
*/

async function searchHospitals(query) {

    if (!query.trim()) {
        return;
    }

    results.innerHTML = `
        <div class="loading">
            🔎 Searching hospitals...
        </div>
    `;

    statusBox.textContent =
        "Searching nearby hospitals...";

    try {

        const url =
            "https://nominatim.openstreetmap.org/search" +
            "?format=json" +
            "&q=" +
            encodeURIComponent(query + " hospital") +
            "&limit=10" +
            "&addressdetails=1";

        const response =
            await fetch(url, {
                headers: {
                    "Accept":
                        "application/json"
                }
            });

        if (!response.ok) {
            throw new Error(
                "Search failed"
            );
        }

        const data =
            await response.json();

        displayHospitals(data);

    } catch (error) {

        results.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">⚠️</div>
                <h2>Search Error</h2>
                <p>
                    Please check your internet
                    connection and try again.
                </p>
            </div>
        `;

        statusBox.textContent =
            "Unable to search hospitals.";
    }
}


/*
|--------------------------------------------------------------------------
| SHOW HOSPITALS
|--------------------------------------------------------------------------
*/

function displayHospitals(hospitals) {

    if (!hospitals.length) {

        results.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🏥</div>

                <h2>
                    No Hospitals Found
                </h2>

                <p>
                    Try another hospital name
                    or city.
                </p>
            </div>
        `;

        statusBox.textContent =
            "No hospitals found.";

        return;
    }


    statusBox.textContent =
        hospitals.length +
        " hospitals found";


    results.innerHTML =
        hospitals.map(
            hospital => {

                const name =
                    hospital.display_name
                    .split(",")[0];


                const address =
                    hospital.display_name;


                const lat =
                    hospital.lat;

                const lon =
                    hospital.lon;


                return `

                    <div class="hospital-card">

                        <div class="hospital-top">

                            <div class="hospital-icon">
                                🏥
                            </div>

                            <div class="hospital-info">

                                <h3>
                                    ${escapeHTML(name)}
                                </h3>

                                <div class="hospital-location">
                                    ${escapeHTML(address)}
                                </div>

                            </div>

                        </div>


                        <div class="hospital-actions">

                            <a
                                class="map-btn"
                                target="_blank"
                                href="https://www.google.com/maps/search/?api=1&query=${lat},${lon}"
                            >
                                📍 Map
                            </a>


                            <button
                                class="book-btn"
                                onclick="bookHospital(
                                    '${escapeAttribute(name)}'
                                )"
                            >
                                📅 Book
                            </button>

                        </div>

                    </div>

                `;

            }
        ).join("");
}


/*
|--------------------------------------------------------------------------
| FIND NEARBY
|--------------------------------------------------------------------------
*/

locationBtn.addEventListener(
    "click",
    function () {

        if (!navigator.geolocation) {

            statusBox.textContent =
                "Location is not supported.";

            return;
        }


        locationBtn.textContent =
            "📍 Finding hospitals...";


        navigator.geolocation.getCurrentPosition(

            async function(position) {

                const lat =
                    position.coords.latitude;

                const lon =
                    position.coords.longitude;


                try {

                    const url =
                        "https://nominatim.openstreetmap.org/reverse" +
                        "?format=json" +
                        "&lat=" + lat +
                        "&lon=" + lon;


                    const response =
                        await fetch(url);


                    const location =
                        await response.json();


                    const city =
                        location.address.city ||
                        location.address.town ||
                        location.address.village ||
                        "";


                    const search =
                        city
                            ? city + " hospital"
                            : "hospital";


                    searchInput.value =
                        city;


                    searchHospitals(
                        search
                    );

                } catch (error) {

                    statusBox.textContent =
                        "Unable to find your location.";

                }


                locationBtn.textContent =
                    "📍 Find Hospitals Near Me";

            },

            function() {

                statusBox.textContent =
                    "Please allow location access.";

                locationBtn.textContent =
                    "📍 Find Hospitals Near Me";

            }
        );

    }
);


/*
|--------------------------------------------------------------------------
| SEARCH INPUT
|--------------------------------------------------------------------------
*/

let searchTimer;


searchInput.addEventListener(
    "input",
    function() {

        clearTimeout(searchTimer);


        const value =
            this.value.trim();


        if (value.length < 3) {

            return;
        }


        searchTimer =
            setTimeout(
                function() {

                    searchHospitals(value);

                },
                600
            );

    }
);


/*
|--------------------------------------------------------------------------
| BOOK HOSPITAL
|--------------------------------------------------------------------------
*/

function bookHospital(name) {

    /*
     * Existing website booking page.
     *
     * Existing website files are NOT modified.
     */

    window.location.href =
        "book.php?hospital=" +
        encodeURIComponent(name);
}


/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

function escapeHTML(value) {

    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}


function escapeAttribute(value) {

    return String(value)
        .replaceAll("\\", "\\\\")
        .replaceAll("'", "\\'");
}