<?php

/**
 * controllers/ticketPdf.php
 * Genera el comprobante de una venta como PDF de página completa (A4),
 * con logo y datos de la empresa (sin SUNAT, es un ticket simple interno).
 *
 * Uso: controllers/ticketPdf.php?id=123
 *
 * SIN DEPENDENCIAS: no usa FPDF ni ninguna librería externa. El PDF se
 * arma a mano (texto monoespaciado con fuentes estándar Courier, y el
 * logo embebido como imagen RGB usando la extensión GD, que ya viene
 * incluida en casi cualquier instalación de PHP). No hay nada que
 * instalar en el servidor para que esto funcione.
 */

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/executeQuery.php';
session_start();

// =============================================================================
// DATOS DE LA EMPRESA (fijos — no vienen de la base de datos)
// =============================================================================

const EMPRESA_NOMBRE_COMERCIAL = 'Chepito Plastic';
const EMPRESA_RAZON_SOCIAL     = 'CHEPITO PLASTIC S.A.C.';
const EMPRESA_RUC              = '20613311620';
const EMPRESA_LOGO_PATH        = __DIR__ . '/../assets/img/logo.png';

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
 * Carga un PNG (con o sin transparencia) y devuelve sus bytes RGB crudos
 * (3 bytes por píxel, sin canal alfa) mezclando lo transparente sobre
 * fondo blanco. Así no hace falta un SMask aparte en el PDF, que
 * complicaría bastante el generador para lo que es un simple logo.
 * Devuelve null si el archivo no existe o no se puede leer (el ticket
 * igual se genera, solo que sin logo).
 */
function cargarLogoComoRGB(string $path): ?array
{
    if (!is_file($path) || !function_exists('imagecreatefrompng')) {
        return null;
    }
    $img = @imagecreatefrompng($path);
    if ($img === false) {
        return null;
    }
    if (!imageistruecolor($img)) {
        imagepalettetotruecolor($img);
    }

    $w = imagesx($img);
    $h = imagesy($img);
    $raw = '';

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($img, $x, $y);
            $alphaGD = ($rgba >> 24) & 0x7F; // GD: 0 = opaco, 127 = transparente
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;

            $factor = (127 - $alphaGD) / 127; // fracción de opacidad, 0..1
            $rr = (int) round($r * $factor + 255 * (1 - $factor));
            $gg = (int) round($g * $factor + 255 * (1 - $factor));
            $bb = (int) round($b * $factor + 255 * (1 - $factor));

            $raw .= chr($rr) . chr($gg) . chr($bb);
        }
    }
    imagedestroy($img);

    return ['ancho' => $w, 'alto' => $h, 'rgb' => $raw];
}

/**
 * @param array      $lineas cada línea: ['texto'=>string,'size'=>int,'bold'=>bool,'x'=>float,'y'=>float]
 *                    (x,y en pt, origen abajo-izquierda, como en PDF)
 * @param array|null $logo   ['ancho','alto','rgb'] (de cargarLogoComoRGB) + 'x','y','dispAncho','dispAlto'
 */
