# SGTH Frontend — Plan de Sprints
# GAD Provincial de Esmeraldas
# Versión 1.0 — Mayo 2026

---

## INSTRUCCIÓN DE USO PARA EL AGENTE

Lee este archivo junto con FRONTEND.md antes de ejecutar cualquier sprint.
Marca cada tarea como [x] cuando esté completada.
Nunca omitas una tarea sin notificar al usuario primero.
Si un componente ya existe en la lista de "Componentes creados", reutilízalo.
Si un tipo ya existe en api.ts, impórtalo desde ahí — nunca lo redefinas.

---

## ESTADO GLOBAL DEL PROYECTO

| Sprint | Módulo | Estado |
|--------|--------|--------|
| F-01 | Setup base | ✅ Completo |
| F-02 | Auth + AppShell | ✅ Completo |
| F-03a | Estructura — Vistas lectura | ✅ Completo |
| F-03b | Estructura — CRUD completo | ✅ Completo |
| F-04 | Expediente Digital | ✅ Completo |
| F-05 | Nómina y Asistencia | ⏳ Pendiente |
| F-06 | Viáticos | ⏳ Pendiente |
| F-07 | Dispensario Médico | ⏳ Pendiente |
| F-08 | Autoservicio del Servidor | ⏳ Pendiente |
| F-09 | Selección y Evaluación | ⏳ Pendiente |
| F-10 | Capacitación y Bienestar | ⏳ Pendiente |
| F-11 | SSO + Disciplinario + SGD | ⏳ Pendiente |
| F-12 | Helpdesk + Inventario TI | ⏳ Pendiente |
| F-13 | Reportería y BI | ⏳ Pendiente |
| F-14 | Dashboard ejecutivo | ⏳ Pendiente |
| F-15 | QA + Optimización + Deploy | ⏳ Pendiente |

---

## COMPONENTES GLOBALES YA CREADOS

Antes de crear cualquier componente nuevo, verifica esta lista.
Si el componente existe, impórtalo — no lo vuelvas a crear.

```
src/components/ui/PageHeader.tsx
src/components/layout/AppShell.tsx
src/components/layout/Sidebar.tsx
src/components/layout/Topbar.tsx
src/components/layout/NavItem.tsx
src/components/layout/NavGroup.tsx
src/components/layout/SidebarUserRow.tsx
src/components/layout/SidebarSystemSelector.tsx
src/hooks/useContainedInput.ts
src/hooks/useMobileBreakpoint.ts
src/hooks/useAuth.ts
src/lib/axios.ts
src/lib/queryClient.ts
src/lib/tablerIcons.ts
src/styles/inputs.contained.module.css
src/store/auth.store.ts
src/store/ui.store.ts
src/config/routes.ts
src/config/nav.ts
src/config/env.ts
src/config/mantine.theme.ts
```

---

## PATRONES OBLIGATORIOS — RECORDATORIO

```
✅ Inputs: siempre {...useContainedInput()} sin iconos
✅ Tablas: siempre mantine-datatable, columnas en archivo .columns.tsx
✅ Gráficas: siempre echarts-for-react
✅ Iconos: siempre @tabler/icons-react, nunca emojis
✅ Estilos: CSS Modules, nunca styles={{}} inline reutilizables
✅ Tipos: importar desde @/types/api, nunca redefinir
✅ Formularios: useForm + zodResolver + zod/v4
✅ Notificaciones: @mantine/notifications con IconCheck/IconX
✅ Modals: fullScreen={isMobile} en todos los modals
✅ Grids: span={{ base: 12, sm: 6, md: 4 }} siempre responsive
✅ Páginas: máx 80 líneas
✅ Componentes: máx 200 líneas
✅ Hooks: máx 150 líneas
✅ Services: máx 100 líneas
```

---

## SPRINT F-01 — Setup Base ✅ COMPLETO

**Rama:** feature/sprint-f01 → mergeado a main
**Fecha:** Mayo 2026

### Archivos creados
```
src/app/layout.tsx
src/app/Providers.tsx
src/app/(dashboard)/layout.tsx
src/app/(dashboard)/page.tsx
src/config/env.ts
src/config/mantine.theme.ts
src/config/nav.ts
src/config/routes.ts
src/hooks/useMobileBreakpoint.ts
src/hooks/useAuth.ts
src/hooks/useContainedInput.ts
src/lib/axios.ts
src/lib/queryClient.ts
src/lib/tablerIcons.ts
src/store/auth.store.ts
src/store/ui.store.ts
src/styles/inputs.contained.module.css
src/types/api.generated.ts
src/types/api.ts
proxy.ts (raíz del proyecto)
```

---

## SPRINT F-02 — Autenticación + AppShell ✅ COMPLETO

**Rama:** feature/sprint-f02 → mergeado a main
**Fecha:** Mayo 2026

### Archivos creados
```
src/app/(auth)/login/page.tsx
src/app/(auth)/cambiar-password/page.tsx
src/components/layout/AppShell.tsx
src/components/layout/Sidebar.tsx
src/components/layout/Topbar.tsx
src/components/layout/NavItem.tsx
src/components/layout/NavGroup.tsx
src/components/layout/SidebarUserRow.tsx
src/components/layout/SidebarSystemSelector.tsx
src/features/auth/components/LoginForm.tsx
src/features/auth/components/CambiarPasswordForm.tsx
src/features/auth/hooks/useLogin.ts
src/features/auth/hooks/useCambiarPassword.ts
src/features/auth/schemas/login.schema.ts
src/features/auth/schemas/cambiarPassword.schema.ts
src/features/auth/services/authService.ts
src/features/auth/styles/authInputStyles.ts
```

### Funcionalidades implementadas
- Login con validación Zod (usuario + contraseña)
- Redirección automática si ya hay token
- Cambio de contraseña obligatorio en primer login
- Cookie sgth_primer_login para bloquear navegación
- AppShell responsive (desktop/tablet/móvil)
- Sidebar Navy dinámico por rol y permisos
- Toggle modo claro/oscuro
- Anchor webmail institucional en login

### Endpoints consumidos
```
POST /auth/login
POST /auth/logout
POST /auth/cambiar-contrasena   → payload: { nueva_contrasena }
```

---

## SPRINT F-03a — Estructura Organizacional (Lectura) ✅ COMPLETO

**Rama:** feature/sprint-f03 → mergeado a main
**Fecha:** Mayo 2026

### Archivos creados
```
src/app/(dashboard)/estructura/page.tsx
src/components/ui/PageHeader.tsx
src/features/estructura/components/OrganigramaNode.tsx
src/features/estructura/components/OrganigramaTree.tsx
src/features/estructura/components/DirectorioTable.tsx
src/features/estructura/components/DirectorioToolbar.tsx
src/features/estructura/components/directorio.columns.tsx
src/features/estructura/hooks/useOrganigrama.ts
src/features/estructura/hooks/useUnidades.ts
src/features/estructura/hooks/useDirectorio.ts
src/features/estructura/hooks/useTiposUnidad.ts
src/features/estructura/services/estructuraService.ts
src/features/estructura/index.ts
```

