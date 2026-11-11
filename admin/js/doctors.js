document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       ELEMENTS
    ===================================================== */

    const addDoctorBtn = document.getElementById("addDoctorBtn");
    const doctorModal = document.getElementById("doctorModal");
    const closeDoctorModal = document.getElementById("closeDoctorModal");

    const viewDoctorModal = document.getElementById("viewDoctorModal");
    const editDoctorModal = document.getElementById("editDoctorModal");
    const deleteDoctorModal = document.getElementById("deleteDoctorModal");

    const moreMenu = document.getElementById("doctorMoreMenu");

    let currentDoctorId = null;


    /* =====================================================
       ADD DOCTOR MODAL
    ===================================================== */

    if (addDoctorBtn && doctorModal) {

        addDoctorBtn.addEventListener("click", function () {

            doctorModal.classList.add("show");

            resetAvailabilityForm("add");

        });

    }


    /* =====================================================
       CLOSE ADD DOCTOR MODAL
    ===================================================== */

    if (closeDoctorModal) {

        closeDoctorModal.addEventListener("click", function () {

            doctorModal.classList.remove("show");

        });

    }


    const cancelAddBtn =
        document.querySelector("#doctorModal .cancel-btn");

    if (cancelAddBtn) {

        cancelAddBtn.addEventListener("click", function () {

            doctorModal.classList.remove("show");

        });

    }


    /* =====================================================
       VIEW DOCTOR
    ===================================================== */

    document.querySelectorAll(".view-doctor-btn").forEach(function (button) {

        button.addEventListener("click", function () {

            const doctorId = this.dataset.id;

            openViewDoctor(doctorId);

        });

    });


    function openViewDoctor(id) {

        const data =
            document.getElementById("doctor-data-" + id);

        if (!data) {
            return;
        }

        currentDoctorId = id;

        const name =
            data.dataset.name || "N/A";

        const qualification =
            data.dataset.qualification || "N/A";

        document.getElementById("viewDoctorName").textContent =
            name;

        document.getElementById("viewDoctorQualification").textContent =
            qualification;

        document.getElementById("viewDepartment").textContent =
            data.dataset.department || "N/A";

        document.getElementById("viewSpecialization").textContent =
            data.dataset.specialization || "N/A";

        document.getElementById("viewExperience").textContent =
            (data.dataset.experience || "0") + " Years";

        document.getElementById("viewPatients").textContent =
            Number(data.dataset.patients || 0)
                .toLocaleString("en-IN");

        document.getElementById("viewFee").textContent =
            "₹ " +
            Number(data.dataset.fee || 0)
                .toLocaleString("en-IN", {
                    minimumFractionDigits: 2
                });

        document.getElementById("viewStatus").textContent =
            data.dataset.status || "N/A";

        document.getElementById("viewGender").textContent =
            data.dataset.gender || "N/A";

        document.getElementById("viewDob").textContent =
            formatDate(data.dataset.dob);

        document.getElementById("viewEmail").textContent =
            data.dataset.email || "N/A";

        document.getElementById("viewPhone").textContent =
            data.dataset.phone || "N/A";

        document.getElementById("viewLicense").textContent =
            data.dataset.license || "N/A";


        const avatar =
            document.getElementById("viewDoctorAvatar");

        if (avatar) {

            const photo =
                data.dataset.photo || "";

            if (photo !== "") {

                avatar.innerHTML =
                    `<img src="${escapeHtml(photo)}" alt="Doctor">`;

                avatar.classList.add("has-photo");

            } else {

                avatar.textContent =
                    getInitials(name);

                avatar.classList.remove("has-photo");

            }

        }


        if (viewDoctorModal) {

            viewDoctorModal.classList.add("show");

        }

    }


    /* =====================================================
       CLOSE VIEW MODAL
    ===================================================== */

    document.querySelectorAll(".close-view-modal")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                viewDoctorModal.classList.remove("show");

            });

        });


    /* =====================================================
       EDIT DOCTOR
    ===================================================== */

    document.querySelectorAll(".edit-doctor-btn")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const doctorId =
                    this.dataset.id;

                openEditDoctor(doctorId);

            });

        });


    function openEditDoctor(id) {

        const data =
            document.getElementById("doctor-data-" + id);

        if (!data) {
            return;
        }

        currentDoctorId = id;


        document.getElementById("editDoctorId").value =
            data.dataset.id || "";

        document.getElementById("editDoctorName").value =
            data.dataset.name || "";

        document.getElementById("editDepartment").value =
            data.dataset.departmentId || "";

        document.getElementById("editSpecialization").value =
            data.dataset.specialization || "";

        document.getElementById("editQualification").value =
            data.dataset.qualification || "";

        document.getElementById("editGender").value =
            data.dataset.gender || "Other";

        document.getElementById("editDob").value =
            data.dataset.dob || "";

        document.getElementById("editExperience").value =
            data.dataset.experience || "0";

        document.getElementById("editFee").value =
            data.dataset.fee || "0";

        document.getElementById("editEmail").value =
            data.dataset.email || "";

        document.getElementById("editPhone").value =
            data.dataset.phone || "";

        document.getElementById("editLicense").value =
            data.dataset.license || "";

        document.getElementById("editStatus").value =
            data.dataset.status || "Active";


        /* =================================================
           LOAD DOCTOR AVAILABILITY
        ================================================= */

        let availability = [];

        try {

            availability = JSON.parse(
                data.dataset.availability || "[]"
            );

        } catch (error) {

            availability = [];

        }

        loadAvailabilityIntoForm(
            "edit",
            availability
        );


        if (editDoctorModal) {

            editDoctorModal.classList.add("show");

        }

    }


    /* =====================================================
       DOCTOR AVAILABILITY
    ===================================================== */

    function resetAvailabilityForm(prefix) {

        const defaultDays = [
            "Monday",
            "Tuesday",
            "Wednesday",
            "Thursday",
            "Friday",
            "Saturday",
            "Sunday"
        ];


        const slotDuration =
            document.getElementById(
                prefix + "SlotDuration"
            );

        const maxPatients =
            document.getElementById(
                prefix + "MaxPatients"
            );

        const consultationMode =
            document.getElementById(
                prefix + "ConsultationMode"
            );


        if (slotDuration) {

            slotDuration.value = "15";

        }


        if (maxPatients) {

            maxPatients.value = "10";

        }


        if (consultationMode) {

            consultationMode.value = "In-Person";

        }


        defaultDays.forEach(function (day) {

            const checkbox =
                document.getElementById(
                    prefix + "Day" + day
                );

            const start =
                document.getElementById(
                    prefix + "Start" + day
                );

            const end =
                document.getElementById(
                    prefix + "End" + day
                );


            if (checkbox) {

                checkbox.checked = false;

            }


            if (start) {

                start.value = "09:30";

            }


            if (end) {

                end.value = "13:00";

            }

        });

    }


    /* =====================================================
       LOAD EXISTING AVAILABILITY
    ===================================================== */

    function loadAvailabilityIntoForm(
        prefix,
        availability
    ) {

        resetAvailabilityForm(prefix);


        if (
            !Array.isArray(availability) ||
            availability.length === 0
        ) {

            return;

        }


        /*
         * Global availability settings are taken
         * from the first availability row.
         */

        const first =
            availability[0];


        const slotDuration =
            document.getElementById(
                prefix + "SlotDuration"
            );

        const maxPatients =
            document.getElementById(
                prefix + "MaxPatients"
            );

        const consultationMode =
            document.getElementById(
                prefix + "ConsultationMode"
            );


        if (
            slotDuration &&
            first.slot_duration_minutes
        ) {

            slotDuration.value =
                String(
                    first.slot_duration_minutes
                );

        }


        if (
            maxPatients &&
            first.max_patients
        ) {

            maxPatients.value =
                String(
                    first.max_patients
                );

        }


        if (
            consultationMode &&
            first.consultation_mode
        ) {

            consultationMode.value =
                first.consultation_mode;

        }


        /*
         * Load each active day.
         */

        availability.forEach(function (item) {

            const day =
                item.day_of_week;


            if (!day) {

                return;

            }


            const checkbox =
                document.getElementById(
                    prefix + "Day" + day
                );

            const start =
                document.getElementById(
                    prefix + "Start" + day
                );

            const end =
                document.getElementById(
                    prefix + "End" + day
                );


            if (checkbox) {

                checkbox.checked = true;

            }


            if (
                start &&
                item.start_time
            ) {

                start.value =
                    String(
                        item.start_time
                    ).substring(0, 5);

            }


            if (
                end &&
                item.end_time
            ) {

                end.value =
                    String(
                        item.end_time
                    ).substring(0, 5);

            }

        });

    }


    /* =====================================================
       CLOSE EDIT MODAL
    ===================================================== */

    document.querySelectorAll(".close-edit-modal")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                if (editDoctorModal) {

                    editDoctorModal.classList.remove("show");

                }

            });

        });


    document.querySelectorAll(".cancel-edit-btn")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                if (editDoctorModal) {

                    editDoctorModal.classList.remove("show");

                }

            });

        });


    /* =====================================================
       3 DOT MENU
    ===================================================== */

    document.querySelectorAll(".more-doctor-btn")
        .forEach(function (button) {

            button.addEventListener("click", function (event) {

                event.preventDefault();

                event.stopPropagation();


                const doctorId =
                    this.getAttribute("data-id");

                currentDoctorId =
                    doctorId;


                if (!moreMenu) {

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | Get Button Position
                |--------------------------------------------------------------------------
                */

                const rect =
                    this.getBoundingClientRect();


                /*
                |--------------------------------------------------------------------------
                | Force Fixed Position
                |--------------------------------------------------------------------------
                */

                moreMenu.style.position =
                    "fixed";

                moreMenu.style.display =
                    "block";

                moreMenu.style.zIndex =
                    "99999";


                let top =
                    rect.bottom + 6;

                let left =
                    rect.right - 170;


                /*
                |--------------------------------------------------------------------------
                | Keep Menu Inside Screen
                |--------------------------------------------------------------------------
                */

                if (left < 10) {

                    left = 10;

                }


                if (
                    left + 170 >
                    window.innerWidth - 10
                ) {

                    left =
                        window.innerWidth - 180;

                }


                if (
                    top + 150 >
                    window.innerHeight - 10
                ) {

                    top =
                        rect.top - 150;

                }


                if (top < 10) {

                    top = 10;

                }


                moreMenu.style.top =
                    top + "px";

                moreMenu.style.left =
                    left + "px";

            });

        });


    /* =====================================================
       MORE MENU → VIEW
    ===================================================== */

    const moreViewBtn =
        document.getElementById(
            "moreViewBtn"
        );

    if (moreViewBtn) {

        moreViewBtn.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();

                hideMoreMenu();


                if (currentDoctorId) {

                    openViewDoctor(
                        currentDoctorId
                    );

                }

            }
        );

    }


    /* =====================================================
       MORE MENU → EDIT
    ===================================================== */

    const moreEditBtn =
        document.getElementById(
            "moreEditBtn"
        );

    if (moreEditBtn) {

        moreEditBtn.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();

                hideMoreMenu();


                if (currentDoctorId) {

                    openEditDoctor(
                        currentDoctorId
                    );

                }

            }
        );

    }


    /* =====================================================
       MORE MENU → DELETE
    ===================================================== */

    const moreDeleteBtn =
        document.getElementById(
            "moreDeleteBtn"
        );

    if (moreDeleteBtn) {

        moreDeleteBtn.addEventListener(
            "click",
            function (event) {

                event.preventDefault();

                event.stopPropagation();

                hideMoreMenu();


                if (currentDoctorId) {

                    openDeleteDoctor(
                        currentDoctorId
                    );

                }

            }
        );

    }


    /* =====================================================
       OPEN DELETE MODAL
    ===================================================== */

    function openDeleteDoctor(id) {

        const data =
            document.getElementById(
                "doctor-data-" + id
            );


        if (!data) {

            return;

        }


        currentDoctorId =
            id;


        const deleteId =
            document.getElementById(
                "deleteDoctorId"
            );

        const deleteName =
            document.getElementById(
                "deleteDoctorName"
            );


        if (deleteId) {

            deleteId.value =
                data.dataset.id || "";

        }


        if (deleteName) {

            deleteName.textContent =
                data.dataset.name ||
                "this doctor";

        }


        if (deleteDoctorModal) {

            deleteDoctorModal.classList.add(
                "show"
            );

        }

    }


    /* =====================================================
       CANCEL DELETE
    ===================================================== */

    document.querySelectorAll(".cancel-delete-btn")
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    if (deleteDoctorModal) {

                        deleteDoctorModal.classList.remove(
                            "show"
                        );

                    }

                }
            );

        });


    /* =====================================================
       OUTSIDE CLICK
    ===================================================== */

    window.addEventListener(
        "click",
        function (event) {

            /*
            |--------------------------------------------------------------------------
            | Close More Menu
            |--------------------------------------------------------------------------
            */

            if (
                moreMenu &&
                moreMenu.style.display === "block" &&
                !moreMenu.contains(event.target) &&
                !event.target.closest(".more-doctor-btn")
            ) {

                hideMoreMenu();

            }


            /*
            |--------------------------------------------------------------------------
            | Close Modal
            |--------------------------------------------------------------------------
            */

            if (
                event.target.classList.contains(
                    "modal-overlay"
                )
            ) {

                event.target.classList.remove(
                    "show"
                );

            }

        }
    );


    /* =====================================================
       HIDE MORE MENU
    ===================================================== */

    function hideMoreMenu() {

        if (moreMenu) {

            moreMenu.style.display =
                "none";

        }

    }


    /* =====================================================
       ESC KEY
    ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Escape") {

                hideMoreMenu();


                document
                    .querySelectorAll(
                        ".modal-overlay"
                    )
                    .forEach(
                        function (modal) {

                            modal.classList.remove(
                                "show"
                            );

                        }
                    );

            }

        }
    );


    /* =====================================================
       ADD FORM VALIDATION
    ===================================================== */

    const doctorForm =
        document.getElementById(
            "doctorForm"
        );


    if (doctorForm) {

        doctorForm.addEventListener(
            "submit",
            function (event) {

                const experience =
                    doctorForm.querySelector(
                        '[name="experience_years"]'
                    );

                const fee =
                    doctorForm.querySelector(
                        '[name="consultation_fee"]'
                    );


                if (
                    experience &&
                    Number(experience.value) < 0
                ) {

                    event.preventDefault();

                    alert(
                        "Experience cannot be negative."
                    );

                    return;

                }


                if (
                    fee &&
                    Number(fee.value) < 0
                ) {

                    event.preventDefault();

                    alert(
                        "Consultation fee cannot be negative."
                    );

                }

            }
        );

    }


    /* =====================================================
       SUCCESS / ERROR MESSAGE
    ===================================================== */

    if (
        window.doctorMessage &&
        window.doctorMessage !== ""
    ) {

        alert(
            window.doctorMessage
        );

    }


    /* =====================================================
       DATE FORMAT
    ===================================================== */

    function formatDate(dateString) {

        if (
            !dateString ||
            dateString === "0000-00-00"
        ) {

            return "N/A";

        }


        const date =
            new Date(
                dateString +
                "T00:00:00"
            );


        if (
            isNaN(
                date.getTime()
            )
        ) {

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


    /* =====================================================
       INITIALS
    ===================================================== */

    function getInitials(name) {

        const parts =
            name.trim()
                .split(/\s+/);

        let initials =
            "";


        if (parts[0]) {

            initials +=
                parts[0].charAt(0);

        }


        if (parts[1]) {

            initials +=
                parts[1].charAt(0);

        }


        return (
            initials
                .toUpperCase()
                .substring(0, 2)
        ) || "DR";

    }


    /* =====================================================
       SAFE IMAGE VALUE
    ===================================================== */

    function escapeHtml(value) {

        return String(value)
            .replace(
                /&/g,
                "&amp;"
            )
            .replace(
                /"/g,
                "&quot;"
            )
            .replace(
                /'/g,
                "&#039;"
            )
            .replace(
                /</g,
                "&lt;"
            )
            .replace(
                />/g,
                "&gt;"
            );

    }

});