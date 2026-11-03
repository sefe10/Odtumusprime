<?php
require __DIR__ . '/app/auth.php';
$_SESSION = [];
session_destroy();
header('Location: /login.php');