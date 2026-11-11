<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';


// =========================================================
// SESSION
// =========================================================

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found.");
}


// =========================================================
// BILL ID
// =========================================================

$bill_id = (int)($_GET['bill_id'] ?? 0);

if ($bill_id <= 0) {
    die("Invalid bill ID.");
}


// =========================================================
// DATABASE CONFIG
// =========================================================

$db_host = DB_HOST;
$db_user = DB_USER;
$db_pass = DB_PASS;


// =========================================================
// HELPER FUNCTIONS
// =========================================================

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function money($value): string
{
    return number_format(
        (float)$value,
        2,
        '.',
        ','
    );
}


/**
 * Prepare SQL safely.
 * This prevents the misleading:
 * mysqli_stmt_bind_param(): Argument #1 must be mysqli_stmt, bool given
 */
function prepare_stmt(
    mysqli $conn,
    string $sql
): mysqli_stmt {

    $stmt = mysqli_prepare(
        $conn,
        $sql
    );

    if ($stmt === false) {

        die(
            "Database query preparation failed.<br><br>" .
            "<strong>MySQL Error:</strong> " .
            e(mysqli_error($conn))
        );

    }

    return $stmt;
}


// =========================================================
// CENTRAL DATABASE
// =========================================================

$central = db_connect();

if (!$central) {

    die(
        "Central database connection failed: " .
        mysqli_connect_error()
    );

}

mysqli_set_charset(
    $central,
    'utf8mb4'
);


// =========================================================
// GET HOSPITAL
// =========================================================

$sql = "
    SELECT
        hospital_id,
        hospital_name,
        database_name

    FROM hospital_registration

    WHERE application_no = ?

    LIMIT 1
";

$stmt = prepare_stmt(
    $central,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $application_no
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$hospital = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$hospital) {
    die("Hospital record not found.");
}


$hospital_name =
    $hospital['hospital_name'];

$db_name =
    $hospital['database_name'];


// =========================================================
// HOSPITAL DATABASE
// =========================================================

$conn = db_connect($db_name);

if (!$conn) {

    die(
        "Hospital database connection failed: " .
        mysqli_connect_error()
    );

}

mysqli_set_charset(
    $conn,
    'utf8mb4'
);


// =========================================================
// BILL INFORMATION
// =========================================================

$sql = "

    SELECT

        b.bill_id,
        b.appointment_id,
        b.bill_no,

        b.total_amount,
        b.discount_amount,
        b.tax_amount,
        b.payable_amount,

        b.bill_status,
        b.remarks,

        b.created_at,
        b.updated_at,

        a.appointment_no,
        a.appointment_date,
        a.appointment_time,
        a.appointment_type,
        a.consultation_mode,

        d.doctor_name,
        d.specialization,
        d.qualification,

        s.service_name,
        s.service_code,
        s.service_type

    FROM billing b

    INNER JOIN appointments a
        ON a.appointment_id = b.appointment_id

    LEFT JOIN doctors d
        ON d.doctor_id = a.doctor_id

    LEFT JOIN services s
        ON s.service_id = a.service_id

    WHERE b.bill_id = ?

    LIMIT 1
";


$stmt = prepare_stmt(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $bill_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$bill = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$bill) {
    die("Bill not found.");
}


// =========================================================
// PATIENT INFORMATION
// =========================================================

$patient = [

    'patient_name' => 'Unknown Patient',

    'hospital_patient_code' => '',

    'phone' => '',

    'email' => ''

];


$sql = "

    SELECT

        CONCAT(
            COALESCE(pa.first_name, ''),
            ' ',
            COALESCE(pa.last_name, '')
        ) AS patient_name,

        phm.hospital_patient_code

    FROM appointments a

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON phm.mapping_id = a.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON pa.account_id = phm.account_id

    WHERE a.appointment_id = ?

    LIMIT 1
";


