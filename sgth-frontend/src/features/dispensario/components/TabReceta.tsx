'use client'

import { confirmar, DataState, StatusBadge } from '@/components/ui'
import {
  Stack, Text, Button, Group,
  Card, ThemeIcon,
} from '@mantine/core'
import { IconPill, IconPlus, IconPrinter } from '@tabler/icons-react'
import { useDisclosure } from '@mantine/hooks'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { esItemExterno, nombreDeItem, recetaService } from '../services/recetaService'
import { RecetaModal } from './RecetaModal'
import { EditarItemRecetaModal } from './EditarItemRecetaModal'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { useEmitirReceta, useAccionesItem, useRecetaPdf } from '../hooks/useReceta'
import { useAuthStore } from '@/store/auth.store'
import type { AgendaMedica } from '../services/agendaService'
import type { ConsultaMedica } from '../services/consultaMedicaService'
import type { SemanticTone } from '@/config/design.tokens'
import type { ItemReceta, RecetaMedica } from '../services/recetaService'
import {
  IconEdit, IconTrash,
} from '@tabler/icons-react'
import { formatFechaMes } from '@/lib/fecha'

interface Props {
  turno:    AgendaMedica
  consulta: ConsultaMedica
}

const ESTADO_RECETA: Record<string, { label: string; tone: SemanticTone }> = {
  pendiente:           { label: 'Pendiente',  tone: 'neutral' },
  despachada_parcial:  { label: 'Parcial',    tone: 'warning' },
  despachada_completa: { label: 'Despachada', tone: 'success' },
  anulada:             { label: 'Anulada',    tone: 'danger'  },
  // No es un problema ni un logro: la farmacia no tiene nada que hacer con
  // ella, y con eso queda cerrada.
  externa:             { label: 'Externa',    tone: 'neutral' },
}

/**
 * Estados en los que la receta todavía se puede retocar, los mismos que acepta
 * el servidor. `externa` es terminal para la farmacia, no para el médico: que
 * nada se entregue aquí no impide corregir la dosis de lo recetado.
 */
const EDITABLES = ['pendiente', 'externa']

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
  const { usuario } = useAuthStore()

  // Retocar la receta es cosa de quien la firmó, y el servidor lo rechaza con
  // un 403 aunque se llame a la API a mano. Aquí solo decide si se ofrecen los
  // botones: enseñar un «Editar» que siempre va a fallar es peor que no
  // enseñarlo.
  const puedeRetocar = EDITABLES.includes(receta.estado)
    && (usuario?.id === undefined || consulta.medico_id === usuario.id)

  const columns = [
    {
      accessor: 'medicina',
      title:    'Medicina',
      render: (item: ItemReceta) => (
        // La insignia va debajo del nombre y no a su lado: esta columna se
        // estrecha con el ancho de la pantalla, y en línea quedaba cortada por
        // el borde justo en el aviso que hay que leer.
        <Stack gap={2} align="flex-start">
          <Text size="sm" fw={500}>{nombreDeItem(item)}</Text>
          {esItemExterno(item) ? (
            <>
              <StatusBadge tone="warning" size="xs">
                Fuera de farmacia
              </StatusBadge>
              <Text size="xs" c="dimmed">
                El paciente lo adquiere fuera del dispensario.
              </Text>
            </>
          ) : item.inventario?.concentracion ? (
            <Text size="xs" c="dimmed">
              {item.inventario.concentracion}
            </Text>
          ) : null}
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
    ...(puedeRetocar ? [{
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (item: ItemReceta) => (
        <TableActions actions={[
          {
            label:   'Editar',
            icon:    <IconEdit size={14} />,
            onClick: () => { setItemSel(item); abrirEdit() },
          },
          {
            label:   'Quitar',
            icon:    <IconTrash size={14} />,
            color:   'red',
            onClick: () => confirmar({
              title:   'Quitar medicamento',
              message: 'Se quitará este medicamento de la receta.',
              destructiva: true,
              confirmLabel: 'Quitar',
              onConfirm: () => quitarItem.mutate({
                recetaId: receta.id,
                itemId:   item.id!,
              }),
            }),
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
  const { abrir: abrirPdf, abriendo } = useRecetaPdf()

  const { data: recetas = [], isLoading, error, refetch } = useQuery({
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
          leftSection={<IconPlus size={13} />}
          onClick={abrirModal}
          loading={emitir.isPending}
        >
          Nueva receta
        </Button>
      </Group>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudieron cargar las recetas"
        errorHint="No quiere decir que esta consulta no tenga recetas emitidas: no se pudieron consultar."
        onRetry={() => refetch()}
        empty={!recetas.length}
        emptyProps={{
          icon: IconPill,
          title: 'Sin recetas',
          description: 'No se han emitido recetas para esta consulta.',
        }}
        skeletonRows={2}
      >
        <Stack gap="sm">
          {recetas.map((receta) => {
            const estadoConfig = ESTADO_RECETA[receta.estado]
              ?? { label: receta.estado, tone: 'neutral' as SemanticTone }
            return (
              <Card key={receta.id} withBorder radius="md" p="sm">
                <Stack gap="xs">
                  <Group justify="space-between">
                    <Group gap="xs">
                      <ThemeIcon
                        size="sm" variant="light"
                      >
                        <IconPill size={12} />
                      </ThemeIcon>
                      <Text size="sm" fw={500}>
                        {formatFechaMes(receta.fecha_emision)}
                      </Text>
                    </Group>
                    <Group gap="xs">
                      {receta.folio && (
                        <Text size="xs" c="dimmed" ff="monospace">
                          {receta.folio}
                        </Text>
                      )}
                      <StatusBadge tone={estadoConfig.tone}>
                        {estadoConfig.label}
                      </StatusBadge>
                      <Button
                        size="compact-xs"
                        variant="light"
                        leftSection={<IconPrinter size={13} />}
                        loading={abriendo === receta.id}
                        onClick={() => abrirPdf(receta.id)}
                      >
                        Imprimir
                      </Button>
                    </Group>
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
      </DataState>

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
