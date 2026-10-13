'use client'

import { Button, Grid, NumberInput, Stack, Switch, Text } from '@mantine/core'
import { TimeInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { DataState, DetailList, SectionCard } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useGuardarReglasPrestamo, useReglasPrestamoTic } from '../hooks/usePrestamosTic'
import { reglasSchema, type ReglasFormData } from '../schemas/reglas.schema'
import type { ReglasPrestamo } from '../services/tipos'
import { momentoCorto } from '../utils/agenda'

/** Las reglas del préstamo (D16): las comprueba el backend en cada solicitud y préstamo directo. */
export function ReglasPrestamoPanel({ puedeEditar }: { puedeEditar: boolean }) {
  const { data, isLoading, error } = useReglasPrestamoTic()

  return (
    <DataState loading={isLoading} error={error}>
      {data && (puedeEditar ? <Formulario reglas={data} /> : <Resumen reglas={data} />)}
    </DataState>
  )
}

/** Para quien consulta: los valores, sin controles apagados que no se leen. */
function Resumen({ reglas: r }: { reglas: ReglasPrestamo }) {
  return (
    <SectionCard title="Reglas del préstamo de equipos">
      <DetailList columnas={2} items={[
        { label: 'Plazo máximo', value: `${r.plazo_maximo_dias_habiles} día(s) hábil(es)` },
        { label: 'Atención', value: `De ${r.hora_apertura} a ${r.hora_cierre}` },
        { label: 'Anticipación mínima', value: r.anticipacion_minima_minutos > 0 ? `${r.anticipacion_minima_minutos} minutos` : 'Ninguna' },
        { label: 'Margen para retirar', value: `${r.margen_retiro_minutos} minutos` },
        { label: 'Fines de semana y feriados', value: r.presta_fines_de_semana ? 'Se presta' : 'No se presta' },
        { label: 'Con un préstamo vencido', value: r.bloquea_con_vencido ? 'No puede pedir otro' : 'Puede pedir otro' },
      ]} />
    </SectionCard>
  )
}

function Formulario({ reglas }: { reglas: ReglasPrestamo }) {
  const contained = useContainedInput()
  const guardar = useGuardarReglasPrestamo()
  const { control, handleSubmit, setError, formState: { errors, isDirty } } = useForm<ReglasFormData>({
    resolver: zodResolver(reglasSchema),
    values: {
      plazo_maximo_dias_habiles: reglas.plazo_maximo_dias_habiles,
      anticipacion_minima_minutos: reglas.anticipacion_minima_minutos,
      hora_apertura: reglas.hora_apertura,
      hora_cierre: reglas.hora_cierre,
      presta_fines_de_semana: reglas.presta_fines_de_semana,
      bloquea_con_vencido: reglas.bloquea_con_vencido,
      margen_retiro_minutos: reglas.margen_retiro_minutos,
    },
  })

  const enviar = (v: ReglasFormData) =>
    guardar.mutateAsync(v).catch((e) => erroresAlFormulario(e, setError, Object.keys(reglasSchema.shape), null))

  const numero = (name: 'plazo_maximo_dias_habiles' | 'anticipacion_minima_minutos' | 'margen_retiro_minutos', label: string, description: string) => (
    <Controller name={name} control={control} render={({ field }) => (
      <NumberInput label={label} description={description} min={0} allowDecimal={false} hideControls
        error={errors[name]?.message} {...contained} value={field.value} onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)} />
    )} />
  )
  const horaCampo = (name: 'hora_apertura' | 'hora_cierre', label: string) => (
    <Controller name={name} control={control} render={({ field }) => (
      <TimeInput label={label} error={errors[name]?.message} {...contained}
        value={field.value} onChange={(e) => field.onChange(e.currentTarget.value)} />
    )} />
  )
  const interruptor = (name: 'presta_fines_de_semana' | 'bloquea_con_vencido', label: string, description: string) => (
    <Controller name={name} control={control} render={({ field }) => (
      <Switch label={label} description={description}
        checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
    )} />
  )

  return (
    <form noValidate onSubmit={handleSubmit(enviar)}>
      <SectionCard title="Reglas del préstamo de equipos"
        actions={<Button type="submit" loading={guardar.isPending} disabled={!isDirty}>Guardar</Button>}>
        <Stack gap="md">
          <Grid>
            <Grid.Col span={{ base: 12, sm: 4 }}>{numero('plazo_maximo_dias_habiles', 'Plazo máximo (días hábiles)', 'El día de la entrega cuenta como el primero')}</Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>{numero('anticipacion_minima_minutos', 'Anticipación mínima (minutos)', '0: se puede pedir para ahora mismo')}</Grid.Col>
            <Grid.Col span={{ base: 12, sm: 4 }}>{numero('margen_retiro_minutos', 'Margen para retirar (minutos)', 'Después, la reserva pasa a «no retirado»')}</Grid.Col>
            <Grid.Col span={{ base: 6, sm: 4 }}>{horaCampo('hora_apertura', 'Atención desde')}</Grid.Col>
            <Grid.Col span={{ base: 6, sm: 4 }}>{horaCampo('hora_cierre', 'Atención hasta')}</Grid.Col>
          </Grid>
          {interruptor('presta_fines_de_semana', 'Prestar en fines de semana y feriados', 'Si no, esos días no se pueden pedir ni devolver equipos')}
          {interruptor('bloquea_con_vencido', 'Bloquear a quien tiene un préstamo vencido', 'No puede pedir otro hasta devolver lo que debe')}
          {reglas.updated_at && (
            <Text size="xs" c="dimmed">Última modificación: {momentoCorto(reglas.updated_at)}{reglas.actualizado_por ? `, por ${reglas.actualizado_por}` : ''}.</Text>
          )}
        </Stack>
      </SectionCard>
    </form>
  )
}
