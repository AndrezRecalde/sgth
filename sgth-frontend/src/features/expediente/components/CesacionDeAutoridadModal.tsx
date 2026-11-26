'use client'

import { Alert, Select, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { fromDateValueOrNull, toDateValue } from '@/lib/fecha'
import { useRegistrarActoDeAutoridad } from '../hooks/useRegistrarActoDeAutoridad'
import {
  cesacionDeAutoridadSchema, type CesacionDeAutoridadFormData,
} from '../schemas/actosDeAutoridad.schema'
import type { AutoridadElecta } from '../services/autoridadElectaService'

/*
| Las cuatro de una autoridad electa, y solo esas: no se la destituye por
| sumario ni se le acepta un visto bueno. El «fin del período» no está porque
| no lo registra nadie — lo marca la fecha y lo cierra el comando diario.
*/
const CAUSALES = [
  { value: 'renuncia', label: 'Renuncia' },
  { value: 'fallecimiento', label: 'Fallecimiento' },
  { value: 'revocatoria_del_mandato', label: 'Revocatoria del mandato' },
  { value: 'remocion_por_el_consejo', label: 'Remoción por el Consejo Provincial' },
]

/** De dónde sale el papel que archiva cada causal. */
const DOCUMENTO: Record<string, string> = {
  renuncia: 'La renuncia aceptada por el Consejo Provincial.',
  fallecimiento: 'La partida de defunción.',
  revocatoria_del_mandato: 'El resultado del CNE (Constitución Art. 105).',
  remocion_por_el_consejo: 'La resolución del Consejo (COOTAD Arts. 333 a 336).',
}

interface Props {
  opened: boolean
  onClose: () => void
  autoridad: AutoridadElecta | null
}

/**
 * La cesación de una autoridad electa (fase 4.1).
 *
 * Se registra aquí y no en «Nueva acción de personal» [TH, 2026-11-26]: es la
 * única de sus actos que comparte figura con el resto del personal, y tenerla
 * en los dos sitios con reglas distintas es como empezó todo esto.
 *
 * Si quien cesa es el Prefecto, al registrarla el sistema prepara sola la
 * asunción definitiva del Viceprefecto (COOTAD Art. 51).
 */
export function CesacionDeAutoridadModal({ opened, onClose, autoridad }: Props) {
  return opened && autoridad ? <Formulario autoridad={autoridad} onClose={onClose} /> : null
}

function Formulario({ autoridad, onClose }: { autoridad: AutoridadElecta; onClose: () => void }) {
  const contained = useContainedInput()

  const { control, register, handleSubmit, setError, formState: { errors } } =
    useForm<CesacionDeAutoridadFormData>({
      resolver: zodResolver(cesacionDeAutoridadSchema),
      defaultValues: {
        causal: undefined,
        fecha_efectiva: '',
        resolucion_numero: '',
        descripcion: '',
      },
    })

  const causal = useWatch({ control, name: 'causal' })

  const { registrar, enviando } = useRegistrarActoDeAutoridad<CesacionDeAutoridadFormData>({
    servidorId: () => Number(autoridad.servidor.id),
    campos: ['causal', 'fecha_efectiva', 'resolucion_numero', 'descripcion'],
    tituloDelError: 'No se pudo registrar la cesación',
    exito: {
      titulo: 'Cesación registrada',
      detalle: 'Quedó en borrador: suscríbala y regístrela en la bandeja de acciones.',
    },
    onListo: onClose,
  })

  const guardar = (v: CesacionDeAutoridadFormData) => registrar({
    clase: 'cesacion',
    causal: v.causal,
    descripcion: v.descripcion.trim(),
    fecha_efectiva: v.fecha_efectiva,
    resolucion_numero: v.resolucion_numero.trim(),
  }, setError)

  return (
    <FormModal
      opened
      onClose={onClose}
      title={`Cesación · ${autoridad.servidor.nombre}`}
      size="md"
      onSubmit={handleSubmit(guardar)}
      submitLabel="Registrar en borrador"
      submitting={enviando}
    >
      <Stack gap="md">
        {autoridad.es_prefecto && (
          <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}>
            Es el Prefecto en funciones: al registrarse, el sistema prepara sola la asunción
            definitiva del Viceprefecto, que asume hasta terminar el período (COOTAD Art. 51).
            Hasta que esa asunción se registre, <b>ninguna acción de personal se puede
            suscribir</b>, porque no habrá autoridad nominadora.
          </Alert>
        )}

        <Controller
          name="causal"
          control={control}
          render={({ field }) => (
            <Select
              label="Por qué termina"
              placeholder="Seleccionar"
              withAsterisk
              data={CAUSALES}
              value={field.value ?? null}
              onChange={(v) => field.onChange(v as CesacionDeAutoridadFormData['causal'])}
              error={errors.causal?.message}
              {...contained}
            />
          )}
        />

        <Controller
          name="fecha_efectiva"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Rige a partir de"
              description="El último día en el cargo es el anterior."
              placeholder="Seleccionar fecha"
              valueFormat="DD/MM/YYYY"
              withAsterisk
              popoverProps={{ withinPortal: true, zIndex: 1100 }}
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
              error={errors.fecha_efectiva?.message}
              {...contained}
            />
          )}
        />

        <TextInput
          label="Documento de origen N.º"
          description={causal ? DOCUMENTO[causal] : 'El resultado del CNE, la resolución del Consejo, la renuncia aceptada o la partida de defunción.'}
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
