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
 * Lo que paga la salida, en su paso de la desvinculación (fase 7.4b): el
 * cálculo de las vacaciones y su confirmación, el formulario de la
 * indemnización con el tope del Mandato 2, y la devolución que levanta la
 * prohibición de reingreso. Montos de referencia: el definitivo lo establece
 * Financiero.
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
        {' '}<b>{numero(l.dias)} días × {dinero(l.remuneracion)} ÷ 30 = {dinero(l.monto)}</b>
      </Text>
      {!paso.confirmada && paso.estado === 'pendiente' && (
        <Group mt={4}>
          <Button
            size="compact-xs"
            variant="light"
            leftSection={<IconCheck size={14} />}
            disabled={!puede || l.monto === null}
            title={puede ? undefined : 'La confirma el Director de Talento Humano'}
            loading={confirmarLiquidacion.isPending}
            onClick={() => confirmar({
              title: 'Confirmar la liquidación',
              message: `Se confirman ${numero(l.dias)} días, ${dinero(l.monto)} de referencia: quedan congelados y llegan a Financiero en las novedades del mes de la salida.`,
              confirmLabel: 'Confirmar',
              onConfirm: () => confirmarLiquidacion.mutate(),
            })}
          >
            Confirmar la liquidación
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
  const [monto, setMonto] = useState<number | ''>(i.sugerido ?? '')
  const [resolucion, setResolucion] = useState('')

  if (!puede) {
    return <Text size="xs" c="dimmed">La registra el Director de Talento Humano.</Text>
  }

  return (
    <Stack gap={6} mt={4}>
      <Text size="xs" c="dimmed">
        {i.tope !== null
          ? `Tope del Mandato 2: ${dinero(i.tope)} (7 salarios básicos de ${dinero(i.salario_basico)} por ${numero(i.anios ?? 0)} años, hasta 210).`
          : 'Sin el salario básico del año de la salida no hay tope: cárguelo en «Contrato colectivo».'}
        {i.sugerido !== null ? ` Su liquidación referencial suma ${dinero(i.sugerido)}.` : ''}
      </Text>
      <Group grow align="flex-end" gap="xs">
        <Select
          size="xs"
          label="Concepto"
          data={Object.entries(i.conceptos).map(([value, label]) => ({ value, label }))}
          value={concepto}
          onChange={(v) => setConcepto(v as ConceptoIndemnizacion | null)}
        />
        <NumberInput size="xs" label="Monto" min={0} decimalScale={2} prefix="$ " hideControls value={monto} onChange={(v) => setMonto(typeof v === 'number' ? v : '')} />
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
            message: `Se registran ${dinero(Number(monto))}: llegan a Financiero en las novedades del mes de la salida, y quien la cobra no reingresa al sector público mientras no la devuelva (Mandato Constituyente 2, art. 8).`,
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
