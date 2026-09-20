'use client'

import { Button, Menu } from '@mantine/core'
import {
  IconChevronDown,
  IconFileSpreadsheet,
  IconFileTypePdf,
  IconHistoryToggle,
  IconStethoscope,
  IconUserPlus,
} from '@tabler/icons-react'

interface Props {
  /** Cuántos servidores hay marcados en la tabla. */
  seleccionados: number
  puedeSolicitarCertificacion: boolean
  puedeVincularInicial: boolean
  exportando: 'excel' | 'pdf' | null
  onSolicitarCertificacion: () => void
  onExportar: (tipo: 'excel' | 'pdf') => void
  onVinculacionInicial: () => void
  onRegistrarFicha: () => void
}

/**
 * Las acciones de la cabecera del Expediente. Eran cinco botones iguales en
 * fila: las dos exportaciones y la carga inicial —que es temporal— pasan a un
 * menú, y queda a la vista la acción principal.
 */
export function ExpedienteAcciones({
  seleccionados,
  puedeSolicitarCertificacion,
  puedeVincularInicial,
  exportando,
  onSolicitarCertificacion,
  onExportar,
  onVinculacionInicial,
  onRegistrarFicha,
}: Props) {
  return (
    <>
      {puedeSolicitarCertificacion && seleccionados > 0 && (
        <Button
          variant="light"
          leftSection={<IconStethoscope size={16} />}
          onClick={onSolicitarCertificacion}
        >
          Solicitar certificación médica ({seleccionados})
        </Button>
      )}

      <Menu position="bottom-end" shadow="md" width={230}>
        <Menu.Target>
          <Button
            variant="default"
            rightSection={<IconChevronDown size={16} />}
            loading={exportando !== null}
          >
            Exportar
          </Button>
        </Menu.Target>
        <Menu.Dropdown>
          <Menu.Item
            leftSection={<IconFileSpreadsheet size={16} />}
            onClick={() => onExportar('excel')}
          >
            Listado en Excel
          </Menu.Item>
          <Menu.Item
            leftSection={<IconFileTypePdf size={16} />}
            onClick={() => onExportar('pdf')}
          >
            Listado en PDF
          </Menu.Item>
          {/* Carga inicial: solo aparece mientras dure la migración, para quien
              tenga el permiso. Al revocarlo la opción desaparece sola. */}
          {puedeVincularInicial && (
            <>
              <Menu.Divider />
              <Menu.Item
                leftSection={<IconHistoryToggle size={16} />}
                onClick={onVinculacionInicial}
              >
                Vinculación inicial
              </Menu.Item>
            </>
          )}
        </Menu.Dropdown>
      </Menu>

      {/* «Registrar ficha» y no «Nuevo servidor»: esto crea a la persona, no
          la contrata. El vínculo se registra en el paso siguiente. */}
      <Button
        variant="light"
        leftSection={<IconUserPlus size={16} />}
        onClick={onRegistrarFicha}
      >
        Registrar ficha
      </Button>
    </>
  )
}
