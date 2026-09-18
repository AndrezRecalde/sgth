'use client'

import { Alert, Divider, Group, Paper, Stack, Text } from '@mantine/core'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { dolares } from '../utils/monto'
import type { CalculoViatico } from '@/types/api'

/*
| La cuenta del viático, tal como la resuelve el backend.
|
| Antes la rehacían cuatro componentes por su cuenta —la pantalla de
| liquidación, el modal de comprobantes, la ficha y su resumen—, cada uno con
| una fórmula distinta: ninguno mostraba el 30 % que se reconoce sin factura y
| todos hablaban de «devolver» aunque la institución tuviera que pagar.
*/

const monto = dolares

interface Props {
  calculo: CalculoViatico
  /** Sin esto no se explica lo que falta por justificar: la liquidación ya está cerrada. */
  enCurso?: boolean
}

function Fila({
  label,
  valor,
  color,
  fuerte,
}: {
  label: string
  valor: string
  color?: string
  fuerte?: boolean
}) {
  return (
    <Group justify="space-between" gap="sm" wrap="nowrap">
      <Text size="xs" c={fuerte ? undefined : 'dimmed'} fw={fuerte ? 600 : undefined}>
        {label}
      </Text>
      <Text size="xs" fw={fuerte ? 700 : 600} c={color}>
        {valor}
      </Text>
    </Group>
  )
}

export function CalculoViaticoCard({ calculo: c, enCurso = false }: Props) {
  const porJustificar = Math.max(c.tope_justificable - c.total_comprobantes, 0)
  const aFavor = c.saldo >= 0

  return (
    <Paper withBorder radius="md" p="md" bg="var(--sgth-surface-sunken)">
      <Stack gap={6}>
        <Fila
          label={`Viático por ${c.noches} ${c.noches === 1 ? 'noche' : 'noches'}`}
          valor={monto(c.derecho)}
        />
        <Fila label="70% a justificar con comprobantes" valor={monto(c.tope_justificable)} />

        <Divider my={2} />

        <Fila label="Comprobantes presentados" valor={monto(c.total_comprobantes)} />
        <Fila
          label="Justificado (hasta el 70%)"
          valor={monto(c.justificado)}
          color={porJustificar > 0 ? SEMANTIC_COLOR.warning : SEMANTIC_COLOR.success}
        />
        {c.excedente > 0 && (
          <Fila
            label="Excedente, a cargo del servidor"
            valor={monto(c.excedente)}
            color={SEMANTIC_COLOR.warning}
          />
        )}
        <Fila label="30% reconocido sin comprobante" valor={monto(c.reconocido_sin_comprobante)} />
        <Fila label="Total reconocido" valor={monto(c.reconocido)} fuerte />

        {c.anticipo > 0 && <Fila label="Anticipo entregado" valor={monto(c.anticipo)} />}

        <Divider my={2} />

        <Fila
          label={
            c.saldo === 0
              ? 'Sin saldo pendiente'
              : aFavor
                ? 'A pagar al servidor'
                : 'A devolver a la institución'
          }
          valor={monto(Math.abs(c.saldo))}
          color={aFavor ? SEMANTIC_COLOR.success : SEMANTIC_COLOR.danger}
          fuerte
        />

        {enCurso && porJustificar > 0 && (
          <Alert color={SEMANTIC_COLOR.warning} variant="light" p="xs">
            <Text size="xs">
              Faltan <strong>{monto(porJustificar)}</strong> en comprobantes para cubrir el 70%.
              Sin ellos {c.saldo < 0
                ? <>deberá devolver <strong>{monto(Math.abs(c.saldo))}</strong>.</>
                : <>se le reconocerán <strong>{monto(c.reconocido)}</strong>.</>}
            </Text>
          </Alert>
        )}
      </Stack>
    </Paper>
  )
}
