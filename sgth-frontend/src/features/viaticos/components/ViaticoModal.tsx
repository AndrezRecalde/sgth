'use client'

import {
  Modal, Stack, Grid, Select, Textarea,
  Button, Group, NumberInput, Divider,
  Alert, Text, ThemeIcon,
} from '@mantine/core'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconInfoCircle, IconRoute, IconArrowRight,
} from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import {
  viaticoSchema,
  type ViaticoFormData,
} from '../schemas/viatico.schema'
import type { Viatico } from '@/types/api'

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
  opened:    boolean
  onClose:   () => void
  onCreated: (viatico: Viatico) => void
}

export function ViaticoModal({ opened, onClose, onCreated }: Props) {
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
    const viatico = await solicitar.mutateAsync(values)
    reset()
    onClose()
    // Abrir drawer automáticamente con el viático creado
    if (viatico) onCreated(viatico as Viatico)
  }

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="sm">
            <IconRoute size={14} />
          </ThemeIcon>
          <Text fw={600}>Nueva solicitud de viático</Text>
        </Group>
      }
      size="xl"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
      closeOnClickOutside={false}
    >
      <form onSubmit={handleSubmit(onSubmit)}>
        <Stack gap="sm">

          <Alert
            icon={<IconInfoCircle size={14} />}
            color="blue"
            variant="light"
          >
            <Text size="xs" fw={500}>
              ¿Cómo funciona?
            </Text>
            <Text size="xs" mt={2}>
              1. Completa este formulario con los datos básicos
              del viaje y la modalidad de anticipo.
            </Text>
            <Text size="xs">
              2. Al crear la solicitud, el sistema te llevará
              directamente a registrar el <strong>itinerario
              del viaje</strong> (tramos de ida y vuelta).
            </Text>
            <Text size="xs">
              3. Las fechas de salida y llegada se calculan
              automáticamente desde el itinerario.
            </Text>
          </Alert>

          <Divider label="Zona y anticipo" labelPosition="left" />

          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="zona"
                control={control}
                render={({ field }) => (
                  <Select
                    label="¿A dónde viaja?"
                    description="Zona geográfica del viaje"
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
                    label="¿Necesita anticipo?"
                    description="Modalidad de pago anticipado"
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
                  description="¿Cuánto dinero necesita antes del viaje?"
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
                color="orange"
                variant="light"
              >
                <Text size="xs" fw={500}>
                  Viaje al exterior
                </Text>
                <Text size="xs" mt={2}>
                  El monto debe calcularse según el Acuerdo
                  MRL-2011-00051: valor base de su nivel
                  × coeficiente del país destino × número
                  de días. Consulte a la UATH si tiene dudas.
                </Text>
              </Alert>
              <Grid>
                <Grid.Col span={{ base: 12, sm: 6 }}>
                  <Controller
                    name="tipo_viaje"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Motivo del viaje al exterior"
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
                        label="Monto total del viático (USD)"
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

          <Divider label="Justificación del viaje" labelPosition="left" />

          <Controller
            name="justificacion"
            control={control}
            render={({ field }) => (
              <Textarea
                label="¿Cuál es el motivo del viaje?"
                description="Explique el objetivo de la comisión de servicio (mínimo 10 caracteres)"
                placeholder="Ej: Participación en el taller de capacitación sobre gestión pública organizado por el SNAP en la ciudad de Quito..."
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
              rightSection={<IconArrowRight size={14} />}
            >
              Crear y registrar itinerario
            </Button>
          </Group>
        </Stack>
      </form>
    </Modal>
  )
}
