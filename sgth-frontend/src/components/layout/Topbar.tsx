"use client";

import {
  Group,
  Burger,
  ActionIcon,
  useMantineColorScheme,
  Box,
  Avatar,
  Menu,
  Text,
  UnstyledButton,
  Switch,
  ThemeIcon,
  Tooltip,
} from "@mantine/core";
import {
  IconSun,
  IconMoon,
  IconBell,
  IconSettings,
  IconLogout,
  IconUsers,
} from "@tabler/icons-react";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import classes from "./Topbar.module.css";

interface TopbarProps {
  mobileOpened: boolean;
  desktopOpened: boolean;
  onMobileToggle: () => void;
  onDesktopToggle: () => void;
}

export function Topbar({
  mobileOpened,
  desktopOpened,
  onMobileToggle,
  onDesktopToggle,
}: TopbarProps) {
  const { colorScheme, toggleColorScheme } = useMantineColorScheme();
  const { usuario, clearAuth } = useAuth();
  const router = useRouter();

  const initials = usuario?.name
    ? usuario.name.substring(0, 2).toUpperCase()
    : "US";
  const role = usuario?.roles?.[0] || "Usuario";

  const handleLogout = (e?: React.MouseEvent) => {
    e?.preventDefault();
    clearAuth();
    window.location.href = "/login?logout=true";
  };

  return (
    <Group
      h="100%"
      px="md"
      justify="space-between"
      align="center"
      style={{
        borderBottom: "0.5px solid var(--mantine-color-default-border)",
      }}
    >
      {/* Sección Izquierda: Burger y Logo */}
      <Group gap="sm">
        <Burger
          opened={mobileOpened}
          onClick={onMobileToggle}
          hiddenFrom="md"
          lineSize={2}
          size="sm"
        />
        <Burger
          opened={desktopOpened}
          onClick={onDesktopToggle}
          visibleFrom="md"
          lineSize={2}
          size="sm"
        />

        <Group
          gap="xs"
          style={{ cursor: "pointer" }}
          onClick={() => router.push("/")}
        >
          <ThemeIcon variant="filled" color="emerald" size="md" radius="sm">
            <IconUsers size={16} />
          </ThemeIcon>
          <Text fw={800} size="lg" style={{ letterSpacing: "-0.5px" }}>
            SGTH
          </Text>
        </Group>
      </Group>

      {/* Sección Derecha: Notificaciones y Menú de Usuario */}
      <Group gap="md">
        <Tooltip label="Notificaciones">
          <ActionIcon
            variant="subtle"
            color="gray"
            size="lg"
            radius="xl"
            aria-label="Notificaciones"
          >
            <IconBell size={20} />
          </ActionIcon>
        </Tooltip>

        <Menu width={280} position="bottom-end" shadow="md" withinPortal>
          <Menu.Target>
            <UnstyledButton
              style={{ display: "flex", alignItems: "center" }}
              aria-label="Menú de usuario"
            >
              <Avatar
                color="emerald"
                size="md"
                style={{ cursor: "pointer", fontWeight: 600 }}
              >
                {initials}
              </Avatar>
            </UnstyledButton>
          </Menu.Target>

          <Menu.Dropdown>
            {/* Cabecera de perfil */}
            <div className={classes.userMenuHeader}>
              <Avatar
                color="emerald"
                size="md"
                style={{ fontWeight: 600 }}
              >
                {initials}
              </Avatar>
              <div className={classes.userMenuInfo}>
                <Text className={classes.userMenuName} truncate>
                  {usuario?.name}
                </Text>
                <Text className={classes.userMenuRole} truncate>
                  {role}
                </Text>
              </div>
            </div>

            {/* Fila de modo oscuro con Switch */}
            <Box
              px="md"
              py="xs"
              style={{ cursor: "pointer" }}
              /* onClick={(e) => {
                // Prevenir doble toggle si el clic fue directamente en el input del switch
                if ((e.target as HTMLElement).tagName !== 'INPUT') {
                  toggleColorScheme()
                }
              }} */
            >
              <Group justify="space-between">
                <Group gap="sm">
                  {colorScheme === "dark" ? (
                    <IconSun size={16} />
                  ) : (
                    <IconMoon size={16} />
                  )}
                  <Text size="sm">Modo oscuro</Text>
                </Group>
                <Switch
                  checked={colorScheme === "dark"}
                  onChange={toggleColorScheme}
                  size="xs"
                  color="emerald"
                  styles={{ track: { cursor: "pointer" } }}
                  aria-label="Toggle modo oscuro"
                />
              </Group>
            </Box>

            <Menu.Divider />

            {/* Configuración */}
            <Menu.Item
              leftSection={<IconSettings size={16} />}
              onClick={() => router.push("/configuracion")}
            >
              Configuración
            </Menu.Item>

            {/* Cerrar sesión */}
            <Menu.Item
              leftSection={<IconLogout size={16} />}
              color="red"
              onClick={handleLogout}
            >
              Cerrar sesión
            </Menu.Item>
          </Menu.Dropdown>
        </Menu>
      </Group>
    </Group>
  );
}
