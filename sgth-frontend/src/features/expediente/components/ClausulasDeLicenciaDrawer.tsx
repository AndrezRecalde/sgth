'use client'

import { useState } from 'react'
import { Button, Stack, Text } from '@mantine/core'
import { IconEdit, IconFileText, IconPlus, IconTrash } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { DataState, SgthDrawer, SgthTable, TableActions, confirmar, type TableAction } from '@/components/ui'
import { useCausasLicencia } from '@/features/asistencia/hooks/useLicencias'
import { useEliminarClausulaDeLicencia } from '../hooks/useContratoColectivo'
import type { ClausulaDeLicencia, ContratoColectivo } from '../services/contratoColectivoService'
import { ClausulaDeLicenciaModal } from './ClausulaDeLicenciaModal'

interface Props {
  contrato: ContratoColectivo | null
  puedeCargar: boolean
  onClose: () => void
}

/**
 * Las licencias que pacta un contrato colectivo (fase 5.3): las que amplían
 * una causa de la ley y las que la ley no tiene. Se aplican a los obreros
 * mientras el contrato rija con el dictamen de Finanzas; la que ya respalda
 * una licencia no se cambia ni se borra.
 */
export function ClausulasDeLicenciaDrawer({ contrato, puedeCargar, onClose }: Props) {
  const causas = useCausasLicencia().data ?? []
  const eliminar = useEliminarClausulaDeLicencia()
  const [abierta, setAbierta] = useState<{ clausula: ClausulaDeLicencia | null } | null>(null)

  const clausulas = contrato?.clausulas_de_licencia ?? []

  const licenciaDe = (c: ClausulaDeLicencia) => {
    if (!c.causa) return { titulo: c.nombre ?? '—', detalle: 'Causa que la ley no tiene' }

    const causa = causas.find((x) => x.codigo === c.causa)
    const caso = c.alcance ? causa?.alcances.find((a) => a.codigo === c.alcance) : undefined
    const legal = caso ? caso.tope : causa?.tope

    return {
      titulo: (causa?.etiqueta ?? c.causa) + (caso ? ` · ${caso.etiqueta}` : ''),
      detalle: legal != null ? `Amplía la ley, que da ${legal} día(s)` : 'Amplía la ley',
    }
  }

  const quitar = (c: ClausulaDeLicencia) => {
    if (!contrato) return

    confirmar({
      title: 'Eliminar la cláusula',
      message: `Se elimina la ${c.clausula}. Si ya respalda alguna licencia, no se puede: el contrato colectivo nuevo la reemplaza.`,
      confirmLabel: 'Eliminar',
      destructiva: true,
      onConfirm: () => eliminar.mutate({ contratoId: contrato.id, id: c.id }),
    })
  }

  const accionesDe = (c: ClausulaDeLicencia): TableAction[] => (puedeCargar
    ? [
      { label: 'Corregir', icon: <IconEdit size={14} />, onClick: () => setAbierta({ clausula: c }) },
      { label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', onClick: () => quitar(c) },
    ]
    : [])

  const columnas: DataTableColumn<ClausulaDeLicencia>[] = [
    { accessor: 'clausula', title: 'Cláusula', width: 120 },
    {
      accessor: 'causa',
      title: 'Licencia',
      render: (c) => {
        const { titulo, detalle } = licenciaDe(c)

        return (
          <div>
            <Text size="sm">{titulo}</Text>
            <Text size="xs" c="dimmed">{detalle}</Text>
          </div>
        )
      },
    },
    {
      accessor: 'dias',
      title: 'Días',
      width: 130,
      render: (c) => (
        <div>
          <Text size="sm">{c.dias} por licencia</Text>
          {c.dias_por_anio != null && <Text size="xs" c="dimmed">{c.dias_por_anio} al año</Text>}
        </div>
      ),
    },
    { accessor: 'acciones', title: '', width: 50, render: (c) => <TableActions actions={accionesDe(c)} /> },
  ]

  return (
    <SgthDrawer
      opened={!!contrato}
      onClose={onClose}
      title="Licencias pactadas"
      description={contrato ? `Contrato colectivo N.º ${contrato.numero}` : undefined}
      ancho="lg"
    >
      {contrato && (
        <Stack gap="md">
          <Text size="sm" c="dimmed">
            Se aplican a los obreros mientras el contrato rija con el dictamen del Ministerio
            de Finanzas{contrato.tiene_dictamen ? '' : ' —este todavía no lo tiene—'}. Las que
            amplían una causa de la ley ya cuentan en sus topes; las otras aparecen como causas
            propias al registrar la licencia.
          </Text>

          {puedeCargar && (
            <div>
              <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => setAbierta({ clausula: null })}>
                Agregar cláusula
              </Button>
            </div>
          )}

          <DataState
            loading={false}
            empty={!clausulas.length}
            emptyProps={{
              icon: IconFileText,
              title: 'Sin licencias pactadas',
              description: 'Con este contrato, los obreros tienen las licencias de la ley.',
            }}
          >
            <SgthTable records={clausulas} columns={columnas} minHeight={120} />
          </DataState>

          <ClausulaDeLicenciaModal
            opened={!!abierta}
            contrato={contrato}
            clausula={abierta?.clausula ?? null}
            onClose={() => setAbierta(null)}
          />
        </Stack>
      )}
    </SgthDrawer>
  )
}
