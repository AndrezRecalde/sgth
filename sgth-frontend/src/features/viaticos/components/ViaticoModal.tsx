'use client'

import { useEffect } from 'react'
import {
  Modal, Stack, Grid, Select, Textarea,
  Button, Group, NumberInput, Divider,
  Alert, Text, Stepper,
} from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import '@mantine/dates/styles.css'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconInfoCircle } from '@tabler/icons-react'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useComisiones } from '../hooks/useComisiones'
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

const TIPO_OPTIONS = [
  { value: 'con_pernocte',  label: 'Con pernocte' },
  { value: 'sin_pernocte',  label: 'Sin pernocte / Subsistencia' },
]

const MODALIDAD_OPTIONS = [
  { value: 'total',         label: 'Anticipo total' },
  { value: 'parcial',       label: 'Anticipo parcial' },
  { value: 'sin_anticipo',  label: 'Sin anticipo' },
]

const TIPO_VIAJE_OPTIONS = [
  { value: 'capacitacion',             label: 'Capacitación' },
  { value: 'reunion_oficial',          label: 'Reunión oficial' },
  { value: 'taller_foro_seminario',    label: 'Taller / Foro / Seminario' },
  { value: 'feria_evento_especial',    label: 'Feria o evento especial' },
  { value: 'visita_protocolar',        label: 'Visita protocolar' },
  { value: 'firma_acuerdo',            label: 'Firma de acuerdo' },
  { value: 'visita_tecnica',           label: 'Visita técnica' },
  { value: 'cooperacion_internacional',label: 'Cooperación internacional' },
  { value: 'asistencia_humanitaria',   label: 'Asistencia humanitaria' },
]

const toDate = (v?: string | null): Date | null => {
  if (!v) return null
  const [y, m, d] = v.split('-').map(Number)
  return new Date(y, m - 1, d)
}

const fromDate = (d: Date | null): string | null => {
  if (!d) return null
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

interface Props {
  opened:  boolean
  onClose: () => void
}

export function ViaticoModal({ opened, onClose }: Props) {
  const { isMobile }  = useMobileBreakpoint()
  const contained     = useContainedInput()
  const { solicitar } = useViaticoMutations()
  const { data: comisiones = [] } = useComisiones()

  const {
    control,
    handleSubmit,
    reset,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<ViaticoFormData>({
    resolver: zodResolver(viaticoSchema),
    defaultValues: {
      comision_id:          null,
      zona:                 'fuera_provincia',
      tipo:                 'con_pernocte',
      tipo_viaje:           null,
      pais_destino:         null,
      fecha_inicio:         '',
      fecha_fin:            '',
      justificacion:        '',
      modalidad_anticipo:   'total',
      monto_calculado:      null,
      servidores_acompanantes: [],
    },
  })

  const zonaWatch       = watch('zona')
  const modalidadWatch  = watch('modalidad_anticipo')

  const handleClose = () => {
    reset()
    onClose()
  }

  const onSubmit = async (values: ViaticoFormData) => {
    await solicitar.mutateAsync({
      ...values,
      monto_calculado: zonaWatch === 'exterior'
        ? (values.monto_calculado ?? 0)
        : undefined,
    })
    handleClose()
  }

  const comisionOptions = comisiones.map((c) => ({
    value: String(c.id),
    label: `${(c as { codigo_comision?: string }).codigo_comision ?? ''} — ${(c as { motivo?: string }).motivo ?? ''}`.trim(),
  }))

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

          {/* Comisión */}
          <Controller
            name="comision_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Comisión de servicio (opcional)"
                placeholder="Seleccionar comisión"
                data={comisionOptions}
                searchable
                clearable
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
                error={errors.comision_id?.message}
              />
            )}
          />

          <Divider label="Datos del viaje" labelPosition="left" />

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
                name="tipo"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Tipo de viático"
                    data={TIPO_OPTIONS}
                    {...contained}
                    value={field.value}
                    onChange={(v) =>
                      field.onChange(v ?? 'con_pernocte')
                    }
                    error={errors.tipo?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          {/* Exterior: tipo de viaje y país */}
          {zonaWatch === 'exterior' && (
            <>
              <Alert
                icon={<IconInfoCircle size={14} />}
                color="blue"
                variant="light"
              >
                <Text size="xs">
                  Para viajes al exterior el monto debe calcularse
                  manualmente según la tabla del Acuerdo MRL-2011-00051
                  (valor base × coeficiente del país).
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
                        error={errors.tipo_viaje?.message}
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
                        data={[
                          'Colombia', 'Perú', 'Bolivia', 'Chile',
                          'Argentina', 'Brasil', 'Venezuela', 'México',
                          'España', 'Estados Unidos', 'Canadá',
                          'Francia', 'Alemania', 'Italia', 'China',
                          'Japón', 'Otro',
                        ]}
                        searchable
                        {...contained}
                        value={field.value ?? null}
                        onChange={(v) => field.onChange(v ?? null)}
                        error={errors.pais_destino?.message}
                      />
                    )}
                  />
                </Grid.Col>
              </Grid>
              <Controller
                name="monto_calculado"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Monto calculado (USD)"
                    description="Ingrese el monto según tabla MDT vigente"
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
            </>
          )}

          <Grid>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="fecha_inicio"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de inicio"
                    placeholder="Seleccionar fecha"
                    valueFormat="YYYY-MM-DD"
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(v) =>
                      field.onChange(fromDate(v as any) ?? '')
                    }
                    error={errors.fecha_inicio?.message}
                  />
                )}
              />
            </Grid.Col>
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="fecha_fin"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Fecha de fin"
                    placeholder="Seleccionar fecha"
                    valueFormat="YYYY-MM-DD"
                    {...contained}
                    value={toDate(field.value)}
                    onChange={(v) =>
                      field.onChange(fromDate(v as any) ?? '')
                    }
                    error={errors.fecha_fin?.message}
                  />
                )}
              />
            </Grid.Col>
          </Grid>

          <Divider label="Anticipo" labelPosition="left" />

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

          {modalidadWatch === 'parcial' && (
            <Controller
              name="monto_calculado"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Monto de anticipo parcial (USD)"
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
