<?php
require_once __DIR__ . '/SunatAuth.php';

// --- Reemplaza estos valores con los tuyos ---
$clientId     = '7345b0dc-4db3-4bcf-96b9-c0a7efa03d49';
$clientSecret = '39MOkYUEIjhBsAJkV3m/ag==';
$ruc          = '10464274397';        // el RUC de OMARY FASHION
$solUser      = 'OPERVALA';     // usuario con el que entras a SOL (sin el RUC)
$solPassword  = 'ddeastude';       // tu Clave SOL
$scope        = 'https://api-cpe.sunat.gob.pe';
// ----------------------------------------------

try {
    $auth  = new SunatAuth($clientId, $clientSecret, $ruc, $solUser, $solPassword, $scope);
    $token = $auth->getAccessToken();

    echo "Token obtenido correctamente:\n";
    echo $token . "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}