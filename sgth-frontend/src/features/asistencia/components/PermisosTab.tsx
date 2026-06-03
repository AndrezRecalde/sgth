'use client'

import { useState } from 'react'
import {
  Stack, Group, Button, Text, Badge,
  TextInput, Chip,
} from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import {
  IconPlus, IconCheck, IconX,
  IconShieldCheck, IconClipboardList,
  IconPrinter,
} from '@tabler/icons-react'
import { notifications } from '@mantine/notifications'
import React from 'react'
import { useContainedInput } from '@/hooks/useContainedInput'
import { SgthTable } from '@/components/ui/SgthTable'
import { TableActions } from '@/components/ui/TableActions'
import { EmptyState } from '@/components/ui/EmptyState'
import { PermisoModal } from './PermisoModal'
import { usePermisos } from '../hooks/usePermisos'
import { usePermisoMutations } from '../hooks/usePermisoMutations'
import { asistenciaService } from '../services/asistenciaService'
import type { PermisoServidor } from '@/types/api'
import type { DataTableColumn } from 'mantine-datatable'

const ESTADO_COLORS: Record<string, string> = {
  pendiente:               'orange',
  activo:                  'blue',
  validado_trabajo_social: 'emerald',
  anulado:                 'red',
}
const ESTADO_LABELS: Record<string, string> = {
  pendiente:               'Pendiente',
  activo:                  'Activo',
  validado_trabajo_social: 'Validado TS',
  anulado:                 'Anulado',
}
const TIPO_LABELS: Record<string, string> = {
  personal:   'Personal',
  oficial:    'Oficial',
  enfermedad: 'Enfermedad',
  calamidad:  'Calamidad',
}

