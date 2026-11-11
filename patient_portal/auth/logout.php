<?php

declare(strict_types=1);

require_once "../includes/config.php";

session_unset();

session_destroy();

header("Location: login.php");

exit();

?>