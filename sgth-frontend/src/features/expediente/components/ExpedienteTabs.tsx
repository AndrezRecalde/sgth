'use client'

import { Stack, Tabs } from '@mantine/core'
import {
  IconBriefcase,
  IconHeart,
  IconPaperclip,
  IconSchool,
  IconStethoscope,
  IconUser,
} from '@tabler/icons-react'
import type { ServidorConRelaciones } from '@/types/api'
import { AcademicoTab } from './tabs/AcademicoTab'
import { CondicionTab } from './tabs/CondicionTab'
import { CuentasBancariasTab } from './tabs/CuentasBancariasTab'
import { DatosPersonalesTab } from './tabs/DatosPersonalesTab'
import { DeclaracionesTab } from './tabs/DeclaracionesTab'
import { DocumentosTab } from './tabs/DocumentosTab'
import { FamiliaTab } from './tabs/FamiliaTab'
import { LaboralTab } from './tabs/LaboralTab'
import { MovimientosTab } from './tabs/MovimientosTab'
import { SaludOcupacionalTab } from './tabs/SaludOcupacionalTab'

/**
 * Las pestañas del expediente de un servidor. Se separaron de la página para
 * que esta no pasara de 200 líneas (regla 02).
 */
export function ExpedienteTabs({ servidor }: { servidor: ServidorConRelaciones }) {
  const servidorId = Number(servidor.id)

  /*
   * Seis pestañas, no diez.
   * Diez etiquetas no caben en una fila —a 1440 px se partían 9 + 1— y
   * eran demasiadas para recorrer. Se agrupan por lo que alguien viene a
   * buscar, y cada parte queda como una sección con su propia acción.
   *
   * Condición se queda fuera de Personal a propósito: con ella dentro,
   * esa pestaña acumulaba cinco secciones, tres de ellas tablas, y es la
   * primera que se abre. Además separa lo que el servidor DECLARA de
   * quién ES.
   *
   * keepMounted={false} sigue puesto, así que agrupar no dispara
   * consultas de más: una pestaña con tres secciones pide sus tres
   * consultas solo cuando se abre.
   */
  return (
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
  )
}
