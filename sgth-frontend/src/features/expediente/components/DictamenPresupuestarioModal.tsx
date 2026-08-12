'use client'

import { useState } from 'react'
import { Alert, Button, Group, Modal, Stack, Text, TextInput } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMovimientoMutations } from '../hooks/useMovimientoMutations'
import type { MovimientoPersonal } from '@/types/api'

/**
 * Referencia del dictamen presupuestario, pedida en el acto de suscribir.
 *
 * El Art. 105 de la LOSEP no deja comprometer presupuesto sin certificación
 * previa, y el backend lo hace valer: sin esta referencia la transición a
 * Suscrita se rechaza. Antes no había dónde escribirla, así que ninguna acción
 * con efecto económico —subrogación, incremento de remuneración— podía
 * suscribirse desde la aplicación.
 *
 * Es un dato de respaldo, no un cálculo: quien certifica es la Dirección
 * Financiera y lo que se guarda aquí es el número de su oficio o memorando,
 * para que el documento diga contra qué certificación se autorizó.
 */
interface Props {
  opened: boolean
  onClose: () => void
  movimiento: MovimientoPersonal | null
}

function dinero(v?: string | number | null): string | null {
  return v != null ? `$ ${Number(v).toFixed(2)}` : null
}

export function DictamenPresupuestarioModal({ opened, onClose, movimiento }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { transicionar } = useMovimientoMutations()

  const [referencia, setReferencia] = useState('')
  const [error, setError] = useState<string | null>(null)

  const cerrar = () => {
    setReferencia('')
    setError(null)
    onClose()
  }

  const suscribir = () => {
    if (!movimiento) return

    if (referencia.trim().length < 3) {
      setError('Escriba la referencia del dictamen — es el respaldo del compromiso.')
      return
    }

    transicionar.mutate(
      {
        id: Number(movimiento.id),
        estado: 'suscrita',
        dictamen_presupuestario_ref: referencia.trim(),
      },
      { onSuccess: cerrar },
    )
  }

  // El monto que se compromete: la diferencia en una subrogación, la
  // remuneración propuesta en el resto. Se muestra para que quien suscribe vea
  // contra qué cifra está exigiendo la certificación.
  const origen    = movimiento?.remuneracion_origen
  const propuesta = movimiento?.remuneracion_propuesta
  const esSubrogacion = movimiento?.tipo_movimiento === 'subrogacion'

  const comprometido = esSubrogacion && origen != null && propuesta != null
    ? Number(propuesta) - Number(origen)
    : propuesta != null ? Number(propuesta) : null

  return (
    <Modal
      opened={opened}
      onClose={cerrar}
      title="Suscribir con dictamen presupuestario"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <Alert color="blue" variant="light" icon={<IconInfoCircle size={16} />}>
          Esta acción compromete presupuesto, así que no puede suscribirse sin la
          certificación previa de la Dirección Financiera (Art. 105 LOSEP).
        </Alert>

        {comprometido != null && comprometido > 0 && (
          <div>
            <Text size="xs" fw={600} c="dimmed" tt="uppercase">
              {esSubrogacion ? 'Diferencia mensual a pagar' : 'Remuneración mensual'}
            </Text>
            <Text size="lg" fw={700} c="emerald">{dinero(comprometido)}</Text>
          </div>
        )}

        <TextInput
          label="N.º de dictamen o certificación presupuestaria"
          placeholder="Ej. DF-CP-2026-0142"
          description="Oficio o memorando con el que la Dirección Financiera certificó la disponibilidad."
          {...contained}
          value={referencia}
          onChange={(e) => { setReferencia(e.currentTarget.value); setError(null) }}
          error={error}
          data-autofocus
        />

        <Group justify="flex-end">
          <Button variant="subtle" color="gray" onClick={cerrar}>Cancelar</Button>
          <Button
            color="emerald"
            loading={transicionar.isPending}
            onClick={suscribir}
          >
            Suscribir
          </Button>
        </Group>
      </Stack>
    </Modal>
  )
}
