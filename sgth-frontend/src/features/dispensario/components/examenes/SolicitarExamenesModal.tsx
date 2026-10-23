'use client'

import { useState } from 'react'
import { useFieldArray, useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import {
  Alert, Button, Checkbox, Grid, Group, SegmentedControl, Stack, Text, Textarea, TextInput,
} from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { DataState, FormModal, SectionHeading } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { hoyIso } from '@/lib/fecha'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ordenExamenSchema, type OrdenExamenFormData } from '../../schemas/ordenExamen.schema'
import { useCatalogoExamenes, useEmitirOrdenExamen, usePerfilesExamen } from '../../hooks/useExamenes'
import { BuscarCie10Input } from '../BuscarCie10Input'
import { CatalogoExamenesCasillas } from './CatalogoExamenesCasillas'
import type { CatalogoExamen, PerfilExamen } from '../../services/examenService'
import type { ConsultaMedica } from '../../services/consultaMedicaService'
import type { DiagnosticoCie10 } from '../../services/cie10Service'

/**
 * De dónde sale la orden: de una consulta, o de una ficha ocupacional (fase
 * 5), que trae los exámenes que proponen los riesgos marcados en su sección G.
 */
export type OrigenOrden =
  | { tipo: 'consulta'; consulta: ConsultaMedica }
  | { tipo: 'ficha'; fichaId: number; propuestos: number[] }

interface Props {
  opened:  boolean
  onClose: () => void
  origen:  OrigenOrden
}

const VACIO: OrdenExamenFormData = {
  prioridad: 'rutina', indicaciones: '', laboratorio_destino: '',
  consentimiento_firmado: false, items: [],
}

