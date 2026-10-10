<?php

/**
 * controllers/ticketPdf.php
 * Genera el comprobante de una venta como ticket térmico de 80 mm,
 * con logo y datos de la empresa, con la estructura de la representación
 * impresa de SUNAT (boleta/factura electrónica) lista para enlazar a un facturador.
 *
 * Uso: controllers/ticketPdf.php?id=123
 *
 * SIN DEPENDENCIAS: no usa FPDF ni ninguna librería externa. El PDF se
 * arma a mano (fuentes estándar Helvetica, líneas/cajas vectoriales y el
 * logo embebido como imagen RGB usando la extensión GD, que ya viene
 * incluida en casi cualquier instalación de PHP).
 *
 * Diseño pensado para impresoras térmicas: solo negro y blanco (nada de
 * grises que se vean punteados), texto alineado por ancho real de letra
 * (no por cantidad de caracteres) y cajas negras para lo importante.
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
const EMPRESA_DIRECCION        = '';   // OBLIGATORIO para SUNAT: domicilio fiscal / establecimiento
const EMPRESA_TELEFONO         = '';   // opcional: ej. '987 654 321'
const EMPRESA_LOGO_PATH        = __DIR__ . '/../assets/img/logo.png';

// Tributación (para el desglose que pide la representación impresa SUNAT)
const IGV_TASA              = 0.18;
const PRECIOS_INCLUYEN_IGV  = true;   // true: el precio/monto_total ya trae IGV
const SUNAT_URL_CONSULTA    = '';     // ej. 'www.tufacturador.pe/consulta' (la da tu OSE/facturador)

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

/**
 * Convierte los bytes de una imagen (PNG/JPG/GIF/WebP, se detecta por el
 * contenido, no por la extensión) en RGB crudo, reducida a $maxPx de ancho
 * como máximo y mezclando la transparencia sobre fondo blanco.
 * Devuelve null si GD no está habilitado o la imagen no se puede leer.
 */
function ticketImagenRGB(string $bytes, int $maxPx = 384): ?array
{
    if ($bytes === '' || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        return null;
    }
    if (!imageistruecolor($src)) {
        imagepalettetotruecolor($src);
    }

    $w0 = imagesx($src);
    $h0 = imagesy($src);
    $w  = min($w0, $maxPx);
    $h  = max(1, (int) round($h0 * ($w / $w0)));

    // Reducción conservando la transparencia original
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $w0, $h0);
    imagedestroy($src);

    $raw = '';
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba    = imagecolorat($img, $x, $y);
            $alphaGD = ($rgba >> 24) & 0x7F; // GD: 0 = opaco, 127 = transparente
            $factor  = (127 - $alphaGD) / 127;
            $raw .= chr((int) round((($rgba >> 16) & 0xFF) * $factor + 255 * (1 - $factor)))
                  . chr((int) round((($rgba >> 8) & 0xFF) * $factor + 255 * (1 - $factor)))
                  . chr((int) round(($rgba & 0xFF) * $factor + 255 * (1 - $factor)));
        }
    }
    imagedestroy($img);

    return ['ancho' => $w, 'alto' => $h, 'rgb' => $raw];
}

/** Carga el logo desde disco. Si falla, deja el motivo en el log de errores de PHP. */
function cargarLogoComoRGB(string $path): ?array
{
    if (!function_exists('imagecreatefromstring')) {
        error_log('ticketPdf: la extensión GD no está habilitada (activa extension=gd en php.ini y reinicia Apache).');
        return null;
    }
    if (!is_file($path)) {
        error_log('ticketPdf: no existe el logo en ' . $path);
        return null;
    }
    $logo = ticketImagenRGB((string) file_get_contents($path));
    if ($logo === null) {
        error_log('ticketPdf: no se pudo leer la imagen del logo (' . $path . ').');
    }
    return $logo;
}

/**
 * Lienzo de ticket. Se dibuja de arriba hacia abajo con un cursor ($y,
 * medido desde el borde superior). Al final se conoce la altura exacta
 * y se arma el PDF con esa altura: ni sobra papel ni se corta nada.
 */
