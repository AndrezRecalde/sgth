import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { examenAdminService } from '../services/examenAdminService'
import type { GuardarExamenCatalogoData, GuardarPerfilExamenData } from '../services/examenAdminService'

export function useCatalogoExamenesAdmin() {
  return useQuery({
    queryKey: ['examenes', 'admin', 'catalogo'],
    queryFn:  examenAdminService.catalogo,
  })
}

export function usePerfilesExamenAdmin() {
  return useQuery({
    queryKey: ['examenes', 'admin', 'perfiles'],
    queryFn:  examenAdminService.perfiles,
  })
}

/**
 * Invalida toda la raíz `examenes`: el modal de la solicitud guarda el
 * catálogo y los perfiles diez y cinco minutos, y un cambio aquí debe verse
 * allí al volver a abrirlo.
 */
export function useGuardarExamenCatalogo() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: Partial<GuardarExamenCatalogoData> }) =>
      examenAdminService.guardarExamen(id, data),
    onSuccess: (respuesta) => {
      // Si salió de perfiles ocupacionales o de riesgos, el aviso dura más: hay que leerlo.
      const quitado = respuesta.meta?.quitado_de
      const salio = !!quitado && (quitado.perfiles.length > 0 || quitado.riesgos.length > 0)
      notificar.exito('Catálogo actualizado', respuesta.mensaje, salio ? { autoClose: 12000 } : undefined)
      qc.invalidateQueries({ queryKey: ['examenes'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el examen'),
  })
}

export function useGuardarPerfilExamen() {
  const qc = useQueryClient()

  return useMutation({
    mutationFn: ({ id, data }: { id?: number; data: Partial<GuardarPerfilExamenData> }) =>
      examenAdminService.guardarPerfil(id, data),
    onSuccess: (perfil) => {
      notificar.exito('Perfil guardado', perfil?.nombre)
      qc.invalidateQueries({ queryKey: ['examenes'] })
    },
    onError: notificar.alFallarSalvoCampos('No se pudo guardar el perfil'),
  })
}
