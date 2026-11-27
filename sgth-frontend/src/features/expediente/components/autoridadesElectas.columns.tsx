import { Text } from '@mantine/core'
import { IconBeach, IconCalendarEvent, IconFileText, IconUmbrella, IconUserCheck, IconUserOff } from '@tabler/icons-react'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge, TableActions } from '@/components/ui'
import { formatFecha } from '@/lib/fecha'
import type { AutoridadElecta } from '../services/autoridadElectaService'

/** Lo que le queda al período. */
function Restante({ a }: { a: AutoridadElecta }) {
  if (a.dias_restantes < 0) return <StatusBadge tone="info">Terminado</StatusBadge>

  return (
    <StatusBadge tone={a.dias_restantes <= 30 ? 'warning' : 'neutral'}>
      {a.dias_restantes} día{a.dias_restantes === 1 ? '' : 's'}
    </StatusBadge>
  )
}

/** La licencia del Consejo y la subrogación en curso (fase 4.1b). */
function Ausencia({ a }: { a: AutoridadElecta }) {
  if (!a.ausencia && !a.subrogacion) return <Text size="sm" c="dimmed">—</Text>

  return (
    <div>
      {a.ausencia && (
        <Text size="xs">
          Licencia del {formatFecha(a.ausencia.desde)} al {formatFecha(a.ausencia.hasta)}
          {a.ausencia.codigo_registro ? ` · ${a.ausencia.codigo_registro}` : ' · en trámite'}
        </Text>
      )}
      {a.subrogacion && (
        <Text size="xs" c="dimmed">
          {a.subrogacion.rol === 'subrogado' ? 'Lo subroga ' : 'Subroga a '}{a.subrogacion.con}
          {' '}del {formatFecha(a.subrogacion.desde)} al {formatFecha(a.subrogacion.hasta)}
          {a.subrogacion.estado === 'pendiente' ? ' (por registrar)' : ''}
        </Text>
      )}
    </div>
  )
}

/**
 * Lo que sigue: la dignidad que va a asumir, el cese en curso, el período
 * nuevo de la autoridad reelegida, o quien entra a su puesto cuando termina
 * (2026-10-21).
 *
 * La asunción va primero porque es la única que pide algo: mientras no se
 * registre, la institución está sin autoridad nominadora.
 */
