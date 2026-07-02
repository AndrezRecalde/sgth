"use client";

import { Text, Badge, Group, Tooltip } from "@mantine/core";
import {
  IconEdit,
  IconPlus,
  IconHistory,
  IconBan,
  IconCircleCheck,
  IconAdjustments,
  IconAlertTriangle,
} from "@tabler/icons-react";
import { TableActions } from "@/components/ui/TableActions";
import type { DataTableColumn } from "mantine-datatable";
import type { InventarioMedicina } from "../services/inventarioMedicinaService";

interface ColumnActions {
  onEditar: (m: InventarioMedicina) => void;
  onIngresarStock: (m: InventarioMedicina) => void;
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
        const stockBajo = m.stock_actual <= m.stock_minimo;
        return (
          <Group gap={4} wrap="nowrap">
            <Text
              size="sm"
              fw={stockBajo ? 600 : 400}
              c={stockBajo ? "red" : undefined}
            >
              {m.stock_actual}
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
          </Group>
        );
      },
    },
    {
      accessor: "fecha_caducidad",
      title: "Caducidad",
      render: (m) => {
        if (!m.fecha_caducidad) {
          return (
            <Text size="sm" c="dimmed">
              —
            </Text>
          );
        }

        const hoy = new Date();
        const caduca = new Date(m.fecha_caducidad);
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
            {
              label: "Ingresar stock",
              icon: <IconPlus size={14} />,
              onClick: () => actions.onIngresarStock(m),
            },
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
            {
              label: m.estado ? "Dar de baja" : "Reactivar",
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
