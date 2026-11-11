<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_once __DIR__ . '/hospital_context.php';


/*
|--------------------------------------------------------------------------
| MODULE
|--------------------------------------------------------------------------
*/

$module =
    (string)(
        $_GET['module']
        ?? 'availability'
    );


$allowed = [
    'availability',
    'medical_records',
    'prescriptions',
    'lab_tests',
    'admissions',
    'rooms_beds',
    'billing',
    'payments'
];


if (!in_array(
    $module,
    $allowed,
    true
)) {

    $module = 'availability';
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function mh(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function module_redirect(
    string $module,
    string $message,
    string $type = 'success'
): never {

    $_SESSION['module_flash'] = [
        'message' => $message,
        'type' => $type
    ];

    header(
        'Location: hospital_module.php?module=' .
        rawurlencode($module)
    );

    exit;
}


function fetch_all_assoc(
    mysqli $conn,
    string $sql
): array {

    $rows = [];

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

            $rows[] = $row;
        }

        mysqli_free_result($result);
    }

    return $rows;
}


$flash =
    $_SESSION['module_flash']
    ?? null;

unset(
    $_SESSION['module_flash']
);


$search =
    trim(
        (string)(
            $_GET['search']
            ?? ''
        )
    );


$status =
    trim(
        (string)(
            $_GET['status']
            ?? ''
        )
    );


/*
|--------------------------------------------------------------------------
| PAGE META
|--------------------------------------------------------------------------
*/

$meta = [

    'availability' => [
        'Doctor Availability',
        'Create and manage doctor schedules, slots and consultation modes.',
        'Doctor Availability',
        'fa-clock'
    ],

    'medical_records' => [
        'Medical Records',
        'Maintain clinical records for hospital appointments.',
        'Medical Records',
        'fa-file-medical'
    ],

    'prescriptions' => [
        'Prescriptions',
        'Create and manage prescriptions linked to medical records.',
        'Prescriptions',
        'fa-prescription-bottle-medical'
    ],

    'lab_tests' => [
        'Lab Tests',
        'Order, update and track laboratory investigations.',
        'Lab Tests',
        'fa-flask'
    ],

    'admissions' => [
        'Admissions',
        'Manage inpatient admissions and discharge workflow.',
        'Admissions',
        'fa-hospital-user'
    ],

    'rooms_beds' => [
        'Rooms & Beds',
        'Manage rooms, beds and occupancy.',
        'Rooms & Beds',
        'fa-bed'
    ],

    'billing' => [
        'Billing',
        'Create and track patient bills.',
        'Billing',
        'fa-file-invoice-dollar'
    ],

    'payments' => [
        'Payments',
        'Record and review hospital payments.',
        'Payments',
        'fa-money-bill-wave'
    ]
];


[
    $title,
    $description,
    $heading,
    $icon
] = $meta[$module];


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

