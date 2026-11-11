// ==========================
// Elements
// ==========================

const addServiceBtn =
    document.querySelector(".add-btn");

const serviceModal =
    document.getElementById("serviceModal");

const closeBtn =
    document.querySelector(".close-btn");

const cancelBtn =
    document.querySelector(".cancel-btn");

const serviceForm =
    document.getElementById("serviceForm");

const serviceModalTitle =
    document.getElementById("serviceModalTitle");

const serviceAction =
    document.getElementById("serviceAction");

const serviceId =
    document.getElementById("serviceId");

const saveServiceBtn =
    document.getElementById("saveServiceBtn");

const serviceName =
    document.getElementById("serviceName");

const serviceCode =
    document.getElementById("serviceCode");

const serviceDepartment =
    document.getElementById("serviceDepartment");

const serviceType =
    document.getElementById("serviceType");

const consultationMode =
    document.getElementById("consultationMode");

const durationMinutes =
    document.getElementById("durationMinutes");

const serviceFee =
    document.getElementById("serviceFee");

const serviceStatus =
    document.getElementById("serviceStatus");

const serviceDescription =
    document.getElementById("serviceDescription");

const preparationInstructions =
    document.getElementById(
        "preparationInstructions"
    );


// ==========================
// Add Service
// ==========================

if (
    addServiceBtn &&
    serviceModal
) {

    addServiceBtn.addEventListener(
        "click",
        function () {

            resetServiceForm();

            serviceModal.style.display =
                "flex";

        }
    );

}


// ==========================
// Reset Add/Edit Form
// ==========================

function resetServiceForm() {

    if (!serviceForm) {
        return;
    }


    serviceForm.reset();


    serviceAction.value =
        "add_service";


    serviceId.value =
        "";


    serviceModalTitle.textContent =
        "Add New Service";


    saveServiceBtn.textContent =
        "Save Service";


    serviceCode.value =
        "Auto-generated";


    durationMinutes.value =
        "30";


    serviceFee.value =
        "0";


    consultationMode.value =
        "In-Person";


    serviceStatus.value =
        "Active";

}


// ==========================
// Open Edit Service
// ==========================

const editButtons =
    document.querySelectorAll(
        ".edit-service-btn"
    );


editButtons.forEach(
    function (button) {

        button.addEventListener(
            "click",
            function () {

                const id =
                    this.dataset.id;


                const data =
                    document.getElementById(
                        "service-data-" + id
                    );


                if (!data) {

                    alert(
                        "Service information could not be loaded."
                    );

                    return;

                }


                serviceAction.value =
                    "edit_service";


                serviceId.value =
                    data.dataset.id || "";


                serviceName.value =
                    data.dataset.name || "";


                serviceCode.value =
                    data.dataset.code ||
                    "Auto-generated";


                serviceDepartment.value =
                    data.dataset.departmentId ||
                    "";


                serviceType.value =
                    data.dataset.type ||
                    "";


                consultationMode.value =
                    data.dataset.mode ||
                    "In-Person";


                durationMinutes.value =
                    data.dataset.duration ||
                    "30";


                serviceFee.value =
                    data.dataset.fee ||
                    "0";


                serviceStatus.value =
                    data.dataset.status ||
                    "Active";


                serviceDescription.value =
                    data.dataset.description ||
                    "";


                preparationInstructions.value =
                    data.dataset.preparation ||
                    "";


                serviceModalTitle.textContent =
                    "Edit Service";


                saveServiceBtn.textContent =
                    "Update Service";


                serviceModal.style.display =
                    "flex";

            }
        );

    }
);


// ==========================
// Close Add/Edit Modal
// ==========================

if (
    closeBtn &&
    serviceModal
) {

    closeBtn.addEventListener(
        "click",
        function () {

            serviceModal.style.display =
                "none";

        }
    );

}


if (
    cancelBtn &&
    serviceModal
) {

    cancelBtn.addEventListener(
        "click",
        function () {

            serviceModal.style.display =
                "none";

        }
    );

}


