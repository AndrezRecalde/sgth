"use client";

import {
  Drawer,
  Tabs,
  Avatar,
  Group,
  Stack,
  Text,
  Badge,
  Button,
  ThemeIcon,
  ScrollArea,
} from "@mantine/core";
import {
  IconUser,
  IconBriefcase,
  IconSchool,
  IconUsers,
  IconCreditCard,
  IconPaperclip,
  IconFileDescription,
  IconHeart,
  IconEdit,
} from "@tabler/icons-react";
import { useMobileBreakpoint } from "@/hooks/useMobileBreakpoint";
import { DatosPersonalesTab } from "./tabs/DatosPersonalesTab";
import { LaboralTab } from "./tabs/LaboralTab";
import { AcademicoTab } from "./tabs/AcademicoTab";
import { FamiliaTab } from "./tabs/FamiliaTab";
import { CuentasBancariasTab } from "./tabs/CuentasBancariasTab";
import { DocumentosTab } from "./tabs/DocumentosTab";
import { DeclaracionesTab } from "./tabs/DeclaracionesTab";
import { CondicionTab } from "./tabs/CondicionTab";
import type { ServidorConRelaciones } from "@/types/api";

interface Props {
  opened: boolean;
  onClose: () => void;
  servidor: ServidorConRelaciones | null;
  onEdit?: (s: ServidorConRelaciones) => void;
}

const REGIMEN_COLORS: Record<string, string> = {
  losep: "emerald",
  codigo_trabajo: "blue",
};

const REGIMEN_LABELS: Record<string, string> = {
  losep: "LOSEP",
  codigo_trabajo: "Cód. Trabajo",
};

export function ServidorDetail({ opened, onClose, servidor, onEdit }: Props) {
  const { isMobile } = useMobileBreakpoint();

  if (!servidor) return null;

  const servidorId = Number(servidor.id);
  const nombreCompleto = [
    servidor.apellido,
    servidor.segundo_apellido,
    servidor.nombre,
    servidor.segundo_nombre,
  ]
    .filter(Boolean)
    .join(" ");

  const initials =
    [servidor.nombre?.charAt(0), servidor.apellido?.charAt(0)]
      .filter(Boolean)
      .join("")
      .toUpperCase() || "?";

  return (
    <Drawer
      opened={opened}
      onClose={onClose}
      title={
        <Group gap="xs">
          <ThemeIcon color="emerald" variant="light" size="md" radius="md">
            <IconUser size={16} />
          </ThemeIcon>
          <Text fw={700} size="md">
            Expediente Digital
          </Text>
        </Group>
      }
      position="right"
      size={isMobile ? "100%" : 720}
      padding="lg"
    >
      <ScrollArea h="calc(100vh - 80px)">
        <Stack gap="md">
          {/* Header del servidor */}
          <Group
            p="md"
            style={{
              borderRadius: 12,
              border: "1px solid var(--mantine-color-default-border)",
              background: "var(--mantine-color-emerald-light)",
            }}
          >
            <Avatar size={52} radius="xl" color="emerald" fw={700}>
              {initials}
            </Avatar>
            <Stack gap={2} style={{ flex: 1 }}>
              <Text fw={700} size="md">
                {nombreCompleto}
              </Text>
              <Text size="sm" c="dimmed" ff="monospace">
                CI: {servidor.cedula ?? "-"}
              </Text>
              <Group gap="xs">
                {servidor.regimen_laboral && (
                  <Badge
                    color={REGIMEN_COLORS[servidor.regimen_laboral] ?? "gray"}
                    variant="light"
                    size="xs"
                  >
                    {REGIMEN_LABELS[servidor.regimen_laboral] ??
                      servidor.regimen_laboral}
                  </Badge>
                )}
                {servidor.contrato_vigente?.estado && (
                  <Badge
                    color={
                      servidor.contrato_vigente.estado === "vigente"
                        ? "emerald"
                        : "gray"
                    }
                    variant="dot"
                    size="xs"
                  >
                    {servidor.contrato_vigente.estado}
                  </Badge>
                )}
              </Group>
            </Stack>
            {onEdit && (
              <Button
                size="xs"
                variant="light"
                color="gray"
                leftSection={<IconEdit size={12} />}
                onClick={() => onEdit(servidor)}
              >
                Editar
              </Button>
            )}
          </Group>

          {/* Tabs del expediente */}
          <Tabs defaultValue="personal" color="emerald">
            <Tabs.List>
              <Tabs.Tab value="personal" leftSection={<IconUser size={13} />}>
                Personal
              </Tabs.Tab>
              <Tabs.Tab
                value="laboral"
                leftSection={<IconBriefcase size={13} />}
              >
                Laboral
              </Tabs.Tab>
              <Tabs.Tab
                value="academico"
                leftSection={<IconSchool size={13} />}
              >
                Académico
              </Tabs.Tab>
              <Tabs.Tab value="familia" leftSection={<IconUsers size={13} />}>
                Familia
              </Tabs.Tab>
              <Tabs.Tab
                value="cuentas"
                leftSection={<IconCreditCard size={13} />}
              >
                Cuentas
              </Tabs.Tab>
              <Tabs.Tab
                value="documentos"
                leftSection={<IconPaperclip size={13} />}
              >
                Documentos
              </Tabs.Tab>
              <Tabs.Tab
                value="declaraciones"
                leftSection={<IconFileDescription size={13} />}
              >
                Declaraciones
              </Tabs.Tab>
              <Tabs.Tab value="condicion" leftSection={<IconHeart size={13} />}>
                Condición
              </Tabs.Tab>
            </Tabs.List>

            <Tabs.Panel value="personal" pt="md">
              <DatosPersonalesTab servidor={servidor} />
            </Tabs.Panel>
            <Tabs.Panel value="laboral" pt="md">
              <LaboralTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="academico" pt="md">
              <AcademicoTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="familia" pt="md">
              <FamiliaTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="cuentas" pt="md">
              <CuentasBancariasTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="documentos" pt="md">
              <DocumentosTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="declaraciones" pt="md">
              <DeclaracionesTab servidorId={servidorId} />
            </Tabs.Panel>
            <Tabs.Panel value="condicion" pt="md">
              <CondicionTab servidorId={servidorId} />
            </Tabs.Panel>
          </Tabs>
        </Stack>
      </ScrollArea>
    </Drawer>
  );
}