try {

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $action =
            (string)(
                $_POST['action']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | DOCTOR AVAILABILITY
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'availability'
            &&
            $action === 'save_availability'
        ) {

            $id =
                (int)(
                    $_POST['availability_id']
                    ?? 0
                );

            $doctorId =
                (int)(
                    $_POST['doctor_id']
                    ?? 0
                );

            $day =
                trim(
                    (string)(
                        $_POST['day_of_week']
                        ?? ''
                    )
                );

            $start =
                trim(
                    (string)(
                        $_POST['start_time']
                        ?? ''
                    )
                );

            $end =
                trim(
                    (string)(
                        $_POST['end_time']
                        ?? ''
                    )
                );

            $slot =
                max(
                    1,
                    (int)(
                        $_POST[
                            'slot_duration_minutes'
                        ] ?? 15
                    )
                );

            $max =
                max(
                    1,
                    (int)(
                        $_POST['max_patients']
                        ?? 1
                    )
                );

            $mode =
                trim(
                    (string)(
                        $_POST[
                            'consultation_mode'
                        ] ?? 'In-Person'
                    )
                );

            $recordStatus =
                trim(
                    (string)(
                        $_POST[
                            'record_status'
                        ] ?? 'Active'
                    )
                );


            $days = [
                'Monday',
                'Tuesday',
                'Wednesday',
                'Thursday',
                'Friday',
                'Saturday',
                'Sunday'
            ];


            $modes = [
                'In-Person',
                'Video',
                'Both'
            ];


            if (
                $doctorId <= 0 ||
                !in_array(
                    $day,
                    $days,
                    true
                ) ||
                !preg_match(
                    '/^\d{2}:\d{2}$/',
                    $start
                ) ||
                !preg_match(
                    '/^\d{2}:\d{2}$/',
                    $end
                ) ||
                $start >= $end ||
                !in_array(
                    $mode,
                    $modes,
                    true
                ) ||
                !in_array(
                    $recordStatus,
                    ['Active', 'Inactive'],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Please enter valid availability details.'
                );
            }


            if ($id > 0) {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE doctor_availability

                        SET
                            doctor_id = ?,
                            day_of_week = ?,
                            start_time = ?,
                            end_time = ?,
                            slot_duration_minutes = ?,
                            max_patients = ?,
                            consultation_mode = ?,
                            status = ?

                        WHERE availability_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'isssiissi',
                    $doctorId,
                    $day,
                    $start,
                    $end,
                    $slot,
                    $max,
                    $mode,
                    $recordStatus,
                    $id
                );

            } else {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        INSERT INTO doctor_availability
                        (
                            doctor_id,
                            day_of_week,
                            start_time,
                            end_time,
                            slot_duration_minutes,
                            max_patients,
                            consultation_mode,
                            status
                        )
                        VALUES
                        (?,?,?,?,?,?,?,?)
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'isssiiss',
                    $doctorId,
                    $day,
                    $start,
                    $end,
                    $slot,
                    $max,
                    $mode,
                    $recordStatus
                );
            }


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                $id > 0
                    ? 'Doctor availability updated successfully.'
                    : 'Doctor availability added successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE AVAILABILITY
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'availability'
            &&
            $action === 'delete_availability'
        ) {

            $id =
                (int)(
                    $_POST['availability_id']
                    ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    DELETE FROM doctor_availability
                    WHERE availability_id = ?
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'i',
                $id
            );


            if (
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    mysqli_stmt_error($st)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Availability deleted successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | MEDICAL RECORDS
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'medical_records'
            &&
            $action === 'save_medical_record'
        ) {

            $id =
                (int)(
                    $_POST['medical_record_id']
                    ?? 0
                );

            $appointmentId =
                (int)(
                    $_POST['appointment_id']
                    ?? 0
                );

            $chief =
                trim(
                    (string)(
                        $_POST['chief_complaint']
                        ?? ''
                    )
                );

            $illness =
                trim(
                    (string)(
                        $_POST['present_illness']
                        ?? ''
                    )
                );

            $history =
                trim(
                    (string)(
                        $_POST[
                            'past_medical_history'
                        ] ?? ''
                    )
                );

            $family =
                trim(
                    (string)(
                        $_POST['family_history']
                        ?? ''
                    )
                );

            $allergies =
                trim(
                    (string)(
                        $_POST['allergies']
                        ?? ''
                    )
                );

            $notes =
                trim(
                    (string)(
                        $_POST['clinical_notes']
                        ?? ''
                    )
                );

            $diagnosis =
                trim(
                    (string)(
                        $_POST['diagnosis_summary']
                        ?? ''
                    )
                );

            $medicalRecordFee =
                max(
                    0,
                    (float)(
                        $_POST['medical_record_fee']
                        ?? 0
                    )
                );

            $follow =
                trim(
                    (string)(
                        $_POST['follow_up_date']
                        ?? ''
                    )
                );

            $followNotes =
                trim(
                    (string)(
                        $_POST['follow_up_notes']
                        ?? ''
                    )
                );

            $recordStatus =
                trim(
                    (string)(
                        $_POST['record_status']
                        ?? 'Open'
                    )
                );


            if (
                $appointmentId <= 0 ||
                !in_array(
                    $recordStatus,
                    [
                        'Open',
                        'Completed',
                        'Archived'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Appointment and valid record status are required.'
                );
            }


            if ($id > 0) {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE medical_records

                        SET
                            appointment_id = ?,
                            chief_complaint = ?,
                            present_illness = ?,
                            past_medical_history = ?,
                            family_history = ?,
                            allergies = ?,
                            clinical_notes = ?,
                            diagnosis_summary = ?,
                            medical_record_fee = ?,
                            follow_up_date =
                                NULLIF(?, ''),
                            follow_up_notes = ?,
                            record_status = ?

                        WHERE medical_record_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'isssssssdsssi',
                    $appointmentId,
                    $chief,
                    $illness,
                    $history,
                    $family,
                    $allergies,
                    $notes,
                    $diagnosis,
                    $medicalRecordFee,
                    $follow,
                    $followNotes,
                    $recordStatus,
                    $id
                );

            } else {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        INSERT INTO medical_records
                        (
                            appointment_id,
                            chief_complaint,
                            present_illness,
                            past_medical_history,
                            family_history,
                            allergies,
                            clinical_notes,
                            diagnosis_summary,
                            medical_record_fee,
                            follow_up_date,
                            follow_up_notes,
                            record_status
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
                            ?,
                            NULLIF(?, ''),
                            ?,
                            ?
                        )
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'isssssssdsss',
                    $appointmentId,
                    $chief,
                    $illness,
                    $history,
                    $family,
                    $allergies,
                    $notes,
                    $diagnosis,
                    $medicalRecordFee,
                    $follow,
                    $followNotes,
                    $recordStatus
                );
            }


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                $id > 0
                    ? 'Medical record updated successfully.'
                    : 'Medical record added successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE MEDICAL RECORD
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'medical_records'
            &&
            $action === 'delete_medical_record'
        ) {

            $id =
                (int)(
                    $_POST[
                        'medical_record_id'
                    ] ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    DELETE FROM medical_records
                    WHERE medical_record_id = ?
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'i',
                $id
            );


            if (
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    mysqli_stmt_error($st)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Medical record deleted successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PRESCRIPTIONS
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'prescriptions'
            &&
            $action === 'save_prescription'
        ) {

            $id =
                (int)(
                    $_POST['prescription_id']
                    ?? 0
                );

            $recordId =
                (int)(
                    $_POST['medical_record_id']
                    ?? 0
                );

            $advice =
                trim(
                    (string)(
                        $_POST['advice']
                        ?? ''
                    )
                );

            $prescriptionFee =
                max(
                    0,
                    (float)(
                        $_POST['prescription_fee']
                        ?? 0
                    )
                );

            $follow =
                trim(
                    (string)(
                        $_POST['follow_up_date']
                        ?? ''
                    )
                );

            $presStatus =
                trim(
                    (string)(
                        $_POST[
                            'prescription_status'
                        ] ?? 'Active'
                    )
                );

            $no =
                trim(
                    (string)(
                        $_POST['prescription_no']
                        ?? ''
                    )
                );

            $medicineNames =
                $_POST['medicine_name']
                ?? [];

            $dosages =
                $_POST['dosage']
                ?? [];

            $frequencies =
                $_POST['frequency']
                ?? [];

            $durations =
                $_POST['duration']
                ?? [];

            $routes =
                $_POST['route']
                ?? [];

            $instructions =
                $_POST['instructions']
                ?? [];

            $medicinePrices =
                $_POST['medicine_price']
                ?? [];


            if (
                $recordId <= 0 ||
                !in_array(
                    $presStatus,
                    [
                        'Active',
                        'Completed',
                        'Cancelled'
                    ],
                    true
                )
            ) {

                throw new RuntimeException(
                    'Medical record and valid prescription status are required.'
                );
            }


            /*
            Validate all medicine rows before changing
            the database.
            */

            $medicineRows = [];

            $rowCount =
                max(
                    count($medicineNames),
                    count($dosages),
                    count($frequencies),
                    count($durations),
                    count($routes),
                    count($instructions),
                    count($medicinePrices)
                );


            $validRoutes = [
                'Oral',
                'Injection',
                'Topical',
                'Inhalation',
                'Other'
            ];


            for (
                $i = 0;
                $i < $rowCount;
                $i++
            ) {

                $medicine =
                    trim(
                        (string)(
                            $medicineNames[$i]
                            ?? ''
                        )
                    );

                $dosageValue =
                    trim(
                        (string)(
                            $dosages[$i]
                            ?? ''
                        )
                    );

                $frequencyValue =
                    trim(
                        (string)(
                            $frequencies[$i]
                            ?? ''
                        )
                    );

                $durationValue =
                    trim(
                        (string)(
                            $durations[$i]
                            ?? ''
                        )
                    );

                $routeValue =
                    trim(
                        (string)(
                            $routes[$i]
                            ?? 'Oral'
                        )
                    );

                $instructionValue =
                    trim(
                        (string)(
                            $instructions[$i]
                            ?? ''
                        )
                    );

                $medicinePriceValue =
                    max(
                        0,
                        (float)(
                            $medicinePrices[$i]
                            ?? 0
                        )
                    );


                /*
                Completely empty rows are ignored.
                */

                if (
                    $medicine === '' &&
                    $dosageValue === '' &&
                    $frequencyValue === '' &&
                    $durationValue === '' &&
                    $instructionValue === ''
                ) {
                    continue;
                }


                if (
                    $medicine === '' ||
                    $dosageValue === '' ||
                    $frequencyValue === '' ||
                    $durationValue === '' ||
                    !in_array(
                        $routeValue,
                        $validRoutes,
                        true
                    )
                ) {

                    throw new RuntimeException(
                        'Please complete all required medicine fields.'
                    );
                }


                $medicineRows[] = [
                    'medicine_name' => $medicine,
                    'dosage' => $dosageValue,
                    'frequency' => $frequencyValue,
                    'duration' => $durationValue,
                    'route' => $routeValue,
                    'instructions' => $instructionValue,
                    'medicine_price' => $medicinePriceValue
                ];
            }


            /*
            At least one medicine is required.
            */

            if (!$medicineRows) {

                throw new RuntimeException(
                    'Please add at least one medicine.'
                );
            }


            /*
            Use a transaction so prescription and its
            medicines are saved together.
            */

            mysqli_begin_transaction(
                $hospital_conn
            );


            try {

                if ($id > 0) {

                    /*
                    Verify prescription exists.
                    */

                    $check =
                        mysqli_prepare(
                            $hospital_conn,
                            "
                            SELECT
                                prescription_id

                            FROM prescriptions

                            WHERE prescription_id = ?

                            LIMIT 1
                            "
                        );


                    if (!$check) {

                        throw new RuntimeException(
                            mysqli_error(
                                $hospital_conn
                            )
                        );
                    }


                    mysqli_stmt_bind_param(
                        $check,
                        'i',
                        $id
                    );


                    mysqli_stmt_execute(
                        $check
                    );


                    $checkResult =
                        mysqli_stmt_get_result(
                            $check
                        );


                    $existingPrescription =
                        $checkResult
                            ? mysqli_fetch_assoc(
                                $checkResult
                            )
                            : null;


                    if ($checkResult) {
                        mysqli_free_result(
                            $checkResult
                        );
                    }


                    mysqli_stmt_close(
                        $check
                    );


                    if (
                        !$existingPrescription
                    ) {

                        throw new RuntimeException(
                            'Prescription not found.'
                        );
                    }


                    /*
                    Update main prescription.
                    */

                    $st =
                        mysqli_prepare(
                            $hospital_conn,
                            "
                            UPDATE prescriptions

                            SET
                                medical_record_id = ?,
                                advice = ?,
                                prescription_fee = ?,
                                follow_up_date =
                                    NULLIF(?, ''),
                                status = ?

                            WHERE prescription_id = ?
                            "
                        );


                    if (!$st) {

                        throw new RuntimeException(
                            mysqli_error(
                                $hospital_conn
                            )
                        );
                    }


                    mysqli_stmt_bind_param(
                        $st,
                        'isdssi',
                        $recordId,
                        $advice,
                        $prescriptionFee,
                        $follow,
                        $presStatus,
                        $id
                    );


                    if (
                        !mysqli_stmt_execute($st)
                    ) {

                        throw new RuntimeException(
                            mysqli_stmt_error($st)
                        );
                    }


                    mysqli_stmt_close($st);


                    /*
                    Existing prescription items are
                    replaced with the submitted list.
                    */

                    $deleteItems =
                        mysqli_prepare(
                            $hospital_conn,
                            "
                            DELETE FROM prescription_items

                            WHERE prescription_id = ?
                            "
                        );


                    if (!$deleteItems) {

                        throw new RuntimeException(
                            mysqli_error(
                                $hospital_conn
                            )
                        );
                    }


                    mysqli_stmt_bind_param(
                        $deleteItems,
                        'i',
                        $id
                    );


                    if (
                        !mysqli_stmt_execute(
                            $deleteItems
                        )
                    ) {

                        throw new RuntimeException(
                            mysqli_stmt_error(
                                $deleteItems
                            )
                        );
                    }


                    mysqli_stmt_close(
                        $deleteItems
                    );


                } else {

                    /*
                    Generate prescription number if blank.
                    */

                    if ($no === '') {

                        $no =
                            'RX' .
                            date('YmdHis') .
                            random_int(
                                100,
                                999
                            );
                    }


                    /*
                    Insert main prescription.
                    */

                    $st =
                        mysqli_prepare(
                            $hospital_conn,
                            "
                            INSERT INTO prescriptions
                            (
                                medical_record_id,
                                prescription_no,
                                advice,
                                prescription_fee,
                                follow_up_date,
                                status
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                NULLIF(?, ''),
                                ?
                            )
                            "
                        );


                    if (!$st) {

                        throw new RuntimeException(
                            mysqli_error(
                                $hospital_conn
                            )
                        );
                    }


                    mysqli_stmt_bind_param(
                        $st,
                        'issdss',
                        $recordId,
                        $no,
                        $advice,
                        $prescriptionFee,
                        $follow,
                        $presStatus
                    );


                    if (
                        !mysqli_stmt_execute($st)
                    ) {

                        throw new RuntimeException(
                            mysqli_stmt_error($st)
                        );
                    }


                    $id =
                        mysqli_insert_id(
                            $hospital_conn
                        );


                    mysqli_stmt_close($st);
                }


                /*
                Insert medicine items.
                */

                $itemStmt =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        INSERT INTO prescription_items
                        (
                            prescription_id,
                            medicine_name,
                            dosage,
                            frequency,
                            duration,
                            route,
                            instructions,
                            medicine_price
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
                        )
                        "
                    );


                if (!$itemStmt) {

                    throw new RuntimeException(
                        mysqli_error(
                            $hospital_conn
                        )
                    );
                }


                foreach (
                    $medicineRows as $medicineRow
                ) {

                    mysqli_stmt_bind_param(
                        $itemStmt,
                        'issssssd',
                        $id,
                        $medicineRow[
                            'medicine_name'
                        ],
                        $medicineRow[
                            'dosage'
                        ],
                        $medicineRow[
                            'frequency'
                        ],
                        $medicineRow[
                            'duration'
                        ],
                        $medicineRow[
                            'route'
                        ],
                        $medicineRow[
                            'instructions'
                        ],
                        $medicineRow[
                            'medicine_price'
                        ]
                    );


                    if (
                        !mysqli_stmt_execute(
                            $itemStmt
                        )
                    ) {

                        throw new RuntimeException(
                            mysqli_stmt_error(
                                $itemStmt
                            )
                        );
                    }
                }


                mysqli_stmt_close(
                    $itemStmt
                );


                mysqli_commit(
                    $hospital_conn
                );

            } catch (Throwable $e) {

                mysqli_rollback(
                    $hospital_conn
                );

                throw $e;
            }


            module_redirect(
                $module,
                $id > 0
                    ? 'Prescription updated successfully.'
                    : 'Prescription added successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE PRESCRIPTION
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'prescriptions'
            &&
            $action === 'delete_prescription'
        ) {

            $id =
                (int)(
                    $_POST[
                        'prescription_id'
                    ] ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    DELETE FROM prescriptions
                    WHERE prescription_id = ?
                    "
                );


            if (!$st) {

                throw new RuntimeException(
                    mysqli_error(
                        $hospital_conn
                    )
                );
            }


            mysqli_stmt_bind_param(
                $st,
                'i',
                $id
            );


            if (
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    mysqli_stmt_error($st)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Prescription deleted successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LAB TESTS
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'lab_tests'
            &&
            $action === 'save_lab_test'
        ) {

            $id =
                (int)(
                    $_POST['lab_test_id']
                    ?? 0
                );

            $recordId =
                (int)(
                    $_POST['medical_record_id']
                    ?? 0
                );

            $name =
                trim(
                    (string)(
                        $_POST['test_name']
                        ?? ''
                    )
                );

            $category =
                trim(
                    (string)(
                        $_POST['test_category']
                        ?? ''
                    )
                );

            $testFee =
                max(
                    0,
                    (float)(
                        $_POST['test_fee']
                        ?? 0
                    )
                );

            $doctorId =
                (int)(
                    $_POST[
                        'ordered_by_doctor_id'
                    ] ?? 0
                );

            $sample =
                trim(
                    (string)(
                        $_POST['sample_type']
                        ?? 'Blood'
                    )
                );

            $result =
                trim(
                    (string)(
                        $_POST['test_result']
                        ?? ''
                    )
                );

            $normal =
                trim(
                    (string)(
                        $_POST['normal_range']
                        ?? ''
                    )
                );

            $testStatus =
                trim(
                    (string)(
                        $_POST['test_status']
                        ?? 'Ordered'
                    )
                );

            $testDate =
                trim(
                    (string)(
                        $_POST['test_date']
                        ?? ''
                    )
                );

            $remarks =
                trim(
                    (string)(
                        $_POST['remarks']
                        ?? ''
                    )
                );


            $samples = [
                'Blood',
                'Urine',
                'Stool',
                'Saliva',
                'Sputum',
                'Other'
            ];


            $statuses = [
                'Ordered',
                'Sample Collected',
                'Processing',
                'Completed',
                'Cancelled'
            ];


            if (
                $recordId <= 0 ||
                $name === '' ||
                $doctorId <= 0 ||
                !in_array(
                    $sample,
                    $samples,
                    true
                ) ||
                !in_array(
                    $testStatus,
                    $statuses,
                    true
                )
            ) {

                throw new RuntimeException(
                    'Medical record, test name, doctor and valid status are required.'
                );
            }


            if ($id > 0) {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE lab_tests

                        SET
                            medical_record_id = ?,
                            test_name = ?,
                            test_category = ?,
                            test_fee = ?,
                            ordered_by_doctor_id = ?,
                            sample_type = ?,
                            test_result = ?,
                            normal_range = ?,
                            test_status = ?,
                            test_date =
                                NULLIF(?, ''),
                            remarks = ?,
                            completed_at =
                                IF(
                                    ? = 'Completed',
                                    COALESCE(
                                        completed_at,
                                        NOW()
                                    ),
                                    NULL
                                )

                        WHERE lab_test_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'issdisssssssi',
                    $recordId,
                    $name,
                    $category,
                    $testFee,
                    $doctorId,
                    $sample,
                    $result,
                    $normal,
                    $testStatus,
                    $testDate,
                    $remarks,
                    $testStatus,
                    $id
                );

            } else {

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        INSERT INTO lab_tests
                        (
                            medical_record_id,
                            test_name,
                            test_category,
                            test_fee,
                            ordered_by_doctor_id,
                            sample_type,
                            test_result,
                            normal_range,
                            test_status,
                            test_date,
                            remarks,
                            completed_at
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
                            ?,
                            NULLIF(?, ''),
                            ?,
                            IF(
                                ? = 'Completed',
                                NOW(),
                                NULL
                            )
                        )
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'issdisssssss',
                    $recordId,
                    $name,
                    $category,
                    $testFee,
                    $doctorId,
                    $sample,
                    $result,
                    $normal,
                    $testStatus,
                    $testDate,
                    $remarks,
                    $testStatus
                );
            }


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                $id > 0
                    ? 'Lab test updated successfully.'
                    : 'Lab test added successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE LAB TEST
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'lab_tests'
            &&
            $action === 'delete_lab_test'
        ) {

            $id =
                (int)(
                    $_POST['lab_test_id']
                    ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    DELETE FROM lab_tests
                    WHERE lab_test_id = ?
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'i',
                $id
            );


            if (
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    mysqli_stmt_error($st)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Lab test deleted successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ADMISSION - IMPORTANT NEW CODE
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'admissions'
            &&
            $action === 'add_admission'
        ) {

            $mappingId =
                (int)(
                    $_POST['mapping_id']
                    ?? 0
                );

            $appointmentId =
                (int)(
                    $_POST['appointment_id']
                    ?? 0
                );

            $roomId =
                (int)(
                    $_POST['room_id']
                    ?? 0
                );

            $bedId =
                (int)(
                    $_POST['bed_id']
                    ?? 0
                );

            $doctorId =
                (int)(
                    $_POST[
                        'admitted_by_doctor_id'
                    ] ?? 0
                );

            $admissionDate =
                trim(
                    (string)(
                        $_POST['admission_date']
                        ?? ''
                    )
                );

            $expected =
                trim(
                    (string)(
                        $_POST[
                            'expected_discharge_date'
                        ] ?? ''
                    )
                );

            $reason =
                trim(
                    (string)(
                        $_POST['admission_reason']
                        ?? ''
                    )
                );

            $admissionFee =
                max(
                    0,
                    (float)(
                        $_POST['admission_fee']
                        ?? 0
                    )
                );


            if (
                $mappingId <= 0 ||
                $bedId <= 0 ||
                $doctorId <= 0 ||
                $roomId <= 0
            ) {

                throw new RuntimeException(
                    'Patient, room, bed and admitting doctor are required.'
                );
            }


            /*
            Admission date.
            */

            if ($admissionDate === '') {

                $admissionDate =
                    date('Y-m-d H:i:s');

            } else {

                $admissionDate =
                    str_replace(
                        'T',
                        ' ',
                        $admissionDate
                    );


                if (
                    strlen($admissionDate)
                    === 16
                ) {

                    $admissionDate .= ':00';
                }


                $ts =
                    strtotime(
                        $admissionDate
                    );


                if ($ts === false) {

                    throw new RuntimeException(
                        'Invalid admission date.'
                    );
                }


                $admissionDate =
                    date(
                        'Y-m-d H:i:s',
                        $ts
                    );
            }


            /*
            Verify patient belongs
            to current hospital.
            */

            $st =
                mysqli_prepare(
                    $conn,
                    "
                    SELECT mapping_id
                    FROM patient_hospital_mapping

                    WHERE mapping_id = ?
                      AND hospital_id = ?

                    LIMIT 1
                    "
                );


            if (!$st) {

                throw new RuntimeException(
                    mysqli_error($conn)
                );
            }


            mysqli_stmt_bind_param(
                $st,
                'ii',
                $mappingId,
                $hospital_id
            );


            mysqli_stmt_execute($st);


            $res =
                mysqli_stmt_get_result($st);


            $patientExists =
                $res
                    ? mysqli_fetch_assoc($res)
                    : null;


            if ($res) {
                mysqli_free_result($res);
            }


            mysqli_stmt_close($st);


            if (!$patientExists) {

                throw new RuntimeException(
                    'Selected patient is not registered in this hospital.'
                );
            }


            /*
            Hospital DB transaction.
            */

            mysqli_begin_transaction(
                $hospital_conn
            );


            try {

                /*
                ------------------------------------------------------------
                SELECTED APPOINTMENT VALIDATION
                ------------------------------------------------------------
                */

                if ($appointmentId > 0) {

                    $st =
                        mysqli_prepare(
                            $hospital_conn,
                            "
                            SELECT
                                appointment_id,
                                mapping_id,
                                appointment_date,
                                appointment_status

                            FROM appointments

                            WHERE appointment_id = ?
                              AND mapping_id = ?

                            LIMIT 1
                            "
                        );


                    if (!$st) {

                        throw new RuntimeException(
                            mysqli_error(
                                $hospital_conn
                            )
                        );
                    }


                    mysqli_stmt_bind_param(
                        $st,
                        'ii',
                        $appointmentId,
                        $mappingId
                    );


                    mysqli_stmt_execute($st);


                    $res =
                        mysqli_stmt_get_result(
                            $st
                        );


                    $appointment =
                        $res
                            ? mysqli_fetch_assoc($res)
                            : null;


                    if ($res) {
                        mysqli_free_result($res);
                    }


                    mysqli_stmt_close($st);


                    if (!$appointment) {

                        throw new RuntimeException(
                            'Selected appointment does not belong to this patient.'
                        );
                    }


                    if (
                        in_array(
                            $appointment[
                                'appointment_status'
                            ],
                            [
                                'Cancelled',
                                'No-Show'
                            ],
                            true
                        )
                    ) {

                        throw new RuntimeException(
                            'Cancelled or No-Show appointment cannot be linked to admission.'
                        );
                    }


                    /*
                    Past appointment is NOT allowed.
                    */

                    if (
                        $appointment[
                            'appointment_date'
                        ] < date('Y-m-d')
                    ) {

                        throw new RuntimeException(
                            'Past appointments cannot be selected for a new admission.'
                        );
                    }

                } else {

                    $appointmentId = 0;
                }


                /*
                ------------------------------------------------------------
                BED + ROOM TYPE VALIDATION
                ------------------------------------------------------------
                */

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        SELECT
                            b.bed_id,
                            b.status,
                            r.room_id,
                            r.room_number,
                            r.room_type,
                            r.status AS room_status

                        FROM beds b

                        JOIN rooms r
                            ON r.room_id = b.room_id

                        WHERE b.bed_id = ?

                        FOR UPDATE
                        "
                    );


                if (!$st) {

                    throw new RuntimeException(
                        mysqli_error(
                            $hospital_conn
                        )
                    );
                }


                mysqli_stmt_bind_param(
                    $st,
                    'i',
                    $bedId
                );


                mysqli_stmt_execute($st);


                $res =
                    mysqli_stmt_get_result(
                        $st
                    );


                $bed =
                    $res
                        ? mysqli_fetch_assoc($res)
                        : null;


                if ($res) {
                    mysqli_free_result($res);
                }


                mysqli_stmt_close($st);


                if (!$bed) {

                    throw new RuntimeException(
                        'Selected bed was not found.'
                    );
                }


                if (
                    $bed['status'] !==
                    'Available'
                ) {

                    throw new RuntimeException(
                        'Selected bed is no longer available.'
                    );
                }


                if (
                    $bed['room_status'] !==
                    'Available'
                ) {

                    throw new RuntimeException(
                        'Selected room is not available.'
                    );
                }


                if (
                    (int)$bed['room_id'] !==
                    $roomId
                ) {

                    throw new RuntimeException(
                        'Selected bed does not belong to the selected room.'
                    );
                }


                /*
                ------------------------------------------------------------
                CREATE ADMISSION
                ------------------------------------------------------------
                */

                $admissionNo =
                    'ADM' .
                    date('YmdHis') .
                    random_int(
                        100,
                        999
                    );


                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        INSERT INTO admissions
                        (
                            mapping_id,
                            appointment_id,
                            bed_id,
                            admitted_by_doctor_id,
                            admission_no,
                            admission_date,
                            expected_discharge_date,
                            admission_reason,
                            admission_fee,
                            admission_status
                        )
                        VALUES
                        (
                            ?,
                            NULLIF(?, 0),
                            ?,
                            ?,
                            ?,
                            ?,
                            NULLIF(?, ''),
                            ?,
                            ?,
                            'Admitted'
                        )
                        "
                    );


                if (!$st) {

                    throw new RuntimeException(
                        mysqli_error(
                            $hospital_conn
                        )
                    );
                }


                mysqli_stmt_bind_param(
                    $st,
                    'iiiissss',
                    $mappingId,
                    $appointmentId,
                    $bedId,
                    $doctorId,
                    $admissionNo,
                    $admissionDate,
                    $expected,
                    $reason
                );


                if (
                    !mysqli_stmt_execute($st)
                ) {

                    throw new RuntimeException(
                        mysqli_stmt_error($st)
                    );
                }


                mysqli_stmt_close($st);


                /*
                Mark bed occupied.
                */

                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE beds

                        SET status = 'Occupied'

                        WHERE bed_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'i',
                    $bedId
                );


                if (
                    !mysqli_stmt_execute($st)
                ) {

                    throw new RuntimeException(
                        mysqli_stmt_error($st)
                    );
                }


                mysqli_stmt_close($st);


                mysqli_commit(
                    $hospital_conn
                );

            } catch (Throwable $e) {

                mysqli_rollback(
                    $hospital_conn
                );

                throw $e;
            }


            module_redirect(
                $module,
                'Admission created successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EDIT ADMISSION
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'admissions'
            &&
            $action === 'edit_admission'
        ) {

            $id = (int)($_POST['admission_id'] ?? 0);
            $mappingId = (int)($_POST['mapping_id'] ?? 0);
            $appointmentId = (int)($_POST['appointment_id'] ?? 0);
            $bedId = (int)($_POST['bed_id'] ?? 0);
            $doctorId = (int)($_POST['admitted_by_doctor_id'] ?? 0);
            $admissionDate = trim((string)($_POST['admission_date'] ?? ''));
            $expected = trim((string)($_POST['expected_discharge_date'] ?? ''));
            $reason = trim((string)($_POST['admission_reason'] ?? ''));
            $admissionFee = max(0, (float)($_POST['admission_fee'] ?? 0));
            $newStatus = trim((string)($_POST['admission_status'] ?? 'Admitted'));

            if ($id <= 0 || $mappingId <= 0 || $bedId <= 0 || $doctorId <= 0) {
                throw new RuntimeException('Invalid admission details.');
            }

            if (!in_array($newStatus, ['Admitted', 'Discharged', 'Transferred', 'Cancelled'], true)) {
                throw new RuntimeException('Invalid admission status.');
            }

            if ($admissionDate === '') {
                $admissionDate = date('Y-m-d H:i:s');
            } else {
                $admissionDate = str_replace('T', ' ', $admissionDate);
                if (strlen($admissionDate) === 16) $admissionDate .= ':00';
                $ts = strtotime($admissionDate);
                if ($ts === false) throw new RuntimeException('Invalid admission date.');
                $admissionDate = date('Y-m-d H:i:s', $ts);
            }

            mysqli_begin_transaction($hospital_conn);

            try {
                $st = mysqli_prepare($hospital_conn,
                    "SELECT admission_id, bed_id, admission_status
                     FROM admissions WHERE admission_id = ? FOR UPDATE");
                if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st);
                $res = mysqli_stmt_get_result($st);
                $old = $res ? mysqli_fetch_assoc($res) : null;
                if ($res) mysqli_free_result($res);
                mysqli_stmt_close($st);
                if (!$old) throw new RuntimeException('Admission not found.');

                $st = mysqli_prepare($conn,
                    "SELECT mapping_id FROM patient_hospital_mapping
                     WHERE mapping_id = ? AND hospital_id = ? LIMIT 1");
                if (!$st) throw new RuntimeException(mysqli_error($conn));
                mysqli_stmt_bind_param($st, 'ii', $mappingId, $hospital_id);
                mysqli_stmt_execute($st);
                $res = mysqli_stmt_get_result($st);
                $okPatient = $res && mysqli_num_rows($res) > 0;
                if ($res) mysqli_free_result($res);
                mysqli_stmt_close($st);
                if (!$okPatient) throw new RuntimeException('Selected patient is not registered in this hospital.');

                if ($appointmentId > 0) {
                    $st = mysqli_prepare($hospital_conn,
                        "SELECT appointment_id FROM appointments
                         WHERE appointment_id = ? AND mapping_id = ? LIMIT 1");
                    if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                    mysqli_stmt_bind_param($st, 'ii', $appointmentId, $mappingId);
                    mysqli_stmt_execute($st);
                    $res = mysqli_stmt_get_result($st);
                    $okAppt = $res && mysqli_num_rows($res) > 0;
                    if ($res) mysqli_free_result($res);
                    mysqli_stmt_close($st);
                    if (!$okAppt) throw new RuntimeException('Selected appointment does not belong to this patient.');
                }

                if ($newStatus === 'Admitted') {
                    $st = mysqli_prepare($hospital_conn,
                        "SELECT bed_id, status FROM beds WHERE bed_id = ? FOR UPDATE");
                    if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                    mysqli_stmt_bind_param($st, 'i', $bedId);
                    mysqli_stmt_execute($st);
                    $res = mysqli_stmt_get_result($st);
                    $bed = $res ? mysqli_fetch_assoc($res) : null;
                    if ($res) mysqli_free_result($res);
                    mysqli_stmt_close($st);
                    if (!$bed) throw new RuntimeException('Selected bed not found.');
                    if ((int)$old['bed_id'] !== $bedId && ($bed['status'] ?? '') !== 'Available') {
                        throw new RuntimeException('Selected bed is not available.');
                    }
                }

                $actualDischarge = $newStatus === 'Discharged' ? 'NOW()' : 'NULL';
                $sql = "UPDATE admissions SET
                            mapping_id = ?, appointment_id = NULLIF(?,0), bed_id = ?,
                            admitted_by_doctor_id = ?, admission_date = ?,
                            expected_discharge_date = NULLIF(?,''), admission_reason = ?,
                            admission_fee = ?,
                            admission_status = ?,
                            actual_discharge_date = {$actualDischarge}
                        WHERE admission_id = ?";
                $st = mysqli_prepare($hospital_conn, $sql);
                if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                mysqli_stmt_bind_param($st, 'iiiisssdsi',
                    $mappingId, $appointmentId, $bedId, $doctorId, $admissionDate,
                    $expected, $reason, $admissionFee, $newStatus, $id);
                if (!mysqli_stmt_execute($st)) throw new RuntimeException(mysqli_stmt_error($st));
                mysqli_stmt_close($st);

                if ((int)$old['bed_id'] !== $bedId || $newStatus !== 'Admitted') {
                    $oldBedId = (int)$old['bed_id'];
                    $st = mysqli_prepare($hospital_conn, "UPDATE beds SET status = 'Available' WHERE bed_id = ?");
                    mysqli_stmt_bind_param($st, 'i', $oldBedId);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                }

                if ($newStatus === 'Admitted') {
                    $st = mysqli_prepare($hospital_conn, "UPDATE beds SET status = 'Occupied' WHERE bed_id = ?");
                    mysqli_stmt_bind_param($st, 'i', $bedId);
                    if (!mysqli_stmt_execute($st)) throw new RuntimeException(mysqli_stmt_error($st));
                    mysqli_stmt_close($st);
                }

                mysqli_commit($hospital_conn);
            } catch (Throwable $e) {
                mysqli_rollback($hospital_conn);
                throw $e;
            }

            module_redirect($module, 'Admission updated successfully.');
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE ADMISSION
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'admissions'
            &&
            $action === 'delete_admission'
        ) {
            $id = (int)($_POST['admission_id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Invalid admission.');

            mysqli_begin_transaction($hospital_conn);
            try {
                $st = mysqli_prepare($hospital_conn,
                    "SELECT bed_id FROM admissions WHERE admission_id = ? FOR UPDATE");
                if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                mysqli_stmt_bind_param($st, 'i', $id);
                mysqli_stmt_execute($st);
                $res = mysqli_stmt_get_result($st);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if ($res) mysqli_free_result($res);
                mysqli_stmt_close($st);
                if (!$row) throw new RuntimeException('Admission not found.');

                $bedId = (int)$row['bed_id'];
                $st = mysqli_prepare($hospital_conn, "DELETE FROM admissions WHERE admission_id = ?");
                if (!$st) throw new RuntimeException(mysqli_error($hospital_conn));
                mysqli_stmt_bind_param($st, 'i', $id);
                if (!mysqli_stmt_execute($st)) throw new RuntimeException(mysqli_stmt_error($st));
                mysqli_stmt_close($st);

                $st = mysqli_prepare($hospital_conn, "UPDATE beds SET status = 'Available' WHERE bed_id = ? AND status = 'Occupied'");
                mysqli_stmt_bind_param($st, 'i', $bedId);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);

                mysqli_commit($hospital_conn);
            } catch (Throwable $e) {
                mysqli_rollback($hospital_conn);
                throw $e;
            }

            module_redirect($module, 'Admission deleted successfully.');
        }


        /*
        |--------------------------------------------------------------------------
        | DISCHARGE
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'admissions'
            &&
            $action === 'discharge'
        ) {

            $id =
                (int)(
                    $_POST['admission_id']
                    ?? 0
                );

            $summary =
                trim(
                    (string)(
                        $_POST[
                            'discharge_summary'
                        ] ?? ''
                    )
                );


            mysqli_begin_transaction(
                $hospital_conn
            );


            try {

                $bedId = 0;


                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        SELECT bed_id

                        FROM admissions

                        WHERE admission_id = ?
                          AND admission_status =
                              'Admitted'

                        FOR UPDATE
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'i',
                    $id
                );


                mysqli_stmt_execute($st);


                $res =
                    mysqli_stmt_get_result($st);


                if (
                    $res &&
                    (
                        $row =
                            mysqli_fetch_assoc($res)
                    )
                ) {

                    $bedId =
                        (int)$row['bed_id'];
                }


                if ($res) {
                    mysqli_free_result($res);
                }


                mysqli_stmt_close($st);


                if ($bedId <= 0) {

                    throw new RuntimeException(
                        'Active admission not found.'
                    );
                }


                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE admissions

                        SET
                            admission_status =
                                'Discharged',
                            actual_discharge_date =
                                NOW(),
                            discharge_summary = ?

                        WHERE admission_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'si',
                    $summary,
                    $id
                );


                if (
                    !mysqli_stmt_execute($st)
                ) {

                    throw new RuntimeException(
                        mysqli_stmt_error($st)
                    );
                }


                mysqli_stmt_close($st);


                $st =
                    mysqli_prepare(
                        $hospital_conn,
                        "
                        UPDATE beds

                        SET status = 'Available'

                        WHERE bed_id = ?
                        "
                    );


                mysqli_stmt_bind_param(
                    $st,
                    'i',
                    $bedId
                );


                mysqli_stmt_execute($st);

                mysqli_stmt_close($st);


                mysqli_commit(
                    $hospital_conn
                );

            } catch (Throwable $e) {

                mysqli_rollback(
                    $hospital_conn
                );

                throw $e;
            }


            module_redirect(
                $module,
                'Patient discharged successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ROOMS / BEDS
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'rooms_beds'
            &&
            $action === 'add_room'
        ) {

            $room =
                trim(
                    (string)(
                        $_POST['room_number']
                        ?? ''
                    )
                );

            $roomType =
                trim(
                    (string)(
                        $_POST['room_type']
                        ?? 'General Ward'
                    )
                );

            $floor =
                trim(
                    (string)(
                        $_POST['floor_number']
                        ?? ''
                    )
                );

            $charge =
                (float)(
                    $_POST['room_charge']
                    ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    INSERT INTO rooms
                    (
                        room_number,
                        room_type,
                        floor_number,
                        room_charge
                    )
                    VALUES
                    (?,?,?,?)
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'sssd',
                $room,
                $roomType,
                $floor,
                $charge
            );


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Room added successfully.'
            );
        }


        if (
            $module === 'rooms_beds'
            &&
            $action === 'add_bed'
        ) {

            $roomId =
                (int)(
                    $_POST['room_id']
                    ?? 0
                );

            $bed =
                trim(
                    (string)(
                        $_POST['bed_number']
                        ?? ''
                    )
                );

            $charge =
                (float)(
                    $_POST['bed_charge']
                    ?? 0
                );


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    INSERT INTO beds
                    (
                        room_id,
                        bed_number,
                        bed_charge
                    )
                    VALUES
                    (?,?,?)
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'isd',
                $roomId,
                $bed,
                $charge
            );


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Bed added successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BILLING
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'billing'
            &&
            $action === 'add_bill'
        ) {

            $appointmentId =
                (int)(
                    $_POST[
                        'appointment_id'
                    ] ?? 0
                );

            $total =
                (float)(
                    $_POST[
                        'total_amount'
                    ] ?? 0
                );

            $discount =
                (float)(
                    $_POST[
                        'discount_amount'
                    ] ?? 0
                );

            $tax =
                (float)(
                    $_POST['tax_amount']
                    ?? 0
                );

            $remarks =
                trim(
                    (string)(
                        $_POST['remarks']
                        ?? ''
                    )
                );


            $payable =
                max(
                    0,
                    $total -
                    $discount +
                    $tax
                );


            $no =
                'BILL' .
                date('YmdHis') .
                random_int(100, 999);


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    INSERT INTO billing
                    (
                        appointment_id,
                        bill_no,
                        total_amount,
                        discount_amount,
                        tax_amount,
                        payable_amount,
                        remarks
                    )
                    VALUES
                    (?,?,?,?,?,?,?)
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'isdddds',
                $appointmentId,
                $no,
                $total,
                $discount,
                $tax,
                $payable,
                $remarks
            );


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            module_redirect(
                $module,
                'Bill created successfully.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENTS
        |--------------------------------------------------------------------------
        */

        if (
            $module === 'payments'
            &&
            $action === 'add_payment'
        ) {

            $billId =
                (int)(
                    $_POST['bill_id']
                    ?? 0
                );

            $method =
                trim(
                    (string)(
                        $_POST[
                            'payment_method'
                        ] ?? 'Cash'
                    )
                );

            $amount =
                (float)(
                    $_POST['amount']
                    ?? 0
                );

            $ref =
                trim(
                    (string)(
                        $_POST[
                            'transaction_reference'
                        ] ?? ''
                    )
                );

            $remarks =
                trim(
                    (string)(
                        $_POST['remarks']
                        ?? ''
                    )
                );


            $no =
                'PAY' .
                date('YmdHis') .
                random_int(100, 999);


            $st =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    INSERT INTO payments
                    (
                        bill_id,
                        payment_no,
                        payment_method,
                        amount,
                        transaction_reference,
                        payment_status,
                        remarks
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Success',
                        ?
                    )
                    "
                );


            mysqli_stmt_bind_param(
                $st,
                'isdsss',
                $billId,
                $no,
                $method,
                $amount,
                $ref,
                $remarks
            );


            if (
                !$st ||
                !mysqli_stmt_execute($st)
            ) {

                throw new RuntimeException(
                    $st
                        ? mysqli_stmt_error($st)
                        : mysqli_error($hospital_conn)
                );
            }


            mysqli_stmt_close($st);


            /*
            Update bill status.
            */

            $sum =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    SELECT
                        COALESCE(
                            SUM(p.amount),
                            0
                        ),
                        b.payable_amount

                    FROM payments p

                    JOIN billing b
                        ON b.bill_id =
                           p.bill_id

                    WHERE p.bill_id = ?
                      AND p.payment_status =
                          'Success'

                    GROUP BY
                        b.payable_amount
                    "
                );


            mysqli_stmt_bind_param(
                $sum,
                'i',
                $billId
            );


            mysqli_stmt_execute($sum);


            mysqli_stmt_bind_result(
                $sum,
                $paid,
                $payable
            );


            mysqli_stmt_fetch($sum);

            mysqli_stmt_close($sum);


            $newStatus =
                $paid >= $payable
                    ? 'Paid'
                    : (
                        $paid > 0
                            ? 'Partially Paid'
                            : 'Pending'
                    );


            $up =
                mysqli_prepare(
                    $hospital_conn,
                    "
                    UPDATE billing

                    SET bill_status = ?

                    WHERE bill_id = ?
                    "
                );


            mysqli_stmt_bind_param(
                $up,
                'si',
                $newStatus,
                $billId
            );


            mysqli_stmt_execute($up);

            mysqli_stmt_close($up);


            module_redirect(
                $module,
                'Payment recorded successfully.'
            );
        }
    }


} catch (Throwable $e) {

    if (
        isset($hospital_conn) &&
        $hospital_conn instanceof mysqli
    ) {

        @mysqli_rollback(
            $hospital_conn
        );
    }


    $_SESSION['module_flash'] = [
        'message' =>
            'Operation failed: ' .
            $e->getMessage(),

        'type' => 'error'
    ];


    header(
        'Location: hospital_module.php?module=' .
        rawurlencode($module)
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$doctors = [];
$appointments = [];
$admissionAppointments = [];
$records = [];
$rooms = [];
$beds = [];
$bills = [];
$centralPatients = [];
$patientByMapping = [];
$medicineOptions = [];


/*
|--------------------------------------------------------------------------
| DOCTORS
|--------------------------------------------------------------------------
*/

$doctors =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            doctor_id,
            doctor_name
        FROM doctors
        ORDER BY doctor_name
        "
    );


/*
|--------------------------------------------------------------------------
| ALL APPOINTMENTS
|--------------------------------------------------------------------------
|
| Used by medical records/billing etc.
|--------------------------------------------------------------------------
*/

$appointments =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            a.appointment_id,
            a.mapping_id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.appointment_status,
            d.doctor_name

        FROM appointments a

        JOIN doctors d
            ON d.doctor_id =
               a.doctor_id

        ORDER BY
            a.appointment_date DESC,
            a.appointment_time DESC

        LIMIT 500
        "
    );


