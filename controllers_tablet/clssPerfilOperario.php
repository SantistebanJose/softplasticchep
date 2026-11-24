<?php
/**
 * controllers_tablet/clssPerfilOperario.php
 * Perfil del operario logueado en la tablet.
 * Usa $_SESSION['operario_id'] (namespace operario_*), no usuario_id.
 *
 * IMPORTANTE: $_SESSION['operario_id'] es el PK de la tabla 'operario',
 * NO el PK de 'usuario'. La cuenta de acceso se busca por
 * usuario.operario_id = :id (FK, UNIQUE) — nunca por usuario.id.
 * (Bug corregido: antes se buscaba por usuario.id = operario_id, lo
 * cual solo "funcionaba" si ambos PKs coincidían por casualidad.)
 */

require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';
require_once __DIR__ . '/../controllers/clssVerificarSession.php';

function controladorPerfilOperario($accion)
{
    switch ($accion) {
        case 'OBTENERPERFILOPERARIO':
            obtenerPerfilOperario();
            break;
        case 'ACTUALIZARPINOPERARIO':
            actualizarPinOperario();
            break;
        case 'ACTUALIZARBANCOPERFIL':
            actualizarDatosBancariosPerfil();
            break;
        default:
            responder(false, 'Acción no reconocida.');
    }
}

function requerirSesionOperario(): int
{
    // Inicia la sesión con cookie segura, strict mode y SameSite=Lax.
    iniciarSesionSegura();

    if (empty($_SESSION['operario_id'])) {
        responderAcceso(401, 'Sesión de operario no válida. Vuelve a iniciar sesión.');
    }

    return (int) $_SESSION['operario_id'];
}

function decodificarRolPerfilesOperario($valor): array
{
    if (is_array($valor)) return $valor;
    if (is_string($valor) && $valor !== '') {
        $decoded = json_decode($valor, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * usuario.rol_y_perfiles y las columnas jsonb de operario
 * (js_sucursales, js_etapas_relacionadas) llegan de executeQuery como
 * strings JSON crudos, no como arreglos PHP — mismo patrón que
 * decodificarJsonFilas() en clssOperario.php.
 */
function decodificarJsonColumna($valor): array
{
    if (is_array($valor)) return $valor;
    if (is_string($valor) && $valor !== '') {
        $decoded = json_decode($valor, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function obtenerPerfilOperario()
{
    $id = requerirSesionOperario();
    $conectar = conectar_oll_BD();

    // operario_id = PK de 'operario' (el de la sesión); se busca la
    // cuenta de acceso vinculada por FK, y de paso se trae cargo/área
    // desde 'operario' (no desde 'usuario', que no tiene esas columnas).
    $result = executeQuery($conectar, "
        SELECT
            u.user_, u.nombre_completo, u.rol_y_perfiles, u.pin, u.created_at,
            o.dni, o.js_sucursales, o.js_etapas_relacionadas, o.banco,
            c.nombre AS cargo_nombre, ar.nombre AS area_nombre
        FROM usuario u
        JOIN operario o  ON o.id = u.operario_id
        LEFT JOIN cargo c ON c.id = o.cargo_id
        LEFT JOIN area ar ON ar.id = c.area_id
        WHERE u.operario_id = :id
    ", ['id' => $id]);

    if (empty($result)) responder(false, 'Operario no encontrado.');

    $u = $result[0];
    $rolPerfiles = decodificarRolPerfilesOperario($u['rol_y_perfiles']);
    $bancos = executeQuery($conectar, 'SELECT nombre FROM public.banco WHERE deleted_at IS NULL ORDER BY nombre');
    $datosBanco = decodificarJsonColumna($u['banco']);

    responder(true, 'OK', [
        'usuario'         => $u['user_'],
        'nombre_completo' => $u['nombre_completo'],
        'rol'             => $rolPerfiles['rol'] ?? 'Operario',
        'pin'             => $u['pin'],
        'dni'             => $u['dni'],
        'banco'           => $datosBanco,
        'bancos_disponibles' => $bancos,
        'cargo'           => $u['cargo_nombre'] ?? null,
        'area'            => $u['area_nombre'] ?? null,
        'sucursales'      => decodificarJsonColumna($u['js_sucursales']),
        'etapas'          => decodificarJsonColumna($u['js_etapas_relacionadas']),
        'miembro_desde'   => $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : null,
    ]);
}

function actualizarDatosBancariosPerfil(): void
{
    $id = requerirSesionOperario();
    $bancoNombre = mb_strtoupper(trim($_POST['banco_nombre'] ?? ''), 'UTF-8');
    $cuenta = trim($_POST['banco_cuenta'] ?? '');
    $cci = trim($_POST['banco_cci'] ?? '');
    if ($cuenta !== '' && !preg_match('/^\d{6,30}$/', $cuenta)) {
        responder(false, 'El número de cuenta debe tener de 6 a 30 dígitos.');
    }
    if ($cci !== '' && !preg_match('/^\d{20}$/', $cci)) {
        responder(false, 'El CCI debe contener exactamente 20 dígitos.');
    }
    $conectar = conectar_oll_BD();
    if ($bancoNombre !== '') {
        $catalogo = executeQuery($conectar, 'SELECT nombre FROM public.banco WHERE UPPER(nombre) = :nombre AND deleted_at IS NULL', ['nombre' => $bancoNombre]);
        if (empty($catalogo)) responder(false, 'Selecciona un banco activo del catálogo.');
        $bancoNombre = $catalogo[0]['nombre'];
    }

    $actual = executeQuery($conectar, 'SELECT banco FROM public.operario WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
    if (empty($actual)) responder(false, 'Operario no encontrado o inactivo.');
    $banco = decodificarJsonColumna($actual[0]['banco']);
    $banco['nombre'] = $bancoNombre;
    $banco['cuenta'] = $cuenta;
    $banco['cci'] = $cci;
    if ($bancoNombre === '' && $cuenta === '' && $cci === '') {
        $nuevoJson = null;
    } else {
        $nuevoJson = json_encode($banco, JSON_UNESCAPED_UNICODE);
    }
    executeNonQuery($conectar, 'UPDATE public.operario SET banco = CAST(:banco AS jsonb), updated_at = NOW() WHERE id = :id', [
        'banco' => $nuevoJson,
        'id' => $id,
    ]);
    responder(true, 'Tus datos bancarios se actualizaron correctamente.');
}

function actualizarPinOperario()
{
    $id  = requerirSesionOperario();
    $pin = trim($_POST['pin'] ?? '');

    if (!preg_match('/^\d{4}$/', $pin)) {
        responder(false, 'El PIN debe tener exactamente 4 dígitos.');
    }

    $conectar = conectar_oll_BD();

    // Igual que en la lectura: la cuenta se identifica por operario_id,
    // no por el PK propio de usuario.
    $filas = executeQuery($conectar, "
        UPDATE usuario SET pin = :pin WHERE operario_id = :id
    ", ['pin' => $pin, 'id' => $id]);

    responder(true, 'PIN actualizado correctamente.');
}

function responder(bool $ok, string $msg, array $extra = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if (isset($_POST['accion'])) {
    controladorPerfilOperario($_POST['accion']);
}
