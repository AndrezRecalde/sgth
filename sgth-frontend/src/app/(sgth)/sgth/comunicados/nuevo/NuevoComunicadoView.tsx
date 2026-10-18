'use client'

import { useRouter } from 'next/navigation'
import { PageHeader, PageShell } from '@/components/ui'
import { ROUTES } from '@/config/routes'
import { erroresAlFormulario } from '@/lib/erroresAlFormulario'
import { ComunicadoForm } from '@/features/comunicados/components/ComunicadoForm'
import { useMutacionesComunicado } from '@/features/comunicados/hooks/useMutacionesComunicado'
import { CAMPOS_COMUNICADO } from '@/features/comunicados/schemas/comunicado.schema'
import { aPayload } from '@/features/comunicados/utils/formulario'

/**
 * Una publicación nueva: se guarda como borrador y se sigue en la pantalla de
 * edición, donde ya se le pueden agregar los documentos, que necesitan que
 * exista.
 */
export function NuevoComunicadoView() {
  const router = useRouter()
  const { crear } = useMutacionesComunicado()

  return (
    <PageShell>
      <PageHeader
        title="Nueva publicación"
        description="Queda como borrador hasta que se publique"
        backHref={ROUTES.SGTH.COMUNICADOS}
      />

      <ComunicadoForm
        enviando={crear.isPending}
        onCancelar={() => router.push(ROUTES.SGTH.COMUNICADOS)}
        onSubmit={(valores, setError) =>
          crear.mutateAsync(aPayload(valores))
            .then((c) => router.push(ROUTES.SGTH.COMUNICADO_EDITAR(c.id)))
            .catch((e) => erroresAlFormulario(e, setError, CAMPOS_COMUNICADO, 'No se pudo guardar el borrador'))
        }
      />
    </PageShell>
  )
}
