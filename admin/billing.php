<?php
require_once __DIR__ . '/../config/database.php';


require 'auth.php';
require 'csrf_helper.php';

$application_no = $_SESSION['application_no'] ?? '';

if ($application_no === '') {
    die("Hospital application number not found.");
}

/* =========================================================
   CENTRAL DATABASE
========================================================= */

$central_conn = db_connect();

if (!$central_conn) {
    die(
        "Central database connection failed: " .
        mysqli_connect_error()
    );
}

mysqli_set_charset($central_conn, 'utf8mb4');

/* =========================================================
   GET HOSPITAL
========================================================= */

$stmt = mysqli_prepare(
    $central_conn,
    "SELECT
        hospital_id,
        hospital_name,
        database_name
     FROM hospital_registration
     WHERE application_no = ?
     LIMIT 1"
);

if ($stmt === false) {
    die(
        "Hospital query prepare failed: " .
        mysqli_error($central_conn)
    );
}

mysqli_stmt_bind_param(
    $stmt,
    "s",
    $application_no
);

if (!mysqli_stmt_execute($stmt)) {
    die(
        "Hospital query execute failed: " .
        mysqli_stmt_error($stmt)
    );
}

$result = mysqli_stmt_get_result($stmt);

if ($result === false) {
    die(
        "Hospital query result failed: " .
        mysqli_stmt_error($stmt)
    );
}

$hospital = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$hospital) {
    die("Hospital record not found.");
}

$hospital_id   = (int)$hospital['hospital_id'];
$hospital_name = $hospital['hospital_name'];
$db            = $hospital['database_name'];

/* =========================================================
   HOSPITAL DATABASE
========================================================= */

$hospital_conn = db_connect($db);

if (!$hospital_conn) {
    die(
        "Hospital database connection failed: " .
        mysqli_connect_error()
    );
}

mysqli_set_charset($hospital_conn, 'utf8mb4');

/* =========================================================
   HELPERS
========================================================= */

function clean($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function money($value)
{
    return number_format(
        (float)$value,
        2,
        '.',
        ''
    );
}

/*
 * bill_items.item_type is an ENUM in the supplied schema:
 * Service, Lab Test, Medicine, Room Charge, Other.
 * The UI uses friendlier labels, so normalize them before INSERT/UPDATE.
 */
function db_bill_item_type($type)
{
    $type = strtolower(trim((string)$type));

    if (in_array($type, ['doctor fee', 'consultation', 'service', 'appointment fee'], true)) {
        return 'Service';
    }

    if ($type === 'lab test') {
        return 'Lab Test';
    }

    if ($type === 'medicine') {
        return 'Medicine';
    }

    if (in_array($type, ['room', 'bed', 'room charge'], true)) {
        return 'Room Charge';
    }

    return 'Other';
}

/**
 * Returns an authoritative database price for billable catalog items.
 * NULL means the supplied schema has no authoritative price for that item,
 * so the user-entered amount may remain editable.
 */
function billing_catalog_locked_price(mysqli $db, string $type, string $name): ?float
{
    $type = strtolower(trim($type));
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    if ($type === 'room') {
        if (preg_match('/^Room\s+(.+?)\s*\/\s*Bed\s+(.+)$/i', $name, $m)) {
            $roomNumber = trim($m[1]);
            $bedNumber = trim($m[2]);

            $q = mysqli_prepare(
                $db,
                "SELECT r.room_charge, b.bed_charge
                 FROM rooms r
                 INNER JOIN beds b ON b.room_id = r.room_id
                 WHERE r.room_number = ?
                   AND b.bed_number = ?
                 LIMIT 1"
            );

            if (!$q) {
                throw new Exception('Room/bed price lookup failed: ' . mysqli_error($db));
            }

            mysqli_stmt_bind_param($q, 'ss', $roomNumber, $bedNumber);
            if (!mysqli_stmt_execute($q)) {
                $error = mysqli_stmt_error($q);
                mysqli_stmt_close($q);
                throw new Exception('Room/bed price lookup failed: ' . $error);
            }

            $result = mysqli_stmt_get_result($q);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) mysqli_free_result($result);
            mysqli_stmt_close($q);

            if (!$row) {
                throw new Exception('Selected room and bed are not connected in the database.');
            }

            return (float)$row['room_charge'] + (float)$row['bed_charge'];
        }

        $roomNumber = preg_replace('/^Room\s+/i', '', $name);
        $q = mysqli_prepare($db, "SELECT room_charge FROM rooms WHERE room_number = ? LIMIT 1");
        if (!$q) throw new Exception('Room price lookup failed: ' . mysqli_error($db));
        mysqli_stmt_bind_param($q, 's', $roomNumber);
        if (!mysqli_stmt_execute($q)) {
            $error = mysqli_stmt_error($q);
            mysqli_stmt_close($q);
            throw new Exception('Room price lookup failed: ' . $error);
        }
        $result = mysqli_stmt_get_result($q);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($q);
        return $row ? (float)$row['room_charge'] : null;
    }

    if ($type === 'bed') {
        $bedNumber = preg_replace('/^Bed\s+/i', '', $name);
        $q = mysqli_prepare($db, "SELECT bed_charge FROM beds WHERE bed_number = ? LIMIT 1");
        if (!$q) throw new Exception('Bed price lookup failed: ' . mysqli_error($db));
        mysqli_stmt_bind_param($q, 's', $bedNumber);
        if (!mysqli_stmt_execute($q)) {
            $error = mysqli_stmt_error($q);
            mysqli_stmt_close($q);
            throw new Exception('Bed price lookup failed: ' . $error);
        }
        $result = mysqli_stmt_get_result($q);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($q);
        return $row ? (float)$row['bed_charge'] : null;
    }

    if ($type === 'lab test') {
        $q = mysqli_prepare(
            $db,
            "SELECT test_fee
             FROM lab_tests
             WHERE test_name = ?
             ORDER BY lab_test_id ASC
             LIMIT 1"
        );
        if (!$q) throw new Exception('Lab test price lookup failed: ' . mysqli_error($db));
        mysqli_stmt_bind_param($q, 's', $name);
        if (!mysqli_stmt_execute($q)) {
            $error = mysqli_stmt_error($q);
            mysqli_stmt_close($q);
            throw new Exception('Lab test price lookup failed: ' . $error);
        }
        $result = mysqli_stmt_get_result($q);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($q);
        return ($row && (float)$row['test_fee'] > 0) ? (float)$row['test_fee'] : null;
    }

    if ($type === 'other service') {
        $q = mysqli_prepare(
            $db,
            "SELECT service_fee
             FROM services
             WHERE service_name = ?
               AND status = 'Active'
             ORDER BY service_id ASC
             LIMIT 1"
        );
        if (!$q) throw new Exception('Service price lookup failed: ' . mysqli_error($db));
        mysqli_stmt_bind_param($q, 's', $name);
        if (!mysqli_stmt_execute($q)) {
            $error = mysqli_stmt_error($q);
            mysqli_stmt_close($q);
            throw new Exception('Service price lookup failed: ' . $error);
        }
        $result = mysqli_stmt_get_result($q);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        if ($result) mysqli_free_result($result);
        mysqli_stmt_close($q);
        return ($row && (float)$row['service_fee'] > 0) ? (float)$row['service_fee'] : null;
    }

    return null;
}

function ui_bill_item_type($type)
{
    $type = strtolower(trim((string)$type));

    if ($type === 'service' || $type === 'consultation') {
        return 'Doctor Fee';
    }

    if ($type === 'room charge') {
        return 'Room';
    }

    return $type === 'lab test'
        ? 'Lab Test'
        : ($type === 'medicine'
            ? 'Medicine'
            : 'Other Service');
}

/* =========================================================
   MESSAGES
========================================================= */

$message = '';
$message_type = '';

