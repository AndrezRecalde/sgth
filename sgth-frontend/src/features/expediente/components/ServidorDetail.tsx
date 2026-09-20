'use client'

import { Button, Stack, Tabs, Tooltip } from '@mantine/core'
import {
  IconCreditCard,
  IconEdit,
  IconFileDescription,
  IconHeart,
  IconPaperclip,
  IconSchool,
  IconStethoscope,
  IconUser,
  IconUsers,
} from '@tabler/icons-react'
import { SgthDrawer } from '@/components/ui'
import { useServidor } from '../hooks/useServidor'
import { ServidorEncabezado, nombreCompletoDe } from './ServidorEncabezado'
import { AcademicoTab } from './tabs/AcademicoTab'
import { CondicionTab } from './tabs/CondicionTab'
import { CuentasBancariasTab } from './tabs/CuentasBancariasTab'
import { DatosPersonalesTab } from './tabs/DatosPersonalesTab'
import { DeclaracionesTab } from './tabs/DeclaracionesTab'
import { DocumentosTab } from './tabs/DocumentosTab'
import { FamiliaTab } from './tabs/FamiliaTab'
import { SaludOcupacionalTab } from './tabs/SaludOcupacionalTab'
import type { ServidorConRelaciones } from '@/types/api'

interface Props {
  opened: boolean
  onClose: () => void
  servidor: ServidorConRelaciones | null
  onEdit?: (s: ServidorConRelaciones) => void
}

export function ServidorDetail({ opened, onClose, servidor: fila, onEdit }: Props) {
  // La fila del listado es una foto: tras editar, el panel seguía mostrando
  // los datos viejos y la pestaña Condición no se habilitaba hasta cerrarlo y
  // volver a abrirlo. La ficha se consulta por su id; editar la invalida.
  const { data: ficha } = useServidor(fila ? Number(fila.id) : null)
  const servidor = ficha ?? fila

  if (!servidor) return null

  const servidorId = Number(servidor.id)
  const sinCondiciones =
    !servidor.tiene_discapacidad && !servidor.tiene_enfermedad_catastrofica

  return (
    <SgthDrawer
      opened={opened}
      onClose={onClose}
      title="Expediente del servidor"
      description={nombreCompletoDe(servidor)}
      ancho="lg"
    >
      <Stack gap="md">
        <ServidorEncabezado
          servidor={servidor}
          conSituacion
          actions={onEdit && (
            <Button
              size="xs"
              variant="light"
              leftSection={<IconEdit size={12} />}
              onClick={() => onEdit(servidor)}
            >
              Editar
            </Button>
          )}
        />

        <Tabs defaultValue="personal">
          <Tabs.List>
            <Tabs.Tab value="personal" leftSection={<IconUser size={14} />}>
              Personal
            </Tabs.Tab>
            <Tabs.Tab value="academico" leftSection={<IconSchool size={14} />}>
              Académico
            </Tabs.Tab>
            <Tabs.Tab value="familia" leftSection={<IconUsers size={14} />}>
              Familia
            </Tabs.Tab>
            <Tabs.Tab value="cuentas" leftSection={<IconCreditCard size={14} />}>
              Cuentas
            </Tabs.Tab>
            <Tabs.Tab value="documentos" leftSection={<IconPaperclip size={14} />}>
              Documentos
            </Tabs.Tab>
            <Tabs.Tab value="declaraciones" leftSection={<IconFileDescription size={14} />}>
              Declaraciones
            </Tabs.Tab>
            <Tooltip
              label="Active el switch de discapacidad o enfermedad catastrófica en Datos Personales para habilitar esta sección"
              disabled={!sinCondiciones}
              multiline
              w={220}
            >
              <Tabs.Tab
                value="condicion"
                leftSection={<IconHeart size={14} />}
                disabled={sinCondiciones}
              >
                Condición
              </Tabs.Tab>
            </Tooltip>
            <Tabs.Tab value="salud" leftSection={<IconStethoscope size={14} />}>
              Salud
            </Tabs.Tab>
          </Tabs.List>

          <Tabs.Panel value="personal" pt="md">
            <DatosPersonalesTab servidor={servidor} />
          </Tabs.Panel>
          <Tabs.Panel value="academico" pt="md">
            <AcademicoTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="familia" pt="md">
            <FamiliaTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="cuentas" pt="md">
            <CuentasBancariasTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="documentos" pt="md">
            <DocumentosTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="declaraciones" pt="md">
            <DeclaracionesTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="condicion" pt="md">
            <CondicionTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="salud" pt="md">
            <SaludOcupacionalTab servidorId={servidorId} />
          </Tabs.Panel>
        </Tabs>
      </Stack>
    </SgthDrawer>
  )
}
