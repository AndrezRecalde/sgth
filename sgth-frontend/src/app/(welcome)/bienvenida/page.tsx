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
      <Center>
        <Stack gap="xl" w="100%" maw={1000}>
          {/* Tarjeta de Perfil Horizontal */}
          <Paper
            radius="xl"
            p={{ base: 'xl', sm: 'calc(2rem + 2vw)' }}
            bg="var(--mantine-color-paper)"
            withBorder
            style={{ 
              borderColor: "var(--mantine-color-default-border)",
              borderTop: "8px solid var(--mantine-color-blue-filled)",
              position: "relative",
              overflow: "hidden",
            }}
          >
            {/* Efecto de fondo sutil */}
            <Box
              style={{
                position: "absolute",
                top: -100,
                right: -100,
                width: 300,
                height: 300,
                borderRadius: "100%",
                background: "radial-gradient(circle, var(--mantine-color-blue-light) 0%, transparent 70%)",
                opacity: 0.5,
              }}
            />

            <Group wrap="nowrap" align="center" gap="xl">
              <Avatar
                color="blue"
                variant="filled"
                size={84}
                radius="100%"
                style={{ fontSize: 28, fontWeight: 700, border: "4px solid var(--mantine-color-body)" }}
              >
                {initials}
              </Avatar>

              <Box style={{ flex: 1 }}>
                <Text size="sm" fw={600} c="blue" tt="uppercase" style={{ letterSpacing: "1px" }} mb={4}>
                  Panel Principal
                </Text>
                <Title order={1} fw={800} size="h2" mb={8} style={{ letterSpacing: "-0.5px" }}>
                  ¡Hola, {nombreCompleto.split(" ")[0]}!
                </Title>
                <Text size="md" fw={500} c="dimmed" lh={1.4}>
                  {usuario.servidor?.puesto?.nombre ?? "Analista de Talento Humano 2"} <br/>
                  {usuario.servidor?.unidad_administrativa?.nombre ?? "Dirección de Gestión de Talento Humano"}
                </Text>
              </Box>

              <Stack gap={6} align="flex-end" display={{ base: "none", sm: "flex" }}>
                <Text size="sm" fw={600} c="dimmed">Roles activos</Text>
                <Group gap={6}>
                  {roles.length === 0 ? (
                    <Badge size="sm" variant="light" color="blue" radius="xl" tt="lowercase">servidor</Badge>
                  ) : (
                    roles.map((r) => (
                      <Badge key={r} size="sm" variant="light" color="blue" radius="xl" tt="lowercase">{r}</Badge>
                    ))
                  )}
                </Group>
              </Stack>
            </Group>
          </Paper>

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
              Tus subsistemas disponibles
            </Text>

            <SimpleGrid
              cols={{
                base: 1,
                sm: disponibles.length === 1 ? 1 : 2,
                lg: disponibles.length <= 2 ? disponibles.length : 3,
              }}
              spacing="xl"
            >
              {disponibles.map((key) => {
                const cfg = SUBSISTEMA_CONFIG[key];
                const Icon = cfg.icon;

                return (
                    <Card
                    key={key}
                    withBorder
                    radius="xl"
                    p="xl"
                    shadow="sm"
                    bg="var(--mantine-color-paper)"
                    style={{
                      cursor: "pointer",
                      transition: "all 0.3s ease",
                      borderColor: "var(--mantine-color-default-border)",
                      display: "flex",
                      flexDirection: "column",
                      alignItems: "center",
                      justifyContent: "center",
                      textAlign: "center",
                      minHeight: 240,
                    }}
                    onClick={() => handleIngresar(key)}
                    onMouseEnter={(e) => {
                      e.currentTarget.style.transform = "translateY(-6px)";
                      e.currentTarget.style.boxShadow = "var(--mantine-shadow-lg)";
                    }}
                    onMouseLeave={(e) => {
                      e.currentTarget.style.transform = "none";
                      e.currentTarget.style.boxShadow = "var(--mantine-shadow-sm)";
                    }}
                  >
                    <ThemeIcon
                      color="gray"
                      variant="transparent"
                      size={64}
                      mb="lg"
                    >
                      <Icon size={40} stroke={1.2} style={{ color: "var(--mantine-color-text)" }} />
                    </ThemeIcon>

                    <Text fw={700} size="xl" mb={8} style={{ letterSpacing: "-0.01em", color: "var(--mantine-color-text)" }}>
                      {cfg.label}
                    </Text>
                    <Text size="sm" c="dimmed" lh={1.6}>
                      {cfg.descripcion}
                    </Text>
                  </Card>
                );
              })}
            </SimpleGrid>
          </Box>
        </Stack>
      </Center>
    </Box>
  );
}
