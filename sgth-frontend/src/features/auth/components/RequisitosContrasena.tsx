'use client'

import { List, ThemeIcon } from '@mantine/core'
import { IconCheck, IconPoint } from '@tabler/icons-react'
import { REQUISITOS_CONTRASENA } from '../schemas/cambiarPassword.schema'
import classes from './RequisitosContrasena.module.css'

interface Props {
  contrasena: string
}

/**
 * Las reglas de la contraseña nueva, a la vista mientras se escribe.
 *
 * Antes solo estaban en el placeholder, que desaparece en cuanto se teclea la
 * primera letra: quien no las había leído las descubría al enviar, una por
 * una. La de la cédula no se puede comprobar aquí —el navegador no la
 * conoce—, pero decirla evita el rechazo.
 */
export function RequisitosContrasena({ contrasena }: Props) {
  return (
    <List size="sm" spacing={4} center aria-live="polite">
      {REQUISITOS_CONTRASENA.map(({ texto, cumple }) => {
        const cumplido = cumple?.(contrasena) ?? false

        return (
          <List.Item
            key={texto}
            className={cumplido ? classes.cumplido : undefined}
            c={cumplido ? undefined : 'dimmed'}
            icon={
              <ThemeIcon
                size={18}
                radius="xl"
                variant={cumplido ? 'light' : 'transparent'}
                color={cumplido ? 'emerald' : 'slate'}
              >
                {cumplido ? <IconCheck size={12} /> : <IconPoint size={12} />}
              </ThemeIcon>
            }
          >
            {texto}
          </List.Item>
        )
      })}
    </List>
  )
}
