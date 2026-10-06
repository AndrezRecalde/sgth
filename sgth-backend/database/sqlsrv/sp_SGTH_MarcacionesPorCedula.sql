/*
================================================================================
  dbo.sp_SGTH_MarcacionesPorCedula
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Marcaciones de un servidor, una fila por día, buscándolo por la cédula
  (USERINFO.SSN) en lugar del BADGENUMBER.

  NO reemplaza a sp_GetMarcacionesPorDiaYTipo_v3, que sigue en uso por otro
  sistema y no se toca. Es un procedimiento nuevo, de solo lectura: no escribe
  en ninguna tabla (la única escritura es en una variable de tabla del propio
  lote).

  Diferencias con el v3
  ---------------------
  - Busca por SSN. Acepta la cédula con o sin el cero inicial, y también
    encuentra los SSN que se guardaron sin él (9 dígitos).
  - Si la persona tiene más de un USERID (se registró de nuevo al reingresar),
    junta las marcaciones de todos. Si el SSN está repetido en registros de
    otras personas (mal digitado), se queda solo con los que tienen un
    BADGENUMBER coherente con la cédula; si no puede decidir, da error.
  - No depende de que el día tenga horario asignado: el v3 recorre
    USER_TEMP_SCH, así que un día con marcaciones pero sin horario no salía.
    En septiembre de 2026 eso era el 40 % de los días marcados (los
    trabajadores de Código del Trabajo no tienen horario cargado).
  - Toma la hora programada del propio día (USER_TEMP_SCH) y no solo la
    plantilla (SchClass).
  - Almuerzo: no repite una sola marca como salida Y retorno, ignora los
    dobles toques y toma la última 'O' de la franja como retorno.
  - No interpreta teclas equivocadas (TH las corrige en el biométrico): 'I'
    es solo entrada/salida y 'O' solo almuerzo. Las marcas que parecen mal
    digitadas salen en la columna MarcasPorRevisar.
  - Lee CHECKINOUT una sola vez por rango (índice USERID, CHECKTIME) en lugar
    de seis subconsultas por día.
  - Columnas nuevas: DiaSemana, Cedula, Departamento, Regimen, UserIds,
    Horario, ToleranciaAtraso, MinutosAtraso, MinutosSalidaAnticipada,
    TotalMarcaciones, Marcaciones (todas las del día, con su tipo) y
    MarcasPorRevisar ('O' fuera de la franja de almuerzo, 'I' dentro de la
    franja que no es la salida, o un tipo distinto de 'I'/'O').
    Conserva con el mismo nombre todas las columnas del v3.

  Validado contra el v3 (2026-10-05, 150 servidores, sep–oct 2026):
  Entrada, Salida, horario y permisos idénticos en el 100 % de los días;
  el almuerzo difiere en un 2 %: marcas únicas que el v3 duplicaba y días
  con tres o más 'O', donde el v3 tomaba la segunda como retorno.

  Parámetros
  ----------
  @Cedula            10 dígitos (se aceptan 9 si falta el cero inicial)
  @FechaInicio       por defecto hoy
  @FechaFin          por defecto = @FechaInicio; rango máximo 1 año
  @IncluirDiasVacios 0 (defecto): solo días con horario, marcaciones o permiso
                     1: todos los días del rango

  Uso
  ---
  EXEC dbo.sp_SGTH_MarcacionesPorCedula '0802704171', '20260901', '20260930';

  Despliegue: el script es idempotente (crea un esqueleto si no existe y luego
  hace ALTER), así que se puede volver a ejecutar sin borrar nada y sin perder
  los permisos concedidos.
================================================================================
*/
IF OBJECT_ID('dbo.sp_SGTH_MarcacionesPorCedula', 'P') IS NULL
    EXEC ('CREATE PROCEDURE dbo.sp_SGTH_MarcacionesPorCedula AS RETURN 0;');
GO

ALTER PROCEDURE dbo.sp_SGTH_MarcacionesPorCedula
    @Cedula            VARCHAR(13),
    @FechaInicio       DATE = NULL,
    @FechaFin          DATE = NULL,
    @IncluirDiasVacios BIT  = 0
