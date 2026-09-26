'use client'

import { useState } from 'react'
import { Select, Stack, Text } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconFileDescription } from '@tabler/icons-react'
import { DataState, SgthTable, Toolbar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useBandejaMovimientos } from '../hooks/useMovimientoMutations'
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
  const contained = useContainedInput()
  const [estado, setEstado] = useState<string | null>('borrador')
  const [seleccionadoId, setSeleccionadoId] = useState<number | null>(null)
  const [detalleOpened, { open: abrirDetalle, close: cerrarDetalle }] = useDisclosure(false)

  const { data, isLoading, error } = useBandejaMovimientos(
    estado ? { estado } : undefined,
  )
  const acciones = data?.data ?? []

  const columns = getBandejaAccionesColumns({
    onVerDetalle: (m) => { setSeleccionadoId(Number(m.id)); abrirDetalle() },
  })

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <Text size="sm" c="dimmed">
            {acciones.length} acción(es) en la vista
          </Text>
        }
      >
        <Select
          label="Estado"
          placeholder="Todos"
          data={ESTADO_OPTIONS}
          value={estado}
          onChange={setEstado}
          clearable
          {...contained}
          style={{ minWidth: 260 }}
        />
      </Toolbar>

      <DataState
        loading={isLoading}
        error={error}
        empty={!acciones.length}
        emptyProps={{
          icon: IconFileDescription,
          title: 'Sin acciones de personal',
          description: estado
            ? 'Ninguna acción se encuentra en ese estado.'
            : 'No hay acciones de personal registradas.',
        }}
      >
        <SgthTable
          records={acciones}
          columns={columns}
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
