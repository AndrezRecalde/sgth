import { usePeriodosMutations } from './usePeriodosMutations'
import { confirmar } from '@/components/ui'
import type { PeriodoVacacion, PrevisualizacionRecalculo } from '@/types/api'

/**
 * Recalcular un período YA CERRADO, preguntando primero qué cambiaría.
 *
 * Un período cerrado tiene un saldo certificado —comunicado al servidor y
 * arrastrado al año siguiente—, así que la confirmación nombra los días
 * concretos de antes y de después. Advertir de «un cambio» sin decir cuál
 * obliga a aceptar para averiguarlo.
 *
 * Vive en un hook y no dentro del componente porque son dos peticiones
 * encadenadas con un diálogo en medio: la previsualización, la decisión, y solo
 * entonces la escritura.
 */
export function useRecalculoCerrado(servidorId: number | null) {
  const { previsualizarRecalculo, recalcularCerrado } = usePeriodosMutations()

  const abrir = async (periodo: PeriodoVacacion) => {
    if (!servidorId) return

    let previa: PrevisualizacionRecalculo
    try {
      previa = await previsualizarRecalculo.mutateAsync({
        servidorId,
        anio: periodo.anio,
      })
    } catch {
      return // La mutación ya avisó del error.
    }

    const saldoAntes = previa.actual.dias_saldo
    const saldoDespues = previa.propuesto.dias_saldo
    // Medio centésimo: es la precisión con la que se muestran los días.
    const sinCambios = Math.abs(saldoAntes - saldoDespues) < 0.005

    confirmar({
      title: `Recalcular el período ${previa.anio}`,
      message: sinCambios ? (
        <>
          El período <b>{previa.anio}</b> está cerrado y recalcularlo lo dejaría
          igual: <b>{saldoAntes.toFixed(2)} días</b> de saldo. Puedes ejecutarlo,
          pero no cambiará nada.
        </>
      ) : (
        <>
          El período <b>{previa.anio}</b> está cerrado y su saldo ya se
          certificó. Al recalcularlo pasará de <b>{saldoAntes.toFixed(2)}</b> a{' '}
          <b>{saldoDespues.toFixed(2)} días</b>, porque los generados cambian de{' '}
          {previa.actual.dias_generados.toFixed(2)} a{' '}
          {previa.propuesto.dias_generados.toFixed(2)}. Los{' '}
          {previa.propuesto.dias_utilizados.toFixed(2)} días ya gozados no se
          tocan. Queda registrado en la bitácora.
        </>
      ),
      confirmLabel: 'Recalcular',
      destructiva: true,
      onConfirm: () =>
        recalcularCerrado.mutate({ servidorId, anio: periodo.anio }),
    })
  }

  return { abrir, pidiendoPrevisualizacion: previsualizarRecalculo.isPending }
}
