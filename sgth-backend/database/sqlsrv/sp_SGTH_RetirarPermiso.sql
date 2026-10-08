/*
================================================================================
  dbo.sp_SGTH_RetirarPermiso
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Retira de dbo.USER_SPEDAY las filas que escribió el SGTH para un permiso,
  cuando en el SGTH se revierte su confirmación o se anula el certificado
  médico que lo originó (decisión del 2026-10-07).

  Borra SOLO filas del SGTH y SOLO las de ese permiso, y prefiere no borrar
  nada a borrar de más:

  - La referencia tiene que empezar por 'SGTH ': las filas que carga TH a mano
    no la llevan, así que no hay forma de tocarlas desde aquí.
  - Persona y referencia exactas, y el SGTH dice cuántas filas registró
    (@Esperadas). Si hay otro número, no se borra ninguna.
  - Una fila a la vez y por ID. El disparador DELETE_SALIDA de USER_SPEDAY
    está escrito para borrados de una sola fila, y por cada una borra en
    USER_SPEDAY_SW y en NOTIFICACION lo que coincida en persona, inicio, fin y
    tipo. Por eso, si existe una solicitud del módulo web idéntica a alguna de
    las filas, tampoco se borra nada: el disparador se la llevaría.
  - Todo en una transacción: o salen todas las filas del permiso o ninguna.

  Parámetros
  ----------
  @Referencia  'SGTH <folio>', la misma con que se registró.
  @UserId      el USERID que devolvió sp_SGTH_RegistrarPermiso.
  @Esperadas   cuántas filas registró el SGTH (1 a 31).

  Devuelve:
    'retirada' con el ID de cada fila borrada, o
    'nada_que_retirar' si no queda ninguna fila con esa referencia (ya se
    retiró, o TH la quitó a mano): repetir el retiro no es un error.

  Errores (RAISERROR, número 50000), sin borrar nada: referencia que no es
  del SGTH, persona o cantidad ausentes, un número de filas distinto del
  esperado y una solicitud web idéntica.

  Script idempotente: crea un esqueleto si falta y hace ALTER, sin DROP, así
  que conserva los permisos concedidos.
================================================================================
*/
IF OBJECT_ID('dbo.sp_SGTH_RetirarPermiso', 'P') IS NULL
    EXEC ('CREATE PROCEDURE dbo.sp_SGTH_RetirarPermiso AS RETURN 0;');
GO

ALTER PROCEDURE dbo.sp_SGTH_RetirarPermiso
    @Referencia VARCHAR(200) = NULL,
    @UserId     INT          = NULL,
    @Esperadas  INT          = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    SET @Referencia = LTRIM(RTRIM(ISNULL(@Referencia, '')));

    IF @Referencia NOT LIKE 'SGTH %' OR LEN(@Referencia) < 6
    BEGIN
        RAISERROR('Solo se retiran filas del SGTH: la referencia tiene que empezar por «SGTH ».', 16, 1);
        RETURN;
    END

    IF @UserId IS NULL
    BEGIN
        RAISERROR('Falta el usuario del biométrico del permiso.', 16, 1);
        RETURN;
    END

    IF @Esperadas IS NULL OR @Esperadas < 1 OR @Esperadas > 31
    BEGIN
        RAISERROR('Falta cuántas filas registró el SGTH (entre 1 y 31).', 16, 1);
        RETURN;
    END

    DECLARE @Filas    TABLE (ID INT PRIMARY KEY, STARTSPECDAY DATETIME, ENDSPECDAY DATETIME, DATEID SMALLINT);
    DECLARE @Borradas TABLE (ID INT PRIMARY KEY);

    -- Dentro de una transacción ajena (una prueba que termina en ROLLBACK)
    -- no se abre otra: un ROLLBACK de aquí desharía también la de afuera. Se
    -- usa un punto de guardado y solo se deshace lo propio.
    DECLARE @TranPropia BIT = CASE WHEN @@TRANCOUNT = 0 THEN 1 ELSE 0 END;

    IF @TranPropia = 1 BEGIN TRANSACTION;
    ELSE SAVE TRANSACTION sgth_retirar;

    INSERT INTO @Filas (ID, STARTSPECDAY, ENDSPECDAY, DATEID)
    SELECT  ID, STARTSPECDAY, ENDSPECDAY, DATEID
    FROM    dbo.USER_SPEDAY WITH (UPDLOCK, HOLDLOCK)
    WHERE   USERID = @UserId
      AND   YUANYING = @Referencia;

    DECLARE @Hay INT = (SELECT COUNT(*) FROM @Filas);

    IF @Hay = 0
    BEGIN
        IF @TranPropia = 1 COMMIT TRANSACTION;
        SELECT 'nada_que_retirar' AS Resultado, CAST(NULL AS INT) AS ID;
        RETURN;
    END

    IF @Hay <> @Esperadas
    BEGIN
        IF @TranPropia = 1 ROLLBACK TRANSACTION; ELSE ROLLBACK TRANSACTION sgth_retirar;
        RAISERROR('Se esperaban %d filas con la referencia %s y hay %d: no se borra ninguna. Revísese a mano en Sirha7.', 16, 1, @Esperadas, @Referencia, @Hay);
        RETURN;
    END

    IF EXISTS (
        SELECT 1
        FROM   @Filas f
        INNER JOIN dbo.USER_SPEDAY_SW sw
                ON  sw.USERID       = @UserId
                AND sw.STARTSPECDAY = f.STARTSPECDAY
                AND sw.ENDSPECDAY   = f.ENDSPECDAY
                AND sw.DATEID       = f.DATEID
    )
    BEGIN
        IF @TranPropia = 1 ROLLBACK TRANSACTION; ELSE ROLLBACK TRANSACTION sgth_retirar;
        RAISERROR('Hay una solicitud del módulo web idéntica a una fila del permiso %s, y el disparador de Sirha7 la borraría con ella: no se borra nada. Retírese a mano en Sirha7.', 16, 1, @Referencia);
        RETURN;
    END

    DECLARE @Id INT;
    DECLARE filas CURSOR LOCAL FAST_FORWARD FOR SELECT ID FROM @Filas ORDER BY ID;

    OPEN filas;
    FETCH NEXT FROM filas INTO @Id;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        -- OUTPUT y no @@ROWCOUNT: el disparador corre sus propios DELETE
        -- entre medias, y la cuenta tiene que ser la de esta sentencia.
        DELETE FROM dbo.USER_SPEDAY
        OUTPUT DELETED.ID INTO @Borradas (ID)
        WHERE  ID = @Id
          AND  USERID = @UserId
          AND  YUANYING = @Referencia;

        FETCH NEXT FROM filas INTO @Id;
    END

    CLOSE filas;
    DEALLOCATE filas;

    IF (SELECT COUNT(*) FROM @Borradas) <> @Esperadas
    BEGIN
        IF @TranPropia = 1 ROLLBACK TRANSACTION; ELSE ROLLBACK TRANSACTION sgth_retirar;
        RAISERROR('El retiro del permiso %s no borró exactamente las filas esperadas: se deshizo y no se borró nada.', 16, 1, @Referencia);
        RETURN;
    END

    IF @TranPropia = 1 COMMIT TRANSACTION;

    SELECT 'retirada' AS Resultado, ID FROM @Borradas ORDER BY ID;
END
GO