### Funcionalidades implementadas
- Organigrama árbol recursivo expandible/colapsable
- Colores por nivel jerárquico (esmeralda → gris)
- Directorio telefónico con mantine-datatable
- Búsqueda y filtro por unidad en directorio
- Tabs: Organigrama / Directorio telefónico
- PageHeader reutilizable para todas las páginas

### Endpoints consumidos
```
GET /estructura/organigrama
GET /estructura/unidades-administrativas
GET /estructura/directorio-telefonico
GET /catalogos/tipos-unidad
```

---

## SPRINT F-03b — Estructura Organizacional (CRUD) ✅ COMPLETO

**Rama:** feature/sprint-f03b
**Prioridad:** Alta — el módulo está incompleto sin CRUD

### Objetivo
Completar el módulo de Estructura con formularios de creación,
edición y eliminación de Unidades Administrativas, Puestos
y Extensiones Telefónicas.

### Endpoints a consumir
```
# Unidades Administrativas
POST   /estructura/unidades-administrativas
PUT    /estructura/unidades-administrativas/{id}
DELETE /estructura/unidades-administrativas/{id}
GET    /estructura/unidades-administrativas/{id}

# Puestos
GET    /estructura/puestos
POST   /estructura/puestos
GET    /estructura/puestos/{id}
PUT    /estructura/puestos/{id}
DELETE /estructura/puestos/{id}

# Extensiones Telefónicas
POST   /estructura/extensiones
PUT    /estructura/extensiones/{id}
DELETE /estructura/extensiones/{id}
```

### Schemas Zod a crear
```
src/features/estructura/schemas/unidad.schema.ts
  - nombre: string min 3
  - codigo: string opcional
  - tipo_unidad_id: number requerido
  - unidad_padre_id: number opcional (para jerarquía)
  - mision: string opcional
  - presupuesto_total: number opcional

src/features/estructura/schemas/puesto.schema.ts
  - nombre: string min 3
  - unidad_administrativa_id: number requerido
  - codigo: string opcional
  - nivel: string opcional
  - remuneracion: number opcional

src/features/estructura/schemas/extension.schema.ts
  - unidad_administrativa_id: number requerido
  - numero_extension: string min 1 max 10
  - responsable: string min 2
  - descripcion: string opcional
  - estado: boolean default true
```

### Hooks a crear
```
src/features/estructura/hooks/usePuestos.ts
  - useQuery puestos.index con PuestoParams
  - staleTime: 5 minutos

src/features/estructura/hooks/useUnidadMutations.ts
  - crear: POST /estructura/unidades-administrativas
  - editar: PUT /estructura/unidades-administrativas/{id}
  - eliminar: DELETE /estructura/unidades-administrativas/{id}
  - onSuccess: invalidar queries ['organigrama', 'unidades']
  - onSuccess: notificación con IconCheck esmeralda
  - onError: notificación con IconX rojo

src/features/estructura/hooks/usePuestoMutations.ts
  - crear: POST /estructura/puestos
  - editar: PUT /estructura/puestos/{id}
  - eliminar: DELETE /estructura/puestos/{id}
  - onSuccess: invalidar query ['puestos']

src/features/estructura/hooks/useExtensionMutations.ts
  - crear: POST /estructura/extensiones
  - editar: PUT /estructura/extensiones/{id}
  - eliminar: DELETE /estructura/extensiones/{id}
  - onSuccess: invalidar query ['directorio']
```

### Componentes a crear

#### Unidades Administrativas
```
src/features/estructura/components/UnidadModal.tsx
  - Modal crear/editar (fullScreen en móvil)
  - Título dinámico: "Nueva unidad" / "Editar unidad"
  - Usa UnidadForm internamente
  - Props: opened, onClose, unidadId? (null = crear)

src/features/estructura/components/UnidadForm.tsx
  - TextInput: Nombre (contained, requerido)
  - TextInput: Código (contained, opcional)
  - Select: Tipo de unidad (opciones de useTiposUnidad)
  - Select: Unidad padre (opciones de useUnidades, opcional)
  - Textarea: Misión (contained, opcional)
  - NumberInput: Presupuesto total (contained, opcional)
  - Botones: Cancelar / Guardar con loading
  - Grid responsive: 2 cols desktop, 1 col móvil

src/features/estructura/components/UnidadDeleteConfirm.tsx
  - Modal de confirmación eliminación
  - Texto: "¿Eliminar [nombre]? Esta acción no se puede deshacer."
  - Botones: Cancelar / Eliminar (color red, loading)
```

#### Puestos
```
src/features/estructura/components/PuestosTab.tsx
  - Vista de puestos por unidad (tab adicional en /estructura)
  - DataTable con columnas: nombre, unidad, código, nivel, remuneración
  - Botón "Nuevo puesto" en toolbar
  - Columna acciones: editar, eliminar

src/features/estructura/components/PuestoModal.tsx
  - Modal crear/editar puesto
  - fullScreen en móvil

src/features/estructura/components/PuestoForm.tsx
  - TextInput: Nombre (contained)
  - Select: Unidad administrativa (contained, searchable)
  - TextInput: Código (contained, opcional)
  - TextInput: Nivel (contained, opcional)
  - NumberInput: Remuneración (contained, opcional)

src/features/estructura/components/puesto.columns.tsx
  - Columnas del DataTable de puestos
  - Columna acciones con IconEdit e IconTrash
```

#### Extensiones Telefónicas
```
src/features/estructura/components/ExtensionModal.tsx
  - Modal crear/editar extensión
  - fullScreen en móvil

src/features/estructura/components/ExtensionForm.tsx
  - Select: Unidad administrativa (contained, searchable)
  - TextInput: Número de extensión (contained, max 10 chars)
  - TextInput: Responsable (contained)
  - Textarea: Descripción (contained, opcional)
  - Switch: Estado activo/inactivo

src/features/estructura/components/ExtensionDeleteConfirm.tsx
  - Modal confirmación eliminación extensión
```

### Actualizar página /estructura
```
src/app/(dashboard)/estructura/page.tsx
  - Agregar Tab "Puestos" con IconBriefcase
  - Agregar botón "Nueva unidad" en Tab Organigrama
  - Agregar botón "Nueva extensión" en Tab Directorio
  - Manejar estado de modal abierto/cerrado para cada entidad
  - Máx 80 líneas — dividir en subcomponentes si supera
```

### Actualizar estructuraService.ts
```
Agregar métodos:
  crearUnidad, editarUnidad, eliminarUnidad
  listarPuestos, crearPuesto, editarPuesto, eliminarPuesto
  crearExtension, editarExtension, eliminarExtension
```

---

## SPRINT F-04 — Expediente Digital ✅ COMPLETO

**Rama:** feature/sprint-f04
**Ruta:** /expediente

### Objetivo
Gestión completa del expediente digital de servidores públicos:
ficha personal, contratos, documentos, discapacidad,
enfermedades catastróficas, cuentas bancarias y movimientos.

