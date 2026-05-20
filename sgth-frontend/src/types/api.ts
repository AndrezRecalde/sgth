// ============================================
// TIPOS DEL API — SGTH GAD Esmeraldas
// ============================================
// Generado automáticamente desde openapi.yaml
// NO editar manualmente.
//
// Para regenerar ejecutar:
// cd sgth-backend
// php artisan scramble:export --path=storage/app/openapi.yaml
// copy storage\app\openapi.yaml ..\sgth-frontend\openapi.yaml
// cd ..\sgth-frontend
// npx openapi-typescript openapi.yaml -o src/types/api.generated.ts
// ============================================

export type { components, paths, operations } from './api.generated'

import type { components } from './api.generated'

// ── Respuesta estándar del API ───────────────
export type ApiResponse<T = unknown> = {
  exito: boolean
  mensaje: string
  datos: T
  errores?: Record<string, string[]>
}

// ── Paginación ───────────────────────────────
export type PaginatedResponse<T> = {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number
  to: number
}

// ── Auth ─────────────────────────────────────
export type LoginRequest = {
  usuario: string
  contrasena: string
}

export type LoginResponse = {
  token: string
  primer_login: boolean
  usuario: {
    id: number
    name: string
    email: string
    roles: string[]
    permisos: string[]
  }
}

// ── Geografía ────────────────────────────────
export type Provincia = components['schemas']['Provincia']
export type Canton = components['schemas']['Canton']

// ── Estructura ───────────────────────────────
export type UnidadAdministrativa = components['schemas']['UnidadAdministrativa']
export type Puesto = components['schemas']['Puesto']
export type TipoUnidad = components['schemas']['TipoUnidad']
export type ExtensionTelefonica = components['schemas']['ExtensionTelefonica']

// ── Expediente ───────────────────────────────
export type Servidor = components['schemas']['Servidor']
export type ContratoServidor = components['schemas']['ContratoServidor']
export type DiscapacidadServidor = components['schemas']['DiscapacidadServidor']
export type EnfermedadCatastroficaServidor = components['schemas']['EnfermedadCatastroficaServidor']
export type CuentaBancariaServidor = components['schemas']['CuentaBancariaServidor']
export type EntidadFinanciera = components['schemas']['EntidadFinanciera']

// ── Viáticos ─────────────────────────────────
export type Viatico = components['schemas']['Viatico']
export type DestinoViatico = components['schemas']['DestinoViatico']
export type TransporteViatico = components['schemas']['TransporteViatico']
export type LiquidacionViatico = components['schemas']['LiquidacionViatico']
export type FacturaViatico = components['schemas']['FacturaViatico']
export type Comision = components['schemas']['Comision']
export type AutorizacionVuelo = components['schemas']['AutorizacionVuelo']

// ── Dispensario ──────────────────────────────
export type Beneficiario = components['schemas']['Beneficiario']
export type HistoriaClinica = components['schemas']['HistoriaClinica']
export type AgendaMedica = components['schemas']['AgendaMedica']
export type ConsultaMedica = components['schemas']['ConsultaMedica']
export type Triaje = components['schemas']['Triaje']
export type RecetaMedica = components['schemas']['RecetaMedica']
export type DiagnosticoCie10 = components['schemas']['DiagnosticoCie10']
export type AlergiaPaciente = components['schemas']['AlergiaPaciente']
export type AntecedentePaciente = components['schemas']['AntecedentePaciente']
export type InventarioMedicina = components['schemas']['InventarioMedicina']

// ── Nómina ───────────────────────────────────
export type RolPago = components['schemas']['RolPago']
export type DetalleRolPago = components['schemas']['DetalleRolPago']

// ── Asistencia ───────────────────────────────
export type Permiso = components['schemas']['Permiso']
export type Vacacion = components['schemas']['Vacacion']

// ── Enums de conveniencia ────────────────────
export type EstadoViatico =
  | 'solicitado'
  | 'aprobado_jefe'
  | 'aprobado_director'
  | 'aprobado_autoridad'
  | 'aprobado_uath'
  | 'aprobado_financiero'
  | 'con_anticipo'
  | 'en_comision'
  | 'pendiente_liquidacion'
  | 'liquidado'
  | 'contabilizado'

export type TipoNombramiento =
  | 'nombramiento_permanente'
  | 'nombramiento_provisional'
  | 'servicios_ocasionales'
  | 'libre_nombramiento_remocion'
  | 'codigo_trabajo'
  | 'servicios_profesionales'

export type EstadoContrato =
  | 'vigente'
  | 'terminado'
  | 'cancelado'

export type ConceptoFactura =
  | 'alimentacion'
  | 'hospedaje'
  | 'transporte_terrestre'
  | 'pasaje_aereo'
  | 'combustible'
  | 'peaje'
  | 'materiales'
  | 'otro'
