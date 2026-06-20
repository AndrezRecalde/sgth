"use client";

import { useState } from "react";
import {
  Stack,
  Group,
  Text,
  Badge,
  Button,
  Collapse,
  Skeleton,
  ActionIcon,
  Tooltip,
  Switch,
} from "@mantine/core";
import { useDisclosure } from "@mantine/hooks";
import {
  IconPlus,
  IconTrash,
  IconEdit,
  IconChevronDown,
  IconChevronRight,
  IconUsers,
} from "@tabler/icons-react";
import { EmptyState } from "@/components/ui/EmptyState";
import { useCargasFamiliares } from "../../hooks/useCargasFamiliares";
import { useCargaFamiliarMutations } from "../../hooks/useCargaFamiliarMutations";
import { CargaFamiliarModal } from "../CargaFamiliarModal";
import { DiscapacidadCargaFamiliarModal } from "../DiscapacidadCargaFamiliarModal";
import { EnfermedadCargaFamiliarModal } from "../EnfermedadCargaFamiliarModal";
import { expedienteService } from "../../services/expedienteService";
import { useQueryClient } from "@tanstack/react-query";
import { notifications } from "@mantine/notifications";
import type {
  CargaFamiliar,
  DiscapacidadCargaFamiliar,
  EnfermedadCatastroficaCargaFamiliar,
} from "@/types/api";
import React from "react";
import { IconCheck, IconX } from "@tabler/icons-react";

const PARENTESCO_LABELS: Record<string, string> = {
  conyuge: "Cónyuge",
  conyugue: "Cónyuge",
  hijo: "Hijo/a",
  padre: "Padre",
  madre: "Madre",
  hermano: "Hermano/a",
  otro: "Otro",
};

const TIPO_DISCAPACIDAD_LABELS: Record<string, string> = {
  fisica: "Física",
  sensorial: "Sensorial",
  intelectual: "Intelectual",
  psicosocial: "Psicosocial",
  visceral: "Visceral",
  multiple: "Múltiple",
};

interface CargaRowProps {
  carga: CargaFamiliar;
  servidorId: number;
  onEdit: (c: CargaFamiliar) => void;
  onDelete: (id: number) => void;
  onToggleEstado: (id: number) => void;
  togglePending: boolean;
}

