document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    const addBillBtn = document.getElementById("addBillBtn");
    const billModal = document.getElementById("billModal");
    const billForm = document.getElementById("billForm");
    const appointmentSelect = document.getElementById("appointmentSelect");
    const itemsContainer = document.getElementById("items");
    const addItemBtn = document.getElementById("addItemBtn");

    const discountInput = billForm?.querySelector(
        'input[name="discount_amount"]'
    );
    const taxInput = billForm?.querySelector(
        'input[name="tax_amount"]'
    );

    const consultationPreview = document.getElementById("consultationPreview");
    const additionalPreview = document.getElementById("additionalPreview");
    const subtotalPreview = document.getElementById("subtotalPreview");
    const discountPreview = document.getElementById("discountPreview");
    const taxPreview = document.getElementById("taxPreview");
    const payablePreview = document.getElementById("payablePreview");
    const mandatoryFeeAmount = document.getElementById("mandatoryFeeAmount");
    const mandatoryFeeBox = document.getElementById("mandatoryFeeBox");
    const mandatoryFeeName = document.getElementById("mandatoryFeeName");
    const mandatoryFeeDoctor = document.getElementById("mandatoryFeeDoctor");

    const catalog = window.billingCatalog || {};

    function money(value) {
        const n = Number.parseFloat(value);
        return Number.isFinite(n) ? n.toFixed(2) : "0.00";
    }

    function escapeHtml(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add("active");
        document.body.classList.add("modal-open");
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove("active");

        if (!document.querySelector(".modal.active")) {
            document.body.classList.remove("modal-open");
        }
    }

    /* ---------------------------------------------------------
       CREATE BILL
    --------------------------------------------------------- */

    if (addBillBtn) {
        addBillBtn.addEventListener("click", function (e) {
            e.preventDefault();
            openModal(billModal);

            if (appointmentSelect && appointmentSelect.value) {
                loadDoctorFee();
            } else {
                removeDoctorFee();
                updateMandatoryFeeBox(0, "", "");
            }

            calculateTotals();
        });
    }

    /* ---------------------------------------------------------
       CLOSE ALL MODALS
    --------------------------------------------------------- */

    document.querySelectorAll("[data-close]").forEach(function (button) {
        button.addEventListener("click", function () {
            const modalId = button.getAttribute("data-close");
            closeModal(document.getElementById(modalId));
        });
    });

    document.querySelectorAll(".modal").forEach(function (modal) {
        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener("keydown", function (e) {
        if (e.key !== "Escape") return;

        document.querySelectorAll(".modal.active").forEach(function (modal) {
            closeModal(modal);
        });
    });

    /* ---------------------------------------------------------
       APPOINTMENT -> MANDATORY DOCTOR FEE
    --------------------------------------------------------- */

    if (appointmentSelect) {
        appointmentSelect.addEventListener("change", loadDoctorFee);
    }

    function loadDoctorFee() {
        if (!appointmentSelect) return;

        const selected =
            appointmentSelect.options[appointmentSelect.selectedIndex];

        if (!selected || !selected.value) {
            updateMandatoryFeeBox(0, "", "");
            renderPatientLinkedCharges("");
            calculateTotals();
            return;
        }

        const doctorName =
            selected.getAttribute("data-doctor") || "Doctor";

        const serviceName =
            selected.getAttribute("data-service-name") ||
            "Doctor Consultation";

        const doctorFee =
            Number.parseFloat(
                selected.getAttribute("data-doctor-fee")
            ) ||
            Number.parseFloat(
                selected.getAttribute("data-service-fee")
            ) ||
            0;

        updateMandatoryFeeBox(
            doctorFee,
            serviceName,
            doctorName
        );

        /*
         * Doctor fee is deliberately NOT rendered inside "Other Charges".
         * The PHP backend adds it from the appointment and locks it.
         * This avoids the old double-source bug where the preview showed
         * ₹650 but the item list/totals remained ₹0.
         */
        renderPatientLinkedCharges(selected.value);
        calculateTotals();
    }

    function renderPatientLinkedCharges(appointmentId) {
        if (!itemsContainer) return;

        itemsContainer
            .querySelectorAll(".auto-linked-item")
            .forEach(function (item) {
                item.remove();
            });

        const charges =
            window.billingPatientCharges?.[String(appointmentId)] ||
            window.billingPatientCharges?.[appointmentId] ||
            [];

        charges.forEach(function (charge) {
            const item = createItemElement({
                type: charge.type || "Other Service",
                name: charge.name || "",
                quantity: charge.quantity || 1,
                unit: charge.unit || 0,
                discount: charge.discount || 0,
                tax: charge.tax || 0,
                remarks: charge.remarks || "",
                roomId: charge.room_id || "",
                roomNumber: charge.room_number || "",
                linkedId: charge.bed_id || charge.room_id || "",
                doctorFee: false,
                autoLinked: true
            });

            itemsContainer.appendChild(item);
        });

        renumberItems();
        refreshCreateBedDropdowns();
        calculateTotals();
    }

    function updateMandatoryFeeBox(amount, serviceName, doctorName) {
        if (mandatoryFeeAmount) {
            mandatoryFeeAmount.textContent = "₹ " + money(amount);
        }

        if (mandatoryFeeName) {
            mandatoryFeeName.textContent =
                serviceName || "Select an appointment";
        }

        if (mandatoryFeeDoctor) {
            mandatoryFeeDoctor.textContent =
                doctorName
                    ? "Fee for Dr. " + doctorName
                    : "Doctor fee will be added automatically.";
        }

        if (mandatoryFeeBox) {
            mandatoryFeeBox.classList.toggle("has-fee", Number(amount) > 0);
        }
    }

    function setDoctorItemValues(item, name, fee, doctorName) {
        const nameInput = item.querySelector(".item-name");
        const unitInput = item.querySelector(".item-unit");
        const remarksInput = item.querySelector(".item-remarks");

        if (nameInput) nameInput.value = name;
        if (unitInput) unitInput.value = money(fee);

        if (remarksInput) {
            remarksInput.value =
                "Automatically added from appointment - Dr. " +
                doctorName;
        }

        calculateItemTotal(item);
    }

    function removeDoctorFee() {
        const doctorItem =
            itemsContainer?.querySelector(".doctor-fee-item");

        if (doctorItem) doctorItem.remove();
    }

    /* ---------------------------------------------------------
       ADD ITEM
    --------------------------------------------------------- */

    if (addItemBtn) {
        addItemBtn.addEventListener("click", function (e) {
            e.preventDefault();

            const item = createItemElement({
                type: "Other Service",
                name: "",
                quantity: 1,
                unit: 0,
                discount: 0,
                tax: 0,
                remarks: "",
                doctorFee: false
            });

            itemsContainer.appendChild(item);
            refreshCreateBedDropdowns();
            calculateTotals();

            const nameField = item.querySelector(".item-name");
            if (nameField) nameField.focus();
        });
    }

    /* ---------------------------------------------------------
       CREATE ITEM
    --------------------------------------------------------- */

    function createItemElement(data) {
        const wrapper = document.createElement("div");

        wrapper.className =
            "bill-item-row" +
            (data.doctorFee ? " doctor-fee-item" : " additional-item") +
            (data.autoLinked ? " auto-linked-item" : "");

        if (data.roomId) {
            wrapper.dataset.roomId = String(data.roomId);
        }

        const removeControl = data.doctorFee
            ? `
                <div class="item-action locked-control">
                    <span class="locked-badge">
                        <i class="fa-solid fa-lock"></i>
                        Mandatory
                    </span>
                </div>
              `
            : `
                <div class="item-action">
                    <button
                        type="button"
                        class="remove-item-btn"
                        title="Remove item"
                    >
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
              `;

        wrapper.innerHTML = `
            <div class="item-number">
                <span>1</span>
            </div>

            <div class="item-field">
                <label>Item Type</label>
                ${
                    data.doctorFee
                        ? `
                            <input
                                type="text"
                                value="Doctor Fee"
                                readonly
                                class="item-type-display"
                            >
                            <input
                                type="hidden"
                                name="items[][item_type]"
                                class="item-type"
                                value="Doctor Fee"
                            >
                          `
                        : `
                            <select
                                name="items[][item_type]"
                                class="item-type"
                            >
                                <option value="Other Service">Other Service</option>
                                <option value="Room">Room</option>
                                <option value="Bed">Bed</option>
                                <option value="Lab Test">Lab Test</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Procedure">Procedure</option>
                            </select>
                          `
                }
            </div>

            <div class="item-field item-name-field">
                <label>Item Name</label>
                <div class="item-name-control"></div>
            </div>

            <div class="item-field">
                <label>Qty</label>
                <input
                    type="number"
                    name="items[][quantity]"
                    class="item-qty"
                    value="${escapeHtml(data.quantity ?? 1)}"
                    min="0.01"
                    step="0.01"
                    required
                    ${data.doctorFee ? "readonly" : ""}
                >
            </div>

            <div class="item-field">
                <label>Unit Price</label>
                <div class="item-price-wrap">
                    <span>₹</span>
                    <input
                        type="number"
                        name="items[][unit_price]"
                        class="item-unit"
                        value="${money(data.unit)}"
                        min="0"
                        step="0.01"
                        required
                        ${data.doctorFee ? "readonly" : ""}
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Discount</label>
                <div class="item-price-wrap">
                    <span>₹</span>
                    <input
                        type="number"
                        name="items[][discount_amount]"
                        class="item-discount"
                        value="${money(data.discount)}"
                        min="0"
                        step="0.01"
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Tax</label>
                <div class="item-price-wrap">
                    <span>₹</span>
                    <input
                        type="number"
                        name="items[][tax_amount]"
                        class="item-tax"
                        value="${money(data.tax)}"
                        min="0"
                        step="0.01"
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Total</label>
                <div class="item-line-total">₹ 0.00</div>
            </div>

            <div class="item-field item-remarks-field">
                <label>Remarks</label>
                <input
                    type="text"
                    name="items[][remarks]"
                    class="item-remarks"
                    value="${escapeHtml(data.remarks || "")}"
                    placeholder="Optional"
                >
            </div>

            ${removeControl}
        `;

        if (data.doctorFee) {
            const typeDisplay =
                wrapper.querySelector(".item-type-display");
            if (typeDisplay) typeDisplay.value = "Doctor Fee";

            setNameField(wrapper, "Doctor Fee", data.name, true);
        } else {
            const typeSelect =
                wrapper.querySelector(".item-type");

            if (typeSelect) {
                typeSelect.value = data.type || "Other Service";

                typeSelect.addEventListener("change", function () {
                    setNameField(
                        wrapper,
                        typeSelect.value,
                        "",
                        false
                    );
                    calculateTotals();
                });
            }

            setNameField(
                wrapper,
                data.type || "Other Service",
                data.name || "",
                false,
                data.roomId || ""
            );
        }

        const removeBtn =
            wrapper.querySelector(".remove-item-btn");

        if (removeBtn) {
            removeBtn.addEventListener("click", function () {
                wrapper.remove();
                renumberItems();
                calculateTotals();
            });
        }

        wrapper.querySelectorAll("input, select").forEach(function (field) {
            field.addEventListener("input", calculateTotals);
            field.addEventListener("change", calculateTotals);
        });

        calculateItemTotal(wrapper);
        renumberItems();

        return wrapper;
    }

    /* ---------------------------------------------------------
       ITEM NAME DROPDOWN / INPUT
    --------------------------------------------------------- */

    function normalizeLinkedName(value, type) {
        const text = String(value ?? "").trim();
        if (!text) return "";

        if (type === "Room") {
            return text.replace(/^Room\s+/i, "").trim();
        }

        if (type === "Bed") {
            return text
                .replace(/^Bed\s+/i, "")
                .replace(/\s*-\s*Room\s+[^-]+$/i, "")
                .trim();
        }

        return text;
    }

    function normalizeRoomNumber(value) {
        return String(value ?? "")
            .replace(/^Room\s+/i, "")
            .replace(/\s*[—-]\s*.*$/, "")
            .trim()
            .toLowerCase();
    }

    function normalizeBedNumber(value) {
        return String(value ?? "")
            .replace(/^Bed\s+/i, "")
            .replace(/\s*[—-]\s*Room\s+.*$/i, "")
            .trim()
            .toLowerCase();
    }

    function findRoomEntry(roomName) {
        const rooms = Array.isArray(catalog.Room) ? catalog.Room : [];
        const wanted = normalizeRoomNumber(roomName);

        return rooms.find(function (entry) {
            return normalizeRoomNumber(entry.name) === wanted;
        }) || null;
    }

    function findBedEntry(bedName, roomId = "") {
        const beds = Array.isArray(catalog.Bed) ? catalog.Bed : [];
        const wanted = normalizeBedNumber(bedName);

        return beds.find(function (entry) {
            const sameBed = normalizeBedNumber(entry.name) === wanted;
            const sameRoom = !roomId || String(entry.room_id || "") === String(roomId);
            return sameBed && sameRoom;
        }) || null;
    }

    function getSelectedRoomId() {
        if (!itemsContainer) return "";

        const rows = Array.from(
            itemsContainer.querySelectorAll(".bill-item-row")
        );

        for (const row of rows) {
            const type = row.querySelector(".item-type")?.value || "";
            if (type !== "Room") continue;

            const roomSelect = row.querySelector(".item-name-select");
            const option = roomSelect?.options[roomSelect.selectedIndex];
            let roomId = option?.getAttribute("data-room-id") || "";

            if (!roomId) {
                const roomName = option?.value || row.querySelector(".item-name")?.value || "";
                roomId = findRoomEntry(roomName)?.room_id || "";
            }

            if (roomId) return String(roomId);
        }

        return "";
    }

    function getRoomIdForSelectedBedName(selectedName) {
        return findBedEntry(selectedName)?.room_id
            ? String(findBedEntry(selectedName).room_id)
            : "";
    }

    function refreshCreateBedDropdowns() {
        if (!itemsContainer) return;

        const selectedRoomId = getSelectedRoomId();

        itemsContainer
            .querySelectorAll(".bill-item-row")
            .forEach(function (row) {
                const type = row.querySelector(".item-type")?.value || "";
                if (type !== "Bed") return;

                const currentName = row.querySelector(".item-name")?.value || "";
                const currentPrice =
                    Number.parseFloat(row.querySelector(".item-unit")?.value) || 0;

                setNameField(
                    row,
                    "Bed",
                    currentName,
                    false,
                    selectedRoomId,
                    row.dataset.roomId || "",
                    currentPrice
                );
            });
    }

    /* ---------------------------------------------------------
       ITEM NAME DROPDOWN / INPUT
       Room and Bed are linked by room_id. A Bed dropdown is
       enabled only after a Room is selected and contains only
       beds belonging to that room. Patient-linked occupied beds
       are retained as an existing option so billing does not lose
       the already assigned bed.
    --------------------------------------------------------- */

    function setNameField(
        item,
        type,
        selectedName,
        locked,
        forcedRoomId = "",
        linkedRoomId = "",
        linkedPrice = 0
    ) {
        const container = item.querySelector(".item-name-control");
        if (!container) return;

        const entries = Array.isArray(catalog[type])
            ? catalog[type]
            : [];

        if (locked) {
            container.innerHTML = `
                <input
                    type="text"
                    name="items[][item_name]"
                    class="item-name"
                    value="${escapeHtml(selectedName || "Doctor Consultation")}" 
                    readonly
                >
            `;
            return;
        }

        if (Object.prototype.hasOwnProperty.call(catalog, type)) {
            let filteredEntries = entries;
            let roomId = forcedRoomId || linkedRoomId || "";

            if (type === "Bed") {
                roomId = roomId || getSelectedRoomId();

                if (roomId) {
                    filteredEntries = entries.filter(function (entry) {
                        return String(entry.room_id || "") === String(roomId);
                    });
                } else {
                    filteredEntries = [];
                }
            }

            const options = [];

            if (type === "Bed" && !roomId) {
                options.push(`<option value="">Select Room first</option>`);
            } else {
                options.push(
                    `<option value="">Select ${escapeHtml(type.toLowerCase())}</option>`
                );
            }

            filteredEntries.forEach(function (entry) {
                const roomLabel =
                    type === "Room" && entry.room_type
                        ? ` — ${entry.room_type}`
                        : "";

                const bedRoomLabel =
                    type === "Bed" && entry.room_number
                        ? ` — Room ${entry.room_number}`
                        : "";

                options.push(`
                    <option
                        value="${escapeHtml(entry.name)}"
                        data-price="${money(entry.price)}"
                        data-price-locked="${entry.price_locked ? "1" : "0"}"
                        ${entry.room_id ? `data-room-id="${escapeHtml(entry.room_id)}"` : ""}
                    >
                        ${escapeHtml(entry.name)}${escapeHtml(roomLabel || bedRoomLabel)}
                    </option>
                `);
            });

            const normalizedSelected = normalizeLinkedName(selectedName, type);
            let selectedEntry = filteredEntries.find(function (entry) {
                return normalizeLinkedName(entry.name, type) === normalizedSelected;
            });

            /* Keep a patient-linked room/bed even when its current database
             * status makes it absent from the Available catalog. */
            if (selectedName && type === "Bed" && roomId && !selectedEntry) {
                const linkedBed = findBedEntry(selectedName, roomId);
                const effectivePrice = linkedPrice > 0
                    ? linkedPrice
                    : (linkedBed?.price || 0);

                options.push(`
                    <option
                        value="${escapeHtml(selectedName)}"
                        data-price="${money(effectivePrice)}"
                        data-room-id="${escapeHtml(roomId)}"
                    >
                        ${escapeHtml(selectedName)} (existing)
                    </option>
                `);

                selectedEntry = {
                    name: selectedName,
                    room_id: roomId,
                    price: effectivePrice
                };
            }

            if (selectedName && !selectedEntry) {
                const historicalRoomId =
                    type === "Bed"
                        ? (getRoomIdForSelectedBedName(selectedName) || roomId)
                        : type === "Room"
                            ? roomId
                            : "";

                if (
                    type !== "Bed" ||
                    !roomId ||
                    !historicalRoomId ||
                    String(historicalRoomId) === String(roomId)
                ) {
                    options.push(`
                        <option
                            value="${escapeHtml(selectedName)}"
                            data-price="${money(linkedPrice)}"
                            ${historicalRoomId ? `data-room-id="${escapeHtml(historicalRoomId)}"` : ""}
                        >
                            ${escapeHtml(selectedName)} (existing)
                        </option>
                    `);
                }
            }

            container.innerHTML = `
                <select
                    name="items[][item_name]"
                    class="item-name item-name-select"
                    required
                    ${type === "Bed" && !roomId ? "disabled" : ""}
                >
                    ${options.join("")}
                </select>
            `;

            const select = container.querySelector(".item-name-select");

            if (select) {
                if (selectedEntry) {
                    const matching = Array.from(select.options).find(function (option) {
                        return normalizeLinkedName(option.value, type) ===
                            normalizeLinkedName(selectedEntry.name, type);
                    });
                    if (matching) select.value = matching.value;
                } else if (selectedName) {
                    const historicalOption = Array.from(select.options).find(
                        function (option) {
                            return normalizeLinkedName(option.value, type) === normalizedSelected;
                        }
                    );

                    if (historicalOption) {
                        select.value = historicalOption.value;
                    }
                }

                select.addEventListener("change", function () {
                    const selected = select.options[select.selectedIndex];
                    const price =
                        Number.parseFloat(selected?.getAttribute("data-price")) || 0;
                    const unit = item.querySelector(".item-unit");

                    if (unit && price >= 0) {
                        unit.value = money(price);
                        if (type === "Lab Test") {
                            unit.readOnly = selected?.getAttribute("data-price-locked") === "1";
                        }
                    }

                    if (type === "Room") {
                        refreshCreateBedDropdowns();
                    }

                    calculateTotals();
                });
            }

            return;
        }

        container.innerHTML = `
            <input
                type="text"
                name="items[][item_name]"
                class="item-name"
                value="${escapeHtml(selectedName || "")}" 
                placeholder="Enter ${escapeHtml(type.toLowerCase())} name"
                required
            >
        `;
    }

    function renumberItems() {
        if (!itemsContainer) return;

        itemsContainer
            .querySelectorAll(".bill-item-row")
            .forEach(function (item, index) {
                const number = item.querySelector(".item-number span");
                if (number) number.textContent = String(index + 1);

                const fields = {
                    ".item-type": "item_type",
                    ".item-name": "item_name",
                    ".item-qty": "quantity",
                    ".item-unit": "unit_price",
                    ".item-discount": "discount_amount",
                    ".item-tax": "tax_amount",
                    ".item-remarks": "remarks"
                };

                Object.entries(fields).forEach(function ([selector, key]) {
                    const field = item.querySelector(selector);
                    if (field) {
                        field.name = `items[${index}][${key}]`;
                    }
                });
            });
    }

    /* ---------------------------------------------------------
       TOTALS
    --------------------------------------------------------- */

    function calculateItemTotal(item) {
        const qty =
            Number.parseFloat(item.querySelector(".item-qty")?.value) || 0;

        const unit =
            Number.parseFloat(item.querySelector(".item-unit")?.value) || 0;

        const discount =
            Number.parseFloat(
                item.querySelector(".item-discount")?.value
            ) || 0;

        const tax =
            Number.parseFloat(
                item.querySelector(".item-tax")?.value
            ) || 0;

        const total = Math.max(
            0,
            qty * unit - discount + tax
        );

        const totalElement =
            item.querySelector(".item-line-total");

        if (totalElement) {
            totalElement.textContent = "₹ " + money(total);
        }

        return total;
    }

    function calculateTotals() {
        if (!itemsContainer) return;

        let additional = 0;

        itemsContainer
            .querySelectorAll(".bill-item-row")
            .forEach(function (item) {
                additional += calculateItemTotal(item);
            });

        /*
         * Doctor fee is authoritative from the selected appointment.
         * It is not an editable "additional item".
         */
        const selected =
            appointmentSelect?.options[
                appointmentSelect.selectedIndex
            ];

        const consultation =
            selected && selected.value
                ? (
                    Number.parseFloat(
                        selected.getAttribute("data-doctor-fee")
                    ) ||
                    Number.parseFloat(
                        selected.getAttribute("data-service-fee")
                    ) ||
                    0
                )
                : 0;

        const subtotal =
            consultation + additional;

        const discount =
            Math.max(0, Number.parseFloat(discountInput?.value) || 0);

        const tax =
            Math.max(0, Number.parseFloat(taxInput?.value) || 0);

        const payable =
            Math.max(0, subtotal - discount + tax);

        if (consultationPreview) {
            consultationPreview.textContent =
                "₹ " + money(consultation);
        }

        if (additionalPreview) {
            additionalPreview.textContent =
                "₹ " + money(additional);
        }

        if (subtotalPreview) {
            subtotalPreview.textContent =
                "₹ " + money(subtotal);
        }

        if (discountPreview) {
            discountPreview.textContent =
                "- ₹ " + money(discount);
        }

        if (taxPreview) {
            taxPreview.textContent =
                "+ ₹ " + money(tax);
        }

        if (payablePreview) {
            payablePreview.textContent = money(payable);
        }
    }

    discountInput?.addEventListener("input", calculateTotals);
    taxInput?.addEventListener("input", calculateTotals);

    /* ---------------------------------------------------------
       BILL FORM VALIDATION
    --------------------------------------------------------- */

    if (billForm) {
        billForm.addEventListener("submit", function (e) {
            if (!appointmentSelect?.value) {
                e.preventDefault();
                alert("Please select an appointment.");
                appointmentSelect?.focus();
                return;
            }

            const selected =
                appointmentSelect.options[
                    appointmentSelect.selectedIndex
                ];

            const doctorUnit =
                Number.parseFloat(
                    selected?.getAttribute("data-doctor-fee")
                ) ||
                Number.parseFloat(
                    selected?.getAttribute("data-service-fee")
                ) ||
                0;

            if (doctorUnit < 0) {
                e.preventDefault();
                alert("Invalid doctor consultation fee.");
                return;
            }

            let invalidAdditional = false;

            itemsContainer
                ?.querySelectorAll(".additional-item")
                .forEach(function (item) {
                    const name = item.querySelector(".item-name");
                    if (!name || !name.value.trim()) {
                        invalidAdditional = true;
                        name?.focus();
                    }
                });

            if (invalidAdditional) {
                e.preventDefault();
                alert("Please select an item name for every additional item.");
                return;
            }

            calculateTotals();
        });
    }

    /* ---------------------------------------------------------
       VIEW BILL
    --------------------------------------------------------- */

    document.querySelectorAll(".view-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            const id = button.getAttribute("data-id");
            const dataElement = document.getElementById("bill-" + id);

            if (!dataElement) return;

            const raw = dataElement.getAttribute("data-bill");
            if (!raw) return;

            let bill;

            try {
                bill = JSON.parse(raw);
            } catch (error) {
                console.error("Bill JSON error:", error);
                alert("Unable to open bill details.");
                return;
            }

            showBillDetails(bill);
            openModal(document.getElementById("viewBillModal"));
        });
    });

    function showBillDetails(bill) {
        const container = document.getElementById("billDetails");
        if (!container) return;

        const statusClass =
            String(bill.bill_status || "")
                .toLowerCase()
                .replace(/\s+/g, "-");

        container.innerHTML = `
            <div class="details-grid">
                <div>
                    <span>Bill Number</span>
                    <strong>${escapeHtml(bill.bill_no || "")}</strong>
                </div>
                <div>
                    <span>Patient</span>
                    <strong>${escapeHtml(bill.patient_name || "")}</strong>
                </div>
                <div>
                    <span>Patient Code</span>
                    <strong>${escapeHtml(bill.hospital_patient_code || "")}</strong>
                </div>
                <div>
                    <span>Appointment</span>
                    <strong>${escapeHtml(bill.appointment_no || "")}</strong>
                </div>
                <div>
                    <span>Doctor</span>
                    <strong>Dr. ${escapeHtml(bill.doctor_name || "")}</strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong class="status ${statusClass}">
                        ${escapeHtml(bill.bill_status || "")}
                    </strong>
                </div>
            </div>

            <div class="details-summary">
                <div>
                    <span>Subtotal</span>
                    <strong>₹ ${money(bill.total_amount)}</strong>
                </div>
                <div>
                    <span>Discount</span>
                    <strong>₹ ${money(bill.discount_amount)}</strong>
                </div>
                <div>
                    <span>Tax</span>
                    <strong>₹ ${money(bill.tax_amount)}</strong>
                </div>
                <div class="final">
                    <span>Payable</span>
                    <strong>₹ ${money(bill.payable_amount)}</strong>
                </div>
                <div>
                    <span>Paid</span>
                    <strong>₹ ${money(bill.paid_amount)}</strong>
                </div>
                <div>
                    <span>Outstanding</span>
                    <strong>₹ ${money(bill.outstanding)}</strong>
                </div>
            </div>

            ${
                bill.remarks
                    ? `
                        <div class="details-remarks">
                            <strong>Remarks</strong>
                            <p>${escapeHtml(bill.remarks)}</p>
                        </div>
                      `
                    : ""
            }
        `;
    }

    /* ---------------------------------------------------------
       EDIT BILL
    --------------------------------------------------------- */

    const editBillModal =
        document.getElementById("editBillModal");

    const editBillForm =
        document.getElementById("editBillForm");

    const editBillId =
        document.getElementById("editBillId");

    const editBillInfo =
        document.getElementById("editBillInfo");

    const editBillMeta =
        document.getElementById("editBillMeta");

    const editItemsContainer =
        document.getElementById("editItems");

    const editAddItemBtn =
        document.getElementById("editAddItemBtn");

    const editDiscountInput =
        document.getElementById("editBillDiscount");

    const editTaxInput =
        document.getElementById("editBillTax");

    const editRemarksInput =
        document.getElementById("editBillRemarks");

    const editSubtotalPreview =
        document.getElementById("editSubtotalPreview");

    const editDiscountPreview =
        document.getElementById("editDiscountPreview");

    const editTaxPreview =
        document.getElementById("editTaxPreview");

    const editPayablePreview =
        document.getElementById("editPayablePreview");

    const editCatalog =
        window.billingEditCatalog ||
        window.billingCatalog ||
        {};

    function normalizeEditLinkedName(value, type) {
        const text = String(value ?? "").trim();
        if (!text) return "";

        if (type === "Room") {
            return text.replace(/^Room\s+/i, "").trim();
        }

        if (type === "Bed") {
            return text
                .replace(/^Bed\s+/i, "")
                .replace(/\s*-\s*Room\s+[^-]+$/i, "")
                .trim();
        }

        return text;
    }

    function getSelectedEditRoomId() {
        if (!editItemsContainer) return "";

        const rows = Array.from(
            editItemsContainer.querySelectorAll(".edit-bill-item-row")
        );

        for (const row of rows) {
            const type = row.querySelector(".edit-item-type")?.value || "";
            if (type !== "Room") continue;

            const select = row.querySelector(".edit-item-name-select");
            const option = select?.options[select.selectedIndex];
            const roomId = option?.getAttribute("data-room-id") || "";

            if (roomId) return roomId;
        }

        return "";
    }

    function getEditBedRoomId(selectedName) {
        const normalized = normalizeEditLinkedName(selectedName, "Bed");
        const entries = Array.isArray(editCatalog.Bed)
            ? editCatalog.Bed
            : [];

        const entry = entries.find(function (item) {
            return normalizeEditLinkedName(item.name, "Bed") === normalized;
        });

        return entry?.room_id ? String(entry.room_id) : "";
    }

    function refreshEditBedDropdowns() {
        if (!editItemsContainer) return;

        const selectedRoomId = getSelectedEditRoomId();

        editItemsContainer
            .querySelectorAll(".edit-bill-item-row")
            .forEach(function (row) {
                const type = row.querySelector(".edit-item-type")?.value || "";
                if (type !== "Bed") return;

                const currentName =
                    row.querySelector(".edit-item-name")?.value || "";

                editItemNameField(
                    row,
                    "Bed",
                    currentName,
                    selectedRoomId
                );
            });
    }

    function editItemNameField(
        item,
        type,
        selectedName,
        forcedRoomId = ""
    ) {
        const container =
            item.querySelector(".edit-item-name-control");

        if (!container) return;

        const entries = Array.isArray(editCatalog[type])
            ? editCatalog[type]
            : [];

        let filteredEntries = entries;
        let roomId = forcedRoomId || "";

        if (type === "Bed") {
            roomId = roomId || getSelectedEditRoomId();

            if (roomId) {
                filteredEntries = entries.filter(function (entry) {
                    return String(entry.room_id || "") === String(roomId);
                });
            } else {
                filteredEntries = [];
            }
        }

        const options = [];

        if (type === "Bed" && !roomId) {
            options.push(`<option value="">Select Room first</option>`);
        } else {
            options.push(
                `<option value="">Select ${escapeHtml(type.toLowerCase())}</option>`
            );
        }

        filteredEntries.forEach(function (entry) {
            const statusText =
                entry.status
                    ? ` — ${entry.status}`
                    : "";

            const roomLabel =
                type === "Room" && entry.room_type
                    ? ` — ${entry.room_type}`
                    : "";

            const bedRoomLabel =
                type === "Bed" && entry.room_number
                    ? ` — Room ${entry.room_number}`
                    : "";

            options.push(`
                <option
                    value="${escapeHtml(entry.name)}"
                    data-price="${money(entry.price)}"
                    data-price-locked="${entry.price_locked ? "1" : "0"}"
                    ${entry.room_id ? `data-room-id="${escapeHtml(entry.room_id)}"` : ""}
                >
                    ${escapeHtml(entry.name)}${escapeHtml(roomLabel || bedRoomLabel)}${escapeHtml(statusText)}
                </option>
            `);
        });

        const normalizedSelected =
            normalizeEditLinkedName(selectedName, type);

        if (
            selectedName &&
            type === "Bed" &&
            roomId &&
            !filteredEntries.some(function (entry) {
                return normalizeEditLinkedName(entry.name, "Bed") === normalizedSelected;
            })
        ) {
            options.push(`
                <option
                    value="${escapeHtml(selectedName)}"
                    data-price="0"
                    data-room-id="${escapeHtml(roomId)}"
                >
                    ${escapeHtml(selectedName)} (existing)
                </option>
            `);
        }

        const selectedEntry = filteredEntries.find(function (entry) {
            return normalizeEditLinkedName(entry.name, type) === normalizedSelected;
        });

        if (selectedName && !selectedEntry) {
            const historicalRoomId =
                type === "Bed"
                    ? getEditBedRoomId(selectedName)
                    : type === "Room"
                        ? roomId
                        : "";

            if (
                type !== "Bed" ||
                !roomId ||
                !historicalRoomId ||
                String(historicalRoomId) === String(roomId)
            ) {
                options.push(`
                    <option
                        value="${escapeHtml(selectedName)}"
                        data-price="0"
                        ${historicalRoomId ? `data-room-id="${escapeHtml(historicalRoomId)}"` : ""}
                    >
                        ${escapeHtml(selectedName)} (existing)
                    </option>
                `);
            }
        }

        if (!Object.prototype.hasOwnProperty.call(editCatalog, type)) {
            container.innerHTML = `
                <input
                    type="text"
                    name=""
                    class="edit-item-name"
                    value="${escapeHtml(selectedName || "")}" 
                    placeholder="Item name"
                    required
                >
            `;
            return;
        }

        container.innerHTML = `
            <select
                class="edit-item-name edit-item-name-select"
                required
                ${type === "Bed" && !roomId ? "disabled" : ""}
            >
                ${options.join("")}
            </select>
        `;

        const select =
            container.querySelector(".edit-item-name-select");

        if (select) {
            if (selectedEntry) {
                select.value = selectedEntry.name;

                const selectedOption =
                    select.options[select.selectedIndex];

                const unit =
                    item.querySelector(".edit-item-unit");

                const price =
                    Number.parseFloat(
                        selectedOption?.getAttribute("data-price")
                    ) || 0;

                if (unit && type === "Lab Test") {
                    if (price > 0) {
                        unit.value = money(price);
                    }
                    unit.readOnly =
                        selectedOption?.getAttribute("data-price-locked") === "1";
                }
            } else if (selectedName) {
                const historicalOption = Array.from(select.options).find(
                    function (option) {
                        return normalizeEditLinkedName(option.value, type) === normalizedSelected;
                    }
                );

                if (historicalOption) {
                    select.value = historicalOption.value;
                }
            }

            select.addEventListener("change", function () {
                const selected =
                    select.options[select.selectedIndex];

                const price =
                    Number.parseFloat(
                        selected?.getAttribute("data-price")
                    ) || 0;

                const unit =
                    item.querySelector(".edit-item-unit");

                if (unit && price >= 0) {
                    unit.value = money(price);
                    if (type === "Lab Test") {
                        unit.readOnly = selected?.getAttribute("data-price-locked") === "1";
                    }
                }

                if (type === "Room") {
                    refreshEditBedDropdowns();
                }

                calculateEditTotals();
            });
        }
    }

    function createEditItemElement(data) {

        const wrapper =
            document.createElement("div");

        const type =
            data.type || "Other Service";

        const locked =
            Boolean(data.locked);

        wrapper.className =
            "bill-item-row edit-bill-item-row" +
            (locked
                ? " edit-locked-item"
                : " edit-additional-item");

        const action =
            locked
                ? `
                    <div class="item-action locked-control">
                        <span class="locked-badge">
                            <i class="fa-solid fa-lock"></i>
                            Mandatory
                        </span>
                    </div>
                  `
                : `
                    <div class="item-action">
                        <button
                            type="button"
                            class="remove-edit-item-btn"
                            title="Remove item"
                        >
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                  `;

        wrapper.innerHTML = `
            <div class="item-number">
                <span>1</span>
            </div>

            <div class="item-field">
                <label>Item Type</label>

                ${
                    locked
                        ? `
                            <input
                                type="text"
                                class="item-type-display"
                                value="${escapeHtml(type)}"
                                readonly
                            >
                            <input
                                type="hidden"
                                class="edit-item-type"
                                value="${escapeHtml(type)}"
                            >
                          `
                        : `
                            <select
                                class="edit-item-type"
                            >
                                <option value="Other Service">Other Service</option>
                                <option value="Room">Room</option>
                                <option value="Bed">Bed</option>
                                <option value="Lab Test">Lab Test</option>
                                <option value="Medicine">Medicine</option>
                                <option value="Procedure">Procedure</option>
                            </select>
                          `
                }
            </div>

            <div class="item-field item-name-field">
                <label>Item Name</label>
                <div class="edit-item-name-control"></div>
            </div>

            <div class="item-field">
                <label>Qty</label>

                <input
                    type="number"
                    class="edit-item-qty"
                    value="${escapeHtml(data.quantity ?? 1)}"
                    min="0.01"
                    step="0.01"
                    required
                    ${locked ? "readonly" : ""}
                >
            </div>

            <div class="item-field">
                <label>Unit Price</label>

                <div class="item-price-wrap">
                    <span>₹</span>

                    <input
                        type="number"
                        class="edit-item-unit"
                        value="${money(data.unit)}"
                        min="0"
                        step="0.01"
                        required
                        ${locked ? "readonly" : ""}
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Discount</label>

                <div class="item-price-wrap">
                    <span>₹</span>

                    <input
                        type="number"
                        class="edit-item-discount"
                        value="${money(data.discount)}"
                        min="0"
                        step="0.01"
                        ${locked ? "readonly" : ""}
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Tax</label>

                <div class="item-price-wrap">
                    <span>₹</span>

                    <input
                        type="number"
                        class="edit-item-tax"
                        value="${money(data.tax)}"
                        min="0"
                        step="0.01"
                        ${locked ? "readonly" : ""}
                    >
                </div>
            </div>

            <div class="item-field">
                <label>Total</label>
                <div class="edit-item-line-total item-line-total">
                    ₹ 0.00
                </div>
            </div>

            <div class="item-field item-remarks-field">
                <label>Remarks</label>

                <input
                    type="text"
                    class="edit-item-remarks"
                    value="${escapeHtml(data.remarks || "")}"
                    placeholder="Optional"
                    ${locked ? "readonly" : ""}
                >
            </div>

            ${action}
        `;

        const typeSelect =
            wrapper.querySelector(".edit-item-type");

        if (typeSelect) {

            typeSelect.value =
                type;

            typeSelect.addEventListener(
                "change",
                function () {

                    editItemNameField(
                        wrapper,
                        typeSelect.value,
                        ""
                    );

                    calculateEditTotals();
                }
            );
        }

        editItemNameField(
            wrapper,
            type,
            data.name || "",
            data.roomId || ""
        );

        if (locked) {
            const nameControl =
                wrapper.querySelector(".edit-item-name-control");

            nameControl.innerHTML = `
                <input
                    type="text"
                    class="edit-item-name"
                    value="${escapeHtml(data.name || "")}"
                    readonly
                >
            `;
        }

        wrapper
            .querySelectorAll("input, select")
            .forEach(function (field) {
                field.addEventListener(
                    "input",
                    calculateEditTotals
                );

                field.addEventListener(
                    "change",
                    calculateEditTotals
                );
            });

        const removeBtn =
            wrapper.querySelector(
                ".remove-edit-item-btn"
            );

        if (removeBtn) {
            removeBtn.addEventListener(
                "click",
                function () {

                    wrapper.remove();

                    renumberEditItems();
                    calculateEditTotals();
                }
            );
        }

        calculateEditItemTotal(wrapper);

        return wrapper;
    }

    function renumberEditItems() {

        if (!editItemsContainer) return;

        editItemsContainer
            .querySelectorAll(".edit-bill-item-row")
            .forEach(function (item, index) {

                const number =
                    item.querySelector(
                        ".item-number span"
                    );

                if (number) {
                    number.textContent =
                        String(index + 1);
                }

                const fields = {
                    ".edit-item-type":
                        "item_type",
                    ".edit-item-name":
                        "item_name",
                    ".edit-item-qty":
                        "quantity",
                    ".edit-item-unit":
                        "unit_price",
                    ".edit-item-discount":
                        "discount_amount",
                    ".edit-item-tax":
                        "tax_amount",
                    ".edit-item-remarks":
                        "remarks"
                };

                Object.entries(fields)
                    .forEach(function ([selector, key]) {

                        const field =
                            item.querySelector(
                                selector
                            );

                        if (field) {
                            field.name =
                                `items[${index}][${key}]`;
                        }
                    });
            });
    }

    function calculateEditItemTotal(item) {

        const qty =
            Number.parseFloat(
                item.querySelector(
                    ".edit-item-qty"
                )?.value
            ) || 0;

        const unit =
            Number.parseFloat(
                item.querySelector(
                    ".edit-item-unit"
                )?.value
            ) || 0;

        const discount =
            Number.parseFloat(
                item.querySelector(
                    ".edit-item-discount"
                )?.value
            ) || 0;

        const tax =
            Number.parseFloat(
                item.querySelector(
                    ".edit-item-tax"
                )?.value
            ) || 0;

        const total =
            Math.max(
                0,
                qty * unit - discount + tax
            );

        const output =
            item.querySelector(
                ".edit-item-line-total"
            );

        if (output) {
            output.textContent =
                "₹ " + money(total);
        }

        return total;
    }

    function calculateEditTotals() {

        if (!editItemsContainer) return;

        let subtotal = 0;

        editItemsContainer
            .querySelectorAll(
                ".edit-bill-item-row"
            )
            .forEach(function (item) {

                subtotal +=
                    calculateEditItemTotal(item);
            });

        const discount =
            Math.max(
                0,
                Number.parseFloat(
                    editDiscountInput?.value
                ) || 0
            );

        const tax =
            Math.max(
                0,
                Number.parseFloat(
                    editTaxInput?.value
                ) || 0
            );

        const payable =
            Math.max(
                0,
                subtotal - discount + tax
            );

        if (editSubtotalPreview) {
            editSubtotalPreview.textContent =
                "₹ " + money(subtotal);
        }

        if (editDiscountPreview) {
            editDiscountPreview.textContent =
                "- ₹ " + money(discount);
        }

        if (editTaxPreview) {
            editTaxPreview.textContent =
                "+ ₹ " + money(tax);
        }

        if (editPayablePreview) {
            editPayablePreview.textContent =
                money(payable);
        }
    }

    function openEditBill(bill) {

        if (!bill || !editBillModal) return;

        if (editBillId) {
            editBillId.value =
                bill.bill_id || "";
        }

        if (editBillMeta) {
            editBillMeta.textContent =
                `${bill.bill_no || ""} • ${bill.patient_name || ""}`;
        }

        if (editBillInfo) {
            editBillInfo.innerHTML = `
                <div>
                    <span>Patient</span>
                    <strong>${escapeHtml(
                        bill.patient_name || ""
                    )}</strong>
                </div>

                <div>
                    <span>Appointment</span>
                    <strong>${escapeHtml(
                        bill.appointment_no || ""
                    )}</strong>
                </div>

                <div>
                    <span>Doctor</span>
                    <strong>Dr. ${escapeHtml(
                        bill.doctor_name || ""
                    )}</strong>
                </div>

                <div>
                    <span>Paid</span>
                    <strong>₹ ${money(
                        bill.paid_amount
                    )}</strong>
                </div>
            `;
        }

        if (editDiscountInput) {
            editDiscountInput.value =
                money(bill.discount_amount);
        }

        if (editTaxInput) {
            editTaxInput.value =
                money(bill.tax_amount);
        }

        if (editRemarksInput) {
            editRemarksInput.value =
                bill.remarks || "";
        }

        if (editItemsContainer) {
            editItemsContainer.innerHTML = "";

            const items =
                Array.isArray(bill.items)
                    ? bill.items
                    : [];

            items.forEach(function (item) {

                const rawType =
                    String(
                        item.item_type ||
                        "Other"
                    ).trim();

                const lowerType =
                    rawType.toLowerCase();

                const locked =
                    lowerType === "service" ||
                    lowerType === "consultation" ||
                    lowerType === "doctor fee";

                const type =
                    locked
                        ? "Doctor Fee"
                        : lowerType === "room charge"
                            ? "Room"
                            : lowerType === "lab test"
                                ? "Lab Test"
                                : lowerType === "medicine"
                                    ? "Medicine"
                                    : "Other Service";

                const row =
                    createEditItemElement({
                        type: type,
                        name: item.item_name || "",
                        quantity:
                            item.quantity || 1,
                        unit:
                            item.unit_price || 0,
                        discount:
                            item.discount_amount || 0,
                        tax:
                            item.tax_amount || 0,
                        remarks:
                            item.remarks || "",
                        locked: locked
                    });

                editItemsContainer.appendChild(row);
            });

            /*
             * Pull in newly recorded patient charges that are not already
             * present on this bill. They remain normal editable rows.
             */
            const existingKeys = new Set();

            editItemsContainer
                .querySelectorAll(".edit-bill-item-row")
                .forEach(function (row) {
                    const name =
                        row.querySelector(".edit-item-name")?.value || "";

                    const type =
                        row.querySelector(".edit-item-type")?.value || "";

                    existingKeys.add(
                        String(type).toLowerCase() +
                        "|" +
                        String(name).toLowerCase()
                    );
                });

            const patientCharges =
                Array.isArray(bill.patient_charges)
                    ? bill.patient_charges
                    : [];

            patientCharges.forEach(function (charge) {
                const type =
                    charge.type || "Other Service";

                const name =
                    charge.name || "";

                const key =
                    String(type).toLowerCase() +
                    "|" +
                    String(name).toLowerCase();

                if (!name || existingKeys.has(key)) {
                    return;
                }

                const row =
                    createEditItemElement({
                        type: type,
                        name: name,
                        quantity: charge.quantity || 1,
                        unit: charge.unit || 0,
                        discount: charge.discount || 0,
                        tax: charge.tax || 0,
                        remarks: charge.remarks || "",
                        roomId: charge.room_id || "",
                        locked: false
                    });

                row.classList.add("auto-linked-item");
                editItemsContainer.appendChild(row);
                existingKeys.add(key);
            });

            renumberEditItems();
            refreshEditBedDropdowns();

            if (!items.length && !patientCharges.length) {
                const row =
                    createEditItemElement({
                        type: "Other Service",
                        name: "",
                        quantity: 1,
                        unit: 0,
                        discount: 0,
                        tax: 0,
                        remarks: "",
                        locked: false
                    });

                editItemsContainer.appendChild(row);
                renumberEditItems();
            }
        }

        calculateEditTotals();
        openModal(editBillModal);
    }

    document
        .querySelectorAll(".edit-btn")
        .forEach(function (button) {

            button.addEventListener(
                "click",
                function () {

                    const id =
                        button.getAttribute(
                            "data-id"
                        );

                    const dataElement =
                        document.getElementById(
                            "bill-" + id
                        );

                    if (!dataElement) return;

                    const raw =
                        dataElement.getAttribute(
                            "data-bill"
                        );

                    if (!raw) return;

                    try {

                        const bill =
                            JSON.parse(raw);

                        openEditBill(bill);

                    } catch (error) {

                        console.error(
                            "Edit bill JSON error:",
                            error
                        );

                        alert(
                            "Unable to open bill editor."
                        );
                    }
                }
            );
        });

    if (editAddItemBtn) {

        editAddItemBtn.addEventListener(
            "click",
            function () {

                const row =
                    createEditItemElement({
                        type: "Other Service",
                        name: "",
                        quantity: 1,
                        unit: 0,
                        discount: 0,
                        tax: 0,
                        remarks: "",
                        locked: false
                    });

                editItemsContainer?.appendChild(row);

                renumberEditItems();
                calculateEditTotals();
            }
        );
    }

    editDiscountInput?.addEventListener(
        "input",
        calculateEditTotals
    );

    editTaxInput?.addEventListener(
        "input",
        calculateEditTotals
    );

    if (editBillForm) {

        editBillForm.addEventListener(
            "submit",
            function (e) {

                renumberEditItems();

                let invalid = false;

                editItemsContainer
                    ?.querySelectorAll(
                        ".edit-bill-item-row"
                    )
                    .forEach(function (item) {

                        const name =
                            item.querySelector(
                                ".edit-item-name"
                            );

                        if (
                            !name ||
                            !String(name.value).trim()
                        ) {
                            invalid = true;
                        }
                    });

                if (invalid) {
                    e.preventDefault();

                    alert(
                        "Please select an item name for every bill item."
                    );

                    return;
                }

                calculateEditTotals();
            }
        );
    }

    /* ---------------------------------------------------------
       CANCEL BILL
    --------------------------------------------------------- */

    const deleteBillModal =
        document.getElementById("deleteBillModal");
    const deleteBillId =
        document.getElementById("deleteBillId");
    const deleteBillName =
        document.getElementById("deleteBillName");

    document.querySelectorAll(".delete-btn").forEach(function (button) {
        button.addEventListener("click", function () {
            if (deleteBillId) {
                deleteBillId.value =
                    button.getAttribute("data-id") || "";
            }

            if (deleteBillName) {
                deleteBillName.textContent =
                    button.getAttribute("data-name") || "";
            }

            openModal(deleteBillModal);
        });
    });

    /* ---------------------------------------------------------
       BILLING-ONLY SIDEBAR SAFETY
       Does not change the shared sidebar file or other pages.
    --------------------------------------------------------- */

    const billingSidebar =
        document.querySelector(".billing-page .sidebar");

    if (billingSidebar) {
        billingSidebar.scrollTop = 0;

        const sidebarToggle =
            billingSidebar.querySelector(".sidebar-toggle");

        if (sidebarToggle) {
            sidebarToggle.addEventListener(
                "click",
                function () {
                    requestAnimationFrame(function () {
                        billingSidebar.scrollTop = 0;
                    });
                }
            );
        }
    }

    /* ---------------------------------------------------------
       INITIAL
    --------------------------------------------------------- */

    removeDoctorFee();
    updateMandatoryFeeBox(0, "", "");
    calculateTotals();
});