// ==========================
// Outside Click
// ==========================

window.addEventListener(
    "click",
    function (event) {

        if (
            event.target ===
            serviceModal
        ) {

            serviceModal.style.display =
                "none";

        }

    }
);


// ==========================
// ESC Key
// ==========================

window.addEventListener(
    "keydown",
    function (event) {

        if (
            event.key ===
            "Escape"
        ) {

            if (
                serviceModal &&
                serviceModal.style.display ===
                    "flex"
            ) {

                serviceModal.style.display =
                    "none";

            }

        }

    }
);


// ==========================
// Form Validation
// ==========================

if (serviceForm) {

    serviceForm.addEventListener(
        "submit",
        function (event) {

            if (
                !serviceName ||
                !serviceDepartment ||
                !serviceType ||
                !durationMinutes ||
                !serviceFee
            ) {

                return;

            }


            if (
                serviceName.value.trim() ===
                ""
            ) {

                event.preventDefault();

                alert(
                    "Please enter service name."
                );

                serviceName.focus();

                return;

            }


            if (
                serviceDepartment.value ===
                ""
            ) {

                event.preventDefault();

                alert(
                    "Please select department."
                );

                serviceDepartment.focus();

                return;

            }


            if (
                serviceType.value.trim() ===
                ""
            ) {

                event.preventDefault();

                alert(
                    "Please enter service type."
                );

                serviceType.focus();

                return;

            }


            if (
                parseInt(
                    durationMinutes.value,
                    10
                ) <= 0
            ) {

                event.preventDefault();

                alert(
                    "Duration must be greater than 0 minutes."
                );

                durationMinutes.focus();

                return;

            }


            if (
                parseFloat(
                    serviceFee.value ||
                    0
                ) < 0
            ) {

                event.preventDefault();

                alert(
                    "Service fee cannot be negative."
                );

                serviceFee.focus();

                return;

            }

        }
    );

}


// ==========================
// View Service
// ==========================

const viewButtons =
    document.querySelectorAll(
        ".view-service-btn"
    );


viewButtons.forEach(
    function (button) {

        button.addEventListener(
            "click",
            function () {

                const id =
                    this.dataset.id;


                const data =
                    document.getElementById(
                        "service-data-" + id
                    );


                if (!data) {

                    alert(
                        "Service information could not be loaded."
                    );

                    return;

                }


                if (
                    typeof openServiceViewModal ===
                    "function"
                ) {

                    openServiceViewModal(
                        data
                    );

                }

            }
        );

    }
);


// ==========================
// Delete Service
// ==========================

const deleteButtons =
    document.querySelectorAll(
        ".delete-service-btn"
    );


deleteButtons.forEach(
    function (button) {

        button.addEventListener(
            "click",
            function () {

                const id =
                    this.dataset.id;


                const data =
                    document.getElementById(
                        "service-data-" + id
                    );


                if (!data) {

                    alert(
                        "Service information could not be loaded."
                    );

                    return;

                }


                const serviceName =
                    data.dataset.name ||
                    "this service";


                const serviceCode =
                    data.dataset.code ||
                    "";


                const doctors =
                    parseInt(
                        data.dataset.doctors ||
                        "0",
                        10
                    );


                let message =
                    "Delete this service?\n\n" +
                    "Service: " +
                    serviceName +
                    "\n" +
                    "Code: " +
                    serviceCode +
                    "\n" +
                    "Doctors linked: " +
                    doctors +
                    "\n\n";


                if (
                    doctors > 0
                ) {

                    message +=
                        "All doctor-service mappings for this service will also be deleted.\n\n";

                }


                message +=
                    "This action cannot be undone.";


                const confirmed =
                    confirm(
                        message
                    );


                if (!confirmed) {

                    return;

                }


                const form =
                    document.createElement(
                        "form"
                    );


                form.method =
                    "POST";


                form.action =
                    "services.php";


                form.style.display =
                    "none";


                const actionInput =
                    document.createElement(
                        "input"
                    );


                actionInput.type =
                    "hidden";

                actionInput.name =
                    "action";

                actionInput.value =
                    "delete_service";


                const idInput =
                    document.createElement(
                        "input"
                    );


                idInput.type =
                    "hidden";

                idInput.name =
                    "service_id";

                idInput.value =
                    id;


                form.appendChild(
                    actionInput
                );


                form.appendChild(
                    idInput
                );


                document.body.appendChild(
                    form
                );


                form.submit();

            }
        );

    }
);


