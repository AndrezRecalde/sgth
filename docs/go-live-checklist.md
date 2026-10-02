# SGTH — Checklist Go-Live
# GAD Provincial de Esmeraldas

## INFRAESTRUCTURA
□ Servidor Ubuntu 24 LTS con Docker instalado
□ Puertos 80 y 443 abiertos en firewall
□ Certificado SSL/TLS configurado en Nginx
□ DNS apuntando al servidor de producción

## BASE DE DATOS
□ PostgreSQL 18 corriendo en Docker
□ php artisan migrate ejecutado sin errores
□ Seeders ejecutados en orden correcto:
  RolPermisoSeeder, AdminTiSeeder,
  UnidadAdministrativaSeeder, EscalaRmuSeeder,
  TarifaViaticoSeeder, CatalogoPermisosSeeder,
  ConceptoNominaSeeder, FeriadoInstitucionalSeeder
□ Backup inicial tomado antes del go-live

## APLICACIÓN
□ .env de producción configurado correctamente
□ APP_KEY generado con php artisan key:generate
□ APP_DEBUG=false en producción
□ APP_ENV=production
□ FRONTEND_URL apuntando al dominio real del frontend
  Se usa en dos sitios y ninguno avisa si está mal:
  · config/cors.php la mete en allowed_origins — si no coincide
    con el dominio desde el que se sirve el frontend, el navegador
    bloquea TODAS las peticiones a la API y el sistema no arranca
  · el QR del PDF de permisos apunta ahí (resources/views/permisos/
    permiso-pdf.blade.php); mal puesta, Talento Humano escanea el
    papel firmado y no llega a ninguna parte
  Sin la variable, ambos caen al valor por defecto de config/app.php,
  que es https://sgth.gad.gob.ec y puede no ser el dominio de este
  despliegue.
□ php artisan config:cache ejecutado
□ php artisan route:cache ejecutado
□ php artisan view:cache ejecutado

## COLAS Y SCHEDULER
□ queue-worker corriendo con restart:unless-stopped
□ scheduler corriendo con restart:unless-stopped
□ php artisan queue:restart ejecutado tras deploy
□ Verificar que jobs de nómina procesan correctamente
□ php artisan schedule:list muestra las 11 tareas registradas
  (si el número no cuadra, manda schedule:list y no este documento;
  docs/scheduler.md las lista una por una)
□ Logs del contenedor scheduler muestran ejecuciones cada minuto
  (que el contenedor esté "up" no prueba que las tareas corran)
□ Ver docs/scheduler.md: qué hace cada tarea y qué se rompe si no corre

## SEGURIDAD
□ CORS: revisar allowed_origins en config/cors.php
  Son dos: FRONTEND_URL —que debe ser el dominio real, ver APLICACIÓN—
  y http://localhost:3000, escrito a mano y presente también en
  producción. Ese localhost está ahí a propósito por ahora; queda
  anotado para que se revise, no para darlo por correcto. Si algún
  día se decide quitarlo, es una línea de config/cors.php.
□ Rate limiting activo en endpoint de login
□ El API se sirve en el MISMO dominio que el frontend (/api y /storage)
  El token de sesión va en una cookie HttpOnly que solo viaja al sitio
  que la puso. Si el navegador llama a la API en otro dominio, el login
  responde 200 y la siguiente petición da 401: nadie puede entrar.
  Dos formas, cualquiera sirve:
  · nginx manda /api y /storage del dominio del frontend a Laravel, o
  · Next lo reenvía: BACKEND_URL (sgth-frontend) apuntando a Laravel
    ANTES de `next build` — se lee al construir, no al arrancar
  NEXT_PUBLIC_API_URL sin definir (o relativa, /api/v1). Una URL
  absoluta a otro dominio rompe el inicio de sesión.
□ SESSION_SECURE_COOKIE=true en el .env de Laravel
  Marca la cookie del token como Secure. Con nginx terminando TLS,
  Laravel recibe http y sin esta variable la cookie saldría sin Secure.
□ HTTPS forzado en Nginx (redirigir HTTP a HTTPS)
□ APP_DEBUG=false verificado
□ Sentry DSN configurado y test enviado

## MÓDULOS CRÍTICOS — Verificar en producción
□ M01: GET /api/v1/estructura/organigrama responde
□ M02: GET /api/v1/expediente/servidores responde
□ M03: GET /api/v1/nomina responde
□ M04: GET /api/v1/asistencia/permisos responde
□ M05: GET /api/v1/sgd/documentos responde
□ Login: POST /api/v1/auth/login con usuario TI

