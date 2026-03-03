<?php
$host = 'localhost';
$dbname = 'cabinet_carre';
$user = 'root';
$pass = '';

try {
    $bdd = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>