'use client'

import { Button, Grid, Group, Input, SegmentedControl, Stack, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconPlus } from '@tabler/icons-react'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { dinero } from '@/lib/dinero'
import { useCargos } from '../hooks/useCargos'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { usePrepararRevision, useVistaPreviaRevision } from '../hooks/useRevisionClasificacion'
import { revisionClasificacionSchema, type RevisionClasificacionFormData } from '../schemas/revisionClasificacion.schema'
import type { CausalRevision } from '../services/revisionClasificacionService'
import { RevisionClasificacionRenglon } from './RevisionClasificacionRenglon'
import type { Cargo, PuestoConRelaciones } from '@/types/api'

const CAUSALES: { value: CausalRevision; label: string; ayuda: string }[] = [
  { value: 'revalorizacion', label: 'Revalorización', ayuda: 'Sube el grado del puesto y conserva su denominación.' },
  { value: 'reclasificacion', label: 'Reclasificación', ayuda: 'Cambia la denominación del puesto; el grado puede quedar o subir.' },
  { value: 'ubicacion', label: 'Ubicación', ayuda: 'Sitúa el puesto en el grado y la denominación de un manual de clasificación nuevo.' },
]

interface Props {
  puesto: PuestoConRelaciones | null
  onClose: () => void
}

/**
 * Revisar la clasificación de puestos ocupados (LOSEP Arts. 61 y 62; fase
 * 7.1b). Uno o varios puestos, cada uno con su grado o su denominación nuevos;
 * cada ocupante recibe su acción de personal, con la remuneración nueva —nunca
 * menor—, en un lote que se suscribe y registra entero en Acciones de Personal.
 */
export function RevisionClasificacionModal({ puesto, onClose }: Props) {
  return puesto ? <Formulario puesto={puesto} onClose={onClose} /> : null
}

function Formulario({ puesto, onClose }: { puesto: PuestoConRelaciones; onClose: () => void }) {
  const contained = useContainedInput()
  const preparar = usePrepararRevision()
  const { data: gruposRaw } = useGruposOcupacionales()
  const { data: cargosRaw } = useCargos()

  const grupos = (gruposRaw ?? [])
    .filter((g) => g.regimen === 'losep' && g.activo)
    .map((g) => ({ value: String(g.id), label: `${g.grado_codigo} — ${g.denominacion_generica ?? g.grupo} (${dinero(g.rmu)})` }))
  const cargos = ((cargosRaw ?? []) as Cargo[]).map((c) => ({ value: String(c.id), label: c.nombre }))

  const { control, register, handleSubmit, setError, formState: { errors } } = useForm<RevisionClasificacionFormData>({
    resolver: zodResolver(revisionClasificacionSchema),
    defaultValues: {
      causal: 'revalorizacion',
      puestos: [{ puesto_id: Number(puesto.id), grupo_ocupacional_id: null, cargo_id: null }],
      documento_origen: '',
      dictamen_presupuestario_ref: '',
      descripcion: '',
      fecha_efectiva: hoyIso(),
    },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'puestos' })
  const [causal, puestos] = useWatch({ control, name: ['causal', 'puestos'] })

  // Solo los renglones con puesto elegido van a la vista previa.
  const elegidos = (puestos ?? []).filter((p) => p.puesto_id > 0)
  const previa = useVistaPreviaRevision(causal, elegidos)
  const filaDe = (puestoId: number) => previa.data?.puestos.find((p) => p.puesto_id === puestoId)
  const hayImpedimentos = (previa.data?.puestos ?? []).some((p) => p.impedimentos.length > 0)
  const pideDictamen = !!previa.data?.sube_la_remuneracion

  const enviar = async (v: RevisionClasificacionFormData) => {
    if (pideDictamen && !v.dictamen_presupuestario_ref?.trim()) {
      setError('dictamen_presupuestario_ref', { type: 'manual', message: 'Sube la remuneración: indique el dictamen presupuestario' })
      return
    }

    try {
      await preparar.mutateAsync({
        ...v,
        puestos: v.puestos.filter((p) => p.puesto_id > 0),
        dictamen_presupuestario_ref: v.dictamen_presupuestario_ref?.trim() || null,
      })
      onClose()
    } catch (error) {
      for (const [campo, mensaje] of Object.entries(erroresDeCampo(error) ?? {})) {
        if (campo in revisionClasificacionSchema.shape) setError(campo as keyof RevisionClasificacionFormData, { type: 'server', message: mensaje })
      }
    }
  }

  return (
    <FormModal
      opened
      onClose={onClose}
      title="Revisar la clasificación"
      size="xl"
      onSubmit={handleSubmit(enviar)}
      submitLabel="Preparar la revisión"
      submitting={preparar.isPending}
      submitDisabled={hayImpedimentos || previa.isLoading || !!previa.error}
    >
      <Stack gap="sm">
        <Input.Wrapper label="Qué se revisa" description={CAUSALES.find((c) => c.value === causal)?.ayuda}>
          <Controller
            name="causal"
            control={control}
            render={({ field }) => (
              <SegmentedControl fullWidth data={CAUSALES} value={field.value} onChange={(v) => field.onChange(v as CausalRevision)} mt={4} />
            )}
          />
        </Input.Wrapper>

        {fields.map((f, i) => (
          <RevisionClasificacionRenglon
            key={f.id}
            control={control}
            indice={i}
            puesto={puesto}
            grupos={grupos}
            cargos={cargos}
            fila={filaDe(puestos?.[i]?.puesto_id ?? 0)}
            onQuitar={() => remove(i)}
          />
        ))}

        <Group>
          <Button
            variant="light"
            size="xs"
            leftSection={<IconPlus size={14} />}
            onClick={() => append({ puesto_id: 0, grupo_ocupacional_id: null, cargo_id: null })}
          >
            Agregar otro puesto
          </Button>
        </Group>

        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Resolución que la aprueba"
              description="La de la autoridad nominadora (Reglamento a la LOSEP, Art. 173)."
              withAsterisk
              error={errors.documento_origen?.message}
              {...contained}
              {...register('documento_origen')}
            />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <TextInput
              label="Dictamen presupuestario"
              description={pideDictamen ? 'Sube la remuneración de alguien: es obligatorio.' : 'Solo si sube la remuneración.'}
              withAsterisk={pideDictamen}
              error={errors.dictamen_presupuestario_ref?.message}
              {...contained}
              {...register('dictamen_presupuestario_ref')}
            />
          </Grid.Col>
        </Grid>
        <Controller
          name="fecha_efectiva"
          control={control}
          render={({ field }) => (
            <DatePickerInput
              label="Rige a partir de"
              withAsterisk
              valueFormat="DD/MM/YYYY"
              value={toDateValue(field.value)}
              onChange={(d) => field.onChange(fromDateValueOrNull(d) ?? '')}
              error={errors.fecha_efectiva?.message}
              {...contained}
            />
          )}
        />
        <Textarea
          label="Explicación"
          description="La imprime cada acción de personal."
          withAsterisk
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

