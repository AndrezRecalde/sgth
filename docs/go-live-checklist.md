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
□ php artisan config:cache ejecutado
□ php artisan route:cache ejecutado
□ php artisan view:cache ejecutado

## COLAS Y SCHEDULER
□ queue-worker corriendo con restart:unless-stopped
□ scheduler corriendo con restart:unless-stopped
□ php artisan queue:restart ejecutado tras deploy
□ Verificar que jobs de nómina procesan correctamente
□ php artisan schedule:list muestra las 8 tareas registradas
□ Logs del contenedor scheduler muestran ejecuciones cada minuto
  (que el contenedor esté "up" no prueba que las tareas corran)
□ Ver docs/scheduler.md: qué hace cada tarea y qué se rompe si no corre

## SEGURIDAD
□ CORS configurado solo para dominio del GAD
□ Rate limiting activo en endpoint de login
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
