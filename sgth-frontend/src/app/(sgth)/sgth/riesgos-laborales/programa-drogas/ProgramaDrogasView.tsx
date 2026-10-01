'use client'

import { useState } from 'react'
import { Group, TextInput, Button, Text, Alert, Accordion, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import {
  IconSearch, IconList, IconAlertCircle, IconEdit, IconChecklist,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, SgthTable, StatusBadge, Toolbar } from '@/components/ui'
import { useListaSeguimientoPrograma } from '../hooks/useProgramaDrogas'
import { CatalogoActividadesProgramaModal } from './CatalogoActividadesProgramaModal'
import { RegistrarSeguimientoProgramaModal } from './RegistrarSeguimientoProgramaModal'
import { TONO_ACTIVIDAD_PROGRAMA, ESTADO_ACTIVIDAD_PROGRAMA_LABELS } from '../schemas/programaDrogas.schema'
import { AYUDA_PERIODO, EJEMPLO_PERIODO, esPeriodoValido } from '../constants/periodo'
import { formatFecha } from '@/lib/fecha'
import type { FilaSeguimientoPrograma } from '../services/programaDrogasService'
import type { DataTableColumn } from 'mantine-datatable'

export function ProgramaDrogasTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [catalogoOpened, { open: openCatalogo, close: closeCatalogo }] = useDisclosure(false)
  const [seguimientoOpened, { open: openSeguimiento, close: closeSeguimiento }] = useDisclosure(false)
  const [filaSeleccionada, setFilaSeleccionada] = useState<FilaSeguimientoPrograma | null>(null)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data: lista, isLoading, error, refetch } = useListaSeguimientoPrograma(periodo)

  const handleConsultar = () => {
    if (esPeriodoValido(periodoInput)) setPeriodo(periodoInput)
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
        <StatusBadge tone={TONO_ACTIVIDAD_PROGRAMA[fila.estado] ?? 'neutral'}>
          {ESTADO_ACTIVIDAD_PROGRAMA_LABELS[fila.estado] ?? fila.estado}
        </StatusBadge>
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
      render: (fila) => puedeGestionar ? (
        <Button size="xs" variant="subtle" leftSection={<IconEdit size={14} />} onClick={() => handleEditar(fila)}>
          Registrar
        </Button>
      ) : null,
    },
  ]

  return (
    <Stack gap="md">
      <Toolbar
        actions={
          <>
            <Button
              leftSection={<IconSearch size={16} />}
              onClick={handleConsultar}
              disabled={!esPeriodoValido(periodoInput)}
            >
              Consultar
            </Button>
            {puedeGestionar && (
              <Button leftSection={<IconList size={16} />} variant="default" onClick={openCatalogo}>
                Catálogo de actividades
              </Button>
            )}
          </>
        }
      >
          <TextInput
            label="Período"
            placeholder={EJEMPLO_PERIODO}
            description={AYUDA_PERIODO}
            {...contained}
            value={periodoInput}
            onChange={(e) => setPeriodoInput(e.currentTarget.value)}
          />
      </Toolbar>

      <Text size="sm" c="dimmed" mb="md">
        Matriz de las 6 fases del Programa de prevención integral de drogas (Instructivo MDT-MSP-2019-038).
      </Text>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
          Ingrese un período y presione Consultar para ver la matriz de seguimiento del programa.
        </Alert>
      )}

      {periodo && (
        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar la matriz de seguimiento"
          errorHint="No quiere decir que no haya actividades registradas: no se pudo consultar."
          onRetry={() => refetch()}
          skeletonRows={6}
          empty={!lista?.totales.total}
          emptyProps={{
            icon: IconChecklist,
            title: 'No hay actividades en el catálogo del programa',
            description: 'La matriz se arma con las actividades activas de las 6 fases. Agréguelas para poder registrar su seguimiento.',
            action: puedeGestionar ? (
              <Button variant="light" leftSection={<IconList size={16} />} onClick={openCatalogo}>
                Catálogo de actividades
              </Button>
            ) : undefined,
          }}
        >
          {lista && (
            <>
              <Group gap="lg" mb="md">
                <Text size="sm">Total: <Text span fw={600}>{lista.totales.total}</Text></Text>
                <Text size="sm" c="emerald">Ejecutadas: <Text span fw={600}>{lista.totales.ejecutada}</Text></Text>
                <Text size="sm" c="amber.7">En proceso: <Text span fw={600}>{lista.totales.en_proceso}</Text></Text>
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
                          <StatusBadge>{fase.filas.length} actividades</StatusBadge>
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
        </DataState>
      )}

      <CatalogoActividadesProgramaModal opened={catalogoOpened} onClose={closeCatalogo} />
      <RegistrarSeguimientoProgramaModal
        opened={seguimientoOpened}
        onClose={() => { setFilaSeleccionada(null); closeSeguimiento() }}
        fila={filaSeleccionada}
        periodo={periodo ?? ''}
      />
    </Stack>
  )
}
