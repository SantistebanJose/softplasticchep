<?php
declare(strict_types=1);

$archivoConfigTipoCambio = __DIR__ . '/config_tipo_cambio.php';
if (is_file($archivoConfigTipoCambio)) {
    require_once $archivoConfigTipoCambio;
}

/** Consulta el tipo de cambio oficial en el servidor; la clave nunca llega al navegador. */
function consultarTipoCambio(string $fecha, string $moneda): array
{
    $moneda = strtoupper(trim($moneda));
    if (!in_array($moneda, ['USD', 'EUR'], true)) {
        throw new InvalidArgumentException('El tipo de cambio oficial solo está disponible para USD y EUR.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        throw new InvalidArgumentException('La fecha del tipo de cambio no es válida.');
    }
    $token = defined('CHEQUEA_API_TOKEN')
        ? trim((string) CHEQUEA_API_TOKEN)
        : trim((string) getenv('CHEQUEA_API_TOKEN'));
    if ($token === '') {
        throw new RuntimeException('Falta configurar CHEQUEA_API_TOKEN en el servidor.');
    }

    $url = 'https://api.chequea.pe/api/v1/tipo-cambio?' . http_build_query([
        'fecha' => $fecha,
        'moneda' => $moneda,
    ]);
    $context = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => 10,
        'ignore_errors' => true,
        'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $m)) $status = (int) $m[1];
    }
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($data) || !isset($data['compra'], $data['venta'])) {
        error_log('Chequea tipo-cambio respondió HTTP ' . $status . '.');
        throw new RuntimeException('No se pudo consultar el tipo de cambio oficial. Revisa la clave API y vuelve a intentar.');
    }
    return $data;
}
