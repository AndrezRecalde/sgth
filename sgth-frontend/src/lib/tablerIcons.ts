import * as Icons from '@tabler/icons-react'
import React from 'react'

export function getNavIcon(name: string) {
  const IconComponent = (Icons as any)[name]
  if (!IconComponent) {
    return React.createElement(Icons.IconPoint, { size: 18 })
  }
  return React.createElement(IconComponent, { size: 18 })
}
