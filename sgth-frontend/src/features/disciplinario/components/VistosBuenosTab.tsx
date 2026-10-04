'use client'

import { useState } from 'react'
import { Button, Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconEye, IconFileCheck, IconPencil, IconPlus } from '@tabler/icons-react'
import {
  DataState, PAGINACION_ES, SgthTable, Toolbar, type TableAction,
} from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useVistosBuenos } from '../hooks/useDisciplinario'
import { VistoBuenoModal } from './VistoBuenoModal'
import { TransicionarVistoBuenoModal } from './TransicionarVistoBuenoModal'
import { VistoBuenoDetalleDrawer } from './VistoBuenoDetalleDrawer'
import { columnasVistoBueno } from './vistosBuenos.columns'
import { ESTADO_VISTO_BUENO_LABELS, TRANSICIONES_VISTO_BUENO } from '../utils/etiquetas'
import classes from './filtros.module.css'
import type { EstadoVistoBueno, VistoBueno } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_VISTO_BUENO_LABELS) as EstadoVistoBueno[])
  .map((e) => ({ value: e, label: ESTADO_VISTO_BUENO_LABELS[e] }))

const POR_PAGINA = 15

export function VistosBuenosTab() {
  // Variante compacta: es una barra de filtros, no un formulario de captura.
  const contained = useContainedInput('sm')
  const [estado, setEstado] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [seleccionado, setSeleccionado] = useState<VistoBueno | null>(null)
  const [crearOpened, { open: openCrear, close: closeCrear }] = useDisclosure(false)
  const [editarOpened, { open: openEditar, close: closeEditar }] = useDisclosure(false)
  const [aVer, setAVer] = useState<VistoBueno | null>(null)
  const [detalleOpened, { open: openDetalle, close: closeDetalle }] = useDisclosure(false)

  // Cambiar el filtro sin volver a la primera página consultaría esa misma
  // página del resultado ya filtrado —casi siempre vacía—, así que la tabla
  // saldría en blanco aunque hubiera coincidencias.
  const cambiarEstado = (valor: string | null) => {
    setEstado(valor)
    setPage(1)
  }

  const { data, isLoading, error } = useVistosBuenos({
    estado: (estado as EstadoVistoBueno | null) ?? undefined,
    page,
    per_page: POR_PAGINA,
  })
  const tramites = data?.data ?? []
  // El cajón enseña la versión recargada del trámite, no la copia de cuando
  // se abrió: adjuntar la resolución desde el propio cajón no se veía hasta
  // cerrarlo y volver a abrirlo.
  const aVerFresco = aVer ? tramites.find((t) => t.id === aVer.id) ?? aVer : null

  const abrirTransicion = (tramite: VistoBueno) => {
    setSeleccionado(tramite)
    openEditar()
  }

  const accionesDe = (t: VistoBueno): TableAction[] => [
    {
      label: 'Ver detalle',
      icon: <IconEye size={14} />,
      onClick: () => {
        setAVer(t)
        openDetalle()
      },
    },
    {
      label: 'Actualizar trámite',
      icon: <IconPencil size={14} />,
      // Un trámite terminal no admite cambios: se muestra apagado en vez de
      // desaparecer, para que se vea que la acción existe (regla 06).
      disabled: TRANSICIONES_VISTO_BUENO[t.estado].length === 0,
      onClick: () => abrirTransicion(t),
    },
  ]

  const solicitar = (
    <Button leftSection={<IconPlus size={16} />} variant="light" onClick={openCrear}>
      Solicitar visto bueno
    </Button>
  )

  return (
    <Stack gap="md">
      <Toolbar actions={solicitar}>
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={cambiarEstado}
          clearable
          {...contained}
          className={classes.filtroEstado}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!tramites.length}
        emptyProps={{
          icon: IconFileCheck,
          title: 'Sin trámites de visto bueno',
          description: estado
            ? 'Ningún trámite se encuentra en ese estado. Pruebe con otro o quite el filtro.'
            : 'Aquí se registran las solicitudes ante el Inspector del Trabajo para terminar con justa causa el contrato de un obrero.',
          action: estado ? undefined : solicitar,
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={tramites}
          columns={columnasVistoBueno(accionesDe)}
          totalRecords={data?.total ?? tramites.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <VistoBuenoDetalleDrawer
        opened={detalleOpened}
        onClose={closeDetalle}
        tramite={aVerFresco}
      />
      <VistoBuenoModal opened={crearOpened} onClose={closeCrear} />
      <TransicionarVistoBuenoModal
        opened={editarOpened}
        onClose={closeEditar}
        tramite={seleccionado}
      />
    </Stack>
  )
}
