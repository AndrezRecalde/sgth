/*
================================================================================
  Reconocimiento de LeaveClass y USER_SPEDAY antes de escribir permisos
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  SOLO LECTURA. No hay INSERT, UPDATE, DELETE, ALTER, CREATE ni DROP: cada
  bloque es un SELECT. Lo corre un usuario que pueda leer las tablas (el DBA);
  sgth_app no puede, porque solo tiene EXECUTE sobre dos procedimientos.

  Para qué: el SGTH va a registrar en USER_SPEDAY los permisos que apruebe
  Talento Humano (decisión del 2026-10-07). Antes de escribir una sola fila hay
  que saber exactamente cómo las escribe hoy el sistema del biométrico, para
  que las del SGTH sean indistinguibles: qué columnas son obligatorias, qué
  clave tiene la tabla, si hay disparadores, cómo se guarda un permiso por
  horas frente a uno de día completo, y quién más lee estas tablas.

  Lee con READ UNCOMMITTED para no bloquear al sistema en uso: los conteos
  pueden diferir en una o dos filas de lo exacto, que aquí no importa.

  Cómo devolver el resultado: cada bloque produce una grilla. Basta con
  copiar las grillas (o exportarlas) en el orden en que salen.

  Datos personales: no se cruza con USERINFO, así que no salen nombres ni
  cédulas; solo el USERID numérico. El bloque 9 sí muestra la columna de
  motivo de USER_SPEDAY si existe, que es texto libre que escribió TH.
================================================================================
*/
SET NOCOUNT ON;
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

-- 1. Columnas de las dos tablas: tipo, largo, si admite nulos, valor por
--    defecto y si es identidad. Dice qué tiene que mandar el SGTH en cada fila.
SELECT  c.TABLE_NAME                                    AS Tabla,
        c.ORDINAL_POSITION                              AS Posicion,
        c.COLUMN_NAME                                   AS Columna,
        c.DATA_TYPE                                     AS Tipo,
        c.CHARACTER_MAXIMUM_LENGTH                      AS Largo,
        c.IS_NULLABLE                                   AS AdmiteNulo,
        c.COLUMN_DEFAULT                                AS PorDefecto,
        COLUMNPROPERTY(OBJECT_ID('dbo.' + c.TABLE_NAME), c.COLUMN_NAME, 'IsIdentity') AS EsIdentidad
FROM    INFORMATION_SCHEMA.COLUMNS c
WHERE   c.TABLE_SCHEMA = 'dbo'
  AND   c.TABLE_NAME IN ('LeaveClass', 'USER_SPEDAY')
ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION;

-- 2. Clave primaria e índices: con qué columnas una fila es única. Decide qué
--    choca si el SGTH inserta dos veces lo mismo.
SELECT  t.name                  AS Tabla,
        i.name                  AS Indice,
        i.type_desc             AS TipoIndice,
        i.is_primary_key        AS EsClavePrimaria,
        i.is_unique             AS EsUnico,
        STUFF((SELECT ', ' + col.name
               FROM   sys.index_columns ic
               INNER JOIN sys.columns col
                       ON col.object_id = ic.object_id AND col.column_id = ic.column_id
               WHERE  ic.object_id = i.object_id AND ic.index_id = i.index_id
               ORDER BY ic.key_ordinal
               FOR XML PATH('')), 1, 2, '') AS Columnas
FROM    sys.indexes i
INNER JOIN sys.tables t ON t.object_id = i.object_id
WHERE   t.name IN ('LeaveClass', 'USER_SPEDAY')
  AND   i.index_id > 0
ORDER BY t.name, i.is_primary_key DESC, i.name;

-- 3. Claves foráneas que salen de o llegan a las dos tablas.
SELECT  fk.name                         AS ClaveForanea,
        OBJECT_NAME(fk.parent_object_id)     AS TablaHija,
        COL_NAME(fkc.parent_object_id, fkc.parent_column_id)         AS ColumnaHija,
        OBJECT_NAME(fk.referenced_object_id) AS TablaPadre,
        COL_NAME(fkc.referenced_object_id, fkc.referenced_column_id) AS ColumnaPadre,
        fk.delete_referential_action_desc    AS AlBorrar
FROM    sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc ON fkc.constraint_object_id = fk.object_id
WHERE   OBJECT_NAME(fk.parent_object_id)     IN ('LeaveClass', 'USER_SPEDAY')
   OR   OBJECT_NAME(fk.referenced_object_id) IN ('LeaveClass', 'USER_SPEDAY');

-- 4. Disparadores sobre las dos tablas, con su código. Uno que escriba en otra
--    tabla al insertar o borrar cambiaría lo que hace el SGTH sin que se vea.
SELECT  OBJECT_NAME(tr.parent_id)       AS Tabla,
        tr.name                         AS Disparador,
        tr.is_disabled                  AS Desactivado,
        OBJECT_DEFINITION(tr.object_id) AS Codigo
