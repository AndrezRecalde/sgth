'use client'

import { Modal, Button, Group, Select,
         TextInput, Grid } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useEntidadesFinancieras } from '../hooks/useEntidadesFinancieras'
import { useCuentaBancariaMutations } from '../hooks/useCuentaBancariaMutations'
import {
  cuentaBancariaSchema,
  type CuentaBancariaFormData,
} from '../schemas/cuentaBancaria.schema'
import type { EntidadFinanciera } from '@/types/api'

const TIPO_CUENTA_OPTIONS = [
  { value: 'ahorros',   label: 'Cuenta de ahorros' },
  { value: 'corriente', label: 'Cuenta corriente' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function CuentaBancariaModal({ opened, onClose, servidorId }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained    = useContainedInput()
  const { crear }    = useCuentaBancariaMutations(servidorId)
  const { data: entidades = [] } = useEntidadesFinancieras()

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CuentaBancariaFormData>({
    resolver: zodResolver(cuentaBancariaSchema),
    defaultValues: {
      entidad_financiera_id: undefined,
      numero_cuenta:         '',
      tipo_cuenta:           'ahorros',
    },
  })

  const entidadOptions = (entidades as EntidadFinanciera[]).map(e => ({
    value: String(e.id),
    label: (e as EntidadFinanciera & { nombre?: string }).nombre
      ?? `Entidad ${e.id}`,
  }))

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = (values: CuentaBancariaFormData) => {
    crear.mutateAsync(values)
      .then(handleClose)
      .catch(() => {})
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Nueva cuenta bancaria"
      size="md"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Grid>
          <Grid.Col span={12}>
            <Controller
              name="entidad_financiera_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Entidad financiera"
                  placeholder="Seleccionar banco o cooperativa"
                  data={entidadOptions}
                  searchable
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.entidad_financiera_id?.message}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 8 }}>
            <TextInput
              label="Número de cuenta"
              placeholder="Número de cuenta bancaria"
              {...contained}
              {...register('numero_cuenta')}
              error={errors.numero_cuenta?.message}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <Controller
              name="tipo_cuenta"
              control={control}
              render={({ field }) => (
                <Select
                  label="Tipo de cuenta"
                  data={TIPO_CUENTA_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) =>
                    field.onChange((v ?? 'ahorros') as 'ahorros' | 'corriente')
                  }
                  error={errors.tipo_cuenta?.message}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        <Group justify="flex-end" mt="xl">
          <Button variant="default" onClick={handleClose}>
            Cancelar
          </Button>
          <Button
            type="submit"
            color="emerald"
            variant="light"
            loading={crear.isPending}
          >
            Registrar cuenta
          </Button>
        </Group>
      </form>
    </Modal>
  )
}
