'use client'

import { Alert, FileInput, Grid, Input, SegmentedControl, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { usePresentarSolicitud } from '../hooks/useMisSolicitudes'
import { solicitudAccionSchema, type SolicitudAccionFormData } from '../schemas/solicitudAccion.schema'

const CAMPOS = ['tipo', 'causal', 'fecha_desde', 'fecha_hasta', 'motivo', 'archivo'] as const

const TIPOS = [
  { value: 'renuncia', label: 'Renuncia' },
  { value: 'licencia_sin_remuneracion', label: 'Licencia sin remuneración' },
  { value: 'jubilacion', label: 'Jubilación' },
]

/**
 * Las causales de la licencia sin remuneración (LOSEP, Art. 28). Aquí y no
 * del catálogo, que es de Talento Humano; el backend las valida igual.
 */
const CAUSALES = [
  { value: 'asuntos_particulares', label: 'Asuntos particulares (Art. 28 a)' },
  { value: 'estudios_posgrado', label: 'Estudios de posgrado (Art. 28 b)' },
  { value: 'servicio_militar', label: 'Servicio militar (Art. 28 c)' },
  { value: 'reemplazo_dignatario', label: 'Reemplazo de un dignatario electo (Art. 28 d)' },
  { value: 'candidatura', label: 'Candidatura a elección popular (Art. 28 e)' },
  { value: 'cuidado_hijos', label: 'Cuidado de hijos (Art. 28 f)' },
]

const AYUDA: Record<SolicitudAccionFormData['tipo'], { fecha: string; respaldo: string; aviso: string }> = {
  renuncia: {
    fecha: 'Deja el puesto a partir del',
    respaldo: 'La carta de renuncia firmada',
    aviso: 'La renuncia se presenta con 15 días de anticipación. Si Talento Humano no responde en 15 días, '
      + 'se tiene por aceptada (Reglamento a la LOSEP, Art. 102).',
  },
  licencia_sin_remuneracion: {
    fecha: 'Desde',
    respaldo: 'El documento que respalda la causal',
    aviso: 'Es solo para nombramientos permanentes. Los días que admite cada causal los revisa Talento Humano.',
  },
  jubilacion: {
    fecha: 'Se jubila a partir del',
    respaldo: 'El certificado del IESS',
    aviso: 'Adjunte el certificado del IESS que acredita su derecho a la jubilación.',
  },
}

const enDias = (dias: number) => {
  const d = new Date()
  d.setHours(0, 0, 0, 0)
  d.setDate(d.getDate() + dias)

  return d
}

/**
 * Una solicitud desde el portal (fase 6.3b): renuncia, licencia sin
 * remuneración o jubilación, con su respaldo. Queda pendiente hasta que
 * Talento Humano prepara la acción o la rechaza.
 */
export function NuevaSolicitudModal({ opened, onClose }: { opened: boolean; onClose: () => void }) {
  return opened ? <Formulario onClose={onClose} /> : null
}

function Formulario({ onClose }: { onClose: () => void }) {
  const contained = useContainedInput()
  const presentar = usePresentarSolicitud()

  const { control, register, handleSubmit, setValue, setError, formState: { errors } } = useForm<SolicitudAccionFormData>({
    resolver: zodResolver(solicitudAccionSchema),
    defaultValues: { tipo: 'renuncia', causal: null, fecha_desde: '', fecha_hasta: null, motivo: '' },
  })
  const tipo = useWatch({ control, name: 'tipo' })
  const ayuda = AYUDA[tipo]
  const esLicencia = tipo === 'licencia_sin_remuneracion'

  const enviar = async (v: SolicitudAccionFormData) => {
    try {
      await presentar.mutateAsync({
        tipo: v.tipo,
        causal: esLicencia ? v.causal : null,
        fecha_desde: v.fecha_desde,
        fecha_hasta: esLicencia ? v.fecha_hasta : null,
        motivo: v.motivo?.trim() || null,
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
      title="Nueva solicitud"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Presentar solicitud"
      submitting={presentar.isPending}
      size="lg"
    >
      <Stack gap="sm">
        {/* Sin el patrón contained: un SegmentedControl no tiene casilla. */}
        <Input.Wrapper label="Qué solicita">
          <Controller
            name="tipo"
            control={control}
            render={({ field }) => (
              <SegmentedControl
                data={TIPOS}
                fullWidth
                value={field.value}
                onChange={(v) => {
                  field.onChange(v)
                  setValue('causal', null)
                  setValue('fecha_desde', '')
                  setValue('fecha_hasta', null)
                }}
              />
            )}
          />
        </Input.Wrapper>

        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          <Text size="sm">{ayuda.aviso}</Text>
        </Alert>

        {esLicencia && (
          <Controller
            name="causal"
            control={control}
            render={({ field }) => (
              <Select
                label="Causal"
                placeholder="Seleccione la causal"
                data={CAUSALES}
                withAsterisk
                error={errors.causal?.message}
                {...contained}
                value={field.value ?? null}
                onChange={(v) => field.onChange(v)}
              />
            )}
          />
        )}

        <Grid>
          <Grid.Col span={{ base: 12, sm: esLicencia ? 6 : 12 }}>
            <Controller
              name="fecha_desde"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label={ayuda.fecha}
                  placeholder="Seleccionar fecha"
                  valueFormat="DD/MM/YYYY"
                  withAsterisk
                  minDate={tipo === 'renuncia' ? enDias(15) : enDias(0)}
                  error={errors.fecha_desde?.message}
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
                />
              )}
            />
          </Grid.Col>
          {esLicencia && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="fecha_hasta"
                control={control}
                render={({ field }) => (
                  <DatePickerInput
                    label="Hasta"
                    placeholder="Seleccionar fecha"
                    valueFormat="DD/MM/YYYY"
                    withAsterisk
                    minDate={enDias(0)}
                    error={errors.fecha_hasta?.message}
                    {...contained}
                    value={toDateValue(field.value)}
                    onChange={(d) => field.onChange(fromDateValueOrNull(d))}
                  />
                )}
              />
            </Grid.Col>
          )}
        </Grid>

        <Controller
          name="archivo"
          control={control}
          render={({ field }) => (
            <FileInput
              label="Respaldo (PDF)"
              placeholder={ayuda.respaldo}
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
          label="Motivo"
          placeholder="Opcional"
          autosize
          minRows={2}
          error={errors.motivo?.message}
          {...contained}
          {...register('motivo')}
        />
      </Stack>
    </FormModal>
  )
}
