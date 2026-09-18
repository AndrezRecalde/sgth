'use client'

import { Button, Menu } from '@mantine/core'
import { IconChevronDown, IconFileText, IconReceipt, IconReport } from '@tabler/icons-react'
import { usePdfViatico } from '../hooks/usePdfViatico'
import type { ViaticoConRelaciones } from '@/types/api'

const CON_LIQUIDACION = ['pendiente_liquidacion', 'liquidado', 'contabilizado']

interface Props {
  viatico: ViaticoConRelaciones
}

/**
 * Los PDF del viático en un solo menú, cada uno cuando ya existe: la solicitud
 * siempre, el informe desde que hay liquidación y el comprobante al
 * contabilizar.
 *
 * Antes eran tres botones sueltos al pie de la página, mezclados con las
 * acciones del flujo.
 */
export function ViaticoDocumentosMenu({ viatico: d }: Props) {
  const pdf = usePdfViatico()
  const id = d.codigo_viatico ?? d.id
  const estado = String(d.estado ?? '')
  const generando = pdf.loadingSolicitud || pdf.loadingInforme || pdf.loadingComprobante

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
