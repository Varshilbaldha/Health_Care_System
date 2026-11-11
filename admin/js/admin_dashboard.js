document.addEventListener("DOMContentLoaded", function () {

    /*
    |--------------------------------------------------------------------------
    | Appointments Overview Chart
    |--------------------------------------------------------------------------
    */

    const appointmentCanvas =
        document.getElementById("appointmentChart");

    if (
        appointmentCanvas &&
        typeof Chart !== "undefined"
    ) {

        const appointmentLabels =
            Array.isArray(window.dashboardAppointmentLabels)
                ? window.dashboardAppointmentLabels
                : [];

        const appointmentData =
            Array.isArray(window.dashboardAppointmentData)
                ? window.dashboardAppointmentData
                : [];

        new Chart(
            appointmentCanvas,
            {
                type: "line",

                data: {
                    labels: appointmentLabels,

                    datasets: [
                        {
                            label: "Appointments",

                            data: appointmentData,

                            borderWidth: 3,

                            fill: true,

                            tension: 0.4,

                            pointRadius: 4,

                            pointHoverRadius: 6
                        }
                    ]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    interaction: {
                        intersect: false,
                        mode: "index"
                    },

                    plugins: {

                        legend: {
                            display: false
                        },

                        tooltip: {
                            enabled: true
                        }
                    },

                    scales: {

                        y: {

                            beginAtZero: true,

                            ticks: {
                                precision: 0
                            }
                        },

                        x: {

                            grid: {
                                display: false
                            }
                        }
                    }
                }
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Patients by Department Chart
    |--------------------------------------------------------------------------
    */

    const departmentCanvas =
        document.getElementById("departmentChart");

    if (
        departmentCanvas &&
        typeof Chart !== "undefined"
    ) {

        const departmentLabels =
            Array.isArray(window.dashboardDepartmentLabels)
                ? window.dashboardDepartmentLabels
                : [];

        const departmentData =
            Array.isArray(window.dashboardDepartmentData)
                ? window.dashboardDepartmentData
                : [];

        if (
            departmentLabels.length === 0 ||
            departmentData.length === 0
        ) {

            departmentLabels.push("No Data");

            departmentData.push(1);
        }

        new Chart(
            departmentCanvas,
            {
                type: "doughnut",

                data: {

                    labels: departmentLabels,

                    datasets: [
                        {
                            data: departmentData,

                            borderWidth: 2
                        }
                    ]
                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    cutout: "68%",

                    plugins: {

                        legend: {
                            position: "bottom"
                        },

                        tooltip: {
                            enabled: true
                        }
                    }
                }
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Three Dot Menus
    |--------------------------------------------------------------------------
    */

    const cardHeaders =
        document.querySelectorAll(".card-header");


    cardHeaders.forEach(function (header) {

        const menuIcon =
            header.querySelector(".fa-ellipsis");

        if (!menuIcon) {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Make Header Relative
        |--------------------------------------------------------------------------
        */

        header.style.position = "relative";


        /*
        |--------------------------------------------------------------------------
        | Make Ellipsis Clickable
        |--------------------------------------------------------------------------
        */

        menuIcon.style.cursor = "pointer";


        /*
        |--------------------------------------------------------------------------
        | Create Menu
        |--------------------------------------------------------------------------
        */

        const menu =
            document.createElement("div");

        menu.className =
            "dashboard-action-menu";


        /*
        |--------------------------------------------------------------------------
        | Detect Card
        |--------------------------------------------------------------------------
        */

        const titleElement =
            header.querySelector("h3");

        const title =
            titleElement
                ? titleElement.textContent.trim()
                : "";


        /*
        |--------------------------------------------------------------------------
        | Appointment Menu
        |--------------------------------------------------------------------------
        */

        if (
            title === "Recent Appointments"
        ) {

            menu.innerHTML = `

                <button type="button" data-action="appointments">
                    <i class="fa-solid fa-calendar-check"></i>
                    View All Appointments
                </button>

                <button type="button" data-action="today">
                    <i class="fa-solid fa-calendar-day"></i>
                    Today's Appointments
                </button>

                <button type="button" data-action="refresh">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh
                </button>

            `;
        }


        /*
        |--------------------------------------------------------------------------
        | Hospital Activity Menu
        |--------------------------------------------------------------------------
        */

        else if (
            title === "Hospital Activity"
        ) {

            menu.innerHTML = `

                <button type="button" data-action="activity-refresh">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh Activity
                </button>

                <button type="button" data-action="reports">
                    <i class="fa-solid fa-chart-line"></i>
                    View Reports
                </button>

            `;
        }


        /*
        |--------------------------------------------------------------------------
        | Appointments Chart Menu
        |--------------------------------------------------------------------------
        */

        else if (
            title === "Appointments Overview"
        ) {

            menu.innerHTML = `

                <button type="button" data-action="refresh">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh Chart
                </button>

                <button type="button" data-action="appointments">
                    <i class="fa-solid fa-calendar-check"></i>
                    View Appointments
                </button>

            `;
        }


        /*
        |--------------------------------------------------------------------------
        | Department Chart Menu
        |--------------------------------------------------------------------------
        */

        else if (
            title === "Patients by Department"
        ) {

            menu.innerHTML = `

                <button type="button" data-action="patients">
                    <i class="fa-solid fa-users"></i>
                    View Patients
                </button>

                <button type="button" data-action="refresh">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh Chart
                </button>

            `;
        }


        /*
        |--------------------------------------------------------------------------
        | Add Menu Only When It Has Options
        |--------------------------------------------------------------------------
        */

        if (menu.innerHTML.trim() !== "") {

            /*
            |--------------------------------------------------------------------------
            | Basic Menu Styling
            |--------------------------------------------------------------------------
            */

            menu.style.position = "absolute";
            menu.style.top = "32px";
            menu.style.right = "0";
            menu.style.minWidth = "210px";
            menu.style.background = "#ffffff";
            menu.style.border = "1px solid #e5e7eb";
            menu.style.borderRadius = "10px";
            menu.style.padding = "6px";
            menu.style.boxShadow =
                "0 10px 30px rgba(0,0,0,0.12)";
            menu.style.zIndex = "1000";
            menu.style.display = "none";


            /*
            |--------------------------------------------------------------------------
            | Menu Button Styling
            |--------------------------------------------------------------------------
            */

            const style =
                document.createElement("style");

            style.textContent = `

                .dashboard-action-menu button {

                    width: 100%;

                    display: flex;

                    align-items: center;

                    gap: 10px;

                    border: none;

                    background: transparent;

                    padding: 10px 12px;

                    border-radius: 7px;

                    cursor: pointer;

                    font-size: 14px;

                    text-align: left;

                    color: #263238;

                    transition: background 0.2s ease;

                }

                .dashboard-action-menu button:hover {

                    background: #f1f5f9;

                }

                .dashboard-action-menu button i {

                    width: 18px;

                    text-align: center;

                }

            `;

            document.head.appendChild(style);


            header.appendChild(menu);


            /*
            |--------------------------------------------------------------------------
            | Open / Close Menu
            |--------------------------------------------------------------------------
            */

            menuIcon.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();

                    document
                        .querySelectorAll(
                            ".dashboard-action-menu"
                        )
                        .forEach(function (otherMenu) {

                            if (
                                otherMenu !== menu
                            ) {

                                otherMenu.style.display =
                                    "none";
                            }

                        });


                    if (
                        menu.style.display === "none"
                    ) {

                        menu.style.display =
                            "block";

                    } else {

                        menu.style.display =
                            "none";
                    }

                }
            );


            /*
            |--------------------------------------------------------------------------
            | Menu Actions
            |--------------------------------------------------------------------------
            */

            menu.querySelectorAll("button")
                .forEach(function (button) {

                    button.addEventListener(
                        "click",
                        function () {

                            const action =
                                this.dataset.action;


                            if (
                                action ===
                                "appointments"
                            ) {

                                window.location.href =
                                    "appointments.php";

                            }


                            else if (
                                action ===
                                "today"
                            ) {

                                window.location.href =
                                    "appointments.php";

                            }


                            else if (
                                action ===
                                "patients"
                            ) {

                                window.location.href =
                                    "patients.php";

                            }


                            else if (
                                action ===
                                "reports"
                            ) {

                                window.location.href =
                                    "reports.php";

                            }


                            else if (
                                action ===
                                "refresh"
                            ) {

                                window.location.reload();

                            }


                            else if (
                                action ===
                                "activity-refresh"
                            ) {

                                window.location.reload();

                            }


                            menu.style.display =
                                "none";

                        }
                    );

                });

        }

    });


    /*
    |--------------------------------------------------------------------------
    | Close All Menus When Clicking Outside
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        "click",
        function () {

            document
                .querySelectorAll(
                    ".dashboard-action-menu"
                )
                .forEach(function (menu) {

                    menu.style.display =
                        "none";

                });

        }
    );

});