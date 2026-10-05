'use client'

import { Button } from '@mantine/core'
import { IconBan, IconCircleOff } from '@tabler/icons-react'
import { useState } from 'react'
import { MotivoModal } from '@/components/ui'
import { useCerrarConvocatoria, type CierreSinGanadores } from '../hooks/useCerrarConvocatoria'

interface Props {
  convocatoriaId: number
  codigo:         string
  /** Con aprobados no cabe «desierta»: corresponde declararlos ganadores. */
  hayAprobados:   boolean
}

const TEXTOS: Record<CierreSinGanadores, { titulo: string; boton: string; descripcion: string }> = {
  desierta: {
    titulo: 'Declarar desierta',
    boton: 'Declarar desierta',
    descripcion: 'Ningún candidato alcanzó el puntaje mínimo. El concurso se cierra sin ganadores.',
  },
  cancelada: {
    titulo: 'Cancelar convocatoria',
    boton: 'Cancelar convocatoria',
    descripcion: 'La institución deja sin efecto el concurso. Los candidatos que seguían en carrera quedan como no seleccionados.',
  },
}

/**
 * Cerrar un concurso publicado sin ganadores (2026-10-05). Es la única salida
 * de «publicada» que no pasa por el Dispensario; antes se hacía cambiando el
 * estado a mano.
 */
export function CerrarConvocatoriaAcciones({ convocatoriaId, codigo, hayAprobados }: Props) {
  const [cierre, setCierre] = useState<CierreSinGanadores | null>(null)
  const cerrar = useCerrarConvocatoria(convocatoriaId)
  const textos = cierre ? TEXTOS[cierre] : null

  return (
    <>
      {!hayAprobados && (
        <Button variant="default" leftSection={<IconCircleOff size={14} />} onClick={() => setCierre('desierta')}>
          Declarar desierta
        </Button>
      )}
      <Button variant="default" color="red" leftSection={<IconBan size={14} />} onClick={() => setCierre('cancelada')}>
        Cancelar convocatoria
      </Button>

      <MotivoModal
        opened={cierre !== null}
        onClose={() => setCierre(null)}
        title={textos?.titulo ?? ''}
        descripcion={<>{textos?.descripcion} No se puede deshacer. ({codigo})</>}
        confirmLabel={textos?.boton ?? ''}
        destructiva
        cargando={cerrar.isPending}
        onConfirm={(motivo) => cierre && cerrar.mutate(
          { estado: cierre, motivo },
          { onSuccess: () => setCierre(null) },
        )}
      />
    </>
  )
}
