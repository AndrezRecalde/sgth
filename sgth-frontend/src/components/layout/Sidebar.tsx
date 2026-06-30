"use client";

import { Box, ScrollArea, Text, Image } from "@mantine/core";
import { usePathname, useRouter } from "next/navigation";
import { useAuth } from "@/hooks/useAuth";
import { useStockBajoCount } from "@/features/dispensario/hooks/useInventarioMedicina";
import { buildNav } from "@/config/nav";
import { NavGroup } from "./NavGroup";
import { NavItem } from "./NavItem";
import { NavItemNested } from "./NavItemNested";
import classes from "./Sidebar.module.css";
import type { Subsistema } from "@/config/routes";

interface Props {
  collapsed: boolean;
  onNavClick?: () => void;
}

function getSubsistema(pathname: string): Subsistema {
  if (pathname.startsWith("/salud")) return "salud";
  if (pathname.startsWith("/portal")) return "portal";
  return "sgth";
}

const SUBSISTEMA_LABELS: Record<Subsistema, string> = {
  sgth: "Talento Humano",
  salud: "Dispensario Médico",
  portal: "Portal Servidor",
};

const SUBSISTEMA_COLORS: Record<Subsistema, string> = {
  sgth: "var(--mantine-color-emerald-6)",
  salud: "var(--mantine-color-blue-6)",
  portal: "var(--mantine-color-violet-6)",
};

export function Sidebar({ collapsed, onNavClick }: Props) {
  const { usuario } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  const subsistema = getSubsistema(pathname);
  const permisos = usuario?.permisos ?? [];

  const { data: stockBajoCount = 0 } = useStockBajoCount();

  const groups = buildNav(subsistema, permisos);

  const groupsConBadges = groups.map(group => ({
    ...group,
    items: group.items.map(item => {
      if (item.href === '/salud/farmacia' && item.children) {
        return {
          ...item,
          children: item.children.map(child =>
            child.href === '/salud/farmacia'
              ? {
                  ...child,
                  badge: stockBajoCount > 0
                    ? String(stockBajoCount)
                    : undefined,
                }
              : child
          ),
        }
      }
      return item
    }),
  }));

  return (
    <Box className={classes.sidebar}>
      {/* Top Header - Logo */}
      {!collapsed && (
        <Box
          px="md"
          py="lg"
          style={{
            display: "flex",
            justifyContent: "space-between",
            alignItems: "center",
          }}
        >
          <Box
            style={{ display: "flex", alignItems: "center", gap: "8px", cursor: "pointer" }}
            onClick={() => router.push("/bienvenida")}
          >
            <Image
              radius="lg"
              h={36}
              w={50}
              fit="contain"
              alt="logo"
              src={
                "https://prefecturadeesmeraldas.gob.ec/wp-content/uploads/2026/05/LogoCompleto-2.png"
              }
              fallbackSrc="https://placehold.co/600x400?text=Placeholder"
            />
            <Text fw={800} size="lg" style={{ letterSpacing: "-0.5px" }} color="#0f172a">
              GADPE
            </Text>
          </Box>
        </Box>
      )}

      <ScrollArea style={{ flex: 1 }} px="xs">
        {groupsConBadges.map((g, i) => (
          <Box key={i} pb="sm">
            <NavGroup label={g.label} collapsed={collapsed} />
            {g.items.map((item) =>
              item.children?.length ? (
                <NavItemNested
                  key={item.href}
                  item={item}
                  collapsed={collapsed}
                  onClick={onNavClick}
                />
              ) : (
                <NavItem
                  key={item.href}
                  {...item}
                  collapsed={collapsed}
                  onClick={onNavClick}
                />
              )
            )}
          </Box>
        ))}
      </ScrollArea>
    </Box>
  );
}
