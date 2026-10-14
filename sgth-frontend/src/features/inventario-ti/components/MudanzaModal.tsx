'use client'

import { useState } from 'react'
import { Alert, Grid, Select, Text } from '@mantine/core'
import { IconInfoCircle } from '@tabler/icons-react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal, SgthTable } from '@/components/ui'
import { SEMANTIC_COLOR } from '@/config/design.tokens'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useUbicacionesMutations } from '../hooks/useUbicaciones'
import { mudanzaSchema, SIN_UBICACION, type MudanzaFormData } from '../schemas/ubicacion.schema'
import type { ResultadoMudanza, UbicacionDeUnidad } from '../services/ubicacionesService'

interface Props {
  opened: boolean
  onClose: () => void
  unidades: UbicacionDeUnidad[]
  ubicaciones: { value: string; label: string }[]
}

/**
 * Cuando una unidad cambia de piso: primero se ven los equipos que se
 * moverían —los de la unidad que están en el origen—, y luego se mueven con
 * sus componentes. Los que están en otra parte no se tocan.
 */
export function MudanzaModal({ opened, ...props }: Props) {
  return opened ? <Formulario {...props} /> : null
}

function Formulario({ onClose, unidades, ubicaciones }: Omit<Props, 'opened'>) {
  const contained = useContainedInput()
  const { mudanza } = useUbicacionesMutations()
  const [vista, setVista] = useState<ResultadoMudanza | null>(null)
  const { control, handleSubmit, setValue, setError, formState: { errors } } = useForm<MudanzaFormData>({
    resolver: zodResolver(mudanzaSchema),
    defaultValues: { desde: '' },
  })
  const [unidadId, hacia] = useWatch({ control, name: ['unidad_administrativa_id', 'hacia_ubicacion_id'] })

  const enviar = (v: MudanzaFormData) => {
    const data = {
      unidad_administrativa_id: v.unidad_administrativa_id,
      desde_ubicacion_id: v.desde === SIN_UBICACION ? null : Number(v.desde),
      hacia_ubicacion_id: v.hacia_ubicacion_id,
      aplicar: !!vista,
    }
    return mudanza.mutateAsync(data)
      .then((r) => (r.aplicada ? onClose() : setVista(r)))
      .catch((e) => erroresAlFormulario(e, setError, ['unidad_administrativa_id', 'hacia_ubicacion_id'], 'No se pudo registrar la mudanza'))
  }

  // Cambiar un dato después de ver la lista obliga a verla otra vez.
  const cambiar = <K extends keyof MudanzaFormData>(campo: K, valor: MudanzaFormData[K], onChange: (v: MudanzaFormData[K]) => void) => {
    setVista(null)
    onChange(valor)
    if (campo === 'unidad_administrativa_id') {
      const habitual = unidades.find((u) => u.unidad_id === valor)?.ubicacion_id
      setValue('desde', habitual ? String(habitual) : '')
    }
  }

  const destino = ubicaciones.find((u) => u.value === String(hacia))?.label

  return (
    <FormModal opened onClose={onClose} title="Mudanza de una unidad" size="lg" onSubmit={handleSubmit(enviar)}
      submitLabel={vista ? `Mover ${vista.total} ${vista.total === 1 ? 'equipo' : 'equipos'}` : 'Ver los equipos'}
      submitting={mudanza.isPending} submitDisabled={vista?.total === 0} closeOnClickOutside={false}>
      <Grid>
        <Grid.Col span={12}>
          <Controller name="unidad_administrativa_id" control={control} render={({ field }) => (
            <Select label="Unidad" withAsterisk searchable {...contained} error={errors.unidad_administrativa_id?.message}
              data={unidades.map((u) => ({ value: String(u.unidad_id), label: u.unidad }))}
              value={field.value ? String(field.value) : null}
              onChange={(v) => cambiar('unidad_administrativa_id', v ? Number(v) : Number.NaN, field.onChange)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="desde" control={control} render={({ field }) => (
            <Select label="De" withAsterisk searchable {...contained} error={errors.desde?.message}
              data={[{ value: SIN_UBICACION, label: 'Sin ubicación' }, ...ubicaciones]}
              value={field.value || null} onChange={(v) => cambiar('desde', v ?? '', field.onChange)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 6 }}>
          <Controller name="hacia_ubicacion_id" control={control} render={({ field }) => (
            <Select label="A" withAsterisk searchable {...contained} error={errors.hacia_ubicacion_id?.message}
              data={ubicaciones} value={field.value ? String(field.value) : null}
              onChange={(v) => cambiar('hacia_ubicacion_id', v ? Number(v) : Number.NaN, field.onChange)} />
          )} />
        </Grid.Col>
        {vista && (
          <Grid.Col span={12}>
            {vista.total === 0 ? (
              <Alert icon={<IconInfoCircle size={16} />} color={SEMANTIC_COLOR.warning} variant="light">
                No hay equipos de esa unidad en la ubicación de origen.
              </Alert>
            ) : (
              <>
                <Text size="sm" mb="xs">
                  Pasarán a <b>{destino}</b> con sus componentes, y la unidad quedará con esa ubicación habitual:
                </Text>
                <SgthTable records={vista.equipos} minHeight={100} mah={280}
                  columns={[
                    { accessor: 'codigo_institucional', title: 'Código', width: 100 },
                    { accessor: 'equipo', title: 'Equipo' },
                    { accessor: 'custodio', title: 'Custodio', render: (e) => e.custodio ?? 'Sin custodio' },
                  ]} />
              </>
            )}
          </Grid.Col>
        )}
      </Grid>
      {unidadId ? null : <Text size="xs" c="dimmed" mt="xs">Cuentan los equipos de quienes trabajan en la unidad y los que, sin custodio, son de ella.</Text>}
    </FormModal>
  )
}
