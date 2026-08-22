<?php
/**
 * controllers_tablet/clssAuthOperario.php
 * Login rápido de operarios en tablet: identificación solo por DNI (sin password).
 * Reutiliza la tabla `usuario` (user_ = DNI del operario), solo permite rol = 'operario'.
 */

require_once __DIR__ . '/../controllers/bd.php';
require_once __DIR__ . '/../controllers/executeQuery.php';

function intentarLoginOperario(string $dni): array
{
    $dni = trim($dni);

    if ($dni === '' || !ctype_digit($dni) || strlen($dni) !== 8) {
        return ['success' => false, 'error' => 'Ingresa un DNI válido de 8 dígitos.'];
    }

    $conectar = conectar_oll_BD();

    $result = executeQuery(
        $conectar,
        "SELECT id, user_, nombre_completo, rol_y_perfiles, deleted_at
         FROM usuario
         WHERE user_ = :user_",
        ['user_' => $dni]
    );

    if (empty($result)) {
        return ['success' => false, 'error' => 'DNI no registrado.'];
    }

    $usuario = $result[0];

    if ($usuario['deleted_at'] !== null) {
        return ['success' => false, 'error' => 'Usuario desactivado. Contacta a un administrador.'];
    }

    $rolPerfiles = decodificarRolPerfiles($usuario['rol_y_perfiles']);
    $rol = strtolower(trim((string) ($rolPerfiles['rol'] ?? '')));

    if ($rol !== 'operario') {
        return ['success' => false, 'error' => 'Este DNI no corresponde a un operario.'];
    }

    session_regenerate_id(true);
    guardarSesionOperario($usuario);

    return ['success' => true, 'error' => null];
}

/**
 * Decodifica rol_y_perfiles sin importar si executeQuery ya lo devolvió
 * como array (jsonb auto-decodificado) o como string JSON crudo.
 */
function decodificarRolPerfiles($valor): array
{
    if (is_array($valor)) {
        return $valor;
    }
    if (is_string($valor) && $valor !== '') {
        $decoded = json_decode($valor, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}
function guardarSesionOperario(array $usuario): void
{
    $rolPerfiles = decodificarRolPerfiles($usuario['rol_y_perfiles']);

    $_SESSION['operario_id']     = $usuario['id'];
    $_SESSION['operario_dni']    = $usuario['user_'];
    $_SESSION['operario_nombre'] = $usuario['nombre_completo'];
    $_SESSION['operario_rol']    = $rolPerfiles['rol'] ?? null;
}
function cerrarSesionOperario(): void
{
    unset(
        $_SESSION['operario_id'],
        $_SESSION['operario_dni'],
        $_SESSION['operario_nombre'],
        $_SESSION['operario_rol']
    );
}