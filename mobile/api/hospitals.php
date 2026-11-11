<?php

declare(strict_types=1);

session_start();

header('Content-Type: application/json; charset=utf-8');


/*====================================================
    JSON RESPONSE
====================================================*/

function hospitalResponse(
    bool $success,
    array $data = [],
    string $message = ''
): void {

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data'    => $data
        ],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*====================================================
    CONFIG
====================================================*/

$config_file =
    __DIR__ .
    '/../../includes/config.php';


if (!is_file($config_file)) {

    hospitalResponse(
        false,
        [],
        'Database configuration not found.'
    );
}


require_once $config_file;


if (
    !isset($conn)
    ||
    !($conn instanceof mysqli)
) {

    hospitalResponse(
        false,
        [],
        'Central database connection unavailable.'
    );
}


mysqli_set_charset(
    $conn,
    'utf8mb4'
);


/*====================================================
    SEARCH
====================================================*/

$search =
    trim(
        (string)(
            $_GET['search']
            ?? ''
        )
    );


$search =
    mb_substr(
        $search,
        0,
        100
    );


/*====================================================
    LOAD HOSPITALS
====================================================*/

if ($search !== '') {

    $sql = "

        SELECT

            hospital_id,
            hospital_name,
            hospital_type,
            hospital_email,
            hospital_phone,
            emergency_no,
            website,

            address1,
            address2,

            city,
            state,
            zip,

            database_name

        FROM hospital_registration

        WHERE hospital_name LIKE ?

           OR city LIKE ?

           OR state LIKE ?

           OR hospital_type LIKE ?

        ORDER BY
            hospital_name ASC

        LIMIT 50

    ";

    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    if (!$stmt) {

        hospitalResponse(
            false,
            [],
            'Unable to search hospitals.'
        );
    }


    $keyword =
        '%' .
        $search .
        '%';


    mysqli_stmt_bind_param(
        $stmt,
        'ssss',
        $keyword,
        $keyword,
        $keyword,
        $keyword
    );

}
else {

    $sql = "

        SELECT

            hospital_id,
            hospital_name,
            hospital_type,
            hospital_email,
            hospital_phone,
            emergency_no,
            website,

            address1,
            address2,

            city,
            state,
            zip,

            database_name

        FROM hospital_registration

        ORDER BY
            hospital_name ASC

        LIMIT 50

    ";


    $stmt =
        mysqli_prepare(
            $conn,
            $sql
        );


    if (!$stmt) {

        hospitalResponse(
            false,
            [],
            'Unable to load hospitals.'
        );
    }
}


/*====================================================
    EXECUTE
====================================================*/

if (
    !mysqli_stmt_execute(
        $stmt
    )
) {

    mysqli_stmt_close(
        $stmt
    );

    hospitalResponse(
        false,
        [],
        'Unable to load hospitals.'
    );
}


$result =
    mysqli_stmt_get_result(
        $stmt
    );


$hospitals = [];


if ($result) {

    while (
        $row =
        mysqli_fetch_assoc(
            $result
        )
    ) {

        $hospitals[] = [

            'hospital_id' =>
                (int)(
                    $row['hospital_id']
                    ?? 0
                ),

            'hospital_name' =>
                (string)(
                    $row['hospital_name']
                    ?? ''
                ),

            'hospital_type' =>
                (string)(
                    $row['hospital_type']
                    ?? ''
                ),

            'hospital_email' =>
                (string)(
                    $row['hospital_email']
                    ?? ''
                ),

            'hospital_phone' =>
                (string)(
                    $row['hospital_phone']
                    ?? ''
                ),

            'emergency_no' =>
                (string)(
                    $row['emergency_no']
                    ?? ''
                ),

            'website' =>
                (string)(
                    $row['website']
                    ?? ''
                ),

            'address1' =>
                (string)(
                    $row['address1']
                    ?? ''
                ),

            'address2' =>
                (string)(
                    $row['address2']
                    ?? ''
                ),

            'city' =>
                (string)(
                    $row['city']
                    ?? ''
                ),

            'state' =>
                (string)(
                    $row['state']
                    ?? ''
                ),

            'zip' =>
                (string)(
                    $row['zip']
                    ?? ''
                )

        ];
    }


    mysqli_free_result(
        $result
    );
}


mysqli_stmt_close(
    $stmt
);


/*====================================================
    RESPONSE
====================================================*/

hospitalResponse(
    true,
    [
        'total' =>
            count($hospitals),

        'hospitals' =>
            $hospitals
    ]
);