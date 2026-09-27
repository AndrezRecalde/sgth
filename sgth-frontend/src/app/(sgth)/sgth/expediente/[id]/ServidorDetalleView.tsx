'use client'

import { Button, Group, Skeleton, Stack, Tabs } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { useRouter } from 'next/navigation'
import {
  IconBriefcase,
  IconCertificate,
  IconEdit,
  IconFolderOff,
  IconHeart,
  IconPaperclip,
  IconSchool,
  IconStethoscope,
  IconUser,
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
import { CertificadoLaboralModal }
  from '@/features/expediente/components/CertificadoLaboralModal'
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
  const [certificadoOpened, { open: abrirCertificado, close: cerrarCertificado }] =
    useDisclosure(false)

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
          <Group gap="xs" wrap="nowrap">
            {/* Lo emite Talento Humano, nunca el interesado: el endpoint está
                cerrado a admin-uath y asistente-uath. */}
            <Button
              variant="default"
              leftSection={<IconCertificate size={16} />}
              onClick={abrirCertificado}
            >
              Certificado
            </Button>
            <Button
              variant="light"
              leftSection={<IconEdit size={16} />}
              onClick={abrirEditar}
            >
              Editar datos
            </Button>
          </Group>
        }
      />

      <Stack gap="md">
        <ServidorEncabezado servidor={servidor} />

        {/* Seis pestañas, no diez.
            Diez etiquetas no caben en una fila —a 1440 px se partían 9 + 1— y
            eran demasiadas para recorrer. Se agrupan por lo que alguien viene a
            buscar, y cada parte queda como una sección con su propia acción.

            Condición se queda fuera de Personal a propósito: con ella dentro,
            esa pestaña acumulaba cinco secciones, tres de ellas tablas, y es la
            primera que se abre. Además separa lo que el servidor DECLARA de
            quién ES.

            keepMounted={false} sigue puesto, así que agrupar no dispara
            consultas de más: una pestaña con tres secciones pide sus tres
            consultas solo cuando se abre. */}
        <Tabs defaultValue="personal" keepMounted={false}>
          <Tabs.List>
            <Tabs.Tab value="personal" leftSection={<IconUser size={14} />}>
              Personal
            </Tabs.Tab>
            <Tabs.Tab value="laboral" leftSection={<IconBriefcase size={14} />}>
              Laboral
            </Tabs.Tab>
            <Tabs.Tab value="formacion" leftSection={<IconSchool size={14} />}>
              Formación
            </Tabs.Tab>
            <Tabs.Tab value="documentos" leftSection={<IconPaperclip size={14} />}>
              Documentos
            </Tabs.Tab>
            <Tabs.Tab value="condicion" leftSection={<IconHeart size={14} />}>
              Condición
            </Tabs.Tab>
            <Tabs.Tab value="salud" leftSection={<IconStethoscope size={14} />}>
              Salud ocupacional
            </Tabs.Tab>
          </Tabs.List>

          {/* Quién es: identidad, contacto y familia. */}
          <Tabs.Panel value="personal" pt="md">
            <Stack gap="md">
              <DatosPersonalesTab servidor={servidor} />
              <FamiliaTab servidorId={servidorId} />
            </Stack>
          </Tabs.Panel>

          {/* El vínculo, lo que le ha pasado y dónde se le paga. Las cuentas
              bancarias son decisión de nómina —por eso su ruta está cerrada a
              Talento Humano—, no un dato personal. */}
          <Tabs.Panel value="laboral" pt="md">
            <Stack gap="md">
              <LaboralTab servidorId={servidorId} />
              <MovimientosTab
                servidorId={servidorId}
                tipoNombramiento={servidor.contrato_vigente?.tipo_nombramiento}
              />
              <CuentasBancariasTab servidorId={servidorId} />
            </Stack>
          </Tabs.Panel>

          <Tabs.Panel value="formacion" pt="md">
            <AcademicoTab servidorId={servidorId} />
          </Tabs.Panel>

          {/* Las declaraciones juramentadas son documentos anexados con un
              formulario encima: viven con los demás papeles. */}
          <Tabs.Panel value="documentos" pt="md">
            <Stack gap="md">
              <DocumentosTab servidorId={servidorId} />
              <DeclaracionesTab servidorId={servidorId} />
            </Stack>
          </Tabs.Panel>

          <Tabs.Panel value="condicion" pt="md">
            <CondicionTab servidorId={servidorId} />
          </Tabs.Panel>

          {/* Del Dispensario, no del Expediente: lo que el médico certifica,
              frente a lo que el servidor declara en «Condición». */}
          <Tabs.Panel value="salud" pt="md">
            <SaludOcupacionalTab servidor={servidor} />
          </Tabs.Panel>
        </Tabs>
      </Stack>

      <ServidorEditarModal
        opened={editarOpened}
        onClose={cerrarEditar}
        servidor={servidor}
      />

      <CertificadoLaboralModal
        opened={certificadoOpened}
        onClose={cerrarCertificado}
        servidor={servidor}
      />
    </PageShell>
  )
}
