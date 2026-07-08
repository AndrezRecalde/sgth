import Link from "next/link";
import { usePathname } from "next/navigation";
import { UnstyledButton, Group, Text, Tooltip, Box } from "@mantine/core";
import { getNavIcon } from "@/lib/tablerIcons";
import classes from "./Sidebar.module.css";

interface Props {
  label: string;
  icon: string;
  href: string;
  collapsed: boolean;
  onClick?: () => void;
}

export function NavItem({ label, icon, href, collapsed, onClick }: Props) {
  const pathname = usePathname();
  // Active state: exact match for root/home pages, prefix match for others
  const exactPaths = [
    "/", "#", "/sgth", "/salud", "/portal",
    "/salud/sso",
    "/salud/farmacia",
  ];
  const isActive = exactPaths.includes(href)
    ? pathname === href
    : pathname === href || pathname.startsWith(`${href}/`);

  const content = (
    <UnstyledButton
      component={Link}
      href={href}
      onClick={onClick}
      className={`${classes.navItem} ${isActive ? classes.navItemActive : ""}`}
    >
      <Group
        wrap="nowrap"
        justify={collapsed ? "center" : "flex-start"}
        gap="lg"
      >
        <Box style={{ display: "flex", alignItems: "center" }}>
          {getNavIcon(icon)}
        </Box>
        {!collapsed && (
          <Text size="sm" fw="inherit">
            {label}
          </Text>
        )}
      </Group>
    </UnstyledButton>
  );

  if (collapsed) {
    return (
      <Tooltip label={label} position="right">
        {content}
      </Tooltip>
    );
  }
  return content;
}