/*
|--------------------------------------------------------------------------
| ADMISSION APPOINTMENTS
|--------------------------------------------------------------------------
|
| ONLY TODAY + FUTURE
| Cancelled / No-Show excluded
|--------------------------------------------------------------------------
*/

$admissionAppointments =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            a.appointment_id,
            a.mapping_id,
            a.appointment_no,
            a.appointment_date,
            a.appointment_time,
            a.appointment_status,
            d.doctor_name

        FROM appointments a

        JOIN doctors d
            ON d.doctor_id =
               a.doctor_id

        WHERE a.appointment_date >=
              CURDATE()

          AND a.appointment_status NOT IN
              (
                  'Cancelled',
                  'No-Show'
              )

        ORDER BY
            a.appointment_date ASC,
            a.appointment_time ASC

        LIMIT 500
        "
    );


/*
|--------------------------------------------------------------------------
| MEDICAL RECORDS
|--------------------------------------------------------------------------
*/

$records =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            mr.medical_record_id,
            mr.appointment_id,
            a.mapping_id,
            mr.record_status,
            a.appointment_no,
            a.appointment_date,
            d.doctor_name

        FROM medical_records mr

        JOIN appointments a
            ON a.appointment_id =
               mr.appointment_id

        JOIN doctors d
            ON d.doctor_id =
               a.doctor_id

        ORDER BY
            mr.created_at DESC

        LIMIT 500
        "
    );


    /*
    Add patient information to medical-record options.
    */

    foreach ($records as &$recordRow) {

        $mappingId =
            (int)(
                $recordRow['mapping_id']
                ?? 0
            );

        $patient =
            $patientByMapping[$mappingId]
            ?? null;

        $recordRow['patient_name'] =
            $patient['patient_name']
            ?? 'Unknown Patient';

        $recordRow['hospital_patient_code'] =
            $patient['hospital_patient_code']
            ?? '';
    }

    unset($recordRow);


