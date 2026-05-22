import { Text, Box } from '@mantine/core'
import classes from './Sidebar.module.css'

interface Props { label: string; collapsed: boolean }

export function NavGroup({ label, collapsed }: Props) {
  if (collapsed) return <Box className={classes.navGroupCollapsed} />
  return (
    <Text className={classes.navGroup} truncate>
      {label}
    </Text>
  )
}
