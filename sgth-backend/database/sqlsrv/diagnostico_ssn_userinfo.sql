/*
================================================================================
  Diagnóstico de USERINFO.SSN (cédula) en Sirha7 — SOLO LECTURA

  sp_SGTH_MarcacionesPorCedula encuentra al servidor por SSN. Estas consultas
  listan a quienes marcaron en los últimos 60 días y NO se podrán consultar
  por cédula (o saldrían mezclados) hasta que TH corrija el SSN.

  Al 2026-10-05: de 556 usuarios que marcaron en 60 días, 263 tienen el SSN
  de relleno 1111111111 (252 de ellos de Código del Trabajo, sin horario),
  12 lo tienen con 9 dígitos (sin el cero inicial; el SP ya los encuentra,
  pero conviene corregirlos) y 3 comparten la cédula con el registro de otra
  persona.
================================================================================
*/

-- 1. Cédula de relleno, vacía o con formato distinto de 10 dígitos
SELECT  u.USERID, u.BADGENUMBER, u.SSN, u.NAME, u.GENDER AS Regimen,
        CASE WHEN u.SSN IS NULL OR LTRIM(RTRIM(u.SSN)) = '' THEN 'Sin cédula'
             WHEN u.SSN = '1111111111'                    THEN 'Cédula de relleno'
             WHEN LEN(LTRIM(RTRIM(u.SSN))) = 9            THEN 'Le falta el cero inicial'
             ELSE 'Formato inválido' END AS Problema
FROM    dbo.USERINFO u
WHERE   EXISTS (SELECT 1 FROM dbo.CHECKINOUT c
                WHERE c.USERID = u.USERID AND c.CHECKTIME >= DATEADD(DAY, -60, GETDATE()))
  AND   (u.SSN IS NULL
         OR u.SSN = '1111111111'
         OR u.SSN NOT LIKE '[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]')
ORDER BY Problema, u.NAME;

-- 2. La misma cédula en varios registros. Si el BADGENUMBER no termina en los
--    últimos 9 dígitos de la cédula, lo más probable es que el SSN de ese
--    registro esté mal digitado (es la cédula de otra persona).
SELECT  RIGHT('0000000000' + LTRIM(RTRIM(u.SSN)), 10) AS Cedula,
        u.USERID, u.BADGENUMBER, u.NAME,
        CONVERT(VARCHAR(10), u.FechaRenuncia, 23) AS FechaRenuncia,   -- 1900-01-01 = activo
        CASE WHEN RIGHT('000000000' + LTRIM(RTRIM(u.BADGENUMBER)), 9)
                = RIGHT(RIGHT('0000000000' + LTRIM(RTRIM(u.SSN)), 10), 9)
             THEN 'Coherente' ELSE 'Revisar SSN' END AS Estado
FROM    dbo.USERINFO u
WHERE   u.SSN <> '1111111111'
  AND   RIGHT('0000000000' + LTRIM(RTRIM(u.SSN)), 10) IN (
            SELECT RIGHT('0000000000' + LTRIM(RTRIM(SSN)), 10)
            FROM   dbo.USERINFO
            WHERE  SSN IS NOT NULL AND SSN <> '1111111111' AND LTRIM(RTRIM(SSN)) <> ''
            GROUP BY RIGHT('0000000000' + LTRIM(RTRIM(SSN)), 10)
            HAVING COUNT(*) > 1)
ORDER BY Cedula, u.USERID;