final class TicketPdf
{
    public float $ancho;
    public float $margen;
    public float $y = 0.0;

    private string $ops = '';
    private array $imagenes = [];

    // Anchos de Helvetica (por 1000 unidades) para los caracteres ASCII 32..126
    private const ANCHO_REG = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];
    // Anchos de Helvetica-Bold
    private const ANCHO_BOLD = [
        278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611,
        975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556,
        333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611,
        611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584,
    ];
    // Letra base de cada carácter acentuado (0xC0..0xFF en Windows-1252),
    // para medir á, é, ñ, Ó, etc. con el ancho de su letra sin tilde.
    private const MAPA_ACENTOS =
        'AAAAAAACEEEEIIIIDNOOOOO+OUUUUYPB' .
        'aaaaaaaceeeeiiiidnooooo+ouuuuypy';

    public function __construct(float $ancho, float $margen)
    {
        $this->ancho  = $ancho;
        $this->margen = $margen;
    }

    // ── Texto ────────────────────────────────────────────────────────────────

    /** UTF-8 -> Windows-1252 (lo que espera WinAnsiEncoding). */
    public static function cp1252(string $s): string
    {
        $r = function_exists('iconv') ? @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) : false;
        if ($r === false && function_exists('mb_convert_encoding')) {
            $r = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        }
        return $r === false ? $s : $r;
    }

    private static function anchoCar(int $b, bool $bold): int
    {
        $tabla = $bold ? self::ANCHO_BOLD : self::ANCHO_REG;
        if ($b >= 32 && $b <= 126) {
            return $tabla[$b - 32];
        }
        if ($b >= 0xC0) {
            $mapa = self::MAPA_ACENTOS;
            return $tabla[ord($mapa[$b - 0xC0]) - 32];
        }
        if ($b === 0xA1) return $tabla[ord('!') - 32]; // ¡
        if ($b === 0xBF) return $tabla[ord('?') - 32]; // ¿
        return 556;
    }

    /** Ancho en puntos de un texto (UTF-8) con la fuente/tamaño dados. */
    public function medir(string $texto, float $size, bool $bold = false): float
    {
        $s = self::cp1252($texto);
        $total = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $total += self::anchoCar(ord($s[$i]), $bold);
        }
        return $total * $size / 1000;
    }

    /** Parte un texto en líneas que quepan en $max puntos de ancho. */
    public function envolver(string $texto, float $size, bool $bold, float $max): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return [];
        }
        $out = [];
        $actual = '';
        foreach (preg_split('/\s+/u', $texto) as $palabra) {
            $prueba = $actual === '' ? $palabra : $actual . ' ' . $palabra;
            if ($this->medir($prueba, $size, $bold) <= $max) {
                $actual = $prueba;
                continue;
            }
            if ($actual !== '') {
                $out[] = $actual;
                $actual = '';
            }
            // Palabra más ancha que la línea: se parte por caracteres
            while (mb_strlen($palabra, 'UTF-8') > 1 && $this->medir($palabra, $size, $bold) > $max) {
                $n = mb_strlen($palabra, 'UTF-8') - 1;
                while ($n > 1 && $this->medir(mb_substr($palabra, 0, $n, 'UTF-8'), $size, $bold) > $max) {
                    $n--;
                }
                $out[]   = mb_substr($palabra, 0, $n, 'UTF-8');
                $palabra = mb_substr($palabra, $n, null, 'UTF-8');
            }
            $actual = $palabra;
        }
        if ($actual !== '') {
            $out[] = $actual;
        }
        return $out;
    }

    /** Reduce el tamaño de letra hasta que el texto quepa en $max puntos. */
    public function ajustar(string $texto, float $size, bool $bold, float $max, float $minimo = 7.0): float
    {
        while ($size > $minimo && $this->medir($texto, $size, $bold) > $max) {
            $size -= 0.5;
        }
        return $size;
    }

    /**
     * Escribe una línea en la posición actual ($y = borde superior de la
     * línea; NO avanza el cursor). align: left | center | right.
     * $x1/$x2 delimitan la zona (por defecto, todo el ancho útil).
     */
    public function texto(
        string $texto,
        float $size,
        bool $bold = false,
        string $align = 'left',
        ?float $x1 = null,
        ?float $x2 = null,
        bool $blanco = false
    ): void {
        $x1 = $x1 ?? $this->margen;
        $x2 = $x2 ?? ($this->ancho - $this->margen);

        $s = self::cp1252($texto);
        $w = $this->medir($texto, $size, $bold);
        if ($align === 'center') {
            $x = $x1 + (($x2 - $x1) - $w) / 2;
        } elseif ($align === 'right') {
            $x = $x2 - $w;
        } else {
            $x = $x1;
        }

        $baseline = $this->y + $size * 0.75; // la altura de mayúscula ≈ 0.72 del tamaño
        $esc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);

        $this->ops .= sprintf(
            "BT %s /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $blanco ? '1 g' : '0 g',
            $bold ? 'F2' : 'F1',
            $size,
            $x,
            -$baseline,
            $esc
        );
    }

    /** Párrafo con salto de línea automático; avanza el cursor. */
    public function parrafo(
        string $texto,
        float $size,
        bool $bold = false,
        string $align = 'left',
        float $interlineado = 0.0,
        ?float $x1 = null,
        ?float $x2 = null
    ): void {
        $x1 = $x1 ?? $this->margen;
        $x2 = $x2 ?? ($this->ancho - $this->margen);
        $interlineado = $interlineado > 0 ? $interlineado : $size + 2.5;

        foreach ($this->envolver($texto, $size, $bold, $x2 - $x1) as $linea) {
            $this->texto($linea, $size, $bold, $align, $x1, $x2);
            $this->y += $interlineado;
        }
    }

    // ── Gráficos vectoriales ─────────────────────────────────────────────────

    public function lineaH(float $yTop, bool $punteada = false, float $grosor = 0.7): void
    {
        $this->ops .= sprintf(
            "q 0 G %.2F w %s 0 d %.2F %.2F m %.2F %.2F l S Q\n",
            $grosor,
            $punteada ? '[2.5 2]' : '[]',
            $this->margen,
            -$yTop,
            $this->ancho - $this->margen,
            -$yTop
        );
    }

    public function rectRelleno(float $x, float $yTop, float $w, float $h): void
    {
        $this->ops .= sprintf("q 0 g %.2F %.2F %.2F %.2F re f Q\n", $x, -($yTop + $h), $w, $h);
    }

    public function rectBorde(float $x, float $yTop, float $w, float $h, float $grosor = 1.0): void
    {
        $this->ops .= sprintf(
            "q 0 G %.2F w %.2F %.2F %.2F %.2F re S Q\n",
            $grosor,
            $x + $grosor / 2,
            -($yTop + $h) + $grosor / 2,
            $w - $grosor,
            $h - $grosor
        );
    }

    /** Imagen centrada, escalada para caber en $maxAncho x $maxAlto; avanza el cursor. */
    public function imagen(array $img, float $maxAncho, float $maxAlto): void
    {
        $escala = min($maxAncho / $img['ancho'], $maxAlto / $img['alto']);
        $dw = $img['ancho'] * $escala;
        $dh = $img['alto'] * $escala;
        $x  = ($this->ancho - $dw) / 2;

        $this->imagenes[] = $img;
        $this->ops .= sprintf(
            "q %.2F 0 0 %.2F %.2F %.2F cm /Im%d Do Q\n",
            $dw,
            $dh,
            $x,
            -($this->y + $dh),
            count($this->imagenes)
        );
        $this->y += $dh;
    }

    // ── Armado del PDF ───────────────────────────────────────────────────────

    public function generar(float $margenInferior = 16.0): string
    {
        $alto = $this->y + $margenInferior;

        // El origen se traslada a la esquina superior izquierda: así todas las
        // coordenadas de arriba son simplemente -y.
        $contenido = sprintf("q 1 0 0 1 0 %.2F cm\n", $alto) . $this->ops . "Q\n";

        $xobj = '';
        foreach ($this->imagenes as $i => $_) {
            $xobj .= sprintf('/Im%d %d 0 R ', $i + 1, 7 + $i);
        }
        $recursos = '/Font << /F1 5 0 R /F2 6 0 R >>' . ($xobj !== '' ? " /XObject << {$xobj}>>" : '');

        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << %s >> /Contents 4 0 R >>',
                $this->ancho,
                $alto,
                $recursos
            ),
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            6 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $streams = [];
        foreach ($this->imagenes as $i => $img) {
            $datos = gzcompress($img['rgb'], 6);
            $objetos[7 + $i] = sprintf(
                '<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>',
                $img['ancho'],
                $img['alto'],
                strlen($datos)
            );
            $streams[7 + $i] = $datos;
        }
        $totalObjs = 6 + count($this->imagenes);

        $out = "%PDF-1.4\n";
        $offsets = [];
        for ($i = 1; $i <= $totalObjs; $i++) {
            $offsets[$i] = strlen($out);
            if ($i === 4) {
                $out .= "4 0 obj\n<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream\nendobj\n";
            } elseif (isset($streams[$i])) {
                $out .= "{$i} 0 obj\n{$objetos[$i]}\nstream\n" . $streams[$i] . "\nendstream\nendobj\n";
            } else {
                $out .= "{$i} 0 obj\n{$objetos[$i]}\nendobj\n";
            }
        }

        $xrefOffset = strlen($out);
        $n = $totalObjs + 1;
        $out .= "xref\n0 {$n}\n0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size {$n} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $out;
    }
}

