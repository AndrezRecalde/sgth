'use client'

import { useState } from 'react'
import { Box, Group, TextInput, Button, Badge, Text, Skeleton, Alert, Accordion } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconSearch, IconList, IconAlertCircle, IconEdit } from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SgthTable } from '@/components/ui/SgthTable'
import { useListaSeguimientoPrograma } from '../hooks/useProgramaDrogas'
import { CatalogoActividadesProgramaModal } from './CatalogoActividadesProgramaModal'
import { RegistrarSeguimientoProgramaModal } from './RegistrarSeguimientoProgramaModal'
import { ESTADO_ACTIVIDAD_PROGRAMA_COLORS, ESTADO_ACTIVIDAD_PROGRAMA_LABELS } from '../schemas/programaDrogas.schema'
import { formatFecha } from '../utils/fecha'
import type { FilaSeguimientoPrograma } from '../services/programaDrogasService'
import type { DataTableColumn } from 'mantine-datatable'

export function ProgramaDrogasTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [catalogoOpened, { open: openCatalogo, close: closeCatalogo }] = useDisclosure(false)
  const [seguimientoOpened, { open: openSeguimiento, close: closeSeguimiento }] = useDisclosure(false)
  const [filaSeleccionada, setFilaSeleccionada] = useState<FilaSeguimientoPrograma | null>(null)

  const { data: lista, isLoading } = useListaSeguimientoPrograma(periodo)

  const handleConsultar = () => {
    if (/^\d{4}(-\d{2})?$/.test(periodoInput)) setPeriodo(periodoInput)
  }

  const handleEditar = (fila: FilaSeguimientoPrograma) => {
    setFilaSeleccionada(fila)
    openSeguimiento()
  }

  const columns: DataTableColumn<FilaSeguimientoPrograma>[] = [
    { accessor: 'actividad.nombre', title: 'Actividad' },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 130,
      render: (fila) => (
        <Badge color={ESTADO_ACTIVIDAD_PROGRAMA_COLORS[fila.estado] ?? 'gray'} variant="light" size="sm">
          {ESTADO_ACTIVIDAD_PROGRAMA_LABELS[fila.estado] ?? fila.estado}
        </Badge>
      ),
    },
    {
      accessor: 'seguimiento.fecha_ejecucion',
      title: 'Fecha',
      width: 110,
      render: (fila) => formatFecha(fila.seguimiento?.fecha_ejecucion),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 110,
      render: (fila) => (
        <Button size="xs" variant="subtle" leftSection={<IconEdit size={14} />} onClick={() => handleEditar(fila)}>
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
        <Button leftSection={<IconList size={16} />} variant="default" onClick={openCatalogo}>
          Catálogo de actividades
        </Button>
      </Group>

      <Text size="sm" c="dimmed" mb="md">
        Matriz de las 6 fases del Programa de prevención integral de drogas (Instructivo MDT-MSP-2019-038).
      </Text>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="blue" variant="light">
          Ingrese un período y presione Consultar para ver la matriz de seguimiento del programa.
        </Alert>
      )}

      {periodo && isLoading && <Skeleton height={300} radius="md" />}

      {periodo && !isLoading && lista && (
        <>
          <Group gap="lg" mb="md">
            <Text size="sm">Total: <Text span fw={600}>{lista.totales.total}</Text></Text>
            <Text size="sm" c="emerald">Ejecutadas: <Text span fw={600}>{lista.totales.ejecutada}</Text></Text>
            <Text size="sm" c="yellow.7">En proceso: <Text span fw={600}>{lista.totales.en_proceso}</Text></Text>
            <Text size="sm" c="red">No ejecutadas: <Text span fw={600}>{lista.totales.no_ejecutada}</Text></Text>
            <Text size="sm" c="dimmed">Pendientes: <Text span fw={600}>{lista.totales.pendiente}</Text></Text>
          </Group>

          <Accordion multiple defaultValue={Object.keys(lista.por_fase)} variant="separated">
            {Object.entries(lista.por_fase)
              .sort(([, a], [, b]) => a.orden - b.orden)
              .map(([faseKey, fase]) => (
                <Accordion.Item key={faseKey} value={faseKey}>
                  <Accordion.Control>
                    <Group justify="space-between" pr="md">
                      <Text fw={600} size="sm">{fase.etiqueta}</Text>
                      <Badge variant="light" size="sm">{fase.filas.length} actividades</Badge>
                    </Group>
                  </Accordion.Control>
                  <Accordion.Panel>
                    <SgthTable
                      records={fase.filas}
                      columns={columns}
                      idAccessor="actividad.id"
                      minHeight={80}
                      noRecordsText="Sin actividades registradas en esta fase."
                    />
                  </Accordion.Panel>
                </Accordion.Item>
              ))}
          </Accordion>
        </>
      )}

      <CatalogoActividadesProgramaModal opened={catalogoOpened} onClose={closeCatalogo} />
      <RegistrarSeguimientoProgramaModal
        opened={seguimientoOpened}
        onClose={() => { setFilaSeleccionada(null); closeSeguimiento() }}
        fila={filaSeleccionada}
        periodo={periodo ?? ''}
      />
    </Box>
  )
}
