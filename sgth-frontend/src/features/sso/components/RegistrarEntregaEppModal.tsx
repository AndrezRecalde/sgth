'use client'

import { useState } from 'react'
import { SegmentedControl, Stack } from '@mantine/core'
import { SgthModal } from '@/components/ui'
import { EntregaEppIndividualForm } from './EntregaEppIndividualForm'
import { EntregaKitEppForm } from './EntregaKitEppForm'

interface Props {
  opened: boolean
  onClose: () => void
}

type Modo = 'individual' | 'kit'

/**
 * Registrar un movimiento de EPP, de una de dos formas.
 *
 * El modal era de 351 líneas con los dos formularios dentro: el individual con
 * React Hook Form y el del kit a mano con cinco `useState`, compartiendo un
 * `handleClose` que limpiaba los dos. Cada formulario vive ahora en su archivo
 * y se monta solo cuando se elige su modo, así que cambiar de modo deja el otro
 * en blanco sin tener que acordarse de limpiarlo.
 */
export function RegistrarEntregaEppModal({ opened, onClose }: Props) {
  const [modo, setModo] = useState<Modo>('individual')

  const cerrar = () => {
    setModo('individual')
    onClose()
  }

  return (
    <SgthModal
      opened={opened}
      onClose={cerrar}
      title="Registrar movimiento de EPP"
      size="md"
    >
      <Stack gap="sm">
        <SegmentedControl
          value={modo}
          onChange={(v) => setModo(v as Modo)}
          data={[
            { label: 'Movimiento individual', value: 'individual' },
            { label: 'Entregar kit completo', value: 'kit' },
          ]}
          fullWidth
        />

        {modo === 'individual'
          ? <EntregaEppIndividualForm onListo={cerrar} onCancelar={cerrar} />
          : <EntregaKitEppForm onListo={cerrar} onCancelar={cerrar} />}
      </Stack>
    </SgthModal>
  )
}
