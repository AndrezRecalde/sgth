import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { regularizacionTipoService } from '../services/regularizacionTipoService'

const CLAVE = ['tipo-de-puesto', 'por-revisar']

/**
 * Lo que queda por revisar del tipo de puesto (P9, PR 4). `habilitado` para
 * quien no gestiona puestos: el backend le negaría la lista.
 */
export function usePorRevisarTipo(habilitado = true) {
  return useQuery({
    queryKey: CLAVE,
    queryFn: regularizacionTipoService.porRevisar,
    enabled: habilitado,
    staleTime: 1000 * 60,
  })
}

export function useRegularizacionTipoMutations() {
  const qc = useQueryClient()

  // Cambia el tipo o el puesto de un contrato: lo cuentan la lista, la
  // Plantilla y el detalle de cada unidad.
  const invalidar = () => {
    for (const queryKey of [CLAVE, ['puestos'], ['plantilla'], ['organigrama'], ['unidad']]) {
      qc.invalidateQueries({ queryKey })
    }
  }

  const pasarAContrato = useMutation({
    mutationFn: (puestoId: number) => regularizacionTipoService.pasarAContrato(puestoId),
    onSuccess: () => {
      notificar.exito('Puesto de contrato', 'Sus plazas dejaron de contarse como vacantes.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo pasar el puesto a contrato'),
  })

  const reubicar = useMutation({
    mutationFn: ({ contratoId, puestoDestinoId }: { contratoId: number; puestoDestinoId: number }) =>
      regularizacionTipoService.reubicar(contratoId, puestoDestinoId),
    onSuccess: () => {
      notificar.exito('Contrato reubicado', 'Quedó en el puesto de contrato, sin acción de personal.')
      invalidar()
    },
    // El modal pone en su campo el error de validación; el resto se notifica.
    onError: notificar.alFallarSalvoCampos('No se pudo reubicar el contrato'),
  })

  return { pasarAContrato, reubicar }
}