### Endpoints a consumir
```
GET    /expediente/servidores
POST   /expediente/servidores
GET    /expediente/servidores/{id}
PUT    /expediente/servidores/{id}

GET    /expediente/servidores/{id}/contratos
POST   /expediente/servidores/{id}/contratos
GET    /expediente/servidores/{id}/documentos
POST   /expediente/servidores/{id}/documentos
GET    /expediente/servidores/{id}/discapacidades
POST   /expediente/servidores/{id}/discapacidades
GET    /expediente/servidores/{id}/enfermedades-catastroficas
POST   /expediente/servidores/{id}/enfermedades-catastroficas
GET    /expediente/servidores/{id}/cuentas-bancarias
POST   /expediente/servidores/{id}/cuentas-bancarias
GET    /expediente/servidores/{id}/movimientos-personal

GET    /catalogos/provincias
GET    /catalogos/provincias/{id}/cantones
GET    /catalogos/entidades-financieras
```

### Tipos disponibles en api.ts
```
Servidor, ContratoServidor, DiscapacidadServidor,
EnfermedadCatastroficaServidor, CuentaBancariaServidor,
EntidadFinanciera, ServidorParams, TipoNombramiento,
EstadoContrato, Provincia, Canton
```

### Schemas Zod a crear
```
src/features/expediente/schemas/servidor.schema.ts
  Datos personales:
  - nombres: string min 2
  - apellidos: string min 2
  - cedula: string length 10, solo dígitos
  - fecha_nacimiento: date
  - genero: enum ['masculino', 'femenino', 'otro']
  - estado_civil: enum ['soltero','casado','divorciado','viudo','union_libre']
  - telefono_personal: string opcional
  - telefono_institucional: string opcional
  - correo_personal: email opcional
  - correo_institucional: email opcional
  - direccion: string opcional
  - provincia_nacimiento_id: number requerido
  - canton_nacimiento_id: number requerido

src/features/expediente/schemas/contrato.schema.ts
  - tipo_nombramiento: TipoNombramiento
  - unidad_administrativa_id: number
  - puesto_id: number
  - fecha_ingreso: date
  - fecha_fin: date opcional
  - remuneracion: number positivo

src/features/expediente/schemas/documento.schema.ts
  - tipo_documento: string
  - numero_documento: string
  - archivo: File (upload)
  - fecha_emision: date opcional
  - fecha_vencimiento: date opcional

src/features/expediente/schemas/cuentaBancaria.schema.ts
  - entidad_financiera_id: number
  - numero_cuenta: string min 5
  - tipo_cuenta: enum ['ahorros', 'corriente']
```

### Hooks a crear
```
src/features/expediente/hooks/useServidores.ts
  - useQuery servidores.index con ServidorParams
  - Paginación server-side

src/features/expediente/hooks/useServidor.ts
  - useQuery servidores.show por id

src/features/expediente/hooks/useServidorMutations.ts
  - crear, editar servidor
  - onSuccess: invalidar ['servidores']

src/features/expediente/hooks/useContratos.ts
  - useQuery contratos por servidorId

src/features/expediente/hooks/useContratoMutations.ts
  - crear contrato

src/features/expediente/hooks/useDocumentos.ts
  - useQuery documentos por servidorId

src/features/expediente/hooks/useCuentasBancarias.ts
  - useQuery cuentas por servidorId

src/features/expediente/hooks/useMovimientosPersonal.ts
  - useQuery movimientos por servidorId

src/features/expediente/hooks/useProvincias.ts
  - useQuery provincias (staleTime: 1 hora)

src/features/expediente/hooks/useCantones.ts
  - useQuery cantones por provinciaId
```

### Componentes a crear
```
LISTADO DE SERVIDORES:
src/features/expediente/components/ServidorPage.tsx
  - Orquestador principal (máx 80 líneas)

src/features/expediente/components/ServidorToolbar.tsx
  - TextInput búsqueda (contained)
  - Select filtro por unidad (contained)
  - Select filtro por estado contrato (contained)
  - Botón "Nuevo servidor" con IconUserPlus

src/features/expediente/components/ServidorTable.tsx
  - DataTable con paginación server-side
  - Columna clic → abre ServidorDetail

src/features/expediente/components/servidor.columns.tsx
  - Cédula, Nombre completo, Unidad, Cargo, Estado, Acciones
  - Badge de estado contrato (vigente=verde, terminado=gris)
  - Botones: IconEye (ver), IconEdit (editar)

FICHA DEL SERVIDOR (Drawer o página /expediente/[id]):
src/features/expediente/components/ServidorDetail.tsx
  - Drawer lateral ancho (600px desktop, fullScreen móvil)
  - Tabs: Datos personales | Contratos | Documentos |
          Cuentas bancarias | Discapacidad | Movimientos

src/features/expediente/components/tabs/DatosPersonalesTab.tsx
  - Muestra datos del servidor en formato de ficha
  - Avatar con iniciales
  - Botón editar abre ServidorModal

src/features/expediente/components/tabs/ContratosTab.tsx
  - Lista contratos del servidor
  - Badge estado (vigente/terminado/cancelado)
  - Botón "Nuevo contrato"

src/features/expediente/components/tabs/DocumentosTab.tsx
  - Lista documentos con tipo, número, fechas
  - Botón descargar/ver documento
  - Botón "Subir documento"

src/features/expediente/components/tabs/CuentasBancariasTab.tsx
  - Lista cuentas bancarias
  - Entidad, número enmascarado, tipo
  - Botón "Nueva cuenta"

src/features/expediente/components/tabs/DiscapacidadTab.tsx
  - Registro de discapacidades si aplica
  - Tipo, porcentaje, carnet CONADIS

src/features/expediente/components/tabs/MovimientosTab.tsx
  - Timeline de movimientos de personal
  - Ascensos, traslados, licencias

FORMULARIOS:
src/features/expediente/components/ServidorModal.tsx
  - Modal crear/editar servidor
  - fullScreen en móvil, size="xl" desktop

src/features/expediente/components/ServidorForm.tsx
  - Grid 2 columnas responsive
  - Secciones: Datos personales, Contacto, Domicilio
  - Select Provincia → carga Cantones dinámicamente
  - Máx 150 líneas → dividir en ServidorFormPersonal.tsx
    y ServidorFormContacto.tsx si supera

src/features/expediente/components/ContratoModal.tsx
src/features/expediente/components/ContratoForm.tsx
  - Select Unidad (searchable, contained)
  - Select Puesto filtrado por unidad (contained)
  - Select Tipo nombramiento (contained)
  - DateInput Fecha ingreso (contained)
  - DateInput Fecha fin (contained, opcional)
  - NumberInput Remuneración (contained)

src/features/expediente/components/DocumentoModal.tsx
  - Dropzone de Mantine para subir archivo
  - Select tipo documento (contained)
  - TextInput número documento (contained)

src/features/expediente/components/CuentaBancariaModal.tsx
  - Select entidad financiera (searchable, contained)
  - TextInput número cuenta (contained)
  - Select tipo cuenta: ahorros/corriente (contained)
```

