'use client'

import { useState } from 'react'
import { Button, Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconArrowRight, IconFolderOff, IconGavel, IconPlus, IconScaleOutline,
} from '@tabler/icons-react'
import {
  DataState, PAGINACION_ES, SgthTable, Toolbar, confirmar, type TableAction,
} from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useSumarios } from '../hooks/useDisciplinario'
import { useDisciplinarioMutations } from '../hooks/useDisciplinarioMutations'
import { SumarioModal } from './SumarioModal'
import { ResolverSumarioModal } from './ResolverSumarioModal'
import { columnasSumario } from './sumarios.columns'
import {
  ESTADO_SUMARIO_LABELS,
  TRANSICIONES_SUMARIO,
  nombreServidor,
  puedeResolverse,
  siguienteHito,
} from '../utils/etiquetas'
import type { EstadoSumario, Sumario } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_SUMARIO_LABELS) as EstadoSumario[])
  .map((e) => ({ value: e, label: ESTADO_SUMARIO_LABELS[e] }))

const POR_PAGINA = 15

export function SumariosTab() {
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [modalOpened, { open, close }] = useDisclosure(false)
  const [aResolver, setAResolver] = useState<Sumario | null>(null)
  const [resolverOpened, { open: openResolver, close: closeResolver }] = useDisclosure(false)

  // Cambiar el filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarEstado = (valor: string | null) => {
    setEstado(valor)
    setPage(1)
  }

  const { data, isLoading, error } = useSumarios({
    estado: (estado as EstadoSumario | null) ?? undefined,
    page,
    per_page: POR_PAGINA,
  })
  const sumarios = data?.data ?? []

  const { avanzarSumario } = useDisciplinarioMutations()

  const abrirResolucion = (sumario: Sumario) => {
    setAResolver(sumario)
    openResolver()
  }

  const cerrar = (sumario: Sumario) => confirmar({
    title: 'Cerrar el sumario',
    message: (
      <>
        El sumario de <b>{nombreServidor(sumario.servidor)}</b> quedará cerrado
        sin sanción y no admitirá más trámite. No se puede deshacer.
      </>
    ),
    confirmLabel: 'Cerrar sumario',
    destructiva: true,
    onConfirm: () => avanzarSumario.mutate({
      id: sumario.id,
      data: { estado: 'cerrado' },
    }),
  })

  /**
   * Las acciones salen del grafo de transiciones, no de una lista escrita
   * aparte: avanzar al hito que sigue, resolver imponiendo la sanción, dejar
   * constancia de la apelación, o cerrar sin sanción.
   */
  const accionesDe = (s: Sumario): TableAction[] => {
    const siguiente = siguienteHito(s.estado)
    const transiciones = TRANSICIONES_SUMARIO[s.estado]

    return [
      {
        label: siguiente ? `Avanzar a ${ESTADO_SUMARIO_LABELS[siguiente]}` : 'Avanzar',
        icon: <IconArrowRight size={14} />,
        hidden: !siguiente,
        disabled: avanzarSumario.isPending,
        onClick: () => siguiente && avanzarSumario.mutate({
          id: s.id,
          data: { estado: siguiente },
        }),
      },
      {
        label: 'Resolver e imponer sanción',
        icon: <IconScaleOutline size={14} />,
        hidden: !puedeResolverse(s.estado),
        onClick: () => abrirResolucion(s),
      },
      {
        label: 'Registrar apelación',
        icon: <IconArrowRight size={14} />,
        hidden: !transiciones.includes('apelado'),
        disabled: avanzarSumario.isPending,
        onClick: () => avanzarSumario.mutate({ id: s.id, data: { estado: 'apelado' } }),
      },
      {
        label: 'Cerrar sin sanción',
        icon: <IconFolderOff size={14} />,
        color: 'red',
        hidden: !transiciones.includes('cerrado'),
        disabled: avanzarSumario.isPending,
        onClick: () => cerrar(s),
      },
    ]
  }

  const columns = columnasSumario(accionesDe)

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Button
            leftSection={<IconPlus size={16} />}
            variant="light"
            onClick={open}
          >
            Abrir sumario
          </Button>
        }
      >
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={cambiarEstado}
          clearable
          {...contained}
          style={{ minWidth: 240 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!sumarios.length}
        emptyProps={{
          icon: IconGavel,
          title: 'Sin sumarios administrativos',
          description: estado
            ? 'Ningún sumario se encuentra en ese estado.'
            : 'No hay sumarios administrativos abiertos.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={sumarios}
          columns={columns}
          totalRecords={data?.total ?? sumarios.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <SumarioModal opened={modalOpened} onClose={close} />
      <ResolverSumarioModal
        opened={resolverOpened}
        onClose={closeResolver}
        sumario={aResolver}
      />
    </Stack>
  )
}