/*
|--------------------------------------------------------------------------
| ROOMS
|--------------------------------------------------------------------------
*/

$rooms =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            room_id,
            room_number,
            room_type,
            floor_number,
            status

        FROM rooms

        ORDER BY
            room_type,
            room_number
        "
    );


/*
|--------------------------------------------------------------------------
| BEDS
|--------------------------------------------------------------------------
|
| Room type included.
|--------------------------------------------------------------------------
*/

$beds =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            b.bed_id,
            b.room_id,
            b.bed_number,
            b.bed_charge,
            b.status,

            r.room_number,
            r.room_type,
            r.floor_number,
            r.status AS room_status

        FROM beds b

        JOIN rooms r
            ON r.room_id =
               b.room_id

        WHERE r.status =
              'Available'

        ORDER BY
            r.room_type,
            r.room_number,
            b.bed_number
        "
    );


/*
|--------------------------------------------------------------------------
| BILLS
|--------------------------------------------------------------------------
*/

$bills =
    fetch_all_assoc(
        $hospital_conn,
        "
        SELECT
            bill_id,
            bill_no,
            payable_amount,
            bill_status

        FROM billing

        ORDER BY
            created_at DESC

        LIMIT 500
        "
    );


/*
|--------------------------------------------------------------------------
| CENTRAL PATIENTS
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Use central $conn.
| Do NOT connect again with hard-coded credentials.
|--------------------------------------------------------------------------
*/

$patient_stmt =
    mysqli_prepare(
        $conn,
        "
        SELECT
            phm.mapping_id,
            phm.hospital_patient_code,
            phm.account_id,

            pa.first_name,
            pa.last_name,
            pa.mobile

        FROM patient_hospital_mapping phm

        JOIN patient_accounts pa
            ON pa.account_id =
               phm.account_id

        WHERE phm.hospital_id = ?
          AND phm.patient_status =
              'Active'

        ORDER BY
            pa.first_name,
            pa.last_name

        LIMIT 500
        "
    );


if ($patient_stmt) {

    mysqli_stmt_bind_param(
        $patient_stmt,
        'i',
        $hospital_id
    );

    mysqli_stmt_execute(
        $patient_stmt
    );


    $result =
        mysqli_stmt_get_result(
            $patient_stmt
        );


    if ($result) {

        while (
            $p =
                mysqli_fetch_assoc($result)
        ) {

            $p['patient_name'] =
                trim(
                    ($p['first_name'] ?? '') .
                    ' ' .
                    ($p['last_name'] ?? '')
                );


            $centralPatients[] =
                $p;
        }


        mysqli_free_result(
            $result
        );
    }


    mysqli_stmt_close(
        $patient_stmt
    );
}


/*
|--------------------------------------------------------------------------
| PATIENT LOOKUP BY HOSPITAL MAPPING
|--------------------------------------------------------------------------
|
| Prescriptions and medical records are linked to patients
| through appointments.mapping_id.
|--------------------------------------------------------------------------
*/

foreach ($centralPatients as $patient) {

    $patientByMapping[
        (int)$patient['mapping_id']
    ] = [
        'patient_name' =>
            (string)(
                $patient['patient_name']
                ?? 'Patient'
            ),

        'hospital_patient_code' =>
            (string)(
                $patient['hospital_patient_code']
                ?? ''
            )
    ];
}


/*
|--------------------------------------------------------------------------
| TABLE DATA
|--------------------------------------------------------------------------
*/

$rows = [];


if ($module === 'availability') {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                da.*,
                d.doctor_name

            FROM doctor_availability da

            JOIN doctors d
                ON d.doctor_id =
                   da.doctor_id

            ORDER BY
                d.doctor_name,
                FIELD(
                    da.day_of_week,
                    'Monday',
                    'Tuesday',
                    'Wednesday',
                    'Thursday',
                    'Friday',
                    'Saturday',
                    'Sunday'
                ),
                da.start_time
            "
        );


} elseif (
    $module === 'medical_records'
) {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                mr.*,
                a.appointment_no,
                a.appointment_date,
                d.doctor_name

            FROM medical_records mr

            JOIN appointments a
                ON a.appointment_id =
                   mr.appointment_id

            JOIN doctors d
                ON d.doctor_id =
                   a.doctor_id

            ORDER BY
                mr.created_at DESC

            LIMIT 500
            "
        );


} elseif (
    $module === 'prescriptions'
) {

    /*
    Do NOT use JSON_ARRAYAGG here.
    Fetch prescriptions normally and load prescription_items
    separately. This works on older MySQL/MariaDB versions too.
    */

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                p.*,
                a.mapping_id,
                a.appointment_no,
                a.appointment_date,
                d.doctor_name,
                mr.medical_record_id

            FROM prescriptions p

            JOIN medical_records mr
                ON mr.medical_record_id =
                   p.medical_record_id

            JOIN appointments a
                ON a.appointment_id =
                   mr.appointment_id

            JOIN doctors d
                ON d.doctor_id =
                   a.doctor_id

            ORDER BY
                p.created_at DESC

            LIMIT 500
            "
        );


    $prescriptionItemsByPrescription = [];

    if ($rows) {

        $itemRows =
            fetch_all_assoc(
                $hospital_conn,
                "
                SELECT
                    prescription_item_id,
                    prescription_id,
                    medicine_name,
                    dosage,
                    frequency,
                    duration,
                    route,
                    instructions,
                    medicine_price

                FROM prescription_items

                ORDER BY
                    prescription_id,
                    prescription_item_id
                "
            );


        foreach (
            $itemRows as $item
        ) {

            $pid =
                (int)$item['prescription_id'];


            if (
                !isset(
                    $prescriptionItemsByPrescription[$pid]
                )
            ) {

                $prescriptionItemsByPrescription[$pid] =
                    [];
            }


            $prescriptionItemsByPrescription[$pid][] =
                [
                    'prescription_item_id' =>
                        (int)$item[
                            'prescription_item_id'
                        ],

                    'medicine_name' =>
                        (string)$item[
                            'medicine_name'
                        ],

                    'dosage' =>
                        (string)$item['dosage'],

                    'frequency' =>
                        (string)$item['frequency'],

                    'duration' =>
                        (string)$item['duration'],

                    'route' =>
                        (string)$item['route'],

                    'instructions' =>
                        (string)$item[
                            'instructions'
                        ],

                    'medicine_price' =>
                        (float)($item[
                            'medicine_price'
                        ] ?? 0)
                ];
        }


        foreach (
            $rows as &$prescriptionRow
        ) {

            $pid =
                (int)$prescriptionRow[
                    'prescription_id'
                ];


            $prescriptionRow['medicine_items'] =
                $prescriptionItemsByPrescription[$pid]
                ?? [];
        }


        unset($prescriptionRow);
    }


    /*
    Add patient information to every prescription.
    */

    foreach ($rows as &$prescriptionRow) {

        $mappingId =
            (int)(
                $prescriptionRow['mapping_id']
                ?? 0
            );

        $patient =
            $patientByMapping[$mappingId]
            ?? null;

        $prescriptionRow['patient_name'] =
            $patient['patient_name']
            ?? 'Unknown Patient';

        $prescriptionRow['hospital_patient_code'] =
            $patient['hospital_patient_code']
            ?? '';
    }

    unset($prescriptionRow);


    /*
    Medicine dropdown catalog.
    */

    $medicineCatalog =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                medicine_name,
                dosage,
                frequency,
                duration,
                route,
                instructions,
                medicine_price

            FROM prescription_items

            WHERE TRIM(medicine_name) <> ''

            ORDER BY
                medicine_name,
                prescription_item_id DESC
            "
        );


    /*
    One dropdown entry per medicine name.
    */

    $medicineOptions = [];
    $seenMedicineNames = [];

    foreach (
        $medicineCatalog as $medicine
    ) {

        $medicineName =
            trim(
                (string)$medicine[
                    'medicine_name'
                ]
            );


        $medicineKey =
            strtolower($medicineName);


        if (
            $medicineName === '' ||
            isset(
                $seenMedicineNames[$medicineKey]
            )
        ) {

            continue;
        }


        $seenMedicineNames[$medicineKey] =
            true;


        $medicineOptions[] = [
            'medicine_name' =>
                $medicineName,

            'dosage' =>
                (string)(
                    $medicine['dosage']
                    ?? ''
                ),

            'frequency' =>
                (string)(
                    $medicine['frequency']
                    ?? ''
                ),

            'duration' =>
                (string)(
                    $medicine['duration']
                    ?? ''
                ),

            'route' =>
                (string)(
                    $medicine['route']
                    ?? 'Oral'
                ),

            'instructions' =>
                (string)(
                    $medicine['instructions']
                    ?? ''
                ),

            'medicine_price' =>
                (float)(
                    $medicine['medicine_price']
                    ?? 0
                )
        ];
    }


} elseif ($module === 'lab_tests') {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                l.*,
                a.appointment_no,
                d.doctor_name

            FROM lab_tests l

            JOIN medical_records mr
                ON mr.medical_record_id =
                   l.medical_record_id

            JOIN appointments a
                ON a.appointment_id =
                   mr.appointment_id

            JOIN doctors d
                ON d.doctor_id =
                   l.ordered_by_doctor_id

            ORDER BY
                l.created_at DESC

            LIMIT 500
            "
        );


} elseif ($module === 'admissions') {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                a.*,

                b.bed_number,

                r.room_number,
                r.room_type,

                d.doctor_name,

                phm.hospital_patient_code,

                pa.first_name,
                pa.last_name

            FROM admissions a

            JOIN beds b
                ON b.bed_id =
                   a.bed_id

            JOIN rooms r
                ON r.room_id =
                   b.room_id

            JOIN doctors d
                ON d.doctor_id =
                   a.admitted_by_doctor_id

            JOIN hospital_management.patient_hospital_mapping phm
                ON phm.mapping_id =
                   a.mapping_id

            JOIN hospital_management.patient_accounts pa
                ON pa.account_id =
                   phm.account_id

            WHERE phm.hospital_id = " . intval($hospital_id) . "

            ORDER BY
                a.admission_date DESC

            LIMIT 500
            "
        );


} elseif ($module === 'rooms_beds') {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                r.*,
                COUNT(b.bed_id) AS bed_count,
                SUM(
                    b.status = 'Occupied'
                ) AS occupied

            FROM rooms r

            LEFT JOIN beds b
                ON b.room_id =
                   r.room_id

            GROUP BY
                r.room_id

            ORDER BY
                r.room_number
            "
        );


} elseif ($module === 'billing') {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                b.*,
                a.appointment_no

            FROM billing b

            JOIN appointments a
                ON a.appointment_id =
                   b.appointment_id

            ORDER BY
                b.created_at DESC

            LIMIT 500
            "
        );


} else {

    $rows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                p.*,
                b.bill_no

            FROM payments p

            JOIN billing b
                ON b.bill_id =
                   p.bill_id

            ORDER BY
                p.paid_at DESC

            LIMIT 500
            "
        );
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER
|--------------------------------------------------------------------------
*/

