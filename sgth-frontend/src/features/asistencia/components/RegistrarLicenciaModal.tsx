'use client'

import { Alert, FileInput, Grid, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useCausasLicencia, useRegistrarLicencia } from '../hooks/useLicencias'
import type { CausaLicencia } from '../services/licenciaService'
import { diasDeLicencia, licenciaSchema, type LicenciaFormData } from './licencia.schema'

const CAMPOS = ['servidor_id', 'causa', 'alcance', 'fecha_inicio', 'fecha_fin', 'observacion', 'archivo'] as const

/**
 * Registrar una licencia con remuneración (fase 5.1): de quién, por qué, qué
 * días y el respaldo en PDF. Queda pendiente; la aprueba quien aprueba esa
 * causa, y entonces se registra en Sirha7. No descuenta vacaciones.
 */
export function RegistrarLicenciaModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  // Se monta al abrir: arranca en blanco sin resetear desde un efecto.
  return opened ? <Formulario onClose={onClose} /> : null
}

function Formulario({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const causas = useCausasLicencia().data ?? []
  const registrar = useRegistrarLicencia()

  const {
    control, register, handleSubmit, setValue, setError, formState: { errors },
  } = useForm<LicenciaFormData>({
    resolver: zodResolver(licenciaSchema),
    defaultValues: {
      servidor_id: 0, causa: '', alcance: null, fecha_inicio: '', fecha_fin: '', observacion: '',
    },
  })

  const [codigoCausa, alcance, desde, hasta] = useWatch({ control, name: ['causa', 'alcance', 'fecha_inicio', 'fecha_fin'] })
  const causa = causas.find((c) => c.codigo === codigoCausa)
  const tope = causa
    ? (alcance ? causa.alcances.find((a) => a.codigo === alcance)?.tope ?? causa.tope : causa.tope)
    : null
  const dias = diasDeLicencia(desde, hasta)
  const pasaDelTope = tope != null && dias != null && dias > tope

  const enviar = async (v: LicenciaFormData) => {
    try {
      await registrar.mutateAsync({
        servidor_id: v.servidor_id,
        causa: v.causa as CausaLicencia,
        alcance: v.alcance || null,
        fecha_inicio: v.fecha_inicio,
        fecha_fin: v.fecha_fin,
        observacion: v.observacion?.trim() || null,
        archivo: v.archivo,
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      for (const campo of CAMPOS) {
        if (campos?.[campo]) setError(campo, { type: 'server', message: campos[campo] })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Registrar licencia con remuneración"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Registrar licencia"
      submitting={registrar.isPending}
      submitDisabled={pasaDelTope}
      size="lg"
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          No es una acción de personal ni descuenta vacaciones. Queda pendiente: la aprueba
          Trabajo Social si es de salud o calamidad, y Talento Humano las demás; al aprobarla
          se registra en Sirha7. La licencia sin remuneración es una acción de personal.
        </Alert>

        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Servidor"
              required
              value={field.value || null}
              onChange={(id) => field.onChange(id ?? 0)}
              error={errors.servidor_id?.message}
            />
          )}
        />

        <Grid>
          <Grid.Col span={{ base: 12, sm: causa?.alcances.length ? 6 : 12 }}>
            <Controller
              name="causa"
              control={control}
              render={({ field }) => (
                <Select
                  label="Causa"
                  placeholder="Seleccione la causa"
                  data={causas.map((c) => ({ value: c.codigo, label: c.etiqueta }))}
                  searchable
                  withAsterisk
                  error={errors.causa?.message}
                  {...contained}
                  value={field.value || null}
                  onChange={(v) => {
                    field.onChange(v ?? '')
                    setValue('alcance', null)
                  }}
                />
              )}
            />
          </Grid.Col>
          {!!causa?.alcances.length && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="alcance"
                control={control}
                render={({ field }) => (
                  <Select
                    label={causa.exige_alcance ? 'De quién' : 'Caso'}
                    placeholder={causa.exige_alcance ? 'Seleccione' : 'Ninguno'}
                    data={causa.alcances.map((a) => ({ value: a.codigo, label: a.etiqueta }))}
                    clearable={!causa.exige_alcance}
                    withAsterisk={causa.exige_alcance}
                    error={errors.alcance?.message}
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => field.onChange(v)}
                  />
                )}
              />
            </Grid.Col>
          )}
        </Grid>

        {causa && (
          <Text size="xs" c="dimmed">
            {causa.explicacion} ({causa.base_legal})
          </Text>
        )}

        <Grid>
          {(['fecha_inicio', 'fecha_fin'] as const).map((nombre) => (
            <Grid.Col key={nombre} span={{ base: 12, sm: 6 }}>
              <Controller
                name={nombre}
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label={nombre === 'fecha_inicio' ? 'Desde' : 'Hasta'}
                    placeholder="Seleccionar fecha"
                    valueFormat="DD/MM/YYYY"
                    withAsterisk
                    popoverProps={{ withinPortal: true, zIndex: 1100 }}
                    error={errors[nombre]?.message}
                    {...contained}
                    value={toDateValue(field.value)}
                    onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
                  />
                )}
              />
            </Grid.Col>
          ))}
        </Grid>

        {dias != null && (
          pasaDelTope ? (
            <Alert color={SEMANTIC_COLOR.danger} variant="light" icon={<IconAlertTriangle size={16} />}>
              <Text size="sm">
                Son {dias} días calendario, y la licencia por {causa?.etiqueta.toLowerCase()} admite hasta {tope}.
              </Text>
            </Alert>
          ) : (
            <Text size="sm">
              <b>{dias}</b> día(s) calendario{tope != null ? `, de ${tope} que admite` : ''}.
            </Text>
          )
        )}

        <Controller
          name="archivo"
          control={control}
          render={({ field }) => (
            <FileInput
              label="Respaldo (PDF)"
              placeholder="El certificado, la partida o el acta"
              accept="application/pdf"
              withAsterisk
              clearable
              error={errors.archivo?.message}
              {...contained}
              value={field.value ?? null}
              onChange={field.onChange}
            />
          )}
        />

        <Textarea
          label="Observación"
          placeholder="Opcional"
          autosize
          minRows={2}
          error={errors.observacion?.message}
          {...contained}
          {...register('observacion')}
        />
      </Stack>
    </FormModal>
  )
}
