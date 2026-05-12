# SGTH — Sistema de Gestión de Talento Humano
# GAD Provincial de Esmeraldas
# Contexto del Agente — Archivo 01: Visión General del Proyecto

---

## IDENTIDAD DEL PROYECTO

- **Sistema:** SGTH — Sistema de Gestión de Talento Humano
- **Institución:** Gobierno Autónomo Descentralizado Provincial de Esmeraldas
- **País:** Ecuador
- **Tipo de institución:** Sector público — Gobierno Autónomo Descentralizado Provincial

---

## STACK TECNOLÓGICO

### Backend
- **Framework:** Laravel 13
- **Lenguaje:** PHP 8.3
- **Base de datos principal:** PostgreSQL 18 (corre en Docker)
- **Caché y colas:** Redis 7 (corre en Docker)
- **Autenticación:** Laravel Sanctum
- **Roles y permisos:** Spatie Laravel Permission
- **Auditoría:** Spatie Laravel Activitylog v4
- **Generación PDF:** Spatie Laravel PDF
- **Códigos QR:** SimpleSoftwareIO/simple-qrcode
- **Exportación Excel:** Maatwebsite/Excel 3.1
- **Pruebas:** PestPHP

### Frontend (referencia — no generar código frontend en este contexto)
- Next.js 16, TypeScript, Mantine 8.3.18, Mantine DataTable 8.3.13
- React Hook Form + Zod, TanStack Query v5, Zustand, ECharts

### Infraestructura de desarrollo
- **Servidor web:** Laragon (Apache + PHP 8.3 nativo en Windows)
- **Base de datos y caché:** Docker Desktop (PostgreSQL 18 + Redis 7 + pgAdmin 4)
- **URL de desarrollo:** http://sgth.test/
- **Debugging:** Laravel Telescope (desarrollo), Laravel Pulse + Sentry (producción)

---

## INTEGRACIÓN CON ERP FINANCIERO

El GAD ya cuenta con un ERP Financiero operativo. La integración es mediante
**modelo handoff desacoplado** — NO integración en tiempo real.

### Flujos de handoff
- SGTH → ERP: archivos XML/JSON al cerrar nómina, al aprobar viáticos, al liquidar viáticos
- ERP → SGTH: archivo con distributivo de partidas presupuestarias al inicio del período
- ERP → SGTH: confirmación de pago tras acreditación bancaria

Cada archivo de handoff se registra en la tabla `handoffs_erp` con hash de integridad
para auditoría de la Contraloría General del Estado.

---

## INTEGRACIÓN CON BIOMÉTRICO

El GAD tiene un proveedor de relojes biométricos con base de datos SQL Server.
- El SGTH lee marcaciones mediante un **Stored Procedure** autorizado
- Acceso en modo **solo lectura** — el SGTH nunca escribe en la BD del biométrico
- Conexión por red interna LAN del GAD mediante driver ODBC PHP

---

## 18 MÓDULOS DEL SISTEMA (ordenados por criticidad)

### 🔴 CRÍTICOS — Primera fase
| # | Módulo | Descripción breve |
|---|--------|-------------------|
| 01 | Estructura Organizacional y Puestos | Organigrama, puestos, distributivo, valoración MRL |
| 02 | Expediente Digital del Servidor | Datos personales, documentos, movimientos, régimen laboral |
| 03 | Nómina y Remuneraciones | Cálculo RMU, IESS, décimos, handoff ERP |
| 04 | Asistencia, Permisos y Vacaciones | Biométrico, 4 tipos de permiso, vacaciones LOSEP vs CT |
| 05 | Sistema de Gestión Documental (SGD) | Repositorio documental, flujos, firma electrónica |

### 🟠 ALTA PRIORIDAD — Segunda fase
| # | Módulo | Descripción breve |
|---|--------|-------------------|
| 06 | Portal de Autoservicio | Web responsive para el servidor público |
| 07 | Reclutamiento, Selección e Incorporación | Concurso méritos y oposición LOSEP |
| 08 | Evaluación del Desempeño | Norma Técnica MRL, evaluación 360° |
| 09 | Viáticos y Movilización | Tarifas MRL, liquidación, handoff ERP |
| 10 | Seguridad y Salud Ocupacional | Riesgos, accidentes, EPP, Comité SSO |
| 11 | Dispensario Médico Institucional | HCE, agenda, recetas, inventario medicinas |
| 12 | Inventario de Bienes Informáticos | Activos TI, asignaciones, mantenimientos |
| 13 | Mesa de Ayuda Tecnológica (Helpdesk) | Tickets, SLA, áreas DTIC, técnicos |

### 🟡 MEDIA PRIORIDAD — Tercera fase
| # | Módulo | Descripción breve |
|---|--------|-------------------|
| 14 | Régimen Disciplinario | Sumarios, sanciones, plazos procesales |
| 15 | Capacitación y Desarrollo | PAC, cursos, certificados, evaluación Kirkpatrick |
| 16 | Actividades Laborales Diarias | Bitácora diaria, informe PDF con membrete GAD |

### 🟢 BAJA PRIORIDAD — Cuarta fase
| # | Módulo | Descripción breve |
|---|--------|-------------------|
| 17 | Bienestar Laboral y Clima | Encuestas clima, plan bienestar |
| 18 | Reportería e Inteligencia Institucional | Dashboard KPIs, reportes legales, LOTAIP |

---

## REGÍMENES LABORALES

El GAD tiene dos regímenes laborales con reglas distintas:

### LOSEP (Ley Orgánica del Servicio Público)
- La mayoría del personal: técnicos, administrativos, directores
- Vacaciones: días **hábiles** por tramos de antigüedad (15/20/25/30 días)
- Límite acumulación: 60 días (pasado este límite los días se pierden)
- Compensación en activo: NO permitida

### Código del Trabajo
- Personal operativo: obreros, conductores, guardias, servicios
- Vacaciones: días **calendario** con +1 día por año adicional
- Límite acumulación: 3 años
- Compensación hasta la mitad: SÍ permitida con acuerdo del trabajador

---

## NORMATIVA ECUATORIANA APLICABLE

- LOSEP y su Reglamento General
- Código del Trabajo
- COOTAD (estructura GAD provinciales)
- Normas Técnicas MRL (puestos, evaluación, selección)
- Reglamento SSO / Decisión 584 CAN (salud ocupacional)
- Acuerdo Ministerial MSP 00005216 (Historia Clínica Única)
- Ley del IESS (aportes patronales y personales)
- Ley de Comercio Electrónico ecuatoriana (firma electrónica)
- LOTAIP Art. 7 (transparencia y acceso a información)
- Reglamento de Viáticos Sector Público / Acuerdo MRL-2013-0090

---

## AUTENTICACIÓN

### Fase 1 — Implementación inicial (actual)
- Usuario asignado por el área de TI: **primera letra del nombre + primer apellido**
  - Ejemplo: Juan Pérez → `jperez`
- Contraseña inicial: **número de cédula del servidor**
- Cambio obligatorio en el primer login — el sistema bloquea el acceso hasta que
  el servidor cambie la contraseña
- Aviso visible: "Su contraseña actual es su número de cédula. Por seguridad, cámbiela antes de continuar."
- El área de TI puede restablecer la contraseña al número de cédula desde el panel admin
- Al restablecer, el sistema vuelve a exigir el cambio obligatorio

### Fase 2 — Mejora futura (no implementar ahora)
- Integración con Active Directory vía OpenID Connect
- La arquitectura actual debe prepararse para esta migración sin rediseño

### Fase 3 — Opcional
- SSO institucional unificado