function Siguiente({ a }: { a: AutoridadElecta }) {
  if (a.asuncion) {
    return (
      <div>
        {/* La insignia, corta como las vecinas: la dignidad no cabe dentro. */}
        <StatusBadge tone="warning">Asunción</StatusBadge>
        <Text size="xs">Asume {a.asuncion.dignidad ?? 'la Prefectura'}</Text>
        <Text size="xs" c="dimmed">
          {a.asuncion.codigo_registro ?? 'En trámite'} · desde el {formatFecha(a.asuncion.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  if (a.cese) {
    return (
      <div>
        <StatusBadge tone="warning">{a.cese.causal ?? 'Cese'}</StatusBadge>
        <Text size="xs" c="dimmed">
          {a.cese.codigo_registro ?? 'En trámite'} · rige el {formatFecha(a.cese.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  if (a.siguiente) {
    return (
      <div>
        <StatusBadge tone="info">Reelección</StatusBadge>
        <Text size="xs" c="dimmed">
          {a.siguiente.codigo_registro ?? 'En trámite'} · desde el {formatFecha(a.siguiente.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  if (a.sucesor) {
    return (
      <div>
        <StatusBadge tone="info">Relevo</StatusBadge>
        <Text size="xs">Le sucede {a.sucesor.nombre}</Text>
        <Text size="xs" c="dimmed">
          {a.sucesor.codigo_registro ?? 'En trámite'} · desde el {formatFecha(a.sucesor.fecha_efectiva)}
        </Text>
      </div>
    )
  }

  return <Text size="sm" c="dimmed">—</Text>
}

interface Opciones {
  /** ¿Quien mira puede preparar acciones para ese servidor? */
  puedePreparar: (servidorId: number | null) => boolean
  onFijarFin: (a: AutoridadElecta) => void
  /** Abre el acto en su cajón, que es donde se suscribe y se registra. */
  onVerActo: (movimientoId: number) => void
  /** El período siguiente de la autoridad reelegida (TH 4). */
  onReelegir: (a: AutoridadElecta) => void
  /** La licencia que concede el Consejo (COOTAD Art. 47 s). */
  onLicencia: (a: AutoridadElecta) => void
  /** Las vacaciones que concede el Consejo, dentro de los mismos 60 días. */
  onVacaciones: (a: AutoridadElecta) => void
  /** El cese: renuncia, fallecimiento, revocatoria o remoción. */
  onCesar: (a: AutoridadElecta) => void
}

export const getAutoridadElectaColumns = ({
  puedePreparar,
  onFijarFin,
  onVerActo,
  onReelegir,
  onLicencia,
  onVacaciones,
  onCesar,
}: Opciones): DataTableColumn<AutoridadElecta>[] => [
  {
    accessor: 'servidor',
    title: 'Autoridad',
    render: (a) => (
      <div>
        <Text size="sm" fw={500}>{a.servidor.nombre || '—'}</Text>
        <Text size="xs" c="dimmed">{a.dignidad ?? '—'}{a.es_prefecto ? ' · firma como autoridad nominadora' : ''}</Text>
      </div>
    ),
  },
  {
    accessor: 'periodo',
    title: 'Período',
    width: 210,
    render: (a) => (
      <div>
        <Text size="sm">{formatFecha(a.desde)} – {formatFecha(a.hasta)}</Text>
        <Restante a={a} />
      </div>
    ),
  },
  {
    accessor: 'inicio',
    title: 'Inicio',
    render: (a) => (
      <div>
        <Text size="sm" ff="monospace">{a.inicio?.codigo_registro ?? '—'}</Text>
        {a.credencial && <Text size="xs" c="dimmed">Credencial {a.credencial}</Text>}
        {a.designacion && <Text size="xs" c="dimmed">Designado por el Consejo: {a.designacion}</Text>}
      </div>
    ),
  },
  {
    accessor: 'ausencia',
    title: 'Ausencia y subrogación',
    render: (a) => <Ausencia a={a} />,
  },
  {
    accessor: 'siguiente',
    title: 'Lo que sigue',
    render: (a) => <Siguiente a={a} />,
  },
  {
    accessor: 'acciones',
    title: '',
    width: 50,
    render: (a) => (
      <TableActions
        actions={[
          {
            label: 'Corregir el fin del período',
            icon: <IconCalendarEvent size={14} />,
            onClick: () => onFijarFin(a),
            hidden: !a.puede_fijar_fin || !puedePreparar(a.servidor.id),
          },
          {
            label: 'Completar la asunción',
            icon: <IconFileText size={14} />,
            onClick: () => a.asuncion && onVerActo(a.asuncion.id),
            hidden: !a.asuncion,
          },
          {
            // Se esconde si ya hay un período siguiente —es uno por autoridad,
            // y el sistema rechaza el segundo— o si está cesando.
            label: 'Registrar su reelección',
            icon: <IconUserCheck size={14} />,
            onClick: () => onReelegir(a),
            hidden: Boolean(a.siguiente) || Boolean(a.cese) || !puedePreparar(a.servidor.id),
          },
          {
            label: 'Registrar licencia del Consejo',
            icon: <IconBeach size={14} />,
            onClick: () => onLicencia(a),
            // Con una licencia en curso, la siguiente se registra cuando esa
            // termina: el tope de los 60 días las suma todas igualmente.
            hidden: Boolean(a.ausencia) || Boolean(a.cese) || !puedePreparar(a.servidor.id),
          },
          {
            label: 'Registrar vacaciones del Consejo',
            icon: <IconUmbrella size={14} />,
            onClick: () => onVacaciones(a),
            // Se puede registrar con una licencia en curso: son dos permisos
            // distintos, y lo que no cabe lo dicen los dos topes.
            hidden: Boolean(a.cese) || !puedePreparar(a.servidor.id),
          },
          {
            label: 'Registrar cesación',
            icon: <IconUserOff size={14} />,
            onClick: () => onCesar(a),
            hidden: Boolean(a.cese) || !puedePreparar(a.servidor.id),
          },
        ]}
      />
    ),
  },
]
