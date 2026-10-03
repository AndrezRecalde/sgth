'use client'

import { Alert, Select, Text } from '@mantine/core'
import { Controller, type Control, type FieldErrors } from 'react-hook-form'
import { IconInfoCircle } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { usePersonalDisponible } from '../hooks/useAgenda'
import type { AgendaFormData } from '../schemas/agenda.schema'

interface Props {
  control:      Control<AgendaFormData>
  errors:       FieldErrors<AgendaFormData>
  tipoAtencion: AgendaFormData['tipo_atencion']
}

/** A quién va el turno, con el aviso de cuando nadie se marcó disponible. */
export function SelectorProfesional({ control, errors, tipoAtencion }: Props) {
  const contained = useContainedInput()
  const { data, isLoading } = usePersonalDisponible(tipoAtencion)

  const opciones = (data?.personal ?? []).map((p) => ({
    value: String(p.id),
    label: p.nombre_completo,
  }))
  const hayDisponibles = data?.hayDisponibles ?? true

  return (
    <>
      <Controller
        name="medico_id"
        control={control}
        render={({ field }) => (
          <Select
            label="Profesional disponible"
            placeholder={
              isLoading
                ? 'Cargando...'
                : opciones.length === 0
                  ? 'Sin profesionales de esta especialidad'
                  : 'Seleccione el profesional'
            }
            data={opciones}
            searchable
            disabled={isLoading}
            {...contained}
            value={field.value ? String(field.value) : null}
            onChange={(v) => field.onChange(v ? Number(v) : undefined)}
            error={errors.medico_id?.message}
          />
        )}
      />

      {/* El aviso decía «no hay profesionales marcados como disponibles»
          cuando la lista salía vacía, pero la disponibilidad no se
          consultaba nunca: la lista vacía solo significaba que no había
          nadie con ese rol. Cada caso dice lo suyo. */}
      {!isLoading && (opciones.length === 0 || !hayDisponibles) && (
        <Alert icon={<IconInfoCircle size={14} />} color="amber" variant="light">
          <Text size="xs">
            {opciones.length === 0
              ? 'No hay ningún profesional registrado para este tipo de atención.'
              : 'Nadie se ha marcado disponible para este tipo de atención. Se muestran todos los profesionales para no detener el turno.'}
          </Text>
        </Alert>
      )}
    </>
  )
}
