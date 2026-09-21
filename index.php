<?php

session_start();

if (isset($_SESSION['id'])) {
    header('Location: private/dashboard.php');
} else {
    header('Location: login.php');
}

exit;