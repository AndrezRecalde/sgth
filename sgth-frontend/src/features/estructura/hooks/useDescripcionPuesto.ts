import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { descripcionPuestoService, type DescripcionPuestoDatos } from '../services/descripcionPuestoService'

const clave = (puestoId: number | null) => ['puesto-descripcion', puestoId]

export function useDescripcionPuesto(puestoId: number | null) {
  return useQuery({
    queryKey: clave(puestoId),
    queryFn: () => descripcionPuestoService.obtener(puestoId!),
    enabled: !!puestoId,
    staleTime: 1000 * 60,
  })
}

export function useGuardarDescripcion(puestoId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (datos: DescripcionPuestoDatos) => descripcionPuestoService.guardar(puestoId, datos),
    onSuccess: (guardada) => {
      notificar.exito('Descripción guardada', 'La descripción del puesto quedó actualizada.')
      qc.setQueryData(clave(puestoId), guardada)
    },
    // El formulario pone en su campo el error de validación; el resto se notifica.
    onError: notificar.alFallarSalvoCampos('No se pudo guardar la descripción'),
  })
}
