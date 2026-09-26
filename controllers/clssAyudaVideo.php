<?php

/** Acceso y reglas del catálogo de videos de ayuda. */
class ClssAyudaVideo
{
    private PDO $pdo;

    private const ROLES = ['conductor', 'operario', 'administrador'];
    private const MODULOS = ['compras', 'perfil', 'produccion', 'ensamblaje', 'empaquetado', 'reportes', 'general'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query("SELECT v.id, v.titulo, v.descripcion, v.youtube_id, v.modulo, v.orden, v.activo, v.created_at,
            COALESCE(json_agg(r.rol ORDER BY r.rol) FILTER (WHERE r.rol IS NOT NULL), '[]'::json) AS roles
            FROM ayuda_video v LEFT JOIN ayuda_video_rol r ON r.ayuda_video_id = v.id
            GROUP BY v.id ORDER BY v.orden, v.id DESC");
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($videos as &$video) $video['roles'] = json_decode($video['roles'], true) ?: [];
        unset($video);
        return $videos;
    }

    public function guardar(array $datos): void
    {
        $id = (int)($datos['id'] ?? 0);
        $titulo = trim((string)($datos['titulo'] ?? ''));
        $descripcion = trim((string)($datos['descripcion'] ?? ''));
        $youtubeId = self::extraerIdYouTube((string)($datos['youtube_url'] ?? ''));
        $modulo = trim((string)($datos['modulo'] ?? ''));
        $orden = filter_var($datos['orden'] ?? 0, FILTER_VALIDATE_INT);
        $roles = array_values(array_unique(array_intersect(self::ROLES, (array)($datos['roles'] ?? []))));

        if ($titulo === '' || mb_strlen($titulo) > 150) throw new InvalidArgumentException('Escribe un título de hasta 150 caracteres.');
        if (!$youtubeId) throw new InvalidArgumentException('Ingresa un enlace válido de YouTube (youtube.com o youtu.be).');
        if (!in_array($modulo, self::MODULOS, true)) throw new InvalidArgumentException('Selecciona un módulo válido.');
        if ($orden === false) throw new InvalidArgumentException('El orden debe ser un número entero.');
        if (!$roles) throw new InvalidArgumentException('Selecciona al menos un rol que pueda ver el video.');

        $this->pdo->beginTransaction();
        try {
            if ($id > 0) {
                $stmt = $this->pdo->prepare('UPDATE ayuda_video SET titulo=:titulo, descripcion=:descripcion, youtube_id=:youtube_id, modulo=:modulo, orden=:orden, updated_at=NOW() WHERE id=:id');
                $stmt->execute(['titulo'=>$titulo, 'descripcion'=>$descripcion ?: null, 'youtube_id'=>$youtubeId, 'modulo'=>$modulo, 'orden'=>$orden, 'id'=>$id]);
                if ($stmt->rowCount() === 0) {
                    $check = $this->pdo->prepare('SELECT 1 FROM ayuda_video WHERE id=:id');
                    $check->execute(['id'=>$id]);
                    if (!$check->fetchColumn()) throw new InvalidArgumentException('El video ya no existe.');
                }
                $videoId = $id;
                $this->pdo->prepare('DELETE FROM ayuda_video_rol WHERE ayuda_video_id=:id')->execute(['id'=>$videoId]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO ayuda_video (titulo, descripcion, youtube_id, modulo, orden) VALUES (:titulo, :descripcion, :youtube_id, :modulo, :orden) RETURNING id');
                $stmt->execute(['titulo'=>$titulo, 'descripcion'=>$descripcion ?: null, 'youtube_id'=>$youtubeId, 'modulo'=>$modulo, 'orden'=>$orden]);
                $videoId = (int)$stmt->fetchColumn();
            }
            $ins = $this->pdo->prepare('INSERT INTO ayuda_video_rol (ayuda_video_id, rol) VALUES (:id, :rol)');
            foreach ($roles as $rol) $ins->execute(['id'=>$videoId, 'rol'=>$rol]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE ayuda_video SET activo = NOT activo, updated_at = NOW() WHERE id = :id RETURNING activo');
        $stmt->execute(['id'=>$id]);
        $activo = $stmt->fetchColumn();
        if ($activo === false) throw new InvalidArgumentException('El video no existe.');
        return in_array($activo, [true, 't', '1', 1], true);
    }

    public function eliminar(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM ayuda_video WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        if (!$stmt->rowCount()) throw new InvalidArgumentException('El video no existe.');
    }

    private static function extraerIdYouTube(string $entrada): ?string
    {
        $entrada = trim($entrada);
        if ($entrada === '') return null;
        if (!preg_match('~^https?://~i', $entrada)) $entrada = 'https://' . $entrada;
        $partes = parse_url($entrada);
        if (!$partes || empty($partes['host'])) return null;
        $host = strtolower(preg_replace('/^www\./', '', $partes['host']));
        $id = null;
        if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str($partes['query'] ?? '', $query);
            $id = $query['v'] ?? null;
            if (!$id && preg_match('~^/(?:embed|shorts|live)/([^/?]+)~', $partes['path'] ?? '', $m)) $id = $m[1];
        } elseif ($host === 'youtu.be') {
            $id = trim($partes['path'] ?? '', '/');
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? $id : null;
    }
}
