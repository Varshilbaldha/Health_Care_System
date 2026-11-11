<?php
require_once __DIR__ . '/../config/database.php';


function createHospitalDatabase($application_no)
{
    if (!preg_match('/^[a-zA-Z0-9]+$/', $application_no)) {
        throw new Exception("Invalid application number format.");
    }

    $database_name = "hospital_" . $application_no;
    $hospital_conn = db_connect(null);

    if (!$hospital_conn) {
        throw new Exception("Connection not established: " . mysqli_connect_errno());
    }

    $query = "CREATE DATABASE IF NOT EXISTS $database_name";

    if (!mysqli_query($hospital_conn, $query)) {
        throw new Exception("Error creating database: " . mysqli_error($hospital_conn));
    }
    if (!mysqli_select_db($hospital_conn, $database_name))
{
    throw new Exception(
        "Hospital database selection failed: "
        . mysqli_error($hospital_conn)
    );
}


$schema_file =
    __DIR__ . "/../database/hospital_schema.sql";


if (!file_exists($schema_file))
{
    throw new Exception(
        "Hospital schema file not found."
    );
}


$schema =
    file_get_contents($schema_file);


if ($schema === false)
{
    throw new Exception(
        "Unable to read hospital schema file."
    );
}


if (!mysqli_multi_query($hospital_conn, $schema))
{
    throw new Exception(
        "Hospital tables creation failed: "
        . mysqli_error($hospital_conn)
    );
}


do
{
    if ($result = mysqli_store_result($hospital_conn))
    {
        mysqli_free_result($result);
    }

}
while (
    mysqli_more_results($hospital_conn)
    &&
    mysqli_next_result($hospital_conn)
);
    mysqli_close($hospital_conn);

    return $database_name;

}
?>