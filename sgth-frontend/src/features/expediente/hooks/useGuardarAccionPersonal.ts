import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { getApiErrorMessage, type CatalogoAccionesPersonal } from '@/types/api'
import { movimientoService } from '../services/movimientoService'
import type { MovimientoFormData } from '../schemas/movimiento.schema'
import { admiteMarcacion } from '../utils/nombramiento'
import { buscarClase } from '../utils/catalogoAcciones'

/**
 * Los campos de contratación solo viajan en la clase que los pide —el ingreso—.
 * Enviarlos en un traslado los grabaría en una acción que nunca creará un
 * contrato, y el documento impreso acabaría con un número que no corresponde a
 * nada.
 */
function soloLoQueAplica(
  data: MovimientoFormData,
  pideContratacion: boolean,
  /** El ascenso trae el acta sin ser ingreso (fase 3.3). */
  pideActa = false,
): MovimientoFormData {
  if (pideContratacion) {
    // El supuesto y el acta solo son del provisional (fase 3.2); el backend
    // también los limpia.
    const provisional = data.tipo_nombramiento_propuesto === 'nombramiento_provisional'
    const conSupuesto = {
      ...data,
      supuesto_provisional_propuesto: provisional ? data.supuesto_provisional_propuesto : null,
      acta_concurso_numero: provisional ? (data.acta_concurso_numero?.trim() || null) : null,
      acta_concurso_fecha: provisional ? data.acta_concurso_fecha : null,
    }

    // Cinturón, además del `setValue` al cambiar de nombramiento: un borrador
    // que ya venía con la marcación puesta y un nombramiento que no marca se
    // edita sin tocar ese selector, y entonces el `onChange` no corre. La
    // modalidad manda sobre lo que quedó guardado.
    return admiteMarcacion(data.tipo_nombramiento_propuesto)
      ? conSupuesto
      : { ...conSupuesto, puede_marcar: false }
  }

  return {
    ...data,
    numero_contrato: null,
    fecha_fin_propuesta: null,
    puede_marcar: null,
    tipo_nombramiento_propuesto: null,
    supuesto_provisional_propuesto: null,
    acta_concurso_numero: pideActa ? (data.acta_concurso_numero?.trim() || null) : null,
    acta_concurso_fecha: pideActa ? data.acta_concurso_fecha : null,
    cubre_movimiento_id: null,
  }
}

/**
 * Registra una acción de personal nueva o guarda los cambios de un borrador.
 *
 * Es la misma mutación para los dos casos porque es el mismo formulario: lo
 * único que cambia es el endpoint y lo que se dice al terminar.
 */
export function useGuardarAccionPersonal({
  servidorId,
  movimientoId,
  catalogo,
  onGuardado,
}: {
  servidorId: number
  /** Presente = se edita un borrador; ausente = se registra uno nuevo. */
  movimientoId?: number | null
  /** De aquí sale qué campos aplican a la clase elegida. */
  catalogo: CatalogoAccionesPersonal
  onGuardado: () => void
}) {
  const qc = useQueryClient()
  const edicion = movimientoId != null

  return useMutation({
    mutationFn: (data: MovimientoFormData) => {
      const clase = buscarClase(catalogo, data.clase)
      const limpio = {
        ...soloLoQueAplica(data, !!clase?.pide_contratacion, !!clase?.pide_acta_concurso),
        // La institución y la marca de estudios solo viajan en las clases que
        // las piden (fase 2.3): en otra acción se grabarían sin significar nada.
        institucion_destino: clase?.pide_institucion_destino ? (data.institucion_destino?.trim() || null) : null,
        para_estudios_o_eventos: clase?.admite_para_estudios_o_eventos ? !!data.para_estudios_o_eventos : false,
        // El informe de la UATH, solo en el traslado (fase 3.1).
        informe_uath_numero: clase?.pide_informe_uath_para.length
          ? (data.informe_uath_numero?.trim() || null)
          : null,
        // La sentencia, solo en la restitución (fase 2.6).
        ...(clase?.pide_datos_judiciales
          ? {
              causa_judicial_numero: data.causa_judicial_numero?.trim() || null,
              unidad_judicial: data.unidad_judicial?.trim() || null,
            }
          : {
              causa_judicial_numero: null,
              unidad_judicial: null,
              fecha_sentencia: null,
              fecha_ejecutoria: null,
              remuneraciones_dejadas_de_percibir: null,
            }),
      }

      if (edicion) {
        // La clase y la causal no se envían: cambiar la naturaleza del acto no
        // es editarlo, sino anularlo y registrar otro.
        const { clase: _clase, causal: _causal, ...editable } = limpio

        return movimientoService.actualizarBorrador(movimientoId, editable)
      }

      return movimientoService.crear(servidorId, limpio)
    },
    onSuccess: () => {
      notificar.exito(
        edicion ? 'Borrador actualizado' : 'Acción de personal registrada',
        edicion
          ? 'Los cambios quedaron guardados en la acción de personal.'
          : 'Quedó en borrador, pendiente de revisión y aprobación.',
      )
      qc.invalidateQueries({ queryKey: ['movimientos'] })
      qc.invalidateQueries({ queryKey: ['movimiento'] })
      qc.invalidateQueries({ queryKey: ['bandeja-movimientos'] })
      onGuardado()
    },
    onError: (error) => {
      notificar.error(
        edicion ? 'No se pudo guardar' : 'No se pudo registrar',
        getApiErrorMessage(
          error,
          edicion
            ? 'No se pudo actualizar el borrador.'
            : 'No se pudo registrar la acción de personal.',
        ),
      )
    },
  })
}
