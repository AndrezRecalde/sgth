/*
================================================================================
  dbo.sp_SGTH_TiposPermiso
  Base: Sirha7 (SQL Server 2019, nivel de compatibilidad 100)

  Los tipos de permiso del biométrico (dbo.LeaveClass), para el selector con
  que Talento Humano elige cómo se registra en Sirha7 un permiso que aprueba
  en el SGTH. Lo elige siempre una persona: el SGTH no traduce sus tipos a
  estos (decisión del 2026-10-07).

  Ninguno descuenta nada por su cuenta (Deduct = 0 en los 25 tipos al
  2026-10-07), así que el único descuento de vacaciones sigue siendo el del
  SGTH.

  Devuelve LeaveId y LeaveName, por nombre.

  Solo lectura. Script idempotente: crea un esqueleto si falta y hace ALTER,
  sin DROP, así que conserva los permisos concedidos.
================================================================================
*/
IF OBJECT_ID('dbo.sp_SGTH_TiposPermiso', 'P') IS NULL
    EXEC ('CREATE PROCEDURE dbo.sp_SGTH_TiposPermiso AS RETURN 0;');
GO

ALTER PROCEDURE dbo.sp_SGTH_TiposPermiso
AS
BEGIN
    SET NOCOUNT ON;

    SELECT  lc.LeaveId,
            LTRIM(RTRIM(lc.LeaveName)) AS LeaveName
    FROM    dbo.LeaveClass lc
    ORDER BY lc.LeaveName;
END
GO
