'use client'

import { useState } from 'react'
import { useDisclosure } from '@mantine/hooks'
import {
  IconArrowRight, IconEye, IconFolderOff, IconScaleOutline,
} from '@tabler/icons-react'
import { confirmar, type TableAction } from '@/components/ui'
import { useDisciplinarioMutations } from './useDisciplinarioMutations'
import { esHitoConFecha, type HitoConFecha } from '../components/AvanzarHitoModal'
import {
  ESTADO_SUMARIO_LABELS,
  TRANSICIONES_SUMARIO,
  nombreServidor,
  puedeResolverse,
  siguienteHito,
} from '../utils/etiquetas'
import type { Sumario } from '@/types/api'

/**
 * Lo que se puede hacer con un sumario desde su fila, y el estado de los
 * paneles que abre cada acción.
 *
 * Las acciones salen del grafo de transiciones, no de una lista escrita
 * aparte: avanzar al hito que sigue, resolver imponiendo la sanción, dejar
 * constancia de la apelación, o cerrar sin sanción. Si el grafo del backend
 * cambia, aquí no hay nada que acordarse de cambiar.
 */
export function useAccionesSumario() {
  const { avanzarSumario } = useDisciplinarioMutations()

  const [aVer, setAVer] = useState<Sumario | null>(null)
  const [detalleOpened, detalle] = useDisclosure(false)

  const [aResolver, setAResolver] = useState<Sumario | null>(null)
  const [resolverOpened, resolver] = useDisclosure(false)

  const [aAvanzar, setAAvanzar] = useState<{ sumario: Sumario; destino: HitoConFecha } | null>(null)
  const [avanzarOpened, avanzar] = useDisclosure(false)

  const cerrarSinSancion = (sumario: Sumario) => confirmar({
    title: 'Cerrar el sumario',
    message: (
      <>
        El sumario de <b>{nombreServidor(sumario.servidor)}</b> quedará cerrado
        sin sanción y no admitirá más trámite. No se puede deshacer.
      </>
    ),
    confirmLabel: 'Cerrar sumario',
    destructiva: true,
    onConfirm: () => avanzarSumario.mutate({ id: sumario.id, data: { estado: 'cerrado' } }),
  })

  const accionesDe = (s: Sumario): TableAction[] => {
    const siguiente = siguienteHito(s.estado)
    const transiciones = TRANSICIONES_SUMARIO[s.estado]

    return [
      {
        label: 'Ver detalle',
        icon: <IconEye size={14} />,
        onClick: () => {
          setAVer(s)
          detalle.open()
        },
      },
      {
        // Cada hito pide su fecha, que es de donde salen los plazos legales.
        label: siguiente ? `Avanzar a ${ESTADO_SUMARIO_LABELS[siguiente]}` : 'Avanzar',
        icon: <IconArrowRight size={14} />,
        hidden: !siguiente || !esHitoConFecha(siguiente),
        onClick: () => {
          if (!siguiente || !esHitoConFecha(siguiente)) return
          setAAvanzar({ sumario: s, destino: siguiente })
          avanzar.open()
        },
      },
      {
        label: 'Resolver e imponer sanción',
        icon: <IconScaleOutline size={14} />,
        hidden: !puedeResolverse(s.estado),
        onClick: () => {
          setAResolver(s)
          resolver.open()
        },
      },
      {
        label: 'Registrar apelación',
        icon: <IconArrowRight size={14} />,
        hidden: !transiciones.includes('apelado'),
        disabled: avanzarSumario.isPending,
        onClick: () => avanzarSumario.mutate({ id: s.id, data: { estado: 'apelado' } }),
      },
      {
        label: 'Cerrar sin sanción',
        icon: <IconFolderOff size={14} />,
        color: 'red',
        hidden: !transiciones.includes('cerrado'),
        disabled: avanzarSumario.isPending,
        onClick: () => cerrarSinSancion(s),
      },
    ]
  }

  return {
    accionesDe,
    detalle: { abierto: detalleOpened, cerrar: detalle.close, sumario: aVer },
    resolucion: { abierto: resolverOpened, cerrar: resolver.close, sumario: aResolver },
    avance: {
      abierto: avanzarOpened,
      cerrar: avanzar.close,
      sumario: aAvanzar?.sumario ?? null,
      destino: aAvanzar?.destino ?? null,
    },
  }
}