/* =========================================================
   POST / CSRF
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

/* =========================================================
   CREATE BILL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['add_bill'])
) {

    $appointment_id = (int)(
        $_POST['appointment_id'] ?? 0
    );

    $discount = (float)(
        $_POST['discount_amount'] ?? 0
    );

    $tax = (float)(
        $_POST['tax_amount'] ?? 0
    );

    $remarks = trim(
        $_POST['remarks'] ?? ''
    );

    $items = $_POST['items'] ?? [];

    if (
        $appointment_id <= 0 ||
        !is_array($items)
    ) {

        $message =
            "Select an appointment.";

        $message_type = 'error';

    } elseif (
        $discount < 0 ||
        $tax < 0
    ) {

        $message =
            "Discount and tax cannot be negative.";

        $message_type = 'error';

    } else {

        mysqli_begin_transaction($hospital_conn);

        try {

            /* =================================================
               GET APPOINTMENT + MANDATORY SERVICE FEE
            ================================================= */

            $q = mysqli_prepare(
                $hospital_conn,
                "SELECT
                    a.appointment_id,
                    a.appointment_no,
                    a.service_id,
                    a.doctor_id,

                    s.service_name,
                    s.service_fee,
                    s.service_type,

                    d.doctor_name,
                    d.consultation_fee AS doctor_consultation_fee

                 FROM appointments a

                 JOIN services s
                    ON s.service_id = a.service_id

                 JOIN doctors d
                    ON d.doctor_id = a.doctor_id

                 WHERE a.appointment_id = ?

                 LIMIT 1

                 FOR UPDATE"
            );

            if ($q === false) {
                throw new Exception(
                    "Appointment query prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $appointment_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            $appointment_result =
                mysqli_stmt_get_result($q);

            $appointment =
                mysqli_fetch_assoc(
                    $appointment_result
                );

            mysqli_stmt_close($q);

            if (!$appointment) {
                throw new Exception(
                    "Appointment not found."
                );
            }

            /* =================================================
               MANDATORY APPOINTMENT FEE
            ================================================= */

            $mandatory_service_name =
                trim(
                    $appointment['service_name']
                );

            /*
             * Mandatory amount = doctor's consultation fee.
             * Old appointments can fall back to their service fee.
             */
            $doctor_consultation_fee =
                (float)($appointment['doctor_consultation_fee'] ?? 0);

            $mandatory_service_fee =
                $doctor_consultation_fee > 0
                    ? $doctor_consultation_fee
                    : (float)$appointment['service_fee'];

            $mandatory_service_name =
                trim($appointment['service_name'] ?? '');

            if ($mandatory_service_name === '') {
                $mandatory_service_name = 'Doctor Consultation';
            }

            $mandatory_service_type = 'Doctor Fee';

            if ($mandatory_service_fee < 0) {
                throw new Exception(
                    "Invalid appointment service fee."
                );
            }

            /* =================================================
               CHECK EXISTING BILL
            ================================================= */

            $q = mysqli_prepare(
                $hospital_conn,
                "SELECT
                    bill_id
                 FROM billing
                 WHERE appointment_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            if ($q === false) {
                throw new Exception(
                    "Existing bill query prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $appointment_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            $existing_result =
                mysqli_stmt_get_result($q);

            $existing =
                mysqli_fetch_assoc(
                    $existing_result
                );

            mysqli_stmt_close($q);

            if ($existing) {
                throw new Exception(
                    "A bill already exists for this appointment."
                );
            }

            /* =================================================
               BILL ITEMS

               FIRST ITEM IS ALWAYS MANDATORY
               APPOINTMENT / CONSULTATION FEE
            ================================================= */

            $clean_items = [];

            $subtotal = 0;

            /*
             * Mandatory appointment fee
             */
            $clean_items[] = [
                'type'     => 'Service',
                'name'     => $mandatory_service_name,
                'qty'      => 1,
                'unit'     => $mandatory_service_fee,
                'discount' => 0,
                'tax'      => 0,
                'total'    => $mandatory_service_fee,
                'remarks'  =>
                    'Mandatory appointment consultation/service fee - Dr. ' .
                    $appointment['doctor_name']
            ];

            $subtotal +=
                $mandatory_service_fee;

            /* =================================================
               ADDITIONAL ITEMS
            ================================================= */

            foreach ($items as $item) {

                $type = trim(
                    $item['item_type'] ??
                    'Other Service'
                );

                $name = trim(
                    $item['item_name'] ?? ''
                );

                $qty = (float)(
                    $item['quantity'] ?? 1
                );

                $unit = (float)(
                    $item['unit_price'] ?? 0
                );

                $item_discount = (float)(
                    $item['discount_amount'] ?? 0
                );

                $item_tax = (float)(
                    $item['tax_amount'] ?? 0
                );

                $item_remarks = trim(
                    $item['remarks'] ?? ''
                );

                /*
                 * Empty additional rows are ignored.
                 */
                if ($name === '') {
                    continue;
                }

                /* Database-backed prices are authoritative. */
                $locked_price = billing_catalog_locked_price(
                    $hospital_conn,
                    $type,
                    $name
                );

                if ($locked_price !== null) {
                    $unit = $locked_price;
                }

                if (strtolower($type) === 'bed') {
                    throw new Exception('Bed must be selected through its Room.');
                }

                /*
                 * Prevent manually adding another
                 * copy of the appointment service.
                 */
                if (
                    strtolower($name) ===
                    strtolower($mandatory_service_name)
                    &&
                    abs(
                        $unit -
                        $mandatory_service_fee
                    ) < 0.01
                ) {
                    continue;
                }

                if (
                    $qty <= 0 ||
                    $unit < 0 ||
                    $item_discount < 0 ||
                    $item_tax < 0
                ) {

                    throw new Exception(
                        "Invalid amount in additional bill item."
                    );
                }

                $line_total = max(
                    0,
                    ($qty * $unit)
                    - $item_discount
                    + $item_tax
                );

                $subtotal += $line_total;

                $clean_items[] = [
                    'type'     => db_bill_item_type($type),
                    'name'     => $name,
                    'qty'      => $qty,
                    'unit'     => $unit,
                    'discount' => $item_discount,
                    'tax'      => $item_tax,
                    'total'    => $line_total,
                    'remarks'  => $item_remarks
                ];
            }

            if (empty($clean_items)) {
                throw new Exception(
                    "Appointment consultation fee is required."
                );
            }

            /* =================================================
               FINAL PAYABLE
            ================================================= */

            $payable = max(
                0,
                $subtotal
                - $discount
                + $tax
            );

            /* =================================================
               BILL NUMBER
            ================================================= */

            $bill_no =
                'BILL-' .
                date('YmdHis') .
                '-' .
                strtoupper(
                    bin2hex(
                        random_bytes(2)
                    )
                );

            $status = 'Pending';

            /* =================================================
               INSERT BILL
            ================================================= */

            $q = mysqli_prepare(
                $hospital_conn,
                "INSERT INTO billing
                (
                    appointment_id,
                    bill_no,
                    total_amount,
                    discount_amount,
                    tax_amount,
                    payable_amount,
                    bill_status,
                    remarks
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );

            if ($q === false) {
                throw new Exception(
                    "Billing insert prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "isddddss",
                $appointment_id,
                $bill_no,
                $subtotal,
                $discount,
                $tax,
                $payable,
                $status,
                $remarks
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            $bill_id =
                mysqli_insert_id(
                    $hospital_conn
                );

            mysqli_stmt_close($q);

            /* =================================================
               INSERT BILL ITEMS
            ================================================= */

            $q = mysqli_prepare(
                $hospital_conn,
                "INSERT INTO bill_items
                (
                    bill_id,
                    item_type,
                    item_name,
                    quantity,
                    unit_price,
                    discount_amount,
                    tax_amount,
                    total_amount,
                    remarks
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );

            if ($q === false) {
                throw new Exception(
                    "Bill items prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            foreach ($clean_items as $item) {

                mysqli_stmt_bind_param(
                    $q,
                    "issddddds",
                    $bill_id,
                    $item['type'],
                    $item['name'],
                    $item['qty'],
                    $item['unit'],
                    $item['discount'],
                    $item['tax'],
                    $item['total'],
                    $item['remarks']
                );

                if (!mysqli_stmt_execute($q)) {
                    throw new Exception(
                        mysqli_stmt_error($q)
                    );
                }
            }

            mysqli_stmt_close($q);

            mysqli_commit($hospital_conn);

            header(
                "Location: billing.php?added=1"
            );

            exit;

        } catch (Throwable $e) {

            mysqli_rollback(
                $hospital_conn
            );

            $message =
                $e->getMessage();

            $message_type =
                'error';
        }
    }
}

/* =========================================================
   EDIT / UPDATE BILL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['edit_bill'])
) {

    $bill_id = (int)(
        $_POST['bill_id'] ?? 0
    );

    $discount = max(
        0,
        (float)($_POST['discount_amount'] ?? 0)
    );

    $tax = max(
        0,
        (float)($_POST['tax_amount'] ?? 0)
    );

    $remarks = trim(
        $_POST['remarks'] ?? ''
    );

    $items = $_POST['items'] ?? [];

    if ($bill_id <= 0 || !is_array($items)) {

        $message = "Invalid bill data.";
        $message_type = 'error';

    } else {

        mysqli_begin_transaction($hospital_conn);

        try {

            /* Lock the bill while editing. */
            $q = mysqli_prepare(
                $hospital_conn,
                "SELECT
                    bill_id,
                    appointment_id,
                    bill_status
                 FROM billing
                 WHERE bill_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            if ($q === false) {
                throw new Exception(
                    "Bill lookup failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $bill_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            $bill_result =
                mysqli_stmt_get_result($q);

            $existing_bill =
                mysqli_fetch_assoc($bill_result);

            mysqli_stmt_close($q);

            if (!$existing_bill) {
                throw new Exception("Bill not found.");
            }

            if ($existing_bill['bill_status'] === 'Cancelled') {
                throw new Exception(
                    "Cancelled bills cannot be edited."
                );
            }

            /*
             * Re-read the appointment/doctor fee from the database.
             * The doctor fee is never trusted from the browser.
             */
            $q = mysqli_prepare(
                $hospital_conn,
                "SELECT
                    a.service_id,
                    s.service_name,
                    s.service_fee,
                    d.doctor_name,
                    d.consultation_fee AS doctor_consultation_fee
                 FROM appointments a
                 JOIN services s ON s.service_id = a.service_id
                 JOIN doctors d ON d.doctor_id = a.doctor_id
                 WHERE a.appointment_id = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            if ($q === false) {
                throw new Exception(
                    "Appointment fee lookup failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $existing_bill['appointment_id']
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(mysqli_stmt_error($q));
            }

            $appointment_result = mysqli_stmt_get_result($q);
            $edit_appointment = mysqli_fetch_assoc($appointment_result);
            mysqli_stmt_close($q);

            if (!$edit_appointment) {
                throw new Exception("Appointment for this bill was not found.");
            }

            $mandatory_edit_fee =
                (float)$edit_appointment['doctor_consultation_fee'] > 0
                    ? (float)$edit_appointment['doctor_consultation_fee']
                    : (float)$edit_appointment['service_fee'];

            $mandatory_edit_name =
                trim($edit_appointment['service_name'] ?? '') !== ''
                    ? trim($edit_appointment['service_name'])
                    : 'Doctor Consultation';

            $clean_items = [];
            $subtotal = 0;

            /*
             * Doctor/appointment fee is always rebuilt from the appointment.
             * A posted Doctor Fee/Service row is ignored so it cannot be edited.
             */
            $clean_items[] = [
                'type'     => 'Service',
                'name'     => $mandatory_edit_name,
                'qty'      => 1,
                'unit'     => $mandatory_edit_fee,
                'discount' => 0,
                'tax'      => 0,
                'total'    => $mandatory_edit_fee,
                'remarks'  => 'Mandatory appointment consultation/service fee - Dr. ' .
                    $edit_appointment['doctor_name']
            ];

            $subtotal += $mandatory_edit_fee;

            foreach ($items as $item) {

                $type = trim(
                    $item['item_type'] ??
                    'Other Service'
                );

                /*
                 * The UI's locked Doctor Fee row is not an editable charge.
                 * The server already inserted the authoritative fee above.
                 */
                if (in_array(strtolower($type), ['doctor fee', 'consultation', 'service', 'appointment fee'], true)) {
                    continue;
                }

                $name = trim(
                    $item['item_name'] ?? ''
                );

                $qty = (float)(
                    $item['quantity'] ?? 1
                );

                $unit = (float)(
                    $item['unit_price'] ?? 0
                );

                $item_discount = max(
                    0,
                    (float)(
                        $item['discount_amount'] ?? 0
                    )
                );

                $item_tax = max(
                    0,
                    (float)(
                        $item['tax_amount'] ?? 0
                    )
                );

                $item_remarks = trim(
                    $item['remarks'] ?? ''
                );

                if ($name === '') {
                    continue;
                }

                /* Database-backed prices are authoritative during edits too. */
                $locked_price = billing_catalog_locked_price(
                    $hospital_conn,
                    $type,
                    $name
                );

                if ($locked_price !== null) {
                    $unit = $locked_price;
                }

                if (
                    $qty <= 0 ||
                    $unit < 0
                ) {
                    throw new Exception(
                        "Invalid quantity or unit price in bill item."
                    );
                }

                $line_total = max(
                    0,
                    ($qty * $unit)
                    - $item_discount
                    + $item_tax
                );

                $subtotal += $line_total;

                $clean_items[] = [
                    'type'     => db_bill_item_type($type),
                    'name'     => $name,
                    'qty'      => $qty,
                    'unit'     => $unit,
                    'discount' => $item_discount,
                    'tax'      => $item_tax,
                    'total'    => $line_total,
                    'remarks'  => $item_remarks
                ];
            }

            if (empty($clean_items)) {
                throw new Exception(
                    "At least one bill item is required."
                );
            }

            $payable = max(
                0,
                $subtotal
                - $discount
                + $tax
            );

            /* Successful payments remain attached to the same bill. */
            $q = mysqli_prepare(
                $hospital_conn,
                "SELECT
                    COALESCE(
                        SUM(amount),
                        0
                    ) AS paid_amount
                 FROM payments
                 WHERE bill_id = ?
                 AND payment_status = 'Success'"
            );

            if ($q === false) {
                throw new Exception(
                    "Payment total query failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $bill_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            $paid_result =
                mysqli_stmt_get_result($q);

            $paid_row =
                mysqli_fetch_assoc($paid_result);

            mysqli_stmt_close($q);

            $paid_amount =
                (float)($paid_row['paid_amount'] ?? 0);

            if ($paid_amount <= 0) {
                $new_status = 'Pending';
            } elseif ($paid_amount >= $payable) {
                $new_status = 'Paid';
            } else {
                $new_status = 'Partially Paid';
            }

            /* Replace the item rows but keep the same bill_id/bill_no. */
            $q = mysqli_prepare(
                $hospital_conn,
                "DELETE FROM bill_items
                 WHERE bill_id = ?"
            );

            if ($q === false) {
                throw new Exception(
                    "Old bill items delete failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "i",
                $bill_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            mysqli_stmt_close($q);

            $q = mysqli_prepare(
                $hospital_conn,
                "INSERT INTO bill_items
                (
                    bill_id,
                    item_type,
                    item_name,
                    quantity,
                    unit_price,
                    discount_amount,
                    tax_amount,
                    total_amount,
                    remarks
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )"
            );

            if ($q === false) {
                throw new Exception(
                    "Bill item update prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            foreach ($clean_items as $item) {

                mysqli_stmt_bind_param(
                    $q,
                    "issddddds",
                    $bill_id,
                    $item['type'],
                    $item['name'],
                    $item['qty'],
                    $item['unit'],
                    $item['discount'],
                    $item['tax'],
                    $item['total'],
                    $item['remarks']
                );

                if (!mysqli_stmt_execute($q)) {
                    throw new Exception(
                        mysqli_stmt_error($q)
                    );
                }
            }

            mysqli_stmt_close($q);

            $q = mysqli_prepare(
                $hospital_conn,
                "UPDATE billing
                 SET
                    total_amount = ?,
                    discount_amount = ?,
                    tax_amount = ?,
                    payable_amount = ?,
                    bill_status = ?,
                    remarks = ?
                 WHERE bill_id = ?
                 LIMIT 1"
            );

            if ($q === false) {
                throw new Exception(
                    "Bill update prepare failed: " .
                    mysqli_error($hospital_conn)
                );
            }

            mysqli_stmt_bind_param(
                $q,
                "ddddssi",
                $subtotal,
                $discount,
                $tax,
                $payable,
                $new_status,
                $remarks,
                $bill_id
            );

            if (!mysqli_stmt_execute($q)) {
                throw new Exception(
                    mysqli_stmt_error($q)
                );
            }

            mysqli_stmt_close($q);

            mysqli_commit($hospital_conn);

            header(
                "Location: billing.php?updated=1"
            );

            exit;

        } catch (Throwable $e) {

            mysqli_rollback(
                $hospital_conn
            );

            $message =
                $e->getMessage();

            $message_type =
                'error';
        }
    }
}

/* =========================================================
   CANCEL BILL
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['delete_bill'])
) {

    $bill_id = (int)(
        $_POST['bill_id'] ?? 0
    );

    if ($bill_id > 0) {

        $q = mysqli_prepare(
            $hospital_conn,
            "SELECT
                COUNT(*) AS c
             FROM payments
             WHERE bill_id = ?
             AND payment_status IN
                 ('Success', 'Pending')"
        );

        if ($q === false) {

            $message =
                "Payment check failed: " .
                mysqli_error($hospital_conn);

            $message_type = 'error';

        } else {

            mysqli_stmt_bind_param(
                $q,
                "i",
                $bill_id
            );

            if (!mysqli_stmt_execute($q)) {

                $message =
                    mysqli_stmt_error($q);

                $message_type = 'error';

                mysqli_stmt_close($q);

            } else {

                $payment_result =
                    mysqli_stmt_get_result($q);

                $payment_check =
                    mysqli_fetch_assoc(
                        $payment_result
                    );

                mysqli_stmt_close($q);

                $payment_count =
                    (int)(
                        $payment_check['c'] ?? 0
                    );

                if ($payment_count > 0) {

                    $message =
                        "Paid or pending bills cannot be cancelled. Resolve payments first.";

                    $message_type = 'error';

                } else {

                    $q = mysqli_prepare(
                        $hospital_conn,
                        "UPDATE billing
                         SET bill_status = 'Cancelled'
                         WHERE bill_id = ?
                         LIMIT 1"
                    );

                    if ($q === false) {

                        $message =
                            "Bill cancellation failed: " .
                            mysqli_error($hospital_conn);

                        $message_type = 'error';

                    } else {

                        mysqli_stmt_bind_param(
                            $q,
                            "i",
                            $bill_id
                        );

                        if (!mysqli_stmt_execute($q)) {

                            $message =
                                mysqli_stmt_error($q);

                            $message_type = 'error';

                        } else {

                            mysqli_stmt_close($q);

                            header(
                                "Location: billing.php?deleted=1"
                            );

                            exit;
                        }

                        mysqli_stmt_close($q);
                    }
                }
            }
        }
    }
}

/* =========================================================
   URL MESSAGES
========================================================= */

if (isset($_GET['added'])) {

    $message =
        "Bill created successfully.";

    $message_type =
        "success";
}

if (isset($_GET['deleted'])) {

    $message =
        "Bill cancelled successfully.";

    $message_type =
        "success";
}

if (isset($_GET['updated'])) {

    $message =
        "Bill updated successfully.";

    $message_type =
        "success";
}

/* =========================================================
   APPOINTMENTS
========================================================= */

$appointments = [];

$appointment_sql = "
    SELECT
        a.appointment_id,
        a.appointment_no,
        a.appointment_date,
        a.appointment_time,
        a.service_id,

        s.service_name,
        s.service_fee,
        s.service_type,

        d.doctor_name,
        d.consultation_fee AS doctor_consultation_fee,

        CONCAT(
            COALESCE(pa.first_name, ''),
            ' ',
            COALESCE(pa.last_name, '')
        ) AS patient_name,

        phm.hospital_patient_code

    FROM appointments a

    JOIN doctors d
        ON d.doctor_id = a.doctor_id

    JOIN services s
        ON s.service_id = a.service_id

    JOIN hospital_management.patient_hospital_mapping phm
        ON phm.mapping_id = a.mapping_id

    JOIN hospital_management.patient_accounts pa
        ON pa.account_id = phm.account_id

    WHERE a.appointment_status NOT IN
        ('Cancelled', 'No-Show')

    ORDER BY
        a.appointment_date DESC,
        a.appointment_time DESC
";

$q = mysqli_query(
    $hospital_conn,
    $appointment_sql
);

if ($q === false) {

    die(
        "Appointment query failed.<br><br>" .
        "<strong>MySQL Error:</strong> " .
        clean(mysqli_error($hospital_conn)) .
        "<br><br><strong>Query:</strong><br>" .
        nl2br(clean($appointment_sql))
    );
}

while ($row = mysqli_fetch_assoc($q)) {
    $appointments[] = $row;
}

/* =========================================================
   PATIENT-LINKED BILLING CHARGES
   Uses only relationships present in the supplied SQL schema.
   - Active admission -> room + bed
   - Medical record -> lab tests
   - Medical record -> prescription medicines
   The supplied schema has no pharmacy-sale/dispensing amount
   column and no lab-test fee column, so those source records
   are loaded with ₹0.00 when no matching service fee exists.
========================================================= */

$patient_charges_by_appointment = [];

foreach ($appointments as $a) {
    $appointment_id = (int)$a['appointment_id'];
    $charges = [];

    $admission_sql = "
        SELECT
            ad.admission_id,
            b.bed_id,
            b.bed_number,
            b.bed_charge,
            r.room_id,
            r.room_number,
            r.room_type,
            r.room_charge
        FROM admissions ad
        JOIN beds b
            ON b.bed_id = ad.bed_id
        JOIN rooms r
            ON r.room_id = b.room_id
        JOIN appointments ap
            ON ap.mapping_id = ad.mapping_id
        WHERE ap.appointment_id = ?
          AND ad.admission_status = 'Admitted'
        ORDER BY ad.admission_date DESC, ad.admission_id DESC
        LIMIT 1
    ";

    $aq = mysqli_prepare($hospital_conn, $admission_sql);

    if ($aq) {
        mysqli_stmt_bind_param($aq, "i", $appointment_id);

        if (mysqli_stmt_execute($aq)) {
            $ar = mysqli_stmt_get_result($aq);

            if ($ad = mysqli_fetch_assoc($ar)) {
                $room_charge = (float)$ad['room_charge'];
                $bed_charge  = (float)$ad['bed_charge'];
                $combined_room_bed_charge = $room_charge + $bed_charge;

                $charges[] = [
                    'type' => 'Room',
                    'name' => 'Room ' . $ad['room_number'] . ' / Bed ' . $ad['bed_number'],
                    'room_id' => (int)$ad['room_id'],
                    'room_number' => (string)$ad['room_number'],
                    'room_type' => (string)($ad['room_type'] ?? ''),
                    'bed_id' => (int)$ad['bed_id'],
                    'bed_number' => (string)$ad['bed_number'],
                    'bed_name' => 'Bed ' . $ad['bed_number'],
                    'room_charge' => $room_charge,
                    'bed_charge' => $bed_charge,
                    'room_price' => $room_charge,
                    'bed_price' => $bed_charge,
                    'price_locked' => true,
                    'quantity' => 1,
                    'unit' => $combined_room_bed_charge,
                    'discount' => 0,
                    'tax' => 0,
                    'remarks' => 'Automatically linked from active admission ' .
                        $ad['admission_id'] .
                        ' • Room ₹' . number_format($room_charge, 2, '.', '') .
                        ' + Bed ₹' . number_format($bed_charge, 2, '.', '')
                ];
            }
        }

        mysqli_stmt_close($aq);
    }

    $lab_sql = "
        SELECT
            lt.lab_test_id,
            lt.test_name,
            lt.test_status,
            lt.test_fee
        FROM medical_records mr
        JOIN lab_tests lt
            ON lt.medical_record_id = mr.medical_record_id
        WHERE mr.appointment_id = ?
          AND lt.test_status <> 'Cancelled'
        ORDER BY lt.created_at ASC, lt.lab_test_id ASC
    ";

    $lq = mysqli_prepare($hospital_conn, $lab_sql);

    if ($lq) {
        mysqli_stmt_bind_param($lq, "i", $appointment_id);

        if (mysqli_stmt_execute($lq)) {
            $lr = mysqli_stmt_get_result($lq);

            while ($lab = mysqli_fetch_assoc($lr)) {
                $charges[] = [
                    'type' => 'Lab Test',
                    'name' => $lab['test_name'],
                    'quantity' => 1,
                    'unit' => (float)$lab['test_fee'],
                    'discount' => 0,
                    'tax' => 0,
                    'remarks' => 'Automatically linked lab record #' .
                        $lab['lab_test_id'] . ' (' . $lab['test_status'] . ')'
                ];
            }
        }

        mysqli_stmt_close($lq);
    }

    $medicine_sql = "
        SELECT
            pi.prescription_item_id,
            pi.medicine_name,
            pi.dosage,
            pi.frequency,
            pi.duration,
            p.prescription_no
        FROM medical_records mr
        JOIN prescriptions p
            ON p.medical_record_id = mr.medical_record_id
        JOIN prescription_items pi
            ON pi.prescription_id = p.prescription_id
        WHERE mr.appointment_id = ?
          AND p.status <> 'Cancelled'
        ORDER BY pi.created_at ASC, pi.prescription_item_id ASC
    ";

    $mq = mysqli_prepare($hospital_conn, $medicine_sql);

    if ($mq) {
        mysqli_stmt_bind_param($mq, "i", $appointment_id);

        if (mysqli_stmt_execute($mq)) {
            $mr = mysqli_stmt_get_result($mq);

            while ($med = mysqli_fetch_assoc($mr)) {
                $charges[] = [
                    'type' => 'Medicine',
                    'name' => $med['medicine_name'],
                    'quantity' => 1,
                    'unit' => 0,
                    'discount' => 0,
                    'tax' => 0,
                    'remarks' =>
                        'Linked prescription ' . $med['prescription_no'] .
                        ' • ' . $med['dosage'] .
                        ' • ' . $med['frequency'] .
                        ' • ' . $med['duration'] .
                        ' • Medicine price is not stored in supplied schema'
                ];
            }
        }

        mysqli_stmt_close($mq);
    }

    $patient_charges_by_appointment[$appointment_id] = $charges;
}

/* =========================================================
   BILLING ITEM CATALOG
   Used by the Create Bill modal for real dropdowns.
========================================================= */

$item_catalog = [
    'Room' => [],
    'Bed' => [],
    'Lab Test' => [],
    'Medicine' => [],
    'Other Service' => []
];

/* Rooms */
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT room_id, room_number, room_type, room_charge
     FROM rooms
     WHERE status = 'Available'
     ORDER BY room_number ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $item_catalog['Room'][] = [
            'id' => (int)$row['room_id'],
            'name' => (string)$row['room_number'],
            'room_id' => (int)$row['room_id'],
            'room_type' => (string)$row['room_type'],
            'price' => (float)$row['room_charge'],
            'price_locked' => true
        ];
    }
}

/* Beds */
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT b.bed_id, b.bed_number, b.bed_charge, b.room_id, r.room_number, r.room_type
     FROM beds b
     INNER JOIN rooms r ON r.room_id = b.room_id
     WHERE b.status = 'Available'
     ORDER BY b.bed_number ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $item_catalog['Bed'][] = [
            'id' => (int)$row['bed_id'],
            'name' => (string)$row['bed_number'],
            'room_id' => (int)$row['room_id'],
            'room_number' => (string)$row['room_number'],
            'room_type' => (string)$row['room_type'],
            'price' => (float)$row['bed_charge'],
            'price_locked' => true
        ];
    }
}

/* Lab tests */
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT
        lt.lab_test_id,
        lt.test_name,
        lt.test_fee
     FROM lab_tests lt
     WHERE lt.test_name IS NOT NULL
       AND lt.test_name <> ''
     ORDER BY lt.test_name ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $item_catalog['Lab Test'][] = [
            'id' => (int)$row['lab_test_id'],
            'name' => (string)$row['test_name'],
            'price' => (float)$row['test_fee'],
            'price_locked' => (float)$row['test_fee'] > 0
        ];
    }
}

/* Medicines */
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT DISTINCT medicine_name
     FROM prescription_items
     WHERE medicine_name IS NOT NULL
       AND medicine_name <> ''
     ORDER BY medicine_name ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $item_catalog['Medicine'][] = [
            'id' => 0,
            'name' => (string)$row['medicine_name'],
            'price' => 0,
            'price_locked' => false
        ];
    }
}

/* Other services */
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT service_id, service_name, service_fee
     FROM services
     WHERE service_name IS NOT NULL
       AND service_name <> ''
     ORDER BY service_name ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $item_catalog['Other Service'][] = [
            'id' => (int)$row['service_id'],
            'name' => (string)$row['service_name'],
            'price' => (float)$row['service_fee'],
            'price_locked' => (float)$row['service_fee'] > 0
        ];
    }
}

/* =========================================================
   EDIT BILL ITEM CATALOG
   Includes already occupied rooms/beds so a bill can be
   updated after admission without losing the dropdown.
========================================================= */

$edit_item_catalog = $item_catalog;

/* For editing historical/assigned billing items, do not limit
   Room/Bed to Available only. */
$edit_item_catalog['Room'] = [];
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT room_id, room_number, room_type, room_charge, status
     FROM rooms
     ORDER BY room_number ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $edit_item_catalog['Room'][] = [
            'id' => (int)$row['room_id'],
            'name' => (string)$row['room_number'],
            'room_id' => (int)$row['room_id'],
            'room_type' => (string)$row['room_type'],
            'price' => (float)$row['room_charge'],
            'price_locked' => true,
            'status' => (string)$row['status']
        ];
    }
}

$edit_item_catalog['Bed'] = [];
$catalog_q = mysqli_query(
    $hospital_conn,
    "SELECT b.bed_id, b.bed_number, b.bed_charge, b.room_id, r.room_number, r.room_type, b.status
     FROM beds b
     INNER JOIN rooms r ON r.room_id = b.room_id
     ORDER BY bed_number ASC"
);

if ($catalog_q) {
    while ($row = mysqli_fetch_assoc($catalog_q)) {
        $edit_item_catalog['Bed'][] = [
            'id' => (int)$row['bed_id'],
            'name' => (string)$row['bed_number'],
            'room_id' => (int)$row['room_id'],
            'room_number' => (string)$row['room_number'],
            'room_type' => (string)$row['room_type'],
            'price' => (float)$row['bed_charge'],
            'price_locked' => true,
            'status' => (string)$row['status']
        ];
    }
}

/* =========================================================
   SEARCH / FILTER
========================================================= */

$search = trim(
    $_GET['search'] ?? ''
);

$status_filter = trim(
    $_GET['status'] ?? ''
);

$where = ["1=1"];

$params = [];
$types = '';

if ($search !== '') {

    $where[] =
        "(
            b.bill_no LIKE ?
            OR a.appointment_no LIKE ?
            OR pa.first_name LIKE ?
            OR pa.last_name LIKE ?
            OR CONCAT(
                pa.first_name,
                ' ',
                pa.last_name
            ) LIKE ?
        )";

    $value = "%{$search}%";

    for ($i = 0; $i < 5; $i++) {
        $params[] = $value;
        $types .= 's';
    }
}

if ($status_filter !== '') {

    $where[] =
        "b.bill_status = ?";

    $params[] =
        $status_filter;

    $types .= 's';
}

$where_sql =
    implode(' AND ', $where);

/* =========================================================
   BILL LIST
========================================================= */

$rows = [];

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

        a.appointment_no,
        a.appointment_date,
        a.appointment_time,

        d.doctor_name,

        CONCAT(
            COALESCE(pa.first_name, ''),
            ' ',
            COALESCE(pa.last_name, '')
        ) AS patient_name,

        phm.hospital_patient_code,

        COALESCE(
            (
                SELECT
                    SUM(p.amount)
                FROM payments p
                WHERE p.bill_id = b.bill_id
                AND p.payment_status = 'Success'
            ),
            0
        ) AS paid_amount

    FROM billing b

    JOIN appointments a
        ON a.appointment_id =
           b.appointment_id

    JOIN doctors d
        ON d.doctor_id =
           a.doctor_id

    JOIN hospital_management.patient_hospital_mapping phm
        ON phm.mapping_id =
           a.mapping_id

    JOIN hospital_management.patient_accounts pa
        ON pa.account_id =
           phm.account_id

    WHERE {$where_sql}

    ORDER BY
        b.created_at DESC
";

$stmt = mysqli_prepare(
    $hospital_conn,
    $sql
);

if ($stmt === false) {

    die(
        "Billing query prepare failed.<br><br>" .
        "<strong>MySQL Error:</strong> " .
        clean(mysqli_error($hospital_conn)) .
        "<br><br><strong>Query:</strong><br>" .
        nl2br(clean($sql))
    );
}

if (!empty($params)) {

    if (
        !mysqli_stmt_bind_param(
            $stmt,
            $types,
            ...$params
        )
    ) {

        die(
            "Billing query bind failed.<br><br>" .
            clean(
                mysqli_stmt_error($stmt)
            )
        );
    }
}

if (!mysqli_stmt_execute($stmt)) {

    die(
        "Billing query execute failed.<br><br>" .
        clean(
            mysqli_stmt_error($stmt)
        )
    );
}

$res =
    mysqli_stmt_get_result($stmt);

if ($res === false) {

    die(
        "Billing result failed.<br><br>" .
        clean(
            mysqli_stmt_error($stmt)
        )
    );
}

while ($r = mysqli_fetch_assoc($res)) {

    $r['outstanding'] =
        max(
            0,
            (float)$r['payable_amount']
            - (float)$r['paid_amount']
        );

    $rows[] = $r;
}

mysqli_stmt_close($stmt);

/* =========================================================
   LOAD BILL ITEMS FOR EDITING
========================================================= */

$bill_items_by_bill = [];

$bill_ids = array_values(
    array_filter(
        array_map(
            'intval',
            array_column($rows, 'bill_id')
        )
    )
);

if (!empty($bill_ids)) {

    $id_list = implode(',', $bill_ids);

    $items_query = mysqli_query(
        $hospital_conn,
        "SELECT
            bill_id,
            item_type,
            item_name,
            quantity,
            unit_price,
            discount_amount,
            tax_amount,
            total_amount,
            remarks
         FROM bill_items
         WHERE bill_id IN ({$id_list})
         ORDER BY bill_id ASC"
    );

    if ($items_query) {

        while ($item_row = mysqli_fetch_assoc($items_query)) {

            $bill_items_by_bill[
                (int)$item_row['bill_id']
            ][] = $item_row;
        }
    }
}

/* =========================================================
   STATISTICS
========================================================= */

$stats = [
    'total' => 0,
    'pending' => 0,
    'paid' => 0,
    'outstanding' => 0
];

$stats_sql = "
    SELECT
        COUNT(*) AS total,

        SUM(
            bill_status = 'Pending'
        ) AS pending,

        SUM(
            bill_status = 'Paid'
        ) AS paid,

        COALESCE(
            SUM(
                GREATEST(
                    0,
                    payable_amount -
                    COALESCE(
                        (
                            SELECT
                                SUM(p.amount)
                            FROM payments p
                            WHERE p.bill_id =
                                  billing.bill_id
                            AND p.payment_status =
                                'Success'
                        ),
                        0
                    )
                )
            ),
            0
        ) AS outstanding

    FROM billing
    WHERE bill_status <> 'Cancelled'
";

$stats_query =
    mysqli_query(
        $hospital_conn,
        $stats_sql
    );

if ($stats_query) {

    $r =
        mysqli_fetch_assoc(
            $stats_query
        );

    if ($r) {

        $stats['total'] =
            (int)$r['total'];

        $stats['pending'] =
            (int)$r['pending'];

        $stats['paid'] =
            (int)$r['paid'];

        $stats['outstanding'] =
            (float)$r['outstanding'];
    }
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
        Billing | <?= clean($hospital_name) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/sidebar.css"
    >

    <link
        rel="stylesheet"
        href="css/billing.css"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

</head>

<body class="billing-page">

<?php include 'sidebar.php'; ?>

<main class="main-content">

    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="header-left">

            <div class="page-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>

            <div>

                <h1>Billing</h1>

                <p>
                    Create, track and manage patient bills.
                </p>

            </div>

        </div>

        <button
            class="add-btn"
            id="addBillBtn"
            type="button"
        >
            <i class="fa-solid fa-plus"></i>
            Create Bill
        </button>

    </div>

    <!-- MESSAGE -->

    <?php if ($message): ?>

        <div
            class="<?= $message_type === 'success'
                ? 'success-message'
                : 'error-message' ?>"
        >

            <i
                class="fa-solid
                <?= $message_type === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation' ?>"
            ></i>

            <span>
                <?= clean($message) ?>
            </span>

        </div>

    <?php endif; ?>

    <!-- STATISTICS -->

    <div class="stats-grid">

        <div class="stat-card">

            <div class="stat-icon purple">
                <i class="fa-solid fa-file-invoice"></i>
            </div>

            <div class="stat-info">
                <span>Total Bills</span>
                <strong><?= $stats['total'] ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon orange">
                <i class="fa-solid fa-clock"></i>
            </div>

            <div class="stat-info">
                <span>Pending</span>
                <strong><?= $stats['pending'] ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon green">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div class="stat-info">
                <span>Paid</span>
                <strong><?= $stats['paid'] ?></strong>
            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon red">
                <i class="fa-solid fa-wallet"></i>
            </div>

            <div class="stat-info">
                <span>Outstanding</span>

                <strong>
                    ₹ <?= money(
                        $stats['outstanding']
                    ) ?>
                </strong>

            </div>

        </div>

    </div>

    <!-- FILTER -->

    <form
        class="toolbar"
        method="get"
    >

        <div class="search-box">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                name="search"
                value="<?= clean($search) ?>"
                placeholder="Search bill, patient or appointment..."
            >

        </div>

        <select name="status">

            <option value="">
                All Status
            </option>

            <?php

            $statuses = [
                'Pending',
                'Partially Paid',
                'Paid',
                'Cancelled'
            ];

            ?>

            <?php foreach ($statuses as $status): ?>

                <option
                    value="<?= clean($status) ?>"
                    <?= $status_filter === $status
                        ? 'selected'
                        : '' ?>
                >
                    <?= clean($status) ?>
                </option>

            <?php endforeach; ?>

        </select>

        <button
            class="filter-btn"
            type="submit"
        >
            <i class="fa-solid fa-filter"></i>
            Filter
        </button>

        <a
            class="reset-btn"
            href="billing.php"
        >
            Reset
        </a>

    </form>

    <!-- BILL TABLE -->

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Bill</th>
                    <th>Patient</th>
                    <th>Appointment</th>
                    <th>Doctor</th>
                    <th>Payable</th>
                    <th>Paid</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

            <?php if (!$rows): ?>

                <tr>

                    <td
                        colspan="9"
                        class="empty"
                    >

                        <div class="empty-icon">

                            <i
                                class="fa-solid fa-file-circle-xmark"
                            ></i>

                        </div>

                        <strong>
                            No bills found
                        </strong>

                        <span>
                            Create a new bill to see it here.
                        </span>

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($rows as $r): ?>

                    <tr>

                        <td>

                            <strong class="bill-number">
                                <?= clean(
                                    $r['bill_no']
                                ) ?>
                            </strong>

                            <small>
                                <?= date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        $r['created_at']
                                    )
                                ) ?>
                            </small>

                        </td>

                        <td>

                            <strong>
                                <?= clean(
                                    trim(
                                        $r['patient_name']
                                    )
                                ) ?>
                            </strong>

                            <small>
                                <?= clean(
                                    $r[
                                        'hospital_patient_code'
                                    ]
                                ) ?>
                            </small>

                        </td>

                        <td>

                            <strong>
                                <?= clean(
                                    $r['appointment_no']
                                ) ?>
                            </strong>

                            <small>

                                <?= date(
                                    'd M Y',
                                    strtotime(
                                        $r['appointment_date']
                                    )
                                ) ?>

                                &nbsp;

                                <?= date(
                                    'h:i A',
                                    strtotime(
                                        $r['appointment_time']
                                    )
                                ) ?>

                            </small>

                        </td>

                        <td>
                            Dr.
                            <?= clean(
                                $r['doctor_name']
                            ) ?>
                        </td>

                        <td class="amount">

                            ₹ <?= money(
                                $r['payable_amount']
                            ) ?>

                        </td>

                        <td class="amount paid-amount">

                            ₹ <?= money(
                                $r['paid_amount']
                            ) ?>

                        </td>

                        <td class="amount outstanding-amount">

                            ₹ <?= money(
                                $r['outstanding']
                            ) ?>

                        </td>

                        <td>

                            <span
                                class="status
                                <?= strtolower(
                                    str_replace(
                                        ' ',
                                        '-',
                                        $r['bill_status']
                                    )
                                ) ?>"
                            >

                                <?= clean(
                                    $r['bill_status']
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <div class="action-buttons">

                                <button
                                    type="button"
                                    class="action-btn view-btn"
                                    data-id="<?= $r['bill_id'] ?>"
                                    title="View Bill"
                                >
                                    <i class="fa-solid fa-eye"></i>
                                </button>

                                <?php if ($r['bill_status'] !== 'Cancelled'): ?>

                                    <button
                                        type="button"
                                        class="action-btn edit-btn"
                                        data-id="<?= $r['bill_id'] ?>"
                                        title="Edit Bill"
                                    >
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <a
                                        class="action-btn payment-btn"
                                        href="payments.php?bill_id=<?= $r['bill_id'] ?>"
                                        title="Payments"
                                    >
                                        <i class="fa-solid fa-credit-card"></i>
                                    </a>

                                <?php endif; ?>


                                <?php
                                if (
                                    $r['bill_status'] !== 'Cancelled'
                                    &&
                                    (float)$r['paid_amount'] == 0
                                ):
                                ?>

                                    <button
                                        type="button"
                                        class="action-btn delete-btn"
                                        data-id="<?= $r['bill_id'] ?>"
                                        data-name="<?= clean(
                                            $r['bill_no']
                                        ) ?>"
                                        title="Cancel Bill"
                                    >
                                        <i class="fa-solid fa-ban"></i>
                                    </button>

                                <?php endif; ?>

                            </div>

                            <div
                                class="bill-data"
                                id="bill-<?= $r['bill_id'] ?>"
                                data-bill="<?= clean(
                                    json_encode(
                                        array_merge(
                                            $r,
                                            [
                                                'items' =>
                                                    $bill_items_by_bill[
                                                        (int)$r['bill_id']
                                                    ] ?? [],
                                                'patient_charges' =>
                                                    $patient_charges_by_appointment[
                                                        (int)$r['appointment_id']
                                                    ] ?? []
                                            ]
                                        ),
                                        JSON_UNESCAPED_UNICODE |
                                        JSON_UNESCAPED_SLASHES
                                    )
                                ) ?>"
                            ></div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>

