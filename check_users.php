<?php
$host='bi.back-mrsoft.com';
$port='5432';
$dbname='bdplasticche';
$user='usrweb';
$pass='admin-Captaian*1278871/&%561652';
try {
    $pdo = new PDO('pgsql:host='.$host.';port='.$port.';dbname='.$dbname, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $stmt = $pdo->query('SELECT id, user_, pass_, deleted_at FROM usuario LIMIT 5');
    $users = $stmt->fetchAll();
    echo json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo 'ERROR: ' . $e->getMessage();
}
?>
