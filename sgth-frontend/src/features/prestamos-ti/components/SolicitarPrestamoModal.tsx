'use client'

import { Checkbox, Grid, MultiSelect, Stack, Text, TextInput, Textarea } from '@mantine/core'
import { DateTimePicker } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { fromDateTimeValue } from '@/lib/fecha'
import { useMisPrestamoMutations } from '../hooks/useMisPrestamos'
import { solicitudSchema, type SolicitudFormData } from '../schemas/solicitud.schema'
import type { EquipoPrestable, ReglasPrestamo } from '../services/tipos'

const SELECTOR_HORA = { withDropdown: true, popoverProps: { withinPortal: false }, format: '24h' as const }
const CAMPOS = ['bienes', 'inicio_previsto', 'fin_previsto', 'motivo', 'lugar_uso', 'fuera_del_edificio'] as const

/** Lo que trae el formulario al abrirse: los equipos marcados y, si se eligió en la agenda, la hora. */
export type PropuestaSolicitud = { bienes: number[]; inicio?: string; fin?: string }

interface Props {
  propuesta: PropuestaSolicitud | null
  equipos: EquipoPrestable[]
  reglas: ReglasPrestamo | undefined
  onClose: () => void
  /** Después de enviarla: la agenda suelta los equipos marcados. */
  onEnviada?: () => void
}

/** Pedir equipos concretos para un horario (D13). TIC la aprueba o la rechaza. */
export function SolicitarPrestamoModal({ propuesta, ...props }: Props) {
  return propuesta ? <Formulario propuesta={propuesta} {...props} /> : null
}

function Formulario({ propuesta, equipos, reglas, onClose, onEnviada }: Props & { propuesta: PropuestaSolicitud }) {
  const contained = useContainedInput()
  const { solicitar } = useMisPrestamoMutations()

  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<SolicitudFormData>({
    resolver: zodResolver(solicitudSchema),
    defaultValues: {
      bienes: propuesta.bienes, inicio_previsto: propuesta.inicio ?? '', fin_previsto: propuesta.fin ?? '',
      motivo: '', lugar_uso: '', fuera_del_edificio: false,
    },
  })

  const opciones = equipos
    .filter((e) => e.ahora.estado !== 'no_disponible')
    .map((e) => ({ value: String(e.id), label: `${e.codigo_institucional} · ${[e.tipo, e.marca, e.modelo].filter(Boolean).join(' ')}` }))

  const enviar = (v: SolicitudFormData) =>
    solicitar.mutateAsync(v)
      .then(() => { onEnviada?.(); onClose() })
      .catch((e) => erroresAlFormulario(e, setError, [...CAMPOS], 'No se pudo enviar la solicitud de préstamo'))

  const fecha = (name: 'inicio_previsto' | 'fin_previsto', label: string) => (
    <Controller name={name} control={control} render={({ field }) => (
      <DateTimePicker label={label} valueFormat="DD/MM/YYYY HH:mm" withAsterisk timePickerProps={SELECTOR_HORA}
        minDate={new Date()} error={errors[name]?.message} {...contained}
        value={field.value ? new Date(field.value) : null} onChange={(d) => field.onChange(fromDateTimeValue(d))} />
    )} />
  )

  return (
    <FormModal opened onClose={onClose} title="Pedir equipos en préstamo" onSubmit={handleSubmit(enviar)}
      submitLabel="Enviar solicitud" submitting={solicitar.isPending} size="lg">
      <Stack gap="md">
        {reglas && (
          <Text size="sm" c="dimmed">
            TIC presta de {reglas.hora_apertura} a {reglas.hora_cierre}
            {reglas.presta_fines_de_semana ? '' : ', de lunes a viernes'}, hasta {reglas.plazo_maximo_dias_habiles} día(s) hábil(es).
            La solicitud no aparta el horario: queda reservado cuando TIC la aprueba.
          </Text>
        )}
        <Controller name="bienes" control={control} render={({ field }) => (
          <MultiSelect label="Equipos" placeholder="Elija uno o varios" data={opciones} searchable withAsterisk
            error={errors.bienes?.message} {...contained}
            value={field.value.map(String)} onChange={(v) => field.onChange(v.map(Number))} />
        )} />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('inicio_previsto', 'Lo retira')}</Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fin_previsto', 'Lo devuelve')}</Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Para qué lo necesita" placeholder="Ej.: Presentación del plan operativo a los directores."
              withAsterisk autosize minRows={2} error={errors.motivo?.message} {...contained} {...register('motivo')} />
          </Grid.Col>
          <Grid.Col span={12}>
            <TextInput label="Dónde lo va a usar" placeholder="Ej.: Sala de sesiones, piso 3" withAsterisk
              error={errors.lugar_uso?.message} {...contained} {...register('lugar_uso')} />
          </Grid.Col>
          <Grid.Col span={12}>
            <Checkbox label="Lo voy a usar fuera del edificio" {...register('fuera_del_edificio')} />
          </Grid.Col>
        </Grid>
      </Stack>
    </FormModal>
  )
}
