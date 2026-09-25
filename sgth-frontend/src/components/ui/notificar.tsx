import { notifications } from '@mantine/notifications'
import { IconAlertTriangle, IconCheck, IconX } from '@tabler/icons-react'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { getApiErrorMessage } from '@/types/api'
import { erroresDeCampo } from '@/lib/erroresDeCampo'

/*
| El resultado de una acción del usuario, siempre con el mismo color e icono
| para el mismo desenlace (regla 08):
|
|   notificar.exito('Permiso registrado', 'PER-2026-00012 quedó pendiente')
|   notificar.error('No se pudo anular el viático', getApiErrorMessage(error))
|   notificar.aviso('Guardado con reparos', 'Faltan dos comprobantes')
|
| Antes eran 310 llamadas a `notifications.show` que repetían color e icono a
| mano: el mismo «se guardó» salía en verde, naranja o azul según el módulo.
|
| El `onError` de una mutación, con el mensaje que devolvió el API:
|
|   useMutation({ …, onError: notificar.alFallar('No se pudo crear el cargo') })
|
| El título dice qué falló. «Error» no dice nada: con 105 mutaciones que lo
| usaban, la misma notificación roja servía para un cargo, un turno o una
| nómina, y el usuario no sabía cuál de sus acciones no se había guardado.
|
| Para una espera que termina en éxito o error —exportar un PDF—:
|
|   const aviso = notificar.proceso('Exportando PDF', 'Generando el archivo…')
|   try { …; aviso.exito('PDF descargado', '…') }
|   catch { aviso.error('No se pudo exportar el PDF', '…') }
*/

type Desenlace = 'exito' | 'error' | 'aviso'

const ESTILO: Record<Desenlace, { color: string; icon: React.ReactNode }> = {
  exito: { color: SEMANTIC_COLOR.success, icon: <IconCheck size={16} /> },
  error: { color: SEMANTIC_COLOR.danger, icon: <IconX size={16} /> },
  aviso: { color: SEMANTIC_COLOR.warning, icon: <IconAlertTriangle size={16} /> },
}

interface Opciones {
  /**
   * Milisegundos en pantalla, o `false` para que se quede hasta cerrarla.
   * Solo para mensajes largos o que no se pueden perder: una alerta de
   * alergia al emitir una receta no se cierra sola.
   */
  autoClose?: number | false
}

let secuencia = 0

function mostrar(desenlace: Desenlace, title: string, message?: React.ReactNode, opciones: Opciones = {}) {
  notifications.show({ title, message, ...ESTILO[desenlace], ...opciones })
}

export const notificar = {
  exito: (title: string, message?: React.ReactNode, opciones?: Opciones) => mostrar('exito', title, message, opciones),
  error: (title: string, message?: React.ReactNode, opciones?: Opciones) => mostrar('error', title, message, opciones),
  aviso: (title: string, message?: React.ReactNode, opciones?: Opciones) => mostrar('aviso', title, message, opciones),

  /** Manejador de error para `onError`: el título dice qué falló; el mensaje, lo que respondió el API. */
  alFallar: (title: string) => (error: unknown) => mostrar('error', title, getApiErrorMessage(error)),

  /**
   * Igual, pero se calla cuando el 422 trae errores por campo, porque esos ya
   * los pinta el formulario debajo de cada casilla (regla 07). Notificarlos
   * además repite el mismo mensaje en dos sitios y obliga a leerlo dos veces.
   * Lo demás —un 500, un 403, un 422 con un solo mensaje— sí se notifica: sin
   * eso la acción fallaría en silencio.
   */
  alFallarSalvoCampos: (title: string) => (error: unknown) => {
    if (!erroresDeCampo(error)) mostrar('error', title, getApiErrorMessage(error))
  },

  /** Una espera visible que después se convierte en su resultado. */
  proceso(title: string, message?: React.ReactNode) {
    const id = `sgth-proceso-${++secuencia}`
    notifications.show({
      id,
      title,
      message,
      loading: true,
      autoClose: false,
      withCloseButton: false,
    })

    const terminar = (desenlace: Desenlace) => (titulo: string, mensaje?: React.ReactNode) =>
      notifications.update({
        id,
        title: titulo,
        message: mensaje,
        loading: false,
        autoClose: 3000,
        withCloseButton: true,
        ...ESTILO[desenlace],
      })

    return { exito: terminar('exito'), error: terminar('error'), aviso: terminar('aviso') }
  },
}
