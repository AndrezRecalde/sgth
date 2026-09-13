'use client'

import { Modal, type ModalProps } from '@mantine/core'
import { useMobileBreakpoint } from '@/hooks/useMobileBreakpoint'

type Props = Omit<ModalProps, 'fullScreen'>

/**
 * El único modal del sistema. Toda ventana modal se monta sobre este, ya sea
 * directamente o a través de `FormModal`.
 *
 * Existe porque cada uno de los ~80 modales resolvía por su cuenta la versión
 * móvil, y una cuarta parte se la olvidaba: un modal centrado con quince campos
 * en un teléfono es una ventana diminuta con scroll dentro de scroll. Aquí se
 * decide una vez: pantalla completa y sin radio por debajo de 768 px.
 *
 * El resto de las props de Mantine pasan tal cual. El radio, el centrado y la
 * sombra de escritorio los pone el tema. Los botones van en `ModalFooter`.
 */
export function SgthModal({ radius, ...props }: Props) {
  const { isMobile } = useMobileBreakpoint()

  return <Modal {...props} fullScreen={isMobile} radius={isMobile ? 0 : radius} />
}