FROM    sys.triggers tr
WHERE   OBJECT_NAME(tr.parent_id) IN ('LeaveClass', 'USER_SPEDAY');

-- 5. Qué procedimientos, vistas y funciones leen o escriben estas tablas: el
--    otro sistema y sus reportes. Lo que el SGTH escriba aparecerá en ellos.
SELECT DISTINCT
        OBJECT_SCHEMA_NAME(d.referencing_id) + '.' + OBJECT_NAME(d.referencing_id) AS Objeto,
        o.type_desc                     AS TipoObjeto,
        d.referenced_entity_name        AS UsaLaTabla
FROM    sys.sql_expression_dependencies d
INNER JOIN sys.objects o ON o.object_id = d.referencing_id
WHERE   d.referenced_entity_name IN ('LeaveClass', 'USER_SPEDAY')
ORDER BY UsaLaTabla, Objeto;

-- 6. Los tipos de permiso tal como están: entre estos elegirá Talento Humano.
--    Las columnas de unidad o descuento (si existen) dicen si un tipo descuenta
--    algo por su cuenta, lo que se sumaría al descuento de vacaciones del SGTH.
SELECT  *
FROM    dbo.LeaveClass
ORDER BY LeaveId;

-- 7. Volumen y rango de fechas de USER_SPEDAY.
SELECT  COUNT(*)            AS Filas,
        MIN(STARTSPECDAY)   AS PrimeraFecha,
        MAX(STARTSPECDAY)   AS UltimaFecha,
        COUNT(DISTINCT USERID) AS Personas
FROM    dbo.USER_SPEDAY;

-- 8. Cómo se registra hoy, en los últimos 6 meses: cuántos permisos son por
--    horas, de día completo o de varios días en una sola fila. Dice cómo debe
--    escribir el SGTH un permiso de 08:00 a 10:00 y uno de reposo de 3 días.
SELECT  Forma, COUNT(*) AS Filas
FROM (
    SELECT CASE
             WHEN sp.ENDSPECDAY IS NULL
                  THEN 'sin fin'
             WHEN DATEDIFF(DAY, sp.STARTSPECDAY, sp.ENDSPECDAY) > 1
               OR (DATEDIFF(DAY, sp.STARTSPECDAY, sp.ENDSPECDAY) = 1
                   AND CONVERT(TIME, sp.ENDSPECDAY) <> '00:00:00')
                  THEN 'varios dias en una fila'
             WHEN CONVERT(TIME, sp.STARTSPECDAY) = '00:00:00'
                  THEN 'dia completo'
             ELSE 'por horas'
           END AS Forma
    FROM   dbo.USER_SPEDAY sp
    WHERE  sp.STARTSPECDAY >= DATEADD(MONTH, -6, GETDATE())
) x
GROUP BY Forma
ORDER BY Filas DESC;

-- 9. Ejemplos recientes, todas las columnas: la plantilla de lo que escribirá
--    el SGTH. Hasta 40 filas de los últimos 3 meses, con el nombre del tipo.
SELECT TOP 40
        sp.*,
        lc.LeaveName
FROM    dbo.USER_SPEDAY sp
LEFT  JOIN dbo.LeaveClass lc ON lc.LeaveId = sp.DATEID
WHERE   sp.STARTSPECDAY >= DATEADD(MONTH, -3, GETDATE())
ORDER BY sp.STARTSPECDAY DESC;

-- 10. Permisos de la misma persona que se pisan: si ya existen, el sistema los
--     tolera, y la comprobación de cruces del SGTH tiene que contar con ellos.
SELECT  COUNT(*) AS ParesQueSeCruzan
FROM    dbo.USER_SPEDAY a
INNER JOIN dbo.USER_SPEDAY b
        ON  b.USERID = a.USERID
        AND b.STARTSPECDAY > a.STARTSPECDAY
        AND b.STARTSPECDAY < ISNULL(a.ENDSPECDAY, a.STARTSPECDAY)
WHERE   a.STARTSPECDAY >= DATEADD(MONTH, -6, GETDATE());

-- 11. Lo que puede hacer hoy sgth_app, para documentar el antes.
SELECT  pr.name                         AS Usuario,
        pe.permission_name              AS Permiso,
        pe.state_desc                   AS Estado,
        OBJECT_NAME(pe.major_id)        AS Sobre
FROM    sys.database_permissions pe
INNER JOIN sys.database_principals pr ON pr.principal_id = pe.grantee_principal_id
WHERE   pr.name = 'sgth_app';

-- 12. Nivel de compatibilidad e idioma: fijan qué SQL se puede usar en los
--     procedimientos y cómo se leen las fechas.
SELECT  name                AS BaseDeDatos,
        compatibility_level AS NivelCompatibilidad,
        @@LANGUAGE          AS IdiomaSesion
FROM    sys.databases
WHERE   name = DB_NAME();
