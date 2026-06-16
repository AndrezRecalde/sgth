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
  Avatar,
  Box,
  Title,
  Paper,
  ActionIcon,
  Grid,
  Divider,
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
  IconArrowRight,
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
    queryKey: ["mi-perfil-bienvenida"],
    queryFn: async () => {
      const res = await api.get<{
        datos: UsuarioAuth;
      }>("/auth/perfil");
      const perfil = res.data.datos;
      if (perfil && token) {
        setAuth(token, perfil);
      }
      return perfil;
    },
    enabled: !!token && !!usuario,
    staleTime: 0,
  });

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
        minHeight: "100dvh", 
        padding: "2rem"
      }}
      bg="var(--mantine-color-body)"
    >
      <Grid w="100%" maw={1200} mx="auto" align="stretch">
        {/* COLUMNA IZQUIERDA: PERFIL */}
        <Grid.Col span={{ base: 12, md: 4, lg: 4 }}>
          <Card
            withBorder
            radius="lg"
            p={0}
            shadow="sm"
            bg="var(--mantine-color-paper)"
            style={{ borderColor: "var(--mantine-color-default-border)", height: "100%" }}
          >
            {/* Cover Photo / Banner Decorativo */}
            <Box
              h={100}
              style={{
                background: "linear-gradient(135deg, var(--mantine-color-blue-8) 0%, var(--mantine-color-blue-5) 100%)",
              }}
            />
            <Box p="xl" style={{ position: "relative" }}>
              <Avatar
                color="blue"
                variant="filled"
                size={84}
                radius="100%"
                style={{
                  fontSize: 28,
                  fontWeight: 700,
                  border: "4px solid var(--mantine-color-paper)",
                  position: "absolute",
                  top: -42,
                }}
              >
                {initials}
              </Avatar>

              <Box pt={48} mb="xl">
                <Title order={2} fw={700} size="h3" mb={4} style={{ letterSpacing: "-0.5px" }}>
                  {nombreCompleto}
                </Title>
                <Text size="sm" fw={500} c="dimmed">
                  {usuario.servidor?.puesto?.nombre ?? "Analista de Talento Humano 2"}
                </Text>
                <Text size="xs" fw={500} c="dimmed" mt={4}>
                  {usuario.servidor?.unidad_administrativa?.nombre ?? "Dirección de Gestión de Talento Humano"}
                </Text>
              </Box>

              <Divider mb="lg" />

              <Stack gap="sm">
                <Text size="xs" fw={700} c="dimmed" tt="uppercase" style={{ letterSpacing: "0.5px" }}>
                  Roles y Accesos
                </Text>
                <Group gap={6}>
                  <Badge size="sm" variant="light" color="violet" radius="sm" tt="capitalize">Activo</Badge>
                  {roles.length === 0 ? (
                    <Badge size="sm" variant="outline" color="blue" radius="sm" tt="lowercase">servidor</Badge>
                  ) : (
                    roles.map((r) => (
                      <Badge key={r} size="sm" variant="outline" color="blue" radius="sm" tt="lowercase">{r}</Badge>
                    ))
                  )}
                </Group>
              </Stack>
            </Box>
          </Card>
        </Grid.Col>

        {/* COLUMNA DERECHA: HERRAMIENTAS */}
        <Grid.Col span={{ base: 12, md: 8, lg: 8 }}>
          <Box pt={{ base: 0, md: "md" }} pl={{ base: 0, md: "md" }}>
            <Title order={2} size="h3" fw={700} mb="xs" style={{ letterSpacing: "-0.5px" }}>
              Tus Herramientas
            </Title>
            <Text size="sm" c="dimmed" mb="xl">
              Selecciona un subsistema para comenzar a trabajar
            </Text>

            <Stack gap="md">
              {disponibles.map((key) => {
                const cfg = SUBSISTEMA_CONFIG[key];
                const Icon = cfg.icon;

                return (
                  <Card
                    key={key}
                    withBorder
                    radius="lg"
                    p="lg"
                    shadow="none"
                    bg="transparent"
                    style={{
                      cursor: "pointer",
                      transition: "all 0.2s ease",
                      borderColor: "var(--mantine-color-default-border)",
                    }}
                    onClick={() => handleIngresar(key)}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.backgroundColor = "var(--mantine-color-default-hover)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.backgroundColor = "transparent";
                    }}
                  >
                    <Group wrap="nowrap" align="center" justify="space-between">
                      <Group wrap="nowrap" gap="lg" style={{ flex: 1 }}>
                        <ThemeIcon
                          color={cfg.color}
                          variant="light"
                          size={56}
                          radius="md"
                        >
                          <Icon size={28} stroke={1.5} />
                        </ThemeIcon>

                        <Box>
                          <Text fw={700} size="lg" mb={4} style={{ color: "var(--mantine-color-text)", letterSpacing: "-0.01em" }}>
                            {cfg.label}
                          </Text>
                          <Text size="sm" c="dimmed" lh={1.4}>
                            {cfg.descripcion}
                          </Text>
                        </Box>
                      </Group>
                      
                      <ActionIcon variant="transparent" color="gray" radius="xl" style={{ pointerEvents: 'none' }}>
                        <IconArrowRight size={24} stroke={1.5} />
                      </ActionIcon>
                    </Group>
                  </Card>
                );
              })}
            </Stack>
          </Box>
        </Grid.Col>
      </Grid>
    </Box>
  );
}