<!-- =========================================================
     CREATE BILL MODAL
========================================================= -->

<div
    class="modal"
    id="billModal"
>

    <div class="modal-content wide">

        <div class="modal-header">

            <div>

                <span class="modal-eyebrow">
                    Billing
                </span>

                <h2>
                    Create New Bill
                </h2>

            </div>

            <button
                type="button"
                class="close-btn"
                data-close="billModal"
            >
                &times;
            </button>

        </div>

        <form
            method="post"
            id="billForm"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="add_bill"
                value="1"
            >

            <div class="form-grid">

                <div class="form-group full-width">

                    <label>
                        Appointment / Patient
                    </label>

                    <select
                        name="appointment_id"
                        id="appointmentSelect"
                        required
                    >

                        <option value="">
                            Select appointment
                        </option>

                        <?php foreach (
                            $appointments as $a
                        ): ?>

                            <option
                                value="<?= (int)$a['appointment_id'] ?>"
                                data-service-id="<?= (int)$a['service_id'] ?>"
                                data-service-name="<?= clean(
                                    $a['service_name']
                                ) ?>"
                                data-service-fee="<?= money(
                                    $a['service_fee']
                                ) ?>"
                                data-service-type="<?= clean(
                                    $a['service_type']
                                ) ?>"
                                data-doctor="<?= clean(
                                    $a['doctor_name']
                                ) ?>"
                                data-doctor-fee="<?= money(
                                    (float)$a['doctor_consultation_fee'] > 0
                                        ? $a['doctor_consultation_fee']
                                        : $a['service_fee']
                                ) ?>"
                            >

                                <?= clean(
                                    $a['appointment_no']
                                ) ?>

                                —

                                <?= clean(
                                    trim(
                                        $a['patient_name']
                                    )
                                ) ?>

                                (<?= clean(
                                    $a[
                                        'hospital_patient_code'
                                    ]
                                ) ?>)

                                —

                                Dr. <?= clean(
                                    $a['doctor_name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <small class="field-hint">
                        The appointment consultation/service fee is automatically added and cannot be removed.
                    </small>

                </div>

                <!-- MANDATORY FEE PREVIEW -->

                <div
                    class="mandatory-fee-box full-width"
                    id="mandatoryFeeBox"
                >

                    <div class="mandatory-fee-icon">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>

                    <div class="mandatory-fee-info">

                        <span class="mandatory-label">
                            Mandatory Doctor / Appointment Fee
                        </span>

                        <strong id="mandatoryFeeName">
                            Select an appointment
                        </strong>

                        <small id="mandatoryFeeDoctor">
                            Doctor fee will be added automatically.
                        </small>

                    </div>

                    <div class="mandatory-fee-amount">
                        ₹ <span id="mandatoryFeeAmount">0.00</span>
                    </div>

                    <div class="locked-badge">
                        <i class="fa-solid fa-lock"></i>
                        Locked
                    </div>

                </div>

                <div class="form-group">

                    <label>
                        Bill Discount
                    </label>

                    <div class="input-with-icon">

                        <span>₹</span>

                        <input
                            type="number"
                            name="discount_amount"
                            id="billDiscount"
                            step="0.01"
                            min="0"
                            value="0"
                        >

                    </div>

                </div>

                <div class="form-group">

                    <label>
                        Bill Tax
                    </label>

                    <div class="input-with-icon">

                        <span>₹</span>

                        <input
                            type="number"
                            name="tax_amount"
                            id="billTax"
                            step="0.01"
                            min="0"
                            value="0"
                        >

                    </div>

                </div>

                <div class="form-group full-width">

                    <label>
                        Remarks
                    </label>

                    <textarea
                        name="remarks"
                        rows="3"
                        placeholder="Optional billing remarks..."
                    ></textarea>

                </div>

            </div>

            <!-- BILL ITEMS -->

            <div class="items-section">

                <div class="items-head">

                    <div>

                        <span class="section-label">
                            ADDITIONAL BILLING
                        </span>

                        <h3>
                            Other Charges
                        </h3>

                    </div>

                    <button
                        type="button"
                        class="secondary-btn"
                        id="addItemBtn"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add Item
                    </button>

                </div>

                <div class="additional-info">
                    Doctor consultation/service fee is already included above.
                    Use Add Item only for Room / Bed, Lab Test, Medicine or Other Service.
                </div>

                <div id="items"></div>

            </div>

            <!-- TOTAL -->

            <div class="bill-summary">

                <div class="summary-row">

                    <span>
                        Consultation / Appointment Fee
                    </span>

                    <strong id="consultationPreview">
                        ₹ 0.00
                    </strong>

                </div>

                <div class="summary-row">

                    <span>
                        Additional Items
                    </span>

                    <strong id="additionalPreview">
                        ₹ 0.00
                    </strong>

                </div>

                <div class="summary-row">

                    <span>
                        Items Subtotal
                    </span>

                    <strong id="subtotalPreview">
                        ₹ 0.00
                    </strong>

                </div>

                <div class="summary-row">

                    <span>
                        Bill Discount
                    </span>

                    <strong id="discountPreview">
                        - ₹ 0.00
                    </strong>

                </div>

                <div class="summary-row">

                    <span>
                        Bill Tax
                    </span>

                    <strong id="taxPreview">
                        + ₹ 0.00
                    </strong>

                </div>

                <div class="summary-total">

                    <span>
                        Payable Amount
                    </span>

                    <strong>
                        ₹
                        <span id="payablePreview">
                            0.00
                        </span>
                    </strong>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                    data-close="billModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="save-btn"
                >
                    <i class="fa-solid fa-check"></i>
                    Create Bill
                </button>

            </div>

        </form>

    </div>

</div>

<!-- =========================================================
     EDIT BILL MODAL
========================================================= -->

<div
    class="modal"
    id="editBillModal"
>
    <div class="modal-content wide">

        <div class="modal-header">

            <div>
                <span class="modal-eyebrow">
                    Billing
                </span>

                <h2>
                    Edit Bill
                </h2>

                <p class="modal-subtitle" id="editBillMeta">
                    Update existing bill items without creating a new bill.
                </p>
            </div>

            <button
                type="button"
                class="close-btn"
                data-close="editBillModal"
            >
                &times;
            </button>

        </div>

        <form method="post" id="editBillForm">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="edit_bill"
                value="1"
            >

            <input
                type="hidden"
                name="bill_id"
                id="editBillId"
                value=""
            >

            <div class="edit-bill-info" id="editBillInfo"></div>

            <div class="form-grid">

                <div class="form-group">
                    <label>Bill Discount</label>

                    <div class="input-with-icon">
                        <span>₹</span>
                        <input
                            type="number"
                            name="discount_amount"
                            id="editBillDiscount"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label>Bill Tax</label>

                    <div class="input-with-icon">
                        <span>₹</span>
                        <input
                            type="number"
                            name="tax_amount"
                            id="editBillTax"
                            min="0"
                            step="0.01"
                            value="0"
                        >
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Remarks</label>

                    <textarea
                        name="remarks"
                        id="editBillRemarks"
                        rows="3"
                        placeholder="Optional billing remarks..."
                    ></textarea>
                </div>

            </div>

            <div class="items-section">

                <div class="items-head">

                    <div>
                        <span class="section-label">
                            BILL ITEMS
                        </span>

                        <h3>
                            Edit Charges
                        </h3>
                    </div>

                    <button
                        type="button"
                        class="secondary-btn"
                        id="editAddItemBtn"
                    >
                        <i class="fa-solid fa-plus"></i>
                        Add Item
                    </button>

                </div>

                <div class="additional-info">
                    Existing items remain on the same bill. Add Room / Bed / Lab Test / Medicine / Other Service here.
                    No new bill is created.
                </div>

                <div id="editItems"></div>

            </div>

            <div class="bill-summary">

                <div class="summary-row">
                    <span>Items Subtotal</span>
                    <strong id="editSubtotalPreview">₹ 0.00</strong>
                </div>

                <div class="summary-row">
                    <span>Bill Discount</span>
                    <strong id="editDiscountPreview">- ₹ 0.00</strong>
                </div>

                <div class="summary-row">
                    <span>Bill Tax</span>
                    <strong id="editTaxPreview">+ ₹ 0.00</strong>
                </div>

                <div class="summary-total">
                    <span>Updated Payable Amount</span>
                    <strong>
                        ₹ <span id="editPayablePreview">0.00</span>
                    </strong>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                    data-close="editBillModal"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="save-btn"
                >
                    <i class="fa-solid fa-floppy-disk"></i>
                    Update Bill
                </button>

            </div>

        </form>
    </div>
</div>

<!-- =========================================================
     VIEW BILL MODAL
========================================================= -->

<div
    class="modal"
    id="viewBillModal"
>

    <div class="modal-content wide">

        <div class="modal-header">

            <div>

                <span class="modal-eyebrow">
                    Billing
                </span>

                <h2>
                    Bill Details
                </h2>

            </div>

            <button
                type="button"
                class="close-btn"
                data-close="viewBillModal"
            >
                &times;
            </button>

        </div>

        <div
            id="billDetails"
            class="details"
        ></div>

    </div>

</div>

<!-- =========================================================
     CANCEL BILL MODAL
========================================================= -->

<div
    class="modal"
    id="deleteBillModal"
>

    <div class="modal-content delete-modal">

        <div class="modal-header">

            <div>

                <span class="modal-eyebrow danger">
                    Warning
                </span>

                <h2>
                    Cancel Bill
                </h2>

            </div>

            <button
                type="button"
                class="close-btn"
                data-close="deleteBillModal"
            >
                &times;
            </button>

        </div>

        <div class="delete-content">

            <div class="warning-icon">

                <i
                    class="fa-solid fa-triangle-exclamation"
                ></i>

            </div>

            <h3>
                Cancel this bill?
            </h3>

            <p>
                This action will mark the bill as cancelled.
            </p>

            <strong id="deleteBillName"></strong>

        </div>

        <form method="post">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="delete_bill"
                value="1"
            >

            <input
                type="hidden"
                name="bill_id"
                id="deleteBillId"
            >

            <div class="modal-footer">

                <button
                    type="button"
                    class="cancel-btn"
                    data-close="deleteBillModal"
                >
                    Keep Bill
                </button>

                <button
                    type="submit"
                    class="delete-confirm-btn"
                >
                    <i class="fa-solid fa-ban"></i>
                    Cancel Bill
                </button>

            </div>

        </form>

    </div>

</div>

<script>
    window.billingCatalog = <?= json_encode(
        $item_catalog,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    window.billingPatientCharges = <?= json_encode(
        $patient_charges_by_appointment,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    window.billingEditCatalog = <?= json_encode(
        $edit_item_catalog,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;
</script>
<script src="js/billing.js"></script>

</body>
</html>