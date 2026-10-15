-- Registra pausas/reanudaciones de producción sin alterar el historial
-- existente de pasadas, mermas ni consumo de materiales.
ALTER TABLE produccion
    ADD COLUMN IF NOT EXISTS js_casusticas JSONB[] NOT NULL DEFAULT ARRAY[]::jsonb[],
    ADD COLUMN IF NOT EXISTS detenido TIMESTAMP WITHOUT TIME ZONE;

COMMENT ON COLUMN produccion.js_casusticas IS
    'Historial JSON de pausas, reanudaciones, reasignaciones y transferencias de material.';

COMMENT ON COLUMN produccion.detenido IS
    'Fecha/hora en que se pausó la producción; NULL cuando no está detenida.';