/** Fila "Etiqueta: valor" con el valor alineado en columna y con salto de línea. */
function ticketFilaDato(TicketPdf $t, string $etiqueta, string $valor, float $xValor): void
{
    $lineas = $t->envolver($valor, 8, false, $t->ancho - $t->margen - $xValor);
    if (!$lineas) {
        return;
    }
    $t->texto($etiqueta, 8, true);
    foreach ($lineas as $linea) {
        $t->texto($linea, 8, false, 'left', $xValor);
        $t->y += 11;
    }
}

/** Fila "Concepto ........ S/ 0.00" (etiqueta a la izquierda, monto a la derecha). */
function ticketFilaMonto(TicketPdf $t, string $etiqueta, float $monto): void
{
    $t->texto($etiqueta, 8, false, 'left');
    $t->texto('S/ ' . number_format($monto, 2), 8, true, 'right');
    $t->y += 11.5;
}

/** 5 -> "5", 2.5 -> "2.5" (sin ceros de sobra). */
function ticketNumero(float $n): string
{
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

/** Enteros 1..999 en letras. $apocopar: UNO -> UN (para "UN MIL", "UN MILLÓN"). */
function ticketLetras999(int $n, bool $apocopar): string
{
    $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
        'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS',
        'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    $decenas   = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA',
        8 => 'OCHENTA', 9 => 'NOVENTA'];
    $centenas  = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS',
        5 => 'QUINIENTOS', 6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    if ($n === 100) {
        return 'CIEN';
    }
    $partes = [];
    $c = intdiv($n, 100);
    $r = $n % 100;
    if ($c > 0) {
        $partes[] = $centenas[$c];
    }
    if ($r > 0) {
        if ($r < 30) {
            $partes[] = $unidades[$r];
        } else {
            $d = intdiv($r, 10);
            $u = $r % 10;
            $partes[] = $decenas[$d] . ($u > 0 ? ' Y ' . $unidades[$u] : '');
        }
    }
    $txt = implode(' ', $partes);
    if ($apocopar) {
        if (substr($txt, -9) === 'VEINTIUNO') {
            $txt = substr($txt, 0, -9) . 'VEINTIÚN';
        } elseif (substr($txt, -3) === 'UNO') {
            $txt = substr($txt, 0, -3) . 'UN';
        }
    }
    return $txt;
}

