"use client";

import type { SemanticTone } from "@/config/design.tokens";
import { Text, Group, Tooltip } from "@mantine/core";
import {
  IconEdit,
  IconHistory,
  IconBan,
  IconCircleCheck,
  IconAdjustments,
  IconAlertTriangle,
  IconTrash,
} from "@tabler/icons-react";
import { TableActions } from "@/components/ui/TableActions";
import type { DataTableColumn } from "mantine-datatable";
import type { InventarioMedicina } from "../services/inventarioMedicinaService";
import { StatusBadge } from "@/components/ui";
import { diasParaCaducar } from '../utils/caducidad';
import { formatFechaMes } from '@/lib/fecha';

interface ColumnActions {
  onEditar: (m: InventarioMedicina) => void;
  onDarDeBaja: (m: InventarioMedicina) => void;
  onAjustar: (m: InventarioMedicina) => void;
  onVerKardex: (m: InventarioMedicina) => void;
  onToggleEstado: (m: InventarioMedicina) => void;
}

export function getMedicinasColumns(
  actions: ColumnActions,
): DataTableColumn<InventarioMedicina>[] {
  return [
    {
      accessor: "codigo",
      title: "Código",
      render: (m) => (
        <Text size="sm" ff="monospace">
          {m.codigo}
        </Text>
      ),
    },
    {
      accessor: "nombre",
      title: "Medicina",
      render: (m) => (
        <Text size="sm" fw={400}>
          {m.nombre}
          {m.concentracion && (
            <Text span c="dimmed">
              {" "}
              — {m.concentracion}
            </Text>
          )}
        </Text>
      ),
    },
    {
      accessor: "principio_activo",
      title: "Principio activo",
      render: (m) => (
        <Text size="sm" c="dimmed">
          {m.principio_activo}
        </Text>
      ),
    },
    {
      accessor: "presentacion",
      title: "Presentación",
    },
    {
      accessor: "stock_actual",
      title: "Stock",
      render: (m) => {
        // Lo que se enseña es lo entregable, no el total: ochenta unidades
        // vencidas no son ochenta unidades para quien está en la ventanilla.
        const despachable = m.stock_despachable ?? m.stock_actual;
        const caducado = m.stock_caducado ?? 0;
        const stockBajo = despachable <= m.stock_minimo;

        return (
          <Group gap={4} wrap="nowrap">
            <Text
              size="sm"
              fw={stockBajo ? 600 : 400}
              c={stockBajo ? "red" : undefined}
            >
              {despachable}
            </Text>
            {stockBajo && (
              <Tooltip label={`Stock mínimo: ${m.stock_minimo}`} withArrow>
                <IconAlertTriangle
                  size={14}
                  color="var(--mantine-color-red-6)"
                />
              </Tooltip>
            )}
            <Text size="xs" c="dimmed">
              unid.
            </Text>
            {caducado > 0 && (
              <Tooltip
                label={`${caducado} unid. vencidas, pendientes de dar de baja`}
                withArrow
              >
                <StatusBadge tone="danger" size="xs">
                  +{caducado}
                </StatusBadge>
              </Tooltip>
            )}
          </Group>
        );
      },
    },
    {
      accessor: "proxima_caducidad",
      title: "Caducidad",
      render: (m) => {
        // La del lote que saldría primero. La ficha ya no guarda ninguna: la
        // que tenía era la de la última entrada, y con varios lotes en el
        // estante podía tapar uno ya vencido.
        const proxima = m.proxima_caducidad;

        if (!proxima) {
          return (
            <Text size="sm" c="dimmed">
              —
            </Text>
          );
        }

        // El día impreso en el envase todavía es válido: la cuenta la lleva
        // `diasParaCaducar`, que es la misma que usan el modal de baja y el
        // backend. Antes se restaba contra `new Date()` con la hora puesta, y
        // por la tarde un lote que caducaba HOY salía como «Vencido».
        const dias = diasParaCaducar(proxima);

        let tone: SemanticTone = "success";
        let label = "OK";

        if (dias === null) {
          tone = "neutral";
          label = "—";
        } else if (dias < 0) {
          tone = "danger";
          label = "Vencido";
        } else if (dias === 0) {
          // «0d» se leía como «ya no sirve». Caduca hoy, y hoy todavía se usa.
          tone = "danger";
          label = "Caduca hoy";
        } else if (dias <= 30) {
          tone = "danger";
          label = `${dias}d`;
        } else if (dias <= 90) {
          tone = "warning";
          label = `${dias}d`;
        } else {
          tone = "success";
          label = formatFechaMes(proxima);
        }

        return (
          <StatusBadge tone={tone}>
            {label}
          </StatusBadge>
        );
      },
    },
    {
      accessor: "estado",
      title: "Estado",
      render: (m) => (
        <StatusBadge tone={m.estado ? 'success' : 'neutral'}>
          {m.estado ? "Activo" : "Inactivo"}
        </StatusBadge>
      ),
    },
    {
      accessor: "acciones",
      title: "Acciones",
      width: "0%",
      render: (m) => (
        <TableActions
          actions={[
            {
              label: "Editar",
              icon: <IconEdit size={14} />,
              onClick: () => actions.onEditar(m),
            },
            ...(m.stock_actual > 0
              ? [{
                  label: "Dar de baja existencias",
                  icon: <IconTrash size={14} />,
                  color: "red",
                  onClick: () => actions.onDarDeBaja(m),
                }]
              : []),
            {
              label: "Ajustar inventario",
              icon: <IconAdjustments size={14} />,
              onClick: () => actions.onAjustar(m),
            },
            {
              label: "Ver kardex",
              icon: <IconHistory size={14} />,
              onClick: () => actions.onVerKardex(m),
            },
            // «Retirar del catálogo» y no «dar de baja»: esto desactiva el
            // medicamento, no mueve existencias, y ya hay una baja de stock
            // justo encima con la que se confundía.
            {
              label: m.estado ? "Retirar del catálogo" : "Reactivar",
              icon: m.estado ? (
                <IconBan size={14} />
              ) : (
                <IconCircleCheck size={14} />
              ),
              color: m.estado ? "red" : undefined,
              onClick: () => actions.onToggleEstado(m),
            },
          ]}
        />
      ),
    },
  ];
}
