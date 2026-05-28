'use client'

import { useState, useEffect } from 'react'
import { Modal, Button, Group, Select, TextInput,
         Grid, Switch, Text, Stack, Alert } from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle } from '@tabler/icons-react'
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
  const { data: rawEntidades, isLoading: loadingEntidades } =
    useEntidadesFinancieras()

  // Mapear entidades de forma segura sin depender del tipo generado
  const entidadOptions = Array.isArray(rawEntidades)
    ? rawEntidades.map((e: Record<string, unknown>) => ({
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

  // Asignar propósito automáticamente según los switches
  useEffect(() => {
    if (esPrincipalSueldo && esPrincipalViatico) {
      setValue('proposito', 'ambos')
    } else if (esPrincipalSueldo) {
      setValue('proposito', 'sueldo')
    } else if (esPrincipalViatico) {
      setValue('proposito', 'viaticos')
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
                        : entidadOptions.length === 0
                          ? 'Sin entidades disponibles'
                          : 'Buscar banco o cooperativa'
                    }
                    data={entidadOptions}
                    searchable
                    disabled={loadingEntidades}
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

            {/* Switches de cuenta principal */}
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="es_principal_sueldo"
                control={control}
                render={({ field }) => (
                  <Switch
                    label="Cuenta principal de nómina"
                    checked={field.value ?? false}
                    onChange={(e) =>
                      field.onChange(e.currentTarget.checked)
                    }
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
                    onChange={(e) =>
                      field.onChange(e.currentTarget.checked)
                    }
                    color="emerald"
                    mt="xs"
                  />
                )}
              />
            </Grid.Col>

            {/* Propósito — auto-asignado pero editable */}
            <Grid.Col span={12}>
              <Controller
                name="proposito"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Propósito de la cuenta"
                    description="Se asigna automáticamente según los switches anteriores"
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
          </Grid>

          <Alert
            icon={<IconInfoCircle size={16} />}
            color="blue"
            variant="light"
            radius="md"
          >
            <Text size="xs">
              Marque los switches para indicar si esta cuenta
              recibirá pagos de nómina, viáticos o ambos.
              El propósito se asignará automáticamente.
            </Text>
          </Alert>

          <Group justify="flex-end" mt="xs">
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
