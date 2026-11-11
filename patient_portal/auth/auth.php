<?php

session_start();

if (!isset($_SESSION['patient_account_id']))
{
    header("Location: login.php");
    exit();
}

?>