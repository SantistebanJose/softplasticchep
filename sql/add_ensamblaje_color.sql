-- Persists the selected final color of the assembled product.
-- Run once on databases that do not yet have this column.
ALTER TABLE ensamblaje
    ADD COLUMN IF NOT EXISTS color_id BIGINT NULL REFERENCES color(id);

-- Preserve a best-effort color for existing records based on their first
-- linked production; the app falls back to this when reading legacy rows.
UPDATE ensamblaje e
SET color_id = (
    SELECT pd.color_id
    FROM rel_ensamblaje_producto rep
    JOIN produccion pd ON pd.id = rep.molde_produccion_id
    WHERE rep.ensamblaje_id = e.id AND rep.deleted_at IS NULL
    ORDER BY rep.id
    LIMIT 1
)
WHERE e.color_id IS NULL;
