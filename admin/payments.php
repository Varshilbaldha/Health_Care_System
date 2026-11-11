<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';
require 'csrf_helper.php';


// =========================================================
// SESSION
// =========================================================

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found.");
}


// =========================================================
// DATABASE CONNECTION
// =========================================================

// IMPORTANT:
// Yaha apne existing payments.php wale SAME database
// username/password rakho.

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

$stmt = mysqli_prepare(
    $central,

    "SELECT
        hospital_id,
        hospital_name,
        database_name
     FROM hospital_registration
     WHERE application_no = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $application_no
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$hospital =
    mysqli_fetch_assoc($result);

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
        ''
    );
}
function redirect_bill_print($bill_id)
{
    header(
        "Location: bill_print.php?bill_id=" .
        (int)$bill_id
    );

    exit;
}


function redirect_payments($query = '')
{
    $url = "payments.php";

    if ($query !== '') {
        $url .= "?" . $query;
    }

    header(
        "Location: " . $url
    );

    exit;
}


// =========================================================
// MESSAGE
// =========================================================

$message = '';
$message_type = '';


// =========================================================
// CSRF
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    verify_csrf();
}


// =========================================================
// ADD PAYMENT
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_payment'])
) {

    $bill_id =
        (int)($_POST['bill_id'] ?? 0);

    $amount =
        (float)($_POST['amount'] ?? 0);

    $payment_method =
        trim(
            $_POST['payment_method'] ?? ''
        );

    $transaction_reference =
        trim(
            $_POST['transaction_reference'] ?? ''
        );

    $remarks =
        trim(
            $_POST['remarks'] ?? ''
        );


    // =====================================================
    // BASIC VALIDATION
    // =====================================================

    if ($bill_id <= 0) {

        $message =
            "Please select a valid bill.";

        $message_type =
            "error";

    } elseif ($amount <= 0) {

        $message =
            "Payment amount must be greater than ₹0.";

        $message_type =
            "error";

    } elseif (
        !in_array(
            $payment_method,
            ['Cash', 'UPI'],
            true
        )
    ) {

        $message =
            "Only Cash and UPI payments are allowed.";

        $message_type =
            "error";

    }


    // =====================================================
    // UPI TRANSACTION ID VALIDATION
    // =====================================================

    elseif (
        $payment_method === 'UPI' &&
        (
            $transaction_reference === '' ||
            !preg_match(
                '/^[A-Za-z0-9][A-Za-z0-9._\/-]{7,49}$/',
                $transaction_reference
            )
        )
    ) {

        $message =
            "Valid Transaction ID / UTR is compulsory for UPI.";

        $message_type =
            "error";

    }


    // =====================================================
    // CASH MUST NOT HAVE TRANSACTION ID
    // =====================================================

    elseif (
        $payment_method === 'Cash' &&
        $transaction_reference !== ''
    ) {

        $message =
            "Transaction ID is not required for Cash.";

        $message_type =
            "error";

    }


    else {

        mysqli_begin_transaction($conn);

        try {

            // =================================================
            // LOCK BILL
            // =================================================

            $stmt = mysqli_prepare(
                $conn,

                "SELECT

                    b.bill_id,
                    b.payable_amount,
                    b.bill_status,

                    COALESCE(
                        (
                            SELECT SUM(p.amount)
                            FROM payments p
                            WHERE p.bill_id = b.bill_id
                            AND p.payment_status = 'Success'
                        ),
                        0
                    ) AS paid

                 FROM billing b

                 WHERE b.bill_id = ?

                 LIMIT 1

                 FOR UPDATE"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $bill_id
            );


            mysqli_stmt_execute($stmt);


            $bill =
                mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmt)
                );


            mysqli_stmt_close($stmt);


            if (!$bill) {

                throw new Exception(
                    "Bill not found."
                );

            }


            // =================================================
            // CANCELLED BILL
            // =================================================

            if (
                $bill['bill_status'] ===
                'Cancelled'
            ) {

                throw new Exception(
                    "Cancelled bill cannot receive payment."
                );

            }


            // =================================================
            // OUTSTANDING
            // =================================================

            $outstanding =
                max(
                    0,
                    (float)$bill['payable_amount'] -
                    (float)$bill['paid']
                );


            if ($outstanding <= 0) {

                throw new Exception(
                    "This bill has no outstanding amount."
                );

            }


            if (
                $amount >
                ($outstanding + 0.0001)
            ) {

                throw new Exception(
                    "Payment amount cannot exceed outstanding amount of ₹" .
                    money($outstanding)
                );

            }


            // =================================================
            // DUPLICATE UPI TRANSACTION ID
            // =================================================

            if (
                $payment_method === 'UPI'
            ) {

                $stmt = mysqli_prepare(
                    $conn,

                    "SELECT payment_id

                     FROM payments

                     WHERE transaction_reference = ?

                     LIMIT 1"
                );


                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $transaction_reference
                );


                mysqli_stmt_execute($stmt);


                $duplicate =
                    mysqli_fetch_assoc(
                        mysqli_stmt_get_result($stmt)
                    );


                mysqli_stmt_close($stmt);


                if ($duplicate) {

                    throw new Exception(
                        "This Transaction ID / UTR has already been recorded."
                    );

                }

            }


            // =================================================
            // PAYMENT NUMBER
            // =================================================

            $payment_no =
                'PAY-' .
                date('YmdHis') .
                '-' .
                strtoupper(
                    bin2hex(
                        random_bytes(2)
                    )
                );


            // =================================================
            // BOTH CASH AND UPI ARE SUCCESS FOR NOW
            //
            // FUTURE:
            // UPI MERCHANT AUTOMATIC VERIFICATION WILL BE
            // ADDED HERE.
            // =================================================

            $payment_status =
                'Success';


            $paid_at =
                date('Y-m-d H:i:s');


            // Cash should never store a transaction ID.

            if (
                $payment_method === 'Cash'
            ) {
                $transaction_reference = '';
            }


            // =================================================
            // INSERT PAYMENT
            // =================================================

            $stmt = mysqli_prepare(
                $conn,

                "INSERT INTO payments
                (
                    bill_id,
                    payment_no,
                    payment_method,
                    amount,
                    transaction_reference,
                    payment_status,
                    remarks,
                    paid_at
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "issdssss",

                $bill_id,
                $payment_no,
                $payment_method,
                $amount,
                $transaction_reference,
                $payment_status,
                $remarks,
                $paid_at
            );


            if (
                !mysqli_stmt_execute($stmt)
            ) {

                throw new Exception(
                    mysqli_stmt_error($stmt)
                );

            }


            mysqli_stmt_close($stmt);


            // =================================================
            // UPDATE BILL STATUS
            // =================================================

            $new_paid =
                (float)$bill['paid'] +
                $amount;


            if (
                $new_paid + 0.0001 >=
                (float)$bill['payable_amount']
            ) {

                $new_status =
                    'Paid';

            } elseif (
                $new_paid > 0
            ) {

                $new_status =
                    'Partially Paid';

            } else {

                $new_status =
                    'Pending';

            }


            $stmt = mysqli_prepare(
                $conn,

                "UPDATE billing

                 SET bill_status = ?

                 WHERE bill_id = ?"
            );


            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $new_status,
                $bill_id
            );


            if (
                !mysqli_stmt_execute($stmt)
            ) {

                throw new Exception(
                    mysqli_stmt_error($stmt)
                );

            }


            mysqli_stmt_close($stmt);


            mysqli_commit($conn);


            redirect_bill_print($bill_id);


        } catch (Throwable $error) {

            mysqli_rollback($conn);

            $message =
                $error->getMessage();

            $message_type =
                'error';
        }
    }
}


