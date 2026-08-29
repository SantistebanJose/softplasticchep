<?php

/**
 * controllers/ticketPdf.php
 * Genera el ticket de una venta como PDF simple (sin SUNAT), formato
 * angosto tipo ticket de impresora térmica (80mm).
 *
 * Uso: controllers/ticketPdf.php?id=123
 *
 * Requiere la librería FPDF (un solo archivo, sin dependencias):
 *   https://www.fpdf.org/  -> descargar, descomprimir la carpeta "fpdf"
 * Colócala donde te acomode dentro del proyecto y ajusta el require_once
 * de abajo a esa ruta (por defecto asume /lib/fpdf/fpdf.php un nivel
 * arriba de controllers/).
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
require_once __DIR__ . '/../lib/fpdf/fpdf.php';
session_start();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Venta inválida.');
}

$conectar = conectar_oll_BD();

$result = executeQuery($conectar, "
    SELECT v.codigo, v.fecha_venta, v.monto_total, v.estado, v.js_items,
           v.cliente_ruc, p.razon_social AS cliente_nombre
    FROM venta v
    JOIN proveedor p ON p.ruc = v.cliente_ruc
    WHERE v.id = :id
", ['id' => $id]);

if (empty($result)) {
    http_response_code(404);
    die('Venta no encontrada.');
}

$venta = $result[0];
$items = json_decode($venta['js_items'], true) ?: [];

// FPDF no maneja UTF-8 directo; se pasa todo por utf8_decode (cp1252),
// suficiente para tildes/ñ en español.
function t(string $texto): string
{
    return utf8_decode($texto);
}

$anchoTicket = 80; // mm, típico de impresora térmica
$pdf = new FPDF('P', 'mm', [$anchoTicket, 200]);
$pdf->SetAutoPageBreak(true, 6);
$pdf->AddPage();
$pdf->SetMargins(5, 5, 5);
$anchoUtil = $anchoTicket - 10;

$pdf->SetFont('Courier', 'B', 12);
$pdf->Cell($anchoUtil, 6, t($venta['codigo']), 0, 1, 'C');

if ($venta['estado'] === 'anulada') {
    $pdf->SetFont('Courier', 'B', 10);
    $pdf->SetTextColor(180, 0, 0);
    $pdf->Cell($anchoUtil, 5, '*** VENTA ANULADA ***', 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->SetFont('Courier', '', 8);
$pdf->Cell($anchoUtil, 4, t('Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_venta']))), 0, 1);
$pdf->Cell($anchoUtil, 4, t('Cliente: ' . $venta['cliente_nombre']), 0, 1);
$pdf->Cell($anchoUtil, 4, t('RUC/DNI: ' . $venta['cliente_ruc']), 0, 1);

$pdf->Ln(1);
$pdf->Cell($anchoUtil, 0, str_repeat('-', 42), 0, 1);

$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell($anchoUtil - 24, 4, t('Producto'), 0, 0);
$pdf->Cell(8, 4, t('Cant'), 0, 0, 'R');
$pdf->Cell(16, 4, t('Subt.'), 0, 1, 'R');

$pdf->SetFont('Courier', '', 8);
foreach ($items as $it) {
    $nombre = $it['producto_codigo'] . ' - ' . $it['producto'];
    if (!empty($it['color']) && $it['color'] !== 'Sin color (registro legado)') {
        $nombre .= ' (' . $it['color'] . ')';
    }
    $lineas = explode("\n", wordwrap(t($nombre), 30, "\n", true));

    $pdf->Cell($anchoUtil - 24, 4, $lineas[0], 0, 0);
    $cantidadTxt = number_format((float)$it['cantidad'], 0) . ' ' . t($it['unidad_venta_corto'] ?? '');
    $pdf->Cell(8, 4, $cantidadTxt, 0, 0, 'R');
    $pdf->Cell(16, 4, 'S/ ' . number_format((float)$it['subtotal'], 2), 0, 1, 'R');

    for ($i = 1; $i < count($lineas); $i++) {
        $pdf->Cell($anchoUtil, 4, $lineas[$i], 0, 1);
    }
}

$pdf->Cell($anchoUtil, 0, str_repeat('-', 42), 0, 1);
$pdf->Ln(1);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell($anchoUtil, 6, t('TOTAL: S/ ' . number_format((float)$venta['monto_total'], 2)), 0, 1, 'R');

// 'I' = mostrar en el navegador (permite imprimir/guardar desde ahí).
$pdf->Output('I', $venta['codigo'] . '.pdf');