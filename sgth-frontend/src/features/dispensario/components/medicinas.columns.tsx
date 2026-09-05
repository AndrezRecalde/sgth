"use client";

import { Text, Badge, Group, Tooltip } from "@mantine/core";
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
                <Badge size="xs" variant="light" color="red">
                  +{caducado}
                </Badge>
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
        // La del lote que saldría primero, no la de la última entrada: con
        // varios lotes en el estante, la de la ficha era la que se hubiera
        // escrito al final y podía tapar un lote ya vencido.
        const proxima = m.proxima_caducidad ?? m.fecha_caducidad;

        if (!proxima) {
          return (
            <Text size="sm" c="dimmed">
              —
            </Text>
          );
        }

        const hoy = new Date();
        const caduca = new Date(proxima);
        const dias = Math.floor(
          (caduca.getTime() - hoy.getTime()) / (1000 * 60 * 60 * 24),
        );

        let color = "emerald";
        let label = "OK";

        if (dias < 0) {
          color = "red";
          label = "Vencido";
        } else if (dias <= 30) {
          color = "red";
          label = `${dias}d`;
        } else if (dias <= 90) {
          color = "orange";
          label = `${dias}d`;
        } else {
          color = "emerald";
          label = caduca.toLocaleDateString("es-EC", {
            day: "2-digit",
            month: "short",
            year: "numeric",
          });
        }

        return (
          <Badge size="sm" variant="light" color={color}>
            {label}
          </Badge>
        );
      },
    },
    {
      accessor: "estado",
      title: "Estado",
      render: (m) => (
        <Badge size="sm" variant="light" color={m.estado ? "emerald" : "gray"}>
          {m.estado ? "Activo" : "Inactivo"}
        </Badge>
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
                  color: "orange",
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
              onClick: () => actions.onToggleEstado(m),
            },
          ]}
        />
      ),
    },
  ];
}
