<?php

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=e_issuance', 'root', '');
    echo "PHP PDO Connected to MySQL successfully!\n";
    $result = $pdo->query('SHOW DATABASES;');
    echo "Databases: \n";
    foreach ($result as $row) {
        echo '  - '.$row[0]."\n";
    }
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
