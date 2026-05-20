import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { UnstyledButton, Group, Text, Tooltip, Box } from '@mantine/core'
import { getNavIcon } from '@/lib/tablerIcons'

interface Props {
  label: string
  icon: string
  href: string
  collapsed: boolean
  onClick?: () => void
}

export function NavItem({ label, icon, href, collapsed, onClick }: Props) {
  const pathname = usePathname()
  // Active state: exact match or starts with href/ (except when href is / or #)
  const isActive = href !== '#' && href !== '/' 
    ? (pathname === href || pathname.startsWith(`${href}/`))
    : pathname === href

  const content = (
    <UnstyledButton
      component={Link}
      href={href}
      onClick={onClick}
      style={{
        display: 'block',
        width: '100%',
        padding: '10px 16px',
        backgroundColor: isActive ? 'rgba(16,185,129,0.18)' : 'transparent',
        borderLeft: `3px solid ${isActive ? '#10B981' : 'transparent'}`,
        color: isActive ? '#fff' : 'rgba(255,255,255,0.85)',
        transition: 'background-color 150ms ease',
      }}
    >
      <Group wrap="nowrap" justify={collapsed ? 'center' : 'flex-start'} gap="sm">
        <Box style={{ opacity: isActive ? 1 : 0.7 }}>{getNavIcon(icon)}</Box>
        {!collapsed && <Text size="sm" fw={isActive ? 600 : 400}>{label}</Text>}
      </Group>
    </UnstyledButton>
  )

  if (collapsed) {
    return <Tooltip label={label} position="right">{content}</Tooltip>
  }
  return content
}