function CargaRow({
  carga, servidorId, onEdit, onDelete,
  onToggleEstado, togglePending,
}: CargaRowProps) {
  const [expanded, setExpanded] = useState(false);
  const qc = useQueryClient();

  const [discModalOpened, { open: openDiscModal, close: closeDiscModal }] =
    useDisclosure(false);

  const [enfModalOpened, { open: openEnfModal, close: closeEnfModal }] =
    useDisclosure(false);

  const discapacidades = (carga.discapacidades ??
    []) as DiscapacidadCargaFamiliar[];
  const enfermedades = (carga.enfermedades_catastroficas ??
    []) as EnfermedadCatastroficaCargaFamiliar[];

  const handleDeleteDisc = async (id: number) => {
    if (!confirm("¿Eliminar esta discapacidad?")) return;
    try {
      await expedienteService.eliminarDiscapacidadCarga(Number(carga.id), id);
      notifications.show({
        title: "Eliminado",
        color: "emerald",
        message: "Discapacidad eliminada.",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      qc.invalidateQueries({ queryKey: ["cargas-familiares", servidorId] });
    } catch {
      notifications.show({
        title: "Error",
        color: "red",
        message: "No se pudo eliminar.",
        icon: React.createElement(IconX, { size: 16 }),
      });
    }
  };

  const handleDeleteEnf = async (id: number) => {
    if (!confirm("¿Eliminar esta enfermedad?")) return;
    try {
      await expedienteService.eliminarEnfermedadCarga(Number(carga.id), id);
      notifications.show({
        title: "Eliminado",
        color: "emerald",
        message: "Enfermedad eliminada.",
        icon: React.createElement(IconCheck, { size: 16 }),
      });
      qc.invalidateQueries({ queryKey: ["cargas-familiares", servidorId] });
    } catch {
      notifications.show({
        title: "Error",
        color: "red",
        message: "No se pudo eliminar.",
        icon: React.createElement(IconX, { size: 16 }),
      });
    }
  };

  const hasCondiciones =
    carga.persona_con_discapacidad || carga.posee_enfermedad_catastrofica;

  return (
    <Stack gap={0}>
      {/* Fila principal de la carga familiar */}
      <Group
        justify="space-between"
        p="sm"
        style={{
          borderRadius: expanded ? "8px 8px 0 0" : 8,
          border: "1px solid var(--mantine-color-default-border)",
          borderBottom: expanded
            ? "1px solid var(--mantine-color-emerald-3)"
            : undefined,
          background: expanded
            ? "var(--mantine-color-emerald-light)"
            : undefined,
          cursor: hasCondiciones ? "pointer" : "default",
        }}
        onClick={() => hasCondiciones && setExpanded((e) => !e)}
      >
        <Group gap="sm">
          {hasCondiciones &&
            (expanded ? (
              <IconChevronDown size={14} />
            ) : (
              <IconChevronRight size={14} />
            ))}
          <div>
            <Text size="sm" fw={600}>
              {carga.apellidos} {carga.nombres}
            </Text>
            <Text size="xs" c="dimmed" ff="monospace">
              CI: {carga.cedula}
            </Text>
            <Group gap="xs" mt={2}>
              <Badge size="xs" variant="light" color="blue">
                {PARENTESCO_LABELS[carga.parentesco ?? ""] ??
                  carga.parentesco ??
                  "-"}
              </Badge>
              {carga.persona_con_discapacidad && (
                <Badge size="xs" variant="dot" color="orange">
                  Discapacidad
                </Badge>
              )}
              {carga.posee_enfermedad_catastrofica && (
                <Badge size="xs" variant="dot" color="red">
                  Enf. catastrófica
                </Badge>
              )}
            </Group>
          </div>
        </Group>

        <Group gap="xs" onClick={(e) => e.stopPropagation()}>
          <Tooltip
            label={carga.estado ? 'Desactivar' : 'Activar'}
            withArrow
          >
            <Switch
              checked={carga.estado}
              onChange={() => onToggleEstado(Number(carga.id))}
              disabled={togglePending}
              color="emerald"
              size="sm"
            />
          </Tooltip>
          <Tooltip label="Editar" withArrow>
            <ActionIcon
              variant="subtle"
              color="blue"
              size="sm"
              onClick={() => onEdit(carga)}
            >
              <IconEdit size={14} />
            </ActionIcon>
          </Tooltip>
          <Tooltip label="Eliminar" withArrow>
            <ActionIcon
              variant="subtle"
              color="red"
              size="sm"
              onClick={() => onDelete(Number(carga.id))}
            >
              <IconTrash size={14} />
            </ActionIcon>
          </Tooltip>
        </Group>
      </Group>

      {/* Panel expandible de sub-condiciones */}
      <Collapse expanded={expanded}>
        <Stack
          gap="sm"
          p="sm"
          style={{
            border: "1px solid var(--mantine-color-default-border)",
            borderTop: "none",
            borderRadius: "0 0 8px 8px",
          }}
        >
          {/* Discapacidades */}
          {carga.persona_con_discapacidad && (
            <div>
              <Group justify="space-between" mb="xs">
                <Text size="xs" fw={600} c="orange">
                  Discapacidades
                </Text>
                <Button
                  size="xs"
                  variant="subtle"
                  color="orange"
                  leftSection={<IconPlus size={12} />}
                  onClick={(e) => {
                    e.stopPropagation();
                    openDiscModal();
                  }}
                >
                  Agregar
                </Button>
              </Group>
              {discapacidades.length === 0 ? (
                <Text size="xs" c="dimmed">
                  Sin discapacidades registradas aún.
                </Text>
              ) : (
                discapacidades.map((d) => (
                  <Group
                    key={d.id}
                    justify="space-between"
                    p="xs"
                    style={{
                      borderRadius: 6,
                      background: "var(--mantine-color-orange-light)",
                    }}
                  >
                    <div>
                      <Text size="xs" fw={500}>
                        {TIPO_DISCAPACIDAD_LABELS[d.tipo_discapacidad] ??
                          d.tipo_discapacidad}
                        {" — "}
                        {d.porcentaje}%
                      </Text>
                      {d.numero_carnet_conadis && (
                        <Text size="xs" c="dimmed" ff="monospace">
                          CONADIS: {d.numero_carnet_conadis}
                        </Text>
                      )}
                    </div>
                    <Tooltip label="Eliminar" withArrow>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        size="xs"
                        onClick={() => handleDeleteDisc(d.id)}
                      >
                        <IconTrash size={12} />
                      </ActionIcon>
                    </Tooltip>
                  </Group>
                ))
              )}
            </div>
          )}

          {/* Enfermedades catastróficas */}
          {carga.posee_enfermedad_catastrofica && (
            <div>
              <Group justify="space-between" mb="xs">
                <Text size="xs" fw={600} c="red">
                  Enfermedades catastróficas
                </Text>
                <Button
                  size="xs"
                  variant="subtle"
                  color="red"
                  leftSection={<IconPlus size={12} />}
                  onClick={(e) => {
                    e.stopPropagation();
                    openEnfModal();
                  }}
                >
                  Agregar
                </Button>
              </Group>
              {enfermedades.length === 0 ? (
                <Text size="xs" c="dimmed">
                  Sin enfermedades registradas aún.
                </Text>
              ) : (
                enfermedades.map((e) => (
                  <Group
                    key={e.id}
                    justify="space-between"
                    p="xs"
                    style={{
                      borderRadius: 6,
                      background: "var(--mantine-color-red-light)",
                    }}
                  >
                    <div>
                      <Text size="xs" fw={500}>
                        {e.tipo_enfermedad}
                        {e.codigo_cie10 && ` (${e.codigo_cie10})`}
                      </Text>
                      {e.fecha_diagnostico && (
                        <Text size="xs" c="dimmed">
                          Diagnóstico:{" "}
                          {new Date(e.fecha_diagnostico).toLocaleDateString(
                            "es-EC",
                          )}
                        </Text>
                      )}
                    </div>
                    <Tooltip label="Eliminar" withArrow>
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        size="xs"
                        onClick={() => handleDeleteEnf(e.id)}
                      >
                        <IconTrash size={12} />
                      </ActionIcon>
                    </Tooltip>
                  </Group>
                ))
              )}
            </div>
          )}
        </Stack>
      </Collapse>

      <DiscapacidadCargaFamiliarModal
        opened={discModalOpened}
        onClose={closeDiscModal}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />

      <EnfermedadCargaFamiliarModal
        opened={enfModalOpened}
        onClose={closeEnfModal}
        cargaId={Number(carga.id)}
        servidorId={servidorId}
      />
    </Stack>
  );
}

interface Props {
  servidorId: number;
}

export function FamiliaTab({ servidorId }: Props) {
  const [opened, { open, close }] = useDisclosure(false);
  const [editItem, setEditItem] = useState<CargaFamiliar | null>(null);

  const { data: cargas = [], isLoading } = useCargasFamiliares(servidorId);
  const { eliminar, toggleEstado } = useCargaFamiliarMutations(servidorId);

  const handleClose = () => {
    setEditItem(null);
    close();
  };

  const handleDelete = (id: number) => {
    if (confirm("¿Eliminar esta carga familiar?")) eliminar.mutate(id);
  };

  return (
    <Stack gap="md">
      <Group justify="flex-end">
        <Button
          size="xs"
          color="emerald"
          variant="light"
          leftSection={<IconPlus size={14} />}
          onClick={() => {
            setEditItem(null);
            open();
          }}
        >
          Agregar carga familiar
        </Button>
      </Group>

      {isLoading ? (
        <Stack gap="xs">
          <Skeleton height={60} radius="md" />
          <Skeleton height={60} radius="md" />
        </Stack>
      ) : (cargas as CargaFamiliar[]).length === 0 ? (
        <EmptyState
          icon={IconUsers}
          title="Sin cargas familiares"
          description="Registra los familiares dependientes del servidor."
        />
      ) : (
        <Stack gap="xs">
          {(cargas as CargaFamiliar[]).map((c) => (
            <CargaRow
              key={c.id}
              carga={c}
              servidorId={servidorId}
              onEdit={(item) => {
                setEditItem(item);
                open();
              }}
              onDelete={handleDelete}
              onToggleEstado={(id) => toggleEstado.mutate(id)}
              togglePending={toggleEstado.isPending}
            />
          ))}
        </Stack>
      )}

      <CargaFamiliarModal
        opened={opened}
        onClose={handleClose}
        servidorId={servidorId}
        initialValues={editItem}
      />
    </Stack>
  );
}
