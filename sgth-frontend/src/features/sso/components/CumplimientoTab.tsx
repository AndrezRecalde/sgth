'use client'

import { useState } from 'react'
import {
  Group, TextInput, Button, Text, Stack, Alert,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useAuth } from '@/hooks/useAuth'
import {
  IconSearch, IconList, IconAlertCircle, IconEdit, IconClipboardCheck,
} from '@tabler/icons-react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { DataState, SgthTable, StatusBadge, Toolbar } from '@/components/ui'
import { useListaVerificacion } from '../hooks/useCumplimiento'
import { NormativaLegalModal } from './NormativaLegalModal'
import { RegistrarCumplimientoModal } from './RegistrarCumplimientoModal'
import { TIPO_NORMATIVA_OPTIONS } from '../schemas/normativaLegal.schema'
import { TONO_ESTADO_CUMPLIMIENTO, ESTADO_CUMPLIMIENTO_LABELS } from '../schemas/cumplimiento.schema'
import type { FilaListaVerificacion } from '../services/ssoService'
import type { DataTableColumn } from 'mantine-datatable'

export function CumplimientoTab() {
  const contained = useContainedInput()
  const [periodoInput, setPeriodoInput] = useState('')
  const [periodo, setPeriodo] = useState<string | null>(null)
  const [normativasOpened, { open: openNormativas, close: closeNormativas }] = useDisclosure(false)
  const [cumplimientoOpened, { open: openCumplimiento, close: closeCumplimiento }] = useDisclosure(false)
  const [filaSeleccionada, setFilaSeleccionada] = useState<FilaListaVerificacion | null>(null)

  // Las acciones siguen la misma matriz que la API: el módulo se abre con
  // `ver-reportes-sso` o con `gestionar-sso`, pero solo el segundo escribe.
  // Ofrecerlas a quien solo lee serviría para que recibiera un 403.
  const { hasPermiso } = useAuth()
  const puedeGestionar = hasPermiso('gestionar-sso')

  const { data: lista, isLoading, error, refetch } = useListaVerificacion(periodo)

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
        <StatusBadge>{getTipoLabel(fila.normativa.tipo)}</StatusBadge>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      render: (fila) => (
        <StatusBadge tone={TONO_ESTADO_CUMPLIMIENTO[fila.estado] ?? 'neutral'}>
          {ESTADO_CUMPLIMIENTO_LABELS[fila.estado] ?? fila.estado}
        </StatusBadge>
      ),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 120,
      render: (fila) => puedeGestionar ? (
        <Button
          size="xs"
          variant="subtle"
          leftSection={<IconEdit size={14} />}
          onClick={() => handleEditar(fila)}
        >
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
              disabled={!/^\d{4}(-\d{2})?$/.test(periodoInput)}
            >
              Consultar
            </Button>
            {puedeGestionar && (
              <Button leftSection={<IconList size={16} />} variant="default" onClick={openNormativas}>
                Catálogo de normativas
              </Button>
            )}
          </>
        }
      >
          <TextInput
            label="Período"
            placeholder="2026 o 2026-07"
            description="Formato AAAA (año) o AAAA-MM (mes)"
            {...contained}
            value={periodoInput}
            onChange={(e) => setPeriodoInput(e.currentTarget.value)}
          />
      </Toolbar>

      {!periodo && (
        <Alert icon={<IconAlertCircle size={18} />} color="ocean" variant="light">
          Ingrese un período y presione Consultar para ver la lista de verificación de cumplimiento.
        </Alert>
      )}

      {periodo && (
        <DataState
          loading={isLoading}
          error={error}
          errorTitle="No se pudo cargar la lista de verificación"
          errorHint="No quiere decir que no haya normativa registrada: no se pudo consultar."
          onRetry={() => refetch()}
          empty={!lista?.filas.length}
          emptyProps={{
            icon: IconClipboardCheck,
            title: 'No hay normativa en el catálogo',
            description: 'La lista de verificación se arma con la normativa activa. Agréguela para poder registrar su cumplimiento.',
            action: puedeGestionar ? (
              <Button variant="light" leftSection={<IconList size={16} />} onClick={openNormativas}>
                Catálogo de normativas
              </Button>
            ) : undefined,
          }}
        >
          {lista && (
            <>
              <Group gap="lg" mb="sm">
                <Text size="sm">Total: <Text span fw={600}>{lista.totales.total}</Text></Text>
                <Text size="sm" c="emerald">Cumple: <Text span fw={600}>{lista.totales.cumple}</Text></Text>
                <Text size="sm" c="red">No cumple: <Text span fw={600}>{lista.totales.no_cumple}</Text></Text>
                <Text size="sm" c="amber.7">En proceso: <Text span fw={600}>{lista.totales.en_proceso}</Text></Text>
                <Text size="sm" c="dimmed">Sin registrar: <Text span fw={600}>{lista.totales.no_registrado}</Text></Text>
              </Group>

              <SgthTable
                records={lista.filas}
                columns={columns}
                idAccessor="normativa.id"
                minHeight={150}
              />
            </>
          )}
        </DataState>
      )}

      <NormativaLegalModal opened={normativasOpened} onClose={closeNormativas} />
      <RegistrarCumplimientoModal
        opened={cumplimientoOpened}
        onClose={() => { setFilaSeleccionada(null); closeCumplimiento() }}
        fila={filaSeleccionada}
        periodo={periodo ?? ''}
      />
    </Stack>
  )
}
