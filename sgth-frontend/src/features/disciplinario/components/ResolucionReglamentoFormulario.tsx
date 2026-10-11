'use client'

import React from 'react'
import { Alert, Grid, NumberInput, Select, Stack, Text, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import type { FaltaReglamentoInterno, Sumario } from '@/types/api'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { useFaltasReglamento } from '../hooks/useDisciplinario'
import {
  crearResolucionReglamentoSchema,
  type ResolucionReglamentoFormData,
} from '../schemas/resolucionReglamento.schema'
import { TIPO_SANCION_LABELS, citaDeLaFalta, nombreServidor, sancionesDeLaFalta } from '../utils/etiquetas'

interface Props {
  opened: boolean
  onClose: () => void
  sumario: Sumario
}

/**
 * La resolución del procedimiento del reglamento interno de un obrero (fase
 * 4.4): la falta probada —la imputada u otra del catálogo vigente— y la sanción
 * que el reglamento le da. La amonestación escrita y la multa dejan su hoja de
 * registro interno; la multa, además, va a Financiero.
 */
export function ResolucionReglamentoFormulario({ opened, onClose, sumario }: Props) {
  const contained = useContainedInput()
  const { resolverSumario } = useDisciplinarioMutations()
  const { data: vigentes = [] } = useFaltasReglamento(true)

  // La imputada, aunque se haya desactivado después: es la del procedimiento.
  const faltas = React.useMemo<FaltaReglamentoInterno[]>(() => {
    const imputada = sumario.falta_reglamento
    return imputada && !vigentes.some((f) => f.id === imputada.id) ? [imputada, ...vigentes] : vigentes
  }, [vigentes, sumario.falta_reglamento])

  const schema = React.useMemo(() => crearResolucionReglamentoSchema(faltas), [faltas])

  const {
    control, register, handleSubmit, setError, resetField, formState: { errors },
  } = useForm<ResolucionReglamentoFormData>({
    resolver: zodResolver(schema),
    defaultValues: {
      falta_reglamento_id: sumario.falta_reglamento_id ?? undefined,
      fecha_efectiva: fromDateValue(new Date()),
      observaciones: '',
    },
  })

  const faltaId = useWatch({ control, name: 'falta_reglamento_id' })
  const sancion = useWatch({ control, name: 'tipo_sancion' })
  const falta = faltas.find((f) => f.id === faltaId)
  const admitidas = sancionesDeLaFalta(falta)
  const tope = falta?.porcentaje_maximo_multa != null ? Number(falta.porcentaje_maximo_multa) : undefined

  const guardar = async (v: ResolucionReglamentoFormData) => {
    try {
      await resolverSumario.mutateAsync({
        id: sumario.id,
        data: {
          falta_reglamento_id: v.falta_reglamento_id,
          tipo_falta: null,
          tipo_sancion: v.tipo_sancion,
          porcentaje_multa: v.tipo_sancion === 'multa' ? v.porcentaje_multa ?? null : null,
          dias_suspension: null,
          fecha_efectiva: v.fecha_efectiva,
          observaciones: v.observaciones.trim() || null,
        },
      })
      onClose()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (['falta_reglamento_id', 'tipo_sancion', 'porcentaje_multa', 'fecha_efectiva', 'observaciones'].includes(campo)) {
          setError(campo as keyof ResolucionReglamentoFormData, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo resolver el procedimiento', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Resolver el procedimiento e imponer la sanción"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Resolver procedimiento"
      submitting={resolverSumario.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        <Text size="sm" c="dimmed">
          {nombreServidor(sumario.servidor)} — {sumario.motivo}
        </Text>

        <Grid>
          <Grid.Col span={12}>
            <Controller
              name="falta_reglamento_id"
              control={control}
              render={({ field }) => (
                <Select
                  label="Falta del reglamento interno"
                  required
                  searchable
                  description="La imputada, u otra del catálogo vigente si la resolución la cambia."
                  data={faltas.map((f) => ({ value: String(f.id), label: citaDeLaFalta(f) }))}
                  error={errors.falta_reglamento_id?.message}
                  {...contained}
                  value={field.value ? String(field.value) : null}
                  onChange={(v) => {
                    field.onChange(v ? Number(v) : undefined)
                    const nueva = faltas.find((f) => String(f.id) === v)
                    if (sancion && !sancionesDeLaFalta(nueva).includes(sancion)) resetField('tipo_sancion')
                  }}
                />
              )}
            />
          </Grid.Col>

          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="tipo_sancion"
              control={control}
              render={({ field }) => (
                <Select
                  label="Sanción que se impone"
                  required
                  description="Las que el reglamento da a la falta"
                  placeholder={falta ? 'Seleccione' : 'Elija primero la falta'}
                  disabled={!falta}
                  data={admitidas.map((s) => ({ value: s, label: TIPO_SANCION_LABELS[s] }))}
                  error={errors.tipo_sancion?.message}
                  {...contained}
                  value={field.value ?? null}
                  onChange={field.onChange}
                />
              )}
            />
          </Grid.Col>

          {sancion === 'multa' && (
            <Grid.Col span={{ base: 12, sm: 6 }}>
              <Controller
                name="porcentaje_multa"
                control={control}
                render={({ field }) => (
                  <NumberInput
                    label="Multa (% de la remuneración)"
                    required
                    description={tope != null ? `El reglamento admite hasta el ${tope} %` : undefined}
                    decimalScale={2}
                    min={0.01}
                    max={tope}
                    hideControls
                    error={errors.porcentaje_multa?.message}
                    {...contained}
                    value={field.value ?? ''}
                    onChange={(v) => field.onChange(typeof v === 'number' ? v : undefined)}
                  />
                )}
              />
            </Grid.Col>
          )}

          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="fecha_efectiva"
              control={control}
              render={({ field }) => (
                <DatePickerInput
                  label="Surte efecto desde"
                  required
                  valueFormat="DD/MM/YYYY"
                  error={errors.fecha_efectiva?.message}
                  {...contained}
                  value={toDateValue(field.value)}
                  onChange={(v) => field.onChange(fromDateValue(v))}
                />
              )}
            />
          </Grid.Col>

          <Grid.Col span={12}>
            <Textarea
              label="Observaciones"
              placeholder="Lo que conste en la resolución y convenga dejar en el expediente"
              rows={3}
              error={errors.observaciones?.message}
              {...contained}
              {...register('observaciones')}
            />
          </Grid.Col>
        </Grid>

        {(sancion === 'amonestacion_escrita' || sancion === 'multa') && (
          <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
            Se generará la hoja de registro interno en borrador, para que Talento
            Humano la registre.
            {sancion === 'multa' && ' Al registrarse, irá por correo al jefe de Gestión Financiera con el descuento referencial.'}
          </Alert>
        )}
      </Stack>
    </FormModal>
  )
}
