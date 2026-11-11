/*
|--------------------------------------------------------------------------
| PATIENT DASHBOARD NAVIGATION
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| HOSPITAL SEARCH
|--------------------------------------------------------------------------
*/

function openHospitalSearch()
{
    window.location.href =
        "hospital_search.php";
}


/*
|--------------------------------------------------------------------------
| VIEW HOSPITALS
|--------------------------------------------------------------------------
|
| Country, State and City are sent to hospital_search.php.
|--------------------------------------------------------------------------
*/

function viewHospitals()
{
    const country =
        document.getElementById("country").value.trim();

    const state =
        document.getElementById("state").value.trim();

    const city =
        document.getElementById("city").value.trim();


    if (country === "")
    {
        alert("Please enter country.");
        document.getElementById("country").focus();
        return;
    }


    if (state === "")
    {
        alert("Please enter state.");
        document.getElementById("state").focus();
        return;
    }


    if (city === "")
    {
        alert("Please enter city.");
        document.getElementById("city").focus();
        return;
    }


    const url =
        "hospital_search.php"
        + "?country="
        + encodeURIComponent(country)
        + "&state="
        + encodeURIComponent(state)
        + "&city="
        + encodeURIComponent(city);


    window.location.href = url;
}


/*
|--------------------------------------------------------------------------
| FOCUS HOSPITAL SEARCH
|--------------------------------------------------------------------------
*/

function focusHospitalSearch()
{
    const searchSection =
        document.querySelector(
            ".hospital-search-section"
        );


    if (searchSection)
    {
        searchSection.scrollIntoView({
            behavior: "smooth",
            block: "start"
        });
    }


    setTimeout(
        function()
        {
            const country =
                document.getElementById("country");

            if (country)
            {
                country.focus();
            }
        },
        500
    );
}


/*
|--------------------------------------------------------------------------
| BOOK APPOINTMENT
|--------------------------------------------------------------------------
*/

function openBooking()
{
    window.location.href =
        "book.php";
}


/*
|--------------------------------------------------------------------------
| AI ASSISTANT
|--------------------------------------------------------------------------
*/

function openAIChat()
{
    window.location.href =
        "../mobile/ai/assistant.php";
}
/*
|--------------------------------------------------------------------------
| AI MEDICAL IMAGE ANALYSIS
|--------------------------------------------------------------------------
*/

function openVisionAnalysis()
{
    window.location.href =
        "../mobile/ai/vision_api.php";
}


/*
|--------------------------------------------------------------------------
| APPOINTMENTS
|--------------------------------------------------------------------------
*/

function openAppointments()
{
    window.location.href =
        "my_appointments.php";
}


/*
|--------------------------------------------------------------------------
| PROFILE
|--------------------------------------------------------------------------
*/

function openProfile()
{
    window.location.href =
        "profile.php";
}


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

function openNotifications()
{
    window.location.href =
        "notifications.php";
}