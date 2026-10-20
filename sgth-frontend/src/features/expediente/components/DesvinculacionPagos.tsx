'use client'

import { useState } from 'react'
import { Button, Group, NumberInput, Select, Stack, Text, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconCash, IconCheck, IconReceiptRefund } from '@tabler/icons-react'
import { SgthModal, confirmar } from '@/components/ui'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { dinero } from '../utils/dinero'
import { usePagosDeLaSalida } from '../hooks/useDesvinculaciones'
import type { ConceptoIndemnizacion, PasoDeLaDesvinculacion } from '../services/desvinculacionService'

/**
 * Lo que trae la salida, en su paso de la desvinculación (fase 7.4b): los días
 * de vacaciones no gozadas y su confirmación, el registro de la indemnización
 * que pagó Financiero y la devolución que levanta la prohibición de reingreso.
 * Sin montos calculados (2026-10-20): los valores los calcula Financiero.
 */
export function PagoDeLaSalida({
  desvinculacionId, paso, esDirector, abierta,
}: { desvinculacionId: number; paso: PasoDeLaDesvinculacion; esDirector: boolean; abierta: boolean }) {
  if (paso.paso === 'vacaciones' && paso.liquidacion) {
    return <Vacaciones desvinculacionId={desvinculacionId} paso={paso} puede={esDirector && abierta} />
  }
  if (paso.paso === 'indemnizacion' && paso.indemnizacion && paso.estado === 'pendiente') {
    return <Indemnizacion desvinculacionId={desvinculacionId} paso={paso} puede={esDirector && abierta} />
  }
  if (paso.paso === 'reingreso' && paso.reingreso?.prohibido) {
    return <Devolucion desvinculacionId={desvinculacionId} />
  }

  return null
}

function numero(v: number): string {
  return v.toLocaleString('es-EC', { maximumFractionDigits: 2 })
}

function Vacaciones({ desvinculacionId, paso, puede }: { desvinculacionId: number; paso: PasoDeLaDesvinculacion; puede: boolean }) {
  const { confirmar: confirmarLiquidacion } = usePagosDeLaSalida(desvinculacionId)
  const l = paso.liquidacion!
  const p = l.proporcional

  return (
    <Stack gap={2}>
      {l.periodos.map((x) => (
        <Text key={x.anio} size="xs">Saldo de {x.anio}: {numero(x.dias)} días</Text>
      ))}
      <Text size="xs">
        De {p.anio}: {numero(p.generados)} días × {p.trabajados}/{p.dias_del_anio} trabajados = {numero(p.ganados)} ganados,
        menos {numero(p.utilizados)} gozados: {numero(p.dias)} días
      </Text>
      <Text size="xs">
        Total {numero(l.total)} días{l.tope !== null && l.total > l.tope ? `, con el tope de ${numero(l.tope)}` : ''}:
        {' '}<b>{numero(l.dias)} días</b>. Su valor lo calcula Financiero.
      </Text>
      {!paso.confirmada && paso.estado === 'pendiente' && (
        <Group mt={4}>
          <Button
            size="compact-xs"
            variant="light"
            leftSection={<IconCheck size={14} />}
            disabled={!puede}
            title={puede ? undefined : 'La confirma el Director de Talento Humano'}
            loading={confirmarLiquidacion.isPending}
            onClick={() => confirmar({
              title: 'Confirmar los días',
              message: `Se confirman ${numero(l.dias)} días: quedan congelados y llegan a Financiero en las novedades del mes de la salida.`,
              confirmLabel: 'Confirmar',
              onConfirm: () => confirmarLiquidacion.mutate(),
            })}
          >
            Confirmar los días
          </Button>
        </Group>
      )}
    </Stack>
  )
}

function Indemnizacion({ desvinculacionId, paso, puede }: { desvinculacionId: number; paso: PasoDeLaDesvinculacion; puede: boolean }) {
  const { indemnizacion: registrar } = usePagosDeLaSalida(desvinculacionId)
  const i = paso.indemnizacion!
  const [concepto, setConcepto] = useState<ConceptoIndemnizacion | null>(null)
  const [monto, setMonto] = useState<number | ''>('')
  const [resolucion, setResolucion] = useState('')

  if (!puede) {
    return <Text size="xs" c="dimmed">La registra el Director de Talento Humano.</Text>
  }

  return (
    <Stack gap={6} mt={4}>
      <Text size="xs" c="dimmed">
        Lo que pagó Financiero, con la resolución que la dispuso: el valor y su tope los calcula él.
      </Text>
      <Group grow align="flex-end" gap="xs">
        <Select
          size="xs"
          label="Concepto"
          data={Object.entries(i.conceptos).map(([value, label]) => ({ value, label }))}
          value={concepto}
          onChange={(v) => setConcepto(v as ConceptoIndemnizacion | null)}
        />
        <NumberInput size="xs" label="Monto pagado" min={0} decimalScale={2} prefix="$ " hideControls value={monto} onChange={(v) => setMonto(typeof v === 'number' ? v : '')} />
        <TextInput size="xs" label="Resolución" value={resolucion} onChange={(e) => setResolucion(e.currentTarget.value)} />
      </Group>
      <Group>
        <Button
          size="compact-xs"
          variant="light"
          leftSection={<IconCash size={14} />}
          disabled={!concepto || !monto || resolucion.trim().length < 3}
          loading={registrar.isPending}
          onClick={() => confirmar({
            title: 'Registrar la indemnización',
            message: `Se registran ${dinero(Number(monto))} pagados por Financiero: quien la cobra no reingresa al sector público mientras no la devuelva (Mandato Constituyente 2, art. 8).`,
            confirmLabel: 'Registrar',
            onConfirm: () => concepto && registrar.mutate({ concepto, monto: Number(monto), resolucion: resolucion.trim() }),
          })}
        >
          Registrar la indemnización
        </Button>
      </Group>
    </Stack>
  )
}

function Devolucion({ desvinculacionId }: { desvinculacionId: number }) {
  const { devolucion } = usePagosDeLaSalida(desvinculacionId)
  const [abierto, setAbierto] = useState(false)
  const [fecha, setFecha] = useState(hoyIso())
  const [documento, setDocumento] = useState('')

  return (
    <>
      <Group mt={4}>
        <Button size="compact-xs" variant="light" leftSection={<IconReceiptRefund size={14} />} onClick={() => setAbierto(true)}>
          Registrar la devolución de la indemnización
        </Button>
      </Group>
      <SgthModal opened={abierto} onClose={() => setAbierto(false)} title="Devolución de la indemnización" size="sm">
        <Stack gap="sm">
          <Text size="sm">Con la devolución registrada, puede reingresar al sector público.</Text>
          <DatePickerInput
            label="Fecha de la devolución"
            withAsterisk
            valueFormat="DD/MM/YYYY"
            maxDate={hoyIso()}
            value={toDateValue(fecha)}
            onChange={(d) => setFecha(fromDateValueOrNull(d) ?? hoyIso())}
          />
          <TextInput
            label="Documento"
            description="El depósito o la resolución que la respalda."
            withAsterisk
            value={documento}
            onChange={(e) => setDocumento(e.currentTarget.value)}
          />
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setAbierto(false)}>Cancelar</Button>
            <Button
              disabled={documento.trim().length < 3}
              loading={devolucion.isPending}
              onClick={() => devolucion.mutate({ fecha, documento: documento.trim() }, { onSuccess: () => setAbierto(false) })}
            >
              Registrar
            </Button>
          </Group>
        </Stack>
      </SgthModal>
    </>
  )
}