// ==========================
// More / Other Actions
// ==========================

// More button is removed in this version.
// Delete now has its own trash button.


// ==========================================================
// SERVICE VIEW MODAL
// ==========================================================

let serviceViewModal =
    document.getElementById(
        "serviceViewModal"
    );


// ==========================
// Create View Modal
// ==========================

function createServiceViewModal() {

    if (serviceViewModal) {

        return serviceViewModal;

    }


    serviceViewModal =
        document.createElement(
            "div"
        );


    serviceViewModal.id =
        "serviceViewModal";


    serviceViewModal.className =
        "service-view-modal";


    serviceViewModal.innerHTML = `

        <div class="service-view-content">

            <div class="service-view-header">

                <div>

                    <h2>
                        Service Details
                    </h2>

                    <p>
                        View complete service information
                    </p>

                </div>


                <button
                    type="button"
                    class="service-view-close"
                >
                    &times;
                </button>

            </div>


            <div class="service-view-body">

                <div class="service-view-profile">

                    <div class="service-view-icon">

                        <i
                            id="viewServiceIcon"
                            class="fa-solid fa-stethoscope"
                        ></i>

                    </div>


                    <div>

                        <h1 id="viewServiceName">
                            Service Name
                        </h1>

                        <p id="viewServiceCode">
                            Code: -
                        </p>

                    </div>

                </div>


                <div class="service-view-divider"></div>


                <div class="service-view-grid">


                    <div class="service-view-card">

                        <span>
                            Department
                        </span>

                        <strong id="viewServiceDepartment">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Service Type
                        </span>

                        <strong id="viewServiceType">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Consultation Mode
                        </span>

                        <strong id="viewServiceMode">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Duration
                        </span>

                        <strong id="viewServiceDuration">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Service Fee
                        </span>

                        <strong id="viewServiceFee">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Doctors
                        </span>

                        <strong id="viewServiceDoctors">
                            -
                        </strong>

                    </div>


                    <div class="service-view-card">

                        <span>
                            Status
                        </span>

                        <strong id="viewServiceStatus">
                            -
                        </strong>

                    </div>


                </div>


                <div
                    class="service-view-description-section"
                    id="viewDescriptionSection"
                >

                    <div class="service-view-section-title">
                        Description
                    </div>

                    <div
                        class="service-view-text"
                        id="viewServiceDescription"
                    >
                        -
                    </div>

                </div>


                <div
                    class="service-view-description-section"
                    id="viewPreparationSection"
                >

                    <div class="service-view-section-title">
                        Preparation Instructions
                    </div>

                    <div
                        class="service-view-text"
                        id="viewServicePreparation"
                    >
                        -
                    </div>

                </div>


            </div>

        </div>

    `;


    document.body.appendChild(
        serviceViewModal
    );


    const viewCloseBtn =
        serviceViewModal.querySelector(
            ".service-view-close"
        );


    viewCloseBtn.addEventListener(
        "click",
        function () {

            closeServiceViewModal();

        }
    );


    serviceViewModal.addEventListener(
        "click",
        function (event) {

            if (
                event.target ===
                serviceViewModal
            ) {

                closeServiceViewModal();

            }

        }
    );


    return serviceViewModal;
}


// ==========================
// Open View Modal
// ==========================

