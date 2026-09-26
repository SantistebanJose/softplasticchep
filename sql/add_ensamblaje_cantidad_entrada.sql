-- Registra la cantidad y unidad que el operario recibe al vincular una
-- producción a un ensamblaje. Ejecutar una vez en la base PostgreSQL usada
-- por esta instalación. IF NOT EXISTS permite repetir la migración sin error.
ALTER TABLE rel_ensamblaje_producto
    ADD COLUMN IF NOT EXISTS cantidad_entrada_produccion NUMERIC(14, 4),
    ADD COLUMN IF NOT EXISTS unidad_entrada_id BIGINT;

-- Se dejan NULL para los registros históricos. No se agrega una FK porque
-- este proyecto usa referencias suaves en varias relaciones de producción.
