'use client'

import { SimpleGrid, Stack } from '@mantine/core'
import { IconDevices, IconHourglassLow, IconTool, IconTrashX } from '@tabler/icons-react'
import { SectionCard, StatCard } from '@/components/ui'
import { ESTADO_BIEN_LABELS, TONO_ESTADO_BIEN } from '@/features/inventario-ti/constants/estados'
import type { EstadoBien } from '@/features/inventario-ti/services/tipos'
import { TRAMO_ANTIGUEDAD } from '../constants/etiquetas'
import type { TableroEquipos as Datos, TramoAntiguedad } from '../services/tableroService'
import { Reparto } from './Reparto'
import { VigenciasProximas } from './VigenciasProximas'

const ORDEN_ESTADOS: EstadoBien[] = ['en_uso', 'disponible', 'en_mantenimiento', 'en_garantia', 'extraviado', 'dado_de_baja']

/**
 * El inventario en el tablero: cuántos equipos hay en servicio, dónde están,
 * qué edad tienen y qué contratos del proveedor vencen pronto.
 */
export function TableroEquipos({ datos }: { datos: Datos }) {
  const enReparacion = datos.por_estado.en_mantenimiento + datos.por_estado.en_garantia
  const estados = ORDEN_ESTADOS.map((e) => ({ clave: e, etiqueta: ESTADO_BIEN_LABELS[e], total: datos.por_estado[e], tono: TONO_ESTADO_BIEN[e] }))
  const tramos = (Object.keys(TRAMO_ANTIGUEDAD) as TramoAntiguedad[]).map((t) => ({
    clave: t, etiqueta: TRAMO_ANTIGUEDAD[t].etiqueta, total: datos.antiguedad[t], tono: TRAMO_ANTIGUEDAD[t].tono,
  }))

  return (
    <Stack gap="md">
      <SimpleGrid cols={{ base: 1, xs: 2, lg: 4 }} spacing="md">
        <StatCard label="Equipos en servicio" value={datos.en_servicio} icon={IconDevices} hint="Sin contar los dados de baja" />
        <StatCard label="En reparación" value={enReparacion} icon={IconTool}
          hint={`${datos.por_estado.en_mantenimiento} en TIC · ${datos.por_estado.en_garantia} con el proveedor`} />
        <StatCard label="Vida útil cumplida" value={datos.vida_util_cumplida} icon={IconHourglassLow}
          tone={datos.vida_util_cumplida ? 'warning' : undefined} hint="Siguen en servicio" />
        {datos.bajas_por_resolver !== null && (
          <StatCard label="Bajas por resolver" value={datos.bajas_por_resolver} icon={IconTrashX}
            tone={datos.bajas_por_resolver ? 'warning' : undefined} hint="Informes técnicos propuestos" />
        )}
      </SimpleGrid>

      <SimpleGrid cols={{ base: 1, lg: 3 }} spacing="md">
        <SectionCard title="Por estado" description="Los equipos principales; sus componentes van con ellos.">
          <Reparto filas={estados} vacio="Todavía no hay equipos registrados." />
        </SectionCard>
        <SectionCard title="Por unidad" description="La de quien lo tiene; si nadie lo tiene, la del equipo.">
          <Reparto filas={datos.por_unidad.map((u) => ({ clave: `${u.unidad_id ?? u.unidad}`, etiqueta: u.unidad, total: u.total }))}
            vacio="Todavía no hay equipos en servicio." />
        </SectionCard>
        <SectionCard title="Antigüedad" description="Años desde la compra, de los equipos en servicio.">
          <Reparto filas={tramos} vacio="Todavía no hay equipos en servicio." />
        </SectionCard>
      </SimpleGrid>

      <VigenciasProximas contratos={datos.contratos_por_vencer} ventanas={datos.ventanas_proximas} />
    </Stack>
  )
}
