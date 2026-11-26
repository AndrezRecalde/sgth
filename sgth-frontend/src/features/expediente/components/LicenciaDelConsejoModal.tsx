'use client'

import { Alert, Grid, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useRegistrarActoDeAutoridad } from '../hooks/useRegistrarActoDeAutoridad'
import {
  licenciaDelConsejoSchema, type LicenciaDelConsejoFormData,
} from '../schemas/actosDeAutoridad.schema'
import type { AutoridadElecta } from '../services/autoridadElectaService'

const PRORROGAS = [
  { value: 'enfermedad_catastrofica', label: 'Enfermedad catastrófica' },
  { value: 'calamidad_domestica', label: 'Calamidad doméstica' },
]

interface Props {
  opened: boolean
  onClose: () => void
  autoridad: AutoridadElecta | null
}

/**
 * La licencia que concede el Consejo Provincial (COOTAD Art. 47 s; fase 4.1b).
 *
 * Hasta 60 días acumulados al año, prorrogables por enfermedad catastrófica o
 * calamidad doméstica; el tope lo comprueba el servidor sumando las demás
 * licencias del mismo año. Si es la del Prefecto y pasa de tres días, al
 * registrarse prepara sola la subrogación por ley del Viceprefecto (Art. 51).
 */
export function LicenciaDelConsejoModal({ opened, onClose, autoridad }: Props) {
  return opened && autoridad ? <Formulario autoridad={autoridad} onClose={onClose} /> : null
}

function Formulario({ autoridad, onClose }: { autoridad: AutoridadElecta; onClose: () => void }) {
  const contained = useContainedInput()

  const { control, register, handleSubmit, setError, formState: { errors } } =
    useForm<LicenciaDelConsejoFormData>({
      resolver: zodResolver(licenciaDelConsejoSchema),
      defaultValues: {
        fecha_inicio: '',
        fecha_fin: '',
        // Sin valor por defecto: lo dice la resolución, no la costumbre.
        con_remuneracion: undefined,
        prorroga_causa: null,
        resolucion_numero: '',
        descripcion: `Licencia concedida por el Consejo Provincial a ${autoridad.servidor.nombre}.`,
      },
    })

  const { registrar, enviando } = useRegistrarActoDeAutoridad<LicenciaDelConsejoFormData>({
    servidorId: () => Number(autoridad.servidor.id),
    campos: ['fecha_inicio', 'fecha_fin', 'con_remuneracion', 'prorroga_causa', 'resolucion_numero', 'descripcion'],
    tituloDelError: 'No se pudo registrar la licencia del Consejo',
    exito: {
      titulo: 'Licencia registrada',
      detalle: 'Quedó en borrador: suscríbala y regístrela en la bandeja de acciones.',
    },
    onListo: onClose,
  })

  const guardar = (v: LicenciaDelConsejoFormData) => registrar({
    clase: 'licencia_consejo',
    descripcion: v.descripcion.trim(),
    // Una ausencia rige el día en que empieza: no hay dos fechas que cuadrar.
    fecha_efectiva: v.fecha_inicio,
    fecha_inicio: v.fecha_inicio,
    fecha_fin: v.fecha_fin,
    con_remuneracion: v.con_remuneracion,
    prorroga_causa: v.prorroga_causa,
    resolucion_numero: v.resolucion_numero.trim(),
  }, setError)

  const fecha = (name: 'fecha_inicio' | 'fecha_fin', label: string) => (
    <Controller
      name={name}
      control={control}
      render={({ field }) => (
        <DatePickerInput
          label={label}
          placeholder="Seleccionar fecha"
          valueFormat="DD/MM/YYYY"
          withAsterisk
          popoverProps={{ withinPortal: true, zIndex: 1100 }}
          value={toDateValue(field.value)}
          onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
          error={errors[name]?.message}
          {...contained}
        />
      )}
    />
  )

  return (
    <FormModal
      opened
      onClose={onClose}
      title={`Licencia del Consejo · ${autoridad.servidor.nombre}`}
      size="md"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Registrar en borrador"
      submitting={enviando}
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          El Consejo Provincial concede licencias a los miembros del gobierno provincial,
          hasta <b>60 días acumulados al año</b>, prorrogables por enfermedad catastrófica o
          calamidad doméstica (COOTAD Art. 47 s).
          {autoridad.es_prefecto && (
            <> Si pasa de tres días, al registrarla se prepara sola la subrogación por ley
            del Viceprefecto (Art. 51).</>
          )}
        </Alert>

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_inicio', 'Desde')}</Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_fin', 'Hasta')}</Grid.Col>
        </Grid>

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="con_remuneracion"
              control={control}
              render={({ field }) => (
                <Select
                  label="Remuneración"
                  description="Lo dice la resolución del Consejo."
                  placeholder="Seleccionar"
                  withAsterisk
                  data={[
                    { value: 'con', label: 'Con remuneración' },
                    { value: 'sin', label: 'Sin remuneración' },
                  ]}
                  value={field.value == null ? null : field.value ? 'con' : 'sin'}
                  onChange={(v) => field.onChange(v == null ? undefined : v === 'con')}
                  error={errors.con_remuneracion?.message}
                  {...contained}
                />
              )}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name="prorroga_causa"
              control={control}
              render={({ field }) => (
                <Select
                  label="Prórroga del Consejo"
                  description="Solo si pasa de los 60 días del año."
                  placeholder="Sin prórroga"
                  clearable
                  data={PRORROGAS}
                  value={field.value ?? null}
                  onChange={(v) => field.onChange(v as LicenciaDelConsejoFormData['prorroga_causa'])}
                  error={errors.prorroga_causa?.message}
                  {...contained}
                />
              )}
            />
          </Grid.Col>
        </Grid>

        <TextInput
          label="Resolución del Consejo N.º"
          description="Es el documento con el que se archiva la licencia."
          placeholder="Ej. RES-CP-2026-045"
          withAsterisk
          error={errors.resolucion_numero?.message}
          {...contained}
          {...register('resolucion_numero')}
        />

        <Textarea
          label="Explicación"
          description="Es el texto que lleva el documento del acto."
          required
          autosize
          minRows={2}
          error={errors.descripcion?.message}
          {...contained}
          {...register('descripcion')}
        />
      </Stack>
    </FormModal>
  )
}
