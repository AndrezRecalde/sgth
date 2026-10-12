'use client'

import { useState } from 'react'
import { Alert, Button, Stack, Text } from '@mantine/core'
import { IconCalendarPlus, IconDownload, IconEdit, IconFileText, IconInfoCircle, IconPlus } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import {
  DataState, DetailList, SectionHeading, SgthTable, StatusBadge, TableActions, notificar, type TableAction,
} from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { guardarArchivo } from '@/lib/archivo'
import { formatFecha } from '@/lib/fecha'
import { getApiErrorMessage } from '@/types/api'
import { useContratoColectivo } from '../hooks/useContratoColectivo'
import {
  contratoColectivoService, type ContratoColectivo, type SalarioBasico,
} from '../services/contratoColectivoService'
import { dinero } from '../utils/dinero'
import { BloqueDetalle } from './BloqueDetalle'
import { ClausulasDeLicenciaDrawer } from './ClausulasDeLicenciaDrawer'
import { ContratoColectivoModal } from './ContratoColectivoModal'
import { SalarioBasicoModal } from './SalarioBasicoModal'

const vigencia = (c: ContratoColectivo) =>
  `Desde el ${formatFecha(c.vigencia_desde)}${c.vigencia_hasta ? ` hasta el ${formatFecha(c.vigencia_hasta)}` : ', sin fecha de fin'}`

const clausula = (v: string | number | null, unidad: string) => (v === null || Number(v) === 0 ? 'Sin cláusula' : `${Number(v)} ${unidad}`)

/**
 * El contrato colectivo de los obreros y el salario básico (diseño, 9.15; fase
 * 4.6). Solo se aplica el que rige con el dictamen del Ministerio de Finanzas:
 * sin él, rige la ley sola. Lo consulta Talento Humano y lo carga su director.
 */
