'use client'

import { Alert, FileInput, Grid, NumberInput, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, type Control, type FieldPath } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useGuardarContratoColectivo } from '../hooks/useContratoColectivo'
import { contratoColectivoSchema, type ContratoColectivoFormData } from '../schemas/contratoColectivo.schema'
import type { ContratoColectivo } from '../services/contratoColectivoService'

interface Props {
  opened: boolean
  /** El que se corrige; sin él, uno nuevo. */
  contrato: ContratoColectivo | null
  onClose: () => void
}

const CAMPOS = [
  'numero', 'fecha_suscripcion', 'fecha_registro_mdt', 'vigencia_desde', 'vigencia_hasta',
  'dictamen_finanzas_numero', 'dictamen_finanzas_fecha', 'dias_adicionales_vacaciones',
  'bonificacion_jubilacion_sbu', 'bonificacion_retiro_sbu', 'incremento_pactado_porcentaje', 'observacion', 'archivo',
] as const

const numero = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? null : Number(v))

/**
 * El contrato colectivo de los obreros (fase 4.6). Sus cláusulas solo se
 * aplican con el dictamen del Ministerio de Finanzas; sin él, el contrato se
 * tiene por inexistente.
 */
export function ContratoColectivoModal({ opened, contrato, onClose }: Props) {
  // Se monta al abrir: arranca con el contrato elegido sin resetear el
  // formulario desde un efecto.
  return opened ? <Formulario key={contrato?.id ?? 'nuevo'} contrato={contrato} onClose={onClose} /> : null
}

