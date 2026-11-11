<?php

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$conn = db_connect();

if (!$conn) {
    die("connetion Error" . mysqli_connect_errno());
}

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
|
| $conn = hospital database connection
|
| Global database:
| hospital_management
|
*/

if (!isset($conn)) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not found.'
    ]);
    exit;
}

$type = $_GET['type'] ?? '';

try {

    /*
    |--------------------------------------------------------------------------
    | SERVICES
    |--------------------------------------------------------------------------
    */

    if ($type === 'service') {

        $sql = "
            SELECT
                service_id,
                service_name,
                service_code,
                service_fee
            FROM services
            WHERE status = 'Active'
            ORDER BY service_name ASC
        ";

        $result = $conn->query($sql);

        $items = [];

        while ($row = $result->fetch_assoc()) {

            $items[] = [
                'id' => (int)$row['service_id'],
                'name' => $row['service_name'],
                'code' => $row['service_code'],
                'price' => (float)$row['service_fee']
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | PRESCRIPTION ITEMS
    |--------------------------------------------------------------------------
    */

    if ($type === 'prescription') {

        $sql = "
            SELECT
                pi.prescription_item_id,
                pi.prescription_id,
                pi.medicine_name,
                pi.dosage,
                pi.frequency,
                pi.duration,
                p.prescription_no
            FROM prescription_items pi
            INNER JOIN prescriptions p
                ON p.prescription_id = pi.prescription_id
            WHERE p.status = 'Active'
            ORDER BY p.created_at DESC, pi.medicine_name ASC
        ";

        $result = $conn->query($sql);

        $items = [];

        while ($row = $result->fetch_assoc()) {

            /*
             * Prescription item table does not have price.
             *
             * Therefore price is initially 0.
             * If medicine price exists in your database,
             * add that column later.
             */

            $items[] = [
                'id' => (int)$row['prescription_item_id'],
                'name' => $row['medicine_name'],
                'price' => 0,
                'prescription_id' => (int)$row['prescription_id'],
                'prescription_no' => $row['prescription_no'],
                'details' =>
                    $row['dosage'] .
                    ' | ' .
                    $row['frequency'] .
                    ' | ' .
                    $row['duration']
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | BEDS
    |--------------------------------------------------------------------------
    */

    if ($type === 'bed') {

        $sql = "
            SELECT
                b.bed_id,
                b.bed_number,
                b.bed_charge,
                b.status,
                r.room_number,
                r.room_type
            FROM beds b
            INNER JOIN rooms r
                ON r.room_id = b.room_id
            WHERE b.status IN ('Available', 'Occupied', 'Reserved')
            ORDER BY r.room_number ASC, b.bed_number ASC
        ";

        $result = $conn->query($sql);

        $items = [];

        while ($row = $result->fetch_assoc()) {

            $items[] = [
                'id' => (int)$row['bed_id'],
                'name' =>
                    'Bed ' .
                    $row['bed_number'] .
                    ' - Room ' .
                    $row['room_number'],

                'price' => (float)$row['bed_charge'],

                'details' =>
                    $row['room_type'] .
                    ' | ' .
                    $row['status']
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ROOMS
    |--------------------------------------------------------------------------
    */

    if ($type === 'room') {

        $sql = "
            SELECT
                room_id,
                room_number,
                room_type,
                room_charge,
                status
            FROM rooms
            WHERE status IN ('Available', 'Maintenance', 'Inactive')
            ORDER BY room_number ASC
        ";

        $result = $conn->query($sql);

        $items = [];

        while ($row = $result->fetch_assoc()) {

            $items[] = [
                'id' => (int)$row['room_id'],

                'name' =>
                    'Room ' .
                    $row['room_number'] .
                    ' - ' .
                    $row['room_type'],

                'price' => (float)$row['room_charge'],

                'details' => $row['status']
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | LAB TESTS
    |--------------------------------------------------------------------------
    */

    if ($type === 'lab_test') {

        $sql = "
            SELECT
                lt.lab_test_id,
                lt.test_name,
                lt.test_category,
                lt.sample_type,
                lt.test_fee,
                lt.test_status
            FROM lab_tests lt
            ORDER BY lt.test_name ASC
        ";

        $result = $conn->query($sql);

        $items = [];

        while ($row = $result->fetch_assoc()) {

            $items[] = [
                'id' => (int)$row['lab_test_id'],

                'name' => $row['test_name'],

                'price' => (float)$row['test_fee'],

                'details' =>
                    $row['test_category'] .
                    ' | Sample: ' .
                    $row['sample_type']
            ];
        }

        echo json_encode([
            'success' => true,
            'items' => $items
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | INVALID TYPE
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => false,
        'message' => 'Invalid item type.'
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}