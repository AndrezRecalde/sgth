# SGTH — Tareas programadas (scheduler)

Nueve tareas corren solas. Ninguna avisa cuando **no** corre: si el scheduler
está caído, nada falla — las cosas simplemente no ocurren, y eso se descubre
semanas después. Por eso este documento existe.

Todas se declaran en [`sgth-backend/routes/console.php`](../sgth-backend/routes/console.php).

## Qué corre y qué pasa si no corre

| Hora | Tarea | Qué hace | Si no corre |
|---|---|---|---|
| 01:00 | `lotaip:generar-reportes` | Reportes de transparencia Art. 7 LOTAIP | Incumplimiento de publicación |
| 02:00 | `backup:base-datos` | Respaldo de PostgreSQL | **No hay respaldos.** Un respaldo que nadie ejecuta no es un respaldo |
| 03:30 | `sanctum:prune-expired --hours=24` | Borra los tokens del API caducados hace más de un día | La tabla `personal_access_tokens` crece sin fin. No rompe nada: Sanctum ya los rechaza |
| 05:00 | `sgth:contratos:detectar-vencidos` | Genera en borrador la Cesación de Funciones de los contratos de Servicios Profesionales vencidos | Los contratos vencen y nadie se entera; el servidor sigue figurando como vigente |
| 05:30 | `sgth:subrogaciones:caducar` | Cierra las subrogaciones y encargos cuyo plazo ya venció | El estado guardado miente. La pantalla queda bien igual —filtra por fecha—, pero cualquier reporte que consulte el estado se equivoca |
| 06:00 | `VerificarAlertasInventarioJob` | Alertas de stock del dispensario | Se agota medicación sin aviso |
| 07:00 L-V | `sgth:visto-bueno:control-plazos` | Plazos del Art. 183 del Código del Trabajo en los trámites de visto bueno | Se vencen plazos legales |
| cada 15 min | `EnviarAlertaSlaJob` | Alertas de SLA del helpdesk | Los tickets se pasan del SLA sin escalar |
| 1 de enero | `generar-periodos-vacaciones` | Crea los períodos anuales de vacaciones | Nadie puede solicitar vacaciones del año nuevo |

## Producción

El contenedor `scheduler` de [`docker-compose.prod.yml`](../docker-compose.prod.yml)
ya lo cubre, con `restart: unless-stopped`. No hace falta crontab en el host:
el contenedor **es** el cron.

### Verificar que de verdad está corriendo

Que el contenedor esté "up" no prueba que las tareas se ejecuten. Tres
comprobaciones, de más barata a más concluyente:

```bash
docker compose -f docker-compose.prod.yml logs --tail=50 scheduler
```

```bash
docker compose -f docker-compose.prod.yml exec laravel php artisan schedule:list
```

La concluyente es de negocio: el 2 de cualquier mes, que exista el respaldo del
día anterior. Si el respaldo de las 02:00 está, el scheduler corrió.

## Desarrollo local

**No lo dejes corriendo.** Dos razones concretas, no teóricas:

- `sgth:contratos:detectar-vencidos` genera acciones de personal en borrador
  sobre los datos sembrados. Aparecen en la bandeja como si alguien las hubiera
  registrado.
- `EnviarAlertaSlaJob` corre cada 15 minutos y envía correos desde tu máquina.

Y la máquina de desarrollo no está encendida a las 02:00 ni a las 05:00, así
que igual no probarías nada: solo acumularías ejecuciones al azar.

### Probar una tarea

Ejecutando el comando directo:

```bash
php artisan sgth:subrogaciones:caducar
```

Casi todos aceptan `--fecha` para simular otro día sin tocar el reloj del
sistema:

```bash
php artisan sgth:subrogaciones:caducar --fecha=2027-01-15
```

Y para dispararla **tal como lo haría el scheduler**, con su mutex y sus
condiciones, eligiéndola de una lista:

```bash
php artisan schedule:test
```

## Por qué `schedule:work` y no un `sleep 60`

El contenedor usaba `while true; do php artisan schedule:run; sleep 60; done`.
El problema es que el ciclo dura *lo que tarda la tarea* **más** los 60
segundos, así que se corre de a poco: con una ejecución de 2 segundos, cada
ciclo son 62s y en media hora ya se saltó un minuto entero del reloj.

Eso importa porque `schedule:run` ejecuta lo que vence **en el minuto actual**.
Si el minuto saltado resulta ser las 05:00, la detección de contratos vencidos
no corre ese día — y no deja rastro de que no corrió.

`schedule:work` duerme hasta el siguiente cambio de minuto en vez de dormir un
minuto fijo, así que no acumula deriva. Es el comando que Laravel provee
exactamente para esto.
