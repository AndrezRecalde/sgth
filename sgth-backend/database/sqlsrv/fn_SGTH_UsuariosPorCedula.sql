/*
================================================================================
  dbo.fn_SGTH_UsuariosPorCedula
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Los registros de USERINFO que corresponden a una cédula, por SSN. Es la
  regla de búsqueda que comparten sp_SGTH_MarcacionesPorCedula (leer) y
  sp_SGTH_RegistrarMarcacionOnline (escribir), para que una marcación online
  caiga en el mismo registro del que luego se leen las marcaciones.

  @Ced debe llegar ya validada y a 10 dígitos: la función no puede lanzar
  errores, así que validar es trabajo de quien la llama.

  Columnas, además de las del registro:
    Coherente     1 si el BADGENUMBER es la cédula (sus últimos 9 dígitos).
    Activo        1 si FechaRenuncia es 1900-01-01 o vacía, que es como el
                  biométrico marca a un activo.
    HayCoherente  1 si alguno de los registros de la cédula es coherente.
    Total         cuántos registros tienen la cédula.

  Quien la llama se queda con `Coherente >= HayCoherente`: si alguno es
  coherente, solo esos (los demás son SSN mal copiados de otra persona); si
  ninguno lo es, todos, y con más de uno no hay forma de decidir.

  Solo lectura. Script idempotente: crea un esqueleto si falta y hace ALTER.
================================================================================
*/
IF OBJECT_ID('dbo.fn_SGTH_UsuariosPorCedula', 'IF') IS NULL
    EXEC ('CREATE FUNCTION dbo.fn_SGTH_UsuariosPorCedula (@Ced VARCHAR(10)) RETURNS TABLE AS RETURN SELECT 1 AS Esqueleto;');
GO

ALTER FUNCTION dbo.fn_SGTH_UsuariosPorCedula (@Ced VARCHAR(10))
RETURNS TABLE
AS
RETURN
    SELECT  u.*,
            MAX(u.Coherente) OVER () AS HayCoherente,
            COUNT(*)         OVER () AS Total
    FROM (
        SELECT  ui.USERID,
                ui.BADGENUMBER,
                ui.NAME,
                ui.DEFAULTDEPTID,
                ui.GENDER AS Regimen,   -- el sistema guarda aquí el régimen: LOSEP / CODIGOT
                CASE WHEN ui.FechaRenuncia IS NULL OR ui.FechaRenuncia < '19000102' THEN 1 ELSE 0 END AS Activo,
                CASE WHEN RIGHT('000000000' + LTRIM(RTRIM(ui.BADGENUMBER)), 9) = RIGHT(@Ced, 9) THEN 1 ELSE 0 END AS Coherente
        FROM    dbo.USERINFO ui
        WHERE   RIGHT('0000000000' + LTRIM(RTRIM(ui.SSN)), 10) = @Ced
    ) u;
GO