/** 3231.25 -> "SON: TRES MIL DOSCIENTOS TREINTA Y UNO CON 25/100 SOLES" */
function ticketMontoEnLetras(float $monto): string
{
    $centavosTotal = (int) round($monto * 100);
    $entero = intdiv($centavosTotal, 100);
    $cent   = $centavosTotal % 100;

    $millones = intdiv($entero, 1000000);
    $miles    = intdiv($entero % 1000000, 1000);
    $resto    = $entero % 1000;

    $partes = [];
    if ($millones > 0) {
        $partes[] = $millones === 1 ? 'UN MILLÓN' : ticketLetras999($millones, true) . ' MILLONES';
    }
    if ($miles > 0) {
        $partes[] = $miles === 1 ? 'MIL' : ticketLetras999($miles, true) . ' MIL';
    }
    if ($resto > 0) {
        $partes[] = ticketLetras999($resto, false);
    }
    $txt = $partes ? implode(' ', $partes) : 'CERO';

    return sprintf('SON: %s CON %02d/100 SOLES', $txt, $cent);
}

/**
 * Cadena que SUNAT pide dentro del código QR (se la pasas a la librería de QR
 * de tu facturador): RUC | tipo | serie | número | IGV | total | fecha | tipo doc. cliente | nro doc. cliente |
 * Tipo de doc. del cliente (catálogo 06): 1 = DNI, 6 = RUC, 0 = sin documento.
 */
