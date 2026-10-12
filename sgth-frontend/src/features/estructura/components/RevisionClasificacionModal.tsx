'use client'

import { Alert, Button, Group, Input, Paper, SegmentedControl, Select, Stack, Text, Textarea, TextInput } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconAlertTriangle, IconPlus, IconTrash } from '@tabler/icons-react'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import { fromDateValueOrNull, hoyIso, toDateValue } from '@/lib/fecha'
import { dinero } from '@/features/expediente/utils/dinero'
import { useCargos } from '../hooks/useCargos'
import { useGruposOcupacionales } from '../hooks/useGruposOcupacionales'
import { usePrepararRevision, useVistaPreviaRevision } from '../hooks/useRevisionClasificacion'
import { revisionClasificacionSchema, type RevisionClasificacionFormData } from '../schemas/revisionClasificacion.schema'
import type { CausalRevision, VistaPreviaRevision } from '../services/revisionClasificacionService'
import { BuscarPuestoSelect } from './BuscarPuestoSelect'
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
          <Paper key={f.id} withBorder p="sm" radius="md">
            <Stack gap="xs">
              <Group justify="space-between" align="flex-end" wrap="nowrap">
                {i === 0 ? (
                  <Text size="sm" fw={600}>{puesto.cargo?.nombre ?? 'Puesto'} · {puesto.unidad_administrativa?.nombre ?? ''}</Text>
                ) : (
                  <Controller
                    name={`puestos.${i}.puesto_id`}
                    control={control}
                    render={({ field }) => (
                      <div style={{ flex: 1 }}>
                        <BuscarPuestoSelect label="Otro puesto" value={field.value > 0 ? field.value : null} onChange={(id) => field.onChange(id ?? 0)} />
                      </div>
                    )}
                  />
                )}
                {i > 0 && (
                  <Button variant="subtle" color="red" size="xs" leftSection={<IconTrash size={14} />} onClick={() => remove(i)}>
                    Quitar
                  </Button>
                )}
              </Group>
              <Group grow align="flex-start">
                <Controller
                  name={`puestos.${i}.grupo_ocupacional_id`}
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Grado nuevo"
                      placeholder="El mismo"
                      data={grupos}
                      searchable
                      clearable
                      value={field.value ? String(field.value) : null}
                      onChange={(v) => field.onChange(v ? Number(v) : null)}
                      {...contained}
                    />
                  )}
                />
                <Controller
                  name={`puestos.${i}.cargo_id`}
                  control={control}
                  render={({ field }) => (
                    <Select
                      label="Denominación nueva"
                      placeholder="La misma"
                      data={cargos}
                      searchable
                      clearable
                      value={field.value ? String(field.value) : null}
                      onChange={(v) => field.onChange(v ? Number(v) : null)}
                      {...contained}
                    />
                  )}
                />
              </Group>
              <ResumenDelPuesto fila={filaDe(puestos?.[i]?.puesto_id ?? 0)} />
            </Stack>
          </Paper>
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

        <Group grow align="flex-start">
          <TextInput
            label="Resolución que la aprueba"
            description="La de la autoridad nominadora (Reglamento a la LOSEP, Art. 173)."
            withAsterisk
            error={errors.documento_origen?.message}
            {...contained}
            {...register('documento_origen')}
          />
          <TextInput
            label="Dictamen presupuestario"
            description={pideDictamen ? 'Sube la remuneración de alguien: es obligatorio.' : 'Solo si sube la remuneración.'}
            withAsterisk={pideDictamen}
            error={errors.dictamen_presupuestario_ref?.message}
            {...contained}
            {...register('dictamen_presupuestario_ref')}
          />
        </Group>
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

/** De cómo está a cómo quedaría el puesto, y cuánto cobraría cada ocupante. */
function ResumenDelPuesto({ fila }: { fila?: VistaPreviaRevision['puestos'][number] }) {
  if (!fila) return null

  const describe = (s: VistaPreviaRevision['puestos'][number]['antes']) =>
    [s.denominacion, s.grado, s.rmu != null ? dinero(s.rmu) : null].filter(Boolean).join(' · ')

  return (
    <Stack gap={4}>
      <Text size="xs" c="dimmed">De {describe(fila.antes)} a {describe(fila.despues)}</Text>
      {fila.ocupantes.map((o) => (
        <Text key={o.servidor_id} size="xs">
          {o.nombre} · {dinero(o.remuneracion_actual)} → <b>{dinero(o.remuneracion_nueva)}</b>
        </Text>
      ))}
      {fila.impedimentos.length > 0 && (
        <Alert variant="light" color="amber" icon={<IconAlertTriangle size={16} />} p="xs">
          <Stack gap={2}>
            {fila.impedimentos.map((i) => <Text key={i} size="xs">{i}</Text>)}
          </Stack>
        </Alert>
      )}
    </Stack>
  )
}
