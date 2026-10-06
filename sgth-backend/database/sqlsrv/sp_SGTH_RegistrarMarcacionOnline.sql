/*
================================================================================
  dbo.sp_SGTH_RegistrarMarcacionOnline
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Registra en CHECKINOUT una marcación hecha desde el SGTH. Existe para que el
  SGTH escriba en el biométrico sin permisos sobre las tablas: su usuario
  (sgth_app) solo puede ejecutar este procedimiento y el de consulta. Antes
  hacía el SELECT a USERINFO y el INSERT a CHECKINOUT él mismo, con sa.

  La persona se busca con dbo.fn_SGTH_UsuariosPorCedula, la misma regla que
  usa sp_SGTH_MarcacionesPorCedula: la marcación cae en el registro del que
  después se lee. Entre varios registros de la misma persona (reingresos),
  en el activo más reciente.

  Parámetros
  ----------
  @Cedula    10 dígitos (se aceptan 9 si falta el cero inicial)
  @Tipo      'I' entrada/salida, 'O' almuerzo
  @Momento   fecha y hora de la marcación. Es DATETIME y no texto: así no
             importa el idioma del servidor (en español, «2026-10-05 17:20»
             como texto se leía como 10 de mayo).
  @Latitud,
  @Longitud  ubicación del dispositivo, obligatoria (decisión del
             2026-10-06: una marcación en línea sin ubicación no vale). Van a
             GEOLT y GEOLG, que la vista v_marcaciones ya muestra. Tienen
             valor por defecto solo para que faltar dé este mensaje y no el
             error genérico de parámetro ausente.
  @Sensor    SENSORID con que se guarda. Lo decide el SGTH (2 por ahora).

  Devuelve una fila: USERID y Resultado
    'registrada'     se insertó
    'duplicada'      ya había una marcación de esa persona en ese mismo
                     segundo (doble toque): no se inserta otra, no es error
    'no_encontrada'  la cédula no está en el biométrico (USERID nulo)

  Errores (RAISERROR, número 50000): cédula inválida o de relleno, tipo
  desconocido, momento a más de 15 minutos de la hora del servidor,
  ubicación ausente, incompleta o fuera de rango, y una cédula repartida en varios
  usuarios sin forma de decidir.

  Script idempotente: crea un esqueleto si falta y hace ALTER, sin DROP, así
  que conserva los permisos concedidos.
================================================================================
*/
IF OBJECT_ID('dbo.sp_SGTH_RegistrarMarcacionOnline', 'P') IS NULL
    EXEC ('CREATE PROCEDURE dbo.sp_SGTH_RegistrarMarcacionOnline AS RETURN 0;');
GO

ALTER PROCEDURE dbo.sp_SGTH_RegistrarMarcacionOnline
    @Cedula   VARCHAR(13),
    @Tipo     VARCHAR(2),
    @Momento  DATETIME,
    @Latitud  DECIMAL(9, 6) = NULL,
    @Longitud DECIMAL(9, 6) = NULL,
    @Sensor   VARCHAR(20)   = '2'
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

    IF @Tipo IS NULL OR @Tipo NOT IN ('I', 'O')
    BEGIN
        RAISERROR('El tipo de marcación debe ser I (entrada/salida) u O (almuerzo).', 16, 1);
        RETURN;
    END

    -- Una marcación online es de ahora. Un momento muy distinto de la hora
    -- del servidor delata un reloj mal puesto o una fecha mal leída, y
    -- quedaría como una marcación de otro día.
    IF @Momento IS NULL OR ABS(DATEDIFF(MINUTE, @Momento, GETDATE())) > 15
    BEGIN
        RAISERROR('La hora de la marcación no coincide con la del biométrico (más de 15 minutos de diferencia).', 16, 1);
        RETURN;
    END

    IF @Latitud IS NULL OR @Longitud IS NULL
    BEGIN
        RAISERROR('La marcación en línea necesita la ubicación: latitud y longitud.', 16, 1);
        RETURN;
    END

    IF @Latitud NOT BETWEEN -90 AND 90 OR @Longitud NOT BETWEEN -180 AND 180
    BEGIN
        RAISERROR('La ubicación está fuera de rango.', 16, 1);
        RETURN;
    END

    -- CHECKTIME es la clave junto con USERID: se guarda al segundo.
    SET @Momento = DATEADD(MILLISECOND, -DATEPART(MILLISECOND, @Momento), @Momento);

    -- ---------------------------------------------------------------
    -- 2. A quién
    -- ---------------------------------------------------------------
    IF EXISTS (SELECT 1 FROM dbo.fn_SGTH_UsuariosPorCedula(@Ced) WHERE HayCoherente = 0 AND Total > 1)
    BEGIN
        RAISERROR('La cédula %s está registrada en varios usuarios del biométrico y ninguno la tiene como código. Corríjase el SSN en USERINFO.', 16, 1, @Ced);
        RETURN;
    END

    DECLARE @UserId INT;

    SELECT TOP 1 @UserId = USERID
    FROM   dbo.fn_SGTH_UsuariosPorCedula(@Ced)
    WHERE  Coherente >= HayCoherente
    ORDER BY Activo DESC, USERID DESC;

    IF @UserId IS NULL
    BEGIN
        SELECT CAST(NULL AS INT) AS USERID, 'no_encontrada' AS Resultado;
        RETURN;
    END

    -- ---------------------------------------------------------------
    -- 3. Registrar
    -- ---------------------------------------------------------------
    IF EXISTS (SELECT 1 FROM dbo.CHECKINOUT WHERE USERID = @UserId AND CHECKTIME = @Momento)
    BEGIN
        SELECT @UserId AS USERID, 'duplicada' AS Resultado;
        RETURN;
    END

    BEGIN TRY
        INSERT INTO dbo.CHECKINOUT (USERID, CHECKTIME, CHECKTYPE, SENSORID, MARCTYPE, GEOLT, GEOLG)
        VALUES (
            @UserId,
            @Momento,
            @Tipo,
            @Sensor,
            'IR',
            CONVERT(VARCHAR(20), @Latitud),
            CONVERT(VARCHAR(20), @Longitud)
        );
    END TRY
    BEGIN CATCH
        -- 2627: otra petición insertó la misma marcación entre la comprobación
        -- y el INSERT (dos toques casi a la vez). Es la misma marcación.
        IF ERROR_NUMBER() = 2627
        BEGIN
            SELECT @UserId AS USERID, 'duplicada' AS Resultado;
            RETURN;
        END;

        THROW;
    END CATCH

    SELECT @UserId AS USERID, 'registrada' AS Resultado;
END
GO