function Formulario({ contrato, onClose }: { contrato: ContratoColectivo | null; onClose: () => void }) {
  const contained = useContainedInput()
  const guardar = useGuardarContratoColectivo()
  const soloFecha = (v?: string | null) => v?.split('T')[0] ?? null

  const {
    control, register, handleSubmit, setError, formState: { errors },
  } = useForm<ContratoColectivoFormData>({
    resolver: zodResolver(contratoColectivoSchema),
    defaultValues: {
      numero: contrato?.numero ?? '',
      fecha_suscripcion: soloFecha(contrato?.fecha_suscripcion),
      fecha_registro_mdt: soloFecha(contrato?.fecha_registro_mdt),
      vigencia_desde: soloFecha(contrato?.vigencia_desde) ?? '',
      vigencia_hasta: soloFecha(contrato?.vigencia_hasta),
      dictamen_finanzas_numero: contrato?.dictamen_finanzas_numero ?? '',
      dictamen_finanzas_fecha: soloFecha(contrato?.dictamen_finanzas_fecha),
      dias_adicionales_vacaciones: contrato?.dias_adicionales_vacaciones ?? 0,
      bonificacion_jubilacion_sbu: numero(contrato?.bonificacion_jubilacion_sbu),
      bonificacion_retiro_sbu: numero(contrato?.bonificacion_retiro_sbu),
      incremento_pactado_porcentaje: numero(contrato?.incremento_pactado_porcentaje),
      observacion: contrato?.observacion ?? '',
      archivo: null,
    },
  })

  const enviar = async (v: ContratoColectivoFormData) => {
    try {
      await guardar.mutateAsync({
        id: contrato?.id,
        data: {
          ...v,
          dictamen_finanzas_numero: v.dictamen_finanzas_numero?.trim() || null,
          observacion: v.observacion?.trim() || null,
        },
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
      title={contrato ? 'Corregir contrato colectivo' : 'Registrar contrato colectivo'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={contrato ? 'Guardar cambios' : 'Registrar contrato'}
      submitting={guardar.isPending}
      size="lg"
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          Sin el dictamen del Ministerio de Finanzas, el contrato colectivo se tiene por
          inexistente (Acuerdo MDT-2024-080): se guarda, pero sus cláusulas no se aplican.
          Las cláusulas vacías dejan regir la ley sola.
        </Alert>

        <SectionHeading title="El documento" mb={0} />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Contrato colectivo N.º"
              withAsterisk
              error={errors.numero?.message}
              {...contained}
              {...register('numero')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="archivo"
              control={control}
              render={({ field }) => (
                <FileInput
                  label="PDF del contrato"
                  placeholder={contrato?.tiene_documento ? 'Reemplazar el adjunto' : 'Opcional'}
                  accept="application/pdf"
                  clearable
                  error={errors.archivo?.message}
                  {...contained}
                  value={field.value ?? null}
                  onChange={field.onChange}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Fecha control={control} name="fecha_suscripcion" label="Suscrito el" error={errors.fecha_suscripcion?.message} pasada />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Fecha control={control} name="fecha_registro_mdt" label="Registrado en el Ministerio del Trabajo el" error={errors.fecha_registro_mdt?.message} pasada />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Fecha control={control} name="vigencia_desde" label="Rige desde" error={errors.vigencia_desde?.message} obligatoria />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Fecha control={control} name="vigencia_hasta" label="Hasta" error={errors.vigencia_hasta?.message} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Dictamen del Ministerio de Finanzas N.º"
              placeholder="Sin él, sus cláusulas no se aplican"
              error={errors.dictamen_finanzas_numero?.message}
              {...contained}
              {...register('dictamen_finanzas_numero')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Fecha control={control} name="dictamen_finanzas_fecha" label="Fecha del dictamen" error={errors.dictamen_finanzas_fecha?.message} pasada />
          </Grid.Col>
        </Grid>

        <SectionHeading title="Las cláusulas que cambian cálculos" mb={0} />
        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Numero
              control={control}
              name="dias_adicionales_vacaciones"
              label="Días adicionales de vacaciones"
              description="Sobre los del Código del Trabajo, Art. 69."
              error={errors.dias_adicionales_vacaciones?.message}
              decimales={0}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Numero
              control={control}
              name="incremento_pactado_porcentaje"
              label="Incremento pactado (%)"
              description="Lo propone el incremento en lote."
              error={errors.incremento_pactado_porcentaje?.message}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Numero
              control={control}
              name="bonificacion_jubilacion_sbu"
              label="Bonificación por jubilación"
              description="Salarios básicos por año de servicio, hasta 7 (Mandato 2)."
              error={errors.bonificacion_jubilacion_sbu?.message}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Numero
              control={control}
              name="bonificacion_retiro_sbu"
              label="Bonificación por retiro voluntario"
              description="Salarios básicos por año de servicio, hasta 7 (Mandato 2)."
              error={errors.bonificacion_retiro_sbu?.message}
            />
          </Grid.Col>
        </Grid>

        <Textarea
          label="Observación"
          placeholder="Opcional: las demás cláusulas, que el sistema no calcula"
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

function Fecha({
  control, name, label, error, obligatoria = false, pasada = false,
}: {
  control: Control<ContratoColectivoFormData>
  name: FieldPath<ContratoColectivoFormData>
  label: string
  error?: string
  obligatoria?: boolean
  /** Lo ya ocurrido no puede tener fecha futura. */
  pasada?: boolean
}) {
  const contained = useContainedInput()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          withAsterisk={obligatoria}
          clearable={!obligatoria}
          maxDate={pasada ? new Date() : undefined}
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          error={error}
          {...contained}
          value={toDateValue(typeof field.value === 'string' ? field.value : null)}
          onChange={(d) => field.onChange(fromDateValueOrNull(d))}
        />
      )}
    />
  )
}

function Numero({
  control, name, label, description, error, decimales = 2,
}: {
  control: Control<ContratoColectivoFormData>
  name: FieldPath<ContratoColectivoFormData>
  label: string
  description: string
  error?: string
  decimales?: number
}) {
  const contained = useContainedInput()

  return (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <NumberInput
          label={label}
          description={description}
          placeholder="Sin cláusula"
          min={0}
          decimalScale={decimales}
          hideControls
          error={error}
          {...contained}
          value={typeof field.value === 'number' ? field.value : ''}
          onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
        />
      )}
    />
  )
}
