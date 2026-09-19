import { useMutation, useQueryClient } from '@tanstack/react-query'
import { expedienteService } from '../services/expedienteService'
import type { ServidorBasicoFormData } from '../schemas/servidorBasico.schema'
import type { ServidorLaboralFormData } from '../schemas/servidorLaboral.schema'
import { notificar } from '@/components/ui'
import { erroresDeCampo } from '@/lib/erroresDeCampo'

/**
 * Un 422 con errores de campo lo muestra el formulario debajo de cada campo;
 * notificarlo además repetía el mismo mensaje en otro sitio.
 */
const alFallar = (titulo: string) => (error: unknown) => {
  if (!erroresDeCampo(error)) notificar.alFallar(titulo)(error)
}

export function useServidorMutations() {
  const qc = useQueryClient()

  // invalidateQueries ya vuelve a pedir las consultas activas; el
  // refetchQueries que lo seguía duplicaba cada petición del listado.
  const invalidar = () => qc.invalidateQueries({ queryKey: ['servidores'] })

  const crear = useMutation({
    mutationFn: (data: ServidorBasicoFormData) =>
      expedienteService.crear(data),
    onSuccess: () => {
      notificar.exito('Servidor registrado', 'El expediente fue creado correctamente.')
      invalidar()
    },
    onError: alFallar('No se pudo registrar el servidor'),
  })

  const editar = useMutation({
    mutationFn: ({ id, data }: {
      id: number
      data: Partial<ServidorBasicoFormData & ServidorLaboralFormData>
    }) => expedienteService.editar(id, data),
    onSuccess: (_, { id }) => {
      notificar.exito(
        'Expediente actualizado',
        'Los datos fueron actualizados correctamente.',
      )
      invalidar()
      qc.invalidateQueries({ queryKey: ['servidor', id] })
    },
    onError: alFallar('No se pudo actualizar el expediente'),
  })

  return { crear, editar }
}
