import { useMutation, useQueryClient } from '@tanstack/react-query'
import { viaticoService }              from '../services/viaticoService'
import { notificar } from '@/components/ui'

export function useViaticoLiquidacion() {
  const qc = useQueryClient()

  const guardarActividades = useMutation({
    mutationFn: ({
      viaticoId, actividades,
    }: {
      viaticoId:   number
      actividades: Parameters<
        typeof viaticoService.liquidacion.guardarActividades
      >[1]
    }) => viaticoService.liquidacion.guardarActividades(
      viaticoId, actividades
    ),
    onSuccess: (_data, { viaticoId }) => {
      notificar.exito('Actividades guardadas', 'Las actividades se guardaron correctamente.')
      qc.invalidateQueries({ queryKey: ['liquidacion', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError: notificar.alFallar('No se pudieron guardar las actividades'),
  })

  const guardarFacturas = useMutation({
    mutationFn: ({
      viaticoId, facturas,
    }: {
      viaticoId: number
      facturas:  Parameters<
        typeof viaticoService.liquidacion.guardarFacturas
      >[1]
    }) => viaticoService.liquidacion.guardarFacturas(
      viaticoId, facturas
    ),
    onSuccess: (_data, { viaticoId }) => {
      notificar.exito('Facturas guardadas', 'Los comprobantes se guardaron correctamente.')
      qc.invalidateQueries({ queryKey: ['liquidacion', viaticoId] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
    },
    onError: notificar.alFallar('No se pudieron guardar las facturas'),
  })

  const revisarFactura = useMutation({
    mutationFn: ({ viaticoId, facturaId, decision, observacion }: {
      viaticoId:    number
      facturaId:    number
      decision:     'aceptada' | 'observada'
      observacion?: string
    }) => viaticoService.liquidacion.revisarFactura(viaticoId, facturaId, { decision, observacion }),
    onSuccess: (_data, { decision }) => {
      notificar.exito(
        decision === 'aceptada' ? 'Comprobante aceptado' : 'Comprobante observado',
        decision === 'aceptada'
          ? 'El comprobante cuenta para contabilizar.'
          : 'El servidor verá la observación al corregir la liquidación.',
      )
      qc.invalidateQueries({ queryKey: ['viatico'] })
      qc.invalidateQueries({ queryKey: ['liquidacion'] })
    },
    onError: notificar.alFallar('No se pudo revisar el comprobante'),
  })

  const confirmarLiquidacion = useMutation({
    mutationFn: (viaticoId: number) =>
      viaticoService.liquidacion.confirmar(viaticoId),
    onSuccess: () => {
      notificar.exito(
        'Liquidación registrada',
        'La liquidación fue confirmada correctamente.',
      )
      qc.invalidateQueries({ queryKey: ['viaticos'] })
      qc.invalidateQueries({ queryKey: ['viatico'] })
      qc.invalidateQueries({ queryKey: ['liquidacion'] })
    },
    onError: notificar.alFallar('No se pudo registrar la liquidación'),
  })

  return {
    guardarActividades,
    guardarFacturas,
    confirmarLiquidacion,
    revisarFactura,
  }
}
