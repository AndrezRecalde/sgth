import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { notificar } from '@/components/ui'
import { getApiErrorMessage } from '@/types/api'
import { clavesComunicacion } from '../constants/claves'
import { FOTOS_POR_TANDA } from '../constants/resultadosCarga'
import { fotografiasService } from '../services/fotografiasService'
import type { ArchivoCarga, InformeCarga } from '../services/tipos'

const resumir = (archivos: ArchivoCarga[]): InformeCarga => {
  const seCargan = archivos.filter((a) => a.se_carga).length

  return { resumen: { total: archivos.length, se_cargan: seCargan, se_omiten: archivos.length - seCargan }, archivos }
}

/**
 * La carga masiva de fotos (fase 1.2): elegir los archivos de la carpeta,
 * revisarlos por el nombre sin subir ninguno, y subir en tandas solo los que
 * se van a cargar. Una tanda que falla entera (la red, el servidor) no detiene
 * las demás: sus fotos salen como no cargadas, con el motivo.
 */
export function useCargaMasiva() {
  const qc = useQueryClient()
  const [archivos, setArchivos] = useState<File[]>([])
  const [rechazados, setRechazados] = useState<string[]>([])
  const [reemplazar, setReemplazarEstado] = useState(false)
  const [avance, setAvance] = useState<{ hechas: number; total: number } | null>(null)
  const [resultado, setResultado] = useState<InformeCarga | null>(null)

  const simulacion = useMutation({
    mutationFn: () => fotografiasService.simularCarga(archivos.map((a) => a.name), reemplazar),
    onError: notificar.alFallar('No se pudo revisar el lote de fotos'),
  })

  /** Cambiar los archivos o la opción deja vieja la revisión. */
  const invalidarRevision = () => simulacion.reset()

  const agregar = (nuevos: File[], nombresRechazados: string[]) => {
    setArchivos((actuales) => {
      const yaEstan = new Set(actuales.map((a) => a.name))
      return [...actuales, ...nuevos.filter((a) => !yaEstan.has(a.name))]
    })
    setRechazados((actuales) => [...actuales, ...nombresRechazados])
    invalidarRevision()
  }

  const setReemplazar = (valor: boolean) => {
    setReemplazarEstado(valor)
    invalidarRevision()
  }

  const reiniciar = () => {
    setArchivos([])
    setRechazados([])
    setReemplazarEstado(false)
    setAvance(null)
    setResultado(null)
    simulacion.reset()
  }

  const cargar = async () => {
    const revision = simulacion.data
    if (!revision) return

    // La revisión llega en el mismo orden en que se mandaron los nombres.
    const omitidos = revision.archivos.filter((a) => !a.se_carga)
    const aSubir = archivos.filter((_, i) => revision.archivos[i]?.se_carga)
    const tandas = Array.from({ length: Math.ceil(aSubir.length / FOTOS_POR_TANDA) },
      (_, i) => aSubir.slice(i * FOTOS_POR_TANDA, (i + 1) * FOTOS_POR_TANDA))

    const cargados: ArchivoCarga[] = []
    setAvance({ hechas: 0, total: aSubir.length })

    for (const tanda of tandas) {
      try {
        const informe = await fotografiasService.cargarTanda(tanda, reemplazar)
        cargados.push(...informe.archivos)
      } catch (error) {
        const motivo = getApiErrorMessage(error)
        cargados.push(...tanda.map((foto): ArchivoCarga => ({
          archivo: foto.name, cedula: null, servidor: null, resultado: 'ilegible',
          resultado_etiqueta: 'No se pudo subir', se_carga: false, detalle: motivo,
        })))
      }
      setAvance({ hechas: cargados.length, total: aSubir.length })
    }

    const final = resumir([...cargados, ...omitidos])
    setResultado(final)
    setAvance(null)
    qc.invalidateQueries({ queryKey: clavesComunicacion.fotografias.todas })

    const titulo = `${final.resumen.se_cargan} ${final.resumen.se_cargan === 1 ? 'foto cargada' : 'fotos cargadas'}`
    const fallidas = cargados.filter((a) => !a.se_carga).length
    if (fallidas > 0) {
      notificar.aviso(titulo, `${fallidas} no se pudieron usar: revise el informe.`)
    } else {
      notificar.exito(titulo, 'Ya son las fotos vigentes de cada servidor.')
    }
  }

  return {
    archivos, rechazados, reemplazar, setReemplazar, agregar, reiniciar,
    revision: simulacion.data ?? null,
    revisar: () => simulacion.mutate(),
    revisando: simulacion.isPending,
    cargar, avance, resultado,
  }
}