// =========================================================
// REFUND PAYMENT
// =========================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['refund_payment'])
) {

    $payment_id =
        (int)($_POST['payment_id'] ?? 0);


    mysqli_begin_transaction($conn);

    try {

        // =================================================
        // LOCK PAYMENT
        // =================================================

        $stmt = mysqli_prepare(
            $conn,

            "SELECT

                payment_id,
                bill_id,
                amount,
                payment_status

             FROM payments

             WHERE payment_id = ?

             LIMIT 1

             FOR UPDATE"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $payment_id
        );


        mysqli_stmt_execute($stmt);


        $payment =
            mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );


        mysqli_stmt_close($stmt);


        if (!$payment) {

            throw new Exception(
                "Payment not found."
            );

        }


        // =================================================
        // ONLY SUCCESSFUL PAYMENT
        // =================================================

        if (
            $payment['payment_status'] !==
            'Success'
        ) {

            throw new Exception(
                "Only successful payments can be refunded."
            );

        }


        // =================================================
        // REFUND
        // =================================================

        $stmt = mysqli_prepare(
            $conn,

            "UPDATE payments

             SET payment_status = 'Refunded'

             WHERE payment_id = ?

             LIMIT 1"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $payment_id
        );


        if (
            !mysqli_stmt_execute($stmt)
        ) {

            throw new Exception(
                mysqli_stmt_error($stmt)
            );

        }


        mysqli_stmt_close($stmt);


        // =================================================
        // RECALCULATE BILL
        // =================================================

        $stmt = mysqli_prepare(
            $conn,

            "SELECT

                payable_amount,

                COALESCE(
                    (
                        SELECT SUM(amount)

                        FROM payments

                        WHERE bill_id = ?

                        AND payment_status = 'Success'
                    ),
                    0
                ) AS paid

             FROM billing

             WHERE bill_id = ?

             LIMIT 1"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $payment['bill_id'],
            $payment['bill_id']
        );


        mysqli_stmt_execute($stmt);


        $bill =
            mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt)
            );


        mysqli_stmt_close($stmt);


        $paid =
            (float)$bill['paid'];


        if (
            $paid + 0.0001 >=
            (float)$bill['payable_amount']
        ) {

            $bill_status =
                'Paid';

        } elseif (
            $paid > 0
        ) {

            $bill_status =
                'Partially Paid';

        } else {

            $bill_status =
                'Pending';

        }


        // =================================================
        // UPDATE BILL
        // =================================================

        $stmt = mysqli_prepare(
            $conn,

            "UPDATE billing

             SET bill_status = ?

             WHERE bill_id = ?"
        );


        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $bill_status,
            $payment['bill_id']
        );


        if (
            !mysqli_stmt_execute($stmt)
        ) {

            throw new Exception(
                mysqli_stmt_error($stmt)
            );

        }


        mysqli_stmt_close($stmt);


        mysqli_commit($conn);


        redirect_payments(
            'refunded=1'
        );


    } catch (Throwable $error) {

        mysqli_rollback($conn);

        $message =
            $error->getMessage();

        $message_type =
            'error';
    }
}


