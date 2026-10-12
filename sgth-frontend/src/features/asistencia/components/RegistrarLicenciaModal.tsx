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
import { useCausasDelServidor, useRegistrarLicencia } from '../hooks/useLicencias'
import type { CausaDelCatalogo, CausaLicencia } from '../services/licenciaService'
import { diasDeLicencia, licenciaSchema, type LicenciaFormData } from './licencia.schema'

const CAMPOS = ['servidor_id', 'causa', 'alcance', 'fecha_inicio', 'fecha_fin', 'observacion', 'archivo'] as const

/**
 * El valor de la causa en el selector. Las pactadas comparten código y se
 * distinguen por su cláusula (fase 5.3).
 */
const claveDe = (c: CausaDelCatalogo) => (c.clausula_id ? `${c.codigo}:${c.clausula_id}` : c.codigo)

/**
 * Registrar una licencia con remuneración (fase 5.1): de quién, por qué, qué
 * días y el respaldo en PDF. Queda pendiente; la aprueba quien aprueba esa
 * causa, y entonces se registra en Sirha7. No descuenta vacaciones.
 *
 * Las causas dependen del servidor: a un obrero, su contrato colectivo puede
 * ampliarle los días o darle causas pactadas (fase 5.3). Por eso se elige
 * primero a quién.
 */
export function RegistrarLicenciaModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  // Se monta al abrir: arranca en blanco sin resetear desde un efecto.
  return opened ? <Formulario onClose={onClose} /> : null
}

function Formulario({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const registrar = useRegistrarLicencia()

  const {
    control, register, handleSubmit, setValue, setError, formState: { errors },
  } = useForm<LicenciaFormData>({
    resolver: zodResolver(licenciaSchema),
    defaultValues: {
      servidor_id: 0, causa: '', alcance: null, fecha_inicio: '', fecha_fin: '', observacion: '',
    },
  })

  const [servidorId, claveCausa, alcance, desde, hasta] = useWatch({
    control, name: ['servidor_id', 'causa', 'alcance', 'fecha_inicio', 'fecha_fin'],
  })
  const catalogo = useCausasDelServidor(servidorId || null, desde)
  const causas = catalogo.data?.causas ?? []
  const contrato = catalogo.data?.contrato

  const causa = causas.find((c) => claveDe(c) === claveCausa)
  const caso = alcance ? causa?.alcances.find((a) => a.codigo === alcance) : undefined
  const tope = caso ? caso.tope : causa?.tope ?? null
  const topeAnual = caso ? caso.tope_anual : causa?.tope_anual ?? null
  const dias = diasDeLicencia(desde, hasta)
  const pasaDelTope = tope != null && dias != null && dias > tope

  const enviar = async (v: LicenciaFormData) => {
    try {
      await registrar.mutateAsync({
        servidor_id: v.servidor_id,
        causa: (causa?.codigo ?? v.causa) as CausaLicencia,
        alcance: v.alcance || null,
        clausula_de_licencia_id: causa?.clausula_id ?? null,
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
              onChange={(id) => {
                // Otro servidor, otras causas: la elegida puede no ser suya.
                field.onChange(id ?? 0)
                setValue('causa', '')
                setValue('alcance', null)
              }}
              error={errors.servidor_id?.message}
            />
          )}
        />

        {contrato?.aviso && (
          <Alert variant="light" color={SEMANTIC_COLOR.warning} icon={<IconAlertTriangle size={16} />}>
            <Text size="sm">{contrato.aviso}</Text>
          </Alert>
        )}
        {contrato?.contrato_colectivo && (
          <Text size="xs" c="dimmed">
            Es obrero: los topes incluyen lo que amplía el contrato colectivo
            N.º {contrato.contrato_colectivo.numero}, y sus causas pactadas están en la lista.
          </Text>
        )}

        <Grid>
          <Grid.Col span={{ base: 12, sm: causa?.alcances.length ? 6 : 12 }}>
            <Controller
              name="causa"
              control={control}
              render={({ field }) => (
                <Select
                  label="Causa"
                  placeholder={servidorId ? 'Seleccione la causa' : 'Elija primero al servidor'}
                  disabled={!servidorId}
                  data={causas.map((c) => ({ value: claveDe(c), label: c.etiqueta }))}
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
            {causa.explicacion} ({caso?.base_legal ?? causa.base_legal})
            {topeAnual != null && ` Hasta ${topeAnual} día(s) al año por esa cláusula.`}
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
