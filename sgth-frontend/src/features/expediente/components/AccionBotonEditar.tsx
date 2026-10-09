'use client'

import { Button, Text } from '@mantine/core'
import { IconPencil } from '@tabler/icons-react'
import type { MovimientoPersonal } from '@/types/api'

/**
 * Único punto de edición del cajón. Se ancla al pie de la tarjeta de la derecha;
 * cuando esa tarjeta no existe —cesación, sanción— baja a la única que hay, para
 * que nunca quede una acción en borrador sin forma de corregirla.
 *
 * La subrogación y el encargo se excluyen: su acción es el reflejo de una fila
 * en `subrogaciones`, y el modal solo escribiría el movimiento. Cambiar aquí el
 * puesto dejaría a los dos registros diciendo cosas distintas —uno para el
 * documento, otro para quién puede firmar—. Se corrigen cancelándolos y
 * volviéndolos a registrar. Qué se corrige con el formulario lo responde el
 * backend en `editable_en_formulario`.
 */
export function AccionBotonEditar({
  m,
  onEditar,
}: {
  m: MovimientoPersonal
  onEditar: () => void
}) {
  if (!m.editable_en_formulario) {
    const reemplazo = m.clase === 'subrogacion' || m.clase === 'encargo'

    return (
      <Text size="xs" c="dimmed" mt="xs">
        {!reemplazo
          ? 'Este registro no se corrige con el formulario de acciones de personal.'
          : m.clase === 'encargo'
            ? 'Para corregirlo, cancele el encargo y regístrelo de nuevo.'
            : 'Para corregirla, cancele la subrogación y regístrela de nuevo.'}
      </Text>
    )
  }

  const enBorrador = m.estado === 'borrador'

  return (
    <Button
      size="xs"
      variant="light"
      mt="xs"
      fullWidth
      leftSection={<IconPencil size={14} />}
      disabled={!enBorrador}
      onClick={onEditar}
    >
      {enBorrador ? 'Editar la acción' : 'Solo se edita en borrador'}
    </Button>
  )
}