$stmt = prepare_stmt(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $bill['appointment_id']
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$patient_data =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if ($patient_data) {

    $patient = array_merge(
        $patient,
        $patient_data
    );

}


// =========================================================
// BILL ITEMS
// =========================================================

$items = [];


$sql = "

    SELECT

        bill_item_id,
        item_type,
        item_name,
        quantity,
        unit_price,
        discount_amount,
        tax_amount,
        total_amount,
        remarks,
        created_at

    FROM bill_items

    WHERE bill_id = ?

    ORDER BY bill_item_id ASC
";


$stmt = prepare_stmt(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $bill_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


while ($row = mysqli_fetch_assoc($result)) {

    $items[] = $row;

}

mysqli_stmt_close($stmt);


// =========================================================
// PAYMENT SUMMARY
// =========================================================

$paid_amount = 0;


$sql = "

    SELECT

        COALESCE(
            SUM(
                CASE
                    WHEN payment_status = 'Success'
                    THEN amount
                    ELSE 0
                END
            ),
            0
        ) AS paid_amount

    FROM payments

    WHERE bill_id = ?
";


$stmt = prepare_stmt(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $bill_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$payment_summary =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if ($payment_summary) {

    $paid_amount =
        (float)$payment_summary['paid_amount'];

}


$payable_amount =
    (float)$bill['payable_amount'];


$outstanding_amount = max(
    0,
    $payable_amount - $paid_amount
);


// =========================================================
// PAYMENT HISTORY
// =========================================================

$payments = [];


$sql = "

    SELECT

        payment_id,
        payment_no,
        payment_method,
        amount,
        transaction_reference,
        payment_status,
        remarks,
        paid_at,
        created_at

    FROM payments

    WHERE bill_id = ?

    ORDER BY payment_id ASC
";


$stmt = prepare_stmt(
    $conn,
    $sql
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $bill_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);


while ($row = mysqli_fetch_assoc($result)) {

    $payments[] = $row;

}

mysqli_stmt_close($stmt);


// =========================================================
// DISPLAY STATUS
// =========================================================

$display_status =
    $bill['bill_status'];


if (
    $bill['bill_status'] !== 'Cancelled'
) {

    if (
        $payable_amount > 0 &&
        $outstanding_amount <= 0
    ) {

        $display_status = 'Paid';

    } elseif (
        $paid_amount > 0 &&
        $outstanding_amount > 0
    ) {

        $display_status = 'Partially Paid';

    } else {

        $display_status = 'Pending';

    }

}


// =========================================================
// SERVICE INFORMATION
// =========================================================

$main_service =
    $bill['service_name'] ?? '';


// =========================================================
// DATE / TIME
// =========================================================

$bill_date = '';

if (!empty($bill['created_at'])) {

    $bill_date = date(
        'd M Y',
        strtotime($bill['created_at'])
    );

}


$appointment_date = '';

if (!empty($bill['appointment_date'])) {

    $appointment_date = date(
        'd M Y',
        strtotime($bill['appointment_date'])
    );

}


$appointment_time = '';

if (!empty($bill['appointment_time'])) {

    $appointment_time = date(
        'h:i A',
        strtotime($bill['appointment_time'])
    );

}

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Invoice <?= e($bill['bill_no']) ?> |
        <?= e($hospital_name) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/invoice.css"
    >

</head>


<body>


<div class="invoice-page">


    <!-- =====================================================
         ACTIONS
    ====================================================== -->

    <div class="invoice-actions no-print">

        <a
            href="billing.php"
            class="action-btn secondary"
        >
            ← Back to Billing
        </a>


        <div class="action-right">

            <button
                type="button"
                class="action-btn print-btn"
                onclick="window.print()"
            >
                🖨 Print
            </button>


            <button
                type="button"
                class="action-btn download-btn"
                onclick="window.print()"
            >
                ↓ Download / Save PDF
            </button>

        </div>

    </div>


    <!-- =====================================================
         INVOICE CARD
    ====================================================== -->

    <div class="invoice-card">


        <!-- HEADER -->

        <div class="invoice-header">


            <div class="hospital-info">

                <div class="hospital-logo">
                    H
                </div>


                <div>

                    <h1>
                        <?= e($hospital_name) ?>
                    </h1>

                    <p>
                        Hospital Management System
                    </p>

                    <span>
                        Patient Billing & Payment Invoice
                    </span>

                </div>

            </div>


            <div class="invoice-title">

                <h2>
                    INVOICE
                </h2>

                <div class="invoice-number">

                    <?= e($bill['bill_no']) ?>

                </div>

                <div class="invoice-date">

                    Date:
                    <?= e($bill_date) ?>

                </div>

            </div>

        </div>


        <div class="divider"></div>


        <!-- PATIENT / DOCTOR -->

        <div class="info-grid">


            <div class="info-box">

                <div class="section-label">
                    PATIENT INFORMATION
                </div>


                <h3>
                    <?= e(
                        trim(
                            $patient['patient_name']
                        )
                    ) ?>
                </h3>


                <?php if (
                    $patient['hospital_patient_code'] !== ''
                ): ?>

                    <p>
                        <strong>
                            Patient Code:
                        </strong>

                        <?= e(
                            $patient['hospital_patient_code']
                        ) ?>
                    </p>

                <?php endif; ?>


                <?php if (
                    $patient['phone'] !== ''
                ): ?>

                    <p>
                        <strong>
                            Phone:
                        </strong>

                        <?= e(
                            $patient['phone']
                        ) ?>
                    </p>

                <?php endif; ?>


                <?php if (
                    $patient['email'] !== ''
                ): ?>

                    <p>
                        <strong>
                            Email:
                        </strong>

                        <?= e(
                            $patient['email']
                        ) ?>
                    </p>

                <?php endif; ?>

            </div>


            <div class="info-box">

                <div class="section-label">
                    DOCTOR INFORMATION
                </div>


                <h3>

                    Dr.
                    <?= e(
                        $bill['doctor_name'] ?? ''
                    ) ?>

                </h3>


                <p>

                    <strong>
                        Specialization:
                    </strong>

                    <?= e(
                        $bill['specialization'] ?? ''
                    ) ?>

                </p>


                <p>

                    <strong>
                        Qualification:
                    </strong>

                    <?= e(
                        $bill['qualification'] ?? ''
                    ) ?>

                </p>


                <p>

                    <strong>
                        Service:
                    </strong>

                    <?= e(
                        $main_service
                    ) ?>

                </p>

            </div>

        </div>


        <!-- APPOINTMENT -->

        <div class="appointment-box">


            <div>

                <span>
                    Appointment No.
                </span>

                <strong>
                    <?= e(
                        $bill['appointment_no']
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Appointment Date
                </span>

                <strong>
                    <?= e(
                        $appointment_date
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Appointment Time
                </span>

                <strong>
                    <?= e(
                        $appointment_time
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Type
                </span>

                <strong>
                    <?= e(
                        $bill['appointment_type']
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Consultation
                </span>

                <strong>
                    <?= e(
                        $bill['consultation_mode']
                    ) ?>
                </strong>

            </div>

        </div>


        <!-- BILL ITEMS -->

        <div class="section-heading">

            <h3>
                Services & Charges
            </h3>

        </div>


        <div class="table-wrapper">

            <table class="items-table">

                <thead>

                    <tr>

                        <th>#</th>

                        <th>Type</th>

                        <th>Item / Service</th>

                        <th class="right">
                            Qty
                        </th>

                        <th class="right">
                            Unit Price
                        </th>

                        <th class="right">
                            Discount
                        </th>

                        <th class="right">
                            Tax
                        </th>

                        <th class="right">
                            Total
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($items)): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-row"
                        >
                            No bill items found.
                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach (
                        $items as $index => $item
                    ): ?>

                        <tr>

                            <td>
                                <?= $index + 1 ?>
                            </td>


                            <td>

                                <span class="item-type">

                                    <?= e(
                                        $item['item_type']
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <strong>

                                    <?= e(
                                        $item['item_name']
                                    ) ?>

                                </strong>


                                <?php if (
                                    !empty(
                                        $item['remarks']
                                    )
                                ): ?>

                                    <small>

                                        <?= e(
                                            $item['remarks']
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </td>


                            <td class="right">

                                <?= money(
                                    $item['quantity']
                                ) ?>

                            </td>


                            <td class="right">

                                ₹
                                <?= money(
                                    $item['unit_price']
                                ) ?>

                            </td>


                            <td class="right">

                                ₹
                                <?= money(
                                    $item['discount_amount']
                                ) ?>

                            </td>


                            <td class="right">

                                ₹
                                <?= money(
                                    $item['tax_amount']
                                ) ?>

                            </td>


                            <td class="right total-cell">

                                ₹
                                <?= money(
                                    $item['total_amount']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>


        <!-- FINANCIAL SUMMARY -->

        <div class="summary-area">


            <div class="remarks-box">

                <div class="section-label">
                    REMARKS
                </div>


                <p>

                    <?= !empty(
                        $bill['remarks']
                    )
                        ? nl2br(
                            e(
                                $bill['remarks']
                            )
                        )
                        : 'No remarks.' ?>

                </p>

            </div>


            <div class="amount-summary">


                <div class="amount-row">

                    <span>
                        Total Amount
                    </span>

                    <strong>
                        ₹
                        <?= money(
                            $bill['total_amount']
                        ) ?>
                    </strong>

                </div>


                <div class="amount-row">

                    <span>
                        Discount
                    </span>

                    <strong>
                        − ₹
                        <?= money(
                            $bill['discount_amount']
                        ) ?>
                    </strong>

                </div>


                <div class="amount-row">

                    <span>
                        Tax
                    </span>

                    <strong>
                        + ₹
                        <?= money(
                            $bill['tax_amount']
                        ) ?>
                    </strong>

                </div>


                <div class="amount-row payable">

                    <span>
                        Payable Amount
                    </span>

                    <strong>
                        ₹
                        <?= money(
                            $payable_amount
                        ) ?>
                    </strong>

                </div>


                <div class="amount-row paid">

                    <span>
                        Paid Amount
                    </span>

                    <strong>
                        ₹
                        <?= money(
                            $paid_amount
                        ) ?>
                    </strong>

                </div>


                <div class="amount-row outstanding">

                    <span>
                        Outstanding
                    </span>

                    <strong>
                        ₹
                        <?= money(
                            $outstanding_amount
                        ) ?>
                    </strong>

                </div>


                <div class="bill-status">

                    <span>
                        Bill Status
                    </span>


                    <strong
                        class="
                            status
                            <?= strtolower(
                                str_replace(
                                    ' ',
                                    '-',
                                    $display_status
                                )
                            )
                        ?>"
                    >

                        <?= e(
                            $display_status
                        ) ?>

                    </strong>

                </div>

            </div>

        </div>


        <!-- PAYMENT HISTORY -->

        <div class="section-heading payment-heading">

            <h3>
                Payment History
            </h3>

        </div>


        <?php if (!empty($payments)): ?>

            <div class="table-wrapper">

                <table class="payment-table">

                    <thead>

                        <tr>

                            <th>
                                Payment No.
                            </th>

                            <th>
                                Method
                            </th>

                            <th>
                                Amount
                            </th>

                            <th>
                                Reference
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $payments as $payment
                    ): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?= e(
                                        $payment['payment_no']
                                    ) ?>

                                </strong>

                            </td>


                            <td>

                                <?= e(
                                    $payment['payment_method']
                                ) ?>

                            </td>


                            <td>

                                ₹
                                <?= money(
                                    $payment['amount']
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    strtolower(
                                        (string)
                                        $payment['payment_method']
                                    ) === 'cash'
                                ): ?>

                                    —

                                <?php else: ?>

                                    <?= e(
                                        $payment[
                                            'transaction_reference'
                                        ] ?: '—'
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        payment-status
                                        <?= strtolower(
                                            str_replace(
                                                ' ',
                                                '-',
                                                $payment[
                                                    'payment_status'
                                                ]
                                            )
                                        )
                                    ?>"
                                >

                                    <?= e(
                                        $payment[
                                            'payment_status'
                                        ]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $payment['paid_at']
                                        ?: $payment['created_at']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>

            <div class="no-payment">

                No payment has been recorded for this bill.

            </div>

        <?php endif; ?>


        <!-- FOOTER -->

        <div class="invoice-footer">

            <div>

                <strong>

                    Thank you for choosing
                    <?= e($hospital_name) ?>.

                </strong>

                <p>

                    This is a computer-generated invoice.
                    No signature is required.

                </p>

            </div>


            <div class="generated">

                Generated:
                <?= date('d M Y h:i A') ?>

            </div>

        </div>


    </div>

</div>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        document.title =
            "Invoice - <?= e($bill['bill_no']) ?>";

    }
);

</script>


</body>

</html>