'use client'

import { useState } from 'react'
import { Button } from '@mantine/core'
import {
  IconArrowBack, IconBan, IconCash, IconCheck, IconFileInvoice, IconPlane, IconReceipt2, IconX,
} from '@tabler/icons-react'
import { confirmar, MotivoModal } from '@/components/ui'
import { useViaticoMutations } from '../hooks/useViaticoMutations'
import { resumenRevision } from '../utils/revisionComprobantes'
import { AprobarExteriorModal } from './AprobarExteriorModal'
import { RespaldoContableModal } from './RespaldoContableModal'
import { ViaticoDocumentosMenu } from './ViaticoDocumentosMenu'
import type { AccionesViatico } from '../hooks/useAccionesViatico'
import type { ViaticoConRelaciones } from '@/types/api'

interface Props {
  viatico: ViaticoConRelaciones
  puede:   AccionesViatico
}

/*
| Las acciones del viático, en la cabecera de la ficha.
|
| Cada botón aparece solo en el estado en que la acción es posible y a quien
| puede usarla (`useAccionesViatico`). Antes vivían en una tarjeta al final de
| la página, con tres tamaños y tres variantes de botón mezclados; ahora la
| acción que hace avanzar el viático es la principal (`light`), lo demás es
| secundario y lo que lo termina va en rojo.
|
| Los modales que abren —respaldo contable, motivo, coeficiente del exterior—
| viven aquí con ellas.
*/
export function ViaticoAcciones({ viatico: d, puede }: Props) {
  const m = useViaticoMutations()
  const [conRespaldo, setConRespaldo] = useState<'anticipo' | 'contabilizar' | null>(null)
  const [conMotivo, setConMotivo] = useState<'rechazar' | 'devolver' | null>(null)
  const [exterior, setExterior] = useState(false)

  const revision = resumenRevision(d.liquidacion?.detalles_factura)
  const codigo = d.codigo_viatico ?? 'el viático'

  const aprobar = () => (d.zona === 'exterior' ? setExterior(true) : m.aprobar.mutate({ id: d.id }))

  const contabilizar = () =>
    d.numero_resolucion && d.partida_presupuestaria_id
      ? m.contabilizar.mutate({ id: d.id })
      : setConRespaldo('contabilizar')

  return (
    <>
      <ViaticoDocumentosMenu viatico={d} />

      {puede.cancelar(d) && (
        <Button
          variant="light" color="red" leftSection={<IconX size={16} />}
          loading={m.cancelar.isPending}
          onClick={() => confirmar({
            title:        'Cancelar solicitud',
            message:      'Se cancelará esta solicitud de viático. No se puede deshacer.',
            destructiva:  true,
            confirmLabel: 'Cancelar solicitud',
            cancelLabel:  'Volver',
            onConfirm:    () => m.cancelar.mutate(d.id),
          })}
        >
          Cancelar solicitud
        </Button>
      )}
      {puede.rechazar(d) && (
        <Button variant="light" color="red" leftSection={<IconBan size={16} />} onClick={() => setConMotivo('rechazar')}>
          Rechazar
        </Button>
      )}
      {puede.revisarLiquidacion(d) && (
        <Button variant="default" leftSection={<IconArrowBack size={16} />} onClick={() => setConMotivo('devolver')}>
          Devolver a corrección
        </Button>
      )}

      {puede.aprobar(d) && (
        <Button variant="light" leftSection={<IconCheck size={16} />} loading={m.aprobar.isPending} onClick={aprobar}>
          Aprobar viático
        </Button>
      )}
      {puede.entregarAnticipo(d) && (
        <Button variant="light" leftSection={<IconCash size={16} />} onClick={() => setConRespaldo('anticipo')}>
          Entregar anticipo
        </Button>
      )}
      {puede.marcarEnComision(d) && (
        // Con anticipo por entregar, salir en comisión es lo secundario.
        <Button
          variant={puede.entregarAnticipo(d) ? 'default' : 'light'}
          leftSection={<IconPlane size={16} />}
          loading={m.marcarEnComision.isPending}
          onClick={() => m.marcarEnComision.mutate(d.id)}
        >
          Marcar en comisión
        </Button>
      )}
      {puede.marcarPendiente(d) && (
        <Button
          variant="light" leftSection={<IconFileInvoice size={16} />}
          loading={m.marcarPendienteLiquidacion.isPending}
          onClick={() => m.marcarPendienteLiquidacion.mutate(d.id)}
        >
          Pasar a liquidación
        </Button>
      )}
      {puede.contabilizar(d) && (
        <Button
          variant="light" leftSection={<IconReceipt2 size={16} />}
          loading={m.contabilizar.isPending}
          disabled={!revision.completa}
          onClick={contabilizar}
        >
          Contabilizar
        </Button>
      )}

      <RespaldoContableModal
        opened={conRespaldo !== null}
        onClose={() => setConRespaldo(null)}
        title={conRespaldo === 'anticipo' ? 'Entregar anticipo' : 'Contabilizar viático'}
        confirmLabel={conRespaldo === 'anticipo' ? 'Entregar anticipo' : 'Contabilizar'}
        cargando={m.entregarAnticipo.isPending || m.contabilizar.isPending}
        descripcion={
          conRespaldo === 'anticipo'
            ? `Con qué se respalda el anticipo de ${codigo}. Los dos datos se imprimen en el comprobante contable.`
            : `${codigo} se tramitó sin anticipo: asigne el respaldo antes de contabilizarlo.`
        }
        onConfirm={(datos) => {
          const cerrar = { onSuccess: () => setConRespaldo(null) }
          if (conRespaldo === 'anticipo') m.entregarAnticipo.mutate({ id: d.id, datos }, cerrar)
          else m.contabilizar.mutate({ id: d.id, datos }, cerrar)
        }}
      />

      <MotivoModal
        opened={conMotivo !== null}
        onClose={() => setConMotivo(null)}
        title={conMotivo === 'devolver' ? 'Devolver para correcciones' : 'Rechazar viático'}
        confirmLabel={conMotivo === 'devolver' ? 'Devolver' : 'Rechazar'}
        destructiva={conMotivo !== 'devolver'}
        cargando={m.rechazar.isPending || m.devolverCorreccion.isPending}
        valorInicial={conMotivo === 'devolver' ? revision.motivoDevolucion : ''}
        descripcion={
          conMotivo === 'devolver'
            ? `La liquidación de ${codigo} vuelve al servidor. El motivo le dice qué corregir.`
            : `${codigo} queda rechazado y no se puede reabrir.`
        }
        onConfirm={(motivo) => {
          const cerrar = { onSuccess: () => setConMotivo(null) }
          if (conMotivo === 'devolver') m.devolverCorreccion.mutate({ id: d.id, motivo }, cerrar)
          else m.rechazar.mutate({ id: d.id, motivo }, cerrar)
        }}
      />

      {exterior && <AprobarExteriorModal opened onClose={() => setExterior(false)} viatico={d} />}
    </>
  )
}