## BIOMÉTRICO
□ Conexión ODBC al SQL Server del biométrico verificada
□ Stored Procedure sp_ObtenerMarcaciones responde
□ ImportarMarcacionesBiometricoCommand ejecutado
  manualmente una vez para verificar integración

## DATOS INICIALES
□ Usuario admin-ti creado por AdminTiSeeder
□ Roles y permisos cargados por RolPermisoSeeder
□ Feriados 2026 y 2027 registrados
□ Unidades administrativas del GAD cargadas
□ Escala RMU grados 1-20 cargada
□ Períodos de vacaciones del año en curso generados
  Asistencia → Períodos de vacaciones → «Generar para todos»
  (o POST /api/v1/asistencia/periodos-vacaciones/generar-todos
  con {"anio": <año>})
  Sin esto no se puede confirmar ningún permiso PERSONAL de un
  servidor LOSEP ni del Código del Trabajo: esas horas se descuentan
  del saldo de vacaciones, y sin período abierto no hay de dónde
  descontarlas. Recepción ve el rechazo, el permiso se queda en
  pendiente, y pasados los tres días hábiles de plazo
  VencerPermisosJob lo marca como falta injustificada.
  El Código del Trabajo entró aquí el 2026-09-30 (#225): antes ni
  siquiera podía pedir permisos, así que sus períodos no hacían falta
  para esto. Ahora sí.
  La tarea generar-periodos-vacaciones solo corre el 1 de enero, así
  que si el go-live cae cualquier otro día hay que hacerlo a mano
  esta vez.
  Se puede repetir sin miedo: no fuerza nada, deja intactos los
  períodos ya cerrados, y omite a los regímenes que no generan
  vacaciones.
□ Verificado sobre un servidor LOSEP real Y sobre uno del Código del
  Trabajo: crear un permiso PERSONAL y confirmarlo. Es lo que prueba
  que el punto anterior quedó bien; la lista de períodos puede verse
  llena y aun así faltar el del año en curso para quien ingresó hace
  poco. Los dos regímenes se comprueban por separado porque el del
  Código del Trabajo es nuevo y nadie lo ha ejercido todavía.

## AVISAR ANTES DE ABRIR — cambios que sorprenden
Dos cambios del 2026-09-30 alteran lo que la gente ya daba por sabido.
Ninguno es un fallo: los dos los pidió Talento Humano. Pero si nadie
los anuncia, el primer día se leen como si el sistema se hubiera roto.

□ Quien tenga sesión abierta del sistema anterior sigue dentro hasta
  que le caduque (24 h). Si alguien ve el login de golpe tras el
  despliegue, es esto: basta con volver a entrar.

□ Los obreros del Código del Trabajo empiezan a consumir saldo de
  vacaciones con sus permisos personales (#225)
  Hasta ahora no podían ni pedirlos. Desde el despliegue piden como
  cualquiera, y cada permiso PERSONAL les descuenta del saldo igual
  que a un LOSEP: se les exige período abierto y saldo suficiente, y
  sin eso Recepción no puede confirmar.
  Avisar a Talento Humano y a Recepción ANTES de abrir, no cuando
  alguien se quede en ventanilla con el papel en la mano.

□ El Consolidado de permisos deja de contar a quien no marca (#226)
  Desde el despliegue solo salen los servidores con la marcación
  habilitada, en Personal, Oficial y Calamidad. Enfermedad queda
  exenta a propósito: ese informe alimenta el indicador de Ausentismo
  por Enfermedad de Riesgos Laborales, y la ausencia existió marque o
  no.
  Consecuencia concreta: un LOSEP con la marcación apagada que SÍ
  aparecía antes deja de aparecer, también al volver a sacar un mes
  ya cerrado. Quien compare con un consolidado impreso antes del
  despliegue no va a cuadrar, y el informe no explica por qué.
  Vale la pena mirar cuántos son antes de abrir:
    SELECT COUNT(*) FROM servidores
    WHERE estado = true AND puede_marcar = false
      AND regimen_laboral IN ('losep','codigo_trabajo');

## CAPACITACIÓN
□ Personal de TI capacitado en administración del sistema
□ Personal UATH capacitado en módulos de RRHH
□ Personal de Recepción capacitado en confirmación permisos
□ Personal médico capacitado en dispensario
□ Técnicos DTIC capacitados en Helpdesk e inventario

## POST GO-LIVE
□ Monitorear Laravel Pulse durante las primeras 48 horas
□ Verificar que Sentry no reporta errores críticos
□ Confirmar que backup automático de 02:00 AM ejecutó
  (es la prueba concluyente de que el scheduler corre)
□ Verificar que jobs de colas procesan sin errores
