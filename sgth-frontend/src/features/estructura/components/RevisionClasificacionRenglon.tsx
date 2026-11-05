'use client'

import { Alert, Button, Grid, Group, Paper, Select, Stack, Text } from '@mantine/core'
import { IconAlertTriangle, IconTrash } from '@tabler/icons-react'
import { Controller, type Control } from 'react-hook-form'
import { useContainedInput } from '@/hooks/useContainedInput'
import { dinero } from '@/lib/dinero'
import type { PuestoConRelaciones } from '@/types/api'
import type { RevisionClasificacionFormData } from '../schemas/revisionClasificacion.schema'
import type { VistaPreviaRevision } from '../services/revisionClasificacionService'
import { BuscarPuestoSelect } from './BuscarPuestoSelect'

type Opcion = { value: string; label: string }
type Fila = VistaPreviaRevision['puestos'][number]

interface Props {
  control:  Control<RevisionClasificacionFormData>
  indice:   number
  /** El puesto desde el que se abrió: el primer renglón es el suyo. */
  puesto:   PuestoConRelaciones
  grupos:   Opcion[]
  cargos:   Opcion[]
  /** Lo que la vista previa dice de este puesto, si ya llegó. */
  fila?:    Fila
  onQuitar: () => void
}

/** Un puesto de la revisión: su grado y su denominación nuevos, y lo que cambiaría. */
export function RevisionClasificacionRenglon({ control, indice, puesto, grupos, cargos, fila, onQuitar }: Props) {
  const contained = useContainedInput()

  return (
    <Paper withBorder p="sm" radius="md">
      <Stack gap="xs">
        <Group justify="space-between" align="flex-end" wrap="nowrap">
          {indice === 0 ? (
            <Text size="sm" fw={600}>{puesto.cargo?.nombre ?? 'Puesto'} · {puesto.unidad_administrativa?.nombre ?? ''}</Text>
          ) : (
            <Controller
              name={`puestos.${indice}.puesto_id`}
              control={control}
              render={({ field }) => (
                <div style={{ flex: 1 }}>
                  <BuscarPuestoSelect label="Otro puesto" value={field.value > 0 ? field.value : null} onChange={(id) => field.onChange(id ?? 0)} />
                </div>
              )}
            />
          )}
          {indice > 0 && (
            <Button variant="subtle" color="red" size="xs" leftSection={<IconTrash size={14} />} onClick={onQuitar}>
              Quitar
            </Button>
          )}
        </Group>
        {/* En rejilla y no en `Group grow`: en un teléfono, un campo por fila (regla 07). */}
        <Grid gap="sm">
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={`puestos.${indice}.grupo_ocupacional_id`}
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
          </Grid.Col>
          <Grid.Col span={{ base: 12, sm: 6 }}>
            <Controller
              name={`puestos.${indice}.cargo_id`}
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
          </Grid.Col>
        </Grid>
        <ResumenDelPuesto fila={fila} />
      </Stack>
    </Paper>
  )
}

/** De cómo está a cómo quedaría el puesto, y cuánto cobraría cada ocupante. */
function ResumenDelPuesto({ fila }: { fila?: Fila }) {
  if (!fila) return null

  const describe = (s: Fila['antes']) =>
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
