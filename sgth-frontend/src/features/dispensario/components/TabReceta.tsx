'use client'

import {
  Stack, Text, Button, Group, Badge,
  Card, ThemeIcon,
} from '@mantine/core'
import { IconPill, IconPlus } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { recetaService } from '../services/recetaService'
import { RecetaModal } from './RecetaModal'
import { EditarItemRecetaModal } from './EditarItemRecetaModal'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { useEmitirReceta, useAccionesItem } from '../hooks/useReceta'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { ItemReceta, RecetaMedica } from '../services/recetaService'
import {
  IconEdit, IconTrash,
} from '@tabler/icons-react'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

function formatFecha(fecha: string): string {
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: 'short', year: 'numeric',
  })
}

const ESTADO_RECETA: Record<string, { label: string; color: string }> = {
  pendiente:           { label: 'Pendiente',  color: 'gray'   },
  despachada_parcial:  { label: 'Parcial',    color: 'orange' },
  despachada_completa: { label: 'Despachada', color: 'emerald'},
  anulada:             { label: 'Anulada',    color: 'red'    },
}

function ItemsRecetaTable({
  receta,
  consulta,
}: {
  receta:   RecetaMedica
  consulta: ConsultaMedica
}) {
  const [itemSel, setItemSel] = useState<ItemReceta | null>(null)
  const [editOpened,
    { open: abrirEdit, close: cerrarEdit }] = useDisclosure(false)
  const { quitarItem } = useAccionesItem(consulta.id)
  const esPendiente = receta.estado === 'pendiente'

  const columns = [
    {
      accessor: 'nombre_medicina',
      title:    'Medicina',
      render: (item: ItemReceta) => (
        <Stack gap={0}>
          <Text size="sm" fw={500}>
            {item.inventario?.nombre ?? item.nombre_medicina ?? '—'}
          </Text>
          {item.inventario?.concentracion && (
            <Text size="xs" c="dimmed">
              {item.inventario.concentracion}
            </Text>
          )}
        </Stack>
      ),
    },
    {
      accessor: 'cantidad_prescrita',
      title:    'Cant.',
      width:    70,
      render: (item: ItemReceta) => (
        <Text size="sm" ta="center">{item.cantidad_prescrita}</Text>
      ),
    },
    {
      accessor: 'dosis',
      title:    'Dosis',
      width:    110,
      render: (item: ItemReceta) => (
        <Text size="sm">{item.dosis}</Text>
      ),
    },
    {
      accessor: 'frecuencia',
      title:    'Frecuencia',
      width:    130,
      render: (item: ItemReceta) => (
        <Text size="sm">{item.frecuencia}</Text>
      ),
    },
    {
      accessor: 'duracion',
      title:    'Duración',
      width:    100,
      render: (item: ItemReceta) => (
        <Text size="sm">{item.duracion}</Text>
      ),
    },
    ...(esPendiente ? [{
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (item: ItemReceta) => (
        <TableActions actions={[
          {
            label:   'Editar',
            icon:    <IconEdit size={14} />,
            color:   'blue',
            onClick: () => { setItemSel(item); abrirEdit() },
          },
          {
            label:   'Quitar',
            icon:    <IconTrash size={14} />,
            color:   'red',
            onClick: () => {
              if (confirm('¿Quitar este medicamento de la receta?')) {
                quitarItem.mutate({
                  recetaId: receta.id,
                  itemId:   item.id!,
                })
              }
            },
          },
        ]} />
      ),
    }] : []),
  ]

  return (
    <>
      <SgthTable
        records={receta.items}
        columns={columns}
        minHeight={60}
      />
      <EditarItemRecetaModal
        opened={editOpened}
        onClose={() => { setItemSel(null); cerrarEdit() }}
        item={itemSel}
        recetaId={receta.id}
        consultaId={consulta.id}
      />
    </>
  )
}

export function TabReceta({ turno, consulta }: Props) {
  const [modalOpened,
    { open: abrirModal, close: cerrarModal }] = useDisclosure(false)
  const emitir = useEmitirReceta(consulta.id)

  const { data: recetas = [], isLoading } = useQuery({
    queryKey: ['recetas', 'consulta', consulta.id],
    queryFn:  () => recetaService.listarPorConsulta(consulta.id),
    staleTime: 1000 * 30,
  })

  return (
    <Stack gap="md" p="md">
      <Group justify="space-between">
        <Text size="sm" fw={500}>
          Recetas de esta consulta
          {recetas.length > 0 && (
            <Text span c="dimmed" ml={4}>
              ({recetas.length})
            </Text>
          )}
        </Text>
        <Button
          size="xs"
          color="emerald"
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
          loading={emitir.isPending}
        >
          Nueva receta
        </Button>
      </Group>

      {isLoading ? (
        <Text size="sm" c="dimmed">Cargando recetas...</Text>
      ) : recetas.length === 0 ? (
        <EmptyState
          icon={IconPill}
          title="Sin recetas"
          description="No se han emitido recetas para esta consulta."
        />
      ) : (
        <Stack gap="sm">
          {recetas.map((receta) => {
            const estadoConfig = ESTADO_RECETA[receta.estado]
              ?? { label: receta.estado, color: 'gray' }
            return (
              <Card key={receta.id} withBorder radius="md" p="sm">
                <Stack gap="xs">
                  <Group justify="space-between">
                    <Group gap="xs">
                      <ThemeIcon
                        size="sm" color="emerald" variant="light"
                      >
                        <IconPill size={12} />
                      </ThemeIcon>
                      <Text size="sm" fw={500}>
                        {formatFecha(receta.fecha_emision)}
                      </Text>
                    </Group>
                    <Badge
                      size="sm"
                      variant="light"
                      color={estadoConfig.color}
                    >
                      {estadoConfig.label}
                    </Badge>
                  </Group>

                  {receta.indicaciones_generales && (
                    <Text size="xs" c="dimmed">
                      {receta.indicaciones_generales}
                    </Text>
                  )}

                  <ItemsRecetaTable
                    receta={receta}
                    consulta={consulta}
                  />
                </Stack>
              </Card>
            )
          })}
        </Stack>
      )}

      <RecetaModal
        opened={modalOpened}
        onClose={cerrarModal}
        turno={turno}
        consulta={consulta}
        onEmitida={cerrarModal}
      />
    </Stack>
  )
}
