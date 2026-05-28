'use client'

import { Modal, Button, Group, Select,
         TextInput, Grid, Switch, Text } from '@mantine/core'
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

const PROPOSITO_OPTIONS = [
  { value: 'sueldo',   label: 'Nómina / Sueldo' },
  { value: 'viaticos', label: 'Viáticos' },
  { value: 'ambos',    label: 'Sueldo y Viáticos' },
]

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function CuentaBancariaModal({ opened, onClose, servidorId }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { crear }     = useCuentaBancariaMutations(servidorId)
  const { data: entidades = [], isLoading: loadingEntidades } =
    useEntidadesFinancieras()

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
      proposito:             'sueldo',
      es_principal_sueldo:   false,
      es_principal_viatico:  false,
      estado:                true,
    },
  })

  const entidadOptions = (entidades as EntidadFinanciera[]).map(e => ({
    value: String(e.id),
    label: e.nombre ?? `Entidad ${e.id}`,
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
          {/* Entidad financiera */}
          <Grid.Col span={12}>
            <Controller
              name="entidad_financiera_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Entidad financiera"
                  placeholder={
                    loadingEntidades
                      ? 'Cargando entidades...'
                      : 'Buscar banco o cooperativa'
                  }
                  data={entidadOptions}
                  searchable
                  disabled={loadingEntidades}
                  {...contained}
                  value={field.value ? String(field.value) : ''}
                  onChange={(v) => field.onChange(v ? Number(v) : undefined)}
                  error={errors.entidad_financiera_id?.message}
                />
              )}
            />
          </Grid.Col>

          {/* Número de cuenta */}
          <Grid.Col span={{ base: 12, sm: 7 }}>
            <TextInput
              label="Número de cuenta"
              placeholder="Número completo de la cuenta"
              {...contained}
              {...register('numero_cuenta')}
              error={errors.numero_cuenta?.message}
            />
          </Grid.Col>

          {/* Tipo de cuenta */}
          <Grid.Col span={{ base: 12, sm: 5 }}>
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

          {/* Propósito */}
          <Grid.Col span={12}>
            <Controller
              name="proposito"
              control={control}
              render={({ field }) => (
                <Select
                  label="Propósito de la cuenta"
                  placeholder="Seleccionar propósito"
                  data={PROPOSITO_OPTIONS}
                  {...contained}
                  value={field.value}
                  onChange={(v) =>
                    field.onChange(
                      (v ?? 'sueldo') as CuentaBancariaFormData['proposito']
                    )
                  }
                  error={errors.proposito?.message}
                />
              )}
            />
          </Grid.Col>

          {/* Switches */}
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="es_principal_sueldo"
              control={control}
              render={({ field }) => (
                <Switch
                  label="Cuenta principal de nómina"
                  checked={field.value ?? false}
                  onChange={(e) => field.onChange(e.currentTarget.checked)}
                  color="emerald"
                  mt="xs"
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="es_principal_viatico"
              control={control}
              render={({ field }) => (
                <Switch
                  label="Cuenta principal de viáticos"
                  checked={field.value ?? false}
                  onChange={(e) => field.onChange(e.currentTarget.checked)}
                  color="emerald"
                  mt="xs"
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
