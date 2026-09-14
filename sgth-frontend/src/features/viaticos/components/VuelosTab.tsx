"use client";

import type { SemanticTone } from '@/config/design.tokens'
import { Text, Stack } from "@mantine/core";
import { IconCheck, IconX, IconAlertCircle } from "@tabler/icons-react";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { EmptyState } from "@/components/ui/EmptyState";
import { useVuelosAutorizacion } from "../hooks/useViaticos";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { viaticoService } from "../services/viaticoService";
import { getApiErrorMessage } from "@/types/api";
import React from "react";
import type { AutorizacionVuelo } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";
import { StatusBadge, notificar } from "@/components/ui";

type AutorizacionVueloConRelaciones = AutorizacionVuelo & {
  viatico?: {
    codigo_viatico?: string
    servidores_ids?: number[]
    servidor?: {
      nombre?: string
      apellido?: string
      puesto?: { cargo?: { nombre?: string } }
    }
  }
  tramo?: {
    origen_tipo?: string
    destino_tipo?: string
    origen_pais?: string
    origen_ciudad?: string
    destino_pais?: string
    destino_ciudad?: string
    datetime_salida?: string
    empresa?: { nombre?: string }
    origenProvincia?: { nombre?: string }
    origenCanton?: { nombre?: string }
    destinoProvincia?: { nombre?: string }
    destinoCanton?: { nombre?: string }
  }
}

const TONO_VUELO: Record<string, SemanticTone> = {
  pendiente: 'warning',
  aprobado:  'success',
  aprobada:  'success',
  rechazado: 'danger',
  rechazada: 'danger',
}

export function VuelosTab() {
  const { data: vuelos = [], isLoading } = useVuelosAutorizacion();
  const qc = useQueryClient();
  const puede = useAccionesViatico();

  // Pendiente y de un viático en el que no viaja quien mira la pantalla.
  const decide = (v: AutorizacionVuelo) =>
    v.estado === 'pendiente' &&
    puede.decidirVuelo(
      (v as AutorizacionVueloConRelaciones).viatico?.servidores_ids ?? [],
    );

  const aprobar = useMutation({
    mutationFn: (id: number) => viaticoService.vuelos.aprobar(id),
    onSuccess: () => {
      notificar.exito("Vuelo aprobado", "La autorización fue aprobada.");
      qc.invalidateQueries({ queryKey: ["vuelos-autorizacion"] });
    },
    onError: (error: unknown) =>
      notificar.error("Error", getApiErrorMessage(error)),
  });

  const rechazar = useMutation({
    mutationFn: (id: number) =>
      viaticoService.vuelos.rechazar(id, {
        observacion: "Rechazado por el gestor",
      }),
    onSuccess: () => {
      notificar.exito("Vuelo rechazado", "La autorización fue rechazada.");
      qc.invalidateQueries({ queryKey: ["vuelos-autorizacion"] });
    },
    onError: (error: unknown) =>
      notificar.error("Error", getApiErrorMessage(error)),
  });

  const columns: DataTableColumn<AutorizacionVuelo>[] = [
    {
      accessor: 'codigo',
      title: 'Código',
      width: 150,
      render: (v) => (
        <Text size="sm" fw={600} c="blue">
          {(v as AutorizacionVueloConRelaciones)
            .viatico?.codigo_viatico ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'servidor',
      title: 'Servidor',
      render: (v) => {
        const srv = (v as AutorizacionVueloConRelaciones)
          .viatico?.servidor
        if (!srv) return <Text size="sm">—</Text>
        return (
          <Stack gap={0}>
            <Text size="sm" fw={500}>
              {[srv.apellido, srv.nombre]
                .filter(Boolean).join(' ')}
            </Text>
            <Text size="xs" c="dimmed">
              {srv.puesto?.cargo?.nombre ?? ''}
            </Text>
          </Stack>
        )
      },
    },
    {
      accessor: 'ruta',
      title: 'Ruta del vuelo',
      render: (v) => {
        const tramo = (v as AutorizacionVueloConRelaciones).tramo
        if (!tramo) return <Text size="sm">—</Text>
        const orig = tramo.origen_tipo === 'nacional'
          ? [
              tramo.origenProvincia?.nombre,
              tramo.origenCanton?.nombre,
            ].filter(Boolean).join(' / ')
          : [tramo.origen_pais, tramo.origen_ciudad]
              .filter(Boolean).join(' / ')
        const dest = tramo.destino_tipo === 'nacional'
          ? [
              tramo.destinoProvincia?.nombre,
              tramo.destinoCanton?.nombre,
            ].filter(Boolean).join(' / ')
          : [tramo.destino_pais, tramo.destino_ciudad]
              .filter(Boolean).join(' / ')
        return (
          <Text size="sm">
            <strong>{orig || '—'}</strong>
            {' → '}
            {dest || '—'}
          </Text>
        )
      },
    },
    {
      accessor: 'fecha',
      title: 'Fecha vuelo',
      width: 130,
      render: (v) => {
        const salida = (v as AutorizacionVueloConRelaciones)
          .tramo?.datetime_salida
        if (!salida) return <Text size="sm">—</Text>
        return (
          <Text size="sm" ff="monospace">
            {new Date(salida as string).toLocaleDateString(
              'es-EC',
              { timeZone: 'UTC',
                day: '2-digit', month: '2-digit',
                year: '2-digit' }
            )}
            {' '}
            {new Date(salida as string).toLocaleTimeString(
              'es-EC',
              { timeZone: 'UTC',
                hour: '2-digit', minute: '2-digit' }
            )}
          </Text>
        )
      },
    },
    {
      accessor: 'empresa',
      title: 'Aerolínea',
      width: 150,
      render: (v) => (
        <Text size="sm">
          {(v as AutorizacionVueloConRelaciones)
            .tramo?.empresa?.nombre ?? '—'}
        </Text>
      ),
    },
    {
      accessor: 'estado',
      title: 'Estado',
      width: 110,
      render: ({ estado }) => {
        return (
          <StatusBadge tone={TONO_VUELO[estado as string] ?? 'neutral'}>
            {String(estado).toUpperCase()}
          </StatusBadge>
        )
      },
    },
    {
      accessor: 'acciones',
      title: '',
      width: 50,
      render: (v) => (
        <TableActions
          actions={[
            {
              label: 'Aprobar',
              icon: <IconCheck size={14} />,
              color: 'emerald',
              onClick: () => aprobar.mutate(Number(v.id)),
              hidden: !decide(v),
            },
            {
              label: 'Rechazar',
              icon: <IconX size={14} />,
              color: 'red',
              onClick: () => rechazar.mutate(Number(v.id)),
              hidden: !decide(v),
            },
          ]}
        />
      ),
    },
  ]

  if (!isLoading && (vuelos as AutorizacionVuelo[]).length === 0) {
    return (
      <EmptyState
        icon={IconAlertCircle}
        title="Sin autorizaciones de vuelo pendientes"
        description="Las autorizaciones aparecen cuando se registra
                     un transporte aéreo en una solicitud."
      />
    );
  }

  return (
    <SgthTable
      records={vuelos as AutorizacionVuelo[]}
      columns={columns}
      fetching={isLoading}
      minHeight={200}
    />
  );
}
