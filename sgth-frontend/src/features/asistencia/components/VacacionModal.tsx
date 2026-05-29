'use client'

import { Modal, Button, Group, Stack,
         Select, NumberInput, Textarea, Grid } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod/v4'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useVacacionMutations } from '../hooks/useVacacionMutations'
import { useUnidades } from '@/features/estructura/hooks/useUnidades'
import type { UnidadConRelaciones } from '@/types/api'

const MOTIVO_OPTIONS = [
  { value: 'vacaciones_anuales',       label: 'Vacaciones Anuales (mayor a 5 días)' },
  { value: 'permiso_cargo_vacaciones', label: 'Permiso con Cargo a Vacaciones (máx. 5 días)' },
  { value: 'licencia_sin_goce',        label: 'Licencia sin Goce de Haberes' },
  { value: 'matrimonio',               label: 'Matrimonio' },
  { value: 'capacitacion',             label: 'Capacitación y/o Adiestramiento' },
  { value: 'enfermedad',               label: 'Enfermedad' },
  { value: 'maternidad',               label: 'Maternidad' },
  { value: 'paternidad',               label: 'Paternidad' },
  { value: 'estudios_sin_remuneracion', label: 'Estudios sin Remuneración' },
  { value: 'calamidad_domestica',      label: 'Calamidad Doméstica' },
  { value: 'licencia_con_goce',        label: 'Licencia con Goce de Sueldo' },
]

const schema = z.object({
  motivo:            z.string().min(1, 'Seleccione el motivo'),
  fecha_inicio:      z.string().min(1, 'Requerido'),
  fecha_fin:         z.string().min(1, 'Requerido'),
  fecha_retorno:     z.string().optional().nullable(),
  dias_solicitados:  z.number().min(1, 'Mínimo 1 día'),
  tipo_dias:         z.enum(['habiles', 'calendario']),
  observacion:       z.string().optional().nullable(),
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
  opened:      boolean
  onClose:     () => void
  servidorId?: number
}

export function VacacionModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()
  const { crear }    = useVacacionMutations()

  const { data: unidadesRaw } = useUnidades({ nivel: 2 })
  const unidadOptions = ((unidadesRaw ?? []) as UnidadConRelaciones[])
    .map(u => ({
      value: String(u.id),
      label: u.nombre ?? `Unidad ${u.id}`,
    }))

  const {
    control, handleSubmit, reset, register,
    formState: { errors, isSubmitting },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      motivo:           '',
      fecha_inicio:     '',
      fecha_fin:        '',
      fecha_retorno:    null,
      dias_solicitados: 1,
      tipo_dias:        'habiles',
      observacion:      '',
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
      title="Solicitud de vacaciones / permiso"
      size="lg" fullScreen={isMobile}
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
          <Controller name="motivo" control={control}
            render={({ field }) => (
              <Select
                label="Motivo"
                placeholder="Seleccionar motivo"
                data={MOTIVO_OPTIONS}
                searchable
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? '')}
                error={errors.motivo?.message}
              />
            )}
          />
          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="fecha_inicio" control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha inicio"
                    placeholder="Seleccionar"
                    valueFormat="YYYY-MM-DD"
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(d: any) => field.onChange(fromDate(d) ?? '')}
                    error={errors.fecha_inicio?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="fecha_fin" control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha fin"
                    placeholder="Seleccionar"
                    valueFormat="YYYY-MM-DD"
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(d: any) => field.onChange(fromDate(d) ?? '')}
                    error={errors.fecha_fin?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller name="fecha_retorno" control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de retorno"
                    placeholder="Opcional"
                    valueFormat="YYYY-MM-DD"
                    clearable
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(d: any) => field.onChange(fromDate(d))}
                    error={errors.fecha_retorno?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 3 }}>
              <Controller name="dias_solicitados" control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Días"
                    min={1} max={365}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(typeof v === 'number' ? v : 1)
                    }
                    error={errors.dias_solicitados?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 3 }}>
              <Controller name="tipo_dias" control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo días"
                    data={[
                      { value: 'habiles',    label: 'Hábiles' },
                      { value: 'calendario', label: 'Calendario' },
                    ]}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange((v ?? 'habiles') as FormData['tipo_dias'])
                    }
                    error={errors.tipo_dias?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>
          <Textarea
            label="Observación"
            placeholder="Observaciones adicionales (opcional)"
            rows={2}
            {...contained}
            {...register('observacion')}
          />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button type="submit" color="emerald" variant="light"
              loading={isSubmitting}>
              Registrar solicitud
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
