'use client'

import { Modal, Button, Group, Stack, Select,
         Textarea, Grid } from '@mantine/core'
import { DatePickerInput, TimeInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePermisoMutations } from '../hooks/usePermisoMutations'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import type { UnidadConRelaciones } from '@/types/api'

const TIPO_OPTIONS = [
  { value: 'personal',   label: 'Personal (máx. 4 horas)' },
  { value: 'oficial',    label: 'Oficial' },
  { value: 'enfermedad', label: 'Enfermedad' },
  { value: 'calamidad',  label: 'Calamidad doméstica' },
]

const schema = z.object({
  tipo:        z.enum(['personal', 'oficial', 'enfermedad', 'calamidad']),
  fecha:       z.string().min(1, 'La fecha es requerida'),
  hora_inicio: z.string().min(1, 'Requerido'),
  hora_fin:    z.string().min(1, 'Requerido'),
  observacion: z.string().optional().nullable(),
  unidad_administrativa_id: z.number().optional().nullable(),
})

type FormData = z.infer<typeof schema>

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}
const fromDate = (d: any): string | null => {
  if (!d) return null
  if (typeof d === 'string') return d.substring(0, 10)
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

interface Props {
  opened: boolean
  onClose: () => void
  servidorId?: number
}

export function PermisoModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()
  const { crear }    = usePermisoMutations()

  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidadOptions = ((unidadesRaw ?? []) as UnidadConRelaciones[])
    .map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    }))

  const {
    register, control, handleSubmit, reset,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      tipo:        'personal',
      fecha:       '',
      hora_inicio: '08:00',
      hora_fin:    '12:00',
      observacion: '',
      unidad_administrativa_id: null,
    },
  })

  const handleClose = () => { reset(); onClose() }

  const onSubmit = async (values: FormData) => {
    await crear.mutateAsync({
      ...values,
      servidor_id: servidorId,
    })
    handleClose()
  }

  return (
    <Modal
      opened={opened} onClose={handleClose}
      title="Registrar permiso"
      size="md" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller
            name="unidad_administrativa_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Unidad administrativa"
                placeholder="Seleccionar unidad (opcional)"
                data={unidadOptions}
                searchable
                clearable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.unidad_administrativa_id?.message}
              />
            )}
          />
          <Controller name="tipo" control={control}
            render={({ field }) => (
              <Select
                label="Tipo de permiso"
                data={TIPO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'personal')}
                error={errors.tipo?.message}
              />
            )}
          />
          <Controller name="fecha" control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha del permiso"
                placeholder="Seleccionar fecha"
                valueFormat="YYYY-MM-DD"
                {...contained}
                value={toDate(field.value)}
                onChange={(d: any) => field.onChange(fromDate(d) ?? '')}
                error={errors.fecha?.message}
              />
            )}
          />
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TimeInput
                label="Hora inicio"
                {...contained}
                {...register('hora_inicio')}
                error={errors.hora_inicio?.message}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <TimeInput
                label="Hora fin"
                {...contained}
                {...register('hora_fin')}
                error={errors.hora_fin?.message}
              />
            </Grid.Col>
          </Grid>
          <Textarea
            label="Observación"
            placeholder="Motivo del permiso (requerido para oficial)"
            rows={3}
            {...contained}
            {...register('observacion')}
            error={errors.observacion?.message}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" color="emerald" variant="light"
              loading={isSubmitting}>
              Registrar permiso
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
