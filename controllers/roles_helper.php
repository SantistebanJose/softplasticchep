<?php

/** Devuelve los permisos efectivos de un rol personalizado guardado en el JSON de usuario. */
function obtenerRolesBaseCatalogo($conectar, string $codigo): array
{
    $rolesBaseSistema = ['administrador', 'operario', 'conductor', 'produccion', 'compras', 'ventas', 'consulta'];
    if (in_array($codigo, $rolesBaseSistema, true)) return [$codigo];

    $rows = executeQuery($conectar, "
        SELECT catalogo.perfil
        FROM usuario u
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(u.rol_y_perfiles->'catalogo_roles', '[]'::jsonb)) AS catalogo(perfil)
        WHERE catalogo.perfil->>'codigo' = :codigo AND catalogo.perfil->>'activo' = 'true'
        LIMIT 1
    ", ['codigo' => $codigo]);
    if (empty($rows)) {
        $asignados = executeQuery($conectar, "
            SELECT rol_y_perfiles->'perfiles' AS perfiles
            FROM usuario
            WHERE rol_y_perfiles->>'rol' = :codigo
            ORDER BY id
            LIMIT 1
        ", ['codigo' => $codigo]);
        if (empty($asignados)) return [];
        $perfiles = $asignados[0]['perfiles'] ?? [];
        if (is_string($perfiles)) $perfiles = json_decode($perfiles, true) ?: [];
        return is_array($perfiles)
            ? array_values(array_intersect($perfiles, ['produccion', 'compras', 'ventas', 'consulta', 'administrador']))
            : [];
    }

    $roles = $rows[0]['perfil'] ?? [];
    if (is_string($roles)) $roles = json_decode($roles, true) ?: [];
    $permisos = is_array($roles) ? ($roles['permisos'] ?? []) : [];
    return is_array($permisos)
        ? array_values(array_intersect($permisos, ['produccion', 'compras', 'ventas', 'consulta', 'administrador']))
        : [];
}

/** Devuelve exclusiones de acceso de un rol personalizado (por ejemplo, usuarios o roles). */
function obtenerExclusionesRolCatalogo($conectar, string $codigo): array
{
    $rows = executeQuery($conectar, "
        SELECT catalogo.perfil
        FROM usuario u
        CROSS JOIN LATERAL jsonb_array_elements(COALESCE(u.rol_y_perfiles->'catalogo_roles', '[]'::jsonb)) AS catalogo(perfil)
        WHERE catalogo.perfil->>'codigo' = :codigo AND catalogo.perfil->>'activo' = 'true'
        LIMIT 1
    ", ['codigo' => $codigo]);
    if (empty($rows)) return [];
    $perfil = $rows[0]['perfil'] ?? [];
    if (is_string($perfil)) $perfil = json_decode($perfil, true) ?: [];
    $exclusiones = is_array($perfil) ? ($perfil['exclusiones'] ?? []) : [];
    return is_array($exclusiones) ? array_values(array_intersect($exclusiones, ['usuarios', 'roles', 'ventas'])) : [];
}
