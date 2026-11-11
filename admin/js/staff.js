document.addEventListener("DOMContentLoaded", function () {

    // ==========================
    // Modal Elements
    // ==========================

    const addStaffBtn =
        document.querySelector(".add-btn");

    const modal =
        document.getElementById("staffModal");

    const closeBtn =
        document.querySelector(".close-btn");

    const cancelBtn =
        document.querySelector(".cancel-btn");

    const staffForm =
        document.getElementById("staffForm");


    // ==========================
    // View Modal
    // ==========================

    const viewModal =
        document.getElementById("viewStaffModal");

    const viewCloseBtn =
        document.querySelector(".view-close-btn");


    // ==========================
    // Edit Modal
    // ==========================

    const editModal =
        document.getElementById("editStaffModal");

    const editCloseBtn =
        document.querySelector(".edit-close-btn");

    const editCancelBtn =
        document.querySelector(".edit-cancel-btn");


    // ==========================
    // Delete Modal
    // ==========================

    const deleteModal =
        document.getElementById("deleteStaffModal");

    const deleteCloseBtn =
        document.querySelector(".delete-close-btn");

    const cancelDeleteBtn =
        document.querySelector(".cancel-delete-btn");


    // ==========================
    // Open Add Modal
    // ==========================

    if (addStaffBtn && modal) {

        addStaffBtn.addEventListener("click", function () {

            modal.style.display = "flex";

            const firstInput =
                modal.querySelector(
                    'input[name="staff_name"]'
                );

            if (firstInput) {
                firstInput.focus();
            }

        });

    }


    // ==========================
    // Close Add Modal
    // ==========================

    if (closeBtn && modal) {

        closeBtn.addEventListener("click", function () {

            modal.style.display = "none";

        });

    }


    if (cancelBtn && modal) {

        cancelBtn.addEventListener("click", function () {

            modal.style.display = "none";

        });

    }


    // ==========================
    // View Staff
    // ==========================

    document.querySelectorAll(".view-staff-btn")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const row =
                    this.closest("tr");

                if (!row || !viewModal) {
                    return;
                }

                const cells =
                    row.querySelectorAll("td");

                const staffId =
                    this.dataset.id || "";

                const name =
                    row.querySelector(
                        ".staff-info h4"
                    )?.textContent.trim() || "Staff";

                const department =
                    cells[1]?.textContent.trim() || "N/A";

                const designation =
                    cells[2]?.textContent.trim() || "N/A";

                const email =
                    cells[3]?.textContent.trim() || "N/A";

                const phone =
                    cells[4]?.textContent.trim() || "N/A";

                const experience =
                    cells[5]?.textContent.trim() || "N/A";

                const status =
                    row.querySelector(
                        ".status"
                    )?.textContent.trim() || "N/A";


                const staffIdElement =
                    document.getElementById(
                        "viewStaffId"
                    );

                const nameElement =
                    document.getElementById(
                        "viewStaffName"
                    );

                const departmentElement =
                    document.getElementById(
                        "viewDepartment"
                    );

                const designationElement =
                    document.getElementById(
                        "viewStaffDesignation"
                    );

                const emailElement =
                    document.getElementById(
                        "viewEmail"
                    );

                const phoneElement =
                    document.getElementById(
                        "viewPhone"
                    );

                const experienceElement =
                    document.getElementById(
                        "viewExperience"
                    );

                const statusElement =
                    document.getElementById(
                        "viewStatus"
                    );


                if (staffIdElement) {
                    staffIdElement.textContent =
                        "EMP-" +
                        String(staffId).padStart(4, "0");
                }

                if (nameElement) {
                    nameElement.textContent =
                        name;
                }

                if (departmentElement) {
                    departmentElement.textContent =
                        department;
                }

                if (designationElement) {
                    designationElement.textContent =
                        designation;
                }

                if (emailElement) {
                    emailElement.textContent =
                        email;
                }

                if (phoneElement) {
                    phoneElement.textContent =
                        phone;
                }

                if (experienceElement) {
                    experienceElement.textContent =
                        experience;
                }

                if (statusElement) {
                    statusElement.textContent =
                        status;
                }


                const sourceImage =
                    row.querySelector(
                        ".staff-info img"
                    );

                const sourcePlaceholder =
                    row.querySelector(
                        ".staff-avatar-placeholder"
                    );

                const viewAvatar =
                    document.getElementById(
                        "viewStaffAvatar"
                    );


                if (viewAvatar) {

                    viewAvatar.innerHTML = "";

                    if (sourceImage) {

                        const image =
                            document.createElement("img");

                        image.src =
                            sourceImage.src;

                        image.alt =
                            name;

                        viewAvatar.appendChild(
                            image
                        );

                    } else if (sourcePlaceholder) {

                        viewAvatar.textContent =
                            sourcePlaceholder.textContent.trim();

                    } else {

                        viewAvatar.textContent =
                            getInitials(name);

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | These values are loaded from the hidden
                | data attributes attached to the table
                | row when available.
                |--------------------------------------------------------------------------
                */

                const dataRow =
                    document.querySelector(
                        `tr[data-staff-id="${CSS.escape(String(staffId))}"]`
                    );


                if (dataRow) {

                    setText(
                        "viewGender",
                        dataRow.dataset.gender || "N/A"
                    );

                    setText(
                        "viewDob",
                        formatDate(
                            dataRow.dataset.dob
                        )
                    );

                    setText(
                        "viewJoiningDate",
                        formatDate(
                            dataRow.dataset.joiningDate
                        )
                    );

                    setText(
                        "viewSalary",
                        dataRow.dataset.salary
                            ? "₹ " +
                              Number(
                                  dataRow.dataset.salary
                              ).toLocaleString(
                                  "en-IN",
                                  {
                                      minimumFractionDigits: 2
                                  }
                              )
                            : "₹ 0.00"
                    );

                } else {

                    setText(
                        "viewGender",
                        "N/A"
                    );

                    setText(
                        "viewDob",
                        "N/A"
                    );

                    setText(
                        "viewJoiningDate",
                        "N/A"
                    );

                    setText(
                        "viewSalary",
                        "N/A"
                    );

                }


                viewModal.style.display =
                    "flex";

            });

        });


    // ==========================
    // Edit Staff
    // ==========================

    document.querySelectorAll(".edit-staff-btn")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                const row =
                    this.closest("tr");

                if (!row || !editModal) {
                    return;
                }

                const staffId =
                    this.dataset.id || "";


                /*
                |--------------------------------------------------------------------------
                | Data attributes are added to each
                | staff row by PHP.
                |--------------------------------------------------------------------------
                */

                const data =
                    row.dataset;


                setValue(
                    "editStaffId",
                    staffId
                );

                setValue(
                    "editStaffName",
                    data.name || ""
                );

                setValue(
                    "editEmail",
                    data.email || ""
                );

                setValue(
                    "editPhone",
                    data.phone || ""
                );

                setValue(
                    "editDepartment",
                    data.departmentId || ""
                );

                setValue(
                    "editDesignation",
                    data.designation || ""
                );

                setValue(
                    "editGender",
                    data.gender || ""
                );

                setValue(
                    "editDob",
                    data.dob || ""
                );

                setValue(
                    "editJoiningDate",
                    data.joiningDate || ""
                );

                setValue(
                    "editSalary",
                    data.salary || "0"
                );

                setValue(
                    "editStatus",
                    data.status || "Active"
                );


                editModal.style.display =
                    "flex";

            });

        });


    // ==========================
    // Close Edit Modal
    // ==========================

    if (editCloseBtn && editModal) {

        editCloseBtn.addEventListener(
            "click",
            function () {

                editModal.style.display =
                    "none";

            }
        );

    }


    if (editCancelBtn && editModal) {

        editCancelBtn.addEventListener(
            "click",
            function () {

                editModal.style.display =
                    "none";

            }
        );

    }


    // ==========================
    // Delete Staff
    // ==========================

    document.querySelectorAll(".delete-staff-btn")
        .forEach(function (button) {

            button.addEventListener("click", function () {

                if (!deleteModal) {
                    return;
                }

                const staffId =
                    this.dataset.id || "";

                const staffName =
                    this.dataset.name ||
                    "this staff member";


                setValue(
                    "deleteStaffId",
                    staffId
                );

                setText(
                    "deleteStaffName",
                    staffName
                );


                deleteModal.style.display =
                    "flex";

            });

        });


    // ==========================
    // Close Delete Modal
    // ==========================

    if (deleteCloseBtn && deleteModal) {

        deleteCloseBtn.addEventListener(
            "click",
            function () {

                deleteModal.style.display =
                    "none";

            }
        );

    }


    if (cancelDeleteBtn && deleteModal) {

        cancelDeleteBtn.addEventListener(
            "click",
            function () {

                deleteModal.style.display =
                    "none";

            }
        );

    }


    // ==========================
    // Close Modals on Outside Click
    // ==========================

    window.addEventListener("click", function (e) {

        if (e.target === modal) {

            modal.style.display =
                "none";

        }

        if (e.target === viewModal) {

            viewModal.style.display =
                "none";

        }

        if (e.target === editModal) {

            editModal.style.display =
                "none";

        }

        if (e.target === deleteModal) {

            deleteModal.style.display =
                "none";

        }

    });


    // ==========================
    // Escape Key
    // ==========================

    document.addEventListener(
        "keydown",
        function (e) {

            if (e.key !== "Escape") {
                return;
            }

            if (modal) {
                modal.style.display =
                    "none";
            }

            if (viewModal) {
                viewModal.style.display =
                    "none";
            }

            if (editModal) {
                editModal.style.display =
                    "none";
            }

            if (deleteModal) {
                deleteModal.style.display =
                    "none";
            }

        }
    );


    // ==========================
    // Add Form Validation
    // ==========================

    if (staffForm) {

        staffForm.addEventListener(
            "submit",
            function (e) {

                const salary =
                    staffForm.querySelector(
                        '[name="salary"]'
                    );

                if (
                    salary &&
                    Number(salary.value) < 0
                ) {

                    e.preventDefault();

                    alert(
                        "Salary cannot be negative."
                    );

                    return;

                }


                const dob =
                    staffForm.querySelector(
                        '[name="date_of_birth"]'
                    );

                const joiningDate =
                    staffForm.querySelector(
                        '[name="joining_date"]'
                    );


                if (
                    dob &&
                    joiningDate &&
                    dob.value &&
                    joiningDate.value &&
                    dob.value >= joiningDate.value
                ) {

                    e.preventDefault();

                    alert(
                        "Date of birth must be earlier than joining date."
                    );

                }

            }
        );

    }


    // ==========================
    // Edit Form Validation
    // ==========================

    const editStaffForm =
        document.getElementById(
            "editStaffForm"
        );


    if (editStaffForm) {

        editStaffForm.addEventListener(
            "submit",
            function (e) {

                const salary =
                    editStaffForm.querySelector(
                        '[name="salary"]'
                    );

                if (
                    salary &&
                    Number(salary.value) < 0
                ) {

                    e.preventDefault();

                    alert(
                        "Salary cannot be negative."
                    );

                    return;

                }


                const dob =
                    editStaffForm.querySelector(
                        '[name="date_of_birth"]'
                    );

                const joiningDate =
                    editStaffForm.querySelector(
                        '[name="joining_date"]'
                    );


                if (
                    dob &&
                    joiningDate &&
                    dob.value &&
                    joiningDate.value &&
                    dob.value >= joiningDate.value
                ) {

                    e.preventDefault();

                    alert(
                        "Date of birth must be earlier than joining date."
                    );

                }

            }
        );

    }


    // ==========================
    // Delete Confirmation
    // ==========================

    const deleteForm =
        document.querySelector(
            ".delete-form"
        );


    if (deleteForm) {

        deleteForm.addEventListener(
            "submit",
            function (e) {

                const name =
                    document.getElementById(
                        "deleteStaffName"
                    )?.textContent ||
                    "this staff member";


                if (
                    !confirm(
                        "Are you sure you want to delete " +
                        name +
                        "?"
                    )
                ) {

                    e.preventDefault();

                }

            }
        );

    }


    // ==========================
    // Auto Hide Message
    // ==========================

    const message =
        document.querySelector(
            ".page-message"
        );


    if (message) {

        setTimeout(function () {

            message.style.opacity =
                "0";

            message.style.transition =
                "opacity .3s";

            setTimeout(function () {

                if (message.parentNode) {
                    message.parentNode.removeChild(
                        message
                    );
                }

            }, 300);

        }, 4000);

    }


    // ==========================
    // Helpers
    // ==========================

    function setValue(id, value) {

        const element =
            document.getElementById(id);

        if (element) {
            element.value =
                value ?? "";
        }

    }


    function setText(id, value) {

        const element =
            document.getElementById(id);

        if (element) {
            element.textContent =
                value ?? "";
        }

    }


    function getInitials(name) {

        const parts =
            String(name)
                .trim()
                .split(/\s+/);

        let initials = "";

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
        ) || "ST";

    }


    function formatDate(value) {

        if (!value) {
            return "N/A";
        }

        const date =
            new Date(
                String(value) +
                "T00:00:00"
            );

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return value;
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

});
