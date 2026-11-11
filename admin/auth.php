<?php

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../auth/login.php");
    exit();
}


if(!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'hospital_admin') {
   die("Access Denied: You do not have permission to access this page.");       
        
}
?>