function ticketSunatQrPayload(string $ruc, string $tipo, string $serie, string $numero, float $igv, float $total, string $fechaYmd, string $tipoDocCliente, string $numDocCliente): string
{
    return implode('|', [
        $ruc, $tipo, $serie, $numero,
        number_format($igv, 2, '.', ''), number_format($total, 2, '.', ''),
        $fechaYmd, $tipoDocCliente, $numDocCliente,
    ]) . '|';
}

// =============================================================================
// DATOS SUNAT (se llenan cuando enlaces el facturador)
// =============================================================================
// Mientras no exista comprobante electrónico emitido, el ticket sale como
// "NOTA DE VENTA" (documento interno). Cuando tu facturador devuelva los datos,
// guárdalos en la venta y llénalos aquí: el ticket pasa solo a modo SUNAT.
$sunat = [
    'tipo'   => $venta['sunat_tipo']   ?? null,   // '01' = factura, '03' = boleta
    'serie'  => $venta['sunat_serie']  ?? null,   // ej. 'B001' / 'F001'
    'numero' => $venta['sunat_numero'] ?? null,   // ej. 9  (se imprime como 00000009)
    'hash'   => $venta['sunat_hash']   ?? null,   // valor resumen (DigestValue) de la firma
    'qr_png' => null,                             // bytes PNG del QR (los entrega la librería de QR / el facturador)
];
$esElectronico = !empty($sunat['tipo']) && !empty($sunat['serie']) && !empty($sunat['numero']);

// =============================================================================
// ARMAR EL CONTENIDO DEL COMPROBANTE (TICKET TÉRMICO DE 80 mm)
// =============================================================================

// 80 mm = 226.77 pt. Con 12 pt de margen por lado quedan ~71.5 mm útiles,
// que es justo lo que imprimen la mayoría de térmicas de 80 mm (72 mm).
$t      = new TicketPdf(226.77, 12.0);
$xL     = $t->margen;
$xR     = $t->ancho - $t->margen;
$anchoU = $xR - $xL;

$docCliente = trim((string)($venta['cliente_ruc'] ?? ''));
$lenDoc     = strlen($docCliente);
$etiquetaDoc   = $lenDoc === 11 ? 'RUC:' : ($lenDoc === 8 ? 'DNI:' : 'Documento:');
$tipoDocCli    = $lenDoc === 11 ? '6' : ($lenDoc === 8 ? '1' : '0');

$montoTotal = (float)$venta['monto_total'];
if (PRECIOS_INCLUYEN_IGV) {
    $opGravada = round($montoTotal / (1 + IGV_TASA), 2);
    $igv       = round($montoTotal - $opGravada, 2);
} else {
    $opGravada = $montoTotal;
    $igv       = round($montoTotal * IGV_TASA, 2);
    $montoTotal = round($opGravada + $igv, 2);
}

