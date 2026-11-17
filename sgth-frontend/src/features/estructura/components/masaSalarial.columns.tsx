import { ActionIcon, Anchor, Box, Group, Stack, Text } from '@mantine/core'
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react'
import Link from 'next/link'
import type { DataTableColumn } from 'mantine-datatable'
import { StatusBadge } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { dineroConMiles } from '@/lib/dinero'
import type {
  BloqueMasaSalarial, FilaMasaSalarial, ServidorSinVinculo, SubrogacionMasaSalarial,
  UnidadMasaSalarial, VinculoMasaSalarial,
} from '@/types/api'

const porcentaje = (p: number) => `${p.toFixed(1)}%`

/** Las cifras de una fila: comunes a las vistas por caso y por unidad. */
function columnasDeCifras<T extends BloqueMasaSalarial>(total?: BloqueMasaSalarial): DataTableColumn<T>[] {
  return [
    { accessor: 'personas', title: 'Personas', width: 90, textAlign: 'right', footer: total?.personas },
    {
      accessor: 'mensual', title: 'Mensual', width: 140, textAlign: 'right',
      // Los casos en cero, apagados: están para decir que no hay nadie.
      render: (f) => <Text size="sm" fw={600} c={f.mensual === 0 ? 'dimmed' : undefined}>{dineroConMiles(f.mensual)}</Text>,
      footer: total && dineroConMiles(total.mensual),
    },
    {
      accessor: 'anual', title: 'Anual (× 12)', width: 150, textAlign: 'right',
      render: (f) => <Text size="sm" c={f.anual === 0 ? 'dimmed' : undefined}>{dineroConMiles(f.anual)}</Text>,
      footer: total && dineroConMiles(total.anual),
    },
    {
      accessor: 'porcentaje', title: 'Del total', width: 90, textAlign: 'right',
      render: (f) => porcentaje(f.porcentaje),
      footer: total && porcentaje(total.porcentaje),
    },
    {
      accessor: 'promedio', title: 'Promedio', width: 120, textAlign: 'right',
      render: (f) => <Text size="sm" c={f.promedio === 0 ? 'dimmed' : undefined}>{dineroConMiles(f.promedio)}</Text>,
    },
  ]
}

/** Por tipo de nombramiento o por régimen, con la fila del total general al pie. */
export function columnasCasoMasa(titulo: string, total?: BloqueMasaSalarial): DataTableColumn<FilaMasaSalarial>[] {
  return [
    {
      accessor: 'etiqueta', title: titulo, width: 260,
      render: (f) => <Text size="sm" c={f.personas === 0 ? 'dimmed' : undefined}>{f.etiqueta}</Text>,
      footer: 'Total',
    },
    ...columnasDeCifras<FilaMasaSalarial>(total),
  ]
}

interface ArbolProps {
  abiertas: ReadonlySet<number>
  alternar: (id: number) => void
}

/** Por unidad, en árbol: la sangría dice la profundidad. */
export function columnasUnidadMasa({ abiertas, alternar }: ArbolProps): DataTableColumn<UnidadMasaSalarial>[] {
  return [
    {
      accessor: 'nombre', title: 'Unidad administrativa', width: 300,
      render: (u) => (
        <Group gap={6} wrap="nowrap" style={{ paddingLeft: u.profundidad * 20 }}>
          {u.tiene_hijas ? (
            <ActionIcon
              variant="subtle" size="sm"
              aria-label={abiertas.has(u.id) ? `Cerrar ${u.nombre}` : `Abrir ${u.nombre}`}
              onClick={(e) => { e.stopPropagation(); alternar(u.id) }}
            >
              {abiertas.has(u.id) ? <IconChevronDown size={14} /> : <IconChevronRight size={14} />}
            </ActionIcon>
          ) : <Box w={22} miw={22} />}
          <Stack gap={0}>
            <Text size="sm" fw={u.profundidad === 0 ? 600 : undefined}>{u.nombre}</Text>
            {u.tiene_hijas && u.propias.personas > 0 && (
              <Text size="xs" c="dimmed">
                Propias: {u.propias.personas} · {dineroConMiles(u.propias.mensual)}
              </Text>
            )}
          </Stack>
        </Group>
      ),
    },
    ...columnasDeCifras<UnidadMasaSalarial>(),
  ]
}