// =========================================================
// SUCCESS MESSAGE
// =========================================================

if (
    isset($_GET['payment_added'])
) {

    $message =
        "Payment recorded successfully.";

    $message_type =
        'success';
}


if (
    isset($_GET['refunded'])
) {

    $message =
        "Payment refunded successfully.";

    $message_type =
        'success';
}


// =========================================================
// BILL OPTIONS
// =========================================================

$bill_options = [];


$sql = "

    SELECT

        b.bill_id,
        b.bill_no,
        b.payable_amount,
        b.bill_status,

        a.appointment_no,

        CONCAT(
            COALESCE(pa.first_name, ''),
            ' ',
            COALESCE(pa.last_name, '')
        ) AS patient_name,

        COALESCE(
            (
                SELECT SUM(p.amount)

                FROM payments p

                WHERE p.bill_id = b.bill_id

                AND p.payment_status = 'Success'
            ),
            0
        ) AS paid

    FROM billing b

    INNER JOIN appointments a
        ON a.appointment_id = b.appointment_id

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON phm.mapping_id = a.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON pa.account_id = phm.account_id

    WHERE b.bill_status <> 'Cancelled'

    ORDER BY b.created_at DESC
";


$result =
    mysqli_query(
        $conn,
        $sql
    );


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc($result)
    ) {

        $row['outstanding'] =
            max(
                0,
                (float)$row['payable_amount'] -
                (float)$row['paid']
            );


        if (
            $row['outstanding'] > 0
        ) {

            $bill_options[] =
                $row;

        }

    }
}


