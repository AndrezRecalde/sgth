/*
================================================================================
  dbo.sp_SGTH_RegistrarPermiso
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Registra en dbo.USER_SPEDAY un permiso que Talento Humano o Trabajo Social
  aprobó en el SGTH. Existe para que el SGTH escriba en el biométrico sin
  permisos sobre las tablas: su usuario (sgth_app) solo ejecuta
  procedimientos.

  Escribe exactamente como lo hace hoy TH a mano en el programa de escritorio
  (reconocimiento del 2026-10-07): una fila por día, con hora de inicio y de
  fin, EST = 1 y la referencia en YUANYING, donde TH anota el memorando.

  Parámetros
  ----------
  @Cedula      10 dígitos (se aceptan 9 si falta el cero inicial).
  @LeaveId     tipo de dbo.LeaveClass; lo elige una persona en el SGTH.
  @Desde,
  @Hasta       días del permiso, ambos incluidos. DATE: no depende del idioma
               del servidor (en español, una fecha como texto se lee
               día/mes/año).
  @HoraInicio,
  @HoraFin     solo en un permiso por horas, que es de un único día: se
               escriben tal cual. Sin ellas, cada día es de jornada completa.
  @Referencia  'SGTH <folio>'. Identifica las filas del SGTH: es con lo que
               se reconocen los reintentos y lo único que permite retirarlas
               (sp_SGTH_RetirarPermiso).

  Jornada completa (decisión del 2026-10-07): el horario de ese día en
  dbo.USER_TEMP_SCH; si la persona no tiene, de lunes a viernes de 08:00 a
  17:00, que es lo que hace TH a mano, y sábado y domingo se omiten.

  Cruces con lo que ya hay en USER_SPEDAY (un permiso cargado a mano, un
  FERIADO): en un permiso de un solo día se rechaza y se dice con cuál choca;
  en uno de varios días se registran los días libres y se omiten los
  cubiertos, como hace el propio Sirha7 al repartir un permiso por días.

  A quién: la persona se busca con dbo.fn_SGTH_UsuariosPorCedula, la misma
  regla de las marcaciones, pero aquí SOLO sirve un registro coherente (su
  BADGENUMBER es la cédula). Hay registros de USERINFO con la cédula de otra
  persona en el SSN: aceptar uno que no es coherente podría cargarle el
  permiso a quien no es. Al 2026-10-07 eso deja fuera a 11 activos, que TH
  corrige en USERINFO.

  Devuelve una fila por día:
    Resultado  'registrada', 'omitida' o 'ya_registrado'
    ID         el de USER_SPEDAY (nulo si se omitió)
    USERID, Inicio, Fin
    Detalle    por qué se omitió

  'ya_registrado': ya había filas con esa referencia para esa persona. No se
  inserta nada y se devuelven las existentes: un reintento tras un corte no
  duplica el permiso.

  Errores (RAISERROR, número 50000), sin escribir nada: cédula inválida, de
  relleno, ausente o sin registro coherente; tipo inexistente; referencia que
  no empieza por 'SGTH '; fechas u horas incoherentes; más de 31 días; un
  permiso de un día que se cruza o cae sin jornada; y ningún día por
  registrar.

  Script idempotente: crea un esqueleto si falta y hace ALTER, sin DROP, así
  que conserva los permisos concedidos.
================================================================================
*/
IF OBJECT_ID('dbo.sp_SGTH_RegistrarPermiso', 'P') IS NULL
    EXEC ('CREATE PROCEDURE dbo.sp_SGTH_RegistrarPermiso AS RETURN 0;');
GO

