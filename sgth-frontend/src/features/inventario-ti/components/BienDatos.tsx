'use client'

import { Stack } from '@mantine/core'
import { DetailList, SectionCard, StatusBadge } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import { CONDICION_LABELS, TONO_CONDICION } from '../constants/estados'
import { CAMPOS_CARACTERISTICA, CAMPOS_POR_FICHA, valorCaracteristica, type CampoRed, type ClaveCaracteristica } from '../constants/fichas'
import type { BienInformatico } from '../services/tipos'

const RED: Record<CampoRed, string> = {
  nombre_red: 'Nombre en la red',
  usuario_dominio: 'Usuario del dominio',
  direccion_ip: 'Dirección IP',
}

/** La ficha del equipo en tres bloques, como el formulario. */
export function BienDatos({ bien }: { bien: BienInformatico }) {
  const c = bien.caracteristicas_tecnicas ?? {}
  const campos = CAMPOS_POR_FICHA[bien.tipo?.ficha ?? 'general']
  // Las de su ficha, y cualquier otra que tenga guardada (de cuando era de otro tipo).
  const claves = [...new Set([...campos.caracteristicas, ...Object.keys(c)])]

  return (
    <Stack gap="lg">
      <SectionCard title="Identificación">
        <DetailList columnas={3} items={[
          { label: 'Código institucional', value: bien.codigo_institucional },
          { label: 'Código anterior', value: bien.codigo_anterior },
          { label: 'Número de serie', value: bien.numero_serie ?? 'S/N' },
          { label: 'Tipo', value: [bien.tipo?.nombre, bien.subtipo?.nombre].filter(Boolean).join(' · ') },
          { label: 'Marca', value: bien.marca?.nombre },
          { label: 'Modelo', value: bien.modelo },
          { label: 'Fecha de compra', value: bien.fecha_compra ? formatFecha(bien.fecha_compra) : null },
          {
            label: 'Fin de la vida útil',
            value: bien.fecha_fin_vida_util
              ? <>{formatFecha(bien.fecha_fin_vida_util)} {bien.vida_util_cumplida ? <StatusBadge tone="warning">Cumplida</StatusBadge> : null}</>
              : null,
          },
          { label: 'Contrato de vigencia', value: bien.contrato_vigencia?.proceso },
        ]} />
      </SectionCard>

      <SectionCard title="Custodia y ubicación">
        <DetailList columnas={3} items={[
          { label: 'Custodio', value: bien.custodio ? `${bien.custodio.nombre} (${bien.custodio.cedula})` : 'Sin custodio' },
          { label: 'Desde', value: bien.custodio?.desde ? formatFecha(bien.custodio.desde) : null },
          { label: 'Unidad', value: bien.unidad?.nombre },
          { label: 'Ubicación', value: bien.ubicacion?.nombre },
          {
            label: 'Condición física',
            value: <StatusBadge variant="dot" tone={TONO_CONDICION[bien.condicion_fisica]}>{CONDICION_LABELS[bien.condicion_fisica]}</StatusBadge>,
          },
          { label: 'Préstamo', value: bien.prestable ? 'Es un equipo de préstamo' : 'No se presta' },
        ]} />
      </SectionCard>

      <SectionCard title="Red y características">
        <DetailList columnas={3} items={[
          ...(Object.keys(RED) as CampoRed[]).filter((k) => campos.red.includes(k) || bien[k]).map((k) => ({ label: RED[k], value: bien[k] })),
          ...claves.map((clave) => ({
            label: CAMPOS_CARACTERISTICA[clave as ClaveCaracteristica]?.etiqueta ?? clave,
            value: c[clave] ? valorCaracteristica(clave, c[clave]) : null,
          })),
          { label: 'Observaciones', value: bien.observaciones, ancho: true },
        ]} />
      </SectionCard>
    </Stack>
  )
}
