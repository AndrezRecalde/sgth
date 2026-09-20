'use client'

import { Button, Group } from '@mantine/core'
import classes from './ModalFooter.module.css'

interface Props {
  onCancel: () => void
  cancelLabel?: string
  submitLabel?: string
  /** Pone el botón principal en carga y bloquea los dos. */
  submitting?: boolean
  submitDisabled?: boolean
  /** `true` para anular, dar de baja o eliminar: el botón principal va en rojo. */
  destructiva?: boolean
  /**
   * Sin `onSubmit`, el botón principal es `type="submit"` y envía el `<form>`
   * que lo contiene (o el que nombra `form`). Con él, es un botón corriente:
   * para modales sin formulario o para el «Siguiente» de un asistente.
   */
  onSubmit?: () => void
  /** `id` del formulario cuando este vive en un componente hijo. */
  form?: string
  /**
   * Solo el botón de cancelar: modales de consulta, o cuando el registro ya no
   * admite la acción. Con él, `cancelLabel` suele ser «Cerrar».
   */
  sinPrincipal?: boolean
  /** Lo que va a la izquierda del pie: el «Atrás» de un asistente, un contador. */
  leftSection?: React.ReactNode
}

/**
 * El pie de todo modal de acción: Cancelar a la izquierda del principal,
 * siempre en ese orden, con las mismas variantes y el mismo color.
 *
 * Antes cada modal lo escribía a mano y el mismo gesto —guardar— salía en
 * verde relleno, verde tenue, azul, naranja o turquesa, con o sin icono, según
 * quién hubiera hecho la pantalla. El color del principal lo pone el tema; solo
 * cambia a rojo si la acción destruye algo.
 *
 * El principal va relleno: es el envío de un formulario, el caso para el que la
 * regla 06 reserva `filled`. En verde tenue apenas se distinguía de Cancelar.
 *
 * Es pegajoso: en un modal largo se queda al fondo de la ventana en vez de
 * esconderse debajo del último campo.
 */
export function ModalFooter({
  onCancel,
  cancelLabel = 'Cancelar',
  submitLabel = 'Guardar',
  submitting = false,
  submitDisabled = false,
  destructiva = false,
  onSubmit,
  form,
  sinPrincipal = false,
  leftSection,
}: Props) {
  return (
    <Group justify="space-between" gap="sm" className={classes.pie}>
      <Group gap="sm">{leftSection}</Group>

      <Group gap="sm" justify="flex-end">
        <Button variant="default" onClick={onCancel} disabled={submitting}>
          {cancelLabel}
        </Button>
        {!sinPrincipal && (
          <Button
            // Dos elementos distintos, no uno que cambia de `type`. En un
            // asistente, el clic en «Siguiente» avanza al último paso y React
            // volvía `submit` ese mismo botón antes de que el navegador
            // terminara de procesar el clic: el formulario se enviaba sin
            // haber visto el último paso.
            key={onSubmit ? 'avanzar' : 'enviar'}
            type={onSubmit ? 'button' : 'submit'}
            form={form}
            onClick={onSubmit}
            variant="filled"
            color={destructiva ? 'red' : undefined}
            loading={submitting}
            disabled={submitDisabled}
          >
            {submitLabel}
          </Button>
        )}
      </Group>
    </Group>
  )
}