// =========================================================
// PAYMENT LIST
// =========================================================

$selected_bill_id = (int)($_GET['bill_id'] ?? 0);

$selected_bill = null;
if ($selected_bill_id > 0) {
    $bill_options = array_values(array_filter(
        $bill_options,
        static fn($bill) => (int)$bill['bill_id'] === $selected_bill_id
    ));

    $stmt = mysqli_prepare($conn, "SELECT b.bill_id,b.bill_no,b.payable_amount,b.bill_status,a.appointment_no,CONCAT(COALESCE(pa.first_name,''),' ',COALESCE(pa.last_name,'')) patient_name FROM billing b INNER JOIN appointments a ON a.appointment_id=b.appointment_id INNER JOIN hospital_management.patient_hospital_mapping phm ON phm.mapping_id=a.mapping_id INNER JOIN hospital_management.patient_accounts pa ON pa.account_id=phm.account_id WHERE b.bill_id=? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $selected_bill_id);
        mysqli_stmt_execute($stmt);
        $selected_bill = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
    if (!$selected_bill) {
        $selected_bill_id = 0;
    }
}

$payments = [];


$sql = "

    SELECT

        p.payment_id,
        p.bill_id,
        p.payment_no,
        p.payment_method,
        p.amount,
        p.transaction_reference,
        p.payment_status,
        p.remarks,
        p.paid_at,
        p.created_at,

        b.bill_no,
        b.payable_amount,

        a.appointment_no,

        CONCAT(
            COALESCE(pa.first_name, ''),
            ' ',
            COALESCE(pa.last_name, '')
        ) AS patient_name

    FROM payments p

    INNER JOIN billing b
        ON b.bill_id = p.bill_id

    INNER JOIN appointments a
        ON a.appointment_id = b.appointment_id

    INNER JOIN hospital_management.patient_hospital_mapping phm
        ON phm.mapping_id = a.mapping_id

    INNER JOIN hospital_management.patient_accounts pa
        ON pa.account_id = phm.account_id

    WHERE (? = 0 OR p.bill_id = ?)

    ORDER BY p.created_at DESC
";


$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Payment query preparation failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ii",
    $selected_bill_id,
    $selected_bill_id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
}

mysqli_stmt_close($stmt);


// =========================================================
// STATISTICS
// =========================================================

$stats = [
    'total' => 0,
    'success' => 0,
    'pending' => 0,
    'refunded' => 0,
    'failed' => 0
];


$stats_sql = "SELECT
        COUNT(*) AS total,
        SUM(payment_status = 'Success') AS success,
        SUM(payment_status = 'Pending') AS pending,
        SUM(payment_status = 'Refunded') AS refunded,
        SUM(payment_status = 'Failed') AS failed
     FROM payments";
if ($selected_bill_id > 0) {
    $stats_sql = "SELECT
        COUNT(*) AS total,
        SUM(payment_status = 'Success') AS success,
        SUM(payment_status = 'Pending') AS pending,
        SUM(payment_status = 'Refunded') AS refunded,
        SUM(payment_status = 'Failed') AS failed
     FROM payments WHERE bill_id=" . (int)$selected_bill_id;
}
$result = mysqli_query($conn, $stats_sql);


