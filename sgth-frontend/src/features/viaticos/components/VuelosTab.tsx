"use client";

import type { SemanticTone } from '@/config/design.tokens'
import { useState } from "react";
import { Text, Stack } from "@mantine/core";
import { IconCheck, IconX, IconAlertCircle } from "@tabler/icons-react";
import { SgthTable } from "@/components/ui/SgthTable";
import { TableActions } from "@/components/ui/TableActions";
import { EmptyState } from "@/components/ui/EmptyState";
import { useVuelosAutorizacion } from "../hooks/useViaticos";
import { useAccionesViatico } from "../hooks/useAccionesViatico";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { viaticoService } from "../services/viaticoService";
import React from "react";
import type { AutorizacionVuelo } from "@/types/api";
import type { DataTableColumn } from "mantine-datatable";
import { confirmar, MotivoModal, StatusBadge, notificar } from "@/components/ui";
import { formatFechaHora } from "@/lib/fecha";

type AutorizacionVueloConRelaciones = AutorizacionVuelo & {
  viatico?: {
    codigo_viatico?: string
    servidor_id?: number
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
  const [rechazando, setRechazando] = useState<AutorizacionVuelo | null>(null);

  // Pendiente y de un viático en el que no viaja quien mira la pantalla.
  const decide = (v: AutorizacionVuelo) =>
    v.estado === 'pendiente' &&
    puede.decidirVuelo(
      (v as AutorizacionVueloConRelaciones).viatico?.servidor_id ?? null,
    );

  const aprobar = useMutation({
    mutationFn: (id: number) => viaticoService.vuelos.aprobar(id),
    onSuccess: () => {
      notificar.exito("Vuelo aprobado", "La autorización fue aprobada.");
      qc.invalidateQueries({ queryKey: ["vuelos-autorizacion"] });
    },
    onError: notificar.alFallar("No se pudo aprobar el vuelo"),
  });

  const rechazar = useMutation({
    mutationFn: ({ id, observacion }: { id: number; observacion: string }) =>
      viaticoService.vuelos.rechazar(id, { observacion }),
    onSuccess: () => {
      setRechazando(null);
      notificar.exito("Vuelo rechazado", "La autorización fue rechazada.");
      qc.invalidateQueries({ queryKey: ["vuelos-autorizacion"] });
    },
    onError: notificar.alFallar("No se pudo rechazar el vuelo"),
  });

  const columns: DataTableColumn<AutorizacionVuelo>[] = [
    {
      accessor: 'codigo',
      title: 'Código',
      width: 150,
      render: (v) => (
        <Text size="sm" fw={600} c="ocean">
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
        // En la hora de Ecuador: con `timeZone: 'UTC'` un vuelo de las 08:00
        // se leía a las 13:00.
        return <Text size="sm">{formatFechaHora(salida)}</Text>
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
              onClick: () => confirmar({
                title: 'Aprobar vuelo',
                message: `Se autoriza el vuelo de ${(v as AutorizacionVueloConRelaciones).viatico?.codigo_viatico ?? 'este viático'}. No se puede deshacer.`,
                confirmLabel: 'Aprobar vuelo',
                onConfirm: () => aprobar.mutate(Number(v.id)),
              }),
              hidden: !decide(v),
            },
            {
              label: 'Rechazar',
              icon: <IconX size={14} />,
              color: 'red',
              onClick: () => setRechazando(v),
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
    <>
      <SgthTable
        records={vuelos as AutorizacionVuelo[]}
        columns={columns}
        fetching={isLoading}
        minHeight={200}
      />

      <MotivoModal
        opened={rechazando !== null}
        onClose={() => setRechazando(null)}
        title="Rechazar vuelo"
        confirmLabel="Rechazar vuelo"
        destructiva
        cargando={rechazar.isPending}
        descripcion={`El servidor verá este motivo en ${(rechazando as AutorizacionVueloConRelaciones | null)?.viatico?.codigo_viatico ?? 'su viático'}.`}
        onConfirm={(observacion) =>
          rechazando && rechazar.mutate({ id: Number(rechazando.id), observacion })
        }
      />
    </>
  );
}
