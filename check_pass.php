<?php
$hash = '$2y$12$NPO3KVaL/5H.5DFBJ/F/dek5rSQ3.UNIJCNH/ffgNT41C7s0WlkG';
$candidates = ['admin', '123456', 'password', 'chepito', 'Plastico2026', 'Admin123', '1234', 'admin123'];
foreach ($candidates as $pw) {
    if (password_verify($pw, $hash)) {
        echo "MATCH: $pw\n";
    }
}
?>
