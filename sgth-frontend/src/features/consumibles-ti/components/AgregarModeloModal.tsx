'use client'

import { Grid, Select, Text, TextInput } from '@mantine/core'
import { Controller, useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { FormModal } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { useMarcas } from '@/features/inventario-ti/hooks/useCatalogosInventario'
import { useConsumibleMutations } from '../hooks/useConsumibles'
import { modeloCompatibleSchema, type ModeloCompatibleFormData } from '../schemas/consumible.schema'

const CAMPOS = ['marca_id', 'modelo']

interface Props {
  /** El consumible al que se le agrega el modelo; sin él, el modal está cerrado. */
  consumible: { id: number; descripcion: string } | null
  /** Desde la entrega: el modelo del equipo elegido, ya puesto. */
  inicial?: { marca_id: number; modelo: string }
  onClose: () => void
}

/** Agregar un modelo de equipo a los compatibles de un consumible [P1]. */
export function AgregarModeloModal({ consumible, inicial, onClose }: Props) {
  return consumible ? <Formulario consumible={consumible} inicial={inicial} onClose={onClose} /> : null
}

function Formulario({ consumible, inicial, onClose }: Omit<Props, 'consumible'> & { consumible: { id: number; descripcion: string } }) {
  const contained = useContainedInput()
  const { data: marcas = [] } = useMarcas()
  const { agregarModelo } = useConsumibleMutations(consumible.id)
  const { register, control, handleSubmit, setError, formState: { errors } } = useForm<ModeloCompatibleFormData>({
    resolver: zodResolver(modeloCompatibleSchema),
    defaultValues: inicial ?? { modelo: '' },
  })

  const enviar = (v: ModeloCompatibleFormData) =>
    agregarModelo.mutateAsync({ consumibleId: consumible.id, marcaId: v.marca_id, modelo: v.modelo })
      .then(onClose).catch((e) => erroresAlFormulario(e, setError, CAMPOS, null))

  return (
    <FormModal opened onClose={onClose} title="Agregar modelo compatible" onSubmit={handleSubmit(enviar)}
      submitLabel="Agregar" submitting={agregarModelo.isPending}>
      <Grid>
        <Grid.Col span={12}>
          <Text size="sm">{consumible.descripcion} también sirve a este modelo. Escríbalo como la ficha del equipo; no importan mayúsculas ni espacios.</Text>
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 5 }}>
          <Controller name="marca_id" control={control} render={({ field }) => (
            <Select label="Marca" placeholder="Seleccionar" withAsterisk searchable {...contained}
              data={marcas.map((m) => ({ value: String(m.id), label: m.nombre }))}
              error={errors.marca_id?.message} value={field.value ? String(field.value) : null}
              onChange={(v) => field.onChange(v ? Number(v) : undefined)} />
          )} />
        </Grid.Col>
        <Grid.Col span={{ base: 12, sm: 7 }}>
          <TextInput label="Modelo" placeholder="Ej.: LaserJet M551" withAsterisk
            error={errors.modelo?.message} {...contained} {...register('modelo')} />
        </Grid.Col>
      </Grid>
    </FormModal>
  )
}
