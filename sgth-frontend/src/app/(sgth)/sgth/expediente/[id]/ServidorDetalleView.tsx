'use client'

import { Button, Skeleton, Stack, Tabs } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import {
  IconBriefcase,
  IconCreditCard,
  IconEdit,
  IconFileDescription,
  IconFolderOff,
  IconHeart,
  IconHistory,
  IconPaperclip,
  IconSchool,
  IconStethoscope,
  IconUser,
  IconUsers,
} from '@tabler/icons-react'
import {
  EmptyState,
  PageHeader,
  PageShell,
  StatusBadge,
} from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { useServidor } from '@/features/expediente/hooks/useServidor'
import { ServidorEncabezado, nombreCompletoDe }
  from '@/features/expediente/components/ServidorEncabezado'
import { ServidorEditarModal }
  from '@/features/expediente/components/ServidorEditarModal'
import { AcademicoTab } from '@/features/expediente/components/tabs/AcademicoTab'
import { CondicionTab } from '@/features/expediente/components/tabs/CondicionTab'
import { CuentasBancariasTab } from '@/features/expediente/components/tabs/CuentasBancariasTab'
import { DatosPersonalesTab } from '@/features/expediente/components/tabs/DatosPersonalesTab'
import { DeclaracionesTab } from '@/features/expediente/components/tabs/DeclaracionesTab'
import { DocumentosTab } from '@/features/expediente/components/tabs/DocumentosTab'
import { FamiliaTab } from '@/features/expediente/components/tabs/FamiliaTab'
import { LaboralTab } from '@/features/expediente/components/tabs/LaboralTab'
import { MovimientosTab } from '@/features/expediente/components/tabs/MovimientosTab'
import { SaludOcupacionalTab } from '@/features/expediente/components/tabs/SaludOcupacionalTab'
import { situacionDe } from '@/features/expediente/utils/situacion'

interface Props {
  id: string
}

/**
 * El expediente de un servidor, en su propia página.
 *
 * Antes vivía en dos paneles laterales distintos —la ficha por un lado y sus
 * acciones de personal por otro—, así que la mitad del expediente no se veía
 * sin volver al listado, y nada de esto se podía enlazar ni recargar.
 *
 * La URL lleva el id y no la cédula: la cédula se corrige cuando viene con
 * una errata (es editable por Talento Humano), así que un enlace guardado
 * dejaría de funcionar, y además es un dato personal que quedaría en el
 * historial del navegador y en los registros del servidor.
 */
export function ServidorDetalleView({ id }: Props) {
  const router = useRouter()
  const servidorId = Number(id)
  const { data: servidor, isLoading } = useServidor(servidorId)
  const [editarOpened, { open: abrirEditar, close: cerrarEditar }] = useDisclosure(false)

  if (isLoading) {
    return (
      <PageShell>
        <Skeleton height={60} radius="lg" />
        <Skeleton height={120} radius="lg" />
        <Skeleton height={320} radius="lg" />
      </PageShell>
    )
  }

  if (!servidor) {
    return (
      <PageShell>
        <PageHeader
          title="Expediente del servidor"
          backHref={ROUTES.SGTH.EXPEDIENTE}
        />
        <EmptyState
          icon={IconFolderOff}
          title="Expediente no encontrado"
          description="La ficha no existe o fue dada de baja. Vuelva al listado y ábrala desde allí."
          action={
            <Button variant="light" onClick={() => router.push(ROUTES.SGTH.EXPEDIENTE)}>
              Ir al listado
            </Button>
          }
        />
      </PageShell>
    )
  }

  const situacion = situacionDe(servidor)

  return (
    <PageShell>
      <PageHeader
        title={nombreCompletoDe(servidor)}
        description={`Cédula ${servidor.cedula ?? '—'}`}
        estado={<StatusBadge tone={situacion.tone}>{situacion.texto}</StatusBadge>}
        // Al expediente se llega desde el listado, desde la bandeja de
        // acciones y desde otros módulos: se vuelve a donde se estaba.
        onBack={() => router.back()}
        actions={
          <Button
            variant="light"
            leftSection={<IconEdit size={16} />}
            onClick={abrirEditar}
          >
            Editar datos
          </Button>
        }
      />

      <Stack gap="md">
        <ServidorEncabezado servidor={servidor} />

        <Tabs defaultValue="personal" keepMounted={false}>
          <Tabs.List>
            <Tabs.Tab value="personal" leftSection={<IconUser size={14} />}>
              Personal
            </Tabs.Tab>
            <Tabs.Tab value="laboral" leftSection={<IconBriefcase size={14} />}>
              Laboral
            </Tabs.Tab>
            <Tabs.Tab value="acciones" leftSection={<IconHistory size={14} />}>
              Acciones de personal
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
            <Tabs.Tab value="condicion" leftSection={<IconHeart size={14} />}>
              Condición
            </Tabs.Tab>
            <Tabs.Tab value="salud" leftSection={<IconStethoscope size={14} />}>
              Salud
            </Tabs.Tab>
          </Tabs.List>

          <Tabs.Panel value="personal" pt="md">
            <DatosPersonalesTab servidor={servidor} />
          </Tabs.Panel>
          <Tabs.Panel value="laboral" pt="md">
            <LaboralTab servidorId={servidorId} />
          </Tabs.Panel>
          <Tabs.Panel value="acciones" pt="md">
            <MovimientosTab
              servidorId={servidorId}
              tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
            />
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

      <ServidorEditarModal
        opened={editarOpened}
        onClose={cerrarEditar}
        servidor={servidor}
      />
    </PageShell>
  )
}
