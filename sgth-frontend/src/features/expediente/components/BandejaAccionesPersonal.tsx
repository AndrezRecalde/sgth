'use client'

import { useState } from 'react'
import { Select, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFileDescription } from '@tabler/icons-react'
import { DataState, PAGINACION_ES, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { POR_PAGINA_BANDEJA, useBandejaMovimientos } from '../hooks/useMovimientoMutations'
import { AccionPersonalDetalleDrawer } from './AccionPersonalDetalleDrawer'
import { getBandejaAccionesColumns } from './bandejaAcciones.columns'
import { ESTADO_LABELS } from '../utils/estadoAccionPersonal'
import type { EstadoAccionPersonal } from '@/types/api'

const ESTADO_OPTIONS = (Object.keys(ESTADO_LABELS) as EstadoAccionPersonal[])
  .map((e) => ({ value: e, label: ESTADO_LABELS[e] }))

/**
 * Bandeja transversal de acciones de personal. Existe para revisar y aprobar
 * sin entrar expediente por expediente: por defecto muestra los borradores,
 * que son los que esperan decisión de Talento Humano.
 */
export function BandejaAccionesPersonal() {
  // Compacta, que es la altura del patrón contained dentro de una `Toolbar`:
  // así el filtro convive con el resto de la barra sin el aire de un formulario
  // de captura.
  const contained = useContainedInput('sm')
  const [estado, setEstado] = useState<EstadoAccionPersonal | null>('borrador')
  const [pagina, setPagina] = useState(1)
  const [seleccionadoId, setSeleccionadoId] = useState<number | null>(null)
  const [detalleOpened, { open: abrirDetalle, close: cerrarDetalle }] = useDisclosure(false)

  const { data, isLoading, error, refetch } = useBandejaMovimientos({
    ...(estado ? { estado } : {}),
    page: pagina,
  })

  const acciones = data?.data ?? []
  const total = data?.total ?? 0

  /** Cambiar el filtro devuelve a la primera página: la 4 puede no existir. */
  const cambiarEstado = (valor: string | null) => {
    setEstado(valor as EstadoAccionPersonal | null)
    setPagina(1)
  }

  const columns = getBandejaAccionesColumns({
    onVerDetalle: (m) => { setSeleccionadoId(Number(m.id)); abrirDetalle() },
  })

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Text size="sm" c="dimmed">
            {total} acción(es) {estado ? `en ${ESTADO_LABELS[estado].toLowerCase()}` : 'en total'}
          </Text>
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
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar la bandeja de acciones de personal"
        onRetry={refetch}
        empty={!acciones.length}
        page={pagina}
        emptyProps={{
          icon: IconFileDescription,
          title: 'Sin acciones de personal',
          description: estado
            ? 'Ninguna acción se encuentra en ese estado.'
            : 'No hay acciones de personal registradas.',
        }}
      >
        <SgthTable
          {...PAGINACION_ES}
          records={acciones}
          columns={columns}
          totalRecords={total}
          recordsPerPage={POR_PAGINA_BANDEJA}
          page={pagina}
          onPageChange={setPagina}
          minHeight={200}
        />
      </DataState>

      <AccionPersonalDetalleDrawer
        opened={detalleOpened}
        onClose={cerrarDetalle}
        movimientoId={seleccionadoId}
      />
    </Stack>
  )
}
