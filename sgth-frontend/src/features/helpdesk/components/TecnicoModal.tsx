'use client'

import { Grid, SegmentedControl, Select, Stack, Switch, Text } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useConfiguracionMutations } from '../hooks/useConfiguracionHelpdesk'
import { useCatalogosHelpdesk } from '../hooks/useTickets'
import { tecnicoSchema, type TecnicoFormData } from '../schemas/configuracion.schema'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { TecnicoHelpdesk } from '../services/tipos'

const CAMPOS = ['user_id', 'unidad_tic_id', 'nivel', 'estado']

interface Props {
  opened: boolean
  onClose: () => void
  tecnico: TecnicoHelpdesk | null
  /** Los usuarios que ya figuran: no se ofrecen otra vez. */
  registrados: number[]
}

/** La subunidad de TIC y el nivel de un técnico. El usuario no cambia al editar. */
export function TecnicoModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, tecnico, registrados }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { data: catalogos } = useCatalogosHelpdesk()
  const { guardar } = useConfiguracionMutations('tecnico', configuracionHelpdeskService.tecnico)
  const { control, handleSubmit, setError, formState: { errors } } = useForm<TecnicoFormData>({
    resolver: zodResolver(tecnicoSchema),
    defaultValues: tecnico
      ? { user_id: tecnico.user_id, unidad_tic_id: tecnico.unidad_tic_id ?? undefined, nivel: tecnico.nivel, estado: tecnico.estado }
      : { nivel: 1, estado: true },
  })

  const candidatos = (catalogos?.tecnicos ?? []).filter((u) => !registrados.includes(u.id))

  const enviar = (v: TecnicoFormData) =>
    guardar.mutateAsync({
      id: tecnico?.id,
      data: tecnico ? { unidad_tic_id: v.unidad_tic_id, nivel: v.nivel, estado: v.estado } : v,
    })
      .then(onClose)
      .catch((e) => erroresAlFormulario(e, setError, CAMPOS, 'No se pudo guardar el técnico'))

  return (
    <FormModal opened onClose={onClose} title={tecnico ? 'Editar técnico' : 'Agregar técnico'} onSubmit={handleSubmit(enviar)}
      submitLabel={tecnico ? 'Guardar cambios' : 'Agregar'} submitting={guardar.isPending}>
      <Grid>
        <Grid.Col span={12}>
          {tecnico ? (
            <Text size="sm" fw={500}>{tecnico.nombre}</Text>
          ) : (
            <Controller name="user_id" control={control} render={({ field }) => (
              <Select label="Usuario" withAsterisk searchable {...contained}
                description="Solo los usuarios con un rol que atiende tickets."
                placeholder={candidatos.length ? 'Elija quién atiende' : 'Nadie más con un rol de TIC'}
                nothingFoundMessage="Sin coincidencias" error={errors.user_id?.message}
                data={candidatos.map((u) => ({ value: String(u.id), label: u.nombre }))}
                value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
            )} />
          )}
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="unidad_tic_id" control={control} render={({ field }) => (
            <Select label="Subunidad de TIC" withAsterisk {...contained} error={errors.unidad_tic_id?.message}
              placeholder="Elija la subunidad"
              data={(catalogos?.subunidades ?? []).map((u) => ({ value: String(u.id), label: u.nombre }))}
              value={field.value ? String(field.value) : null} onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="nivel" control={control} render={({ field }) => (
            <Stack gap={4}>
              <Text size="sm" fw={500}>Nivel</Text>
              <SegmentedControl fullWidth value={String(field.value)} onChange={(v) => field.onChange(Number(v))}
                data={[{ value: '1', label: 'Nivel 1' }, { value: '2', label: 'Nivel 2' }, { value: '3', label: 'Nivel 3' }]} />
            </Stack>
          )} />
        </Grid.Col>
        <Grid.Col span={12}>
          <Controller name="estado" control={control} render={({ field }) => (
            <Switch label="Atiende tickets" description="Pausado, no se le propone al asignar (vacaciones, licencia)."
              checked={field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
          )} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
