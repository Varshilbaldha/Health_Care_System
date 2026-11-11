document.addEventListener("DOMContentLoaded", function () {

    // =====================================================
    // ELEMENTS
    // =====================================================

    const addPaymentBtn =
        document.getElementById("addPaymentBtn");

    const paymentModal =
        document.getElementById("paymentModal");

    const paymentForm =
        document.getElementById("paymentForm");

    const billSelect =
        document.getElementById("billSelect");

    const outstandingInput =
        document.getElementById("outstanding");

    const amountInput =
        document.getElementById("amount");

    const paymentMethod =
        document.getElementById("paymentMethod");

    const transactionGroup =
        document.getElementById("transactionGroup");

    const transactionInput =
        document.getElementById("transactionReference");


    // =====================================================
    // OPEN MODAL
    // =====================================================

    if (addPaymentBtn) {

        addPaymentBtn.addEventListener(
            "click",
            function () {

                paymentModal.classList.add("show");

                document.body.classList.add(
                    "modal-open"
                );

                paymentForm.reset();

                outstandingInput.value =
                    "₹ 0.00";

                amountInput.value = "";

                amountInput.removeAttribute(
                    "max"
                );

                transactionGroup.style.display =
                    "none";

                transactionInput.required =
                    false;

                transactionInput.value =
                    "";

            }
        );

    }


    // =====================================================
    // CLOSE MODAL
    // =====================================================

    function closeModal() {

        paymentModal.classList.remove(
            "show"
        );

        document.body.classList.remove(
            "modal-open"
        );

    }


    const closeBtn =
        paymentModal.querySelector(
            ".close-btn"
        );


    const cancelBtn =
        paymentModal.querySelector(
            ".cancel-btn"
        );


    if (closeBtn) {

        closeBtn.addEventListener(
            "click",
            closeModal
        );

    }


    if (cancelBtn) {

        cancelBtn.addEventListener(
            "click",
            closeModal
        );

    }


    if (paymentModal) {

        paymentModal.addEventListener(
            "click",
            function (event) {

                if (
                    event.target ===
                    paymentModal
                ) {

                    closeModal();

                }

            }
        );

    }


    // =====================================================
    // CASH / UPI
    // =====================================================

    function updateTransactionField() {

        if (
            paymentMethod.value ===
            "UPI"
        ) {

            transactionGroup.style.display =
                "flex";

            transactionInput.required =
                true;

        } else {

            transactionGroup.style.display =
                "none";

            transactionInput.required =
                false;

            transactionInput.value =
                "";

        }

    }


    paymentMethod.addEventListener(
        "change",
        updateTransactionField
    );


    // =====================================================
    // BILL SELECTION
    // =====================================================

    billSelect.addEventListener(
        "change",
        function () {

            const billId =
                billSelect.value;


            if (!billId) {

                outstandingInput.value =
                    "₹ 0.00";

                amountInput.value =
                    "";

                amountInput.removeAttribute(
                    "max"
                );

                return;

            }


            let billData = null;


            if (
                typeof window.paymentBills !==
                "undefined"
            ) {

                billData =
                    window.paymentBills[billId];

            }


            if (!billData) {
                return;
            }


            const outstanding =
                parseFloat(
                    billData.outstanding
                ) || 0;


            outstandingInput.value =
                "₹ " +
                outstanding.toFixed(2);


            amountInput.max =
                outstanding.toFixed(2);


            const currentAmount =
                parseFloat(
                    amountInput.value
                ) || 0;


            if (
                currentAmount >
                outstanding
            ) {

                amountInput.value =
                    outstanding.toFixed(2);

            }

        }
    );


    // =====================================================
    // AMOUNT VALIDATION
    // =====================================================

    amountInput.addEventListener(
        "input",
        function () {

            const max =
                parseFloat(
                    amountInput.max
                ) || 0;


            const value =
                parseFloat(
                    amountInput.value
                ) || 0;


            if (value < 0) {

                amountInput.value =
                    "";

                return;

            }


            if (
                max > 0 &&
                value > max
            ) {

                amountInput.value =
                    max.toFixed(2);

            }

        }
    );


    // =====================================================
    // TRANSACTION ID
    // =====================================================

    transactionInput.addEventListener(
        "input",
        function () {

            let value =
                transactionInput.value;


            value =
                value.replace(
                    /\s/g,
                    ""
                );


            value =
                value.replace(
                    /[^A-Za-z0-9._\/-]/g,
                    ""
                );


            if (value.length > 50) {

                value =
                    value.substring(
                        0,
                        50
                    );

            }


            transactionInput.value =
                value;

        }
    );


    // =====================================================
    // FORM SUBMIT
    // =====================================================

    paymentForm.addEventListener(
        "submit",
        function (event) {

            // ---------------------------------------------
            // BILL
            // ---------------------------------------------

            if (!billSelect.value) {

                event.preventDefault();

                alert(
                    "Please select a bill."
                );

                billSelect.focus();

                return;

            }


            // ---------------------------------------------
            // AMOUNT
            // ---------------------------------------------

            const amount =
                parseFloat(
                    amountInput.value
                ) || 0;


            const max =
                parseFloat(
                    amountInput.max
                ) || 0;


            if (amount <= 0) {

                event.preventDefault();

                alert(
                    "Payment amount must be greater than ₹0."
                );

                amountInput.focus();

                return;

            }


            if (
                max > 0 &&
                amount > max
            ) {

                event.preventDefault();

                alert(
                    "Payment amount cannot be greater than outstanding amount."
                );

                amountInput.focus();

                return;

            }


            // ---------------------------------------------
            // UPI TRANSACTION ID
            // ---------------------------------------------

            if (
                paymentMethod.value ===
                "UPI"
            ) {

                const transactionId =
                    transactionInput.value.trim();


                const pattern =
                    /^[A-Za-z0-9][A-Za-z0-9._\/-]{7,49}$/;


                if (!transactionId) {

                    event.preventDefault();

                    alert(
                        "Transaction ID / UTR is compulsory for UPI."
                    );

                    transactionInput.focus();

                    return;

                }


                if (
                    !pattern.test(
                        transactionId
                    )
                ) {

                    event.preventDefault();

                    alert(
                        "Please enter a valid Transaction ID / UTR."
                    );

                    transactionInput.focus();

                    return;

                }

            }


            // ---------------------------------------------
            // CASH
            // ---------------------------------------------

            if (
                paymentMethod.value ===
                "Cash"
            ) {

                transactionInput.value =
                    "";

            }


            // ---------------------------------------------
            // CONFIRM
            // ---------------------------------------------

            const confirmed =
                confirm(
                    "Record this payment?\n\n" +
                    "Amount: ₹" +
                    amount.toFixed(2) +
                    "\n" +
                    "Method: " +
                    paymentMethod.value +
                    "\n\n" +
                    "Payment will be recorded as Successful."
                );


            if (!confirmed) {

                event.preventDefault();

            }

        }
    );


    // =====================================================
    // ESC KEY
    // =====================================================

    document.addEventListener(
        "keydown",
        function (event) {

            if (
                event.key === "Escape"
            ) {

                closeModal();

            }

        }
    );


    // =====================================================
    // INITIAL STATE
    // =====================================================

    updateTransactionField();

});