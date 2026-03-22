<?php
$dsn = 'mysql:host=backend;dbname=whodat;charset=utf8mb4';
$user = 'whodatuser';
$pass = 'whodatpass';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

?>