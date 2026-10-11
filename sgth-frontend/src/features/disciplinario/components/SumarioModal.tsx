'use client'

import { useState } from 'react'
import { Alert, Select, Stack, Textarea } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarServidorSelect } from '@/features/expediente/components/BuscarServidorSelect'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { useFaltasReglamento } from '../hooks/useDisciplinario'
import { sumarioSchema, type SumarioFormValues } from '../schemas/sumario.schema'
import { citaDeLaFalta } from '../utils/etiquetas'

interface Props {
  opened: boolean
  onClose: () => void
}

export function SumarioModal({ opened, onClose }: Props) {
  const contained = useContainedInput()
  const { crearSumario } = useDisciplinarioMutations()

  // Al obrero lo rige el Código del Trabajo: se le abre el procedimiento de su
  // reglamento interno, con la falta que se le imputa (fase 4.4).
  const [esObrero, setEsObrero] = useState(false)
  const { data: faltas = [] } = useFaltasReglamento(true)

  const {
    control,
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors },
  } = useForm<SumarioFormValues>({
    resolver: zodResolver(sumarioSchema),
    // `servidor_id` se omite: lo elige quien abre el sumario (regla 09).
    defaultValues: { motivo: '', fecha_apertura: fromDateValue(new Date()) },
  })

  const cerrar = () => {
    reset()
    setEsObrero(false)
    onClose()
  }

  const guardar = async (valores: SumarioFormValues) => {
    if (esObrero && !valores.falta_reglamento_id) {
      setError('falta_reglamento_id', { message: 'Indique la falta del reglamento interno que se imputa' })
      return
    }

    try {
      await crearSumario.mutateAsync({
        ...valores,
        falta_reglamento_id: esObrero ? valores.falta_reglamento_id ?? null : null,
      })
      cerrar()
    } catch (error) {
      const campos = erroresDeCampo(error)
      if (!campos) return // el hook ya lo notificó

      const sinCampo: string[] = []
      for (const [campo, mensaje] of Object.entries(campos)) {
        if (campo in sumarioSchema.shape) {
          setError(campo as keyof SumarioFormValues, { message: mensaje })
        } else {
          sinCampo.push(mensaje)
        }
      }

      if (sinCampo.length) {
        notificar.error('No se pudo abrir el sumario', sinCampo.join(' '))
      }
    }
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title={esObrero ? 'Abrir procedimiento del reglamento interno' : 'Abrir sumario administrativo'}
      onSubmit={handleSubmit(guardar)}
      submitLabel={esObrero ? 'Abrir procedimiento' : 'Abrir sumario'}
      submitting={crearSumario.isPending}
      closeOnClickOutside={false}
    >
      <Stack gap="sm">
        <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
          {esObrero
            ? 'Es obrero: se le sanciona con su reglamento interno, por una falta de su catálogo, '
              + 'con amonestación o multa. Si la falta es grave, lo que corresponde es un visto bueno '
              + 'ante el Inspector del Trabajo.'
            : 'El sumario administrativo es el procedimiento de la LOSEP. A un obrero se le abre el '
              + 'procedimiento de su reglamento interno.'}
        </Alert>

        <Controller
          name="servidor_id"
          control={control}
          render={({ field }) => (
            <BuscarServidorSelect
              label="Servidor sumariado"
              required
              error={errors.servidor_id?.message}
              value={field.value ?? null}
              onChange={(id) => {
                field.onChange(id)
                if (!id) setEsObrero(false)
              }}
              onSelect={(s) => {
                setEsObrero(s.contrato_vigente?.tipo_nombramiento === 'codigo_trabajo')
              }}
            />
          )}
        />

        {esObrero && (
          <Controller
            name="falta_reglamento_id"
            control={control}
            render={({ field }) => (
              <Select
                label="Falta del reglamento interno que se imputa"
                required
                searchable
                placeholder={faltas.length ? 'Seleccione' : 'El reglamento interno no tiene faltas cargadas'}
                disabled={!faltas.length}
                data={faltas.map((f) => ({ value: String(f.id), label: citaDeLaFalta(f) }))}
                error={errors.falta_reglamento_id?.message}
                {...contained}
                value={field.value ? String(field.value) : null}
                onChange={(v) => field.onChange(v ? Number(v) : null)}
              />
            )}
          />
        )}

        <Controller
          name="fecha_apertura"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Fecha de apertura"
              valueFormat="DD/MM/YYYY"
              error={errors.fecha_apertura?.message}
              {...contained}
              value={toDateValue(field.value)}
              onChange={(v) => field.onChange(fromDateValue(v))}
            />
          )}
        />

        <Textarea
          label="Motivo"
          required
          placeholder="Describa los hechos que motivan la apertura del sumario"
          rows={4}
          error={errors.motivo?.message}
          {...contained}
          {...register('motivo')}
        />
      </Stack>
    </FormModal>
  )
}
