"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import api from "@/lib/axios";
import type { UsuarioAuth } from "@/store/auth.store";
import {
  Stack,
  Group,
  Text,
  Badge,
  Card,
  Center,
  Loader,
  ThemeIcon,
  SimpleGrid,
  Divider,
  Avatar,
} from "@mantine/core";
import {
  IconUsers,
  IconBuildingHospital,
  IconUserCircle,
  IconPlane,
  IconCalendarEvent,
  IconBeach,
  IconStethoscope,
  IconPill,
  IconFolder,
  IconChevronRight,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { ROUTES } from "@/config/routes";
import { getSubsistemasDisponibles } from "@/config/nav";

const SUBSISTEMA_CONFIG = {
  sgth: {
    label: "SGTH",
    descripcion: "Gestión de Talento Humano",
    color: "emerald" as const,
    icon: IconUsers,
    home: ROUTES.SGTH.HOME,
    modulos: [
      { label: "Expediente", icon: IconFolder },
      { label: "Asistencia", icon: IconCalendarEvent },
      { label: "Nómina", icon: IconUsers },
      { label: "Estructura", icon: IconUsers },
    ],
  },
  salud: {
    label: "Dispensario",
    descripcion: "Sistema de Salud Ambulatoria",
    color: "blue" as const,
    icon: IconBuildingHospital,
    home: ROUTES.SALUD.HOME,
    modulos: [
      { label: "Consultas", icon: IconStethoscope },
      { label: "Odontología", icon: IconStethoscope },
      { label: "Farmacia", icon: IconPill },
      { label: "SSO", icon: IconUsers },
    ],
  },
  portal: {
    label: "Portal servidor",
    descripcion: "Mi espacio personal",
    color: "violet" as const,
    icon: IconUserCircle,
    home: ROUTES.PORTAL.HOME,
    modulos: [
      { label: "Mi perfil", icon: IconUserCircle },
      { label: "Permisos", icon: IconCalendarEvent },
      { label: "Vacaciones", icon: IconBeach },
      { label: "Viáticos", icon: IconPlane },
    ],
  },
} as const;

type SubsistemaKey = keyof typeof SUBSISTEMA_CONFIG;

export default function BienvenidaPage() {
  const router = useRouter();
  const { usuario, clearAuth, setAuth, token } = useAuth();

  useQuery({
    queryKey: ['mi-perfil-bienvenida'],
    queryFn: async () => {
      const res = await api.get<{
        datos: UsuarioAuth
      }>('/auth/me')
      const perfil = res.data.datos
      if (perfil && token) {
        setAuth(token, perfil)
      }
      return perfil
    },
    enabled: !!token && !!usuario,
    staleTime: 0,
  })

  useEffect(() => {
    if (!usuario) {
      router.replace(ROUTES.AUTH.LOGIN);
    }
  }, [usuario, router]);

  if (!usuario) {
    return (
      <Center h="100vh">
        <Loader color="emerald" size="xl" type="dots" />
      </Center>
    );
  }

  const roles = (usuario.roles as string[]) ?? [];
  const disponibles = getSubsistemasDisponibles(roles);

  const nombreCompleto =
    usuario.nombre_completo || usuario.usuario_ti || usuario.email;

  const initials =
    (nombreCompleto ?? "")
      .split(" ")
      .slice(0, 2)
      .map((w: string) => w[0] ?? "")
      .join("")
      .toUpperCase() || "US";

  const handleIngresar = (key: SubsistemaKey) => {
    router.push(SUBSISTEMA_CONFIG[key].home);
  };

  return (
    <Center py="xl" px="md">
      <Stack gap="xl" w="100%" maw={720}>
          {/* Tarjeta de perfil */}
          <Card withBorder radius="xl" p="xl">
            <Group gap="lg">
              <Avatar
                color="emerald"
                size={72}
                radius="xl"
                style={{ fontSize: 24, fontWeight: 700 }}
              >
                {initials}
              </Avatar>
              <Stack gap={4} style={{ flex: 1 }}>
                <Text fw={600} size="lg">
                  {nombreCompleto}
                </Text>
                <Text size="sm" c="dimmed">
                  {usuario.servidor?.puesto?.nombre ?? usuario.email}
                </Text>
                <Group gap={6} mt={4}>
                  {roles.map((r) => (
                    <Badge key={r} size="xs" variant="light" color="emerald">
                      {r}
                    </Badge>
                  ))}
                </Group>
              </Stack>
            </Group>
          </Card>

          {/* Selector de subsistemas */}
          <Stack gap="xs">
            <Text
              size="xs"
              fw={600}
              c="dimmed"
              tt="uppercase"
              style={{ letterSpacing: "0.05em" }}
            >
              Selecciona un subsistema
            </Text>

            <SimpleGrid
              cols={{
                base: 1,
                sm:
                  disponibles.length === 1
                    ? 1
                    : disponibles.length === 2
                      ? 2
                      : 3,
              }}
              spacing="md"
            >
              {disponibles.map((key) => {
                const cfg = SUBSISTEMA_CONFIG[key];
                const Icon = cfg.icon;

                return (
                  <Card
                    key={key}
                    withBorder
                    radius="xl"
                    p="lg"
                    style={{ cursor: "pointer" }}
                    onClick={() => handleIngresar(key)}
                  >
                    <Stack gap="md">
                      <Group justify="space-between">
                        <ThemeIcon
                          color={cfg.color}
                          variant="light"
                          size="xl"
                          radius="lg"
                        >
                          <Icon size={24} />
                        </ThemeIcon>
                        <IconChevronRight
                          size={16}
                          color="var(--mantine-color-dimmed)"
                        />
                      </Group>

                      <Stack gap={2}>
                        <Text fw={600} size="md">
                          {cfg.label}
                        </Text>
                        <Text size="xs" c="dimmed">
                          {cfg.descripcion}
                        </Text>
                      </Stack>

                      <Divider />

                      <Stack gap={6}>
                        {cfg.modulos.map((m) => (
                          <Group key={m.label} gap="xs">
                            <m.icon
                              size={12}
                              color={`var(--mantine-color-${cfg.color}-6)`}
                            />
                            <Text size="xs" c="dimmed">
                              {m.label}
                            </Text>
                          </Group>
                        ))}
                      </Stack>
                    </Stack>
                  </Card>
                );
              })}
            </SimpleGrid>
          </Stack>
        </Stack>
      </Center>
  );
}