if ($result) {

    $data =
        mysqli_fetch_assoc($result);

    $stats =
        array_merge(
            $stats,
            $data
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
        Payments |
        <?= e($hospital_name) ?>
    </title>


    <link
        rel="stylesheet"
        href="css/sidebar.css"
    >

    <link
        rel="stylesheet"
        href="css/payments.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>


<body>


<?php include 'sidebar.php'; ?>


<main class="main-content">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Payments
            </h1>

            <p>
                Record and track patient bill payments.
            </p>

        </div>


        <button
            type="button"
            class="add-btn"
            id="addPaymentBtn"
        >

            <i class="fa-solid fa-plus"></i>

            Record Payment

        </button>

    </div>


    <!-- =====================================================
         MESSAGE
    ====================================================== -->

    <?php if ($message !== ''): ?>

        <div
            class="<?= $message_type === 'success'
                ? 'success-message'
                : 'error-message' ?>"
        >

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <?php if ($selected_bill_id > 0 && $selected_bill): ?>
        <div class="selected-bill-bar">
            <div>
                <strong>Payments for <?= e(trim($selected_bill['patient_name'])) ?></strong>
                <span>Bill <?= e($selected_bill['bill_no']) ?> · Appointment <?= e($selected_bill['appointment_no']) ?></span>
            </div>
            <div class="selected-bill-actions">
                <a class="action-btn" href="bill_print.php?bill_id=<?= (int)$selected_bill_id ?>" title="View / Download Bill"><i class="fa-solid fa-file-invoice"></i></a>
                <a class="reset-btn" href="payments.php">All Payments</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- =====================================================
         STATS
    ====================================================== -->

    <div class="stats-grid">

        <div class="stat-card">

            <span>
                Total Payments
            </span>

            <strong>
                <?= (int)$stats['total'] ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Successful
            </span>

            <strong>
                <?= (int)$stats['success'] ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Pending
            </span>

            <strong>
                <?= (int)$stats['pending'] ?>
            </strong>

        </div>


        <div class="stat-card">

            <span>
                Refunded
            </span>

            <strong>
                <?= (int)$stats['refunded'] ?>
            </strong>

        </div>

    </div>


    <!-- =====================================================
         TABLE
    ====================================================== -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Payment
                    </th>

                    <th>
                        Patient
                    </th>

                    <th>
                        Bill
                    </th>

                    <th>
                        Method
                    </th>

                    <th>
                        Amount
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Reference
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (empty($payments)): ?>

                <tr>

                    <td
                        colspan="9"
                        class="empty"
                    >

                        No payments found.

                    </td>

                </tr>

            <?php endif; ?>


            <?php foreach ($payments as $payment): ?>

                <tr>


                    <!-- PAYMENT -->

                    <td>

                        <strong>

                            <?= e(
                                $payment['payment_no']
                            ) ?>

                        </strong>

                    </td>


                    <!-- PATIENT -->

                    <td>

                        <strong>

                            <?= e(
                                trim(
                                    $payment['patient_name']
                                )
                            ) ?>

                        </strong>

                        <small>

                            <?= e(
                                $payment['appointment_no']
                            ) ?>

                        </small>

                    </td>


                    <!-- BILL -->

                    <td>

                        <?= e(
                            $payment['bill_no']
                        ) ?>

                    </td>


                    <!-- METHOD -->

                    <td>

                        <?= e(
                            $payment['payment_method']
                        ) ?>

                    </td>


                    <!-- AMOUNT -->

                    <td>

                        ₹ <?= money(
                            $payment['amount']
                        ) ?>

                    </td>


                    <!-- STATUS -->

                    <td>

                        <span
                            class="status <?= strtolower(
                                $payment['payment_status']
                            ) ?>"
                        >

                            <?= e(
                                $payment['payment_status']
                            ) ?>

                        </span>

                    </td>


                    <!-- REFERENCE -->

                    <td>

                        <?php if (
                            $payment['payment_method']
                            === 'Cash'
                        ): ?>

                            —

                        <?php else: ?>

                            <?= e(
                                $payment['transaction_reference']
                                    ?: '—'
                            ) ?>

                        <?php endif; ?>

                    </td>


                    <!-- DATE -->

                    <td>

                        <?= e(
                            $payment['paid_at']
                                ?: $payment['created_at']
                        ) ?>

                    </td>


                    <!-- ACTION -->

                    <td class="payment-actions">

                        <a
                            class="action-btn bill-action"
                            href="bill_print.php?bill_id=<?= (int)$payment['bill_id'] ?>"
                            title="View / Download Bill"
                        >
                            <i class="fa-solid fa-file-invoice"></i>
                        </a>

                        <?php if (
                            $payment['payment_status']
                            === 'Success'
                        ): ?>

                            <form
                                method="post"
                                class="inline-form"
                            >

                                <?= csrf_field() ?>


                                <input
                                    type="hidden"
                                    name="refund_payment"
                                    value="1"
                                >


                                <input
                                    type="hidden"
                                    name="payment_id"
                                    value="<?= (int)$payment['payment_id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="action-btn refund"
                                    title="Refund"
                                >

                                    <i
                                        class="fa-solid fa-rotate-left"
                                    ></i>

                                </button>

                            </form>


                        <?php else: ?>

                            <span
                                style="color:#94a3b8;"
                            >
                                —
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>


            </tbody>

        </table>

    </div>


</main>


<!-- =========================================================
     ADD PAYMENT MODAL
========================================================= -->

<div
    class="modal"
    id="paymentModal"
>

    <div class="modal-content">


        <div class="modal-header">

            <h2>
                Record Payment
            </h2>


            <button
                type="button"
                class="close-btn"
            >

                &times;

            </button>

        </div>


        <form
            method="post"
            id="paymentForm"
        >

            <?= csrf_field() ?>


            <input
                type="hidden"
                name="add_payment"
                value="1"
            >


            <div class="form-grid">


                <!-- BILL -->

                <div
                    class="form-group full-width"
                >

                    <label>
                        Bill
                    </label>


                    <select
                        name="bill_id"
                        id="billSelect"
                        required
                    >

                        <option value="">
                            Select Bill
                        </option>


                        <?php foreach (
                            $bill_options
                            as $bill
                        ): ?>

                            <option
                                value="<?= (int)$bill['bill_id'] ?>"
                            >

                                <?= e(
                                    $bill['bill_no']
                                ) ?>

                                —

                                <?= e(
                                    trim(
                                        $bill['patient_name']
                                    )
                                ) ?>

                                —

                                Outstanding ₹<?= money(
                                    $bill['outstanding']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- OUTSTANDING -->

                <div class="form-group">

                    <label>
                        Outstanding
                    </label>

                    <input
                        type="text"
                        id="outstanding"
                        value="₹ 0.00"
                        readonly
                    >

                </div>


                <!-- AMOUNT -->

                <div class="form-group">

                    <label>
                        Amount
                    </label>

                    <input
                        type="number"
                        name="amount"
                        id="amount"
                        min="0.01"
                        step="0.01"
                        required
                    >

                </div>


                <!-- PAYMENT METHOD -->

                <div class="form-group">

                    <label>
                        Payment Method
                    </label>


                    <select
                        name="payment_method"
                        id="paymentMethod"
                        required
                    >

                        <option value="Cash">
                            Cash
                        </option>

                        <option value="UPI">
                            UPI
                        </option>

                    </select>

                </div>


                <!-- TRANSACTION ID -->

                <div
                    class="form-group full-width"
                    id="transactionGroup"
                    style="display:none;"
                >

                    <label>

                        Transaction ID / UTR

                        <span
                            style="color:#dc2626;"
                        >
                            *
                        </span>

                    </label>


                    <input
                        type="text"
                        name="transaction_reference"
                        id="transactionReference"
                        minlength="8"
                        maxlength="50"
                        autocomplete="off"
                        placeholder="Enter Transaction ID / UTR"
                    >


                    <small>

                        Required only for UPI payments.

                    </small>

                </div>


                <!-- REMARKS -->

                <div
                    class="form-group full-width"
                >

                    <label>
                        Remarks
                    </label>


                    <textarea
                        name="remarks"
                        rows="3"
                    ></textarea>

                </div>


            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                >

                    Cancel

                </button>


                <button
                    type="submit"
                    class="save-btn"
                >

                    Save Payment

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     BILL DATA FOR JAVASCRIPT
========================================================= -->

<script>

window.paymentBills =
<?= json_encode(
    array_column(
        $bill_options,
        null,
        'bill_id'
    ),
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;

</script>


<script
    src="js/payments.js"
></script>


</body>

</html>