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
// HELPERS
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
// BILL DETAILS
// =========================================================

$sql = "

    SELECT

        b.bill_id,
        b.bill_no,
        b.appointment_id,

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

        s.service_name,
        s.service_code

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
// PATIENT DETAILS
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

$data =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if ($data) {

    $patient = array_merge(
        $patient,
        $data
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
        remarks

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
// SUCCESSFUL PAYMENT TOTAL
// =========================================================

$paid_amount = 0;


$sql = "

    SELECT

        COALESCE(
            SUM(amount),
            0
        ) AS paid_amount

    FROM payments

    WHERE bill_id = ?

    AND payment_status = 'Success'
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

$payment_total =
    mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if ($payment_total) {

    $paid_amount =
        (float)$payment_total['paid_amount'];

}


$payable_amount =
    (float)$bill['payable_amount'];


$outstanding =
    max(
        0,
        $payable_amount - $paid_amount
    );


// =========================================================
// DISPLAY STATUS
// =========================================================

$display_status = $bill['bill_status'];
if ($bill['bill_status'] !== 'Cancelled') {
    if ($payable_amount > 0 && $outstanding <= 0) {
        $display_status = 'Paid';
    } elseif ($paid_amount > 0 && $outstanding > 0) {
        $display_status = 'Partially Paid';
    } else {
        $display_status = 'Pending';
    }
}


// =========================================================
// PAYMENT HISTORY
// =========================================================

$payments = [];


$sql = "

    SELECT

        payment_no,
        payment_method,
        amount,
        transaction_reference,
        payment_status,
        paid_at

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
// DOCUMENT TITLE
// =========================================================

if ($bill['bill_status'] === 'Paid') {

    $document_title = 'FINAL BILL';

} elseif ($paid_amount > 0) {

    $document_title = 'BILL / PAYMENT RECEIPT';

} else {

    $document_title = 'BILL';

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

        <?= e($bill['bill_no']) ?>
        |
        <?= e($hospital_name) ?>

    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            padding: 30px;

            background: #eef2f7;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #1f2937;

        }


        .toolbar {

            max-width: 950px;

            margin: 0 auto 20px;

            display: flex;

            justify-content:
                space-between;

            gap: 10px;

        }


        .toolbar button,
        .toolbar a {

            border: 0;

            padding: 11px 18px;

            border-radius: 9px;

            cursor: pointer;

            text-decoration: none;

            font-weight: 600;

            font-size: 14px;

        }


        .print-btn {

            background: #6c63ff;

            color: white;

        }


        .back-btn {

            background: #e5e7eb;

            color: #334155;

        }


        .invoice {

            max-width: 950px;

            margin: auto;

            background: white;

            padding: 40px;

            box-shadow:
                0 10px 35px #00000018;

        }


        .hospital-header {

            display: flex;

            justify-content:
                space-between;

            gap: 30px;

            border-bottom:
                2px solid #e5e7eb;

            padding-bottom: 22px;

        }


        .hospital-name {

            font-size: 28px;

            font-weight: 700;

            color: #1f2937;

            margin-bottom: 8px;

        }


        .hospital-subtitle {

            color: #64748b;

            font-size: 13px;

        }


        .document-title {

            text-align: right;

        }


        .document-title h1 {

            margin: 0;

            font-size: 25px;

            color: #6c63ff;

        }


        .document-title p {

            margin: 7px 0 0;

            color: #64748b;

            font-size: 13px;

        }


        .info-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin: 25px 0;

        }


        .info-box {

            border:
                1px solid #e5e7eb;

            border-radius: 10px;

            padding: 16px;

        }


        .info-box h3 {

            margin:
                0 0 12px;

            font-size: 14px;

            color: #6c63ff;

        }


        .info-row {

            display: flex;

            justify-content:
                space-between;

            gap: 15px;

            padding: 5px 0;

            font-size: 13px;

        }


        .info-row span:first-child {

            color: #64748b;

        }


        .info-row strong {

            text-align: right;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            margin-top: 20px;

        }


        th,
        td {

            padding:
                11px 10px;

            border-bottom:
                1px solid #e5e7eb;

            font-size: 12px;

            text-align: left;

        }


        th {

            background: #f8fafc;

            color: #475569;

        }


        td.amount,
        th.amount {

            text-align: right;

        }


        .summary {

            width: 360px;

            max-width: 100%;

            margin:
                25px 0 0 auto;

        }


        .summary-row {

            display: flex;

            justify-content:
                space-between;

            padding: 8px 0;

            font-size: 14px;

        }


        .summary-row.total {

            border-top:
                2px solid #1f2937;

            margin-top: 5px;

            padding-top: 12px;

            font-size: 17px;

            font-weight: 700;

        }


        .summary-row.paid {

            color: #15803d;

        }


        .summary-row.outstanding {

            color: #b45309;

            font-weight: 700;

        }


        .status {

            display: inline-block;

            padding:
                6px 12px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;

        }


        .status.paid {

            background: #dcfce7;

            color: #15803d;

        }


        .status.partial {

            background: #fef3c7;

            color: #b45309;

        }


        .status.pending {

            background: #fef3c7;

            color: #b45309;

        }


        .status.cancelled {

            background: #fee2e2;

            color: #dc2626;

        }


        .payment-history {

            margin-top: 35px;

        }


        .payment-history h3 {

            font-size: 16px;

            margin-bottom: 10px;

        }


        .remarks {

            margin-top: 25px;

            padding: 14px;

            background: #f8fafc;

            border-radius: 8px;

            font-size: 12px;

            color: #475569;

        }


        .footer {

            margin-top: 35px;

            padding-top: 18px;

            border-top:
                1px solid #e5e7eb;

            text-align: center;

            color: #64748b;

            font-size: 11px;

        }


        @media (max-width: 650px) {

            body {
                padding: 10px;
            }

            .invoice {
                padding: 20px;
            }

            .hospital-header {
                flex-direction: column;
            }

            .document-title {
                text-align: left;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .summary {
                width: 100%;
            }

            table {
                min-width: 650px;
            }

            .table-wrap {
                overflow-x: auto;
            }

        }


        @media print {

            @page {

                size: A4;

                margin: 12mm;

            }


            body {

                background: white;

                padding: 0;

            }


            .toolbar {

                display: none;

            }


            .invoice {

                max-width: none;

                box-shadow: none;

                padding: 0;

            }


            .no-print {

                display: none !important;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     TOOLBAR
====================================================== -->

<div class="toolbar">


    <a
        href="billing.php"
        class="back-btn"
    >

        ← Back to Billing

    </a>


    <button
        type="button"
        class="print-btn"
        onclick="window.print()"
    >

        🖨 Print / Save PDF

    </button>


</div>


<!-- =====================================================
     BILL
====================================================== -->

<div class="invoice">


    <!-- HEADER -->

    <div class="hospital-header">


        <div>

            <div class="hospital-name">

                <?= e($hospital_name) ?>

            </div>


            <div class="hospital-subtitle">

                Hospital Management System

            </div>

        </div>


        <div class="document-title">

            <h1>

                <?= e(
                    $document_title
                ) ?>

            </h1>


            <p>

                Bill No:

                <strong>

                    <?= e(
                        $bill['bill_no']
                    ) ?>

                </strong>

            </p>


            <p>

                Date:

                <strong>

                    <?= e(
                        date(
                            'd M Y',
                            strtotime(
                                $bill['created_at']
                            )
                        )
                    ) ?>

                </strong>

            </p>

        </div>

    </div>


    <!-- PATIENT / APPOINTMENT -->

    <div class="info-grid">


        <div class="info-box">

            <h3>
                Patient Information
            </h3>


            <div class="info-row">

                <span>
                    Patient Name
                </span>

                <strong>

                    <?= e(
                        trim(
                            $patient['patient_name']
                        )
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Patient Code
                </span>

                <strong>

                    <?= e(
                        $patient[
                            'hospital_patient_code'
                        ]
                    ) ?>

                </strong>

            </div>


            <?php if (
                $patient['phone'] !== ''
            ): ?>

                <div class="info-row">

                    <span>
                        Phone
                    </span>

                    <strong>

                        <?= e(
                            $patient['phone']
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>


            <?php if (
                $patient['email'] !== ''
            ): ?>

                <div class="info-row">

                    <span>
                        Email
                    </span>

                    <strong>

                        <?= e(
                            $patient['email']
                        ) ?>

                    </strong>

                </div>

            <?php endif; ?>

        </div>


        <div class="info-box">

            <h3>
                Appointment Information
            </h3>


            <div class="info-row">

                <span>
                    Appointment
                </span>

                <strong>

                    <?= e(
                        $bill['appointment_no']
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Doctor
                </span>

                <strong>

                    Dr.
                    <?= e(
                        $bill['doctor_name'] ?? ''
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Specialization
                </span>

                <strong>

                    <?= e(
                        $bill['specialization'] ?? ''
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Service
                </span>

                <strong>

                    <?= e(
                        $bill['service_name'] ?? ''
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Appointment Date
                </span>

                <strong>

                    <?= e(
                        $bill['appointment_date']
                    ) ?>

                </strong>

            </div>


            <div class="info-row">

                <span>
                    Appointment Time
                </span>

                <strong>

                    <?= e(
                        $bill['appointment_time']
                    ) ?>

                </strong>

            </div>

        </div>

    </div>


    <!-- STATUS -->

    <div>


        <strong>
            Bill Status:
        </strong>


        <?php

        $status_class = 'pending';

        if (
            $display_status === 'Paid'
        ) {

            $status_class = 'paid';

        } elseif (
            $display_status === 'Cancelled'
        ) {

            $status_class = 'cancelled';

        } elseif (
            $display_status === 'Partially Paid'
        ) {

            $status_class = 'partial';

        }

        ?>


        <span
            class="
                status
                <?= e($status_class) ?>
            "
        >

            <?= e(
                $display_status
            ) ?>

        </span>

    </div>


    <!-- BILL ITEMS -->

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Type</th>

                    <th>Item / Service</th>

                    <th class="amount">
                        Qty
                    </th>

                    <th class="amount">
                        Unit Price
                    </th>

                    <th class="amount">
                        Discount
                    </th>

                    <th class="amount">
                        Tax
                    </th>

                    <th class="amount">
                        Total
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($items)): ?>

                <tr>

                    <td
                        colspan="8"
                        style="text-align:center;"
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

                            <?= e(
                                $item['item_type']
                            ) ?>

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

                                <br>

                                <small>

                                    <?= e(
                                        $item['remarks']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </td>


                        <td class="amount">

                            <?= money(
                                $item['quantity']
                            ) ?>

                        </td>


                        <td class="amount">

                            ₹
                            <?= money(
                                $item['unit_price']
                            ) ?>

                        </td>


                        <td class="amount">

                            ₹
                            <?= money(
                                $item['discount_amount']
                            ) ?>

                        </td>


                        <td class="amount">

                            ₹
                            <?= money(
                                $item['tax_amount']
                            ) ?>

                        </td>


                        <td class="amount">

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


    <!-- SUMMARY -->

    <div class="summary">


        <div class="summary-row">

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


        <div class="summary-row">

            <span>
                Discount
            </span>

            <strong>

                ₹
                <?= money(
                    $bill['discount_amount']
                ) ?>

            </strong>

        </div>


        <div class="summary-row">

            <span>
                Tax
            </span>

            <strong>

                ₹
                <?= money(
                    $bill['tax_amount']
                ) ?>

            </strong>

        </div>


        <div class="summary-row total">

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


        <div class="summary-row paid">

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


        <div class="summary-row outstanding">

            <span>
                Outstanding
            </span>

            <strong>

                ₹
                <?= money(
                    $outstanding
                ) ?>

            </strong>

        </div>

    </div>


    <!-- PAYMENT HISTORY -->

    <?php if (!empty($payments)): ?>


        <div class="payment-history">

            <h3>
                Payment History
            </h3>


            <div class="table-wrap">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Payment No
                            </th>

                            <th>
                                Method
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

                            <th class="amount">
                                Amount
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $payments as $payment
                    ): ?>

                        <tr>

                            <td>

                                <?= e(
                                    $payment['payment_no']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $payment['payment_method']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $payment[
                                        'transaction_reference'
                                    ] ?: '—'
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $payment['payment_status']
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $payment['paid_at']
                                        ?: '—'
                                ) ?>

                            </td>


                            <td class="amount">

                                ₹
                                <?= money(
                                    $payment['amount']
                                ) ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>

        </div>


    <?php endif; ?>


    <!-- REMARKS -->

    <?php if (
        !empty($bill['remarks'])
    ): ?>


        <div class="remarks">

            <strong>
                Remarks:
            </strong>

            <?= nl2br(
                e(
                    $bill['remarks']
                )
            ) ?>

        </div>


    <?php endif; ?>


    <!-- FOOTER -->

    <div class="footer">

        This is a computer-generated document.

        <br>

        Bill generated on

        <?= e(
            date(
                'd-m-Y H:i',
                strtotime(
                    $bill['created_at']
                )
            )
        ) ?>

    </div>


</div>


</body>

</html>