/**
 * El nombre lleva al expediente solo si quien mira lo puede abrir: el auditor
 * y Financiero ven la masa salarial, pero no el Expediente.
 */
function Servidor({ id, nombre, enlace }: { id: number; nombre: string; enlace: boolean }) {
  return enlace
    ? <Anchor component={Link} href={ROUTES.SGTH.EXPEDIENTE_SERVIDOR(id)} size="sm">{nombre}</Anchor>
    : <Text size="sm">{nombre}</Text>
}

const TONO_SITUACION: Record<string, { tono: 'warning' | 'danger'; texto: string }> = {
  sin_remuneracion_en_la_fecha: { tono: 'warning', texto: 'Sin remuneración ese día' },
  sin_remuneracion_registrada:  { tono: 'danger',  texto: 'Sin remuneración registrada' },
}

/** Los vínculos de una fila, en el cajón de detalle. */
export const columnasVinculoMasa = (enlace: boolean): DataTableColumn<VinculoMasaSalarial>[] => [
  {
    accessor: 'servidor', title: 'Servidor',
    render: (v) => (
      <Stack gap={0}>
        <Servidor id={v.servidor_id} nombre={v.servidor} enlace={enlace} />
        <Text size="xs" c="dimmed">{[v.cedula, v.tipo_nombramiento_etiqueta].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'puesto', title: 'Puesto',
    render: (v) => (
      <Stack gap={0}>
        <Text size="sm">{v.puesto ?? '—'}</Text>
        <Text size="xs" c="dimmed">{[v.grado, v.unidad].filter(Boolean).join(' · ')}</Text>
      </Stack>
    ),
  },
  {
    accessor: 'remuneracion', title: 'Remuneración', width: 150, textAlign: 'right',
    render: (v) => {
      const situacion = TONO_SITUACION[v.situacion]
      return situacion && v.remuneracion == null
        ? <StatusBadge tone={situacion.tono}>{situacion.texto}</StatusBadge>
        : dineroConMiles(v.remuneracion)
    },
  },
]

/** Las subrogaciones y los encargos de la fecha: su diferencia no suma. */
export const columnasSubrogacionMasa = (enlace: boolean): DataTableColumn<SubrogacionMasaSalarial>[] => [
  {
    accessor: 'servidor', title: 'Quien asume',
    render: (s) => (
      <Stack gap={0}>
        <Servidor id={s.servidor_id} nombre={s.servidor} enlace={enlace} />
        <Text size="xs" c="dimmed">{s.tipo === 'encargo' ? 'Encargo' : 'Subrogación'}</Text>
      </Stack>
    ),
  },
  { accessor: 'puesto_del_que_asume', title: 'Del puesto', width: 130, textAlign: 'right', render: (s) => dineroConMiles(s.puesto_del_que_asume) },
  { accessor: 'la_suya', title: 'La suya', width: 130, textAlign: 'right', render: (s) => dineroConMiles(s.la_suya) },
  {
    accessor: 'diferencia', title: 'Diferencia', width: 130, textAlign: 'right',
    render: (s) => <Text size="sm" fw={600}>{dineroConMiles(s.diferencia)}</Text>,
  },
]

/** Los servidores activos sin vínculo vigente: hay que completar su expediente. */
export const columnasSinVinculo = (enlace: boolean): DataTableColumn<ServidorSinVinculo>[] => [
  {
    accessor: 'servidor', title: 'Servidor',
    render: (s) => <Servidor id={s.id} nombre={s.servidor} enlace={enlace} />,
  },
  { accessor: 'cedula', title: 'Cédula', width: 130, render: (s) => s.cedula ?? '—' },
]
