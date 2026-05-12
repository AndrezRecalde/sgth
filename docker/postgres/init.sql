-- Crear bases de datos adicionales al inicializar el contenedor
CREATE DATABASE sgth_testing;

-- Extensiones necesarias para PostgreSQL 18
\c sgth_desarrollo;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "unaccent";

\c sgth_testing;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "unaccent";