$filtered = [];


foreach (
    $rows as $row
) {

    $hay =
        strtolower(
            json_encode(
                $row,
                JSON_UNESCAPED_UNICODE
            )
        );


    if (
        $search !== '' &&
        strpos(
            $hay,
            strtolower($search)
        ) === false
    ) {

        continue;
    }


    $rowStatus =
        (string)(
            $row['status']
            ??
            $row['record_status']
            ??
            $row['test_status']
            ??
            $row['admission_status']
            ??
            $row['bill_status']
            ??
            $row['payment_status']
            ??
            ''
        );


    if (
        $status !== '' &&
        $rowStatus !== $status
    ) {

        continue;
    }


    $filtered[] =
        $row;
}


/*
|--------------------------------------------------------------------------
| STATUS OPTIONS
|--------------------------------------------------------------------------
*/

$statusOptions =
    match ($module) {

        'availability' => [
            'Active',
            'Inactive'
        ],

        'medical_records' => [
            'Open',
            'Completed',
            'Archived'
        ],

        'prescriptions' => [
            'Active',
            'Completed',
            'Cancelled'
        ],

        'lab_tests' => [
            'Ordered',
            'Sample Collected',
            'Processing',
            'Completed',
            'Cancelled'
        ],

        'admissions' => [
            'Admitted',
            'Discharged',
            'Transferred',
            'Cancelled'
        ],

        'rooms_beds' => [
            'Available',
            'Maintenance',
            'Inactive'
        ],

        'billing' => [
            'Pending',
            'Partially Paid',
            'Paid',
            'Cancelled'
        ],

        'payments' => [
            'Pending',
            'Success',
            'Failed',
            'Refunded'
        ],

        default => []
    };


/*
|--------------------------------------------------------------------------
| STATS
|--------------------------------------------------------------------------
*/

$stats = [
    'total' => count($rows),
    'active' => 0,
    'today' => 0,
    'filtered' => count($filtered)
];


foreach (
    $rows as $r
) {

    $s =
        (string)(
            $r['status']
            ??
            $r['record_status']
            ??
            $r['test_status']
            ??
            $r['admission_status']
            ??
            $r['bill_status']
            ??
            $r['payment_status']
            ??
            ''
        );


    if (
        in_array(
            $s,
            [
                'Active',
                'Open',
                'Ordered',
                'Admitted',
                'Pending'
            ],
            true
        )
    ) {

        $stats['active']++;
    }


    if (
        (
            $r['test_date']
            ??
            $r['appointment_date']
            ??
            ''
        )
        === date('Y-m-d')
    ) {

        $stats['today']++;
    }
}

?>
<!doctype html>

<html lang="en">

<head>

<meta charset="utf-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1"
>

<title><?=mh($title)?></title>

<link
    rel="stylesheet"
    href="css/sidebar.css"
>

<link
    rel="stylesheet"
    href="css/hospital_modules.css"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
>


<style>
.medicine-section {
    margin-top: 24px;
    border-top: 1px solid #e2e8f0;
    padding-top: 20px;
}

.medicine-section-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 16px;
}

.medicine-section-head h3 {
    margin: 0 0 4px;
}

.medicine-section-head small {
    color: #64748b;
}

.medicine-rows {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.medicine-row {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px;
    background: #f8fafc;
}

.medicine-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    align-items: end;
}

.medicine-grid label {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.medicine-grid .medicine-instructions {
    grid-column: span 2;
}

.medicine-remove {
    border: 1px solid #fecaca;
    background: #fff;
    color: #dc2626;
    border-radius: 8px;
    padding: 10px 12px;
    cursor: pointer;
}

.medicine-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 80px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    color: #64748b;
}

@media (max-width: 900px) {
    .medicine-grid {
        grid-template-columns: 1fr 1fr;
    }

    .medicine-grid .medicine-instructions {
        grid-column: span 2;
    }
}

@media (max-width: 600px) {
    .medicine-grid {
        grid-template-columns: 1fr;
    }

    .medicine-grid .medicine-instructions {
        grid-column: span 1;
    }

    .medicine-section-head {
        align-items: flex-start;
        flex-direction: column;
    }
}

.medicine-grid .medicine-name-select {
    width: 100%;
    box-sizing: border-box;
}

.medicine-grid select,
.medicine-grid input {
    width: 100%;
    box-sizing: border-box;
}

.medicine-grid .medicine-remove {
    align-self: end;
}
</style>

</head>


<body>

<div class="dashboard">

<?php require __DIR__ . '/sidebar.php'; ?>


<main class="main-content">


<!-- HEADER -->

<div class="page-header">

<div>

<div class="eyebrow">

<i
    class="fa-solid <?=mh($icon)?>"
></i>

Hospital Management

</div>


<h1>
    <?=mh($heading)?>
</h1>


<p>
    <?=mh($description)?>
</p>

</div>


<div class="header-actions">


<?php if (
    $module === 'availability'
): ?>

<button
    class="primary-btn"
    data-open="availabilityModal"
>

<i class="fa-solid fa-plus"></i>

Add Availability

</button>

<?php endif; ?>


<?php if (
    $module === 'medical_records'
): ?>

<button
    class="primary-btn"
    data-open="recordModal"
>

<i class="fa-solid fa-plus"></i>

Add Record

</button>

<?php endif; ?>


<?php if (
    $module === 'prescriptions'
): ?>

<button
    class="primary-btn"
    data-open="prescriptionModal"
>

<i class="fa-solid fa-plus"></i>

Add Prescription

</button>

<?php endif; ?>


<?php if (
    $module === 'lab_tests'
): ?>

<button
    class="primary-btn"
    data-open="labModal"
>

<i class="fa-solid fa-plus"></i>

Add Lab Test

</button>

<?php endif; ?>


<?php if (
    $module === 'admissions'
): ?>

<button
    class="primary-btn"
    data-open="admissionModal"
>

<i class="fa-solid fa-plus"></i>

New Admission

</button>

<?php endif; ?>


<?php if (
    $module === 'rooms_beds'
): ?>

<button
    class="primary-btn"
    data-open="roomModal"
>

<i class="fa-solid fa-plus"></i>

Add Room

</button>


<button
    class="secondary-btn"
    data-open="bedModal"
>

<i class="fa-solid fa-bed"></i>

Add Bed

</button>

<?php endif; ?>


<?php if (
    $module === 'billing'
): ?>

<button
    class="primary-btn"
    data-open="billModal"
>

<i class="fa-solid fa-plus"></i>

Create Bill

</button>

<?php endif; ?>


<?php if (
    $module === 'payments'
): ?>

<button
    class="primary-btn"
    data-open="paymentModal"
>

<i class="fa-solid fa-plus"></i>

Record Payment

</button>

<?php endif; ?>


</div>

</div>


<!-- FLASH -->

<?php if ($flash): ?>

<div
    class="page-message <?=mh(
        (string)$flash['type']
    )?>"
>

<i class="fa-solid fa-circle-check"></i>

<?=mh(
    (string)$flash['message']
)?>

</div>

<?php endif; ?>


<!-- STATS -->

<div class="stats-row">

<div class="mini-stat">

<span>Total</span>

<strong>
    <?=number_format(
        $stats['total']
    )?>
</strong>

<i class="fa-solid fa-layer-group"></i>

</div>


<div class="mini-stat">

<span>
    Active / Open
</span>

<strong>
    <?=number_format(
        $stats['active']
    )?>
</strong>

<i class="fa-solid fa-circle-check"></i>

</div>


<div class="mini-stat">

<span>
    Today
</span>

<strong>
    <?=number_format(
        $stats['today']
    )?>
</strong>

<i class="fa-solid fa-calendar-day"></i>

</div>


<div class="mini-stat">

<span>
    Showing
</span>

<strong>
    <?=number_format(
        $stats['filtered']
    )?>
</strong>

<i class="fa-solid fa-filter"></i>

</div>

</div>


<!-- FILTER -->

<form
    class="module-toolbar"
    method="get"
>

<input
    type="hidden"
    name="module"
    value="<?=mh($module)?>"
>


<div class="search-wrap">

<i class="fa-solid fa-magnifying-glass"></i>

<input
    name="search"
    value="<?=mh($search)?>"
    placeholder="
        Search <?=mh(
            strtolower($heading)
        )?>...
    "
>

</div>


<select name="status">

<option value="">
    All Status
</option>


<?php foreach (
    $statusOptions as $s
): ?>

<option
    value="<?=mh($s)?>"
    <?=$status === $s
        ? 'selected'
        : ''?>
>

<?=mh($s)?>

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
    href="
        hospital_module.php?module=
        <?=mh($module)?>
    "
>

<i class="fa-solid fa-rotate-left"></i>

Reset

</a>

</form>


<!-- TABLE -->

<div class="table-card">

<div class="table-head">

<div>

<h2>
    <?=mh($heading)?> List
</h2>

<span>
    <?=number_format(
        count($filtered)
    )?>
    record(s)
</span>

</div>

</div>


<div class="table-scroll">

<table>

<thead>

<tr>


<?php if (
    $module === 'availability'
): ?>

<th>Doctor</th>
<th>Day</th>
<th>Time</th>
<th>Slot</th>
<th>Max</th>
<th>Mode</th>
<th>Status</th>
<th>Actions</th>


<?php elseif (
    $module === 'medical_records'
): ?>

<th>Appointment</th>
<th>Doctor</th>
<th>Complaint</th>
<th>Diagnosis</th>
<th>Fee</th>
<th>Follow Up</th>
<th>Status</th>
<th>Actions</th>


<?php elseif (
    $module === 'prescriptions'
): ?>

<th>Prescription</th>
<th>Patient</th>
<th>Appointment</th>
<th>Doctor</th>
<th>Medicines</th>
<th>Fee</th>
<th>Follow Up</th>
<th>Status</th>
<th>Actions</th>


<?php elseif (
    $module === 'lab_tests'
): ?>

<th>Test</th>
<th>Appointment</th>
<th>Doctor</th>
<th>Category</th>
<th>Test Fee</th>
<th>Status</th>
<th>Date</th>
<th>Actions</th>


<?php elseif (
    $module === 'admissions'
): ?>

<th>Patient</th>
<th>Admission</th>
<th>Room / Bed</th>
<th>Doctor</th>
<th>Fee</th>
<th>Date</th>
<th>Status</th>
<th>Actions</th>


<?php elseif (
    $module === 'rooms_beds'
): ?>

<th>Room</th>
<th>Type</th>
<th>Floor</th>
<th>Beds</th>
<th>Occupied</th>
<th>Room Charge</th>
<th>Status</th>
<th>Actions</th>


<?php elseif (
    $module === 'billing'
): ?>

<th>Bill</th>
<th>Appointment</th>
<th>Total</th>
<th>Payable</th>
<th>Status</th>
<th>Actions</th>


<?php else: ?>

<th>Payment</th>
<th>Bill</th>
<th>Method</th>
<th>Amount</th>
<th>Status</th>
<th>Paid At</th>
<th>Actions</th>

<?php endif; ?>


</tr>

</thead>


<tbody>


<?php foreach (
    $filtered as $r
): ?>

<tr>


<?php if (
    $module === 'availability'
): ?>

<td>
<strong>
<?=mh($r['doctor_name'])?>
</strong>
</td>

<td>
<?=mh($r['day_of_week'])?>
</td>

<td>
<?=mh(
    substr(
        $r['start_time'],
        0,
        5
    )
    .
    ' - '
    .
    substr(
        $r['end_time'],
        0,
        5
    )
)?>
</td>

<td>
<?=intval(
    $r['slot_duration_minutes']
)?> min
</td>

<td>
<?=intval(
    $r['max_patients']
)?>
</td>

<td>
<?=mh(
    $r['consultation_mode']
)?>
</td>

<td>

<span class="status-pill">

<?=mh(
    $r['status']
)?>

</span>

</td>

<td class="actions">

<button
    class="icon-btn"
    title="View"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>


<button
    class="icon-btn"
    title="Edit"
    data-edit='<?=mh(
        json_encode($r)
    )?>'
    data-open="availabilityModal"
>

<i class="fa-regular fa-pen-to-square"></i>

</button>


<form
    method="post"
    class="inline-form"
    onsubmit="
        return confirm(
            'Delete this availability?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="delete_availability"
>

<input
    type="hidden"
    name="availability_id"
    value="<?=intval(
        $r['availability_id']
    )?>"
>

<button
    class="icon-btn danger"
    title="Delete"
>

<i class="fa-regular fa-trash-can"></i>

</button>

</form>

</td>


<?php elseif (
    $module === 'medical_records'
): ?>

<td>

<strong>
<?=mh($r['appointment_no'])?>
</strong>

<small>
<?=mh($r['appointment_date'])?>
</small>

</td>

<td>
<?=mh($r['doctor_name'])?>
</td>

<td>
<?=mh(
    mb_strimwidth(
        (string)$r['chief_complaint'],
        0,
        55,
        '...'
    )
)?>
</td>

<td>
<?=mh(
    mb_strimwidth(
        (string)$r['diagnosis_summary'],
        0,
        55,
        '...'
    )
)?>
</td>

<td>
₹ <?=number_format(
    (float)($r['medical_record_fee'] ?? 0),
    2
)?>
</td>

<td>
<?=mh(
    $r['follow_up_date']
    ?? '—'
)?>
</td>

<td>
<span class="status-pill">
<?=mh(
    $r['record_status']
)?>
</span>
</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>


<button
    class="icon-btn"
    data-edit='<?=mh(
        json_encode($r)
    )?>'
    data-open="recordModal"
>

<i class="fa-regular fa-pen-to-square"></i>

</button>


<form
    method="post"
    class="inline-form"
    onsubmit="
        return confirm(
            'Delete this medical record?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="delete_medical_record"
>

<input
    type="hidden"
    name="medical_record_id"
    value="<?=intval(
        $r['medical_record_id']
    )?>"
>

<button
    class="icon-btn danger"
>

<i class="fa-regular fa-trash-can"></i>

</button>

</form>

</td>


<?php elseif (
    $module === 'prescriptions'
): ?>

<td>
<strong>
<?=mh($r['prescription_no'])?>
</strong>
</td>

<td>
<strong>
<?=mh($r['patient_name'] ?? 'Unknown Patient')?>
</strong>
<small>
<?=mh($r['hospital_patient_code'] ?? '')?>
</small>
</td>

<td>
<?=mh($r['appointment_no'])?>
</td>

<td>
<?=mh($r['doctor_name'])?>
</td>

<td>
<?php
$medicineItemsForRow =
    $r['medicine_items']
    ?? [];

if (!is_array($medicineItemsForRow)) {
    $medicineItemsForRow = [];
}
?>
<?=count($medicineItemsForRow)?> medicine(s)
</td>

<td>
₹ <?=number_format(
    (float)($r['prescription_fee'] ?? 0)
    + array_sum(
        array_map(
            static function ($item) {
                return (float)($item['medicine_price'] ?? 0);
            },
            $medicineItemsForRow
        )
    ),
    2
)?>
</td>

<td>
<?=mh(
    $r['follow_up_date']
    ?? '—'
)?>
</td>

<td>
<span class="status-pill">
<?=mh($r['status'])?>
</span>
</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>


<button
    class="icon-btn"
    data-edit='<?=mh(
        json_encode($r)
    )?>'
    data-open="prescriptionModal"
>

<i class="fa-regular fa-pen-to-square"></i>

