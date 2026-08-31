'use client'

import { confirmar } from '@/components/ui'
import { useState } from 'react'
import {
  Modal, Stack, Group, Select, NumberInput, Button,
  ActionIcon, Divider,
} from '@mantine/core'
import { IconTrash, IconPlus } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'
import { SgthTable } from '@/components/ui/SgthTable'
import { BuscarPuestoSelect } from '@/features/estructura/components/BuscarPuestoSelect'
import { useEquiposPorPuesto, usePuestoEppMutations } from '../hooks/usePuestoEpp'
import { useEquiposProteccion } from '../hooks/useEquiposProteccion'
import type { PuestoEpp } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

interface Props {
  opened: boolean
  onClose: () => void
}

export function AsignarEppPuestoModal({ opened, onClose }: Props) {
  const { isMobile } = useMobileBreakpoint()
  const contained = useContainedInput()

  const [puestoId, setPuestoId] = useState<number | null>(null)
  const [equipoId, setEquipoId] = useState<string | null>(null)
  const [cantidad, setCantidad] = useState<number | ''>(1)
  const [frecuencia, setFrecuencia] = useState<number | ''>('')

  const { data: asignaciones = [], isLoading } = useEquiposPorPuesto(puestoId)
  const { asignar, eliminar } = usePuestoEppMutations(puestoId)
  const { data: equiposData } = useEquiposProteccion({ estado: true })
  const equipoOptions = (equiposData?.data ?? []).map(e => ({ value: String(e.id), label: `${e.codigo} — ${e.nombre}` }))

  const handleClose = () => {
    setPuestoId(null)
    setEquipoId(null)
    setCantidad(1)
    setFrecuencia('')
    onClose()
  }

  const handleAsignar = () => {
    if (!equipoId || !cantidad) return
    asignar.mutate({
      equipo_proteccion_id: Number(equipoId),
      cantidad_requerida: Number(cantidad),
      frecuencia_reposicion_meses: frecuencia ? Number(frecuencia) : undefined,
    }, {
      onSuccess: () => {
        setEquipoId(null)
        setCantidad(1)
        setFrecuencia('')
      },
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
        <ActionIcon
          color="red"
          variant="subtle"
          onClick={() => confirmar({
            title:   'Eliminar asignación',
            message: 'Se eliminará esta asignación de equipo al puesto. No se puede deshacer.',
            destructiva: true,
            onConfirm: () => eliminar.mutate(a.id),
          })}
        >
          <IconTrash size={16} />
        </ActionIcon>
      ),
    },
  ]

  return (
    <Modal
      opened={opened}
      onClose={handleClose}
      title="EPP requerido por puesto"
      size="lg"
      fullScreen={isMobile}
      radius={isMobile ? 0 : 'xl'}
    >
      <Stack gap="md">
        <BuscarPuestoSelect
          label="Puesto"
          value={puestoId}
          onChange={(id) => setPuestoId(id)}
        />

        {puestoId && (
          <>
            <Divider label="Agregar equipo requerido" labelPosition="left" />
            <Group align="flex-end" wrap="nowrap">
              <Select
                label="Equipo"
                placeholder="Seleccione un equipo"
                data={equipoOptions}
                searchable
                style={{ flex: 1 }}
                {...contained}
                value={equipoId}
                onChange={setEquipoId}
              />
              <NumberInput
                label="Cantidad"
                min={1}
                style={{ width: 100 }}
                {...contained}
                value={cantidad}
                onChange={(v) => setCantidad(typeof v === 'number' ? v : '')}
              />
              <NumberInput
                label="Reposición (meses)"
                min={1}
                style={{ width: 150 }}
                {...contained}
                value={frecuencia}
                onChange={(v) => setFrecuencia(typeof v === 'number' ? v : '')}
              />
              <Button
                leftSection={<IconPlus size={16} />}
                color="emerald"
                loading={asignar.isPending}
                onClick={handleAsignar}
                disabled={!equipoId}
              >
                Agregar
              </Button>
            </Group>

            <SgthTable
              records={asignaciones}
              columns={columns}
              fetching={isLoading}
              noRecordsText="Este puesto no tiene EPP requerido todavía."
              minHeight={120}
            />
          </>
        )}
      </Stack>
    </Modal>
  )
}