export function ContratoColectivoPanel() {
  const { data, isLoading, error, refetch } = useContratoColectivo()
  const puedeCargar = useAuth().hasRole('admin-uath')

  const [contratoAbierto, setContratoAbierto] = useState<{ contrato: ContratoColectivo | null } | null>(null)
  const [salarioAbierto, setSalarioAbierto] = useState<{ salario: SalarioBasico | null } | null>(null)
  // Por id y no el objeto: tras guardar una cláusula, el contrato se relee.
  const [licenciasDe, setLicenciasDe] = useState<number | null>(null)

  const vigente = data?.vigente ?? null
  const contratoDeLicencias = data?.contratos.find((c) => c.id === licenciasDe) ?? null

  const descargar = async (c: ContratoColectivo) => {
    try {
      guardarArchivo(await contratoColectivoService.descargar(c.id), c.documento_nombre || `contrato-colectivo-${c.numero}.pdf`)
    } catch (e) {
      notificar.error('No se pudo descargar el contrato colectivo', getApiErrorMessage(e))
    }
  }

  const accionesDe = (c: ContratoColectivo): TableAction[] => [
    ...(puedeCargar
      ? [{ label: 'Corregir', icon: <IconEdit size={14} />, onClick: () => setContratoAbierto({ contrato: c }) }]
      : []),
    { label: 'Licencias pactadas', icon: <IconCalendarPlus size={14} />, onClick: () => setLicenciasDe(c.id) },
    ...(c.tiene_documento
      ? [{ label: 'Descargar el PDF', icon: <IconDownload size={14} />, onClick: () => descargar(c) }]
      : []),
  ]

  const columnasContratos: DataTableColumn<ContratoColectivo>[] = [
    {
      accessor: 'numero',
      title: 'Contrato',
      render: (c) => (
        <div>
          <Text size="sm" fw={500}>N.º {c.numero}</Text>
          <Text size="xs" c="dimmed">{vigencia(c)}</Text>
        </div>
      ),
    },
    {
      accessor: 'dictamen_finanzas_numero',
      title: 'Dictamen de Finanzas',
      width: 230,
      render: (c) => (c.tiene_dictamen
        ? (
          <div>
            <StatusBadge tone="success">N.º {c.dictamen_finanzas_numero}</StatusBadge>
            <Text size="xs" c="dimmed" mt={2}>{formatFecha(c.dictamen_finanzas_fecha)}</Text>
          </div>
        )
        : <StatusBadge tone="warning">Sin dictamen: no se aplica</StatusBadge>),
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (c) => <TableActions actions={accionesDe(c)} />,
    },
  ]

  const columnasSalarios: DataTableColumn<SalarioBasico>[] = [
    { accessor: 'anio', title: 'Año', width: 90 },
    { accessor: 'monto', title: 'Salario básico', width: 150, render: (s) => dinero(s.monto) },
    { accessor: 'acuerdo', title: 'Acuerdo ministerial', render: (s) => s.acuerdo ?? '—' },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (s) => (
        <TableActions
          actions={puedeCargar
            ? [{ label: 'Corregir', icon: <IconEdit size={14} />, onClick: () => setSalarioAbierto({ salario: s }) }]
            : []}
        />
      ),
    },
  ]

  return (
    <Stack gap="lg">
      <Alert variant="light" color="ocean" icon={<IconInfoCircle size={16} />}>
        Las cláusulas del contrato colectivo cambian cálculos de los obreros: los días
        adicionales de vacaciones, las bonificaciones por jubilación y por retiro
        voluntario —con los topes del Mandato 2—, el incremento pactado y las licencias
        que amplía o añade. Solo se aplica
        el que rige con el dictamen del Ministerio de Finanzas; sin él, rige la ley sola.
      </Alert>

      <DataState
        loading={isLoading}
        error={error}
        errorTitle="No se pudo cargar el contrato colectivo"
        onRetry={refetch}
      >
        <Stack gap="lg">
          <BloqueDetalle>
            <SectionHeading title="El que rige hoy" mb="xs" />
            {vigente ? (
              <DetailList
                columnas={3}
                items={[
                  { label: 'Contrato', value: `N.º ${vigente.numero}` },
                  { label: 'Vigencia', value: vigencia(vigente) },
                  { label: 'Dictamen de Finanzas', value: `N.º ${vigente.dictamen_finanzas_numero} · ${formatFecha(vigente.dictamen_finanzas_fecha)}` },
                  { label: 'Días adicionales de vacaciones', value: clausula(vigente.dias_adicionales_vacaciones, 'días') },
                  { label: 'Bonificación por jubilación', value: clausula(vigente.bonificacion_jubilacion_sbu, 'salarios básicos por año') },
                  { label: 'Bonificación por retiro voluntario', value: clausula(vigente.bonificacion_retiro_sbu, 'salarios básicos por año') },
                  { label: 'Incremento pactado', value: clausula(vigente.incremento_pactado_porcentaje, '%') },
                  {
                    label: 'Licencias pactadas',
                    value: vigente.clausulas_de_licencia?.length
                      ? `${vigente.clausulas_de_licencia.length} cláusula(s)`
                      : 'Sin cláusula',
                  },
                ]}
              />
            ) : (
              <Text size="sm" c="dimmed">
                No hay un contrato colectivo vigente con el dictamen del Ministerio de Finanzas:
                los cálculos de los obreros siguen la ley sola.
              </Text>
            )}
          </BloqueDetalle>

          <div>
            <SectionHeading
              title="Contratos colectivos registrados"
              mb="xs"
              action={puedeCargar && (
                <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => setContratoAbierto({ contrato: null })}>
                  Registrar contrato
                </Button>
              )}
            />
            <DataState
              loading={false}
              empty={!data?.contratos.length}
              emptyProps={{
                icon: IconFileText,
                title: 'Sin contratos colectivos',
                description: 'Registre el contrato colectivo vigente cuando Talento Humano tenga la copia.',
              }}
            >
              <SgthTable records={data?.contratos ?? []} columns={columnasContratos} minHeight={120} />
            </DataState>
          </div>

          <div>
            <SectionHeading
              title="Salario básico unificado"
              mb="xs"
              action={puedeCargar && (
                <Button size="xs" variant="light" leftSection={<IconPlus size={14} />} onClick={() => setSalarioAbierto({ salario: null })}>
                  Registrar año
                </Button>
              )}
            />
            <Text size="xs" c="dimmed" mb="xs">
              Lo usan los topes del Mandato 2 en la liquidación de los obreros: sin el del año,
              la bonificación queda sin monto.
            </Text>
            <DataState
              loading={false}
              empty={!data?.salarios_basicos.length}
              emptyProps={{
                icon: IconFileText,
                title: 'Sin salarios básicos registrados',
                description: 'Registre el del año en curso.',
              }}
            >
              <SgthTable records={data?.salarios_basicos ?? []} columns={columnasSalarios} minHeight={120} />
            </DataState>
          </div>
        </Stack>
      </DataState>

      <ContratoColectivoModal
        opened={!!contratoAbierto}
        contrato={contratoAbierto?.contrato ?? null}
        onClose={() => setContratoAbierto(null)}
      />
      <SalarioBasicoModal
        opened={!!salarioAbierto}
        salario={salarioAbierto?.salario ?? null}
        onClose={() => setSalarioAbierto(null)}
      />
      <ClausulasDeLicenciaDrawer
        contrato={contratoDeLicencias}
        puedeCargar={puedeCargar}
        onClose={() => setLicenciasDe(null)}
      />
    </Stack>
  )
}
