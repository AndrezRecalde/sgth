'use client'

import { useState } from 'react'
import {
  Box, Group, TextInput, Button, Badge, Text,
  Skeleton, Alert,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconSearch, IconList, IconAlertCircle, IconEdit } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SgthTable } from '@/components/ui/SgthTable'
import { useListaVerificacion } from '../hooks/useCumplimiento'
import { NormativaLegalModal } from './NormativaLegalModal'
import { RegistrarCumplimientoModal } from './RegistrarCumplimientoModal'
import { TIPO_NORMATIVA_OPTIONS } from '../schemas/normativaLegal.schema'
import { ESTADO_CUMPLIMIENTO_COLORS, ESTADO_CUMPLIMIENTO_LABELS } from '../schemas/cumplimiento.schema'
import type { FilaListaVerificacion } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function CumplimientoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [normativasOpened, { open: openNormativas, close: closeNormativas }] = useDisclosure(false)
  const [cumplimientoOpened, { open: openCumplimiento, close: closeCumplimiento }] = useDisclosure(false)
  const [filaSeleccionada, setFilaSeleccionada] = useState<FilaListaVerificacion | null>(null)

  const { data: lista, isLoading } = useListaVerificacion(periodo)

  const getTipoLabel = (valor: string) =>
    TIPO_NORMATIVA_OPTIONS.find(o => o.value === valor)?.label ?? valor

  const handleConsultar = () => {
    if (/^\d{4}(-\d{2})?$/.test(periodoInput)) {
      setPeriodo(periodoInput)
    }
  }

  const handleEditar = (fila: FilaListaVerificacion) => {
    setFilaSeleccionada(fila)
    openCumplimiento()
  }

  const columns: DataTableColumn<FilaListaVerificacion>[] = [
    { accessor: 'normativa.nombre', title: 'Normativa' },
    {
      accessor: 'normativa.tipo',
      title: 'Tipo',
      render: (fila) => (
        <Badge variant="light" size="sm">{getTipoLabel(fila.normativa.tipo)}</Badge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      render: (fila) => (
        <Badge color={ESTADO_CUMPLIMIENTO_COLORS[fila.estado] ?? 'gray'} variant="light" size="sm">
          {ESTADO_CUMPLIMIENTO_LABELS[fila.estado] ?? fila.estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 120,
      render: (fila) => (
        <Button
          size="xs"
          variant="subtle"
          leftSection={<IconEdit size={14} />}
          onClick={() => handleEditar(fila)}
        >
          Registrar
        </Button>
      ),
    },
  ]

  return (
    <Box>
      <Group justify="space-between" mb="md" align="flex-end">
        <Group align="flex-end">
          <TextInput
            label="Período"
            placeholder="2026 o 2026-07"
            description="Formato AAAA (año) o AAAA-MM (mes)"
            {...contained}
            value={periodoInput}
            onChange={(e) => setPeriodoInput(e.currentTarget.value)}
          />
          <Button
            leftSection={<IconSearch size={16} />}
            color="emerald"
            onClick={handleConsultar}
            disabled={!/^\d{4}(-\d{2})?$/.test(periodoInput)}
          >
            Consultar
          </Button>
        </Group>
        <Button leftSection={<IconList size={16} />} variant="default" onClick={openNormativas}>
          Catálogo de normativas
        </Button>
      </Group>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="blue" variant="light">
          Ingrese un período y presione Consultar para ver la lista de verificación de cumplimiento.
        </Alert>
      )}

      {periodo && isLoading && <Skeleton height={200} radius="md" />}

      {periodo && !isLoading && lista && (
        <>
          <Group gap="lg" mb="sm">
            <Text size="sm">Total: <Text span fw={600}>{lista.totales.total}</Text></Text>
            <Text size="sm" c="emerald">Cumple: <Text span fw={600}>{lista.totales.cumple}</Text></Text>
            <Text size="sm" c="red">No cumple: <Text span fw={600}>{lista.totales.no_cumple}</Text></Text>
            <Text size="sm" c="yellow.7">En proceso: <Text span fw={600}>{lista.totales.en_proceso}</Text></Text>
            <Text size="sm" c="dimmed">Sin registrar: <Text span fw={600}>{lista.totales.no_registrado}</Text></Text>
          </Group>

          <SgthTable
            records={lista.filas}
            columns={columns}
            idAccessor="normativa.id"
            noRecordsText='No hay normativas registradas en el catálogo. Agréguelas desde "Catálogo de normativas".'
            minHeight={150}
          />
        </>
      )}

      <NormativaLegalModal opened={normativasOpened} onClose={closeNormativas} />
      <RegistrarCumplimientoModal
        opened={cumplimientoOpened}
        onClose={() => { setFilaSeleccionada(null); closeCumplimiento() }}
        fila={filaSeleccionada}
        periodo={periodo ?? ''}
      />
    </Box>
  )
}
