import { Avatar, Text, Box } from '@mantine/core'
import { useAuth } from '@/hooks/useAuth'
import classes from './Sidebar.module.css'

interface Props { collapsed: boolean }

export function SidebarUserRow({ collapsed }: Props) {
  const { usuario } = useAuth()
  if (!usuario) return null

  const displayName = usuario.nombre_completo
    || usuario.servidor?.nombre
    || usuario.email
    || 'Usuario'

  const initials = displayName
    .split(' ')
    .slice(0, 2)
    .map(w => w[0] ?? '')
    .join('')
    .toUpperCase()

  const role = usuario.roles?.[0] || 'Usuario'

  if (collapsed) {
    return (
      <Box className={classes.userRowCollapsed}>
        <Avatar color="emerald" className={classes.avatarCollapsed}>
          {initials}
        </Avatar>
      </Box>
    )
  }

  return (
    <Box className={classes.userRow}>
      <Avatar color="emerald" className={classes.avatar}>
        {initials}
      </Avatar>
      <Text className={classes.title} truncate>{displayName}</Text>
      <Text className={classes.subtitle} truncate>{role}</Text>
    </Box>
  )
}
