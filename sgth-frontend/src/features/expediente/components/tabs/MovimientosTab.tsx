'use client'

import { useState } from 'react'
import { Stack, Button } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconPlus, IconHistory } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, StatusBadge, notificar } from '@/components/ui'
import { useMovimientos } from '../../hooks/useMovimientos'
import { MovimientoModal } from '../MovimientoModal'
import { AccionPersonalDetalleDrawer } from '../AccionPersonalDetalleDrawer'
import { getMovimientoColumns } from '../movimientos.columns'
import { movimientoService } from '../../services/movimientoService'
import { getApiErrorMessage } from '@/types/api'
import type { MovimientoPersonal } from '@/types/api'
import { guardarArchivo } from '@/lib/archivo'

interface Props {
  servidorId: number
  tipoNombramiento?: string | null
}

export function MovimientosTab({ servidorId, tipoNombramiento }: Props) {
  const [opened, { open, close }] = useDisclosure(false)
  const [detalleOpened, { open: abrirDetalle, close: cerrarDetalle }] = useDisclosure(false)
  const [detalleId, setDetalleId] = useState<number | null>(null)
  const { data: movimientos = [], isLoading, error } = useMovimientos(servidorId)
  const [descargandoId, setDescargandoId] = useState<number | null>(null)

  const lista = movimientos as MovimientoPersonal[]

  const handleDescargarPdf = async (movimiento: MovimientoPersonal) => {
    setDescargandoId(Number(movimiento.id))
    try {
      const blob = await movimientoService.descargarPdf(Number(movimiento.id))
      guardarArchivo(blob, `accion_personal_${movimiento.codigo ?? movimiento.id}.pdf`)
    } catch (error) {
      notificar.error(
        'No se pudo generar el PDF de la acción de personal',
        getApiErrorMessage(error, 'Inténtalo de nuevo en unos segundos.'),
      )
    } finally {
      setDescargandoId(null)
    }
  }

  const columns = getMovimientoColumns({
    onVerDetalle: (m) => { setDetalleId(Number(m.id)); abrirDetalle() },
    onDescargarPdf: handleDescargarPdf,
    descargandoId,
  })

  return (
    <Stack gap="md">
      <SectionCard
        title="Historial de acciones"
        description="Registro inmutable de los movimientos y acciones de personal del servidor."
        actions={
          <Button
            size="xs" variant="light"
            leftSection={<IconPlus size={14} />}
            onClick={open}
          >
            Nueva acción de personal
          </Button>
        }
      >
        {!tipoNombramiento && (
          <StatusBadge tone="warning" mb="sm">
            Sin contrato vigente — no se pueden registrar acciones de personal
          </StatusBadge>
        )}

        <DataState
          loading={isLoading}
          error={error}
          empty={lista.length === 0}
          skeletonRows={3}
          emptyProps={{
            icon: IconHistory,
            title: 'Sin movimientos registrados',
            description: 'El historial de movimientos y acciones de personal aparecerá aquí.',
          }}
        >
          <SgthTable records={lista} columns={columns} minHeight={120} />
        </DataState>
      </SectionCard>

      <MovimientoModal
        opened={opened}
        onClose={close}
        servidorId={servidorId}
        tipoNombramiento={tipoNombramiento}
      />

      <AccionPersonalDetalleDrawer
        opened={detalleOpened}
        onClose={cerrarDetalle}
        movimientoId={detalleId}
      />
    </Stack>
  )
}