### Página a crear
```
src/app/(dashboard)/expediente/page.tsx
  - PageHeader: "Expediente Digital" + IconFolder
  - Botón "Nuevo servidor" en actions del header
  - ServidorToolbar
  - ServidorTable
  - ServidorModal (estado: opened, servidorId)
  - ServidorDetail (estado: opened, servidorId)
  - Máx 80 líneas
```

---

## SPRINT F-05 — Nómina y Asistencia ⏳ PENDIENTE

**Rama:** feature/sprint-f05
**Rutas:** /nomina, /asistencia

### Endpoints a consumir
```
# Nómina
GET  /nomina
POST /nomina/calcular
GET  /nomina/{id}
POST /nomina/{id}/cerrar
GET  /nomina/{id}/rol-pago/{servidorId}

# Asistencia
GET  /asistencia/marcaciones
GET  /asistencia/permisos
POST /asistencia/permisos          (solicitud UATH)
GET  /asistencia/permisos/{id}
POST /asistencia/permisos/{id}/confirmar
POST /asistencia/permisos/{id}/anular
GET  /asistencia/vacaciones
```

### Tipos disponibles en api.ts
```
RolPago, DetalleNomina, Permiso, Vacacion
```

### Componentes a crear — Nómina
```
src/features/nomina/components/NominaPage.tsx
  - Tabs: Lista nóminas | Calcular nómina

src/features/nomina/components/NominaToolbar.tsx
  - Select mes/año (contained)
  - Select estado nómina (contained)
  - Botón "Calcular nómina" (solo admin)

src/features/nomina/components/NominaTable.tsx
  - DataTable: período, estado, total servidores, total monto
  - Badge estado: abierta/cerrada/contabilizada
  - Botón ver detalle → abre NominaDetail

src/features/nomina/components/nomina.columns.tsx

src/features/nomina/components/NominaDetail.tsx
  - Drawer con detalle de nómina
  - Lista de roles de pago por servidor
  - Resumen: ingresos, descuentos, neto

src/features/nomina/components/RolPagoModal.tsx
  - Vista del rol de pago individual
  - Tabla de conceptos (ingresos/descuentos)
  - Total a pagar

src/features/nomina/components/NominaCalcularModal.tsx
  - Confirmación antes de calcular
  - Select período (mes/año)
  - Advertencia: "Esta acción calculará la nómina para todos los servidores activos"
```

### Componentes a crear — Asistencia
```
src/features/asistencia/components/AsistenciaPage.tsx
  - Tabs: Marcaciones | Permisos | Vacaciones

src/features/asistencia/components/MarcacionesTab.tsx
  - DataTable marcaciones
  - Filtro por servidor y rango de fechas
  - Columnas: servidor, fecha, hora entrada, hora salida, estado

src/features/asistencia/components/marcacion.columns.tsx

src/features/asistencia/components/PermisosTab.tsx
  - DataTable permisos
  - Badge estado: solicitado/confirmado/anulado
  - Botones confirmar/anular (según rol)

src/features/asistencia/components/permiso.columns.tsx

src/features/asistencia/components/PermisoModal.tsx
  - Modal solicitar/ver permiso
  - Select servidor (admin) o solo del propio (autoservicio)
  - Select tipo permiso
  - DateInput fecha
  - NumberInput horas
  - Textarea justificación

src/features/asistencia/components/VacacionesTab.tsx
  - DataTable vacaciones
  - Días disponibles por servidor
  - Estado de solicitudes
```

---

## SPRINT F-06 — Viáticos ⏳ PENDIENTE

**Rama:** feature/sprint-f06
**Ruta:** /viaticos

### Endpoints a consumir
```
GET    /viaticos
POST   /viaticos
GET    /viaticos/{id}
POST   /viaticos/{id}/solicitar
POST   /viaticos/servidor/{servidorId}/solicitar
POST   /viaticos/{id}/liquidar
GET    /viaticos/{id}/destinos
POST   /viaticos/{id}/destinos
GET    /viaticos/{id}/transportes
POST   /viaticos/{id}/transportes
GET    /viaticos/{id}/facturas
POST   /viaticos/{id}/facturas
GET    /comisiones
POST   /comisiones
GET    /autorizaciones-vuelo
POST   /autorizaciones-vuelo/{id}/aprobar
POST   /autorizaciones-vuelo/{id}/rechazar
```

### Tipos disponibles en api.ts
```
Viatico, DestinoViatico, TransporteViatico,
LiquidacionViatico, FacturaViatico, Comision,
AutorizacionVuelo, EstadoViatico, ViaticoParams
```

### Componentes a crear
```
src/features/viaticos/components/ViaticosPage.tsx
  - Tabs: Viáticos | Comisiones | Autorizaciones de vuelo

src/features/viaticos/components/ViaticoToolbar.tsx
  - TextInput búsqueda (contained)
  - Select filtro por estado EstadoViatico (contained)
  - Select filtro por servidor (contained, admin)
  - Botón "Nuevo viático"

src/features/viaticos/components/ViaticoTable.tsx
  - DataTable con paginación
  - Badge de estado con color por fase del workflow

src/features/viaticos/components/viatico.columns.tsx
  - Código, Servidor, Comisión, Destino, Fechas, Estado, Acciones

src/features/viaticos/components/ViaticoDetail.tsx
  - Drawer detalle completo del viático
  - Tabs: Info general | Destinos | Transporte | Facturas | Liquidación

src/features/viaticos/components/tabs/ViaticoInfoTab.tsx
  - Datos del viático: servidor, comisión, motivo, fechas, zona
  - Timeline del workflow de aprobación
  - Botones de acción según rol y estado actual

src/features/viaticos/components/tabs/DestinosTab.tsx
  - Lista de destinos con provincia, ciudad, fechas
  - Botón agregar destino

src/features/viaticos/components/tabs/TransporteTab.tsx
  - Lista medios de transporte
  - Badge tipo transporte
  - Si es avión → muestra estado autorización de vuelo

src/features/viaticos/components/tabs/FacturasTab.tsx
  - Lista facturas con concepto, número, monto
  - Total facturas vs. anticipo

src/features/viaticos/components/tabs/LiquidacionTab.tsx
  - Resumen de liquidación
  - Diferencia: anticipo vs. gasto real
  - Estado de liquidación

src/features/viaticos/components/ViaticoModal.tsx
  - Modal crear viático
  - fullScreen móvil, size="xl" desktop

src/features/viaticos/components/ViaticoForm.tsx
  - Select comisión (searchable, contained)
  - Select servidor (admin) (contained)
  - Select zona: dentro_provincia/fuera_provincia/exterior
  - DateInput fecha inicio/fin
  - NumberInput días
  - NumberInput anticipo solicitado
  - Textarea motivo (contained)

src/features/viaticos/components/DestinoForm.tsx
  - Select provincia origen/destino (contained)
  - TextInput ciudad (contained)
  - DateInput fecha llegada/salida (contained)

src/features/viaticos/components/FacturaForm.tsx
  - Select concepto ConceptoFactura (contained)
  - TextInput número factura (contained)
  - TextInput RUC emisor (contained)
  - NumberInput monto (contained)
  - DateInput fecha factura (contained)

src/features/viaticos/components/ComisionModal.tsx
src/features/viaticos/components/ComisionForm.tsx
  - TextInput código comisión (contained, auto-generado)
  - Textarea motivo (contained)
  - Select unidad administrativa (contained)
  - DateInput fecha inicio/fin

src/features/viaticos/components/WorkflowBadge.tsx
  - Badge de estado con colores del workflow
  - solicitado=gris, aprobado_jefe=azul, aprobado_director=cyan
  - aprobado_autoridad=indigo, aprobado_uath=violeta
  - aprobado_financiero=verde, liquidado=esmeralda
```

