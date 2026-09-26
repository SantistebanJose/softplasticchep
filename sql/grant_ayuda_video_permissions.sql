-- Ejecutar en la misma base de datos de la aplicación, conectado como dueño
-- de las tablas (o un rol administrador de PostgreSQL).
-- La aplicación se conecta como usrwebapp (controllers/bd.php).
GRANT SELECT, INSERT, UPDATE, DELETE
    ON TABLE public.ayuda_video, public.ayuda_video_rol
    TO usrwebapp;

-- ayuda_video.id usa SERIAL, por lo que INSERT requiere permiso en su secuencia.
GRANT USAGE, SELECT
    ON SEQUENCE public.ayuda_video_id_seq
    TO usrwebapp;
