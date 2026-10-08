/*
================================================================================
  Usuario sgth_app — el SGTH entra al biométrico con él, no con sa
  Base: Sirha7. Lo ejecuta un administrador (sysadmin).

  sa administra el servidor entero, y su contraseña vivía en el .env del
  SGTH: con ella se podía leer, cambiar o borrar cualquier base del servidor,
  incluida la del otro sistema que usa el biométrico. El SGTH solo necesita
  consultar marcaciones y registrar las online, así que sgth_app solo puede
  EJECUTAR sus procedimientos. No lee ni escribe ninguna tabla: los
  procedimientos son de dbo, igual que las tablas, y SQL Server les deja
  usarlas sin pedirle permisos a quien los llama.

  Comprobado al crearlo (2026-10-06): en Sirha7, `public` no tiene permisos
  sobre ninguna tabla y `guest` no puede conectarse, así que sgth_app no
  hereda nada más.

  Antes de ejecutarlo: reemplazar <CONTRASENA> por una contraseña larga y
  aleatoria (la política del servidor exige complejidad), y ponerla en el
  .env del SGTH como DB_SQLSRV_PASSWORD, con DB_SQLSRV_USERNAME=sgth_app.

  Orden de despliegue: fn_SGTH_UsuariosPorCedula, sp_SGTH_MarcacionesPorCedula,
  sp_SGTH_RegistrarMarcacionOnline, sp_SGTH_TiposPermiso,
  sp_SGTH_RegistrarPermiso, sp_SGTH_RetirarPermiso y, al final, este script.

  Idempotente: si el login o el usuario ya existen no los toca; los GRANT se
  pueden repetir.
================================================================================
*/
IF SUSER_ID('sgth_app') IS NULL
    CREATE LOGIN sgth_app WITH PASSWORD = '<CONTRASENA>',
        DEFAULT_DATABASE = Sirha7, CHECK_POLICY = ON, CHECK_EXPIRATION = OFF;
GO

USE Sirha7;
GO

IF USER_ID('sgth_app') IS NULL
    CREATE USER sgth_app FOR LOGIN sgth_app;
GO

GRANT EXECUTE ON dbo.sp_SGTH_MarcacionesPorCedula     TO sgth_app;
GRANT EXECUTE ON dbo.sp_SGTH_RegistrarMarcacionOnline TO sgth_app;
-- Permisos aprobados en el SGTH (2026-10-08): listar tipos, registrar y
-- retirar solo las filas propias. Sin permisos sobre ninguna tabla.
GRANT EXECUTE ON dbo.sp_SGTH_TiposPermiso           TO sgth_app;
GRANT EXECUTE ON dbo.sp_SGTH_RegistrarPermiso       TO sgth_app;
GRANT EXECUTE ON dbo.sp_SGTH_RetirarPermiso         TO sgth_app;
GO
