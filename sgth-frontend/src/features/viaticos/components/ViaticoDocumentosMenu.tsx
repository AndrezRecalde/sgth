'use client'

import { Button, Menu } from '@mantine/core'
import { IconCash, IconChevronDown, IconFileText, IconReceipt, IconReport } from '@tabler/icons-react'
import { usePdfViatico } from '../hooks/usePdfViatico'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import type { ViaticoConRelaciones } from '@/types/api'

const CON_LIQUIDACION = ['pendiente_liquidacion', 'liquidado', 'contabilizado']

/** La orden se emite desde que el viático está aprobado; antes no hay qué pagar. */
const SIN_ORDEN = ['solicitado', 'cancelado', 'rechazado']

interface Props {
  viatico: ViaticoConRelaciones
  puede:   AccionesViatico
}

/**
 * Los PDF del viático en un solo menú, cada uno cuando ya existe: la solicitud
 * siempre, la orden del anticipo desde que está aprobado, el informe desde que
 * hay liquidación y el comprobante al contabilizar.
 *
 * Antes eran tres botones sueltos al pie de la página, mezclados con las
 * acciones del flujo.
 *
 * La orden es la única que no se le ofrece a todo el que ve el viático: se
 * sella con el nombre de quien la genera, así que solo la emite quien opera
 * (2026-11-23, S4).
 */
export function ViaticoDocumentosMenu({ viatico: d, puede }: Props) {
  const pdf = usePdfViatico()
  const id = d.codigo_viatico ?? d.id
  const estado = String(d.estado ?? '')
  const generando = pdf.loadingSolicitud || pdf.loadingInforme
    || pdf.loadingComprobante || pdf.loadingOrden

  const conOrden = puede.opera
    && String(d.modalidad_anticipo) !== 'sin_anticipo'
    && !SIN_ORDEN.includes(estado)

  return (
    <Menu shadow="md" position="bottom-end">
      <Menu.Target>
        <Button variant="default" rightSection={<IconChevronDown size={16} />} loading={generando}>
          Documentos
        </Button>
      </Menu.Target>
      <Menu.Dropdown>
        <Menu.Item leftSection={<IconFileText size={16} />} onClick={() => pdf.descargarSolicitud(id)}>
          Solicitud
        </Menu.Item>
        {conOrden && (
          <Menu.Item leftSection={<IconCash size={16} />} onClick={() => pdf.descargarOrdenAnticipo(id)}>
            Orden de pago del anticipo
          </Menu.Item>
        )}
        {CON_LIQUIDACION.includes(estado) && (
          <Menu.Item leftSection={<IconReport size={16} />} onClick={() => pdf.descargarInforme(id)}>
            Informe de liquidación
          </Menu.Item>
        )}
        {estado === 'contabilizado' && (
          <Menu.Item leftSection={<IconReceipt size={16} />} onClick={() => pdf.descargarComprobante(id)}>
            Comprobante contable
          </Menu.Item>
        )}
      </Menu.Dropdown>
    </Menu>
  )
}
