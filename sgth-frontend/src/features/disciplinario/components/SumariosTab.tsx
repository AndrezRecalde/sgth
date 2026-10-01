'use client'

import { useState } from 'react'
import { Button, Select, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconGavel, IconPlus } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useSumarios } from '../hooks/useDisciplinario'
import { useAccionesSumario } from '../hooks/useAccionesSumario'
import { SumarioModal } from './SumarioModal'
import { ResolverSumarioModal } from './ResolverSumarioModal'
import { AvanzarHitoModal } from './AvanzarHitoModal'
import { SumarioDetalleDrawer } from './SumarioDetalleDrawer'
import { columnasSumario } from './sumarios.columns'
import { ESTADO_SUMARIO_LABELS } from '../utils/etiquetas'
import classes from './filtros.module.css'
import type { EstadoSumario } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_SUMARIO_LABELS) as EstadoSumario[])
  .map((e) => ({ value: e, label: ESTADO_SUMARIO_LABELS[e] }))

const POR_PAGINA = 15

export function SumariosTab() {
  // Variante compacta: es una barra de filtros, no un formulario de captura.
  const contained = useContainedInput('sm')
  const [estado, setEstado] = useState<string | null>(null)
  const [page, setPage] = useState(1)
  const [modalOpened, { open, close }] = useDisclosure(false)

  // Las acciones de la fila y los paneles que abre cada una viven en su hook.
  const { accionesDe, detalle, resolucion, avance } = useAccionesSumario()

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

  const abrir = (
    <Button leftSection={<IconPlus size={16} />} variant="light" onClick={open}>
      Abrir sumario
    </Button>
  )

  return (
    <Stack gap="md">
      <Toolbar actions={abrir}>
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
        empty={!sumarios.length}
        emptyProps={{
          icon: IconGavel,
          title: 'Sin sumarios administrativos',
          description: estado
            ? 'Ningún sumario se encuentra en ese estado. Pruebe con otro o quite el filtro.'
            : 'Aquí se instruyen los sumarios del personal LOSEP, hito por hito, hasta la resolución que impone la sanción.',
          // Con el filtro puesto, lo que hace falta es quitarlo, no abrir un
          // sumario: el botón de la barra sigue a la vista.
          action: estado ? undefined : abrir,
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={sumarios}
          columns={columnasSumario(accionesDe)}
          totalRecords={data?.total ?? sumarios.length}
          recordsPerPage={POR_PAGINA}
          page={page}
          onPageChange={setPage}
          minHeight={200}
        />
      </DataState>

      <SumarioModal opened={modalOpened} onClose={close} />
      <SumarioDetalleDrawer
        opened={detalle.abierto}
        onClose={detalle.cerrar}
        sumario={detalle.sumario}
      />
      <ResolverSumarioModal
        opened={resolucion.abierto}
        onClose={resolucion.cerrar}
        sumario={resolucion.sumario}
      />
      <AvanzarHitoModal
        opened={avance.abierto}
        onClose={avance.cerrar}
        sumario={avance.sumario}
        destino={avance.destino}
      />
    </Stack>
  )
}
