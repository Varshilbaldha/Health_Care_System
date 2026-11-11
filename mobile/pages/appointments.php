<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        My Appointments
    </title>

    <link
        rel="stylesheet"
        href="../style.css"
    >

<link rel="stylesheet" href="../css/common.css">
</head>


<body>


<div class="mobile-app">


    <!-- HEADER -->

    <header class="mobile-header">

        <button
            onclick="goHome()"
            class="back-button"
        >
            ←
        </button>

        <div>

            <h1>
                My Appointments
            </h1>

            <span>
                Appointment history
            </span>

        </div>

    </header>


    <!-- CONTENT -->

    <main class="appointments-page">


        <!-- COUNT -->

        <div class="appointment-summary">

            <span>
                Total Appointments
            </span>

            <strong id="appointmentCount">
                0
            </strong>

        </div>


        <!-- LOADING -->

        <div
            id="appointmentLoading"
            class="mobile-loading"
        >

            Loading appointments...

        </div>


        <!-- LIST -->

        <div
            id="appointmentsList"
            class="mobile-appointments-list"
        ></div>


    </main>


    <!-- BOTTOM NAV -->

    <nav class="bottom-navigation">


        <a
            href="../dashboard.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <path d="m3 11 9-8 9 8"/>
                <path d="M5 10v10h14V10"/>
            </svg>

            <small>Home</small>

        </a>


        <a
            href="../my_appointments.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <rect x="4" y="5" width="16" height="15" rx="3"/>
                <path d="M8 3v4"/>
                <path d="M16 3v4"/>
                <path d="M4 10h16"/>
            </svg>

            <small>Appointments</small>

        </a>


        <a
            href="../book.php"
            class="nav-link book-link"
        >

            <span class="book-button">
                +
            </span>

            <small>Book</small>

        </a>


        <a
            href="../hospital_search.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/>
                <circle cx="12" cy="9" r="2.5"/>
            </svg>

            <small>Hospitals</small>

        </a>


        <a
            href="../profile.php"
            class="nav-link"
        >

            <svg viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4"/>
                <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>
            </svg>

            <small>Profile</small>

        </a>


    </nav>


</div>


<script src="../app.js"></script>


<script>

/*====================================================
    LOAD APPOINTMENTS
====================================================*/

document.addEventListener(
    "DOMContentLoaded",
    loadAppointments
);


async function loadAppointments() {

    const loading =
        document.getElementById(
            "appointmentLoading"
        );


    const list =
        document.getElementById(
            "appointmentsList"
        );


    const count =
        document.getElementById(
            "appointmentCount"
        );


    try {

        const response =
            await fetch(
                "../api/appointments.php",
                {
                    credentials:
                        "same-origin",
                    cache:
                        "no-store"
                }
            );


        const result =
            await response.json();


        loading.style.display =
            "none";


        if (!result.success) {

            list.innerHTML = `

                <div class="empty-state">

                    <div>
                        ⚠️
                    </div>

                    <strong>
                        Unable to load appointments
                    </strong>

                    <span>
                        ${escapeHtml(
                            result.message
                        )}
                    </span>

                </div>

            `;

            return;
        }


        const appointments =
            result.data.appointments || [];


        count.textContent =
            appointments.length;


        if (
            appointments.length === 0
        ) {

            list.innerHTML = `

                <div class="empty-state">

                    <div>
                        📅
                    </div>

                    <strong>
                        No Appointments
                    </strong>

                    <span>
                        You don't have any appointments yet.
                    </span>

                    <button
                        onclick="openBooking()"
                        class="primary-button"
                    >
                        Book Appointment
                    </button>

                </div>

            `;

            return;
        }


        list.innerHTML =
            appointments
                .map(
                    renderAppointment
                )
                .join("");

    }
    catch (error) {

        loading.style.display =
            "none";


        list.innerHTML = `

            <div class="empty-state">

                <div>
                    ⚠️
                </div>

                <strong>
                    Connection Error
                </strong>

                <span>
                    Please try again.
                </span>

                <button
                    onclick="loadAppointments()"
                    class="primary-button"
                >
                    Try Again
                </button>

            </div>

        `;

        console.error(error);

    }

}


/*====================================================
    RENDER CARD
====================================================*/

function renderAppointment(
    appointment
) {

    const status =
        appointment.appointment_status ||
        "Unknown";


    let statusClass =
        "status-other";


    if (
        status === "Scheduled"
        ||
        status === "Checked-In"
        ||
        status === "In-Progress"
    ) {

        statusClass =
            "status-scheduled";

    }
    else if (
        status === "Completed"
    ) {

        statusClass =
            "status-completed";

    }
    else if (
        status === "Cancelled"
        ||
        status === "No-Show"
    ) {

        statusClass =
            "status-cancelled";

    }


    return `

        <article class="mobile-appointment-card">


            <div class="appointment-card-top">

                <div class="hospital-icon">
                    🏥
                </div>

                <div class="appointment-hospital">

                    <strong>
                        ${escapeHtml(
                            appointment.hospital_name
                        )}
                    </strong>

                    <span>
                        ${escapeHtml(
                            appointment.city || ""
                        )}
                    </span>

                </div>

                <span
                    class="appointment-status ${statusClass}"
                >
                    ${escapeHtml(status)}
                </span>

            </div>


            <div class="appointment-doctor">

                <div class="doctor-icon">
                    🩺
                </div>

                <div>

                    <strong>
                        ${escapeHtml(
                            appointment.doctor_name
                        )}
                    </strong>

                    <span>
                        ${escapeHtml(
                            appointment.specialization || ""
                        )}
                    </span>

                </div>

            </div>


            <div class="appointment-date-time">

                <div>

                    <span>
                        DATE
                    </span>

                    <strong>
                        ${formatDate(
                            appointment.appointment_date
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        TIME
                    </span>

                    <strong>
                        ${formatTime(
                            appointment.appointment_time
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        TOKEN
                    </span>

                    <strong>
                        #${Number(
                            appointment.token_number
                        )}
                    </strong>

                </div>

            </div>


            <div class="appointment-details">

                <div>

                    <span>
                        Appointment No
                    </span>

                    <strong>
                        ${escapeHtml(
                            appointment.appointment_no
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        Department
                    </span>

                    <strong>
                        ${escapeHtml(
                            appointment.department_name
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        Service
                    </span>

                    <strong>
                        ${escapeHtml(
                            appointment.service_name
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        Mode
                    </span>

                    <strong>
                        ${escapeHtml(
                            appointment.consultation_mode
                        )}
                    </strong>

                </div>


                <div>

                    <span>
                        Fee
                    </span>

                    <strong>
                        ₹${Number(
                            appointment.consultation_fee
                        ).toFixed(2)}
                    </strong>

                </div>

            </div>


            ${
                status === "Scheduled"
                ?
                `
                    <button
                        class="cancel-appointment-button"
                        onclick="openCancel(
                            ${Number(
                                appointment.appointment_id
                            )},
                            ${Number(
                                appointment.hospital_id
                            )}
                        )"
                    >
                        Cancel Appointment
                    </button>
                `
                :
                ""
            }


        </article>

    `;

}


/*====================================================
    CANCEL
====================================================*/

function openCancel(
    appointmentId,
    hospitalId
) {

    alert(
        "Cancel appointment will be connected in the next step."
    );

}


/*====================================================
    ESCAPE
====================================================*/

function escapeHtml(
    value
) {

    const div =
        document.createElement(
            "div"
        );


    div.textContent =
        String(
            value ?? ""
        );


    return div.innerHTML;

}

</script>


</body>

</html>