export function PermisosTab() {
  const contained = useContainedInput()
  const [opened, { open, close }] = useDisclosure(false)

  // ── Filtros ──────────────────────────────────────
  const [filtroEstado, setFiltroEstado] =
    useState<string>('pendiente')
  const [busquedaFolio, setBusquedaFolio] =
    useState<string>('')
  const [folioQuery, setFolioQuery] =
    useState<string>('')

  // Construir params para el backend
  const filtros = {
    estado:   filtroEstado === 'todos' ? undefined : filtroEstado,
    folio:    folioQuery || undefined,
    per_page: 50,
  }

  const { data, isLoading } = usePermisos(filtros)
  const lista = (data?.data ?? []) as PermisoServidor[]

  const { confirmar, anular, validarTs } = usePermisoMutations()
  const [exportandoId, setExportandoId] =
    useState<number | null>(null)

  const handleExportar = async (id: number) => {
    setExportandoId(id)
    notifications.show({
      id:      `export-permiso-${id}`,
      title:   'Exportando permiso...',
      message: 'Generando el documento PDF, espere.',
      color:   'blue',
      loading:  true,
      autoClose: false,
      withCloseButton: false,
    })
    try {
      const blob = await asistenciaService.permisos.exportar(id)
      const url  = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href     = url
      link.download = `permiso_${id}.pdf`
      link.click()
      URL.revokeObjectURL(url)
      notifications.update({
        id:       `export-permiso-${id}`,
        title:    'PDF descargado',
        message:  'El permiso fue exportado correctamente.',
        color:    'emerald',
        loading:   false,
        autoClose: 3000,
        withCloseButton: true,
        icon: React.createElement(IconCheck, { size: 16 }),
      })
    } catch {
      notifications.update({
        id:       `export-permiso-${id}`,
        title:    'Error',
        message:  'No se pudo exportar el permiso.',
        color:    'red',
        loading:   false,
        autoClose: 3000,
        withCloseButton: true,
      })
    } finally {
      setExportandoId(null)
    }
  }

  const columns: DataTableColumn<PermisoServidor>[] = [
    {
      accessor: 'folio',
      title:    'Folio',
      width:    145,
      render: ({ folio }) => (
        <Text size="sm" ff="monospace" fw={500}>
          {folio ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title:    'Servidor',
      render: (p) => {
        const s = p.servidor
        if (!s) return <Text size="sm" c="dimmed">—</Text>
        return (
          <Text size="sm">
            {[s.apellido, s.nombre].filter(Boolean).join(' ')}
          </Text>
        )
      },
    },
    {
      accessor: 'tipo',
      title:    'Tipo',
      width:    100,
      render: ({ tipo }) => (
        <Badge size="sm" variant="light" color="blue">
          {TIPO_LABELS[tipo as string] ?? tipo}
        </Badge>
      ),
    },
    {
      accessor: 'fecha',
      title:    'Fecha',
      width:    110,
      render: ({ fecha }) => (
        <Text size="sm">
          {fecha
            ? new Date(fecha).toLocaleDateString('es-EC', {
                timeZone: 'UTC',
                day: '2-digit', month: '2-digit', year: 'numeric',
              })
            : '—'}
        </Text>
      ),
    },
    {
      accessor: 'hora_inicio',
      title:    'Horario / Tiempo',
      width:    140,
      render: ({ hora_inicio, hora_fin }) => {
        if (!hora_inicio || !hora_fin) {
          return <Text size="sm" c="dimmed">—</Text>
        }
        const hi = (hora_inicio as string).substring(0, 5)
        const hf = (hora_fin as string).substring(0, 5)
        const [hI, mI] = hi.split(':').map(Number)
        const [hF, mF] = hf.split(':').map(Number)
        const minutos  = (hF * 60 + mF) - (hI * 60 + mI)
        const horas    = Math.floor(minutos / 60)
        const mins     = minutos % 60
        const duracion = horas > 0
          ? `${horas}h${mins > 0 ? ` ${mins}m` : ''}`
          : `${mins}m`
        return (
          <Stack gap={2}>
            <Text size="sm" ff="monospace">
              {hi} — {hf}
            </Text>
            <Badge size="xs" color="blue" variant="light">
              {duracion}
            </Badge>
          </Stack>
        )
      },
    },
    {
      accessor: 'estado',
      title:    'Estado',
      width:    120,
      render: ({ estado }) => (
        <Badge
          color={ESTADO_COLORS[estado as string] ?? 'gray'}
          variant="light" size="sm"
        >
          {ESTADO_LABELS[estado as string] ?? estado}
        </Badge>
      ),
    },
    {
      accessor: 'acciones',
      title:    '',
      width:    50,
      render: (p) => (
        <TableActions actions={[
          {
            label:   exportandoId === p.id
              ? 'Exportando...'
              : 'Imprimir permiso',
            icon:    <IconPrinter size={14} />,
            color:   'blue',
            onClick: () => handleExportar(p.id),
          },
          {
            label:   'Confirmar recepción',
            icon:    <IconCheck size={14} />,
            color:   'blue',
            onClick: () => p.folio && confirmar.mutate(p.folio),
            hidden:  (p.estado as string) !== 'pendiente',
          },
          {
            label:   'Validar Trabajo Social',
            icon:    <IconShieldCheck size={14} />,
            color:   'emerald',
            onClick: () => validarTs.mutate(p.id),
            hidden:  (p.estado as string) !== 'activo' ||
              !['enfermedad', 'calamidad']
                .includes(p.tipo as string),
          },
          {
            label:   'Anular',
            icon:    <IconX size={14} />,
            color:   'red',
            onClick: () => {
              if (confirm('¿Anular este permiso?'))
                anular.mutate(p.id)
            },
            hidden:  (p.estado as string) !== 'pendiente',
          },
        ]} />
      ),
    },
  ]

  return (
    <Stack gap="md">

      {/* ── Búsqueda por folio ── */}
      <Group gap="sm" align="flex-end">
        <TextInput
          label="Búsqueda por folio"
          placeholder="Ej: PER-2026-00001"
          value={busquedaFolio}
          onChange={(e) =>
            setBusquedaFolio(e.currentTarget.value)
          }
          onKeyDown={(e) => {
            if (e.key === 'Enter') setFolioQuery(busquedaFolio)
          }}
          style={{ maxWidth: 320 }}
          {...contained}
        />
        <Button
          size="sm"
          variant="light"
          color="blue"
          onClick={() => setFolioQuery(busquedaFolio)}
        >
          Buscar
        </Button>
        {folioQuery && (
          <Button
            size="sm"
            variant="subtle"
            color="gray"
            onClick={() => {
              setBusquedaFolio('')
              setFolioQuery('')
            }}
          >
            Limpiar
          </Button>
        )}
      </Group>

      {/* ── Chips de estado ── */}
      <Group gap="xs">
        <Text size="sm" fw={500} c="dimmed">Estado:</Text>
        {[
          { value: 'todos',                   label: 'Todos',       color: 'gray'    },
          { value: 'pendiente',               label: 'Pendiente',   color: 'orange'  },
          { value: 'activo',                  label: 'Activo',      color: 'blue'    },
          { value: 'validado_trabajo_social', label: 'Validado TS', color: 'emerald' },
          { value: 'anulado',                 label: 'Anulado',     color: 'red'     },
        ].map(op => (
          <Chip
            key={op.value}
            size="sm"
            color={op.color}
            checked={filtroEstado === op.value}
            onChange={() => setFiltroEstado(op.value)}
          >
            {op.label}
          </Chip>
        ))}
      </Group>

      {/* ── Botón nuevo permiso ── */}
      <Group justify="flex-end">
        <Button
          size="xs" color="emerald" variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={open}
        >
          Nuevo permiso
        </Button>
      </Group>

      {lista.length === 0 && !isLoading ? (
        <EmptyState
          icon={IconClipboardList}
          title="Sin permisos registrados"
          description={
            folioQuery
              ? `No se encontraron permisos con folio "${folioQuery}"`
              : 'No hay permisos en este estado.'
          }
        />
      ) : (
        <SgthTable
          records={lista}
          columns={columns}
          fetching={isLoading}
          minHeight={200}
        />
      )}

      <PermisoModal opened={opened} onClose={close} isAdmin={true} />
    </Stack>
  )
}
