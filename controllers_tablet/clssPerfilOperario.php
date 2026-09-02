<?php
/**
 * controllers_tablet/clssPerfilOperario.php
 * Perfil del operario logueado en la tablet.
 * Usa $_SESSION['operario_id'] (namespace operario_*), no usuario_id.
 */

require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';
session_start();

function controladorPerfilOperario($accion)
{
    switch ($accion) {
        case 'OBTENERPERFILOPERARIO':
            obtenerPerfilOperario();
            break;
        case 'ACTUALIZARPINOPERARIO':
            actualizarPinOperario();
            break;
        default:
            responder(false, 'Acción no reconocida.');
    }
}

function requerirSesionOperario(): int
{
    if (empty($_SESSION['operario_id'])) {
        responder(false, 'Sesión no válida. Vuelve a iniciar sesión.');
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

function obtenerPerfilOperario()
{
    $id = requerirSesionOperario();
    $conectar = conectar_oll_BD();

    $result = executeQuery($conectar, "
        SELECT user_, nombre_completo, rol_y_perfiles, pin, created_at
        FROM usuario
        WHERE id = :id
    ", ['id' => $id]);

    if (empty($result)) responder(false, 'Operario no encontrado.');

    $u = $result[0];
    $rolPerfiles = decodificarRolPerfilesOperario($u['rol_y_perfiles']);

    responder(true, 'OK', [
        'usuario'         => $u['user_'],
        'nombre_completo' => $u['nombre_completo'],
        'rol'             => $rolPerfiles['rol'] ?? 'Operario',
        'pin'             => $u['pin'],
        'miembro_desde'   => $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : null,
    ]);
}

function actualizarPinOperario()
{
    $id  = requerirSesionOperario();
    $pin = trim($_POST['pin'] ?? '');

    if (!preg_match('/^\d{4}$/', $pin)) {
        responder(false, 'El PIN debe tener exactamente 4 dígitos.');
    }

    $conectar = conectar_oll_BD();
    executeQuery($conectar, "UPDATE usuario SET pin = :pin WHERE id = :id", ['pin' => $pin, 'id' => $id]);

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