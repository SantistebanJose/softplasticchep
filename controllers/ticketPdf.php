<?php

/**
 * controllers/ticketPdf.php
 * Genera el ticket de una venta como PDF simple (sin SUNAT), formato
 * angosto tipo ticket de impresora térmica (80mm).
 *
 * Uso: controllers/ticketPdf.php?id=123
 *
 * SIN DEPENDENCIAS: no usa FPDF ni ninguna librería externa. El PDF se
 * arma a mano (texto monoespaciado con fuentes estándar Courier, sin
 * necesidad de embeber ni instalar nada) — así no hay que instalar nada
 * en el servidor para que esto funcione.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
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

// =============================================================================
// GENERADOR DE PDF MÍNIMO (sin librerías)
// Arma un PDF de una sola página con texto monoespaciado (Courier /
// Courier-Bold, fuentes estándar de PDF: no requieren embeber archivos).
// Usa WinAnsiEncoding para que tildes/ñ se vean bien con solo utf8_decode().
// =============================================================================

function pdfEscaparTexto(string $s): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

// UTF-8 -> Latin-1/CP1252, que es lo que espera WinAnsiEncoding.
function pdfTexto(string $s): string
{
    return pdfEscaparTexto(utf8_decode($s));
}

// str_pad() cuenta BYTES, no caracteres visibles: con tildes/ñ (2 bytes en
// UTF-8) desalinea las columnas del ticket. Este helper cuenta caracteres
// reales (mb_strlen) para que "Colgador Osito" y "Pinza Palaníta" ocupen
// el mismo ancho de columna.
function mbPad(string $s, int $length, string $padType = STR_PAD_RIGHT): string
{
    $faltante = $length - mb_strlen($s, 'UTF-8');
    if ($faltante <= 0) return $s;
    $relleno = str_repeat(' ', $faltante);
    return $padType === STR_PAD_LEFT ? $relleno . $s : $s . $relleno;
}

/**
 * @param array $lineas cada línea: ['texto'=>string, 'size'=>int, 'bold'=>bool, 'alto'=>int (pt, opcional)]
 * @param float $anchoPt ancho de página en puntos
 */
function generarPdfTicket(array $lineas, float $anchoPt = 226.77): string
{
    $margen = 14.0;
    $alturaTotal = $margen * 2;
    foreach ($lineas as $ln) {
        $alturaTotal += $ln['alto'] ?? ($ln['size'] + 3);
    }
    $alturaPt = $alturaTotal;

    $contenido = "BT\n";
    $x0 = $margen;
    $y0 = $alturaPt - $margen;
    $contenido .= sprintf("1 0 0 1 %.2F %.2F Tm\n", $x0, $y0);

    foreach ($lineas as $ln) {
        $fuente = !empty($ln['bold']) ? '/F2' : '/F1';
        $contenido .= "{$fuente} {$ln['size']} Tf\n";
        $contenido .= '(' . pdfTexto($ln['texto']) . ") Tj\n";
        $alto = $ln['alto'] ?? ($ln['size'] + 3);
        $contenido .= sprintf("0 %.2F Td\n", -$alto);
    }
    $contenido .= "ET\n";

    $objetos = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
            $anchoPt,
            $alturaPt
        ),
        // 4 = stream de contenido, se maneja aparte
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>",
        6 => "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>",
    ];

    $out = "%PDF-1.4\n";
    $offsets = [];
    $totalObjs = 6;

    for ($i = 1; $i <= $totalObjs; $i++) {
        $offsets[$i] = strlen($out);
        if ($i === 4) {
            $out .= "4 0 obj\n<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream\nendobj\n";
        } else {
            $out .= "{$i} 0 obj\n{$objetos[$i]}\nendobj\n";
        }
    }

    $xrefOffset = strlen($out);
    $n = $totalObjs + 1;
    $out .= "xref\n0 {$n}\n";
    $out .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $totalObjs; $i++) {
        $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $out .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    return $out;
}

// =============================================================================
// ARMAR EL CONTENIDO DEL TICKET
// =============================================================================

const TICKET_ANCHO_CHARS = 42; // caracteres por línea a 8pt Courier en ~80mm

$lineas = [];

$lineas[] = ['texto' => $venta['codigo'], 'size' => 12, 'bold' => true, 'alto' => 16];

if ($venta['estado'] === 'anulada') {
    $lineas[] = ['texto' => '*** VENTA ANULADA ***', 'size' => 10, 'bold' => true, 'alto' => 13];
}

$lineas[] = ['texto' => 'Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])), 'size' => 8, 'alto' => 11];
$lineas[] = ['texto' => 'Cliente: ' . $venta['cliente_nombre'], 'size' => 8, 'alto' => 11];
$lineas[] = ['texto' => 'RUC/DNI: ' . $venta['cliente_ruc'], 'size' => 8, 'alto' => 13];
$lineas[] = ['texto' => str_repeat('-', TICKET_ANCHO_CHARS), 'size' => 8, 'alto' => 11];

// Encabezado de columnas: Producto (26) | Cant (6, der.) | Subtotal (10, der.)
$lineas[] = [
    'texto' => mbPad('Producto', 26) . mbPad('Cant', 6, STR_PAD_LEFT) . mbPad('Subt.', 10, STR_PAD_LEFT),
    'size'  => 8,
    'bold'  => true,
    'alto'  => 11,
];

foreach ($items as $it) {
    $nombre = $it['producto_codigo'] . ' - ' . $it['producto'];
    if (!empty($it['color']) && $it['color'] !== 'Sin color (registro legado)') {
        $nombre .= ' (' . $it['color'] . ')';
    }

    $cantidadTxt = number_format((float)$it['cantidad'], 0) . ' ' . ($it['unidad_venta_corto'] ?? '');
    $subtotalTxt = 'S/ ' . number_format((float)$it['subtotal'], 2);

    $wrap = explode("\n", wordwrap($nombre, 26, "\n", true));

    // Primera línea del nombre, junto con cantidad y subtotal.
    $lineas[] = [
        'texto' => mbPad(mb_substr($wrap[0], 0, 26, 'UTF-8'), 26)
                 . mbPad($cantidadTxt, 6, STR_PAD_LEFT)
                 . mbPad($subtotalTxt, 10, STR_PAD_LEFT),
        'size'  => 8,
        'alto'  => 11,
    ];
    // Líneas adicionales del nombre (si es largo), sin cantidad/subtotal.
    for ($i = 1; $i < count($wrap); $i++) {
        $lineas[] = ['texto' => $wrap[$i], 'size' => 8, 'alto' => 11];
    }
}

$lineas[] = ['texto' => str_repeat('-', TICKET_ANCHO_CHARS), 'size' => 8, 'alto' => 13];
$lineas[] = [
    'texto' => mbPad('TOTAL: S/ ' . number_format((float)$venta['monto_total'], 2), TICKET_ANCHO_CHARS, STR_PAD_LEFT),
    'size'  => 10,
    'bold'  => true,
    'alto'  => 14,
];

$pdf = generarPdfTicket($lineas);

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $venta['codigo'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;