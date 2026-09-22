'use client'

import { useMemo } from 'react'
import {
  Stack, Group, NumberInput, Button, SimpleGrid,
  Textarea, Text, Card, Avatar, Divider, Alert,
} from '@mantine/core'
import {
  useForm, useWatch, Controller, type DefaultValues,
} from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  IconCheck, IconUser, IconScale, IconHeartbeat,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useRegistrarSignosVitalesSolicitud } from '../hooks/useSolicitudSignosVitales'
import {
  solicitudSignosVitalesSchema,
  type SolicitudSignosVitalesFormData,
} from '../schemas/solicitudSignosVitales.schema'
import { TIPO_EVENTO_OPTIONS } from '../services/solicitudCertificacionService'
import type { SolicitudCertificacion } from '../services/solicitudCertificacionService'
import { StatusBadge } from '@/components/ui'

interface Props {
  solicitud:  SolicitudCertificacion
  onCreado:   () => void
  onCancelar: () => void
}

/**
 * Ninguna constante vital arranca con un valor: se teclean todas. Las claves
 * se omiten en vez de escribirlas `undefined`, que es lo que antes obligaba a
 * una aserción de tipo — el esquema las declara `number`, no `number |
 * undefined` (ver regla 09).
 */
const VALORES_INICIALES: DefaultValues<SolicitudSignosVitalesFormData> = {
  observaciones_enfermera: '',
}

function calcularImc(pesoKg?: number, tallaCm?: number): number | null {
  if (!pesoKg || !tallaCm) return null
  const tallaMetros = tallaCm / 100
  if (tallaMetros <= 0) return null
  return Math.round((pesoKg / (tallaMetros ** 2)) * 100) / 100
}

function clasificacionImc(imc: number | null): {
  texto: string; color: string
} {
  if (imc === null) return { texto: '—', color: 'slate' }
  if (imc < 18.5) return { texto: 'Bajo peso', color: 'ocean' }
  if (imc < 25)   return { texto: 'Normal',    color: 'emerald' }
  if (imc < 30)   return { texto: 'Sobrepeso', color: 'amber' }
  return { texto: 'Obesidad', color: 'red' }
}

export function SolicitudSignosVitalesForm({ solicitud, onCreado, onCancelar }: Props) {
  const contained = useContainedInput()
  const registrar = useRegistrarSignosVitalesSolicitud()

  const tipoLabel = TIPO_EVENTO_OPTIONS.find(
    o => o.value === solicitud.tipo_evento
  )?.label ?? solicitud.tipo_evento

  const {
    control, handleSubmit,
    formState: { errors },
  } = useForm<SolicitudSignosVitalesFormData>({
    resolver: zodResolver(solicitudSignosVitalesSchema),
    defaultValues: VALORES_INICIALES,
  })

  const peso  = useWatch({ control, name: 'peso_kg' })
  const talla = useWatch({ control, name: 'talla_cm' })

  const imc = useMemo(
    () => calcularImc(peso, talla),
    [peso, talla]
  )
  const clasificacion = clasificacionImc(imc)

  const onSubmit = (values: SolicitudSignosVitalesFormData) => {
    registrar.mutate(
      { id: solicitud.id, data: values },
      { onSuccess: () => onCreado() }
    )
  }

  return (
    <Card withBorder radius="lg" p="xl">
      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack gap="lg">

          <Group justify="space-between" wrap="nowrap">
            <Group gap="sm">
              <Avatar radius="xl">
                <IconUser size={16} />
              </Avatar>
              <Stack gap={0}>
                <Text size="sm" fw={600}>
                  {solicitud.nombres_paciente}
                </Text>
                <Text size="xs" c="dimmed" ff="monospace">
                  {solicitud.cedula_paciente}
                </Text>
              </Stack>
            </Group>
            <StatusBadge>
              {tipoLabel}
            </StatusBadge>
          </Group>

          <Divider
            label={
              <Group gap={6}>
                <IconScale size={13} />
                <Text
                  size="xs" fw={600} tt="uppercase"
                  style={{ letterSpacing: '0.04em' }}
                >
                  Antropometría
                </Text>
              </Group>
            }
            labelPosition="left"
          />

          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <Controller
              name="peso_kg"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Peso (kg)"
                  decimalScale={2}
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.peso_kg?.message}
                />
              )}
            />
            <Controller
              name="talla_cm"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Talla (cm)"
                  decimalScale={1}
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.talla_cm?.message}
                />
              )}
            />
          </SimpleGrid>

          {imc !== null && (
            <Alert
              icon={<IconScale size={14} />}
              color={clasificacion.color}
              variant="light"
              radius="md"
            >
              <Text size="xs">
                IMC calculado: <strong>{imc}</strong>
                {' — '}{clasificacion.texto}
              </Text>
            </Alert>
          )}

          <Divider
            label={
              <Group gap={6}>
                <IconHeartbeat size={13} />
                <Text
                  size="xs" fw={600} tt="uppercase"
                  style={{ letterSpacing: '0.04em' }}
                >
                  Signos vitales
                </Text>
              </Group>
            }
            labelPosition="left"
          />

          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <Controller
              name="presion_sistolica"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="P. sistólica"
                  description="Normal: 90–120 mmHg"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.presion_sistolica?.message}
                />
              )}
            />
            <Controller
              name="presion_diastolica"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="P. diastólica"
                  description="Normal: 60–80 mmHg"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.presion_diastolica?.message}
                />
              )}
            />
          </SimpleGrid>

          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <Controller
              name="frecuencia_cardiaca"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Frec. cardíaca"
                  description="Normal: 60–100 lpm"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.frecuencia_cardiaca?.message}
                />
              )}
            />
            <Controller
              name="frecuencia_respiratoria"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Frec. respiratoria"
                  description="Normal: 12–20 rpm"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.frecuencia_respiratoria?.message}
                />
              )}
            />
          </SimpleGrid>

          <SimpleGrid cols={{ base: 1, sm: 2 }}>
            <Controller
              name="temperatura_c"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Temperatura (°C)"
                  decimalScale={1}
                  description="Normal: 36.1–37.2 °C"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.temperatura_c?.message}
                />
              )}
            />
            <Controller
              name="saturacion_oxigeno"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Sat. oxígeno (%)"
                  decimalScale={1}
                  description="Normal: 95–100 %"
                  hideControls
                  {...contained}
                  value={field.value}
                  onChange={(v) => field.onChange(Number(v) || undefined)}
                  error={errors.saturacion_oxigeno?.message}
                />
              )}
            />
          </SimpleGrid>

          <Controller
            name="glucosa"
            control={control}
            render={({ field }) => (
              <NumberInput
                label="Glucosa (opcional)"
                decimalScale={1}
                description="Normal en ayunas: 70–100 mg/dL"
                hideControls
                {...contained}
                value={field.value ?? undefined}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />

          <Controller
            name="observaciones_enfermera"
            control={control}
            render={({ field }) => (
              <Textarea
                label="Observaciones (opcional)"
                placeholder="Anotaciones relevantes durante la toma de signos vitales"
                autosize
                minRows={2}
                {...contained}
                value={field.value ?? ''}
                onChange={(e) => field.onChange(e.currentTarget.value)}
              />
            )}
          />

          <Group justify="flex-end" mt="sm">
            <Button variant="default" onClick={onCancelar}>
              Cancelar
            </Button>
            <Button
              type="submit"
              leftSection={<IconCheck size={14} />}
              loading={registrar.isPending}
            >
              Registrar signos vitales
            </Button>
          </Group>

        </Stack>
      </form>
    </Card>
  )
}
