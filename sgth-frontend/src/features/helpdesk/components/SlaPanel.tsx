'use client'

import { useState } from 'react'
import { Button, Text } from '@mantine/core'
import { IconClockHour4, IconEdit, IconPlus, IconTrash } from '@tabler/icons-react'
import { DataState, SectionCard, SgthTable, confirmar } from '@/components/ui'
import { ETIQUETA_PRIORIDAD, PRIORIDADES } from '../constants/estados'
import { useConfiguracionMutations, useTablaSla } from '../hooks/useConfiguracionHelpdesk'
import { configuracionHelpdeskService } from '../services/configuracionService'
import type { GuardarSlaData, TablaSla } from '../services/tipos'
import { columnasSla, type FilaSla } from './configuracion.columns'
import { SlaModal } from './SlaModal'

/**
 * Primero el plazo general de cada prioridad —el fijado por TIC o, si no hay,
 * el de fábrica— y después lo que afina cada categoría.
 */
function filasDe(tabla: TablaSla | undefined): FilaSla[] {
  if (!tabla) return []
  const generales = PRIORIDADES.map((prioridad): FilaSla => {
    const regla = tabla.reglas.find((r) => r.categoria_id === null && r.prioridad === prioridad) ?? null
    const defecto = tabla.por_defecto[prioridad]
    return {
      clave: `general-${prioridad}`, prioridad, categoria: null, regla,
      respuesta: regla?.tiempo_respuesta_horas ?? defecto.respuesta,
      resolucion: regla?.tiempo_resolucion_horas ?? defecto.resolucion,
    }
  })
  const porCategoria = tabla.reglas.filter((r) => r.categoria_id !== null).map((r): FilaSla => ({
    clave: `regla-${r.id}`, prioridad: r.prioridad, categoria: r.categoria?.nombre ?? '—', regla: r,
    respuesta: r.tiempo_respuesta_horas, resolucion: r.tiempo_resolucion_horas,
  }))
  return [...generales, ...porCategoria]
}

type Edicion = { abierta: boolean; id?: number; valores?: GuardarSlaData }

/**
 * La tabla del SLA en horas hábiles (D7). Cambiarla rige para los tickets
 * nuevos y los que cambian de prioridad: los demás conservan su plazo.
 */
export function SlaPanel() {
  const { data, isLoading, error } = useTablaSla()
  const { eliminar } = useConfiguracionMutations('sla', configuracionHelpdeskService.sla)
  const [edicion, setEdicion] = useState<Edicion>({ abierta: false })
  const filas = filasDe(data)

  const valoresDe = (f: FilaSla): GuardarSlaData => ({
    prioridad: f.prioridad, categoria_id: f.regla?.categoria_id ?? null,
    tiempo_respuesta_horas: f.respuesta, tiempo_resolucion_horas: f.resolucion,
  })

  return (
    <SectionCard
      title="Plazos de atención (SLA)"
      description="En horas hábiles: de lunes a viernes, en la jornada de TIC y sin feriados. Mientras se espera al usuario, el plazo se pausa."
      actions={<Button variant="light" leftSection={<IconPlus size={16} />} onClick={() => setEdicion({ abierta: true })}>Plazo por categoría</Button>}
    >
      <DataState loading={isLoading} error={error} empty={!filas.length}
        emptyProps={{ icon: IconClockHour4, title: 'Sin plazos', description: 'No se pudo leer la tabla del SLA.' }}>
        <SgthTable
          idAccessor="clave"
          records={filas}
          columns={columnasSla((f) => [
            {
              label: f.regla ? 'Editar' : 'Fijar otro plazo', icon: <IconEdit size={14} />,
              onClick: () => setEdicion({ abierta: true, id: f.regla?.id, valores: valoresDe(f) }),
            },
            {
              label: 'Eliminar', icon: <IconTrash size={14} />, color: 'red', hidden: !f.regla,
              onClick: () => confirmar({
                title: 'Eliminar plazo',
                message: f.categoria
                  ? <>La categoría <b>{f.categoria}</b> pasará a usar el plazo general de prioridad {ETIQUETA_PRIORIDAD[f.prioridad].toLowerCase()}.</>
                  : <>La prioridad {ETIQUETA_PRIORIDAD[f.prioridad].toLowerCase()} volverá al plazo por defecto.</>,
                destructiva: true, onConfirm: () => f.regla && eliminar.mutate(f.regla.id),
              }),
            },
          ])}
        />
      </DataState>
      <Text size="xs" c="dimmed" mt="xs">
        Un ticket vencido sube de nivel solo; la alerta sale cuando le queda la cuarta parte del plazo de resolución.
      </Text>
      <SlaModal opened={edicion.abierta} onClose={() => setEdicion({ abierta: false })} id={edicion.id} valores={edicion.valores} />
    </SectionCard>
  )
}