</button>


<form
    method="post"
    class="inline-form"
    onsubmit="
        return confirm(
            'Delete this prescription?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="delete_prescription"
>

<input
    type="hidden"
    name="prescription_id"
    value="<?=intval(
        $r['prescription_id']
    )?>"
>

<button
    class="icon-btn danger"
>

<i class="fa-regular fa-trash-can"></i>

</button>

</form>

</td>


<?php elseif (
    $module === 'lab_tests'
): ?>

<td>

<strong>
<?=mh($r['test_name'])?>
</strong>

<small>
<?=mh($r['sample_type'])?>
</small>

</td>

<td>
<?=mh($r['appointment_no'])?>
</td>

<td>
<?=mh($r['doctor_name'])?>
</td>

<td>
<?=mh(
    $r['test_category']
    ?? '—'
)?>
</td>

<td>
₹ <?=number_format(
    (float)($r['test_fee'] ?? 0),
    2
)?>
</td>

<td>
<span class="status-pill">
<?=mh($r['test_status'])?>
</span>
</td>

<td>
<?=mh(
    $r['test_date']
    ?? '—'
)?>
</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>


<button
    class="icon-btn"
    data-edit='<?=mh(
        json_encode($r)
    )?>'
    data-open="labModal"
>

<i class="fa-regular fa-pen-to-square"></i>

</button>


<form
    method="post"
    class="inline-form"
    onsubmit="
        return confirm(
            'Delete this lab test?'
        );
    "
>

<input
    type="hidden"
    name="action"
    value="delete_lab_test"
>

<input
    type="hidden"
    name="lab_test_id"
    value="<?=intval(
        $r['lab_test_id']
    )?>"
>

<button
    class="icon-btn danger"
>

<i class="fa-regular fa-trash-can"></i>

</button>

</form>

</td>


<?php elseif (
    $module === 'admissions'
): ?>

<td>

<strong>
<?=mh(
    trim(
        $r['first_name'] .
        ' ' .
        $r['last_name']
    )
)?>
</strong>

<small>
<?=mh(
    $r['hospital_patient_code']
)?>
</small>

</td>

<td>

<strong>
<?=mh($r['admission_no'])?>
</strong>

<small>
<?php if (
    !empty(
        $r['appointment_id']
    )
): ?>

Appointment Linked

<?php else: ?>

No Appointment

<?php endif; ?>
</small>

</td>

<td>

<?=mh(
    $r['room_type']
)?>
<br>

<?=mh(
    $r['room_number'] .
    ' / ' .
    $r['bed_number']
)?>

</td>

<td>
<?=mh($r['doctor_name'])?>
</td>

<td>
₹ <?=number_format(
    (float)($r['admission_fee'] ?? 0),
    2
)?>
</td>

<td>
<?=mh(
    substr(
        $r['admission_date'],
        0,
        16
    )
)?>
</td>

<td>

<span class="status-pill">

<?=mh(
    $r['admission_status']
)?>

</span>

</td>

<td class="actions">

<button
    class="icon-btn"
    title="View"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>


<button
    class="icon-btn"
    title="Edit"
    data-edit-admission='<?=mh(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))?>'
    data-open="editAdmissionModal"
>
<i class="fa-regular fa-pen-to-square"></i>
</button>

<form method="post" class="inline-form" onsubmit="return confirm('Delete this admission?');">
<input type="hidden" name="action" value="delete_admission">
<input type="hidden" name="admission_id" value="<?=intval($r['admission_id'])?>">
<button class="icon-btn danger" title="Delete">
<i class="fa-regular fa-trash-can"></i>
</button>
</form>

<?php if (
    $r['admission_status']
    === 'Admitted'
): ?>

<button
    class="text-btn"
    data-discharge="<?=intval(
        $r['admission_id']
    )?>"
>

Discharge

</button>

<?php endif; ?>

</td>


<?php elseif (
    $module === 'rooms_beds'
): ?>

<td>
<strong>
<?=mh($r['room_number'])?>
</strong>
</td>

<td>
<?=mh($r['room_type'])?>
</td>

<td>
<?=mh(
    $r['floor_number']
    ?? '—'
)?>
</td>

<td>
<?=intval(
    $r['bed_count']
)?>
</td>

<td>
<?=intval(
    $r['occupied']
    ?? 0
)?>
</td>

<td>
₹ <?=number_format(
    (float)($r['room_charge'] ?? 0),
    2
)?>
</td>

<td>

<span class="status-pill">

<?=mh(
    $r['status']
)?>

</span>

</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>

</td>


<?php elseif (
    $module === 'billing'
): ?>

<td>
<strong>
<?=mh($r['bill_no'])?>
</strong>
</td>

<td>
<?=mh(
    $r['appointment_no']
)?>
</td>

<td>
₹ <?=number_format(
    (float)$r['total_amount'],
    2
)?>
</td>

<td>
₹ <?=number_format(
    (float)$r['payable_amount'],
    2
)?>
</td>

<td>

<span class="status-pill">

<?=mh(
    $r['bill_status']
)?>

</span>

</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>

</td>


<?php else: ?>

<td>
<strong>
<?=mh($r['payment_no'])?>
</strong>
</td>

<td>
<?=mh($r['bill_no'])?>
</td>

<td>
<?=mh($r['payment_method'])?>
</td>

<td>
₹ <?=number_format(
    (float)$r['amount'],
    2
)?>
</td>

<td>

<span class="status-pill">

<?=mh(
    $r['payment_status']
)?>

</span>

</td>

<td>
<?=mh($r['paid_at'])?>
</td>

<td class="actions">

<button
    class="icon-btn"
    data-view='<?=mh(
        json_encode($r)
    )?>'
>

<i class="fa-regular fa-eye"></i>

</button>

</td>

<?php endif; ?>


</tr>

<?php endforeach; ?>


<?php if (!$filtered): ?>

<tr>

<td
    colspan="8"
    class="empty"
>

<div>

<i class="fa-regular fa-folder-open"></i>

<strong>
    No records found
</strong>

<span>
    Try changing your search or filter,
    or add a new record.
</span>

</div>

</td>

</tr>

<?php endif; ?>


</tbody>

</table>

</div>

</div>


</main>

</div>


<!-- =========================================================
     AVAILABILITY MODAL
========================================================= -->

<?php if (
    $module === 'availability'
): ?>

<div
    class="module-modal"
    id="availabilityModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Doctor Availability
</span>

<h2>
Add Availability
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="save_availability"
>

<input
    type="hidden"
    name="availability_id"
    id="availability_id"
>


<div class="form-grid">


<label>

Doctor

<select
    name="doctor_id"
    id="availability_doctor"
    required
>

<option value="">
    Select Doctor
</option>

<?php foreach (
    $doctors as $d
): ?>

<option
    value="<?=intval(
        $d['doctor_id']
    )?>"
>

<?=mh(
    $d['doctor_name']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Day

<select
    name="day_of_week"
    id="availability_day"
    required
>

<?php foreach (
    [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ] as $d
): ?>

<option>
<?=mh($d)?>
</option>

<?php endforeach; ?>

</select>

</label>


<label>

Start Time

<input
    type="time"
    name="start_time"
    id="availability_start"
    required
>

</label>


<label>

End Time

<input
    type="time"
    name="end_time"
    id="availability_end"
    required
>

</label>


<label>

Slot Duration

<select
    name="slot_duration_minutes"
    id="availability_slot"
>

<option>10</option>
<option>15</option>
<option>20</option>
<option>30</option>
<option>60</option>

</select>

</label>


<label>

Maximum Patients

<input
    type="number"
    min="1"
    name="max_patients"
    id="availability_max"
    value="20"
    required
>

</label>


<label>

Consultation Mode

<select
    name="consultation_mode"
    id="availability_mode"
>

<option>
In-Person
</option>

<option>
Video
</option>

<option>
Both
</option>

</select>

</label>


<label>

Status

<select
    name="record_status"
    id="availability_status"
>

<option>
Active
</option>

<option>
Inactive
</option>

</select>

</label>


</div>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button
    class="primary-btn"
>
Save Availability
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     MEDICAL RECORD MODAL
========================================================= -->

<?php if (
    $module === 'medical_records'
): ?>

<div
    class="module-modal"
    id="recordModal"
>

<div class="modal-card wide">

<div class="modal-title">

<div>

<span>
Clinical
</span>

<h2>
Add Medical Record
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="save_medical_record"
>

<input
    type="hidden"
    name="medical_record_id"
    id="medical_record_id"
>


<div class="form-grid">


<label>

Appointment

<select
    name="appointment_id"
    id="record_appointment"
    required
>

<option value="">
Select Appointment
</option>

<?php foreach (
    $appointments as $a
): ?>

<option
    value="<?=intval(
        $a['appointment_id']
    )?>"
>

