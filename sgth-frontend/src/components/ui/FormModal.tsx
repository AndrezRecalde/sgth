'use client'

import { SgthModal } from './SgthModal'
import { ModalFooter } from './ModalFooter'

interface Props {
  opened: boolean
  onClose: () => void
  title: React.ReactNode
  children: React.ReactNode
  /** Se dispara al enviar el formulario. Normalmente `handleSubmit(onSubmit)`. */
  onSubmit: (e: React.FormEvent<HTMLFormElement>) => void
  submitLabel?: string
  cancelLabel?: string
  /** Deshabilita el envío y muestra el botón en carga. */
  submitting?: boolean
  submitDisabled?: boolean
  /** `true` si guardar destruye algo: el botón principal va en rojo. */
  destructiva?: boolean
  /** No cerrar al hacer clic fuera: formularios largos que duele perder. */
  closeOnClickOutside?: boolean
  size?: string | number
}

/**
 * Modal de formulario: el caso más común, capturar y guardar.
 *
 * Es `SgthModal` más lo que cada formulario repetía: el `<form>` envolviendo
 * el contenido para que Enter envíe, y `ModalFooter` con Cancelar y Guardar.
 * Mientras se envía no se cierra ni con Escape ni con clic fuera.
 *
 * Si el pie necesita algo más —un «Atrás», un botón fuera del formulario—, se
 * arma con `SgthModal` y `ModalFooter` por separado.
 */
export function FormModal({
  opened,
  onClose,
  title,
  children,
  onSubmit,
  submitLabel,
  cancelLabel,
  submitting = false,
  submitDisabled,
  destructiva,
  closeOnClickOutside = true,
  size = 'lg',
}: Props) {
  return (
    <SgthModal
      opened={opened}
      onClose={onClose}
      title={title}
      size={size}
      closeOnClickOutside={closeOnClickOutside && !submitting}
      closeOnEscape={!submitting}
    >
      <form onSubmit={onSubmit} noValidate>
        {children}

        <ModalFooter
          onCancel={onClose}
          cancelLabel={cancelLabel}
          submitLabel={submitLabel}
          submitting={submitting}
          submitDisabled={submitDisabled}
          destructiva={destructiva}
        />
      </form>
    </SgthModal>
  )
}
