'use client'

import { useEffect } from 'react'
import { Modal, Button, Group, Select, TextInput,
         Grid, Switch, Stack, Text, Badge } from '@mantine/core'
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

const TIPO_CUENTA_OPTIONS = [
  { value: 'ahorros',   label: 'Cuenta de ahorros' },
  { value: 'corriente', label: 'Cuenta corriente' },
]

const PROPOSITO_LABEL: Record<string, string> = {
  sueldo:   'Nómina / Sueldo',
  viaticos: 'Viáticos',
  ambos:    'Nómina y Viáticos',
}

interface Props {
  opened:     boolean
  onClose:    () => void
  servidorId: number
}

export function CuentaBancariaModal({ opened, onClose, servidorId }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { crear }     = useCuentaBancariaMutations(servidorId)
  const { data: rawEntidades = [], isLoading: loadingEntidades } =
    useEntidadesFinancieras()

  const entidadOptions = Array.isArray(rawEntidades)
    ? (rawEntidades as Record<string, unknown>[]).map(e => ({
        value: String(e.id),
        label: String(e.nombre ?? `Entidad ${e.id}`),
      }))
    : []

  const {
    register,
    control,
    handleSubmit,
    reset,
    watch,
    setValue,
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

  const esPrincipalSueldo  = watch('es_principal_sueldo')
  const esPrincipalViatico = watch('es_principal_viatico')
  const propositoActual    = watch('proposito')

  // Auto-asignar propósito según switches
  useEffect(() => {
    if (esPrincipalSueldo && esPrincipalViatico) {
      setValue('proposito', 'ambos', { shouldValidate: true })
    } else if (esPrincipalSueldo) {
      setValue('proposito', 'sueldo', { shouldValidate: true })
    } else if (esPrincipalViatico) {
      setValue('proposito', 'viaticos', { shouldValidate: true })
    } else {
      setValue('proposito', 'sueldo', { shouldValidate: true })
    }
  }, [esPrincipalSueldo, esPrincipalViatico, setValue])

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
        <Stack gap="sm">
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
                    nothingFoundMessage="No se encontró la entidad"
                    {...contained}
                    value={field.value ? String(field.value) : null}
                    onChange={(v) =>
                      field.onChange(v ? Number(v) : undefined)
                    }
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
                      field.onChange(
                        (v ?? 'ahorros') as 'ahorros' | 'corriente'
                      )
                    }
                    error={errors.tipo_cuenta?.message}
                  />
                )}
              />
            </Grid.Col>

            {/* Switch nómina */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="es_principal_sueldo"
                control={control}
                render={({ field }) => (
                  <Switch
                    label="Cuenta para nómina"
                    description="Pago de sueldo mensual"
                    checked={field.value ?? false}
                    onChange={(e) =>
                      field.onChange(e.currentTarget.checked)
                    }
                    color="emerald"
                  />
                )}
              />
            </Grid.Col>

            {/* Switch viáticos */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="es_principal_viatico"
                control={control}
                render={({ field }) => (
                  <Switch
                    label="Cuenta para viáticos"
                    description="Pago de viáticos y comisiones"
                    checked={field.value ?? false}
                    onChange={(e) =>
                      field.onChange(e.currentTarget.checked)
                    }
                    color="blue"
                  />
                )}
              />
            </Grid.Col>

            {/* Propósito auto-asignado — solo visual */}
            {(esPrincipalSueldo || esPrincipalViatico) && (
              <Grid.Col span={12}>
                <Group gap="xs">
                  <Text size="xs" c="dimmed">
                    Propósito asignado:
                  </Text>
                  <Badge
                    color={
                      propositoActual === 'ambos' ? 'violet'
                      : propositoActual === 'sueldo' ? 'emerald'
                      : 'blue'
                    }
                    variant="light"
                    size="sm"
                  >
                    {PROPOSITO_LABEL[propositoActual ?? 'sueldo']}
                  </Badge>
                </Group>
              </Grid.Col>
            )}
          </Grid>

          <Group justify="flex-end" mt="md">
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
        </Stack>
      </form>
    </Modal>
  )
}