<?=mh(
    $a['appointment_no'] .
    ' — ' .
    $a['doctor_name'] .
    ' — ' .
    $a['appointment_date']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Status

<select
    name="record_status"
    id="record_status"
>

<option>
Open
</option>

<option>
Completed
</option>

<option>
Archived
</option>

</select>

</label>


<label class="full">

Chief Complaint

<textarea
    name="chief_complaint"
    id="record_complaint"
></textarea>

</label>


<label class="full">

Present Illness

<textarea
    name="present_illness"
    id="record_illness"
></textarea>

</label>


<label>

Past Medical History

<textarea
    name="past_medical_history"
    id="record_history"
></textarea>

</label>


<label>

Family History

<textarea
    name="family_history"
    id="record_family"
></textarea>

</label>


<label>

Allergies

<textarea
    name="allergies"
    id="record_allergies"
></textarea>

</label>


<label>

Clinical Notes

<textarea
    name="clinical_notes"
    id="record_notes"
></textarea>

</label>


<label>

Diagnosis Summary

<textarea
    name="diagnosis_summary"
    id="record_diagnosis"
></textarea>

</label>


<label>

Medical Record Fee (₹)

<input
    type="number"
    name="medical_record_fee"
    id="medical_record_fee"
    min="0"
    step="0.01"
    value="0.00"
>

</label>


<label>

Follow-up Date

<input
    type="date"
    name="follow_up_date"
    id="record_follow"
>

</label>


<label class="full">

Follow-up Notes

<textarea
    name="follow_up_notes"
    id="record_follow_notes"
></textarea>

</label>


</div>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Save Record
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<?php
/*
A medical record can have only one prescription because
prescriptions.medical_record_id is UNIQUE.
*/
$existingPrescriptionByRecord = [];

if ($module === 'prescriptions') {

    $existingPrescriptionRows =
        fetch_all_assoc(
            $hospital_conn,
            "
            SELECT
                prescription_id,
                medical_record_id,
                prescription_no

            FROM prescriptions
            "
        );

    foreach (
        $existingPrescriptionRows as $existingPrescription
    ) {

        $existingPrescriptionByRecord[
            (int)$existingPrescription[
                'medical_record_id'
            ]
        ] = [
            'prescription_id' =>
                (int)$existingPrescription[
                    'prescription_id'
                ],

            'prescription_no' =>
                (string)$existingPrescription[
                    'prescription_no'
                ]
        ];
    }
}
?>

<!-- =========================================================
     PRESCRIPTION MODAL
========================================================= -->

<?php if (
    $module === 'prescriptions'
): ?>

<div
    class="module-modal"
    id="prescriptionModal"
>

<div class="modal-card wide">

<div class="modal-title">

<div>

<span>
Clinical
</span>

<h2>
Prescription
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form
    method="post"
    id="prescriptionForm"
>

<input
    type="hidden"
    name="action"
    value="save_prescription"
>

<input
    type="hidden"
    name="prescription_id"
    id="prescription_id"
>


<div class="form-grid">


<label>

Medical Record

<select
    name="medical_record_id"
    id="prescription_record"
    required
>

<option value="">
Select Medical Record
</option>

<?php foreach (
    $records as $r
): ?>

<?php
$recordIdForOption =
    (int)$r['medical_record_id'];

$existingPrescription =
    $existingPrescriptionByRecord[
        $recordIdForOption
    ]
    ?? null;
?>

<option
    value="<?=$recordIdForOption?>"
    data-has-prescription="<?=$existingPrescription ? '1' : '0'?>"
    data-prescription-id="<?=$existingPrescription ? intval($existingPrescription['prescription_id']) : '0'?>"
    <?=$existingPrescription ? 'disabled' : ''?>
>

<?=mh(
    ($r['patient_name'] ?? 'Unknown Patient') .
    ' — ' .
    ($r['hospital_patient_code'] ?? '') .
    ' — ' .
    $r['appointment_no'] .
    ' — ' .
    $r['doctor_name'] .
    ' — ' .
    $r['appointment_date'] .
    (
        $existingPrescription
            ? ' — Prescription already exists'
            : ''
    )
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Prescription No

<input
    name="prescription_no"
    id="prescription_no"
    placeholder="Auto generated if blank"
>

</label>


<label>

Follow-up Date

<input
    type="date"
    name="follow_up_date"
    id="prescription_follow"
>

</label>


<label>

Status

<select
    name="prescription_status"
    id="prescription_status"
>

<option>
Active
</option>

<option>
Completed
</option>

<option>
Cancelled
</option>

</select>

</label>


<label>

Prescription Fee (₹)

<input
    type="number"
    name="prescription_fee"
    id="prescription_fee"
    min="0"
    step="0.01"
    value="0.00"
>

</label>


<label class="full">

Advice

<textarea
    name="advice"
    id="prescription_advice"
    rows="3"
></textarea>

</label>

</div>


<div class="medicine-section">

<div class="medicine-section-head">

<div>

<h3>
Medicines
</h3>

<small>
Select a medicine from the existing prescription medicine list.
The other fields will be filled automatically.
</small>

</div>


<button
    type="button"
    class="secondary-btn"
    id="addMedicineBtn"
>

<i class="fa-solid fa-plus"></i>

Add Medicine

</button>

</div>


<div
    id="medicineRows"
    class="medicine-rows"
></div>


<div
    id="medicineEmpty"
    class="medicine-empty"
>

<i class="fa-solid fa-pills"></i>

<span>
No medicines added yet.
</span>

</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button
    type="submit"
    class="primary-btn"
>

<i class="fa-solid fa-floppy-disk"></i>

Save Prescription

</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     LAB TEST MODAL
========================================================= -->

<?php if (
    $module === 'lab_tests'
): ?>

<div
    class="module-modal"
    id="labModal"
>

<div class="modal-card wide">

<div class="modal-title">

<div>

<span>
Diagnostics
</span>

<h2>
Lab Test
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="save_lab_test"
>

<input
    type="hidden"
    name="lab_test_id"
    id="lab_test_id"
>


<div class="form-grid">


<label>

Medical Record

<select
    name="medical_record_id"
    id="lab_record"
    required
>

<option value="">
Select Medical Record
</option>

<?php foreach (
    $records as $r
): ?>

<option
    value="<?=intval(
        $r['medical_record_id']
    )?>"
>

<?=mh(
    $r['appointment_no'] .
    ' — ' .
    $r['doctor_name'] .
    ' — ' .
    $r['appointment_date']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Ordered By Doctor

<select
    name="ordered_by_doctor_id"
    id="lab_doctor"
    required
>

<option value="">
Select Doctor
</option>

<?php foreach (
    $doctors as $d
): ?>

<option
    value="<?=intval(
        $d['doctor_id']
    )?>"
>

<?=mh(
    $d['doctor_name']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Test Name

<input
    name="test_name"
    id="lab_name"
    required
>

</label>


<label>

Category

<input
    name="test_category"
    id="lab_category"
>

</label>


<label>

Test Fee (₹)

<input
    type="number"
    name="test_fee"
    id="lab_fee"
    min="0"
    step="0.01"
    value="0.00"
>

</label>


<label>

Sample Type

<select
    name="sample_type"
    id="lab_sample"
>

<option>
Blood
</option>

<option>
Urine
</option>

<option>
Stool
</option>

<option>
Saliva
</option>

<option>
Sputum
</option>

<option>
Other
</option>

</select>

</label>


<label>

Status

<select
    name="test_status"
    id="lab_status"
>

<option>
Ordered
</option>

<option>
Sample Collected
</option>

<option>
Processing
</option>

<option>
Completed
</option>

<option>
Cancelled
</option>

</select>

</label>


<label>

Test Date

<input
    type="date"
    name="test_date"
    id="lab_date"
>

</label>


<label>

Normal Range

<input
    name="normal_range"
    id="lab_normal"
>

</label>


<label class="full">

Result

<textarea
    name="test_result"
    id="lab_result"
></textarea>

</label>


<label class="full">

Remarks

<textarea
    name="remarks"
    id="lab_remarks"
></textarea>

</label>


</div>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Save Lab Test
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     ROOMS
========================================================= -->

<?php if (
    $module === 'rooms_beds'
): ?>

<div
    class="module-modal"
    id="roomModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Hospital
</span>

<h2>
Add Room
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="add_room"
>


<label>

Room Number

<input
    name="room_number"
    required
>

</label>


<label>

Room Type

<select name="room_type">

<option>
General Ward
</option>

<option>
Semi Private
</option>

<option>
Private
</option>

<option>
ICU
</option>

<option>
NICU
</option>

<option>
Operation Theatre
</option>

<option>
Emergency
</option>

</select>

</label>


<label>

Floor

<input
    name="floor_number"
>

</label>


<label>

Room Charge

<input
    type="number"
    step="0.01"
    name="room_charge"
    value="0"
>

</label>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Save Room
</button>

</div>

</form>

</div>

</div>


<!-- ADD BED -->

<div
    class="module-modal"
    id="bedModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Hospital
</span>

<h2>
Add Bed
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="add_bed"
>


<label>

Room

<select
    name="room_id"
    required
>

<?php foreach (
    $rooms as $r
): ?>

<option
    value="<?=intval(
        $r['room_id']
    )?>"
>

<?=mh(
    $r['room_number'] .
    ' — ' .
    $r['room_type']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Bed Number

<input
    name="bed_number"
    required
>

</label>


<label>

Bed Charge

<input
    type="number"
    step="0.01"
    name="bed_charge"
    value="0"
>

</label>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Save Bed
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     BILLING
========================================================= -->

<?php if (
    $module === 'billing'
): ?>

<div
    class="module-modal"
    id="billModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Finance
</span>

<h2>
Create Bill
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="add_bill"
>


<label>

Appointment

<select
    name="appointment_id"
    required
>

<?php foreach (
    $appointments as $a
): ?>

<option
    value="<?=intval(
        $a['appointment_id']
    )?>"
>

<?=mh(
    $a['appointment_no'] .
    ' — ' .
    $a['doctor_name'] .
    ' — ' .
    $a['appointment_date']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Total Amount

<input
    type="number"
    step="0.01"
    min="0"
    name="total_amount"
    required
>

</label>


<label>

Discount

<input
    type="number"
    step="0.01"
    min="0"
    name="discount_amount"
    value="0"
>

</label>


<label>

Tax

<input
    type="number"
    step="0.01"
    min="0"
    name="tax_amount"
    value="0"
>

</label>


<label>

Remarks

<textarea
    name="remarks"
></textarea>

</label>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Create Bill
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     PAYMENTS
========================================================= -->

<?php if (
    $module === 'payments'
): ?>

<div
    class="module-modal"
    id="paymentModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Finance
</span>

<h2>
Record Payment
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="add_payment"
>


<label>

Bill

<select
    name="bill_id"
    required
>

<?php foreach (
    $bills as $b
): ?>

<option
    value="<?=intval(
        $b['bill_id']
    )?>"
>

<?=mh(
    $b['bill_no'] .
    ' — ₹' .
    number_format(
        (float)$b['payable_amount'],
        2
    )
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<label>

Payment Method

<select name="payment_method">

<option>Cash</option>
<option>Card</option>
<option>UPI</option>
<option>Net Banking</option>
<option>Insurance</option>
<option>Other</option>

</select>

</label>


<label>

Amount

<input
    type="number"
    step="0.01"
    min="0"
    name="amount"
    required
>

</label>


<label>

Transaction Reference

<input
    name="transaction_reference"
>

</label>


<label>

Remarks

<textarea
    name="remarks"
></textarea>

</label>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Save Payment
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     ADMISSION MODAL
========================================================= -->

<?php if (
    $module === 'admissions'
): ?>

<div
    class="module-modal"
    id="admissionModal"
>

<div class="modal-card wide">

<div class="modal-title">

<div>

<span>
Hospital
</span>

<h2>
New Admission
</h2>

</div>


<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form
    method="post"
    id="admissionForm"
>

<input
    type="hidden"
    name="action"
    value="add_admission"
>


<div class="form-grid">


<!-- PATIENT -->

<label>

Patient

<select
    name="mapping_id"
    id="admissionPatient"
    required
>

<option value="">
Select Patient
</option>


<?php foreach (
    $centralPatients as $p
): ?>

<option
    value="<?=intval(
        $p['mapping_id']
    )?>"
>

<?=mh(
    (
        $p['patient_name']
        ?: 'Patient'
    )
    .
    ' — ' .
    $p['hospital_patient_code']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<!-- APPOINTMENT -->

<label>

Appointment (optional)

<select
    name="appointment_id"
    id="admissionAppointment"
>

<option value="0">
Select Patient First
</option>

</select>


<small
    id="appointmentHelp"
    style="
        display:block;
        margin-top:6px;
        color:#64748b;
    "
>

Select a patient to see
current/future appointments.

</small>

</label>


<!-- ROOM -->

<label>

Room

<select
    name="room_id"
    id="admissionRoom"
    required
>

<option value="">
Select Room
</option>

<?php foreach ($rooms as $room): ?>

<?php if (($room['status'] ?? 'Available') === 'Available'): ?>

<option
    value="<?=intval($room['room_id'])?>"
    data-room-type="<?=mh($room['room_type'])?>"
>

<?=mh('Room ' . $room['room_number'] . ' — ' . $room['room_type'])?>

</option>

<?php endif; ?>

<?php endforeach; ?>

</select>

</label>


<!-- BED -->

<label>

Bed

<select
    name="bed_id"
    id="admissionBed"
    required
    disabled
>

<option value="">
Select Room First
</option>

<?php foreach ($beds as $b): ?>

<?php if (
    $b['status'] === 'Available'
    &&
    ($b['room_status'] ?? 'Available') === 'Available'
): ?>

<option
    value="<?=intval($b['bed_id'])?>"
    data-room-id="<?=intval($b['room_id'])?>"
>

<?=mh(
    'Room ' . $b['room_number'] . ' — Bed ' . $b['bed_number']
)?>

</option>

<?php endif; ?>

<?php endforeach; ?>

</select>

</label>


<!-- DOCTOR -->

<label>

Admitting Doctor

<select
    name="admitted_by_doctor_id"
    required
>

<option value="">
Select Doctor
</option>


<?php foreach (
    $doctors as $d
): ?>

<option
    value="<?=intval(
        $d['doctor_id']
    )?>"
>

<?=mh(
    $d['doctor_name']
)?>

</option>

<?php endforeach; ?>

</select>

</label>


<!-- ADMISSION DATE -->

<label>

Admission Date

<input
    type="datetime-local"
    name="admission_date"
    id="admissionDate"
    value="<?=date(
        'Y-m-d\TH:i'
    )?>"
    required
>

</label>


<!-- EXPECTED DISCHARGE -->

<label>

Expected Discharge

<input
    type="date"
    name="expected_discharge_date"
>

</label>


<!-- REASON -->

<label class="full">

Admission Reason

<textarea
    name="admission_reason"
    placeholder="
        Enter reason for admission...
    "
></textarea>

</label>


<label>

Admission Fee (₹)

<input
    type="number"
    name="admission_fee"
    id="admissionFee"
    min="0"
    step="0.01"
    value="0.00"
>

</label>


</div>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>

Cancel

</button>


<button
    class="primary-btn"
>

<i class="fa-solid fa-bed-pulse"></i>

Create Admission

</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- VIEW MODAL -->

<div
    class="module-modal"
    id="viewModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Details
</span>

<h2>
Record Details
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<div
    id="viewContent"
    class="view-details"
></div>


<div class="modal-footer">

<button
    type="button"
    class="primary-btn"
    data-close
>

Close

</button>

</div>

</div>

</div>


<!-- EDIT ADMISSION -->

<?php if ($module === 'admissions'): ?>
<div class="module-modal" id="editAdmissionModal">
<div class="modal-card wide">
<div class="modal-title">
<div><span>Admission</span><h2>Edit Admission</h2></div>
<button type="button" data-close><i class="fa-solid fa-xmark"></i></button>
</div>
<form method="post" id="editAdmissionForm">
<input type="hidden" name="action" value="edit_admission">
<input type="hidden" name="admission_id" id="editAdmissionId">
<div class="form-grid">
<label>Patient<select name="mapping_id" id="editAdmissionPatient" required>
<option value="">Select Patient</option>
<?php foreach ($centralPatients as $p): ?>
<option value="<?=intval($p['mapping_id'])?>"><?=mh(($p['patient_name'] ?: 'Patient').' — '.$p['hospital_patient_code'])?></option>
<?php endforeach; ?>
</select></label>
<label>Appointment<select name="appointment_id" id="editAdmissionAppointment"><option value="0">No Appointment</option></select></label>
<label>Room Type<select id="editAdmissionRoomType" required><option value="">Select Room Type</option>
<?php foreach ($roomTypes as $roomType): ?><option value="<?=mh($roomType)?>"><?=mh($roomType)?></option><?php endforeach; ?>
</select></label>
<label>Bed<select name="bed_id" id="editAdmissionBed" required><option value="">Select Bed</option>
<?php foreach ($beds as $b): ?>
<option value="<?=intval($b['bed_id'])?>" data-room-type="<?=mh($b['room_type'])?>" data-bed-status="<?=mh($b['status'])?>"><?=mh($b['room_type'].' — Room '.$b['room_number'].' — Bed '.$b['bed_number'].' ('.$b['status'].')')?></option>
<?php endforeach; ?>
</select></label>
<label>Admitting Doctor<select name="admitted_by_doctor_id" id="editAdmissionDoctor" required><option value="">Select Doctor</option>
<?php foreach ($doctors as $d): ?><option value="<?=intval($d['doctor_id'])?>"><?=mh($d['doctor_name'])?></option><?php endforeach; ?>
</select></label>
<label>Admission Date<input type="datetime-local" name="admission_date" id="editAdmissionDate" required></label>
<label>Expected Discharge<input type="date" name="expected_discharge_date" id="editExpectedDischarge"></label>
<label>Status<select name="admission_status" id="editAdmissionStatus"><option>Admitted</option><option>Discharged</option><option>Transferred</option><option>Cancelled</option></select></label>
<label class="full">Admission Reason<textarea name="admission_reason" id="editAdmissionReason"></textarea></label>
<label>Admission Fee (₹)<input type="number" name="admission_fee" id="editAdmissionFee" min="0" step="0.01" value="0.00"></label>
</div>
<div class="modal-footer">
<button type="button" class="reset-modal" data-close>Cancel</button>
<button class="primary-btn"><i class="fa-solid fa-floppy-disk"></i> Update Admission</button>
</div>
</form>
</div>
</div>
<?php endif; ?>

<!-- DISCHARGE -->

<?php if (
    $module === 'admissions'
): ?>

<div
    class="module-modal"
    id="dischargeModal"
>

<div class="modal-card">

<div class="modal-title">

<div>

<span>
Admission
</span>

<h2>
Discharge Patient
</h2>

</div>

<button
    type="button"
    data-close
>

<i class="fa-solid fa-xmark"></i>

</button>

</div>


<form method="post">

<input
    type="hidden"
    name="action"
    value="discharge"
>


<input
    type="hidden"
    name="admission_id"
    id="discharge_id"
>


<label>

Discharge Summary

<textarea
    name="discharge_summary"
    required
></textarea>

</label>


<div class="modal-footer">

<button
    type="button"
    class="reset-modal"
    data-close
>
Cancel
</button>

<button class="primary-btn">
Confirm Discharge
</button>

</div>

</form>

</div>

</div>

<?php endif; ?>


<!-- =========================================================
     ADMISSION JS
========================================================= -->

