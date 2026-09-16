<?php

/**
 * controllers/ticketPdf.php
 * Genera el comprobante de una venta como ticket térmico de 80 mm,
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
require_once __DIR__ . '/auditoria.php';   
session_start();

// Evita que notices/warnings accidentales se mezclen con los bytes del PDF.
ob_start();

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
           v.cliente_ruc, COALESCE(p.razon_social, 'Clientes Varios') AS cliente_nombre
    FROM venta v
    LEFT JOIN proveedor p ON p.ruc = v.cliente_ruc
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

// UTF-8 -> Windows-1252, que es lo que espera WinAnsiEncoding. utf8_decode()
// fue obsoleta en PHP 8.2 y sus avisos terminaban corrompiendo el PDF.
function pdfTexto(string $s): string
{
    $convertido = function_exists('iconv')
        ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $s)
        : mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    return pdfEscaparTexto($convertido === false ? $s : $convertido);
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
// ARMAR EL CONTENIDO DEL COMPROBANTE (TICKET TÉRMICO DE 80 mm)
// =============================================================================

// 80 mm equivalen a 226.77 puntos PDF. La altura se calcula según el
// contenido, para no dejar espacio de A4 ni cortar tickets con varios ítems.
$anchoPt = 226.77;
$margen  = 12.0;
$columnasTicket = 48;

function envolverTicket(string $texto, int $columnas): array
{
    $texto = trim($texto);
    if ($texto === '') return [];
    return explode("\n", wordwrap($texto, $columnas, "\n", true));
}

function centrarTicket(string $texto, int $columnas): string
{
    $espacios = max(0, intdiv($columnas - mb_strlen($texto, 'UTF-8'), 2));
    return str_repeat(' ', $espacios) . $texto;
}

// Primero contamos las líneas variables para calcular una altura exacta.
$lineasVariables = 0;
foreach ($items as $it) {
    $nombre = trim(($it['producto_codigo'] ?? '') . ' - ' . ($it['producto'] ?? ''));
    $color  = $it['color'] ?? '';
    if ($color === 'Sin color (registro legado)') $color = '';
    $lineasVariables += max(1, count(envolverTicket($nombre, $columnasTicket)));
    $lineasVariables += count(envolverTicket($color, $columnasTicket - 2));
    $lineasVariables += 1; // cantidad × precio = subtotal
}
$lineasVariables += count(envolverTicket($venta['cliente_nombre'], $columnasTicket - 9));
$altoPt = max(285.0, 265.0 + ($lineasVariables * 10.5));

$lineas = [];
$y = $altoPt - $margen; // cursor vertical, baja a medida que se agregan líneas

function agregarLinea(array &$lineas, float &$y, string $texto, int $size, bool $bold, float $margen, float $salto = 0)
{
    $lineas[] = ['texto' => $texto, 'size' => $size, 'bold' => $bold, 'x' => $margen, 'y' => $y];
    $y -= $salto > 0 ? $salto : ($size + 4);
}

// ── Logo + datos de la empresa ──────────────────────────────────────────────
$logo = cargarLogoComoRGB(EMPRESA_LOGO_PATH);

if ($logo !== null) {
    $maxAncho = 60.0;
    $maxAlto  = 35.0;
    $escala   = min($maxAncho / $logo['ancho'], $maxAlto / $logo['alto']);
    $logo['dispAncho'] = $logo['ancho'] * $escala;
    $logo['dispAlto']  = $logo['alto'] * $escala;
    $logo['x'] = ($anchoPt - $logo['dispAncho']) / 2;
    $logo['y'] = $y - $logo['dispAlto'];
    $y -= $logo['dispAlto'] + 5;
}

agregarLinea($lineas, $y, centrarTicket(EMPRESA_NOMBRE_COMERCIAL, $columnasTicket), 11, true, $margen, 13);
agregarLinea($lineas, $y, centrarTicket(EMPRESA_RAZON_SOCIAL, $columnasTicket), 7, false, $margen, 10);
agregarLinea($lineas, $y, centrarTicket('RUC: ' . EMPRESA_RUC, $columnasTicket), 7, false, $margen, 12);
agregarLinea($lineas, $y, str_repeat('-', $columnasTicket), 7, false, $margen, 10);

// ── Datos de la venta ────────────────────────────────────────────────────────
agregarLinea($lineas, $y, centrarTicket($venta['codigo'], $columnasTicket), 11, true, $margen, 14);

if ($venta['estado'] === 'anulada') {
    agregarLinea($lineas, $y, centrarTicket('*** VENTA ANULADA ***', $columnasTicket), 9, true, $margen, 12);
}

agregarLinea($lineas, $y, 'Fecha: ' . date('d/m/Y H:i', strtotime($venta['fecha_venta'])), 7, false, $margen, 10);
foreach (envolverTicket($venta['cliente_nombre'], $columnasTicket - 9) as $i => $linea) {
    agregarLinea($lineas, $y, ($i === 0 ? 'Cliente: ' : '         ') . $linea, 7, false, $margen, 10);
}
agregarLinea($lineas, $y, 'RUC/DNI: ' . $venta['cliente_ruc'], 7, false, $margen, 12);

agregarLinea($lineas, $y, str_repeat('-', $columnasTicket), 7, false, $margen, 10);

// ── Tabla de ítems ───────────────────────────────────────────────────────────
agregarLinea($lineas, $y, centrarTicket('DETALLE DE VENTA', $columnasTicket), 8, true, $margen, 11);

// El nombre del producto se imprime arriba para darle espacio. Los importes se
// muestran abajo en una tabla con bordes visibles, pensada para ticket de 80 mm.
$colCant     = 9;
$colPUnit    = 13;
$colSubtotal = 14;
$bordeTabla = '+' . str_repeat('-', $colCant) . '+' . str_repeat('-', $colPUnit) . '+' . str_repeat('-', $colSubtotal) . '+';
$encabezadoTabla = '|'
    . mbPad('CANT.', $colCant)
    . '|'
    . mbPad('P. UNIT.', $colPUnit)
    . '|'
    . mbPad('SUBTOTAL', $colSubtotal)
    . '|';
agregarLinea($lineas, $y, $bordeTabla, 7, false, $margen, 8);
agregarLinea($lineas, $y, $encabezadoTabla, 7, true, $margen, 9);
agregarLinea($lineas, $y, $bordeTabla, 7, false, $margen, 9);

foreach ($items as $it) {
    $nombre = $it['producto_codigo'] . ' - ' . $it['producto'];
    $color  = $it['color'] ?? '';
    if ($color === 'Sin color (registro legado)') $color = '-';

    $cantidadTxt = number_format((float)$it['cantidad'], 0) . ' ' . ($it['unidad_venta_corto'] ?? '');
    $precioTxt   = number_format((float)$it['precio_unitario'], 2);
    $subtotalTxt = number_format((float)$it['subtotal'], 2);

    foreach (envolverTicket($nombre, $columnasTicket) as $linea) {
        agregarLinea($lineas, $y, $linea, 8, true, $margen, 10);
    }
    foreach (envolverTicket($color, $columnasTicket - 2) as $linea) {
        agregarLinea($lineas, $y, '  ' . $linea, 7, false, $margen, 9);
    }
    $filaImportes = '|'
        . mbPad($cantidadTxt, $colCant)
        . '|'
        . mbPad('S/ ' . $precioTxt, $colPUnit, STR_PAD_LEFT)
        . '|'
        . mbPad('S/ ' . $subtotalTxt, $colSubtotal, STR_PAD_LEFT)
        . '|';
    agregarLinea($lineas, $y, $filaImportes, 7, false, $margen, 9);
    agregarLinea($lineas, $y, $bordeTabla, 7, false, $margen, 9);
}

agregarLinea($lineas, $y, str_repeat('-', $columnasTicket), 7, false, $margen, 13);

$totalTxt = 'TOTAL: S/ ' . number_format((float)$venta['monto_total'], 2);
agregarLinea($lineas, $y, centrarTicket($totalTxt, $columnasTicket), 11, true, $margen, 14);
agregarLinea($lineas, $y, centrarTicket('¡Gracias por su compra!', $columnasTicket), 7, false, $margen, 8);

$pdf = generarPdfComprobante($lineas, $anchoPt, $altoPt, $logo);

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $venta['codigo'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
