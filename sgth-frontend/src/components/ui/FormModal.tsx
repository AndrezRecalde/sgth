'use client'

import { Button, Group, Modal, ScrollArea } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

interface Props {
  opened: boolean
  onClose: () => void
  title: string
  children: React.ReactNode
  /** Se dispara al enviar el formulario. Normalmente `handleSubmit(onSubmit)`. */
  onSubmit: (e: React.FormEvent<HTMLFormElement>) => void
  submitLabel?: string
  cancelLabel?: string
  /** Deshabilita el envío y muestra el botón en carga. */
  submitting?: boolean
  size?: string | number
}

/**
 * Modal de formulario: la envoltura estándar de las ~80 pantallas de captura.
 *
 * Resuelve de una vez lo que cada modal repetía por su cuenta:
 *  - `fullScreen` en móvil, porque un modal centrado con quince campos en un
 *    teléfono es una ventana diminuta con scroll dentro de scroll;
 *  - el `<form>` envolviendo al contenido, para que Enter envíe;
 *  - el pie con Cancelar/Guardar, siempre en el mismo orden y variantes;
 *  - el cuerpo con scroll propio, para que el pie no se pierda al hacer scroll.
 */
export function FormModal({
  opened,
  onClose,
  title,
  children,
  onSubmit,
  submitLabel = 'Guardar',
  cancelLabel = 'Cancelar',
  submitting = false,
  size = 'lg',
}: Props) {
  const { isMobile } = useMobileBreakpoint()

  return (
    <Modal
      opened={opened}
      onClose={onClose}
      title={title}
      size={size}
      fullScreen={isMobile}
      scrollAreaComponent={ScrollArea.Autosize}
      closeOnClickOutside={!submitting}
      closeOnEscape={!submitting}
    >
      <form onSubmit={onSubmit} noValidate>
        {children}

        <Group justify="flex-end" gap="sm" mt="xl">
          <Button variant="default" onClick={onClose} disabled={submitting}>
            {cancelLabel}
          </Button>
          <Button type="submit" variant="light" loading={submitting}>
            {submitLabel}
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
