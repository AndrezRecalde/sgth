import type { DefaultValues } from 'react-hook-form'
import type { MovimientoPersonal } from '@/types/api'
import type { CompletarVinculoFormData } from '../schemas/completarVinculo.schema'
import { admiteMarcacion, esLosep } from './nombramiento'

/** Nombramientos cuyo vínculo lleva plazo pactado. */
const CON_PLAZO = ['servicios_ocasionales', 'servicios_profesionales']

export function llevaPlazo(nombramiento: string | null): boolean {
  return nombramiento ? CON_PLAZO.includes(nombramiento) : false
}

/**
 * Lo que el formulario de «Aprobar y registrar el vínculo» trae ya escrito:
 * lo guardado en la acción y, si falta, lo que se deduce del puesto.
 *
 * Hasta el 2026-09-27 ese formulario llevaba seis `useState` y validaba a mano
 * en el `submit`, con lo que faltaba en un `Alert` al pie: el usuario leía
 * «falta número de contrato y remuneración» y tenía que buscar cuáles de los
 * seis campos eran.
 */
export function valoresIniciales(
  movimiento: MovimientoPersonal,
): DefaultValues<CompletarVinculoFormData> {
  const nombramiento = movimiento.tipo_nombramiento_propuesto ?? null
  const puesto = movimiento.puesto_destino

  // La RMU solo se sugiere en LOSEP, donde sale del grupo ocupacional del
  // puesto. En Código del Trabajo y Servicios Profesionales se negocia en el
  // contrato, así que el campo arranca vacío a propósito.
  const rmuSugerida = esLosep(nombramiento) && puesto?.rmu != null ? Number(puesto.rmu) : undefined

  return {
    numero_contrato: movimiento.numero_contrato ?? '',
    remuneracion_propuesta: movimiento.remuneracion_propuesta != null
      ? Number(movimiento.remuneracion_propuesta)
      : rmuSugerida,
    resolucion_numero: movimiento.resolucion_numero ?? '',
    partida_presupuestaria_id: movimiento.partida_presupuestaria_id
      ?? puesto?.partida_presupuestaria?.id
      ?? null,
    // La modalidad manda sobre lo que quedó guardado: servicios profesionales,
    // libre nombramiento y elección popular no marcan nunca.
    puede_marcar: admiteMarcacion(nombramiento) && (movimiento.puede_marcar ?? false),
    fecha_fin_propuesta: movimiento.fecha_fin_propuesta?.split('T')[0] ?? null,
  }
}
