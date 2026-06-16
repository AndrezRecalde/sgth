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
  Button,
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
      <Stack gap="xl" w="100%" maw={1000} mx="auto">
        {/* TARJETA DE PERFIL HORIZONTAL */}
        <Card
          withBorder
          radius="md"
          p={{ base: 'xl', md: 40 }}
          bg="var(--mantine-color-paper)"
          style={{ borderColor: "var(--mantine-color-default-border)" }}
        >
          <Group wrap="nowrap" align="center" justify="space-between">
            <Group wrap="nowrap" gap="xl">
              <Avatar
                color="teal"
                variant="light"
                size={96}
                radius="100%"
                style={{ fontSize: 36, fontWeight: 500 }}
              >
                {initials}
              </Avatar>
              <Box>
                <Title order={2} fw={700} size="h3" mb={4} style={{ color: "var(--mantine-color-text)" }}>
                  {nombreCompleto}
                </Title>
                <Text size="md" c="dimmed" fw={500}>
                  {usuario.servidor?.puesto?.nombre ?? "Analista de Talento Humano 2"}
                </Text>
                <Text size="md" c="dimmed" fw={500} mb="md">
                  {usuario.servidor?.unidad_administrativa?.nombre ?? "Dirección de Gestión de Talento Humano"}
                </Text>
                <Group gap={8}>
                  <Badge size="md" variant="light" color="teal" radius="xl" tt="lowercase">admin-uath</Badge>
                  {roles.length === 0 ? (
                    <Badge size="md" variant="light" color="blue" radius="xl" tt="lowercase">servidor</Badge>
                  ) : (
                    roles.map((r) => (
                      <Badge key={r} size="md" variant="light" color="blue" radius="xl" tt="lowercase">{r}</Badge>
                    ))
                  )}
                  <Badge size="md" variant="light" color="violet" radius="xl" tt="capitalize">Activo</Badge>
                </Group>
              </Box>
            </Group>
            
            <Stack align="flex-end" justify="flex-start" h="100%" display={{ base: 'none', sm: 'flex' }}>
              <Button variant="subtle" color="gray" radius="xl" size="md">
                Ver perfil
              </Button>
            </Stack>
          </Group>
        </Card>

        {/* TITULO DE SECCION */}
        <Text
          size="sm"
          fw={800}
          c="dimmed"
          tt="uppercase"
          style={{ letterSpacing: "0.05em" }}
          mt="md"
        >
          SELECCIONA UN SUBSISTEMA
        </Text>

        {/* GRID DE SUBSISTEMAS */}
        <SimpleGrid
          cols={{ base: 1, sm: 2, md: 3 }}
          spacing="lg"
        >
          {disponibles.map((key) => {
            const cfg = SUBSISTEMA_CONFIG[key];
            const Icon = cfg.icon;

            return (
              <Card
                key={key}
                withBorder
                radius="md"
                p="xl"
                shadow="none"
                bg="var(--mantine-color-paper)"
                style={{
                  cursor: "pointer",
                  transition: "box-shadow 0.2s ease, transform 0.2s ease",
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
                  e.currentTarget.style.boxShadow = "var(--mantine-shadow-md)";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.boxShadow = "none";
                }}
              >
                <ThemeIcon
                  color={cfg.color}
                  variant="light"
                  size={72}
                  radius="100%"
                  mb="xl"
                >
                  <Icon size={36} stroke={2} />
                </ThemeIcon>

                <Text fw={700} size="xl" mb={8} style={{ color: "var(--mantine-color-text)", letterSpacing: "-0.01em" }}>
                  {cfg.label}
                </Text>
                <Text size="sm" c="dimmed">
                  {cfg.descripcion}
                </Text>
              </Card>
            );
          })}
        </SimpleGrid>
      </Stack>
    </Box>
  );
}
