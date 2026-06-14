"use client";

import { Group, Chip, TextInput, Button, ActionIcon } from "@mantine/core";
import { IconSearch, IconX } from "@tabler/icons-react";

const ESTADO_CHIPS = [
  { value: "todos", label: "Todos", color: "gray" },
  { value: "solicitado", label: "Solicitados", color: "orange" },
  { value: "aprobado", label: "Aprobados", color: "blue" },
  { value: "con_anticipo", label: "Con anticipo", color: "cyan" },
  { value: "pendiente_liquidacion", label: "Pend. liquid.", color: "yellow" },
  { value: "liquidado", label: "Liquidados", color: "emerald" },
];

interface Props {
  filtroEstado: string;
  busquedaCodigo: string;
  onEstadoChange: (v: string) => void;
  onBusquedaChange: (v: string) => void;
  onBuscar: () => void;
  onLimpiar: () => void;
}

export function ViaticoFiltros({
  filtroEstado,
  busquedaCodigo,
  onEstadoChange,
  onBusquedaChange,
  onBuscar,
  onLimpiar,
}: Props) {
  return (
    <Group justify="space-between">
      <Group gap="xs">
        <TextInput
          placeholder="Buscar por código..."
          leftSection={<IconSearch size={14} />}
          value={busquedaCodigo}
          onChange={(e) => onBusquedaChange(e.currentTarget.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") onBuscar();
          }}
          style={{ width: 350 }}
          rightSection={
            busquedaCodigo ? (
              <ActionIcon
                size="sm"
                variant="subtle"
                color="gray"
                onClick={onLimpiar}
              >
                <IconX size={12} />
              </ActionIcon>
            ) : null
          }
        />
        <Button
          variant="light"
          leftSection={<IconSearch size={14} />}
          onClick={onBuscar}
        >
          Buscar
        </Button>
      </Group>

      <Group gap="xs">
        {ESTADO_CHIPS.map((op) => (
          <Chip
            key={op.value}
            size="sm"
            color={op.color}
            checked={filtroEstado === op.value}
            onChange={() => onEstadoChange(op.value)}
          >
            {op.label}
          </Chip>
        ))}
      </Group>
    </Group>
  );
}