$t->y = 14.0;

// ── Logo + datos del emisor ──────────────────────────────────────────────────
$logo = cargarLogoComoRGB(EMPRESA_LOGO_PATH);
if ($logo !== null) {
    $t->imagen($logo, 130.0, 56.0);
    $t->y += 8;
}

$t->parrafo(EMPRESA_NOMBRE_COMERCIAL, 15, true, 'center', 18);
$t->parrafo(EMPRESA_RAZON_SOCIAL, 8, false, 'center', 11);
if (EMPRESA_DIRECCION !== '') {
    $t->parrafo(EMPRESA_DIRECCION, 7.5, false, 'center', 10);
}
if (EMPRESA_TELEFONO !== '') {
    $t->parrafo('Tel. ' . EMPRESA_TELEFONO, 7.5, false, 'center', 10);
}

$t->y += 4;
$t->lineaH($t->y, true);
$t->y += 10;

// ── Caja SUNAT: RUC + tipo de comprobante + serie-número ─────────────────────
if ($esElectronico) {
    $tituloDoc = $sunat['tipo'] === '01' ? 'FACTURA ELECTRÓNICA' : 'BOLETA DE VENTA ELECTRÓNICA';
    $numeroDoc = $sunat['serie'] . '-' . str_pad((string)$sunat['numero'], 8, '0', STR_PAD_LEFT);
} else {
    $tituloDoc = 'NOTA DE VENTA';
    $numeroDoc = (string)$venta['codigo'];
}

$altoCaja = 60.0;
$cajaY    = $t->y;
$t->rectBorde($xL, $cajaY, $anchoU, $altoCaja, 1.3);

$t->y = $cajaY + 8;
$t->texto('R.U.C. ' . EMPRESA_RUC, 9.5, true, 'center');
$t->y = $cajaY + 23;
$t->texto($tituloDoc, $t->ajustar($tituloDoc, 9.5, true, $anchoU - 16), true, 'center');
$t->y = $cajaY + 37;
$t->texto($numeroDoc, $t->ajustar($numeroDoc, 16, true, $anchoU - 16), true, 'center');

$t->y = $cajaY + $altoCaja + 7;

if ($venta['estado'] === 'anulada') {
    $t->rectRelleno($xL, $t->y, $anchoU, 20);
    $t->y += 6;
    $t->texto('*** VENTA ANULADA ***', 10, true, 'center', null, null, true);
    $t->y += 14 + 7;
}

// ── Datos de la operación y del adquirente ───────────────────────────────────
$etiquetas = ['Fecha emisión:', 'Cliente:', $etiquetaDoc, 'Moneda:'];
$xValor = $xL;
foreach ($etiquetas as $e) {
    $xValor = max($xValor, $xL + $t->medir($e, 8, true));
}
$xValor += 6;

ticketFilaDato($t, 'Fecha emisión:', date('d/m/Y  H:i', strtotime($venta['fecha_venta'])), $xValor);
ticketFilaDato($t, 'Cliente:', (string)$venta['cliente_nombre'], $xValor);
if ($docCliente !== '') {
    ticketFilaDato($t, $etiquetaDoc, $docCliente, $xValor);
}
ticketFilaDato($t, 'Moneda:', 'SOLES', $xValor);

$t->y += 5;

// ── Cabecera de la tabla de ítems (barra negra con letras blancas) ───────────
$barraY = $t->y;
$t->rectRelleno($xL, $barraY, $anchoU, 15);
$t->y = $barraY + 4.7;
$t->texto('CANT. / DESCRIPCIÓN / P. UNIT.', 7, true, 'left', $xL + 5, null, true);
$t->texto('IMPORTE', 7, true, 'right', null, $xR - 5, true);
$t->y = $barraY + 15 + 8;