AS
BEGIN
    SET NOCOUNT ON;

    -- ---------------------------------------------------------------
    -- 1. Validación de parámetros
    -- ---------------------------------------------------------------
    DECLARE @Ced VARCHAR(20) = LTRIM(RTRIM(ISNULL(@Cedula, '')));

    IF @Ced = '' OR @Ced LIKE '%[^0-9]%' OR LEN(@Ced) NOT BETWEEN 9 AND 10
    BEGIN
        RAISERROR('La cédula debe tener 10 dígitos numéricos.', 16, 1);
        RETURN;
    END

    -- Cédulas de Esmeraldas (08...) guardadas sin el cero inicial: se comparan a 10 dígitos.
    SET @Ced = RIGHT('0000000000' + @Ced, 10);

    IF @Ced = '1111111111'
    BEGIN
        RAISERROR('1111111111 es la cédula de relleno del biométrico, no identifica a nadie.', 16, 1);
        RETURN;
    END

    IF @FechaInicio IS NULL SET @FechaInicio = CAST(GETDATE() AS DATE);
    IF @FechaFin    IS NULL SET @FechaFin    = @FechaInicio;

    IF @FechaFin < @FechaInicio
    BEGIN
        RAISERROR('La fecha fin no puede ser anterior a la fecha inicio.', 16, 1);
        RETURN;
    END

    IF DATEDIFF(DAY, @FechaInicio, @FechaFin) > 366
    BEGIN
        RAISERROR('El rango máximo de consulta es de un año.', 16, 1);
        RETURN;
    END

    DECLARE @Hasta DATE = DATEADD(DAY, 1, @FechaFin);   -- límite superior exclusivo

    -- ---------------------------------------------------------------
    -- 2. Usuarios del biométrico que corresponden a la cédula (por SSN)
    --
    --    Una misma persona puede tener más de un USERID (se registró de
    --    nuevo al reingresar); sus marcaciones se juntan. Pero hay SSN mal
    --    digitados que repiten la cédula de otra persona: si alguno de los
    --    candidatos tiene un BADGENUMBER coherente con la cédula (sus
    --    últimos 9 dígitos), solo se toman esos.
    -- ---------------------------------------------------------------
    DECLARE @Usuarios TABLE (
        USERID        INT PRIMARY KEY,
        BADGENUMBER   VARCHAR(24),
        NAME          VARCHAR(200),
        DEFAULTDEPTID SMALLINT,
        Regimen       VARCHAR(400),
        Activo        BIT,
        Coherente     BIT
    );

    INSERT INTO @Usuarios (USERID, BADGENUMBER, NAME, DEFAULTDEPTID, Regimen, Activo, Coherente)
    SELECT  ui.USERID,
            ui.BADGENUMBER,
            ui.NAME,
            ui.DEFAULTDEPTID,
            ui.GENDER,                      -- el sistema guarda aquí el régimen: LOSEP / CODIGOT
            CASE WHEN ui.FechaRenuncia IS NULL OR ui.FechaRenuncia < '19000102' THEN 1 ELSE 0 END,
            CASE WHEN RIGHT('000000000' + LTRIM(RTRIM(ui.BADGENUMBER)), 9) = RIGHT(@Ced, 9) THEN 1 ELSE 0 END
    FROM    dbo.USERINFO ui
    WHERE   RIGHT('0000000000' + LTRIM(RTRIM(ui.SSN)), 10) = @Ced;

    DECLARE @HayCoherente BIT = CASE WHEN EXISTS (SELECT 1 FROM @Usuarios WHERE Coherente = 1) THEN 1 ELSE 0 END;

    IF @HayCoherente = 0 AND (SELECT COUNT(*) FROM @Usuarios) > 1
    BEGIN
        RAISERROR('La cédula %s está registrada en varios usuarios del biométrico y ninguno la tiene como código. Corríjase el SSN en USERINFO.', 16, 1, @Ced);
        RETURN;
    END

    -- ---------------------------------------------------------------
    -- 3. Una fila por día
    -- ---------------------------------------------------------------
    ;WITH U AS (
        SELECT * FROM @Usuarios WHERE Coherente >= @HayCoherente
    ),
    Persona AS (
        -- Para los datos de cabecera manda el registro activo más reciente
        SELECT TOP 1 * FROM U ORDER BY Activo DESC, USERID DESC
    ),
    Digitos AS (
        SELECT d FROM (VALUES (0),(1),(2),(3),(4),(5),(6),(7),(8),(9)) v(d)
    ),
    Dias AS (
        -- 1000 números bastan para el tope de 367 días
        SELECT DATEADD(DAY, a.d * 100 + b.d * 10 + c.d, @FechaInicio) AS Fecha
        FROM   Digitos a CROSS JOIN Digitos b CROSS JOIN Digitos c
        WHERE  a.d * 100 + b.d * 10 + c.d <= DATEDIFF(DAY, @FechaInicio, @FechaFin)
    ),
    Marcas AS (
        -- Rango sobre CHECKTIME sin funciones: aprovecha el índice (USERID, CHECKTIME)
        SELECT  c.USERID,
                CAST(c.CHECKTIME AS DATE) AS Fecha,
                c.CHECKTIME,
                CAST(c.CHECKTIME AS TIME(0)) AS Hora,
                c.CHECKTYPE
        FROM    dbo.CHECKINOUT c
        INNER JOIN U ON U.USERID = c.USERID
        WHERE   c.CHECKTIME >= @FechaInicio
          AND   c.CHECKTIME <  @Hasta
    ),
    Horarios AS (
        SELECT * FROM (
            SELECT  CAST(t.COMETIME AS DATE)                AS Fecha,
                    CAST(t.COMETIME  AS TIME(0))            AS Entrada,
                    CAST(t.LEAVETIME AS TIME(0))            AS Salida,
                    CASE WHEN CAST(sc.entradaAlmuerzo AS TIME(0)) = '00:00:00' THEN NULL
                         ELSE CAST(sc.entradaAlmuerzo AS TIME(0)) END AS AlmuerzoIni,
                    CASE WHEN CAST(sc.salidaAlmuerzo AS TIME(0)) = '00:00:00' THEN NULL
                         ELSE CAST(sc.salidaAlmuerzo AS TIME(0)) END AS AlmuerzoFin,
                    sc.schName,
                    sc.LateMinutes,
                    ROW_NUMBER() OVER (PARTITION BY CAST(t.COMETIME AS DATE) ORDER BY t.COMETIME) AS rn
            FROM    dbo.USER_TEMP_SCH t
            INNER JOIN U ON U.USERID = t.USERID
            LEFT  JOIN dbo.SchClass sc ON sc.schClassid = t.SCHCLASSID
            WHERE   t.COMETIME >= @FechaInicio
              AND   t.COMETIME <  @Hasta
        ) x
        WHERE rn = 1
    ),
    Jornada AS (
        -- 'I' es entrada/salida de la institución: la primera antes de las 12:00
        -- es la entrada y la última desde las 12:00 la salida (misma regla del v3).
        SELECT  Fecha,
                MIN(CASE WHEN CHECKTYPE = 'I' AND Hora <  '12:00:00' THEN Hora END) AS Entrada,
                MAX(CASE WHEN CHECKTYPE = 'I' AND Hora >= '12:00:00' THEN Hora END) AS Salida,
                COUNT(*) AS Total
        FROM    Marcas
        GROUP BY Fecha
    ),
    Franja AS (
        -- Franja de almuerzo del día: la del horario con una hora de margen a
        -- cada lado, o 11:00–15:00 si no hay horario o es jornada única.
        SELECT  d.Fecha,
                ISNULL(DATEADD(MINUTE, -60, h.AlmuerzoIni), CAST('11:00:00' AS TIME(0))) AS Desde,
                ISNULL(DATEADD(MINUTE,  60, h.AlmuerzoFin), CAST('15:00:00' AS TIME(0))) AS Hasta,
                ISNULL(DATEADD(MINUTE, DATEDIFF(MINUTE, h.AlmuerzoIni, h.AlmuerzoFin) / 2, h.AlmuerzoIni),
                       CAST('13:00:00' AS TIME(0)))                                     AS Mitad
        FROM    Dias d
        LEFT JOIN Horarios h ON h.Fecha = d.Fecha
    ),
    MarcasAlmuerzo AS (
        -- Solo las 'O' dentro de la franja. Una 'O' fuera de ella (a las 17:00,
        -- a las 07:00) es una tecla equivocada: no se interpreta, va a
        -- MarcasPorRevisar para que TH la corrija en el biométrico.
        SELECT  m.Fecha, m.Hora, fr.Mitad
        FROM    Marcas m
        INNER JOIN Franja fr ON fr.Fecha = m.Fecha
        WHERE   m.CHECKTYPE = 'O'
          AND   m.Hora BETWEEN fr.Desde AND fr.Hasta
    ),
    PrimeraAlmuerzo AS (
        SELECT Fecha, MIN(Hora) AS Primera, MAX(Hora) AS Ultima, MIN(Mitad) AS Mitad
        FROM   MarcasAlmuerzo
        GROUP BY Fecha
    ),
    Almuerzo AS (
        -- La primera 'O' es la salida y la última el retorno; las marcas del
        -- medio (salir y volver a marcar) no cuentan. Una 'O' repetida por
        -- doble toque (menos de 3 minutos) es la misma marca: del último grupo
        -- vale la primera. Si todas forman un solo grupo, se ubica según caiga
        -- antes o después de la mitad de la franja, y nunca ocupa las dos
        -- columnas.
        SELECT  p.Fecha,
                CASE WHEN p.Ultima >= DATEADD(MINUTE, 3, p.Primera) OR p.Primera < p.Mitad
                     THEN p.Primera END                                            AS Salida,
                CASE WHEN p.Ultima >= DATEADD(MINUTE, 3, p.Primera) THEN r.Retorno
                     WHEN p.Primera >= p.Mitad THEN p.Primera END                  AS Retorno
        FROM    PrimeraAlmuerzo p
        OUTER APPLY (SELECT MIN(ma.Hora) AS Retorno FROM MarcasAlmuerzo ma
                     WHERE  ma.Fecha = p.Fecha
                       AND  ma.Hora >= DATEADD(MINUTE, 3, p.Primera)
                       AND  ma.Hora >  DATEADD(MINUTE, -3, p.Ultima)) r
    ),
    PorRevisar AS (
        -- Marcas con la tecla probablemente equivocada, para que TH las corrija:
        --   'O' fuera de la franja de almuerzo,
        --   'I' dentro de la franja que no es la salida del día,
        --   cualquier tipo distinto de 'I' y 'O'.
        SELECT  m.Fecha, m.CHECKTIME, m.Hora, m.CHECKTYPE
        FROM    Marcas m
        INNER JOIN Franja fr ON fr.Fecha = m.Fecha
        LEFT  JOIN Jornada j ON j.Fecha = m.Fecha
        WHERE   (m.CHECKTYPE = 'O' AND m.Hora NOT BETWEEN fr.Desde AND fr.Hasta)
           OR   (m.CHECKTYPE = 'I' AND m.Hora BETWEEN fr.Desde AND fr.Hasta
                 AND m.Hora >= '12:00:00' AND m.Hora < j.Salida)
           OR   ISNULL(m.CHECKTYPE, '') NOT IN ('I', 'O')
    ),
    Permisos AS (
        SELECT  sp.STARTSPECDAY,
                ISNULL(sp.ENDSPECDAY, sp.STARTSPECDAY) AS ENDSPECDAY,
                ISNULL(lc.LeaveName, 'TIPO ' + CAST(sp.DATEID AS VARCHAR(10))) AS LeaveName
        FROM    dbo.USER_SPEDAY sp
        INNER JOIN U ON U.USERID = sp.USERID
        LEFT  JOIN dbo.LeaveClass lc ON lc.LeaveId = sp.DATEID
        WHERE   sp.DATEID <> 0
          AND   sp.STARTSPECDAY < @Hasta
          AND   ISNULL(sp.ENDSPECDAY, sp.STARTSPECDAY) >= @FechaInicio
    ),
    Filas AS (
        SELECT  d.Fecha,
                h.Entrada AS hEntrada, h.Salida AS hSalida, h.AlmuerzoIni, h.AlmuerzoFin, h.schName, h.LateMinutes,
                j.Entrada, j.Salida, ISNULL(j.Total, 0) AS Total,
                a.Salida AS AlmSalida, a.Retorno AS AlmRetorno,
                CASE WHEN EXISTS (SELECT 1 FROM Permisos p
                                  WHERE p.STARTSPECDAY < DATEADD(DAY, 1, d.Fecha) AND p.ENDSPECDAY >= d.Fecha)
                     THEN 1 ELSE 0 END AS TienePermiso
        FROM    Dias d
        LEFT JOIN Horarios h ON h.Fecha = d.Fecha
        LEFT JOIN Jornada  j ON j.Fecha = d.Fecha
        LEFT JOIN Almuerzo a ON a.Fecha = d.Fecha
    )
    SELECT  f.Fecha,
            (DATEDIFF(DAY, '19000101', f.Fecha) % 7) + 1                 AS DiaSemana,   -- 1 = lunes … 7 = domingo
            @Ced                                                          AS Cedula,
            pe.BADGENUMBER,
            pe.NAME                                                       AS Nombre,
            dep.DEPTNAME                                                  AS Departamento,
            pe.Regimen,
            STUFF((SELECT ',' + CAST(U.USERID AS VARCHAR(10)) FROM U ORDER BY U.USERID
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(200)'), 1, 1, '') AS UserIds,

            f.schName                                                     AS Horario,
            CONVERT(VARCHAR(8), f.hEntrada,    108)                       AS HoraEntradaProgramada,
            CONVERT(VARCHAR(8), f.AlmuerzoIni, 108)                       AS HoraAlmuerzoSalidaProgramada,
            CONVERT(VARCHAR(8), f.AlmuerzoFin, 108)                       AS HoraAlmuerzoRetornoProgramada,
            CONVERT(VARCHAR(8), f.hSalida,     108)                       AS HoraSalidaProgramada,
            f.LateMinutes                                                 AS ToleranciaAtraso,

            CONVERT(VARCHAR(8), f.Entrada,    108)                        AS Entrada,
            CONVERT(VARCHAR(8), f.AlmSalida,  108)                        AS AlmuerzoSalida,
            CONVERT(VARCHAR(8), f.AlmRetorno, 108)                        AS AlmuerzoRetorno,
            CONVERT(VARCHAR(8), f.Salida,     108)                        AS Salida,

            -- Minutos completos, sin descontar tolerancia
            CASE WHEN f.Entrada > f.hEntrada THEN DATEDIFF(SECOND, f.hEntrada, f.Entrada) / 60 END AS MinutosAtraso,
            CASE WHEN f.Salida  < f.hSalida  THEN DATEDIFF(SECOND, f.Salida,  f.hSalida)  / 60 END AS MinutosSalidaAnticipada,

            f.Total                                                       AS TotalMarcaciones,
            STUFF((SELECT ', ' + CONVERT(VARCHAR(8), m.Hora, 108) + ' ' + ISNULL(m.CHECKTYPE, '?')
                   FROM Marcas m WHERE m.Fecha = f.Fecha ORDER BY m.CHECKTIME
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(MAX)'), 1, 2, '') AS Marcaciones,
            STUFF((SELECT ', ' + CONVERT(VARCHAR(8), r.Hora, 108) + ' ' + ISNULL(r.CHECKTYPE, '?')
                   FROM PorRevisar r WHERE r.Fecha = f.Fecha ORDER BY r.CHECKTIME
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(MAX)'), 1, 2, '') AS MarcasPorRevisar,

            STUFF((SELECT ', ' + p.LeaveName FROM Permisos p
                   WHERE p.STARTSPECDAY < DATEADD(DAY, 1, f.Fecha) AND p.ENDSPECDAY >= f.Fecha
                   ORDER BY p.STARTSPECDAY
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(MAX)'), 1, 2, '') AS TipoPermiso,
            STUFF((SELECT ', ' + CONVERT(VARCHAR(19), p.STARTSPECDAY, 120) FROM Permisos p
                   WHERE p.STARTSPECDAY < DATEADD(DAY, 1, f.Fecha) AND p.ENDSPECDAY >= f.Fecha
                   ORDER BY p.STARTSPECDAY
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(MAX)'), 1, 2, '') AS PermisoDesde,
            STUFF((SELECT ', ' + CONVERT(VARCHAR(19), p.ENDSPECDAY, 120) FROM Permisos p
                   WHERE p.STARTSPECDAY < DATEADD(DAY, 1, f.Fecha) AND p.ENDSPECDAY >= f.Fecha
                   ORDER BY p.STARTSPECDAY
                   FOR XML PATH(''), TYPE).value('.', 'VARCHAR(MAX)'), 1, 2, '') AS PermisoHasta
    FROM    Filas f
    CROSS JOIN Persona pe
    LEFT JOIN dbo.DEPARTMENTS dep ON dep.DEPTID = pe.DEFAULTDEPTID
    WHERE   @IncluirDiasVacios = 1
       OR   f.hEntrada IS NOT NULL
       OR   f.Total > 0
       OR   f.TienePermiso = 1
    ORDER BY f.Fecha;
END
GO
