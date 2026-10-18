import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { clavesComunicados } from '../constants/claves'
import { comunicadosService } from '../services/comunicadosService'
import type { ComunicadoPayload } from '../services/tipos'

/**
 * El ciclo de una publicación. Cada acción cambia el listado y el detalle, así
 * que todas invalidan lo del módulo; cada una avisa con su propio título.
 */
export function useMutacionesComunicado() {
  const qc = useQueryClient()
  const refrescar = () => qc.invalidateQueries({ queryKey: clavesComunicados.todas })

  const crear = useMutation({
    mutationFn: comunicadosService.crear,
    onSuccess: () => {
      refrescar()
      notificar.exito('Borrador guardado', 'Agregue los documentos si hacen falta, revíselo y publíquelo cuando esté listo.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el borrador'),
  })

  const actualizar = useMutation({
    mutationFn: ({ id, datos }: { id: number; datos: ComunicadoPayload }) => comunicadosService.actualizar(id, datos),
    onSuccess: () => {
      refrescar()
      notificar.exito('Borrador guardado', 'Los cambios quedaron guardados.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el borrador'),
  })

  const eliminar = useMutation({
    mutationFn: comunicadosService.eliminar,
    onSuccess: () => {
      refrescar()
      notificar.exito('Borrador eliminado', 'Se borró junto con sus documentos.')
    },
    onError: notificar.alFallar('No se pudo eliminar el borrador'),
  })

  const publicar = useMutation({
    mutationFn: comunicadosService.publicar,
    onSuccess: ({ comunicado, mensaje }) => {
      refrescar()
      notificar.exito(comunicado.vigencia === 'programado' ? 'Publicación programada' : 'Publicado en el Portal', mensaje)
    },
    onError: notificar.alFallar('No se pudo publicar en el Portal'),
  })

  const retirar = useMutation({
    mutationFn: ({ id, motivo }: { id: number; motivo: string }) => comunicadosService.retirar(id, motivo),
    onSuccess: () => {
      refrescar()
      notificar.exito('Publicación retirada', 'Ya no se ve en el Portal. Queda en el historial con su motivo.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo retirar la publicación'),
  })

  const duplicar = useMutation({
    mutationFn: comunicadosService.duplicar,
    onSuccess: () => {
      refrescar()
      notificar.exito('Copia creada', 'Quedó como borrador, sin fechas ni fijado: corríjala y publíquela.')
    },
    onError: notificar.alFallar('No se pudo duplicar la publicación'),
  })

  const actualizarPublicacion = useMutation({
    mutationFn: ({ id, cambios }: { id: number; cambios: { fijado?: boolean; publicado_hasta?: string | null } }) =>
      comunicadosService.actualizarPublicacion(id, cambios),
    onSuccess: () => {
      refrescar()
      notificar.exito('Publicación actualizada', 'El Portal ya muestra el cambio.')
    },
    onError: notificar.alFallarSalvoCampos('No se pudo cambiar la publicación'),
  })

  return { crear, actualizar, eliminar, publicar, retirar, duplicar, actualizarPublicacion }
}
