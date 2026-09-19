'use client'

import { useState } from 'react'
import { Grid, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { guardarArchivo } from '@/lib/archivo'
import { fromDateValue, toDateValue } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { declaracionService } from '../services/declaracionService'
import {
  exportarDeclaracionesSchema,
  type ExportarDeclaracionesFormData,
} from '../schemas/declaracion.schema'

const FORMATO_OPTIONS = [
  { value: 'txt', label: 'Archivo para la Contraloría (TXT)' },
  { value: 'pdf', label: 'Reporte legible (PDF)' },
]

interface Props {
  opened: boolean
  onClose: () => void
  servidorId: number
  cedula?: string | null
}

/**
 * «Exportar todas» llamaba al backend sin el rango ni el formato que exige,
 * recibía un 422 y no hacía nada visible. El rango se pide aquí.
 */
export function ExportarDeclaracionesModal({ opened, onClose, servidorId, cedula }: Props) {
  const contained = useContainedInput()
  const [exportando, setExportando] = useState(false)

  const { control, handleSubmit, formState: { errors } } = useForm<ExportarDeclaracionesFormData>({
    resolver: zodResolver(exportarDeclaracionesSchema),
    defaultValues: { fecha_inicio: '', fecha_fin: '', formato: 'txt' },
  })

  const exportar = async (params: ExportarDeclaracionesFormData) => {
    setExportando(true)
    try {
      const archivo = await declaracionService.exportar(servidorId, params)
      if (!archivo) {
        notificar.aviso('Sin declaraciones en ese rango', 'Amplíe las fechas e inténtelo de nuevo.')
        return
      }
      guardarArchivo(
        archivo,
        `declaraciones_${cedula ?? servidorId}_${params.fecha_inicio}_${params.fecha_fin}.${params.formato}`,
      )
      onClose()
    } catch (error) {
      notificar.error('No se pudieron exportar las declaraciones', getApiErrorMessage(error))
    } finally {
      setExportando(false)
    }
  }

  const campoFecha = (name: 'fecha_inicio' | 'fecha_fin', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          {...contained}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValue(d))}
          error={errors[name]?.message}
        />
      )}
    />
  )

  return (
    <FormModal
      opened={opened}
      onClose={onClose}
      title="Exportar declaraciones"
      size="md"
      onSubmit={handleSubmit(exportar)}
      submitLabel="Exportar"
      submitting={exportando}
    >
      <Grid>
        <Grid.Col span={{ base: 12, sm: 6 }}>{campoFecha('fecha_inicio', 'Desde')}</Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>{campoFecha('fecha_fin', 'Hasta')}</Grid.Col>
        <Grid.Col span={12}>
          <Controller
            name="formato"
            control={control}
            render={({ field }) => (
              <Select
                label="Formato"
                data={FORMATO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? 'txt')}
              />
            )}
          />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
