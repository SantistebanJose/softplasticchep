-- Índices para que el listado de Producción y sus conteos no escaneen
-- repetidamente todas las relaciones al crecer el historial.
-- Ejecutar una vez en la base softplasticchep.

CREATE INDEX IF NOT EXISTS idx_rel_produccion_material_produccion_activo
    ON rel_produccion_material (produccion_id)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_produccion_operario_activo_id
    ON produccion (operario_id, id DESC)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_produccion_operarios_gin_activo
    ON produccion USING GIN (js_operarios jsonb_path_ops)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_produccion_listado_activo
    ON produccion (enviado_ensamblaje ASC, id DESC)
    WHERE deleted_at IS NULL;
