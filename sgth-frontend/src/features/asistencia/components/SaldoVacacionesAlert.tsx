'use client'

import { Alert, Badge, Group, Text } from '@mantine/core'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { usePeriodosVacaciones } from '../hooks/usePeriodosVacaciones'
import { MOTIVOS_QUE_DESCUENTAN } from './vacaciones.constants'

interface Props {
  servidorId: number
  motivo:     string
}

/** El saldo de vacaciones del servidor elegido, y si el motivo lo descuenta. */
export function SaldoVacacionesAlert({ servidorId, motivo }: Props) {
  const { data } = usePeriodosVacaciones(servidorId)
  const saldo = data?.saldo_total ?? 0
  const alertaLimite = data?.alerta_limite ?? false
  const descuenta = MOTIVOS_QUE_DESCUENTAN.some(m => m === motivo)

  return (
    <Alert
      icon={alertaLimite ? <IconAlertTriangle size={16} /> : <IconInfoCircle size={16} />}
      color={alertaLimite ? 'orange' : 'blue'}
      variant="light"
    >
      <Group gap="sm">
        <Text size="sm">Saldo disponible de vacaciones:</Text>
        <Badge color={alertaLimite ? 'orange' : 'emerald'} size="lg">
          {Number(saldo).toFixed(1)} días
        </Badge>
        {alertaLimite && (
          <Text size="xs" c="orange">
            Se acerca al límite máximo de acumulación
          </Text>
        )}
      </Group>
      {!descuenta && motivo && (
        <Text size="xs" c="dimmed" mt={4}>
          Este motivo no descuenta del saldo de vacaciones.
        </Text>
      )}
    </Alert>
  )
}
