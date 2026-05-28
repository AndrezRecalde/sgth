'use client'

import { Modal, Button, Group, Stack, TextInput,
         Select, Textarea } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useDeclaracionMutations } from '../hooks/useDeclaracionMutations'
import { declaracionSchema, type DeclaracionFormData }
  from '../schemas/declaracion.schema'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'

const TIPO_OPTIONS = [
  { value: 'ingreso',       label: 'Ingreso' },
  { value: 'salida',        label: 'Salida' },
  { value: 'actualizacion', label: 'Actualización' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

const toDate = (v?: string | null): Date | null =>
  v ? new Date(v) : null
const fromDate = (d: Date | string | null): string | null => {
  if (!d) return null
  const date = new Date(d)
  return isNaN(date.getTime()) ? null : date.toISOString().split('T')[0]
}

export function DeclaracionModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()
  const { crear }  = useDeclaracionMutations(servidorId)

  const { register, control, handleSubmit, reset, formState: { errors } } =
    useForm<DeclaracionFormData>({
      resolver: zodResolver(declaracionSchema),
      defaultValues: {
        tipo_declaracion:  'ingreso',
        fecha_declaracion: '',
        codigo_barras:     '',
        observaciones:     '',
      },
    })

  const onSubmit = (values: DeclaracionFormData) => {
    crear.mutateAsync(values as Record<string, unknown>)
      .then(() => { reset(); onClose() })
      .catch(() => {})
  }

  return (
    <Modal opened={opened} onClose={onClose}
      title="Registrar declaración juramentada"
      size="md" fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}>
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">
          <Controller name="tipo_declaracion" control={control}
            render={({ field }) => (
              <Select label="Tipo de declaración"
                data={TIPO_OPTIONS} {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'ingreso')}
                error={errors.tipo_declaracion?.message} />
            )} />
          <Controller
            name="fecha_declaracion"
            control={control}
            render={({ field }) => (
              <DatePickerInput
                label="Fecha de declaración"
                placeholder="Seleccionar fecha"
                valueFormat="YYYY-MM-DD"
                clearable
                {...contained}
                value={toDate(field.value)}
                onChange={(d) => field.onChange(fromDate(d))}
                error={errors.fecha_declaracion?.message}
              />
            )}
          />
          <TextInput label="Código de barras / Número"
            placeholder="Número de la declaración"
            {...contained} {...register('codigo_barras')}
            error={errors.codigo_barras?.message} />
          <Textarea label="Observaciones"
            placeholder="Opcional" rows={3}
            {...contained} {...register('observaciones')}
            error={errors.observaciones?.message} />
          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={onClose}>Cancelar</Button>
            <Button type="submit" color="emerald" variant="light"
              loading={crear.isPending}>
              Registrar declaración
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