function openServiceViewModal(
    data
) {

    const modal =
        createServiceViewModal();


    const name =
        data.dataset.name ||
        "Service";


    const code =
        data.dataset.code ||
        "-";


    const department =
        data.dataset.department ||
        "Not Assigned";


    const type =
        data.dataset.type ||
        "-";


    const mode =
        data.dataset.mode ||
        "-";


    const duration =
        data.dataset.duration ||
        "0";


    const fee =
        parseFloat(
            data.dataset.fee ||
            0
        );


    const doctors =
        data.dataset.doctors ||
        "0";


    const status =
        data.dataset.status ||
        "-";


    const description =
        data.dataset.description ||
        "";


    const preparation =
        data.dataset.preparation ||
        "";


    document.getElementById(
        "viewServiceName"
    ).textContent =
        name;


    document.getElementById(
        "viewServiceCode"
    ).textContent =
        "Code: " + code;


    document.getElementById(
        "viewServiceDepartment"
    ).textContent =
        department;


    document.getElementById(
        "viewServiceType"
    ).textContent =
        type;


    document.getElementById(
        "viewServiceMode"
    ).textContent =
        mode;


    document.getElementById(
        "viewServiceDuration"
    ).textContent =
        duration +
        " Minutes";


    document.getElementById(
        "viewServiceFee"
    ).textContent =
        "₹" +
        fee.toFixed(2);


    document.getElementById(
        "viewServiceDoctors"
    ).textContent =
        doctors;


    const statusElement =
        document.getElementById(
            "viewServiceStatus"
        );


    statusElement.textContent =
        status;


    statusElement.className =
        "";


    if (
        status.toLowerCase() ===
        "active"
    ) {

        statusElement.classList.add(
            "view-status-active"
        );

    } else {

        statusElement.classList.add(
            "view-status-inactive"
        );

    }


    const descriptionSection =
        document.getElementById(
            "viewDescriptionSection"
        );


    const preparationSection =
        document.getElementById(
            "viewPreparationSection"
        );


    if (
        description.trim() !== ""
    ) {

        document.getElementById(
            "viewServiceDescription"
        ).textContent =
            description;


        descriptionSection.style.display =
            "block";

    } else {

        descriptionSection.style.display =
            "none";

    }


    if (
        preparation.trim() !== ""
    ) {

        document.getElementById(
            "viewServicePreparation"
        ).textContent =
            preparation;


        preparationSection.style.display =
            "block";

    } else {

        preparationSection.style.display =
            "none";

    }


    const icon =
        document.getElementById(
            "viewServiceIcon"
        );


    let iconClass =
        "fa-stethoscope";


    const typeLower =
        type.toLowerCase();


    if (
        typeLower.includes(
            "laboratory"
        ) ||
        typeLower.includes(
            "lab"
        )
    ) {

        iconClass =
            "fa-flask";

    } else if (
        typeLower.includes(
            "x-ray"
        ) ||
        typeLower.includes(
            "xray"
        )
    ) {

        iconClass =
            "fa-x-ray";

    } else if (
        typeLower.includes(
            "mri"
        )
    ) {

        iconClass =
            "fa-magnet";

    } else if (
        typeLower.includes(
            "ct"
        )
    ) {

        iconClass =
            "fa-circle-radiation";

    } else if (
        typeLower.includes(
            "ultrasound"
        )
    ) {

        iconClass =
            "fa-wave-square";

    } else if (
        typeLower.includes(
            "diagnostic"
        )
    ) {

        iconClass =
            "fa-x-ray";

    }


    icon.className =
        "fa-solid " +
        iconClass;


    modal.classList.add(
        "show"
    );


    document.body.classList.add(
        "service-modal-open"
    );

}


// ==========================
// Close View Modal
// ==========================

function closeServiceViewModal() {

    if (
        serviceViewModal
    ) {

        serviceViewModal.classList.remove(
            "show"
        );

    }


    document.body.classList.remove(
        "service-modal-open"
    );

}