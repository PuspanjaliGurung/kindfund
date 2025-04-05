<?php
session_start();
require_once '../includes/auth.php';

if (!isAdmin()) {
    header("Location: ../index.php?page=login&error=access_denied");
    exit;
}