/** Pedir exámenes desde la consulta o la ficha ocupacional, marcando casillas como en la hoja del laboratorio. */
export function SolicitarExamenesModal({ opened, onClose, origen }: Props) {
  const contained = useContainedInput()
  const catalogo = useCatalogoExamenes(opened)
  const deFicha = origen.tipo === 'ficha'
  const { data: todosLosPerfiles = [] } = usePerfilesExamen(deFicha ? 'medicina_general' : origen.consulta.especialidad, opened)
  // La ficha es para trabajar: solo perfiles ocupacionales y ningún examen
  // sensible, que no se exige para el empleo (D8, MDT-2024-198).
  const perfiles = deFicha ? todosLosPerfiles.filter((p) => p.ocupacional) : todosLosPerfiles
  const ofrecidos = (catalogo.data?.examenes ?? []).filter((e) => !deFicha || !e.sensible)
  const emitir = useEmitirOrdenExamen()

  // El diagnóstico presuntivo arranca en el principal de la consulta.
  const principal: DiagnosticoCie10 | null = deFicha ? null : origen.consulta.diagnostico_cie10_principal ?? null
  const [cie10, setCie10] = useState<DiagnosticoCie10 | null>(principal)

  const { control, register, handleSubmit, reset, setError, formState: { errors } } =
    useForm<OrdenExamenFormData>({ resolver: zodResolver(ordenExamenSchema), defaultValues: VACIO })
  const { fields, append, remove } = useFieldArray({ control, name: 'items' })

  const porId = new Map(ofrecidos.map((e) => [e.id, e]))
  const marcados = new Set(fields.map((f) => f.catalogo_examen_id))
  const sensibles = fields.map((f) => porId.get(f.catalogo_examen_id)).filter((e) => e?.sensible)
  const conNota = fields
    .map((f, i) => ({ id: f.id, i, examen: porId.get(f.catalogo_examen_id) }))
    .filter((x) => x.examen?.etiqueta_nota)

  const alternar = (examen: CatalogoExamen) => {
    const i = fields.findIndex((f) => f.catalogo_examen_id === examen.id)
    if (i >= 0) remove(i)
    else append({ catalogo_examen_id: examen.id, nota: '' })
  }

  // Un perfil, o lo que proponen los riesgos de la ficha, marca sus exámenes
  // de una vez; lo ya marcado se queda.
  const marcarTodos = (ids: number[]) => ids
    .filter((id) => porId.has(id) && !marcados.has(id))
    .forEach((id) => append({ catalogo_examen_id: id, nota: '' }))
  const aplicarPerfil = (perfil: PerfilExamen) => marcarTodos(perfil.examenes.map((e) => e.id))
  const propuestos = deFicha ? origen.propuestos.filter((id) => porId.has(id)) : []

  const cerrar = () => {
    reset(VACIO)
    setCie10(principal)
    onClose()
  }

  const enviar = (valores: OrdenExamenFormData) => {
    if (sensibles.length > 0 && !valores.consentimiento_firmado) {
      setError('consentimiento_firmado', { message: 'Confirme que el paciente firmó el consentimiento.' })
      return
    }
    emitir.mutateAsync({
      ...(deFicha ? { ficha_salud_ocupacional_id: origen.fichaId } : { consulta_medica_id: origen.consulta.id }),
      fecha_solicitud:        hoyIso(),
      diagnostico_cie10_id:   cie10?.id ?? null,
      prioridad:              valores.prioridad,
      indicaciones:           valores.indicaciones.trim() || null,
      laboratorio_destino:    valores.laboratorio_destino.trim() || null,
      consentimiento_firmado: sensibles.length > 0 && valores.consentimiento_firmado,
      items: valores.items.map((i) => ({ catalogo_examen_id: i.catalogo_examen_id, nota: i.nota.trim() || null })),
    }).then(cerrar).catch((e) => erroresAlFormulario(
      e, setError,
      [...Object.keys(ordenExamenSchema.shape), ...fields.map((_, i) => `items.${i}.nota`)],
      'No se pudo emitir la orden',
    ))
  }

  return (
    <FormModal
      opened={opened}
      onClose={cerrar}
      title="Solicitar exámenes"
      size="90%"
      closeOnClickOutside={false}
      onSubmit={handleSubmit(enviar)}
      submitLabel={fields.length > 0 ? `Emitir orden (${fields.length})` : 'Emitir orden'}
      submitting={emitir.isPending}
      submitDisabled={fields.length === 0}
    >
      <Stack gap="md">
        {propuestos.length > 0 && (
          <Group gap="xs">
            <Text size="xs" c="dimmed">Por los riesgos marcados en la sección G:</Text>
            <Button size="compact-xs" onClick={() => marcarTodos(propuestos)}>
              Marcar los propuestos ({propuestos.length})
            </Button>
          </Group>
        )}
        {perfiles.length > 0 && (
          <Group gap="xs">
            <Text size="xs" c="dimmed">Perfiles:</Text>
            {perfiles.map((p) => (
              <Button key={p.id} size="compact-xs" variant="light" onClick={() => aplicarPerfil(p)}
                title={p.descripcion ?? undefined}>
                {p.nombre}
              </Button>
            ))}
          </Group>
        )}

        <DataState loading={catalogo.isLoading} error={catalogo.error} onRetry={() => catalogo.refetch()}
          errorTitle="No se pudo cargar el catálogo de exámenes" skeletonRows={4}>
          <CatalogoExamenesCasillas examenes={ofrecidos} areas={catalogo.data?.areas ?? []}
            marcados={marcados} onAlternar={alternar} />
        </DataState>
        {errors.items?.message && <Text size="xs" c="red">{errors.items.message}</Text>}

        {conNota.length > 0 && (
          <Stack gap="xs">
            <SectionHeading title="Datos de los exámenes marcados" />
            <Grid gap="sm">
              {conNota.map(({ id, i, examen }) => (
                <Grid.Col key={id} span={{ base: 12, sm: 6 }}>
                  <TextInput label={`${examen?.nombre}: ${examen?.etiqueta_nota}`} maxLength={100} {...contained}
                    {...register(`items.${i}.nota`)} error={errors.items?.[i]?.nota?.message} />
                </Grid.Col>
              ))}
            </Grid>
          </Stack>
        )}

        <SectionHeading title="Datos de la orden" />
        <Grid gap="sm">
          <Grid.Col span={{ base: 12, md: 6 }}>
            <BuscarCie10Input value={cie10} onChange={setCie10} />
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <Stack gap={4}>
              <Text size="xs" fw={500}>Prioridad</Text>
              <Controller name="prioridad" control={control} render={({ field }) => (
                <SegmentedControl size="xs" value={field.value} onChange={field.onChange}
                  data={[{ value: 'rutina', label: 'Rutina' }, { value: 'urgente', label: 'Urgente' }]} />
              )} />
            </Stack>
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6, md: 3 }}>
            <TextInput label="Laboratorio de destino" placeholder="Opcional" maxLength={150} {...contained}
              {...register('laboratorio_destino')} error={errors.laboratorio_destino?.message} />
          </Grid.Col>
          <Grid.Col span={12}>
            <Textarea label="Indicaciones al paciente" placeholder="Ayuno, preparación (opcional)" autosize
              minRows={2} maxLength={1000} {...contained} {...register('indicaciones')} error={errors.indicaciones?.message} />
          </Grid.Col>
        </Grid>

        {sensibles.length > 0 && (
          <Alert color="amber" variant="light" icon={<IconAlertTriangle size={16} />}
            title={`Exámenes sensibles: ${sensibles.map((e) => e?.nombre).join(', ')}`}>
            <Controller name="consentimiento_firmado" control={control} render={({ field }) => (
              <Checkbox label="El paciente firmó el consentimiento" checked={field.value}
                onChange={(e) => field.onChange(e.currentTarget.checked)}
                error={errors.consentimiento_firmado?.message} />
            )} />
          </Alert>
        )}
      </Stack>
    </FormModal>
  )
}