// ── Ítems ────────────────────────────────────────────────────────────────────
$totalItems = count($items);
$n = 0;
foreach ($items as $it) {
    $n++;
    $codProd = trim((string)($it['producto_codigo'] ?? ''));
    $nombre  = trim(($codProd !== '' ? $codProd . ' - ' : '') . ($it['producto'] ?? ''));
    $color   = trim((string)($it['color'] ?? ''));
    if ($color === 'Sin color (registro legado)') {
        $color = '';
    }

    $cantidadTxt = ticketNumero((float)($it['cantidad'] ?? 0));
    $unidad      = trim((string)($it['unidad_venta_corto'] ?? ''));
    if ($unidad !== '') {
        $cantidadTxt .= ' ' . $unidad;
    }
    $precioTxt   = 'S/ ' . number_format((float)($it['precio_unitario'] ?? 0), 2);
    $subtotalTxt = 'S/ ' . number_format((float)($it['subtotal'] ?? 0), 2);

    $t->parrafo($nombre, 8.5, true, 'left', 11);
    if ($color !== '') {
        $t->parrafo('Color: ' . $color, 7.5, false, 'left', 10);
    }
    $t->y += 2;

    // cantidad unidad × precio unitario ........................ importe
    $t->texto($cantidadTxt . '  ×  ' . $precioTxt, 8, false, 'left');
    $t->texto($subtotalTxt, 9, true, 'right');
    $t->y += 13;

    if ($n < $totalItems) {
        $t->y += 1;
        $t->lineaH($t->y, true, 0.5);
        $t->y += 7;
    }
}

// ── Totales (op. gravada, IGV, importe total) ────────────────────────────────
$t->y += 3;
$t->lineaH($t->y, false, 1.0);
$t->y += 8;

ticketFilaMonto($t, 'Op. gravada', $opGravada);
ticketFilaMonto($t, 'IGV ' . rtrim(rtrim(number_format(IGV_TASA * 100, 2, '.', ''), '0'), '.') . '%', $igv);
$t->y += 4;

$totalTxt  = 'S/ ' . number_format($montoTotal, 2);
$altoTotal = 28.0;
$totalY    = $t->y;
$etiquetaTotal = 'IMPORTE TOTAL';
$sizeTotal = $t->ajustar($totalTxt, 16, true, $anchoU - 16 - 8 - $t->medir($etiquetaTotal, 9, true), 9);

$t->rectRelleno($xL, $totalY, $anchoU, $altoTotal);
$t->y = $totalY + 10.5;
$t->texto($etiquetaTotal, 9, true, 'left', $xL + 8, null, true);
$t->y = $totalY + (($altoTotal - $sizeTotal * 0.72) / 2);
$t->texto($totalTxt, $sizeTotal, true, 'right', null, $xR - 8, true);

$t->y = $totalY + $altoTotal + 9;
$t->parrafo(ticketMontoEnLetras($montoTotal), 7.5, true, 'left', 10);

// ── Mecanismo de seguridad SUNAT (solo si ya es comprobante electrónico) ─────
if ($esElectronico) {
    $t->y += 4;
    $t->lineaH($t->y, true);
    $t->y += 10;

    if (!empty($sunat['qr_png'])) {
        $qr = ticketImagenRGB((string)$sunat['qr_png'], 400);
        if ($qr !== null) {
            $t->imagen($qr, 84.0, 84.0);
            $t->y += 8;
        }
    }
    if (!empty($sunat['hash'])) {
        $t->parrafo('Hash: ' . $sunat['hash'], 6.5, false, 'center', 8);
        $t->y += 3;
    }
    $t->parrafo('Representación impresa de la ' . ($sunat['tipo'] === '01' ? 'Factura Electrónica' : 'Boleta de Venta Electrónica'), 7, false, 'center', 9);
    if (SUNAT_URL_CONSULTA !== '') {
        $t->parrafo('Consulte su documento en: ' . SUNAT_URL_CONSULTA, 7, false, 'center', 9);
    }
}

// ── Pie ──────────────────────────────────────────────────────────────────────
$t->y += 10;
$t->parrafo('¡Gracias por su compra!', 10, true, 'center', 13);
if (!$esElectronico) {
    $t->parrafo('Documento interno · no válido como comprobante de pago', 6.5, false, 'center', 9);
}

$pdf = $t->generar(18.0);

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $venta['codigo'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;