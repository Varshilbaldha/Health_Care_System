document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       ELEMENTS
    ===================================================== */

    const addAppointmentBtn =
        document.getElementById("addAppointmentBtn");

    const appointmentModal =
        document.getElementById("appointmentModal");

    const viewModal =
        document.getElementById("viewAppointmentModal");

    const editModal =
        document.getElementById("editAppointmentModal");

    const deleteModal =
        document.getElementById("deleteAppointmentModal");


    /* =====================================================
       ADD APPOINTMENT MODAL
    ===================================================== */

    if (addAppointmentBtn && appointmentModal) {

        addAppointmentBtn.addEventListener("click", function () {

            appointmentModal.style.display = "flex";

            const dateInput =
                appointmentModal.querySelector(
                    'input[name="appointment_date"]'
                );

            const timeInput =
                appointmentModal.querySelector(
                    'input[name="appointment_time"]'
                );

            if (dateInput) {

                const today =
                    new Date().toISOString().split("T")[0];

                dateInput.min = today;

                if (!dateInput.value) {
                    dateInput.value = today;
                }
            }

            if (timeInput) {

                timeInput.min = "09:30";

                if (!timeInput.value) {
                    timeInput.value = "09:30";
                }
            }

        });

    }


    /* =====================================================
       CLOSE MODALS
    ===================================================== */

    document.querySelectorAll("[data-close]").forEach(function (button) {

        button.addEventListener("click", function () {

            const modalId =
                this.getAttribute("data-close");

            const modal =
                document.getElementById(modalId);

            if (modal) {
                modal.style.display = "none";
            }

        });

    });


    /* =====================================================
       OUTSIDE CLICK
    ===================================================== */

    window.addEventListener("click", function (event) {

        if (event.target.classList.contains("modal")) {
            event.target.style.display = "none";
        }

    });


    /* =====================================================
       ESC KEY
    ===================================================== */

    document.addEventListener("keydown", function (event) {

        if (event.key === "Escape") {

            document.querySelectorAll(".modal").forEach(function (modal) {

                modal.style.display = "none";

            });

        }

    });


    /* =====================================================
       VIEW APPOINTMENT
    ===================================================== */

    document.querySelectorAll(".view-btn").forEach(function (button) {

        button.addEventListener("click", function () {

            const id =
                this.getAttribute("data-id");

            const data =
                document.getElementById(
                    "appointment-" + id
                );

            if (!data || !viewModal) {
                return;
            }


            document.getElementById("viewAppointmentNo").textContent =
                data.dataset.appointmentNo || "N/A";

            document.getElementById("viewPatient").textContent =
                data.dataset.patient || "N/A";

            document.getElementById("viewPatientCode").textContent =
                data.dataset.patientCode || "N/A";

            document.getElementById("viewMobile").textContent =
                data.dataset.mobile || "N/A";

            document.getElementById("viewDoctor").textContent =
                "Dr. " + (data.dataset.doctor || "N/A");

            document.getElementById("viewDepartment").textContent =
                data.dataset.department || "N/A";

            document.getElementById("viewService").textContent =
                data.dataset.service || "N/A";

            const bookedOnElement =
                document.getElementById("viewBookedOn");

            if (bookedOnElement) {
                bookedOnElement.textContent =
                    formatDateTime(data.dataset.createdAt);
            }


            document.getElementById("viewDate").textContent =
                formatDate(data.dataset.date);

            document.getElementById("viewTime").textContent =
                formatTime(data.dataset.time);

            document.getElementById("viewType").textContent =
                data.dataset.type || "N/A";

            document.getElementById("viewMode").textContent =
                data.dataset.mode || "N/A";

            document.getElementById("viewToken").textContent =
                data.dataset.token && data.dataset.token !== "0"
                    ? "#" + data.dataset.token
                    : "N/A";

            document.getElementById("viewStatus").textContent =
                data.dataset.status || "N/A";

            document.getElementById("viewSymptoms").textContent =
                data.dataset.symptoms || "N/A";

            document.getElementById("viewNotes").textContent =
                data.dataset.notes || "N/A";

            document.getElementById("viewCancellation").textContent =
                data.dataset.cancellation || "N/A";


            viewModal.style.display = "flex";

        });

    });


    /* =====================================================
       EDIT APPOINTMENT
    ===================================================== */

    document.querySelectorAll(".edit-btn").forEach(function (button) {

        button.addEventListener("click", function () {

            const id =
                this.getAttribute("data-id");

            const data =
                document.getElementById(
                    "appointment-" + id
                );

            if (!data || !editModal) {
                return;
            }


            document.getElementById("editAppointmentId").value =
                data.dataset.id || "";

            document.getElementById("editPatientDisplay").value =
                data.dataset.patient || "";


            const doctorSelect =
                document.getElementById("editDoctor");

            const serviceSelect =
                document.getElementById("editService");


            doctorSelect.value =
                data.dataset.doctorId || "";


            serviceSelect.value =
                data.dataset.serviceId || "";


            document.getElementById("editDate").value =
                data.dataset.date || "";


            document.getElementById("editTime").value =
                data.dataset.time || "09:30";


            document.getElementById("editType").value =
                data.dataset.type || "Walk-In";


            document.getElementById("editMode").value =
                data.dataset.mode || "In-Person";


            document.getElementById("editStatus").value =
                data.dataset.status || "Scheduled";


            document.getElementById("editSymptoms").value =
                data.dataset.symptoms || "";


            document.getElementById("editNotes").value =
                data.dataset.notes || "";

            const editCancellation =
                document.getElementById("editCancellationReason");

            if (editCancellation) {
                editCancellation.value =
                    data.dataset.cancellation || "";
            }


            /* Date minimum */

            const today =
                new Date().toISOString().split("T")[0];

            document.getElementById("editDate").min =
                today;


            /* Time minimum */

            document.getElementById("editTime").min =
                "09:30";


            editModal.style.display = "flex";

        });

    });


    /* =====================================================
       DELETE APPOINTMENT
    ===================================================== */

    document.querySelectorAll(".delete-btn").forEach(function (button) {

        button.addEventListener("click", function () {

            const id =
                this.getAttribute("data-id");

            const name =
                this.getAttribute("data-name");


            const idInput =
                document.getElementById(
                    "deleteAppointmentId"
                );

            const nameElement =
                document.getElementById(
                    "deletePatientName"
                );


            if (idInput) {
                idInput.value = id;
            }

            if (nameElement) {
                nameElement.textContent =
                    name || "this patient";
            }


            if (deleteModal) {
                deleteModal.style.display = "flex";
            }

        });

    });


    /* =====================================================
       EDIT DATE VALIDATION
    ===================================================== */

    const editDate =
        document.getElementById("editDate");

    const editTime =
        document.getElementById("editTime");


    if (editDate) {

        const today =
            new Date().toISOString().split("T")[0];

        editDate.min = today;

    }


    if (editTime) {

        editTime.min = "09:30";

    }


    /* =====================================================
       VALIDATE EDIT FORM
    ===================================================== */

    const editForm =
        editModal
            ? editModal.querySelector("form")
            : null;


    if (editForm) {

        editForm.addEventListener("submit", function (event) {

            const dateValue =
                editDate.value;

            const timeValue =
                editTime.value;


            if (!dateValue) {

                event.preventDefault();

                alert("Please select appointment date.");

                return;

            }


            if (!timeValue) {

                event.preventDefault();

                alert("Please select appointment time.");

                return;

            }


            if (timeValue < "09:30") {

                event.preventDefault();

                alert(
                    "Appointment time cannot be earlier than 9:30 AM."
                );

                return;

            }


            const today =
                new Date().toISOString().split("T")[0];


            if (dateValue < today) {

                event.preventDefault();

                alert(
                    "Appointment date cannot be in the past."
                );

                return;

            }

        });

    }


    /* =====================================================
       ADD FORM DATE/TIME VALIDATION
    ===================================================== */

    if (appointmentModal) {

        const addForm =
            appointmentModal.querySelector("form");

        const addDate =
            appointmentModal.querySelector(
                'input[name="appointment_date"]'
            );

        const addTime =
            appointmentModal.querySelector(
                'input[name="appointment_time"]'
            );


        if (addDate) {

            const today =
                new Date().toISOString().split("T")[0];

            addDate.min = today;

        }


        if (addTime) {

            addTime.min = "09:30";

        }


        if (addForm) {

            addForm.addEventListener(
                "submit",
                function (event) {

                    const dateValue =
                        addDate ? addDate.value : "";

                    const timeValue =
                        addTime ? addTime.value : "";


                    if (!dateValue) {

                        event.preventDefault();

                        alert(
                            "Please select appointment date."
                        );

                        return;

                    }


                    if (!timeValue) {

                        event.preventDefault();

                        alert(
                            "Please select appointment time."
                        );

                        return;

                    }


                    if (timeValue < "09:30") {

                        event.preventDefault();

                        alert(
                            "Appointment time cannot be earlier than 9:30 AM."
                        );

                        return;

                    }


                    const today =
                        new Date().toISOString().split("T")[0];


                    if (dateValue < today) {

                        event.preventDefault();

                        alert(
                            "Appointment date cannot be in the past."
                        );

                    }

                }
            );

        }

    }


    /* =====================================================
       DEPARTMENT → DOCTOR FILTER
    ===================================================== */

    const departmentFilter =
        document.getElementById("departmentFilter");

    const doctorFilter =
        document.getElementById("doctorFilter");


    if (departmentFilter && doctorFilter) {

        departmentFilter.addEventListener(
            "change",
            function () {

                const departmentId =
                    this.value;


                const options =
                    doctorFilter.querySelectorAll(
                        "option"
                    );


                options.forEach(function (option, index) {

                    if (index === 0) {

                        option.hidden = false;
                        return;

                    }


                    const optionDepartment =
                        option.dataset.department;


                    if (
                        departmentId === "" ||
                        optionDepartment === departmentId
                    ) {

                        option.hidden = false;

                    } else {

                        option.hidden = true;

                    }

                });


                const selected =
                    doctorFilter.options[
                        doctorFilter.selectedIndex
                    ];


                if (
                    selected &&
                    selected.hidden
                ) {

                    doctorFilter.value = "";

                }

            }
        );

    }


    /* =====================================================
       UTILITY FUNCTIONS
    ===================================================== */

    function formatDate(dateString) {

        if (!dateString) {
            return "N/A";
        }

        const date =
            new Date(dateString + "T00:00:00");

        if (isNaN(date.getTime())) {
            return dateString;
        }

        return date.toLocaleDateString(
            "en-IN",
            {
                day: "2-digit",
                month: "short",
                year: "numeric"
            }
        );

    }


    function formatDateTime(dateTimeString) {

        if (!dateTimeString) {
            return "N/A";
        }

        const normalized =
            dateTimeString.replace(" ", "T");

        const date = new Date(normalized);

        if (isNaN(date.getTime())) {
            return dateTimeString;
        }

        return date.toLocaleDateString(
            "en-IN",
            {
                day: "2-digit",
                month: "short",
                year: "numeric"
            }
        ) + " " + date.toLocaleTimeString(
            "en-IN",
            {
                hour: "2-digit",
                minute: "2-digit",
                hour12: true
            }
        );

    }


    function formatTime(timeString) {

        if (!timeString) {
            return "N/A";
        }

        const parts =
            timeString.split(":");

        if (parts.length < 2) {
            return timeString;
        }

        let hour =
            parseInt(parts[0], 10);

        const minute =
            parts[1];

        const ampm =
            hour >= 12 ? "PM" : "AM";

        hour =
            hour % 12;

        if (hour === 0) {
            hour = 12;
        }

        return (
            hour.toString().padStart(2, "0") +
            ":" +
            minute +
            " " +
            ampm
        );

    }

});