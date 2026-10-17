import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { abrirOGuardarArchivo, guardarArchivo } from '@/lib/archivo'
import { miPerfilService } from '../services/miPerfilService'

const CLAVE_FORMACION = ['mi-formacion-academica']

/** La formación académica propia, con el estado de lo que registró él mismo. */
export function useMiFormacion() {
  return useQuery({
    queryKey: CLAVE_FORMACION,
    queryFn:  () => miPerfilService.formacion(),
  })
}

/** Registrar, retirar y ver el documento de un título o curso propio. */
export function useMiFormacionMutations() {
  const qc = useQueryClient()
  const invalidar = () => qc.invalidateQueries({ queryKey: CLAVE_FORMACION })

  const registrar = useMutation({
    mutationFn: miPerfilService.registrarFormacion,
    onSuccess: (respuesta) => {
      notificar.exito('Título registrado', respuesta.mensaje)
      invalidar()
    },
    onError: notificar.alFallarSalvoCampos('No se pudo registrar el título'),
  })

  const retirar = useMutation({
    mutationFn: (id: number) => miPerfilService.retirarFormacion(id),
    onSuccess: () => {
      notificar.exito('Registro retirado', 'Ya no espera la revisión de Talento Humano.')
      invalidar()
    },
    onError: notificar.alFallar('No se pudo retirar el registro'),
  })

  const verDocumento = useMutation({
    mutationFn: async (id: number) =>
      abrirOGuardarArchivo(await miPerfilService.documentoFormacion(id), 'titulo.pdf'),
    onError: notificar.alFallar('No se pudo abrir el documento'),
  })

  return { registrar, retirar, verDocumento }
}

/** La hoja de vida en PDF, armada en el backend con los datos del expediente. */
export function useDescargarHojaDeVida() {
  return useMutation({
    mutationFn: async () => guardarArchivo(await miPerfilService.hojaDeVida(), 'hoja-de-vida.pdf'),
    onError: notificar.alFallar('No se pudo descargar su hoja de vida'),
  })
}
