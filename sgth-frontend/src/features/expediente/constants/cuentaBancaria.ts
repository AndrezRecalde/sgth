import type { DefaultValues } from 'react-hook-form'
import type { CuentaBancariaConRelaciones } from '@/types/api'
import type { CuentaBancariaFormData } from '../schemas/cuentaBancaria.schema'

/** Catálogos y valores del formulario de cuenta bancaria, fuera del modal (regla 02). */
export const TIPO_CUENTA_OPTIONS = [
  { value: 'ahorros', label: 'Ahorros' },
  { value: 'corriente', label: 'Corriente' },
]

export const PROPOSITO_OPTIONS = [
  { value: 'sueldo', label: 'Nómina' },
  { value: 'viaticos', label: 'Viáticos' },
  { value: 'ambos', label: 'Nómina y viáticos' },
]

// Sin entidad: todavía no se elige (DefaultValues admite omitir la clave).
export const CUENTA_VACIA: DefaultValues<CuentaBancariaFormData> = {
  numero_cuenta: '',
  tipo_cuenta: 'ahorros',
  proposito: 'sueldo',
  es_principal_sueldo: false,
  es_principal_viatico: false,
  estado: true,
}

export function valoresDeCuenta(c: CuentaBancariaConRelaciones): CuentaBancariaFormData {
  return {
    entidad_financiera_id: Number(c.entidad_financiera_id),
    numero_cuenta: c.numero_cuenta ?? '',
    tipo_cuenta: c.tipo_cuenta === 'corriente' ? 'corriente' : 'ahorros',
    proposito: c.proposito === 'viaticos' || c.proposito === 'ambos' ? c.proposito : 'sueldo',
    es_principal_sueldo: c.es_principal_sueldo ?? false,
    es_principal_viatico: c.es_principal_viatico ?? false,
    estado: c.estado ?? true,
  }
}

/** «sueldo» → «Nómina», para la tabla. */
export const PROPOSITO_LABELS = Object.fromEntries(PROPOSITO_OPTIONS.map((o) => [o.value, o.label]))
