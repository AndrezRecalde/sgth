'use client'

import { DataState, SectionHeading, SgthModal, SgthTable, TableActions, confirmar } from '@/components/ui'
import { useState } from 'react'
import {
  Stack, Grid, Select, NumberInput, Button,
} from '@mantine/core'
import { Controller, useForm, type Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { IconTrash, IconPlus, IconHelmet } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useEquiposPorPuesto, usePuestoEppMutations } from '../hooks/usePuestoEpp'
import { useEquiposProteccion } from '../hooks/useEquiposProteccion'
import { puestoEppSchema, type PuestoEppFormData } from '../schemas/puestoEpp.schema'
import { erroresDeCampo } from '@/lib/erroresDeCampo'
import type { PuestoEpp } from '../services/tipos'
import type { DataTableColumn } from 'mantine-datatable'

const VALORES_INICIALES: PuestoEppFormData = {
  equipo_proteccion_id: 0,
  cantidad_requerida: 1,
  frecuencia_reposicion_meses: null,
}

interface Props {
  opened: boolean
  onClose: () => void
}

export function AsignarEppPuestoModal({ opened, onClose }: Props) {
  const contained = useContainedInput()

  const [puestoId, setPuestoId] = useState<number | null>(null)

  const { data: asignaciones = [], isLoading, error, refetch } = useEquiposPorPuesto(puestoId)
  const { asignar, eliminar } = usePuestoEppMutations(puestoId)
  const { data: equiposData, error: errorEquipos } = useEquiposProteccion({ estado: true })

  // Fuera los que este puesto ya requiere. `asignarEquipoAPuesto` es un
  // `updateOrCreate`: volver a elegir uno ya asignado no daba error, pisaba su
  // cantidad y dejaba la frecuencia de reposición en blanco, y la pantalla
  // respondía «equipo asignado». La tabla de abajo ya los muestra; ofrecerlos
  // otra vez en el desplegable solo servía para pisarlos sin querer.
  const yaAsignados = new Set(asignaciones.map(a => a.equipo_proteccion_id))
  const equipoOptions = (equiposData?.data ?? [])
    .filter(e => !yaAsignados.has(e.id))
    .map(e => ({ value: String(e.id), label: `${e.codigo} — ${e.nombre}` }))

  // Estaba a mano con tres `useState` y sin validación: una cantidad en blanco
  // solo desactivaba el botón, sin decir por qué. El reparto de errores por
  // campo se mantiene para lo que el backend sí rechaza —un equipo que ya no
  // existe, por ejemplo—; el duplicado no llega a intentarse porque el
  // desplegable ya no lo ofrece.
  const {
    control, handleSubmit, reset, setError,
    formState: { errors },
  } = useForm<PuestoEppFormData>({
    resolver: zodResolver(puestoEppSchema) as Resolver<PuestoEppFormData>,
    defaultValues: VALORES_INICIALES,
  })

  const handleClose = () => {
    setPuestoId(null)
    reset(VALORES_INICIALES)
    onClose()
  }

  const guardar = (valores: PuestoEppFormData) => {
    asignar.mutateAsync({
      equipo_proteccion_id: valores.equipo_proteccion_id,
      cantidad_requerida: valores.cantidad_requerida,
      frecuencia_reposicion_meses: valores.frecuencia_reposicion_meses ?? undefined,
    })
      .then(() => reset(VALORES_INICIALES))
      .catch((error) => {
        const campos = erroresDeCampo(error)
        if (!campos) return // el hook ya lo notificó
        for (const [campo, mensaje] of Object.entries(campos)) {
          setError(campo as keyof PuestoEppFormData, { message: mensaje })
        }
      })
  }

  const columns: DataTableColumn<PuestoEpp>[] = [
    {
      accessor: 'equipo_proteccion',
      title: 'Equipo',
      render: (a) => a.equipo_proteccion?.nombre ?? `Equipo ${a.equipo_proteccion_id}`,
    },
    { accessor: 'cantidad_requerida', title: 'Cantidad' },
    {
      accessor: 'frecuencia_reposicion_meses',
      title: 'Reposición',
      render: (a) => a.frecuencia_reposicion_meses ? `Cada ${a.frecuencia_reposicion_meses} meses` : '—',
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (a) => (
        <TableActions
          actions={[
            {
              label: 'Quitar del kit',
              icon: <IconTrash size={14} />,
              color: 'red',
              onClick: () => confirmar({
                title:   'Eliminar asignación',
                message: (
                  <>
                    Se quitará <b>{a.equipo_proteccion?.nombre ?? 'el equipo'}</b> del EPP
                    requerido de este puesto. No se puede deshacer.
                  </>
                ),
                destructiva: true,
                onConfirm: () => eliminar.mutate(a.id),
              }),
            },
          ]}
        />
      ),
    },
  ]

  return (
    <SgthModal
      opened={opened}
      onClose={handleClose}
      title="EPP requerido por puesto"
      size="lg"
    >
      <Stack gap="md">
        <BuscarPuestoSelect
          label="Puesto"
          value={puestoId}
          onChange={(id) => setPuestoId(id)}
        />

        {puestoId && (
          <>
            <SectionHeading title="Agregar equipo requerido" />
            {/* Alineados arriba: el error de un campo crece hacia abajo y no
                mueve a los otros dos. */}
            <form onSubmit={handleSubmit(guardar)} noValidate>
              {/* El equipo se lleva la fila entera: su etiqueta es el código
                  más el nombre —«EPP-014 — Respirador de media cara con
                  filtros P100»— y compartiendo fila con la cantidad, la
                  reposición y el botón le quedaban 91 px de 354. */}
              <Grid gap="sm">
                <Grid.Col span={12}>
                  <Controller
                    name="equipo_proteccion_id"
                    control={control}
                    render={({ field }) => (
                      <Select
                        label="Equipo"
                        placeholder="Seleccione un equipo"
                        data={equipoOptions}
                        searchable
                        // Un desplegable vacío porque ya está todo asignado se
                        // veía igual que un catálogo sin equipos.
                        nothingFoundMessage={
                          yaAsignados.size && !equipoOptions.length
                            ? 'Este puesto ya requiere todos los equipos activos del catálogo.'
                            : 'Sin equipos en el catálogo.'
                        }
                        {...contained}
                        value={field.value ? String(field.value) : null}
                        onChange={(v) => field.onChange(v ? Number(v) : 0)}
                        // Un catálogo que no cargó se veía igual que un catálogo vacío.
                        error={
                          errors.equipo_proteccion_id?.message
                          ?? (errorEquipos ? 'No se pudo cargar el catálogo de equipos de protección.' : undefined)
                        }
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name="cantidad_requerida"
                    control={control}
                    render={({ field }) => (
                      <NumberInput
                        label="Cantidad"
                        min={1}
                        hideControls
                        {...contained}
                        value={field.value}
                        onChange={(v) => field.onChange(typeof v === 'number' ? v : 0)}
                        error={errors.cantidad_requerida?.message}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  <Controller
                    name="frecuencia_reposicion_meses"
                    control={control}
                    render={({ field }) => (
                      <NumberInput
                        label="Reposición (meses)"
                        min={1}
                        hideControls
                        {...contained}
                        value={field.value ?? ''}
                        onChange={(v) => field.onChange(typeof v === 'number' ? v : null)}
                        error={errors.frecuencia_reposicion_meses?.message}
                      />
                    )}
                  />
                </Grid.Col>
                <Grid.Col span={{ base: 12, sm: 4 }}>
                  {/* Los campos contained miden 48 px; el botón los iguala. */}
                  <Button
                    type="submit"
                    h={48}
                    fullWidth
                    leftSection={<IconPlus size={16} />}
                    loading={asignar.isPending}
                  >
                    Agregar
                  </Button>
                </Grid.Col>
              </Grid>
            </form>

            <DataState
              loading={isLoading}
              error={error}
              errorTitle="No se pudo cargar el EPP requerido del puesto"
              errorHint="No quiere decir que el puesto no tenga EPP asignado: no se pudo consultar."
              onRetry={() => refetch()}
              skeletonRows={3}
              empty={!asignaciones.length}
              emptyProps={{
                icon: IconHelmet,
                title: 'Este puesto no tiene EPP requerido todavía',
                description: 'Agregue los equipos con el formulario de arriba: de aquí sale el kit que se entrega al servidor.',
              }}
            >
              <SgthTable
                records={asignaciones}
                columns={columns}
                minHeight={120}
              />
            </DataState>
          </>
        )}
      </Stack>
    </SgthModal>
  )
}