ALTER PROCEDURE dbo.sp_SGTH_RegistrarPermiso
    @Cedula     VARCHAR(13),
    @LeaveId    INT,
    @Desde      DATE,
    @Hasta      DATE,
    @HoraInicio TIME(0)      = NULL,
    @HoraFin    TIME(0)      = NULL,
    @Referencia VARCHAR(200) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    -- ---------------------------------------------------------------
    -- 1. Validación
    -- ---------------------------------------------------------------
    DECLARE @Ced VARCHAR(20) = LTRIM(RTRIM(ISNULL(@Cedula, '')));

    IF @Ced = '' OR @Ced LIKE '%[^0-9]%' OR LEN(@Ced) NOT BETWEEN 9 AND 10
    BEGIN
        RAISERROR('La cédula debe tener 10 dígitos numéricos.', 16, 1);
        RETURN;
    END

    SET @Ced = RIGHT('0000000000' + @Ced, 10);

    IF @Ced = '1111111111'
    BEGIN
        RAISERROR('1111111111 es la cédula de relleno del biométrico, no identifica a nadie.', 16, 1);
        RETURN;
    END

    IF @LeaveId IS NULL OR NOT EXISTS (SELECT 1 FROM dbo.LeaveClass WHERE LeaveId = @LeaveId)
    BEGIN
        RAISERROR('El tipo de permiso elegido no existe en Sirha7.', 16, 1);
        RETURN;
    END

    SET @Referencia = LTRIM(RTRIM(ISNULL(@Referencia, '')));

    IF @Referencia NOT LIKE 'SGTH %' OR LEN(@Referencia) < 6
    BEGIN
        RAISERROR('La referencia debe empezar por «SGTH »: es como se reconocen las filas que escribe el SGTH.', 16, 1);
        RETURN;
    END

    IF @Desde IS NULL OR @Hasta IS NULL OR @Hasta < @Desde
    BEGIN
        RAISERROR('El permiso necesita un día de inicio y uno de fin, y el fin no puede ser anterior al inicio.', 16, 1);
        RETURN;
    END

    IF DATEDIFF(DAY, @Desde, @Hasta) > 30
    BEGIN
        RAISERROR('Un permiso del SGTH no cubre más de 31 días.', 16, 1);
        RETURN;
    END

    DECLARE @PorHoras BIT = CASE WHEN @HoraInicio IS NOT NULL OR @HoraFin IS NOT NULL THEN 1 ELSE 0 END;

    IF @PorHoras = 1 AND (@HoraInicio IS NULL OR @HoraFin IS NULL OR @HoraFin <= @HoraInicio OR @Desde <> @Hasta)
    BEGIN
        RAISERROR('Un permiso por horas es de un solo día, con la hora de fin posterior a la de inicio.', 16, 1);
        RETURN;
    END

    -- ---------------------------------------------------------------
    -- 2. A quién: solo un registro coherente
    -- ---------------------------------------------------------------
    DECLARE @Total INT, @Coherentes INT, @UserId INT;

    SELECT @Total = COUNT(*), @Coherentes = ISNULL(SUM(Coherente), 0)
    FROM   dbo.fn_SGTH_UsuariosPorCedula(@Ced);

    IF @Total = 0
    BEGIN
        RAISERROR('La cédula %s no está en el biométrico.', 16, 1, @Ced);
        RETURN;
    END

    IF @Coherentes = 0
    BEGIN
        RAISERROR('La cédula %s está en el biométrico, pero en un registro cuyo código (BADGENUMBER) no es la cédula. Puede ser de otra persona: corríjase en USERINFO antes de registrar el permiso.', 16, 1, @Ced);
        RETURN;
    END

    SELECT TOP 1 @UserId = USERID
    FROM   dbo.fn_SGTH_UsuariosPorCedula(@Ced)
    WHERE  Coherente = 1
    ORDER BY Activo DESC, USERID DESC;

    -- ---------------------------------------------------------------
    -- 3. Ya registrado: un reintento no duplica
    -- ---------------------------------------------------------------
    IF EXISTS (SELECT 1 FROM dbo.USER_SPEDAY WHERE USERID = @UserId AND YUANYING = @Referencia)
    BEGIN
        SELECT  'ya_registrado'          AS Resultado,
                sp.ID,
                sp.USERID,
                sp.STARTSPECDAY          AS Inicio,
                sp.ENDSPECDAY            AS Fin,
                CAST(NULL AS VARCHAR(400)) AS Detalle
        FROM    dbo.USER_SPEDAY sp
        WHERE   sp.USERID = @UserId AND sp.YUANYING = @Referencia
        ORDER BY sp.STARTSPECDAY;
        RETURN;
    END

    -- ---------------------------------------------------------------
    -- 4. Las filas, día por día
    -- ---------------------------------------------------------------
    DECLARE @Filas TABLE (
        Dia     DATE PRIMARY KEY,
        Inicio  DATETIME     NULL,
        Fin     DATETIME     NULL,
        Omitida VARCHAR(400) NULL
    );
    DECLARE @Insertadas TABLE (ID INT PRIMARY KEY, Inicio DATETIME);

    DECLARE @Dia DATE = @Desde, @Ini DATETIME, @Fin DATETIME, @Omitida VARCHAR(400);

    BEGIN TRANSACTION;

    WHILE @Dia <= @Hasta
    BEGIN
        SELECT @Ini = NULL, @Fin = NULL, @Omitida = NULL;

        IF @PorHoras = 1
        BEGIN
            SET @Ini = CAST(@Dia AS DATETIME) + CAST(@HoraInicio AS DATETIME);
            SET @Fin = CAST(@Dia AS DATETIME) + CAST(@HoraFin AS DATETIME);
        END
        ELSE
        BEGIN
            -- El horario de ese día; USER_TEMP_SCH tiene a lo sumo uno por día.
            SELECT TOP 1 @Ini = COMETIME, @Fin = LEAVETIME
            FROM   dbo.USER_TEMP_SCH
            WHERE  USERID = @UserId
              AND  COMETIME >= @Dia
              AND  COMETIME <  DATEADD(DAY, 1, @Dia)
            ORDER BY COMETIME;

            IF @Ini IS NULL
            BEGIN
                -- Sin horario: lunes a viernes, la jornada de 08:00 a 17:00.
                -- 1900-01-01 fue lunes, así que el resto da 5 y 6 en fin de
                -- semana sin depender de SET DATEFIRST.
                IF DATEDIFF(DAY, '19000101', @Dia) % 7 >= 5
                    SET @Omitida = 'sin jornada: fin de semana';
                ELSE
                BEGIN
                    SET @Ini = DATEADD(HOUR, 8,  CAST(@Dia AS DATETIME));
                    SET @Fin = DATEADD(HOUR, 17, CAST(@Dia AS DATETIME));
                END
            END
            ELSE IF @Fin IS NULL OR @Fin <= @Ini
                SET @Omitida = 'el horario de ese día en Sirha7 no tiene una salida posterior a la entrada';
        END

        -- Lo que ya ocupa esa franja. Con el bloqueo, dos registros a la vez
        -- de la misma persona no pasan cada uno la comprobación del otro.
        IF @Omitida IS NULL
            SELECT TOP 1 @Omitida =
                       'cruce con ' + ISNULL(LTRIM(RTRIM(lc.LeaveName)), 'TIPO ' + CAST(sp.DATEID AS VARCHAR(10)))
                     + ' de ' + CONVERT(VARCHAR(5), sp.STARTSPECDAY, 108)
                     + ' a '  + CONVERT(VARCHAR(5), ISNULL(sp.ENDSPECDAY, sp.STARTSPECDAY), 108)
                     + ISNULL(' (' + NULLIF(LTRIM(RTRIM(sp.YUANYING)), '') + ')', '')
            FROM   dbo.USER_SPEDAY sp WITH (UPDLOCK, HOLDLOCK)
            LEFT  JOIN dbo.LeaveClass lc ON lc.LeaveId = sp.DATEID
            WHERE  sp.USERID = @UserId
              AND  sp.DATEID <> 0
              AND  sp.STARTSPECDAY < @Fin
              AND  ISNULL(sp.ENDSPECDAY, sp.STARTSPECDAY) > @Ini
            ORDER BY sp.STARTSPECDAY;

        INSERT INTO @Filas (Dia, Inicio, Fin, Omitida)
        VALUES (@Dia, CASE WHEN @Omitida IS NULL THEN @Ini END, CASE WHEN @Omitida IS NULL THEN @Fin END, @Omitida);

        SET @Dia = DATEADD(DAY, 1, @Dia);
    END

    -- Un permiso de un solo día que no se puede registrar entero no se
    -- registra a medias: lo decide quien aprueba.
    IF @Desde = @Hasta AND EXISTS (SELECT 1 FROM @Filas WHERE Omitida IS NOT NULL)
    BEGIN
        SELECT @Omitida = Omitida FROM @Filas;
        ROLLBACK TRANSACTION;
        DECLARE @DiaTexto VARCHAR(10) = CONVERT(VARCHAR(10), @Desde, 103);
        RAISERROR('No se registró el permiso del %s: %s.', 16, 1, @DiaTexto, @Omitida);
        RETURN;
    END

    IF NOT EXISTS (SELECT 1 FROM @Filas WHERE Omitida IS NULL)
    BEGIN
        ROLLBACK TRANSACTION;
        RAISERROR('Ningún día del permiso quedó por registrar: todos estaban cubiertos o sin jornada.', 16, 1);
        RETURN;
    END

    INSERT INTO dbo.USER_SPEDAY (USERID, STARTSPECDAY, ENDSPECDAY, DATEID, YUANYING, [DATE], EST)
    OUTPUT INSERTED.ID, INSERTED.STARTSPECDAY INTO @Insertadas (ID, Inicio)
    SELECT @UserId, f.Inicio, f.Fin, @LeaveId, @Referencia, GETDATE(), 1
    FROM   @Filas f
    WHERE  f.Omitida IS NULL;

    COMMIT TRANSACTION;

    SELECT  CASE WHEN f.Omitida IS NULL THEN 'registrada' ELSE 'omitida' END AS Resultado,
            i.ID,
            @UserId                                       AS USERID,
            ISNULL(f.Inicio, CAST(f.Dia AS DATETIME))     AS Inicio,
            f.Fin,
            f.Omitida                                     AS Detalle
    FROM    @Filas f
    LEFT  JOIN @Insertadas i ON i.Inicio = f.Inicio
    ORDER BY f.Dia;
END
GO