---

## SPRINT F-07 — Dispensario Médico ⏳ PENDIENTE

**Rama:** feature/sprint-f07
**Ruta:** /dispensario

### Endpoints a consumir
```
GET  /dispensario/dashboard/kpis
GET  /dispensario/agenda
POST /dispensario/agenda
GET  /dispensario/agenda/{id}
POST /dispensario/agenda/{id}/confirmar
POST /dispensario/agenda/{id}/cancelar
GET  /dispensario/consultas
POST /dispensario/consultas
GET  /dispensario/consultas/{id}
POST /dispensario/triaje
GET  /dispensario/triaje/{consultaId}
POST /dispensario/recetas
GET  /dispensario/recetas/{id}
POST /dispensario/recetas/{id}/despachar
GET  /dispensario/cie10/buscar?q=
GET  /dispensario/historia-clinica/{beneficiarioId}
GET  /dispensario/alergias/{beneficiarioId}
POST /dispensario/alergias
GET  /dispensario/antecedentes/{beneficiarioId}
POST /dispensario/antecedentes
GET  /dispensario/inventario
POST /dispensario/inventario
GET  /dispensario/inventario/kardex/{id}
GET  /expediente/servidores/{id}/beneficiarios
POST /expediente/servidores/{id}/beneficiarios
```

### Tipos disponibles en api.ts
```
Beneficiario, HistoriaClinica, AgendaMedica,
ConsultaMedica, Triaje, RecetaMedica, DiagnosticoCie10,
AlergiaPaciente, AntecedentePaciente, InventarioMedicina,
AgendaParams
```

### Componentes a crear
```
DASHBOARD MÉDICO:
src/features/dispensario/components/DispensarioPage.tsx
  - Tabs: Dashboard | Agenda | Consultas | Inventario

src/features/dispensario/components/DashboardTab.tsx
  - KPI cards: consultas hoy, agendadas, medicamentos críticos
  - Gráfica ECharts: consultas por mes
  - Gráfica ECharts: diagnósticos más frecuentes

AGENDA:
src/features/dispensario/components/AgendaTab.tsx
  - Vista calendario o lista de citas
  - Filtro por médico y fecha
  - Badge estado: programada/confirmada/cancelada

src/features/dispensario/components/AgendaModal.tsx
src/features/dispensario/components/AgendaForm.tsx
  - Select beneficiario/servidor (searchable, contained)
  - DateInput fecha (contained)
  - TextInput hora (contained)
  - Textarea motivo (contained)

CONSULTAS:
src/features/dispensario/components/ConsultasTab.tsx
  - DataTable consultas del día
  - Botón "Nueva consulta"

src/features/dispensario/components/ConsultaDetail.tsx
  - Drawer con detalle de consulta
  - Tabs: Triaje | Diagnóstico | Receta | Historia

src/features/dispensario/components/TriajeForm.tsx
  - NumberInput peso, talla, temperatura (contained)
  - NumberInput presión sistólica/diastólica (contained)
  - NumberInput frecuencia cardíaca (contained)
  - Textarea observaciones (contained)

src/features/dispensario/components/DiagnosticoForm.tsx
  - Autocomplete búsqueda CIE-10 (AsyncAutocomplete)
  - Lista diagnósticos seleccionados
  - Textarea observaciones médicas (contained)
  - Textarea tratamiento (contained)

src/features/dispensario/components/RecetaForm.tsx
  - Autocomplete búsqueda medicamento del inventario
  - Lista ítems de receta: medicamento, dosis, frecuencia, días
  - Botón despachar receta

HISTORIA CLÍNICA:
src/features/dispensario/components/HistoriaClinicaDrawer.tsx
  - Drawer con historia completa del paciente
  - Tabs: Consultas previas | Alergias | Antecedentes

INVENTARIO:
src/features/dispensario/components/InventarioTab.tsx
  - DataTable medicamentos con stock
  - Badge alerta stock mínimo (rojo si crítico)
  - Botón "Registrar ingreso"

src/features/dispensario/components/inventario.columns.tsx
  - Nombre, código, stock actual, stock mínimo, unidad, estado

src/features/dispensario/components/InventarioModal.tsx
src/features/dispensario/components/InventarioForm.tsx
  - TextInput nombre medicamento (contained)
  - TextInput código (contained)
  - NumberInput stock inicial (contained)
  - NumberInput stock mínimo (contained)
  - TextInput unidad medida (contained)
  - DateInput fecha vencimiento (contained)
```

---

## SPRINT F-08 — Autoservicio del Servidor ⏳ PENDIENTE