<?php if (
    $module === 'admissions'
): ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const patientSelect =
            document.getElementById(
                'admissionPatient'
            );


        const appointmentSelect =
            document.getElementById(
                'admissionAppointment'
            );


        const appointmentHelp =
            document.getElementById(
                'appointmentHelp'
            );


        const roomSelect =
            document.getElementById(
                'admissionRoom'
            );


        const bedSelect =
            document.getElementById(
                'admissionBed'
            );


        const admissionAppointments =
            <?=json_encode(
                $admissionAppointments,
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES
            )?>;


        /*
        ----------------------------------------------------------
        PATIENT -> APPOINTMENTS
        ----------------------------------------------------------
        */

        function loadPatientAppointments()
        {

            if (!appointmentSelect) {
                return;
            }


            const mappingId =
                patientSelect
                    ? patientSelect.value
                    : '';


            appointmentSelect.innerHTML =
                '';


            /*
            No patient selected.
            */

            if (!mappingId) {

                const option =
                    document.createElement(
                        'option'
                    );


                option.value = '0';

                option.textContent =
                    'Select Patient First';


                appointmentSelect.appendChild(
                    option
                );


                if (appointmentHelp) {

                    appointmentHelp.textContent =
                        'Select a patient to see current/future appointments.';
                }


                return;
            }


            /*
            ONLY selected patient's appointments.
            */

            const patientAppointments =
                admissionAppointments.filter(
                    function (appointment) {

                        return String(
                            appointment.mapping_id
                        )
                        ===
                        String(
                            mappingId
                        );
                    }
                );


            /*
            Always show No Appointment.
            */

            const noAppointment =
                document.createElement(
                    'option'
                );


            noAppointment.value =
                '0';

            noAppointment.textContent =
                'No Appointment';


            appointmentSelect.appendChild(
                noAppointment
            );


            /*
            Add current/future appointments.
            */

            patientAppointments.forEach(
                function (appointment) {

                    const option =
                        document.createElement(
                            'option'
                        );


                    option.value =
                        appointment.appointment_id;


                    let text =
                        appointment.appointment_no
                        +
                        ' — '
                        +
                        (
                            appointment.doctor_name
                            ||
                            'Doctor'
                        )
                        +
                        ' — '
                        +
                        appointment.appointment_date;


                    if (
                        appointment.appointment_time
                    ) {

                        text +=
                            ' ' +
                            appointment.appointment_time;
                    }


                    option.textContent =
                        text;


                    appointmentSelect.appendChild(
                        option
                    );

                }
            );


            /*
            Add Appointment option.
            */

            const addAppointment =
                document.createElement(
                    'option'
                );


            addAppointment.value =
                '__add_appointment__';


            addAppointment.textContent =
                '＋ Add Appointment';


            appointmentSelect.appendChild(
                addAppointment
            );


            if (appointmentHelp) {

                if (
                    patientAppointments.length
                    > 0
                ) {

                    appointmentHelp.textContent =
                        patientAppointments.length +
                        ' current/future appointment(s) found.';

                } else {

                    appointmentHelp.textContent =
                        'This patient has no current/future appointment. You can add an appointment.';
                }
            }

        }


        /*
        ----------------------------------------------------------
        ROOM TYPE -> BEDS
        ----------------------------------------------------------
        */

        const admissionBedOptions = bedSelect
            ? Array.from(bedSelect.options)
                .filter(function (option) { return option.value; })
                .map(function (option) {
                    return {
                        value: option.value,
                        roomId: option.dataset.roomId || '',
                        text: option.textContent
                    };
                })
            : [];


        function filterBeds()
        {

            if (!roomSelect || !bedSelect) {
                return;
            }

            const selectedRoomId = roomSelect.value;
            const matchingBeds = admissionBedOptions.filter(function (bed) {
                return String(bed.roomId) === String(selectedRoomId);
            });

            bedSelect.innerHTML = '';

            const firstOption = document.createElement('option');
            firstOption.value = '';

            if (!selectedRoomId) {
                firstOption.textContent = 'Select Room First';
            } else if (!matchingBeds.length) {
                firstOption.textContent = 'No Available Bed in this Room';
            } else {
                firstOption.textContent = 'Select Available Bed';
            }

            bedSelect.appendChild(firstOption);

            matchingBeds.forEach(function (bed) {
                const option = document.createElement('option');
                option.value = bed.value;
                option.textContent = bed.text;
                option.dataset.roomId = bed.roomId;
                bedSelect.appendChild(option);
            });

            bedSelect.value = '';
            bedSelect.disabled = !selectedRoomId || matchingBeds.length === 0;
        }


        /*
        ----------------------------------------------------------
        PATIENT CHANGE
        ----------------------------------------------------------
        */

        if (patientSelect) {

            patientSelect.addEventListener(
                'change',
                loadPatientAppointments
            );
        }


        /*
        ----------------------------------------------------------
        ROOM CHANGE
        ----------------------------------------------------------
        */

        if (roomSelect) {

            roomSelect.addEventListener(
                'change',
                filterBeds
            );
        }


        /*
        ----------------------------------------------------------
        ADD APPOINTMENT
        ----------------------------------------------------------
        */

        if (appointmentSelect) {

            appointmentSelect.addEventListener(
                'change',
                function () {

                    if (
                        this.value
                        ===
                        '__add_appointment__'
                    ) {

                        const mappingId =
                            patientSelect
                                ? patientSelect.value
                                : '';


                        window.location.href =
                            'appointments.php'
                            +
                            (
                                mappingId
                                    ? '?mapping_id='
                                      +
                                      encodeURIComponent(
                                          mappingId
                                      )
                                    : ''
                            );
                    }

                }
            );
        }


        /*
        ----------------------------------------------------------
        FORM VALIDATION
        ----------------------------------------------------------
        */

        const form =
            document.getElementById(
                'admissionForm'
            );


        if (form) {

            form.addEventListener(
                'submit',
                function (event) {

                    if (
                        appointmentSelect &&
                        appointmentSelect.value
                        ===
                        '__add_appointment__'
                    ) {

                        event.preventDefault();

                        alert(
                            'Please add the appointment first, then create the admission.'
                        );

                        return;
                    }


                    if (
                        roomSelect &&
                        !roomSelect.value
                    ) {

                        event.preventDefault();

                        alert(
                            'Please select a room.'
                        );

                        roomSelect.focus();

                        return;
                    }


                    if (
                        bedSelect &&
                        (
                            bedSelect.disabled
                            ||
                            !bedSelect.value
                        )
                    ) {

                        event.preventDefault();

                        alert(
                            'Please select an available bed.'
                        );

                        bedSelect.focus();

                        return;
                    }

                }
            );
        }


        /*
        ----------------------------------------------------------
        EDIT ADMISSION
        ----------------------------------------------------------
        */
        const editAdmissionForm = document.getElementById('editAdmissionForm');
        const editPatient = document.getElementById('editAdmissionPatient');
        const editAppointment = document.getElementById('editAdmissionAppointment');
        const editRoomType = document.getElementById('editAdmissionRoomType');
        const editBed = document.getElementById('editAdmissionBed');
        const editDoctor = document.getElementById('editAdmissionDoctor');

        function editLoadAppointments(mappingId, selectedId) {
            if (!editAppointment) return;
            editAppointment.innerHTML = '<option value="0">No Appointment</option>';
            admissionAppointments.filter(a => String(a.mapping_id) === String(mappingId)).forEach(a => {
                const o = document.createElement('option');
                o.value = a.appointment_id;
                o.textContent = a.appointment_no + ' — ' + (a.doctor_name || 'Doctor') + ' — ' + a.appointment_date + (a.appointment_time ? ' ' + a.appointment_time : '');
                editAppointment.appendChild(o);
            });
            editAppointment.value = String(selectedId || 0);
        }

        function editFilterBeds(selectedBedId) {
            if (!editRoomType || !editBed) return;
            const type = editRoomType.value;
            let visible = 0;
            Array.from(editBed.options).forEach(o => {
                if (!o.value) return;
                const isSelected = String(o.value) === String(selectedBedId);
                const isAvailable = (o.dataset.bedStatus || 'Available') === 'Available';
                const show = (o.dataset.roomType === type && (isAvailable || isSelected)) || isSelected;
                o.hidden = !show;
                o.disabled = !isAvailable && !isSelected;
                if (show) visible++;
            });
            if (selectedBedId) editBed.value = String(selectedBedId);
            else editBed.value = '';
            editBed.disabled = !type || visible === 0;
        }

        document.querySelectorAll('[data-edit-admission]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const r = JSON.parse(this.dataset.editAdmission);
                const modal = document.getElementById('editAdmissionModal');
                if (!modal) return;
                document.getElementById('editAdmissionId').value = r.admission_id || '';
                editPatient.value = r.mapping_id || '';
                editLoadAppointments(r.mapping_id, r.appointment_id || 0);
                editRoomType.value = r.room_type || '';
                editFilterBeds(r.bed_id || '');
                editDoctor.value = r.admitted_by_doctor_id || '';
                document.getElementById('editAdmissionDate').value = (r.admission_date || '').replace(' ', 'T').slice(0,16);
                document.getElementById('editExpectedDischarge').value = r.expected_discharge_date || '';
                document.getElementById('editAdmissionStatus').value = r.admission_status || 'Admitted';
                document.getElementById('editAdmissionReason').value = r.admission_reason || '';
                document.getElementById('editAdmissionFee').value = r.admission_fee || 0;
                modal.classList.add('show');
            });
        });

        if (editPatient) editPatient.addEventListener('change', function(){ editLoadAppointments(this.value, 0); });
        if (editRoomType) editRoomType.addEventListener('change', function(){ editFilterBeds(''); });

        /*
        Initial state.
        */

        loadPatientAppointments();

        filterBeds();

    }
);

</script>

<?php endif; ?>



<?php if ($module === 'prescriptions'): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('prescriptionForm');
    const rows = document.getElementById('medicineRows');
    const empty = document.getElementById('medicineEmpty');
    const addBtn = document.getElementById('addMedicineBtn');
    const recordSelect = document.getElementById('prescription_record');
    const prescriptionId = document.getElementById('prescription_id');

    if (!form || !rows || !addBtn) {
        return;
    }

    const medicineCatalog =
        <?=json_encode(
            $medicineOptions,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        )?>;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function routeOption(value, selected) {
        return `
            <option
                value="${escapeHtml(value)}"
                ${selected === value ? 'selected' : ''}
            >
                ${escapeHtml(value)}
            </option>
        `;
    }

    function updateEmptyState() {
        empty.style.display =
            rows.querySelectorAll('.medicine-row').length
                ? 'none'
                : 'flex';
    }

    function findMedicine(name) {
        return medicineCatalog.find(function (item) {
            return String(item.medicine_name).toLowerCase() ===
                   String(name || '').toLowerCase();
        }) || null;
    }

    function addMedicineRow(item = {}) {

        const row = document.createElement('div');
        row.className = 'medicine-row';

        const selectedMedicine =
            findMedicine(item.medicine_name || '');

        const dosage =
            item.dosage ||
            selectedMedicine?.dosage ||
            '';

        const frequency =
            item.frequency ||
            selectedMedicine?.frequency ||
            '';

        const duration =
            item.duration ||
            selectedMedicine?.duration ||
            '';

        const route =
            item.route ||
            selectedMedicine?.route ||
            'Oral';

        const instructions =
            item.instructions ||
            selectedMedicine?.instructions ||
            '';

        const medicinePrice =
            Number(
                item.medicine_price ??
                selectedMedicine?.medicine_price ??
                0
            ) || 0;

        const options =
            medicineCatalog.map(function (medicine) {

                const selected =
                    String(medicine.medicine_name).toLowerCase() ===
                    String(item.medicine_name || '').toLowerCase();

                return `
                    <option
                        value="${escapeHtml(medicine.medicine_name)}"
                        ${selected ? 'selected' : ''}
                    >
                        ${escapeHtml(medicine.medicine_name)}
                    </option>
                `;

            }).join('');

        row.innerHTML = `
            <div class="medicine-grid">

                <label>
                    Medicine Name

                    <select
                        name="medicine_name[]"
                        class="medicine-name-select"
                        required
                    >
                        <option value="">
                            Select Medicine
                        </option>

                        ${options}
                    </select>
                </label>

                <label>
                    Dosage

                    <input
                        type="text"
                        name="dosage[]"
                        class="medicine-dosage"
                        required
                        value="${escapeHtml(dosage)}"
                        placeholder="e.g. 500 mg"
                    >
                </label>

                <label>
                    Frequency

                    <input
                        type="text"
                        name="frequency[]"
                        class="medicine-frequency"
                        required
                        value="${escapeHtml(frequency)}"
                        placeholder="e.g. Twice daily"
                    >
                </label>

                <label>
                    Duration

                    <input
                        type="text"
                        name="duration[]"
                        class="medicine-duration"
                        required
                        value="${escapeHtml(duration)}"
                        placeholder="e.g. 5 days"
                    >
                </label>

                <label>
                    Price (₹)

                    <input
                        type="number"
                        name="medicine_price[]"
                        class="medicine-price"
                        min="0"
                        step="0.01"
                        value="${medicinePrice.toFixed(2)}"
                        placeholder="0.00"
                    >
                </label>

                <label>
                    Route

                    <select
                        name="route[]"
                        class="medicine-route"
                        required
                    >
                        ${routeOption('Oral', route)}
                        ${routeOption('Injection', route)}
                        ${routeOption('Topical', route)}
                        ${routeOption('Inhalation', route)}
                        ${routeOption('Other', route)}
                    </select>
                </label>

                <label class="medicine-instructions">
                    Instructions

                    <input
                        type="text"
                        name="instructions[]"
                        class="medicine-instructions-input"
                        value="${escapeHtml(instructions)}"
                        placeholder="e.g. Take after food"
                    >
                </label>

                <button
                    type="button"
                    class="medicine-remove"
                >
                    <i class="fa-regular fa-trash-can"></i>
                    Remove
                </button>

            </div>
        `;

        rows.appendChild(row);

        const medicineSelect =
            row.querySelector('.medicine-name-select');

        medicineSelect.addEventListener('change', function () {

            const selected =
                findMedicine(this.value);

            if (!selected) {
                return;
            }

            row.querySelector('.medicine-dosage').value =
                selected.dosage || '';

            row.querySelector('.medicine-frequency').value =
                selected.frequency || '';

            row.querySelector('.medicine-duration').value =
                selected.duration || '';

            row.querySelector('.medicine-route').value =
                selected.route || 'Oral';

            row.querySelector('.medicine-instructions-input').value =
                selected.instructions || '';

            const priceInput =
                row.querySelector('.medicine-price');

            if (priceInput) {
                priceInput.value =
                    Number(selected.medicine_price || 0).toFixed(2);
            }
        });

        row.querySelector('.medicine-remove')
            .addEventListener('click', function () {
                row.remove();
                updateEmptyState();
            });

        updateEmptyState();
    }

    addBtn.addEventListener('click', function () {

        if (!medicineCatalog.length) {
            alert(
                'No medicines are available in prescription_items.'
            );
            return;
        }

        addMedicineRow();
    });

    /*
    New prescription.
    */
    document.querySelectorAll(
        '[data-open="prescriptionModal"]'
    ).forEach(function (button) {

        button.addEventListener('click', function () {

            if (this.dataset.edit) {
                return;
            }

            setTimeout(function () {

                prescriptionId.value = '';

                document.getElementById(
                    'prescription_no'
                ).value = '';

                rows.innerHTML = '';

                if (medicineCatalog.length) {
                    addMedicineRow();
                }

                updateEmptyState();

            }, 0);
        });
    });

    /*
    Edit existing prescription.
    */
    document.querySelectorAll(
        '[data-edit]'
    ).forEach(function (button) {

        const raw = button.dataset.edit;

        if (!raw) {
            return;
        }

        let record;

        try {
            record = JSON.parse(raw);
        } catch (e) {
            return;
        }

        if (!record || !record.prescription_id) {
            return;
        }

        button.addEventListener('click', function () {

            prescriptionId.value =
                record.prescription_id || '';

            document.getElementById(
                'prescription_record'
            ).value =
                record.medical_record_id || '';

            document.getElementById(
                'prescription_no'
            ).value =
                record.prescription_no || '';

            document.getElementById(
                'prescription_advice'
            ).value =
                record.advice || '';

            document.getElementById(
                'prescription_follow'
            ).value =
                record.follow_up_date || '';

            document.getElementById(
                'prescription_status'
            ).value =
                record.status || 'Active';

            rows.innerHTML = '';

            let items = record.medicine_items || [];

            if (typeof items === 'string') {
                try {
                    items = JSON.parse(items);
                } catch (e) {
                    items = [];
                }
            }

            if (Array.isArray(items) && items.length) {

                items.forEach(function (item) {
                    addMedicineRow(item);
                });

            } else if (medicineCatalog.length) {

                addMedicineRow();
            }

            updateEmptyState();
        });
    });

    /*
    Prevent selecting a medical record that already
    has a prescription in Add mode.
    */
    if (recordSelect) {

        recordSelect.addEventListener('change', function () {

            if (prescriptionId.value) {
                return;
            }

            const option =
                this.options[this.selectedIndex];

            if (
                option &&
                option.dataset.hasPrescription === '1'
            ) {

                alert(
                    'This medical record already has a prescription. Please edit the existing prescription.'
                );

                this.value = '';
            }
        });
    }

    form.addEventListener('submit', function (event) {

        const medicineRows =
            rows.querySelectorAll('.medicine-row');

        if (!medicineRows.length) {

            event.preventDefault();

            alert(
                'Please add at least one medicine.'
            );

            return;
        }

        if (!medicineCatalog.length) {

            event.preventDefault();

            alert(
                'No medicines are available in prescription_items.'
            );

            return;
        }

        if (
            !prescriptionId.value &&
            recordSelect
        ) {

            const option =
                recordSelect.options[
                    recordSelect.selectedIndex
                ];

            if (
                option &&
                option.dataset.hasPrescription === '1'
            ) {

                event.preventDefault();

                alert(
                    'This medical record already has a prescription. Please edit the existing prescription.'
                );
            }
        }
    });

    updateEmptyState();
});
</script>
<?php endif; ?>


<script src="js/hospital_modules.js"></script>

</body>

</html>