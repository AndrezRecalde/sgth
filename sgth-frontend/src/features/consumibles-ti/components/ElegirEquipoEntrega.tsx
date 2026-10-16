'use client'

import { useState } from 'react'
import { Button, Group, Stack, Switch, TextInput } from '@mantine/core'
import { IconScan } from '@tabler/icons-react'
import { SectionCard, notificar } from '@/components/ui'
import { useContainedInput } from '@/hooks/useContainedInput'
import { BuscarEquipoSelect } from '@/features/inventario-ti/components/BuscarEquipoSelect'
import { LectorQr } from '@/features/inventario-ti/components/LectorQr'
import { resolverEquipo } from '../utils/resolverEquipo'

interface Props {
  onElegir: (bienId: number) => void
}

/**
 * Elegir el equipo de la entrega: con el buscador, escribiendo su código o,
 * desde el celular, leyendo su etiqueta QR con el mismo lector de la
 * constatación física.
 */
export function ElegirEquipoEntrega({ onElegir }: Props) {
  const contained = useContainedInput()
  const [camara, setCamara] = useState(false)
  const [codigo, setCodigo] = useState('')
  const [buscando, setBuscando] = useState(false)

  const leer = async (texto: string) => {
    setBuscando(true)
    try {
      const id = await resolverEquipo(texto)
      if (id) {
        onElegir(id)
        setCodigo('')
      } else {
        notificar.aviso('Equipo no encontrado', `«${texto.trim()}» no está en el inventario.`)
      }
    } catch {
      notificar.error('No se pudo buscar el equipo', 'Revise la conexión e inténtelo de nuevo.')
    } finally {
      setBuscando(false)
    }
  }

  return (
    <SectionCard title="Equipo" description="Elija la impresora o el equipo que recibe el consumible.">
      <Stack gap="md">
        <BuscarEquipoSelect label="Buscar el equipo" value={null} soloPrincipales={false}
          placeholder="Código, serie o modelo" onChange={(id) => { if (id) onElegir(id) }} />
        <form noValidate onSubmit={(e) => { e.preventDefault(); if (codigo.trim()) void leer(codigo) }}>
          <Group align="flex-end" gap="sm" wrap="nowrap">
            <TextInput label="O escriba su código" placeholder="Ej.: 002033" style={{ flex: 1 }} autoComplete="off"
              {...contained} value={codigo} onChange={(e) => setCodigo(e.currentTarget.value)} />
            <Button type="submit" variant="light" leftSection={<IconScan size={16} />} loading={buscando} disabled={!codigo.trim()} h={48}>
              Elegir
            </Button>
          </Group>
        </form>
        <Switch label="Leer la etiqueta con la cámara" checked={camara} onChange={(e) => setCamara(e.currentTarget.checked)} />
        {camara && <LectorQr onLeer={(t) => void leer(t)} />}
      </Stack>
    </SectionCard>
  )
}