**Rama:** feature/sprint-f08
**Ruta:** /autoservicio/*

### Objetivo
Portal de autoservicio donde el servidor puede gestionar
sus propias solicitudes sin intervención de UATH.

### Endpoints a consumir
```
GET  /auth/perfil
PUT  /auth/perfil (si existe)
GET  /autoservicio/permisos
POST /autoservicio/permisos/solicitar-cita
GET  /autoservicio/vacaciones
GET  /asistencia/marcaciones (propias)
GET  /actividades/mis-actividades
POST /actividades
PUT  /actividades/{id}
DELETE /actividades/{id}
GET  /helpdesk/tickets (propios)
POST /helpdesk/tickets
GET  /expediente/servidores/{id}/beneficiarios
POST /expediente/servidores/{id}/beneficiarios
```

### Componentes a crear
```
src/features/autoservicio/components/MiPerfilPage.tsx
  - Datos personales del servidor autenticado
  - Avatar con iniciales grandes
  - Información: cargo, unidad, tipo nombramiento
  - Información de contacto
  - Botón "Editar datos de contacto"

src/features/autoservicio/components/MisPermisosPage.tsx
  - Lista permisos propios con estado
  - Botón "Solicitar permiso"
  - PermisoModal con campos para autoservicio

src/features/autoservicio/components/MisVacacionesPage.tsx
  - Días disponibles en card destacada
  - Historial de vacaciones tomadas
  - Botón "Solicitar vacaciones"

src/features/autoservicio/components/MisMarcacionesPage.tsx
  - Tabla marcaciones propias
  - Filtro por rango de fechas
  - Resumen: días trabajados, atrasos, ausencias

src/features/autoservicio/components/MisActividadesPage.tsx
  - Lista actividades laborales del mes
  - Formulario agregar actividad diaria:
    - DateInput fecha (contained)
    - Select categoría: reunion/visita_campo/informe/
      capacitacion/tramite/otro (contained)
    - TimeInput hora inicio/fin (contained)
    - Textarea descripción (contained)
  - Botones editar/eliminar actividad

src/features/autoservicio/components/MisBeneficiariosPage.tsx
  - Lista familiares beneficiarios del dispensario
  - Botón "Agregar beneficiario"
  - BeneficiarioModal con datos del familiar

src/features/autoservicio/components/MisTicketsPage.tsx
  - Lista tickets de soporte propios
  - Estado: abierto/en_progreso/resuelto/cerrado
  - Botón "Nuevo ticket"
  - Redirige a Helpdesk simplificado
```

### Páginas a crear
```
src/app/(dashboard)/autoservicio/mi-perfil/page.tsx
src/app/(dashboard)/autoservicio/mis-permisos/page.tsx
src/app/(dashboard)/autoservicio/mis-vacaciones/page.tsx
src/app/(dashboard)/autoservicio/mis-marcaciones/page.tsx
src/app/(dashboard)/autoservicio/mis-actividades/page.tsx
src/app/(dashboard)/autoservicio/mis-tickets/page.tsx
```

---

## SPRINT F-09 — Selección y Evaluación ⏳ PENDIENTE

**Rama:** feature/sprint-f09
**Rutas:** /seleccion, /evaluacion

### Endpoints a consumir
```
# Selección
GET  /seleccion/convocatorias
POST /seleccion/convocatorias
GET  /seleccion/convocatorias/{id}
GET  /seleccion/postulantes
POST /seleccion/postulantes
POST /seleccion/calificar
POST /seleccion/declarar-ganador
GET  /seleccion/onboarding

# Evaluación
GET  /evaluacion/criterios
POST /evaluacion/criterios
GET  /evaluacion/evaluaciones
POST /evaluacion/evaluaciones
GET  /evaluacion/resultados
GET  /evaluacion/planes-mejora
POST /evaluacion/planes-mejora
```

### Componentes a crear — Selección
```
src/features/seleccion/components/SeleccionPage.tsx
  - Tabs: Convocatorias | Postulantes | Onboarding

src/features/seleccion/components/ConvocatoriaTable.tsx
  - DataTable convocatorias con estado y fechas

src/features/seleccion/components/convocatoria.columns.tsx

src/features/seleccion/components/ConvocatoriaModal.tsx
src/features/seleccion/components/ConvocatoriaForm.tsx
  - TextInput cargo convocado (contained)
  - Select unidad administrativa (contained)
  - DateInput fecha inicio/cierre inscripciones
  - NumberInput vacantes
  - Textarea requisitos (contained)
  - Textarea descripción (contained)

src/features/seleccion/components/PostulantesTab.tsx
  - DataTable postulantes por convocatoria
  - Badge calificación
  - Botón calificar, declarar ganador

src/features/seleccion/components/CalificarModal.tsx
  - NumberInput puntaje (contained)
  - Textarea observaciones (contained)
```

### Componentes a crear — Evaluación
```
src/features/evaluacion/components/EvaluacionPage.tsx
  - Tabs: Evaluaciones | Criterios | Planes de mejora

src/features/evaluacion/components/EvaluacionTable.tsx
  - DataTable evaluaciones con servidor, período, puntaje

src/features/evaluacion/components/evaluacion.columns.tsx

src/features/evaluacion/components/EvaluacionModal.tsx
src/features/evaluacion/components/EvaluacionForm.tsx
  - Select servidor evaluado (contained)
  - Select período (contained)
  - Lista de criterios con NumberInput puntaje por criterio
  - Textarea observaciones generales

src/features/evaluacion/components/PlanMejoraModal.tsx
  - Select evaluación relacionada (contained)
  - Textarea objetivos (contained)
  - DateInput fecha límite (contained)
  - TextInput acciones comprometidas (lista dinámica)
```

---

## SPRINT F-10 — Capacitación y Bienestar ⏳ PENDIENTE

**Rama:** feature/sprint-f10
**Rutas:** /capacitacion, /bienestar

### Endpoints a consumir
```
# Capacitación
GET  /capacitacion/planes
POST /capacitacion/planes
GET  /capacitacion/planes/{id}
GET  /capacitacion/cursos
POST /capacitacion/cursos
GET  /capacitacion/inscripciones
POST /capacitacion/inscripciones
GET  /capacitacion/certificados
POST /capacitacion/evaluaciones

# Bienestar
GET  /bienestar/planes
POST /bienestar/planes
GET  /bienestar/actividades
POST /bienestar/actividades
GET  /bienestar/encuestas
POST /bienestar/encuestas
GET  /bienestar/resultados
```

### Componentes a crear — Capacitación
```
src/features/capacitacion/components/CapacitacionPage.tsx
  - Tabs: Plan de capacitación | Cursos | Inscripciones | Certificados

src/features/capacitacion/components/PlanCapacitacionTab.tsx
  - Lista planes con estado y período
  - Botón crear plan

src/features/capacitacion/components/CursosTab.tsx
  - DataTable cursos
  - Badge modalidad: presencial/virtual/híbrido
  - Botón inscribir participantes

src/features/capacitacion/components/curso.columns.tsx

src/features/capacitacion/components/CursoModal.tsx
src/features/capacitacion/components/CursoForm.tsx
  - TextInput nombre curso (contained)
  - Select modalidad (contained)
  - DateInput fecha inicio/fin (contained)
  - NumberInput horas (contained)
  - TextInput instructor (contained)
  - NumberInput costo (contained)
  - Textarea descripción (contained)

src/features/capacitacion/components/InscripcionModal.tsx
  - MultiSelect servidores a inscribir
  - Select curso (contained)

src/features/capacitacion/components/CertificadosTab.tsx
  - DataTable certificados por servidor
  - Botón ver/descargar certificado
```

### Componentes a crear — Bienestar
```
src/features/bienestar/components/BienestarPage.tsx
  - Tabs: Plan | Actividades | Encuestas | Resultados

src/features/bienestar/components/PlanBienestarTab.tsx
  - Plan anual de bienestar
  - Timeline de actividades programadas

src/features/bienestar/components/ActividadesTab.tsx
  - DataTable actividades realizadas
  - Badge tipo: deportiva/cultural/salud/social

src/features/bienestar/components/ActividadModal.tsx
src/features/bienestar/components/ActividadForm.tsx
  - TextInput nombre actividad (contained)
  - Select tipo (contained)
  - DateInput fecha (contained)
  - TextInput lugar (contained)
  - NumberInput participantes esperados (contained)
  - Textarea descripción (contained)

src/features/bienestar/components/EncuestasTab.tsx
  - Lista encuestas de clima laboral
  - Badge estado: activa/cerrada
  - Gráfica ECharts resultados promedio
```

---

## SPRINT F-11 — SSO + Disciplinario + SGD ⏳ PENDIENTE

**Rama:** feature/sprint-f11
**Rutas:** /sso, /disciplinario, /sgd

### Endpoints a consumir — SSO
```
GET  /sso/riesgos
POST /sso/riesgos
PUT  /sso/riesgos/{id}
GET  /sso/inspecciones
POST /sso/inspecciones
GET  /sso/accidentes
POST /sso/accidentes
GET  /sso/equipos-proteccion
POST /sso/equipos-proteccion
GET  /sso/capacitaciones
POST /sso/capacitaciones
```

### Endpoints a consumir — Disciplinario
```
GET  /disciplinario/sumarios
POST /disciplinario/sumarios
GET  /disciplinario/sumarios/{id}
GET  /disciplinario/sanciones
POST /disciplinario/sanciones
```

### Endpoints a consumir — SGD
```
GET  /sgd/documentos
POST /sgd/documentos
GET  /sgd/tramites
POST /sgd/tramites
GET  /sgd/series-documentales
GET  /sgd/expedientes-electronicos
```

### Componentes a crear — SSO
```
src/features/sso/components/SsoPage.tsx
  - Tabs: Riesgos | Inspecciones | Accidentes |
          Equipos protección | Capacitaciones SSO

src/features/sso/components/RiesgosTab.tsx
  - DataTable riesgos laborales por área
  - Badge nivel riesgo: bajo/medio/alto/crítico
  - Color badge según nivel

src/features/sso/components/InspeccionesTab.tsx
  - DataTable inspecciones realizadas
  - Badge resultado: conforme/no_conforme

src/features/sso/components/AccidentesTab.tsx
  - DataTable accidentes de trabajo
  - Badge gravedad: leve/grave/mortal
```

### Componentes a crear — Disciplinario
```
src/features/disciplinario/components/DisciplinarioPage.tsx
  - Tabs: Sumarios | Sanciones

src/features/disciplinario/components/SumariosTab.tsx
  - DataTable sumarios con servidor, tipo falta, estado
  - Badge estado: iniciado/en_proceso/resuelto/archivado

src/features/disciplinario/components/SumarioModal.tsx
src/features/disciplinario/components/SumarioForm.tsx
  - Select servidor investigado (contained)
  - Select tipo falta (contained)
  - DateInput fecha inicio (contained)
  - Textarea descripción hechos (contained)
  - Dropzone documentos soporte

src/features/disciplinario/components/SancionesTab.tsx
  - DataTable sanciones aplicadas
  - Badge tipo: amonestacion/multa/suspension/destitución
```

### Componentes a crear — SGD
```
src/features/sgd/components/SgdPage.tsx
  - Tabs: Documentos | Trámites | Series documentales

src/features/sgd/components/DocumentosTab.tsx
  - DataTable documentos institucionales
  - Filtro por serie documental, tipo, fecha
  - Botón subir documento

src/features/sgd/components/TramitesTab.tsx
  - DataTable trámites con estado de workflow
  - Badge estado: recibido/en_proceso/resuelto
  - Timeline del trámite al hacer clic
```

---

## SPRINT F-12 — Helpdesk + Inventario TI ⏳ PENDIENTE

**Rama:** feature/sprint-f12
**Rutas:** /helpdesk, /inventario-ti

### Endpoints a consumir — Helpdesk
```
GET  /helpdesk/tickets
POST /helpdesk/tickets
GET  /helpdesk/tickets/{id}
POST /helpdesk/tickets/{id}/cambiar-estado
POST /helpdesk/tickets/{id}/asignar
POST /helpdesk/tickets/{id}/escalar
POST /helpdesk/tickets/{id}/cerrar
POST /helpdesk/tickets/{id}/vincular-bien
GET  /helpdesk/tecnicos
GET  /helpdesk/tecnico/carga-trabajo
GET  /helpdesk/slas
POST /helpdesk/slas
```

### Endpoints a consumir — Inventario TI
```
GET  /inventario-ti/bienes
POST /inventario-ti/bienes
GET  /inventario-ti/bienes/{id}
GET  /inventario-ti/asignaciones
POST /inventario-ti/asignaciones
GET  /inventario-ti/mantenimientos
POST /inventario-ti/mantenimientos
```

### Componentes a crear — Helpdesk
```
src/features/helpdesk/components/HelpdeskPage.tsx
  - Tabs: Tickets | Técnicos | SLAs | Carga de trabajo

src/features/helpdesk/components/TicketToolbar.tsx
  - TextInput búsqueda (contained)
  - Select filtro estado (contained)
  - Select filtro técnico asignado (contained)
  - Select filtro categoría (contained)
  - Botón "Nuevo ticket"

src/features/helpdesk/components/TicketTable.tsx
  - DataTable tickets con prioridad, estado, técnico, SLA

src/features/helpdesk/components/ticket.columns.tsx
  - Badge prioridad: baja=gris/media=amber/alta=rojo/crítica=rojo oscuro
  - Badge estado: abierto/en_progreso/resuelto/cerrado
  - Indicador SLA: en tiempo=verde, próximo=amber, vencido=rojo

src/features/helpdesk/components/TicketDetail.tsx
  - Drawer con detalle del ticket
  - Timeline de cambios de estado
  - Sección comentarios
  - Botones: asignar técnico, escalar, cerrar

src/features/helpdesk/components/TicketModal.tsx
src/features/helpdesk/components/TicketForm.tsx
  - TextInput título (contained)
  - Select categoría (contained)
  - Select prioridad (contained)
  - Select servidor afectado (contained, admin)
  - Textarea descripción del problema (contained)
  - Dropzone adjuntos opcionales

src/features/helpdesk/components/CargaTrabajoTab.tsx
  - Gráfica ECharts: tickets por técnico
  - Gráfica ECharts: tickets por estado
```

### Componentes a crear — Inventario TI
```
src/features/inventario-ti/components/InventarioTiPage.tsx
  - Tabs: Bienes | Asignaciones | Mantenimientos

src/features/inventario-ti/components/BienesTab.tsx
  - DataTable bienes informáticos
  - Badge estado: disponible/asignado/mantenimiento/baja

src/features/inventario-ti/components/bien.columns.tsx
  - Código, descripción, marca, modelo, serie, estado, asignado a

src/features/inventario-ti/components/BienModal.tsx
src/features/inventario-ti/components/BienForm.tsx
  - TextInput código bien (contained)
  - TextInput descripción (contained)
  - Select tipo bien (contained)
  - Select marca (contained)
  - TextInput modelo (contained)
  - TextInput número serie (contained)
  - Select origen: compra/donacion/transferencia (contained)
  - DateInput fecha adquisición (contained)
  - NumberInput valor (contained)

src/features/inventario-ti/components/AsignacionesTab.tsx
  - DataTable asignaciones activas
  - Columnas: bien, servidor, unidad, fecha asignación

src/features/inventario-ti/components/AsignacionModal.tsx
  - Select bien disponible (searchable, contained)
  - Select servidor (searchable, contained)
  - DateInput fecha asignación (contained)
  - Textarea observaciones (contained)

src/features/inventario-ti/components/MantenimientosTab.tsx
  - DataTable mantenimientos
  - Badge tipo: preventivo/correctivo
  - Columnas: bien, tipo, fecha, técnico, costo
```

---

## SPRINT F-13 — Reportería y BI ⏳ PENDIENTE

**Rama:** feature/sprint-f13
**Ruta:** /reporteria

### Endpoints a consumir
```
GET  /reporteria/dashboard
GET  /reporteria/configuraciones
POST /reporteria/configuraciones
POST /reporteria/ad-hoc
POST /reporteria/background
GET  /reporteria/background/{job_id}
GET  /dispensario/dashboard/kpis
```

### Componentes a crear
```
src/features/reporteria/components/ReporteriaPage.tsx
  - Tabs: Dashboard ejecutivo | Reportes | Configuración

src/features/reporteria/components/DashboardTab.tsx
  KPI Cards (SimpleGrid 4 columnas):
  - Total servidores activos
  - Nómina del mes (monto total)
  - Consultas médicas del mes
  - Tickets abiertos

  Gráficas ECharts (Grid 2 columnas):
  - Barras: servidores por unidad administrativa
  - Línea: evolución nómina últimos 6 meses
  - Dona: distribución por tipo nombramiento
  - Barras: viáticos por mes

src/features/reporteria/components/ReportesTab.tsx
  - Select módulo (nomina/viaticos/asistencia/dispensario)
  - Select tipo reporte
  - DateRangePicker período (contained)
  - Botón "Generar reporte"
  - Indicador de progreso si es asíncrono
  - Botón descargar cuando esté listo

src/features/reporteria/components/KpiCard.tsx
  - Componente reutilizable para KPI cards
  - Props: label, value, unit?, trend?, trendValue?, color?
  - Flecha arriba/abajo según trend

src/features/reporteria/components/charts/ServidoresPorUnidad.tsx
  - ECharts barras horizontales
  - Top 10 unidades por número de servidores

src/features/reporteria/components/charts/EvolucionNomina.tsx
  - ECharts línea
  - Últimos 6 meses de nómina

src/features/reporteria/components/charts/DistribucionNombramiento.tsx
  - ECharts dona
  - Por tipo de nombramiento

src/features/reporteria/components/charts/ViaticosPorMes.tsx
  - ECharts barras
  - Monto de viáticos por mes
```

---

## SPRINT F-14 — Dashboard Ejecutivo ⏳ PENDIENTE

**Rama:** feature/sprint-f14
**Ruta:** / (página principal del dashboard)

### Objetivo
Reemplazar el placeholder actual de `/` con un dashboard
ejecutivo real con KPIs y gráficas del sistema.

### Componentes a crear
```
src/app/(dashboard)/page.tsx  (REEMPLAZAR placeholder actual)

src/features/dashboard/components/DashboardPage.tsx
  - Saludo personalizado con nombre del usuario
  - Fecha actual
  - KPI cards en SimpleGrid responsive
  - Sección "Pendientes de atención" (según rol)
  - Gráficas resumidas

src/features/dashboard/components/KpiSection.tsx
  - SimpleGrid cols={{ base:1, sm:2, lg:4 }}
  - KpiCard reutilizable de Reportería

src/features/dashboard/components/PendientesSection.tsx
  - Lista de acciones pendientes según rol:
    Admin UATH: viáticos por aprobar, permisos pendientes
    Médico: citas del día, recetas pendientes despacho
    Técnico: tickets asignados abiertos
    Servidor: mis solicitudes en trámite

src/features/dashboard/components/AccesosRapidos.tsx
  - Grid de botones de acceso rápido a módulos frecuentes
  - Según rol del usuario
```

---

## SPRINT F-15 — QA + Optimización + Deploy ⏳ PENDIENTE

**Rama:** feature/sprint-f15

### Tareas de calidad
```
□ Revisar todos los archivos con más líneas que el límite
□ Eliminar todos los console.log de debug
□ Verificar que no hay ningún 'any' en TypeScript
□ Verificar que todos los modals tienen fullScreen={isMobile}
□ Verificar que todos los Grid.Col tienen span responsive
□ Verificar que todos los inputs usan useContainedInput()
□ Verificar que ningún input tiene leftSection decorativo
□ Verificar que todos los iconos son de @tabler/icons-react
□ Verificar que todas las tablas usan mantine-datatable
□ Verificar que todas las gráficas usan echarts-for-react
□ Ejecutar npm run build limpio sin warnings
□ Revisar accesibilidad: aria-labels en botones de ícono
□ Revisar que el toggle claro/oscuro funciona en todas las páginas
□ Probar en móvil (Chrome DevTools): login, dashboard, formularios
□ Probar flujo completo: login → cambiar password → dashboard
```

### Optimizaciones
```
□ Lazy loading de páginas pesadas con next/dynamic
□ Memoización con useMemo/useCallback donde aplique
□ Revisar staleTime de queries (algunos pueden ser más largos)
□ Comprimir imágenes si se agregan logos o avatares
□ Configurar next.config.js para producción
```

### Deploy
```
□ Configurar variables de entorno de producción
□ Configurar nginx sgth-frontend.conf en Laragon/servidor
□ Configurar PM2 para mantener Next.js corriendo
□ Verificar CORS del backend para el dominio de producción
□ Configurar sgth-app.test en hosts y nginx para desarrollo
```

---

## NOTAS IMPORTANTES PARA EL AGENTE

### Sobre los tipos
Antes de crear cualquier tipo nuevo, verificar en src/types/api.ts.
Si el tipo existe, importarlo. Si no existe pero debería generarse
desde OpenAPI, ejecutar npm run types:generate después de actualizar
el openapi.yaml del backend.

### Sobre los servicios
Cada feature tiene su propio service. No mezclar llamadas de
diferentes módulos en el mismo service. Si necesitas datos de
otro módulo, importa el service de ese módulo.

### Sobre las mutations
Siempre invalidar las queries relacionadas en onSuccess.
Siempre mostrar notificación con @mantine/notifications.
Siempre tipar el error como AxiosError<ApiResponse>.

### Sobre los modals
Siempre detectar si es móvil con useMobileBreakpoint().
Siempre pasar fullScreen={isMobile} al componente Modal.
En móvil radius debe ser 0 cuando fullScreen es true.

### Sobre las columnas de DataTable
Siempre en archivo separado: [entidad].columns.tsx
Siempre tipar con DataTableColumn<TipoEntidad>[].
Nunca definir columnas inline en el componente de tabla.

### Sobre los schemas Zod
Siempre en archivo separado: [entidad].schema.ts
Siempre importar de 'zod/v4'
Siempre exportar el schema Y el tipo inferido.

---

_Fin del documento — SGTH Sprints v1.0_