function generarPdfComprobante(array $lineas, float $anchoPt, float $altoPt, ?array $logo = null): string
{
    $contenido = '';

    if ($logo !== null) {
        $contenido .= "q\n";
        $contenido .= sprintf(
            "%.2F 0 0 %.2F %.2F %.2F cm\n",
            $logo['dispAncho'],
            $logo['dispAlto'],
            $logo['x'],
            $logo['y']
        );
        $contenido .= "/Im1 Do\nQ\n";
    }

    $contenido .= "BT\n";
    foreach ($lineas as $ln) {
        $fuente = !empty($ln['bold']) ? '/F2' : '/F1';
        $contenido .= "{$fuente} {$ln['size']} Tf\n";
        $contenido .= sprintf("1 0 0 1 %.2F %.2F Tm\n", $ln['x'], $ln['y']);
        $contenido .= '(' . pdfTexto($ln['texto']) . ") Tj\n";
    }
    $contenido .= "ET\n";

    $objetos = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
    ];

    $recursos = '/Font << /F1 5 0 R /F2 6 0 R >>';
    if ($logo !== null) {
        $recursos .= ' /XObject << /Im1 7 0 R >>';
    }
    $objetos[3] = sprintf(
        "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << %s >> /Contents 4 0 R >>",
        $anchoPt,
        $altoPt,
        $recursos
    );
    $objetos[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>";
    $objetos[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>";

    $totalObjs = 6;
    $imagenComprimida = null;
    if ($logo !== null) {
        $imagenComprimida = gzcompress($logo['rgb'], 6);
        $objetos[7] = sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>",
            $logo['ancho'],
            $logo['alto'],
            strlen($imagenComprimida)
        );
        $totalObjs = 7;
    }

    $out = "%PDF-1.4\n";
    $offsets = [];

    for ($i = 1; $i <= $totalObjs; $i++) {
        $offsets[$i] = strlen($out);
        if ($i === 4) {
            $out .= "4 0 obj\n<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream\nendobj\n";
        } elseif ($i === 7) {
            $out .= "7 0 obj\n{$objetos[7]}\nstream\n" . $imagenComprimida . "\nendstream\nendobj\n";
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
// ARMAR EL CONTENIDO DEL COMPROBANTE (A4)
// =============================================================================

$anchoPt = 595.28; // A4
$altoPt  = 841.89;
$margen  = 40.0;
$anchoUtil = $anchoPt - 2 * $margen;

$lineas = [];
$y = $altoPt - $margen; // cursor vertical, baja a medida que se agregan líneas

function agregarLinea(array &$lineas, float &$y, string $texto, int $size, bool $bold, float $margen, float $salto = 0)
{
    $lineas[] = ['texto' => $texto, 'size' => $size, 'bold' => $bold, 'x' => $margen, 'y' => $y];
    $y -= $salto > 0 ? $salto : ($size + 4);
}

// ── Logo + datos de la empresa ──────────────────────────────────────────────
$logo = cargarLogoComoRGB(EMPRESA_LOGO_PATH);
$xTextoEmpresa = $margen;

if ($logo !== null) {
    $maxAncho = 110.0;
    $maxAlto  = 50.0;
    $escala   = min($maxAncho / $logo['ancho'], $maxAlto / $logo['alto']);
    $logo['dispAncho'] = $logo['ancho'] * $escala;
    $logo['dispAlto']  = $logo['alto'] * $escala;
    $logo['x'] = $margen;
    $logo['y'] = $y - $logo['dispAlto'];

    $xTextoEmpresa = $margen + $logo['dispAncho'] + 15;
}

$yEmpresa = $y;
$lineas[] = ['texto' => EMPRESA_NOMBRE_COMERCIAL, 'size' => 14, 'bold' => true, 'x' => $xTextoEmpresa, 'y' => $yEmpresa];
$yEmpresa -= 16;
$lineas[] = ['texto' => EMPRESA_RAZON_SOCIAL, 'size' => 9, 'bold' => false, 'x' => $xTextoEmpresa, 'y' => $yEmpresa];
$yEmpresa -= 12;
$lineas[] = ['texto' => 'RUC: ' . EMPRESA_RUC, 'size' => 9, 'bold' => false, 'x' => $xTextoEmpresa, 'y' => $yEmpresa];
$yEmpresa -= 12;

// El cursor baja lo que ocupe más alto: el bloque de texto de la empresa
// o el logo (si el logo es más alto que el texto, el punto de partida
// del resto del documento respeta esa altura).
$y = min($yEmpresa, $logo !== null ? ($y - $logo['dispAlto']) : $yEmpresa) - 10;

agregarLinea($lineas, $y, str_repeat('-', 95), 8, false, $margen, 14);

// ── Datos de la venta ────────────────────────────────────────────────────────
agregarLinea($lineas, $y, $venta['codigo'], 14, true, $margen, 18);

if ($venta['estado'] === 'anulada') {
    agregarLinea($lineas, $y, '*** VENTA ANULADA ***', 11, true, $margen, 15);
}

agregarLinea($lineas, $y, 'Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])), 9, false, $margen, 13);
agregarLinea($lineas, $y, 'Cliente: ' . $venta['cliente_nombre'], 9, false, $margen, 13);
agregarLinea($lineas, $y, 'RUC/DNI: ' . $venta['cliente_ruc'], 9, false, $margen, 16);

agregarLinea($lineas, $y, str_repeat('-', 95), 8, false, $margen, 14);

// ── Tabla de ítems ───────────────────────────────────────────────────────────
// Columnas (en caracteres, fuente Courier 9pt): Producto 45 | Color 15 |
// Cant 8 (der.) | P.Unit 12 (der.) | Subtotal 14 (der.) ≈ 94 caracteres,
// entra cómodo en el ancho útil de A4.
$colProducto = 45;
$colColor    = 15;
$colCant     = 8;
$colPUnit    = 12;
$colSubtotal = 14;

$encabezado = mbPad('Producto', $colProducto)
            . mbPad('Color', $colColor)
            . mbPad('Cant', $colCant, STR_PAD_LEFT)
            . mbPad('P.Unit', $colPUnit, STR_PAD_LEFT)
            . mbPad('Subtotal', $colSubtotal, STR_PAD_LEFT);
agregarLinea($lineas, $y, $encabezado, 9, true, $margen, 13);

foreach ($items as $it) {
    $nombre = $it['producto_codigo'] . ' - ' . $it['producto'];
    $color  = $it['color'] ?? '';
    if ($color === 'Sin color (registro legado)') $color = '-';

    $cantidadTxt = number_format((float)$it['cantidad'], 0) . ' ' . ($it['unidad_venta_corto'] ?? '');
    $precioTxt   = number_format((float)$it['precio_unitario'], 2);
    $subtotalTxt = number_format((float)$it['subtotal'], 2);

    $wrapNombre = explode("\n", wordwrap($nombre, $colProducto, "\n", true));
    $wrapColor  = explode("\n", wordwrap($color, $colColor, "\n", true));
    $filasTexto = max(count($wrapNombre), count($wrapColor));

    for ($i = 0; $i < $filasTexto; $i++) {
        $nombreLinea = mbPad($wrapNombre[$i] ?? '', $colProducto);
        $colorLinea  = mbPad($wrapColor[$i] ?? '', $colColor);
        if ($i === 0) {
            $linea = $nombreLinea . $colorLinea
                   . mbPad($cantidadTxt, $colCant, STR_PAD_LEFT)
                   . mbPad($precioTxt, $colPUnit, STR_PAD_LEFT)
                   . mbPad($subtotalTxt, $colSubtotal, STR_PAD_LEFT);
        } else {
            $linea = $nombreLinea . $colorLinea;
        }
        agregarLinea($lineas, $y, $linea, 9, false, $margen, 12);
    }
}

agregarLinea($lineas, $y, str_repeat('-', 95), 8, false, $margen, 18);

$totalTxt = mbPad('TOTAL: S/ ' . number_format((float)$venta['monto_total'], 2), 95, STR_PAD_LEFT);
agregarLinea($lineas, $y, $totalTxt, 12, true, $margen, 16);

$pdf = generarPdfComprobante($lineas, $anchoPt, $altoPt, $logo);

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $venta['codigo'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;