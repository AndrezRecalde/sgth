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
  Button,
  Avatar,
  Box,
  Title,
  Tooltip,
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
      { label: "Viáticos", icon: IconPlane },
      { label: "Asistencia", icon: IconCalendarEvent },
      { label: "Nómina", icon: IconUsers },
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
      { label: "Mis tickets", icon: IconFolder },
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
      }>('/auth/perfil')
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
    <Box
      style={{
        minHeight: "calc(100vh - 70px)",
        backgroundColor: "var(--mantine-color-body)",
        padding: "2rem",
      }}
    >
      <Center>
        <Stack gap="xl" w="100%" maw={1000}>
          {/* Tarjeta de Perfil Horizontal */}
          <Card 
            withBorder 
            radius="md" 
            p="xl" 
            style={{ borderColor: 'var(--mantine-color-gray-3)' }}
          >
            <Group wrap="nowrap" align="flex-start" gap="xl">
              <Avatar
                color="emerald"
                variant="light"
                size={96}
                radius="100%"
                style={{ fontSize: 32, fontWeight: 600 }}
              >
                {initials}
              </Avatar>
              
              <Box style={{ flex: 1 }}>
                <Title order={2} fw={700} size="h3" mb={4}>
                  {nombreCompleto}
                </Title>
                <Text size="sm" fw={500} c="dimmed">
                  {usuario.servidor?.puesto?.nombre ?? "Analista de Talento Humano 2"}
                </Text>
                <Text size="sm" fw={500} c="dimmed" mb="md">
                  {usuario.servidor?.unidad_administrativa?.nombre ?? "Dirección de Gestión de Talento Humano"}
                </Text>
                <Group gap="xs">
                  {roles.map((r, i) => {
                    const badgeColors = ["emerald", "blue", "violet", "orange"];
                    const color = badgeColors[i % badgeColors.length];
                    return (
                      <Badge key={r} size="sm" variant="light" color={color} radius="xl" tt="lowercase">
                        {r}
                      </Badge>
                    );
                  })}
                  {roles.length === 0 && (
                    <Badge size="sm" variant="light" color="emerald" radius="xl" tt="lowercase">
                      servidor
                    </Badge>
                  )}
                  <Badge size="sm" variant="light" color="violet" radius="xl" tt="capitalize">
                    Activo
                  </Badge>
                </Group>
              </Box>
              
              {/* Último acceso */}
              <Stack gap={4} align="flex-end" display={{ base: 'none', sm: 'flex' }}>
                <Text size="xs" fw={600} c="dimmed" tt="none">
                  Último acceso
                </Text>
                <Text size="sm" fw={700}>
                  Hoy, 09:14
                </Text>
              </Stack>
            </Group>
          </Card>

          {/* Listado de Subsistemas */}
          <Box>
            <Text
              size="sm"
              fw={800}
              c="dimmed"
              tt="uppercase"
              mb="md"
              style={{ letterSpacing: "0.05em" }}
            >
              SELECCIONA UN SUBSISTEMA
            </Text>

            <SimpleGrid
              cols={{
                base: 1,
                sm: disponibles.length === 1 ? 1 : disponibles.length === 2 ? 2 : 3,
              }}
              spacing="lg"
            >
              {disponibles.map((key) => {
                const cfg = SUBSISTEMA_CONFIG[key];
                const Icon = cfg.icon;

                const tooltipContent = (
                  <Box p={4}>
                    <Text size="xs" fw={700} tt="uppercase" c="gray.4" mb={8}>
                      Módulos incluidos
                    </Text>
                    {cfg.modulos.map((m) => (
                      <Group key={m.label} gap="xs" wrap="nowrap" mb={4}>
                        <m.icon size={14} style={{ color: "var(--mantine-color-white)" }} />
                        <Text size="sm" fw={500} style={{ color: "var(--mantine-color-white)" }}>
                          {m.label}
                        </Text>
                      </Group>
                    ))}
                  </Box>
                );

                return (
                  <Tooltip
                    key={key}
                    label={tooltipContent}
                    position="top"
                    withArrow
                    transitionProps={{ transition: "pop", duration: 200 }}
                    color="dark"
                  >
                    <Card
                      withBorder
                      radius="lg"
                      p="xl"
                      style={{
                        cursor: "pointer",
                        display: "flex",
                        flexDirection: "column",
                        alignItems: "center",
                        justifyContent: "center",
                        height: "100%",
                        minHeight: 240,
                        transition: "all 0.2s ease",
                        borderColor: "var(--mantine-color-gray-2)",
                        boxShadow: "0 4px 12px rgba(0,0,0,0.03)",
                      }}
                      onClick={() => handleIngresar(key)}
                      onMouseEnter={(e) => {
                        e.currentTarget.style.borderColor = `var(--mantine-color-${cfg.color}-4)`;
                        e.currentTarget.style.transform = "translateY(-4px)";
                        e.currentTarget.style.boxShadow = "0 12px 24px rgba(0,0,0,0.08)";
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.borderColor = "var(--mantine-color-gray-2)";
                        e.currentTarget.style.transform = "none";
                        e.currentTarget.style.boxShadow = "0 4px 12px rgba(0,0,0,0.03)";
                      }}
                    >
                      <ThemeIcon
                        color={cfg.color}
                        variant="light"
                        size={64}
                        radius="100%"
                        mb="lg"
                      >
                        <Icon size={32} stroke={1.8} />
                      </ThemeIcon>

                      <Text
                        fw={800}
                        size="lg"
                        mb={8}
                        ta="center"
                        style={{ letterSpacing: "-0.01em", color: "var(--mantine-color-dark-8)" }}
                      >
                        {cfg.label}
                      </Text>
                      <Text size="sm" c="dimmed" ta="center" lh={1.4} px="sm">
                        {cfg.descripcion}
                      </Text>
                    </Card>
                  </Tooltip>
                );
              })}
            </SimpleGrid>
          </Box>
        </Stack>
      </Center>
    </Box>
  );
}
