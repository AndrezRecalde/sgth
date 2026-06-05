'use client'

import {
  Modal, Stack, Grid, Select, Textarea,
  Button, Group, NumberInput, Divider,
  Alert, Text,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import {
  viaticoSchema,
  type ViaticoFormData,
} from '../schemas/viatico.schema'

const ZONA_OPTIONS = [
  { value: 'dentro_provincia', label: 'Dentro de la provincia' },
  { value: 'fuera_provincia',  label: 'Fuera de la provincia'  },
  { value: 'exterior',         label: 'Exterior (internacional)' },
]

const MODALIDAD_OPTIONS = [
  { value: 'total',        label: 'Anticipo total (100%)' },
  { value: 'parcial',      label: 'Anticipo parcial'      },
  { value: 'sin_anticipo', label: 'Sin anticipo'          },
]

const TIPO_VIAJE_OPTIONS = [
  { value: 'capacitacion',              label: 'Capacitación' },
  { value: 'reunion_oficial',           label: 'Reunión oficial' },
  { value: 'taller_foro_seminario',     label: 'Taller / Foro / Seminario' },
  { value: 'feria_evento_especial',     label: 'Feria o evento especial' },
  { value: 'visita_protocolar',         label: 'Visita protocolar' },
  { value: 'firma_acuerdo',             label: 'Firma de acuerdo' },
  { value: 'visita_tecnica',            label: 'Visita técnica' },
  { value: 'cooperacion_internacional', label: 'Cooperación internacional' },
  { value: 'asistencia_humanitaria',    label: 'Asistencia humanitaria' },
]

const PAISES_COMUNES = [
  'Colombia', 'Perú', 'Bolivia', 'Chile', 'Argentina',
  'Brasil', 'Venezuela', 'México', 'España',
  'Estados Unidos', 'Canadá', 'Francia', 'Alemania',
  'Italia', 'China', 'Japón', 'Otro',
]

interface Props {
  opened:  boolean
  onClose: () => void
}

export function ViaticoModal({ opened, onClose }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { solicitar } = useViaticoMutations()

  const {
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: {
      zona:                    'fuera_provincia',
      tipo_viaje:              null,
      pais_destino:            null,
      justificacion:           '',
      modalidad_anticipo:      'total',
      monto_calculado:         null,
      servidores_acompanantes: [],
    },
  })

  const zonaWatch      = watch('zona')
  const modalidadWatch = watch('modalidad_anticipo')

  const handleClose = () => { reset(); onClose() }

  const onSubmit = async (values: ViaticoFormData) => {
    await solicitar.mutateAsync(values)
    handleClose()
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="Nueva solicitud de viático"
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">

          <Divider label="Zona del viaje" labelPosition="left" />

          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="zona"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Zona geográfica"
                    data={ZONA_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(v ?? 'fuera_provincia')
                    }
                    error={errors.zona?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="modalidad_anticipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Modalidad de anticipo"
                    data={MODALIDAD_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(v ?? 'total')
                    }
                    error={errors.modalidad_anticipo?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          {modalidadWatch === 'parcial' && (
            <Controller
              name="monto_calculado"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Monto del anticipo parcial (USD)"
                  description="Monto a entregar antes del viaje"
                  prefix="$"
                  decimalScale={2}
                  min={0}
                  {...contained}
                  value={field.value ?? 0}
                  onChange={(v) =>
                    field.onChange(typeof v === 'number' ? v : null)
                  }
                  error={errors.monto_calculado?.message}
                />
              )}
            />
          )}

          {zonaWatch === 'exterior' && (
            <>
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="blue"
                variant="light"
              >
                <Text size="xs">
                  Para viajes al exterior el monto se calcula
                  manualmente según el Acuerdo MRL-2011-00051
                  (valor base × coeficiente del país destino).
                </Text>
              </Alert>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="tipo_viaje"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Tipo de viaje"
                        placeholder="Seleccionar tipo"
                        data={TIPO_VIAJE_OPTIONS}
                        searchable
                        clearable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="pais_destino"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="País de destino"
                        placeholder="Seleccionar país"
                        data={PAISES_COMUNES}
                        searchable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={12}>
                  <Controller
                    name="monto_calculado"
                    control={control}
                    render={({ field }) => (
                      <NumberInput
                        label="Monto calculado exterior (USD)"
                        description="Valor base × coeficiente país × días"
                        prefix="$"
                        decimalScale={2}
                        min={0}
                        {...contained}
                        value={field.value ?? 0}
                        onChange={(v) =>
                          field.onChange(
                            typeof v === 'number' ? v : null
                          )
                        }
                        error={errors.monto_calculado?.message}
                      />
                    )}
                  />
                </Grid.Col>
              </Grid>
            </>
          )}

          <Divider label="Justificación" labelPosition="left" />

          <Controller
            name="justificacion"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Justificación del viaje"
                placeholder="Detalle el motivo de la comisión de servicio"
                autosize
                minRows={3}
                maxRows={6}
                {...contained}
                value={field.value}
                onChange={(e) =>
                  field.onChange(e.currentTarget.value)
                }
                error={errors.justificacion?.message}
              />
            )}
          />

          <Group justify="flex-end" mt="md">
            <Button variant="default" onClick={handleClose}>
              Cancelar
            </Button>
            <Button
              type="submit"
              color="emerald"
              variant="light"
              loading={isSubmitting}
            >
              Crear solicitud
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
