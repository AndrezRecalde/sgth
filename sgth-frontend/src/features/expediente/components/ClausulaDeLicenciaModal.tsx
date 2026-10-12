'use client'

import { Grid, Input, NumberInput, SegmentedControl, Select, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useCausasLicencia } from '@/features/asistencia/hooks/useLicencias'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { useGuardarClausulaDeLicencia } from '../hooks/useContratoColectivo'
import { clausulaDeLicenciaSchema, type ClausulaDeLicenciaFormData } from '../schemas/clausulaDeLicencia.schema'
import type { ClausulaDeLicencia, ContratoColectivo } from '../services/contratoColectivoService'

const CAMPOS = ['clausula', 'causa', 'alcance', 'nombre', 'dias', 'dias_por_anio', 'observacion'] as const

const CLASES = [
  { value: 'amplia', label: 'Amplía una causa de la ley' },
  { value: 'nueva', label: 'Causa que la ley no tiene' },
]

interface Props {
  opened: boolean
  contrato: ContratoColectivo
  /** La que se corrige; sin ella, una nueva. */
  clausula: ClausulaDeLicencia | null
  onClose: () => void
}

/**
 * Una cláusula de licencia del contrato colectivo (fase 5.3). La que amplía
 * una causa de la ley tiene que dar más días que la ley: los derechos del
 * trabajador son irrenunciables. La causa nueva lleva el nombre que le da el
 * contrato. Los días son por licencia; el tope por año, si la cláusula lo pone.
 */
export function ClausulaDeLicenciaModal({ opened, contrato, clausula, onClose }: Props) {
  return opened
    ? <Formulario key={clausula?.id ?? 'nueva'} contrato={contrato} clausula={clausula} onClose={onClose} />
    : null
}

function Formulario({ contrato, clausula, onClose }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const guardar = useGuardarClausulaDeLicencia()

  // Las de la ley que tienen días que ampliar: la pactada no, y la catastrófica
  // tampoco, que dura lo que diga el certificado.
  const causas = (useCausasLicencia().data ?? [])
    .filter((c) => c.codigo !== 'pactada' && (c.tope != null || c.alcances.length > 0))

  const {
    control, register, handleSubmit, setValue, setError, formState: { errors },
  } = useForm<ClausulaDeLicenciaFormData>({
    resolver: zodResolver(clausulaDeLicenciaSchema),
    defaultValues: {
      clase: clausula && !clausula.causa ? 'nueva' : 'amplia',
      clausula: clausula?.clausula ?? '',
      causa: clausula?.causa ?? null,
      alcance: clausula?.alcance ?? null,
      nombre: clausula?.nombre ?? '',
      dias: clausula?.dias,
      dias_por_anio: clausula?.dias_por_anio ?? null,
      observacion: clausula?.observacion ?? '',
    },
  })

  const [clase, codigoCausa, alcance] = useWatch({ control, name: ['clase', 'causa', 'alcance'] })
  const causa = causas.find((c) => c.codigo === codigoCausa)
  const caso = alcance ? causa?.alcances.find((a) => a.codigo === alcance) : undefined
  const topeLegal = caso ? caso.tope : causa?.tope ?? null

  const enviar = async (v: ClausulaDeLicenciaFormData) => {
    const amplia = v.clase === 'amplia'

    try {
      await guardar.mutateAsync({
        contratoId: contrato.id,
        id: clausula?.id,
        data: {
          clausula: v.clausula.trim(),
          causa: amplia ? v.causa : null,
          alcance: amplia ? v.alcance || null : null,
          nombre: amplia ? null : v.nombre?.trim() || null,
          dias: v.dias,
          dias_por_anio: v.dias_por_anio ?? null,
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
      title={clausula ? 'Corregir cláusula de licencia' : 'Agregar cláusula de licencia'}
      onSubmit={handleSubmit(enviar)}
      submitLabel={clausula ? 'Guardar cambios' : 'Agregar cláusula'}
      submitting={guardar.isPending}
      size="lg"
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          Contrato colectivo N.º {contrato.numero}. Vale para los obreros mientras rija con el
          dictamen del Ministerio de Finanzas.
        </Text>

        {/* Sin el patrón contained: un SegmentedControl no tiene casilla donde
            dibujar la etiqueta. */}
        <Input.Wrapper label="Qué hace la cláusula">
          <Controller
            name="clase"
            control={control}
            render={({ field }) => (
              <SegmentedControl
                data={CLASES}
                fullWidth
                value={field.value}
                onChange={(v) => {
                  field.onChange(v)
                  setValue('causa', null)
                  setValue('alcance', null)
                  setValue('nombre', '')
                }}
              />
            )}
          />
        </Input.Wrapper>

        <Grid>
          <Grid.Col span={{ base: 12, sm: 4 }}>
            <TextInput
              label="Cláusula"
              placeholder="Cláusula 18"
              withAsterisk
              error={errors.clausula?.message}
              {...contained}
              {...register('clausula')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 8 }}>
            {clase === 'amplia' ? (
              <Controller
                name="causa"
                control={control}
                render={({ field }) => (
                  <Select
                    label="Causa de la ley que amplía"
                    placeholder="Seleccione la causa"
                    data={causas.map((c) => ({ value: c.codigo, label: c.etiqueta }))}
                    searchable
                    withAsterisk
                    error={errors.causa?.message}
                    {...contained}
                    value={field.value ?? null}
                    onChange={(v) => {
                      field.onChange(v)
                      setValue('alcance', null)
                    }}
                  />
                )}
              />
            ) : (
              <TextInput
                label="Nombre de la licencia"
                placeholder="Como la nombra el contrato"
                withAsterisk
                error={errors.nombre?.message}
                {...contained}
                {...register('nombre')}
              />
            )}
          </Grid.Col>
        </Grid>

        {clase === 'amplia' && !!causa?.alcances.length && (
          <Controller
            name="alcance"
            control={control}
            render={({ field }) => (
              <Select
                label={causa.exige_alcance ? 'De quién' : 'Caso'}
                placeholder={causa.exige_alcance ? 'Seleccione' : 'Toda la causa'}
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
        )}

        {clase === 'amplia' && topeLegal != null && (
          <Text size="xs" c="dimmed">
            La ley da {topeLegal} día(s) ({caso?.base_legal ?? causa?.base_legal}): la cláusula tiene que dar más.
          </Text>
        )}

        <Grid>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="dias"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Días por licencia"
                  withAsterisk
                  min={1}
                  max={365}
                  allowDecimal={false}
                  hideControls
                  error={errors.dias?.message}
                  {...contained}
                  value={typeof field.value === 'number' ? field.value : ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="dias_por_anio"
              control={control}
              render={({ field }) => (
                <NumberInput
                  label="Días por año"
                  placeholder="Sin tope por año"
                  min={1}
                  max={366}
                  allowDecimal={false}
                  hideControls
                  error={errors.dias_por_anio?.message}
                  {...contained}
                  value={typeof field.value === 'number' ? field.value : ''}
                  onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
                />
              )}
            />
          </Grid.Col>
        </Grid>

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
