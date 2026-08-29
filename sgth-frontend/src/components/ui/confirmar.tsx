import { Text } from '@mantine/core'
import { modals } from '@mantine/modals'

interface Opciones {
  title: string
  /** Qué va a pasar exactamente. Nombrar el registro afectado. */
  message: React.ReactNode
  confirmLabel?: string
  cancelLabel?: string
  /** `true` para acciones destructivas: el botón de confirmar se pone en rojo. */
  destructiva?: boolean
  onConfirm: () => void
}

/**
 * Diálogo de confirmación para acciones irreversibles.
 *
 * Se usa SIEMPRE en lugar del `confirm()` del navegador o de un modal escrito
 * a mano, para que borrar un servidor y anular un viático se pregunten igual.
 *
 *   confirmar({
 *     title: 'Eliminar extensión',
 *     message: <>Se eliminará la extensión <b>{ext.numero}</b>. No se puede deshacer.</>,
 *     destructiva: true,
 *     onConfirm: () => eliminar.mutate(ext.id),
 *   })
 *
 * Requiere `ModalsProvider`, que ya monta `app/Providers.tsx`.
 */
export function confirmar({
  title,
  message,
  confirmLabel = 'Confirmar',
  cancelLabel = 'Cancelar',
  destructiva = false,
  onConfirm,
}: Opciones) {
  modals.openConfirmModal({
    title,
    centered: true,
    radius: 'xl',
    children: (
      <Text size="sm" c="dimmed">
        {message}
      </Text>
    ),
    labels: { confirm: confirmLabel, cancel: cancelLabel },
    confirmProps: {
      color: destructiva ? 'red' : undefined,
      variant: 'light',
    },
    cancelProps: { variant: 'default' },
    onConfirm,
  })
}
