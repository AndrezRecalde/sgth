"use client";

import Link from "next/link";
import { useState } from "react";
import {
  UnstyledButton,
  Group,
  Text,
  Tooltip,
  Box,
  Collapse,
  Badge,
} from "@mantine/core";
import { IconChevronRight } from "@tabler/icons-react";
import { usePathname } from "next/navigation";
import { getNavIcon } from "@/lib/tablerIcons";
import type { NavItem } from "@/config/nav";
import classes from "./Sidebar.module.css";

interface Props {
  item: NavItem;
  collapsed: boolean;
  onClick?: () => void;
}

export function NavItemNested({ item, collapsed, onClick }: Props) {
  const pathname = usePathname();

  const isChildActive =
    item.children?.some(
      (child) =>
        pathname === child.href || pathname.startsWith(`${child.href}/`),
    ) ?? false;

  const [opened, setOpened] = useState(isChildActive);

  // Abrir el submenú cuando la navegación activa a un hijo. Se ajusta durante
  // el render comparando con el valor anterior, en lugar de en un efecto: así
  // no se dispara un render en cascada por cada cambio de ruta.
  const [prevChildActive, setPrevChildActive] = useState(isChildActive);
  if (isChildActive !== prevChildActive) {
    setPrevChildActive(isChildActive);
    if (isChildActive) setOpened(true);
  }

  const content = (
    <UnstyledButton
      onClick={() => setOpened((o) => !o)}
      className={`${classes.navItem} ${isChildActive ? classes.navItemActive : ""}`}
    >
      <Group
        wrap="nowrap"
        justify={collapsed ? "center" : "space-between"}
        gap="lg"
        w="100%"
      >
        <Group wrap="nowrap" gap="lg">
          <Box style={{ display: "flex", alignItems: "center" }}>
            {getNavIcon(item.icon)}
          </Box>
          {!collapsed && (
            <Text size="sm" fw="inherit">
              {item.label}
            </Text>
          )}
        </Group>
        {!collapsed && (
          <IconChevronRight
            size={14}
            style={{
              transform: opened ? "rotate(90deg)" : "rotate(0deg)",
              transition: "transform 200ms ease",
            }}
          />
        )}
      </Group>
    </UnstyledButton>
  );

  if (collapsed) {
    return (
      <Tooltip label={item.label} position="right">
        {content}
      </Tooltip>
    );
  }

  return (
    <>
      {content}
      <Collapse expanded={opened}>
        <Box pl="md" pt={2} pb={2}>
          {item.children?.map((child) => {
            const exactChildPaths = [
              '/salud/farmacia',
              '/salud/sso',
              '/salud/enfermeria',
              '/sgth/reclutamiento/convocatorias',
              '/sgth/reclutamiento/plantillas',
              '/sgth/expediente',
            ]
            const isActive = (child as NavItem).children
              ? (child as NavItem).children!.some(
                  (c) =>
                    pathname === c.href || pathname.startsWith(`${c.href}/`),
                )
              : exactChildPaths.includes(child.href)
                ? pathname === child.href
                : pathname === child.href ||
                  pathname.startsWith(`${child.href}/`);

            return (
              <UnstyledButton
                key={child.href}
                component={Link}
                href={child.href}
                onClick={onClick}
                className={`${classes.navItem} ${isActive ? classes.navItemActive : ""}`}
                style={{ paddingLeft: "0.75rem" }}
              >
                <Group wrap="nowrap" gap="sm" justify="space-between" w="100%">
                  <Group wrap="nowrap" gap="sm">
                    <Box
                      style={{
                        display: "flex",
                        alignItems: "center",
                        opacity: 0.7,
                      }}
                    >
                      {getNavIcon(child.icon)}
                    </Box>
                    <Text size="sm" fw="inherit">
                      {child.label}
                    </Text>
                  </Group>
                  {child.badge && (
                    <Badge size="xs" color="red" circle>
                      {child.badge}
                    </Badge>
                  )}
                </Group>
              </UnstyledButton>
            );
          })}
        </Box>
      </Collapse>
    </>
  );
}
