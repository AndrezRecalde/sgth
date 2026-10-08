'use client'

import { useState } from 'react'
import { Tabs } from '@mantine/core'
import { IconCertificate, IconClipboardList } from '@tabler/icons-react'
import { PageHeader, PageShell } from '@/components/ui'
import { useAuth } from '@/hooks/useAuth'
import { CertificadosTab } from '@/features/asistencia/components/CertificadosTab'
import { PermisosTab } from '@/features/asistencia/components/PermisosTab'

/*
| Permisos y, para quien los aprueba, los certificados médicos del dispensario.
|
| Los certificados van aquí y no en una pantalla aparte: Trabajo Social solo
| entra al SGTH a Asistencia › Permisos (`ROLES_SGTH_SOLO_PERMISOS`), y la
| viñeta la ven TH y Trabajo Social, los que aprueban (decisión del 2026-10-08).
*/
export function PermisosView() {
  const { hasPermiso } = useAuth()
  const apruebaCertificados = hasPermiso('aprobar-permiso-sirha7') || hasPermiso('validar-trabajo-social')
  const [vineta, setVineta] = useState<string | null>('permisos')

  return (
    <PageShell>
      <PageHeader
        title="Permisos"
        description="Solicitudes de permiso: registro, aprobación y anulación"
      />

      {apruebaCertificados ? (
        <Tabs value={vineta} onChange={setVineta}>
          <Tabs.List mb="md">
            <Tabs.Tab value="permisos" leftSection={<IconClipboardList size={16} />}>
              Permisos
            </Tabs.Tab>
            <Tabs.Tab value="certificados" leftSection={<IconCertificate size={16} />}>
              Certificados médicos
            </Tabs.Tab>
          </Tabs.List>

          <Tabs.Panel value="permisos">
            <PermisosTab />
          </Tabs.Panel>

          {/* Pide la lista solo cuando se abre: no a quien no la mira. */}
          <Tabs.Panel value="certificados">
            <CertificadosTab activa={vineta === 'certificados'} />
          </Tabs.Panel>
        </Tabs>
      ) : (
        <PermisosTab />
      )}
    </PageShell>
  )
}
