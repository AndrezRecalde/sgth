"use client";

import {
  Card,
  Group,
  Stack,
  Text,
  Avatar,
  Divider,
} from "@mantine/core";
import type { UsuarioAuth } from "@/store/auth.store";
import { StatusBadge } from "@/components/ui";
import { useIdentidadPublica } from "@/features/comunicacion/hooks/useIdentidadPublica";

/** Mientras llegan los datos de Identidad institucional, el nombre de siempre. */
const NOMBRE_INSTITUCION = "Gobierno Autónomo Descentralizado Provincial de Esmeraldas";

interface Props {
  usuario: UsuarioAuth;
}

export function PerfilServidorCard({ usuario }: Props) {
  const servidor = usuario.servidor;
  const { data: identidad } = useIdentidadPublica();

  const nombreCompleto =
    usuario.nombre_completo ||
    [servidor?.nombre, servidor?.apellido].filter(Boolean).join(" ") ||
    usuario.email;

  const initials =
    nombreCompleto
      .split(" ")
      .slice(0, 2)
      .map((w: string) => w[0] ?? "")
      .join("")
      .toUpperCase() || "US";

  const activo = usuario.activo ?? false;

  return (
    <Card withBorder radius="xl" p="md">
      <Stack gap="lg">
        {/* El nombre oficial de Identidad institucional; antes iba escrito
            aquí, partido a mano en dos líneas. */}
        <Text size="xs" fw={600} c="dimmed" tt="uppercase" ta="center" maw={420} mx="auto" style={{ textWrap: "balance" }}>
          {identidad?.datos.nombre_oficial || NOMBRE_INSTITUCION}
        </Text>

        <Divider />

        <Group gap="lg" wrap="nowrap">
          <Avatar
            size={84}
            radius="xl"
            style={{ fontSize: 28, fontWeight: 700 }}
          >
            {initials}
          </Avatar>

          <Stack gap={4} style={{ flex: 1 }}>
            <Text fw={700} size="lg">
              {nombreCompleto}
            </Text>

            {servidor?.puesto?.nombre && (
              <Text size="sm" c="dimmed">
                {servidor.puesto.nombre}
              </Text>
            )}

            {servidor?.unidad_administrativa?.nombre && (
              <Text size="sm" c="dimmed">
                {servidor.unidad_administrativa.nombre}
              </Text>
            )}

            <Group gap={6} mt={6}>
              {servidor?.tipo_nombramiento_label && (
                <StatusBadge>
                  {servidor.tipo_nombramiento_label}
                </StatusBadge>
              )}
              <StatusBadge tone={activo ? 'success' : 'danger'}>
                {activo ? "Activo" : "Inactivo"}
              </StatusBadge>
            </Group>
          </Stack>
        </Group>
      </Stack>
    </Card>
  );
}
