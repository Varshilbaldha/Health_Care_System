document.addEventListener("DOMContentLoaded", function () {

    /* =====================================================
       ELEMENTS
    ===================================================== */

    const addButton =
        document.getElementById("addDepartmentBtn");

    const addModal =
        document.getElementById("departmentModal");

    const editModal =
        document.getElementById("editDepartmentModal");

    const viewModal =
        document.getElementById("viewDepartmentModal");

    const deleteModal =
        document.getElementById("deleteDepartmentModal");

    const actionMenu =
        document.getElementById("departmentActionMenu");


    let selectedDepartmentId = "";


    /* =====================================================
       ADD MODAL
    ===================================================== */

    if (addButton && addModal) {

        addButton.addEventListener(
            "click",
            function () {

                addModal.style.display = "flex";

            }
        );

    }


    /* =====================================================
       CLOSE BUTTONS
    ===================================================== */

    document
        .querySelectorAll("[data-close]")
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    const modalId =
                        this.getAttribute("data-close");

                    const modal =
                        document.getElementById(
                            modalId
                        );

                    if (modal) {

                        modal.style.display =
                            "none";
                    }

                }
            );

        });


    /* =====================================================
       OLD CLOSE BUTTON
    ===================================================== */

    const closeModal =
        document.getElementById("closeModal");

    if (closeModal && addModal) {

        closeModal.addEventListener(
            "click",
            function () {

                addModal.style.display =
                    "none";

            }
        );

    }


    /* =====================================================
       CANCEL ADD
    ===================================================== */

    const cancelButton =
        document.querySelector(
            "#departmentModal .cancel-btn"
        );

    if (cancelButton) {

        cancelButton.addEventListener(
            "click",
            function () {

                addModal.style.display =
                    "none";

            }
        );

    }


    /* =====================================================
       OUTSIDE CLICK
    ===================================================== */

    window.addEventListener(
        "click",
        function (event) {

            if (
                event.target.classList.contains(
                    "modal-overlay"
                )
            ) {

                event.target.style.display =
                    "none";
            }


            if (
                actionMenu &&
                !event.target.closest(
                    ".more-department-btn"
                ) &&
                !event.target.closest(
                    ".department-action-menu"
                )
            ) {

                actionMenu.style.display =
                    "none";
            }

        }
    );


    /* =====================================================
       ESC
    ===================================================== */

    document.addEventListener(
        "keydown",
        function (event) {

            if (event.key === "Escape") {

                document
                    .querySelectorAll(
                        ".modal-overlay"
                    )
                    .forEach(function (modal) {

                        modal.style.display =
                            "none";

                    });


                if (actionMenu) {

                    actionMenu.style.display =
                        "none";

                }

            }

        }
    );


    /* =====================================================
       GET DEPARTMENT DATA
    ===================================================== */

    function getDepartmentData(id) {

        return document.getElementById(
            "department-" + id
        );

    }


    /* =====================================================
       VIEW DEPARTMENT
    ===================================================== */

    document
        .querySelectorAll(
            ".view-department-btn"
        )
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    const id =
                        this.getAttribute(
                            "data-id"
                        );

                    openViewModal(id);

                }
            );

        });


    function openViewModal(id) {

        const data =
            getDepartmentData(id);

        if (!data || !viewModal) {
            return;
        }


        document.getElementById(
            "viewDepartmentName"
        ).textContent =
            data.dataset.name || "N/A";


        document.getElementById(
            "viewDepartmentDescription"
        ).textContent =
            data.dataset.description ||
            "No description";


        let headDoctor =
            data.dataset.headDoctor || "";


        if (headDoctor !== "") {

            if (
                headDoctor.toLowerCase()
                    .indexOf("dr.") !== 0 &&
                headDoctor.toLowerCase()
                    .indexOf("dr ") !== 0
            ) {

                headDoctor =
                    "Dr. " + headDoctor;

            }

        } else {

            headDoctor =
                "Not Assigned";

        }


        document.getElementById(
            "viewDepartmentHeadDoctor"
        ).textContent =
            headDoctor;


        document.getElementById(
            "viewDepartmentSpecialization"
        ).textContent =
            data.dataset.headSpecialization ||
            "N/A";


        document.getElementById(
            "viewDepartmentDoctors"
        ).textContent =
            data.dataset.doctors || "0";


        document.getElementById(
            "viewDepartmentStaff"
        ).textContent =
            data.dataset.staff || "0";


        document.getElementById(
            "viewDepartmentLocation"
        ).textContent =
            data.dataset.location ||
            "Not specified";


        const status =
            data.dataset.status ||
            "N/A";


        const statusElement =
            document.getElementById(
                "viewDepartmentStatus"
            );


        statusElement.textContent =
            status;


        statusElement.className =
            "view-status " +
            (
                status.toLowerCase() === "active"
                    ? "active"
                    : "inactive"
            );


        viewModal.style.display =
            "flex";

    }


    /* =====================================================
       EDIT DEPARTMENT
    ===================================================== */

    document
        .querySelectorAll(
            ".edit-department-btn"
        )
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    const id =
                        this.getAttribute(
                            "data-id"
                        );

                    openEditModal(id);

                }
            );

        });


    function openEditModal(id) {

        const data =
            getDepartmentData(id);

        if (!data || !editModal) {
            return;
        }


        selectedDepartmentId =
            id;


        document.getElementById(
            "editDepartmentId"
        ).value =
            id;


        document.getElementById(
            "editDepartmentName"
        ).value =
            data.dataset.name || "";


        document.getElementById(
            "editDepartmentDescription"
        ).value =
            data.dataset.description || "";


        document.getElementById(
            "editDepartmentLocation"
        ).value =
            data.dataset.location || "";


        document.getElementById(
            "editDepartmentStatus"
        ).value =
            data.dataset.status || "Active";


        const headDoctorSelect =
            document.getElementById(
                "editHeadDoctor"
            );


        if (headDoctorSelect) {

            const departmentId =
                id;


            headDoctorSelect
                .querySelectorAll("option")
                .forEach(function (option) {

                    if (option.value === "") {

                        option.hidden = false;

                        return;

                    }


                    const doctorDepartment =
                        option.dataset.department;


                    if (
                        doctorDepartment ===
                        departmentId
                    ) {

                        option.hidden =
                            false;

                    } else {

                        option.hidden =
                            true;
                    }

                });


            const headDoctorId =
                data.dataset.headDoctorId ||
                "";


            headDoctorSelect.value =
                headDoctorId;


            if (
                headDoctorSelect.value !==
                headDoctorId
            ) {

                headDoctorSelect.value =
                    "";

            }

        }


        editModal.style.display =
            "flex";

    }


    /* =====================================================
       MORE ACTION BUTTON
    ===================================================== */

    document
        .querySelectorAll(
            ".more-department-btn"
        )
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function (event) {

                    event.stopPropagation();


                    const id =
                        this.getAttribute(
                            "data-id"
                        );


                    selectedDepartmentId =
                        id;


                    if (!actionMenu) {
                        return;
                    }


                    const rect =
                        this.getBoundingClientRect();


                    actionMenu.style.display =
                        "block";


                    actionMenu.style.position =
                        "fixed";


                    let left =
                        rect.right -
                        160;


                    let top =
                        rect.bottom +
                        8;


                    if (
                        left < 10
                    ) {

                        left = 10;

                    }


                    if (
                        left + 160 >
                        window.innerWidth
                    ) {

                        left =
                            window.innerWidth -
                            170;

                    }


                    if (
                        top + 150 >
                        window.innerHeight
                    ) {

                        top =
                            rect.top -
                            150;

                    }


                    actionMenu.style.left =
                        left + "px";


                    actionMenu.style.top =
                        top + "px";

                }
            );

        });


    /* =====================================================
       MORE MENU - VIEW
    ===================================================== */

    const menuViewBtn =
        document.getElementById(
            "menuViewBtn"
        );


    if (menuViewBtn) {

        menuViewBtn.addEventListener(
            "click",
            function () {

                if (actionMenu) {

                    actionMenu.style.display =
                        "none";

                }


                openViewModal(
                    selectedDepartmentId
                );

            }
        );

    }


    /* =====================================================
       MORE MENU - EDIT
    ===================================================== */

    const menuEditBtn =
        document.getElementById(
            "menuEditBtn"
        );


    if (menuEditBtn) {

        menuEditBtn.addEventListener(
            "click",
            function () {

                if (actionMenu) {

                    actionMenu.style.display =
                        "none";

                }


                openEditModal(
                    selectedDepartmentId
                );

            }
        );

    }


    /* =====================================================
       MORE MENU - DELETE
    ===================================================== */

    const menuDeleteBtn =
        document.getElementById(
            "menuDeleteBtn"
        );


    if (menuDeleteBtn) {

        menuDeleteBtn.addEventListener(
            "click",
            function () {

                if (actionMenu) {

                    actionMenu.style.display =
                        "none";

                }


                openDeleteModal(
                    selectedDepartmentId
                );

            }
        );

    }


    /* =====================================================
       DIRECT DELETE BUTTON
       Currently 3-dot is used for delete,
       but this function handles it centrally.
    ===================================================== */

    function openDeleteModal(id) {

        const data =
            getDepartmentData(id);

        if (!data || !deleteModal) {
            return;
        }


        document.getElementById(
            "deleteDepartmentId"
        ).value =
            id;


        document.getElementById(
            "deleteDepartmentName"
        ).textContent =
            data.dataset.name ||
            "this department";


        deleteModal.style.display =
            "flex";

    }


    /* =====================================================
       EDIT FORM VALIDATION
    ===================================================== */

    const editForm =
        document.getElementById(
            "editDepartmentForm"
        );


    if (editForm) {

        editForm.addEventListener(
            "submit",
            function (event) {

                const name =
                    document.getElementById(
                        "editDepartmentName"
                    ).value.trim();


                if (name === "") {

                    event.preventDefault();

                    alert(
                        "Please enter department name."
                    );

                    return;

                }


                const headDoctor =
                    document.getElementById(
                        "editHeadDoctor"
                    );


                if (
                    headDoctor &&
                    headDoctor.value !== ""
                ) {

                    const selectedOption =
                        headDoctor.options[
                            headDoctor.selectedIndex
                        ];


                    if (
                        selectedOption &&
                        selectedOption.hidden
                    ) {

                        event.preventDefault();

                        alert(
                            "Please select a valid Head Doctor for this department."
                        );

                    }

                }

            }
        );

    }


    /* =====================================================
       ADD FORM VALIDATION
    ===================================================== */

    const addForm =
        document.getElementById(
            "departmentForm"
        );


    if (addForm) {

        addForm.addEventListener(
            "submit",
            function (event) {

                const nameInput =
                    addForm.querySelector(
                        'input[name="department_name"]'
                    );


                if (
                    !nameInput ||
                    nameInput.value.trim() === ""
                ) {

                    event.preventDefault();

                    alert(
                        "Please enter department name."
                    );

                }

            }
        );

    }


    /* =====================================================
       AUTO HIDE MESSAGE
    ===================================================== */

    const messages =
        document.querySelectorAll(
            ".department-message"
        );


    if (messages.length > 0) {

        setTimeout(
            function () {

                messages.forEach(
                    function (message) {

                        message.style.opacity =
                            "0";

                        message.style.transform =
                            "translateY(-5px)";

                        setTimeout(
                            function () {

                                message.remove();

                            },
                            300
                        );

                    }
                );

            },
            4000
        );

    }

});