'use client'

import { Stack, Text } from '@mantine/core'
import { DetailList, SectionCard } from '@/components/ui'
import { formatFechaMes } from '@/lib/fecha'
import type { FichaSaludOcupacional } from '../../services/femoService'
import {
  REGIONES_EXAMEN_FISICO, TIPO_ANTECEDENTE_OPTIONS, TIPO_FICHA_OPTIONS,
} from '../../services/femoOptions'

interface Props {
  ficha: FichaSaludOcupacional
}

const siNo = (v?: boolean | null) => (v === null || v === undefined ? 'Sin respuesta' : v ? 'Sí' : 'No')
const etiquetaDe = (opciones: { value: string; label: string }[], v: string) =>
  opciones.find(o => o.value === v)?.label ?? v

/** Una lista, o «Ninguno registrado» si está vacía. */
function Lista({ items }: { items: string[] }) {
  if (items.length === 0) return <Text size="sm" c="dimmed">Ninguno registrado.</Text>
  return (
    <Stack gap={4} component="ul" m={0} pl="md">
      {items.map((t, i) => <Text key={i} size="sm" component="li">{t}</Text>)}
    </Stack>
  )
}

/** La ficha FEMO en solo lectura, agrupada por las secciones del impreso. */
export function FemoResumen({ ficha }: Props) {
  const hallazgos = (ficha.examen_fisico ?? []).filter(e => !e.normal)
  const regionDe = (r: string) => REGIONES_EXAMEN_FISICO.find(x => x.value === r)?.label ?? r

  return (
    <Stack gap="md">
      <SectionCard title="A–B. Usuario y motivo de consulta">
        <DetailList columnas={3} items={[
          { label: 'Tipo de evaluación', value: etiquetaDe(TIPO_FICHA_OPTIONS, ficha.tipo_ficha) },
          // `formatFechaMes` lee la fecha como fecha local: con `new Date()`
          // se veía un día antes en Ecuador.
          { label: 'Fecha de atención', value: formatFechaMes(ficha.fecha_evaluacion) },
          { label: 'Puesto de trabajo', value: ficha.puesto_trabajo ?? 'No registrado' },
          { label: 'Observación', value: ficha.observaciones || '—', ancho: true },
        ]} />
      </SectionCard>

      <SectionCard title="C–D. Antecedentes y enfermedad actual">
        <Stack gap="sm">
          <Lista items={(ficha.antecedentes ?? []).map(a =>
            `${etiquetaDe(TIPO_ANTECEDENTE_OPTIONS, a.tipo)}: ${a.descripcion}`)} />
          <DetailList columnas={3} items={[
            { label: 'Autoriza transfusión', value: siNo(ficha.autoriza_transfusion) },
            { label: 'Tratamiento hormonal', value: siNo(ficha.tratamiento_hormonal) },
            { label: 'Enfermedad actual', value: ficha.enfermedad_actual || 'No refiere' },
          ]} />
        </Stack>
      </SectionCard>

      <SectionCard
        title="F. Examen físico regional"
        description={`${hallazgos.length} hallazgo${hallazgos.length !== 1 ? 's' : ''} con evidencia de patología`}
      >
        <Lista items={hallazgos.map(h =>
          `${regionDe(h.region)} — ${h.item}${h.observacion ? `: ${h.observacion}` : ''}`)} />
      </SectionCard>

      <SectionCard title="G. Factores de riesgo del trabajo actual">
        <Lista items={(ficha.factores_riesgo ?? []).map(f => f.factor)} />
      </SectionCard>

      <SectionCard title="K. Diagnóstico">
        <Lista items={(ficha.diagnosticos ?? []).map(d =>
          `${d.diagnostico?.codigo ?? '—'} ${d.diagnostico?.descripcion ?? ''} · ${d.tipo === 'definitivo' ? 'DEF' : 'PRE'}`)} />
      </SectionCard>

      <SectionCard title="L–M. Aptitud, recomendaciones y tratamiento">
        <DetailList columnas={2} items={[
          { label: 'Observaciones de la aptitud', value: ficha.restricciones || '—', ancho: true },
          { label: 'Recomendaciones', value: ficha.recomendaciones || '—', ancho: true },
          { label: 'Tratamiento', value: ficha.tratamiento || '—', ancho: true },
        ]} />
      </SectionCard>

      {ficha.tipo_ficha === 'retiro' && (
        <SectionCard title="N. Retiro (evaluación)">
          <DetailList columnas={2} items={[
            { label: 'Se realiza la evaluación', value: siNo(ficha.se_realiza_evaluacion_retiro) },
            { label: 'Condición relacionada con el trabajo', value: siNo(ficha.condicion_relacionada_trabajo) },
            { label: 'Observación', value: ficha.observacion_retiro || '—', ancho: true },
          ]} />
        </SectionCard>
      )}
    </Stack>
  )
}
