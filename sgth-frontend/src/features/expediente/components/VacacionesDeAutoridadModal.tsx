'use client'

import { Alert, Grid, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useRegistrarActoDeAutoridad } from '../hooks/useRegistrarActoDeAutoridad'
import {
  vacacionesDeAutoridadSchema, type VacacionesDeAutoridadFormData,
} from '../schemas/actosDeAutoridad.schema'
import {
  autoridadElectaService, type AutoridadElecta, type VacacionesDeAutoridadData,
} from '../services/autoridadElectaService'

interface Props {
  opened: boolean
  onClose: () => void
  autoridad: AutoridadElecta | null
}

/**
 * Las vacaciones que el Consejo Provincial le concede a una autoridad electa
 * (COOTAD Art. 47 s; TH 2026-11-26; fase 4.1d).
 *
 * Se registran aquí y no en «Vacaciones» porque a una autoridad esa vía le
 * está cerrada: no hay jefe inmediato que las autorice, las concede el Consejo
 * por resolución. Pero siguen siendo una solicitud de vacaciones [TH N11] y
 * descuentan del mismo saldo, con el mismo conteo.
 *
 * Dos topes, y conviene no confundirlos: los **60 días al año** que el Consejo
 * puede conceder entre licencias y vacaciones, y los **días que la autoridad
 * tiene acumulados**, que son los suyos de siempre. Que las conceda el Consejo
 * no multiplica el derecho.
 */
export function VacacionesDeAutoridadModal({ opened, onClose, autoridad }: Props) {
  return opened && autoridad ? <Formulario autoridad={autoridad} onClose={onClose} /> : null
}

function Formulario({ autoridad, onClose }: { autoridad: AutoridadElecta; onClose: () => void }) {
  const contained = useContainedInput()

  const { control, register, handleSubmit, setError, formState: { errors } } =
    useForm<VacacionesDeAutoridadFormData>({
      resolver: zodResolver(vacacionesDeAutoridadSchema),
      defaultValues: {
        fecha_inicio: '',
        fecha_fin: '',
        resolucion_numero: '',
        observacion: '',
      },
    })

  const { registrar, enviando } = useRegistrarActoDeAutoridad<
    VacacionesDeAutoridadFormData, VacacionesDeAutoridadData
  >({
    servidorId: () => Number(autoridad.servidor.id),
    campos: ['fecha_inicio', 'fecha_fin', 'resolucion_numero', 'observacion'],
    tituloDelError: 'No se pudieron registrar las vacaciones',
    exito: {
      titulo: 'Vacaciones registradas',
      detalle: 'Los días ya salieron de su saldo: la resolución del Consejo es la autorización.',
    },
    // No es una acción de personal: lo que queda desactualizado es la pestaña
    // y el módulo de Vacaciones, no la bandeja de acciones. Los períodos van
    // aparte de las solicitudes porque de ahí sale el saldo que se acaba de
    // descontar.
    claves: [['autoridades-electas'], ['vacaciones'], ['periodos-vacaciones']],
    enviar: (id, datos) => autoridadElectaService.registrarVacaciones(id, datos),
    onListo: onClose,
  })

  const guardar = (v: VacacionesDeAutoridadFormData) => registrar({
    fecha_inicio: v.fecha_inicio,
    fecha_fin: v.fecha_fin,
    resolucion_numero: v.resolucion_numero.trim(),
    observacion: v.observacion?.trim() || null,
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
      title={`Vacaciones del Consejo · ${autoridad.servidor.nombre}`}
      size="md"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Registrar vacaciones"
      submitting={enviando}
    >
      <Stack gap="md">
        <Alert color="ocean" variant="light" icon={<IconInfoCircle size={16} />}>
          A las autoridades electas <b>les concede las vacaciones el Consejo Provincial, por
          resolución</b>, dentro de los mismos 60 días al año de sus licencias (COOTAD Art. 47 s).
          Se descuentan de los días que tiene acumulados, como las de cualquier servidor, y
          quedan registradas al guardar: no esperan en ventanilla.
        </Alert>

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_inicio', 'Desde')}</Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>{fecha('fecha_fin', 'Hasta')}</Grid.Col>
        </Grid>

        <TextInput
          label="Resolución del Consejo N.º"
          description="Es la autorización: sin ella no se registran."
          placeholder="Ej. RES-CP-2026-120"
          withAsterisk
          error={errors.resolucion_numero?.message}
          {...contained}
          {...register('resolucion_numero')}
        />

        <Textarea
          label="Observación"
          description="Opcional, para lo que la resolución aclare."
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
