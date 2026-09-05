"use client";

import {
  Stack,
  Textarea,
  Select,
  Group,
  Button,
  Text,
  Badge,
  Card,
  Tooltip,
  Alert,
} from "@mantine/core";
import { useForm, Controller, useWatch } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  IconCheck, IconEdit, IconX, IconCloud, IconCloudOff, IconHistory,
} from "@tabler/icons-react";
import { useState, useEffect, useRef } from "react";
import { useContainedInput } from "@/hooks/useContainedInput";
import { useBorradorConsulta } from "../hooks/useBorradorConsulta";
import {
  useRegistrarConsulta,
  useActualizarConsulta,
} from "../hooks/useConsultaMedica";
import { BuscarCie10Input } from "./BuscarCie10Input";
import { CorreccionesConsulta } from "./CorreccionesConsulta";
import { RichTextInput } from "./RichTextInput";
import { useAuthStore } from "@/store/auth.store";
import {
  consultaMedicaSchema,
  type ConsultaMedicaFormData,
  TIPO_ATENCION_OPTIONS,
  TIPO_DIAGNOSTICO_OPTIONS,
} from "../schemas/consultaMedica.schema";
import type { AgendaMedica } from "../services/agendaService";
import type { ConsultaMedica } from "../services/consultaMedicaService";
import type { DiagnosticoCie10 } from "../services/cie10Service";

interface Props {
  turno: AgendaMedica;
  historiaClinicaId: number;
  consultaPrevia?: ConsultaMedica | null;
  onGuardada: (consulta: ConsultaMedica) => void;
}

function formatFechaLocal(d: Date): string {
  return [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, "0"),
    String(d.getDate()).padStart(2, "0"),
  ].join("-");
}

/**
 * Si hay algo que merezca guardarse, o el formulario sigue como nació.
 *
 * Sin esto se guardaba un borrador en cuanto se abría la consulta, y al volver
 * a entrar la pantalla anunciaba que «recuperó lo que estaba escrito» sin que
 * nadie hubiera escrito nada: un aviso que miente es peor que no avisar.
 *
 * El motivo de consulta no cuenta por sí solo: viene rellenado con lo que pidió
 * el paciente al agendar, no con lo que el médico teclea.
 */
function hayAlgoEscrito(
  valores: Partial<ConsultaMedicaFormData>,
  motivoInicial: string,
  cie10Principal: DiagnosticoCie10 | null,
  cie10Secundarios: DiagnosticoCie10[],
): boolean {
  const conTexto = [
    valores.enfermedad_actual,
    valores.examen_fisico,
    valores.diagnostico_detallado,
    valores.plan_tratamiento,
    valores.notas_medico,
  ].some((v) => !!limpiarHtml(v));

  return (
    conTexto ||
    !!cie10Principal ||
    cie10Secundarios.length > 0 ||
    (valores.motivo_consulta ?? "") !== motivoInicial
  );
}

/** El texto de un campo del editor, sin las etiquetas que deja vacío. */
function limpiarHtml(valor?: string | null): string {
  return (valor ?? "").replace(/<[^>]*>/g, "").trim();
}

/** «hace un momento», «hace 12 minutos», o la hora si ya es de antes. */
function formatCuando(iso: string): string {
  const minutos = Math.floor((Date.now() - new Date(iso).getTime()) / 60_000);

  if (minutos < 1) return "hace un momento";
  if (minutos < 60) return `hace ${minutos} minuto${minutos === 1 ? "" : "s"}`;

  return `el ${new Date(iso).toLocaleString("es-EC", {
    day: "2-digit",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  })}`;
}

/**
 * El mismo plazo que aplica el servidor (`ConsultaMedicaController`). Aquí solo
 * decide si se ofrece el botón: quien manda es el backend, que rechaza igual
 * aunque alguien llame a la API a mano.
 */
const HORAS_PARA_CORREGIR = 24;

/**
 * Por qué no se puede corregir, o null si sí se puede.
 *
 * La nota la firma quien atendió, y solo mientras es reciente. Pasado el plazo
 * corregir dejaría de ser corregir: lo que corresponde es una consulta nueva.
 */
function motivoParaNoEditar(
  consulta: ConsultaMedica,
  usuarioId?: number,
): string | null {
  if (usuarioId !== undefined && consulta.medico_id !== usuarioId) {
    return "Solo quien atendió la consulta puede corregirla.";
  }

  if (consulta.created_at) {
    const horas =
      (Date.now() - new Date(consulta.created_at).getTime()) / 3_600_000;

    if (horas >= HORAS_PARA_CORREGIR) {
      return "Ya pasaron más de 24 horas: para añadir algo, registre una consulta nueva.";
    }
  }

  return null;
}

function SeccionVista({
  label,
  valor,
}: {
  label: string;
  valor?: string | null;
}) {
  if (!valor) return null;
  // El HTML llega saneado del servidor: se limpia al guardar con lista blanca
  // de las etiquetas que produce el editor, y lo que ya estaba guardado se
  // saneó en una migración. Ver App\Support\HtmlClinico.
  const esHtml = valor.startsWith("<");
  return (
    <Stack
      gap={4}
      py="sm"
      style={{ borderTop: "0.5px solid var(--mantine-color-gray-2)" }}
    >
      <Text
        size="xs"
        fw={500}
        c="dimmed"
        tt="uppercase"
        style={{ letterSpacing: "0.05em" }}
      >
        {label}
      </Text>
      {esHtml ? (
        <div
          style={{
            fontSize: "var(--mantine-font-size-sm)",
            lineHeight: 1.6,
            color: "var(--mantine-color-text)",
          }}
          dangerouslySetInnerHTML={{ __html: valor }}
        />
      ) : (
        <Text size="sm" style={{ lineHeight: 1.6 }}>
          {valor}
        </Text>
      )}
    </Stack>
  );
}

export function TabConsulta({
  turno,
  historiaClinicaId,
  consultaPrevia,
  onGuardada,
}: Props) {
  const contained = useContainedInput();
  const registrar = useRegistrarConsulta();
  const actualizar = useActualizarConsulta();
  const { usuario } = useAuthStore();
  const [modoEdicion, setModoEdicion] = useState(!consultaPrevia);

  // Al cargarse una consulta previa —o al cambiar de consulta— el formulario
  // vuelve al modo lectura. Se ajusta durante el render, no en un efecto:
  // hacerlo en el efecto sacaba del modo edición en cada refresco de la
  // consulta, perdiendo lo que el médico estuviera escribiendo.
  const semillaConsulta = consultaPrevia?.id ?? null;
  const [semillaAplicada, setSemillaAplicada] =
    useState<number | null>(semillaConsulta);

  if (semillaConsulta !== semillaAplicada) {
    setSemillaAplicada(semillaConsulta);
    setModoEdicion(!semillaConsulta);
  }
  const [cie10Principal, setCie10Principal] = useState<DiagnosticoCie10 | null>(
    null,
  );
  const [cie10Secundarios, setCie10Secundarios] = useState<DiagnosticoCie10[]>(
    [],
  );

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ConsultaMedicaFormData>({
    resolver: zodResolver(consultaMedicaSchema),
    defaultValues: {
      tipo_atencion: "primera_vez",
      tipo_diagnostico: "presuntivo",
      motivo_consulta: turno.motivo_solicitud ?? "",
      enfermedad_actual: "",
      examen_fisico: "",
      diagnostico_detallado: "",
      plan_tratamiento: "",
      notas_medico: "",
    },
  });

  // El borrador solo tiene sentido en una consulta que aún no existe: una ya
  // guardada se corrige, y esa corrección tiene su propio rastro versionado.
  const esConsultaNueva = !consultaPrevia;
  const motivoInicial = turno.motivo_solicitud ?? "";
  const borradorCtl = useBorradorConsulta(turno.id, esConsultaNueva);
  const [recuperado, setRecuperado] = useState(false);
  const yaReseteado = useRef(false);

  const contenidoBorrador = esConsultaNueva
    ? (borradorCtl.borrador?.contenido as
        | (ConsultaMedicaFormData & {
            cie10_principal?: DiagnosticoCie10 | null;
            cie10_secundarios?: DiagnosticoCie10[];
          })
        | undefined)
    : undefined;

  // Se recupera una sola vez, al llegar el borrador: volver a aplicarlo en cada
  // refresco pisaría con una copia vieja lo que se está escribiendo. El ajuste
  // se hace durante el render y no en un efecto, igual que el del modo edición
  // de más arriba: React lo vuelve a pintar antes de que nada llegue a verse.
  const idBorrador = borradorCtl.borrador?.id ?? null;
  const [borradorAplicado, setBorradorAplicado] =
    useState<number | null>(null);

  if (idBorrador !== null && idBorrador !== borradorAplicado) {
    setBorradorAplicado(idBorrador);

    // Un borrador puede no llevar nada dentro: no se anuncia como recuperación
    // de algo que nadie escribió.
    if (
      contenidoBorrador &&
      hayAlgoEscrito(
        contenidoBorrador,
        motivoInicial,
        contenidoBorrador.cie10_principal ?? null,
        contenidoBorrador.cie10_secundarios ?? [],
      )
    ) {
      setCie10Principal(contenidoBorrador.cie10_principal ?? null);
      setCie10Secundarios(contenidoBorrador.cie10_secundarios ?? []);
      setRecuperado(true);
    }
  }

  // Los campos del formulario sí van por efecto: `reset` toca el estado interno
  // de react-hook-form y no se puede llamar mientras se renderiza.
  useEffect(() => {
    if (!recuperado || yaReseteado.current || !contenidoBorrador) return;

    yaReseteado.current = true;
    const { cie10_principal, cie10_secundarios, ...campos } = contenidoBorrador;
    void cie10_principal;
    void cie10_secundarios;
    reset(campos);
  }, [recuperado, contenidoBorrador, reset]);

  // Lo que hay escrito ahora mismo, para el guardado automático.
  const valores = useWatch({ control });
  const huella = JSON.stringify([valores, cie10Principal, cie10Secundarios]);

  useEffect(() => {
    if (!esConsultaNueva) return;
    // Hasta que no se recupere lo guardado no se anota nada: si no, el
    // formulario vacío del primer render pisaría el borrador que iba a llegar.
    if (borradorCtl.cargando) return;
    if (!hayAlgoEscrito(
      valores, motivoInicial, cie10Principal, cie10Secundarios
    )) {
      return;
    }

    borradorCtl.anotar({
      ...valores,
      cie10_principal: cie10Principal,
      cie10_secundarios: cie10Secundarios,
    });
    // `huella` resume el contenido: `valores` es un objeto nuevo en cada render
    // y dispararía el efecto sin que nada hubiera cambiado.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [huella, esConsultaNueva, borradorCtl.cargando]);

  useEffect(() => {
    if (consultaPrevia) {
      reset({
        tipo_atencion:
          (consultaPrevia.tipo_atencion as ConsultaMedicaFormData["tipo_atencion"]) ??
          "primera_vez",
        tipo_diagnostico:
          (consultaPrevia.tipo_diagnostico as ConsultaMedicaFormData["tipo_diagnostico"]) ??
          "presuntivo",
        motivo_consulta: consultaPrevia.motivo_consulta ?? "",
        enfermedad_actual: consultaPrevia.enfermedad_actual ?? "",
        examen_fisico: consultaPrevia.examen_fisico ?? "",
        diagnostico_detallado: consultaPrevia.diagnostico_detallado ?? "",
        plan_tratamiento: consultaPrevia.plan_tratamiento ?? "",
        notas_medico: consultaPrevia.notas_medico ?? "",
      });
    }
  }, [consultaPrevia, reset]);

  const onSubmit = (values: ConsultaMedicaFormData) => {
    if (consultaPrevia) {
      actualizar.mutate(
        {
          id: consultaPrevia.id,
          data: {
            tipo_atencion: values.tipo_atencion,
            tipo_diagnostico: values.tipo_diagnostico,
            motivo_consulta: values.motivo_consulta,
            enfermedad_actual: values.enfermedad_actual || null,
            examen_fisico: values.examen_fisico || null,
            diagnostico_cie10_id: cie10Principal?.id ?? null,
            diagnosticos_secundarios: cie10Secundarios.map((d) => d.id),
            diagnostico_detallado: values.diagnostico_detallado,
            plan_tratamiento: values.plan_tratamiento || null,
            notas_medico: values.notas_medico || null,
          },
        },
        { onSuccess: () => setModoEdicion(false) },
      );
    } else {
      const ahora = new Date();
      registrar.mutate(
        {
          historia_clinica_id: historiaClinicaId,
          agenda_medica_id: turno.id,
          fecha_consulta: formatFechaLocal(ahora),
          hora_consulta: ahora.toTimeString().slice(0, 5),
          tipo_atencion: values.tipo_atencion,
          tipo_diagnostico: values.tipo_diagnostico,
          motivo_consulta: values.motivo_consulta,
          enfermedad_actual: values.enfermedad_actual || null,
          examen_fisico: values.examen_fisico || null,
          diagnostico_cie10_id: cie10Principal?.id ?? null,
          diagnosticos_secundarios: cie10Secundarios.map((d) => d.id),
          diagnostico_detallado: values.diagnostico_detallado,
          plan_tratamiento: values.plan_tratamiento || null,
          notas_medico: values.notas_medico || null,
        },
        {
          onSuccess: (consulta) => {
            // El servidor ya retiró el borrador al registrar la consulta; esto
            // corta el guardado que pudiera quedar en vuelo y limpia la caché.
            borradorCtl.olvidar();
            onGuardada(consulta);
          },
        },
      );
    }
  };

  const noEditable = consultaPrevia
    ? motivoParaNoEditar(consultaPrevia, usuario?.id)
    : null;

  if (!modoEdicion && consultaPrevia) {
    return (
      <Card withBorder radius="lg" p={0}>
        <Group
          justify="space-between"
          align="center"
          px="md"
          py="sm"
          style={{ borderBottom: "0.5px solid var(--mantine-color-gray-2)" }}
        >
          <Group gap="xs" wrap="wrap">
            <Badge size="sm" variant="light" color="emerald">
              Guardada
            </Badge>
            <Badge size="sm" variant="light" color="blue">
              {consultaPrevia.tipo_atencion?.replace(/_/g, " ")}
            </Badge>
            <Badge
              size="sm"
              variant="light"
              color={
                consultaPrevia.tipo_diagnostico === "definitivo"
                  ? "emerald"
                  : "orange"
              }
            >
              {consultaPrevia.tipo_diagnostico}
            </Badge>
          </Group>
          <Tooltip
            label={noEditable ?? "Editar consulta"}
            withArrow
            multiline
            w={noEditable ? 240 : undefined}
          >
            {/* El `span` sostiene el tooltip cuando el botón está deshabilitado:
                un botón inerte no emite los eventos que lo abren, y sin el
                motivo a la vista el bloqueo parecería un fallo. */}
            <span>
              <Button
                size="compact-xs"
                variant="subtle"
                color="blue"
                leftSection={<IconEdit size={13} />}
                disabled={!!noEditable}
                onClick={() => setModoEdicion(true)}
              >
                Editar
              </Button>
            </span>
          </Tooltip>
        </Group>

        <CorreccionesConsulta consultaId={consultaPrevia.id} />

        <Stack gap={0} px="md" pb="md">
          <SeccionVista
            label="Motivo de consulta"
            valor={consultaPrevia.motivo_consulta}
          />
          <SeccionVista
            label="Enfermedad actual / Anamnesis"
            valor={consultaPrevia.enfermedad_actual}
          />
          <SeccionVista
            label="Examen físico"
            valor={consultaPrevia.examen_fisico}
          />

          {consultaPrevia.diagnostico_cie10_principal && (
            <Stack
              gap={8}
              py="sm"
              px="sm"
              my="xs"
              style={{
                borderTop: "0.5px solid var(--mantine-color-gray-2)",
                backgroundColor: "var(--mantine-color-blue-light)",
                borderRadius: 8,
              }}
            >
              <Text
                size="xs"
                fw={500}
                c="blue"
                tt="uppercase"
                style={{ letterSpacing: "0.05em" }}
              >
                Diagnóstico
              </Text>
              <Group gap="xs" align="flex-start">
                <Badge size="md" color="blue" variant="light">
                  {consultaPrevia.diagnostico_cie10_principal.codigo}
                </Badge>
                <Text size="sm" fw={500} style={{ flex: 1, lineHeight: 1.5 }}>
                  {consultaPrevia.diagnostico_cie10_principal.descripcion}
                </Text>
              </Group>

              {(consultaPrevia.diagnosticos_secundarios?.length ?? 0) > 0 && (
                <Stack gap={4}>
                  {consultaPrevia.diagnosticos_secundarios?.map((ds) => (
                    <Group key={ds.id} gap="xs" align="flex-start">
                      <Badge size="sm" variant="default">
                        {ds.diagnostico?.codigo}
                      </Badge>
                      <Text size="xs" c="dimmed" style={{ flex: 1 }}>
                        {ds.diagnostico?.descripcion}
                      </Text>
                    </Group>
                  ))}
                </Stack>
              )}
            </Stack>
          )}

          <SeccionVista
            label="Diagnóstico detallado"
            valor={consultaPrevia.diagnostico_detallado}
          />
          <SeccionVista
            label="Plan de tratamiento"
            valor={consultaPrevia.plan_tratamiento}
          />
          <SeccionVista
            label="Notas del médico"
            valor={consultaPrevia.notas_medico}
          />
        </Stack>
      </Card>
    );
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <Stack gap="sm" p="md">
        {/* Recuperar en silencio sería peor que no recuperar: el médico tiene
            que saber que lo que ve en pantalla es de antes, y poder tirarlo. */}
        {recuperado && (
          <Alert
            icon={<IconHistory size={15} />}
            color="blue"
            variant="light"
            p="xs"
          >
            <Group justify="space-between" wrap="nowrap" gap="xs">
              <Text size="xs">
                Se recuperó lo que estaba escrito
                {borradorCtl.borrador?.updated_at && (
                  <> (guardado {formatCuando(borradorCtl.borrador.updated_at)})</>
                )}
                . Todavía no es parte de la historia clínica.
              </Text>
              <Button
                size="compact-xs"
                variant="subtle"
                color="gray"
                onClick={() => {
                  reset({
                    tipo_atencion: "primera_vez",
                    tipo_diagnostico: "presuntivo",
                    motivo_consulta: turno.motivo_solicitud ?? "",
                    enfermedad_actual: "",
                    examen_fisico: "",
                    diagnostico_detallado: "",
                    plan_tratamiento: "",
                    notas_medico: "",
                  });
                  setCie10Principal(null);
                  setCie10Secundarios([]);
                  setRecuperado(false);
                  void borradorCtl.descartar();
                }}
              >
                Descartar
              </Button>
            </Group>
          </Alert>
        )}

        {esConsultaNueva && borradorCtl.estado !== "inactivo" && (
          <Group gap={6} justify="flex-end">
            {borradorCtl.estado === "error" ? (
              <>
                <IconCloudOff size={13} color="var(--mantine-color-orange-6)" />
                <Text size="xs" c="orange">
                  No se pudo guardar el borrador. Lo escrito sigue en pantalla.
                </Text>
              </>
            ) : (
              <>
                <IconCloud size={13} color="var(--mantine-color-gray-6)" />
                <Text size="xs" c="dimmed">
                  {borradorCtl.estado === "guardando"
                    ? "Guardando borrador…"
                    : `Borrador guardado ${borradorCtl.guardadoEn
                        ? "a las " +
                          borradorCtl.guardadoEn.toLocaleTimeString("es-EC", {
                            hour: "2-digit",
                            minute: "2-digit",
                          })
                        : ""}`}
                </Text>
              </>
            )}
          </Group>
        )}

        {consultaPrevia && (
          <Group justify="flex-end">
            <Button
              size="xs"
              variant="subtle"
              color="gray"
              leftSection={<IconX size={13} />}
              onClick={() => setModoEdicion(false)}
            >
              Cancelar edición
            </Button>
          </Group>
        )}

        <Group grow align="flex-start">
          <Controller
            name="tipo_atencion"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de atención"
                data={TIPO_ATENCION_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? "primera_vez")}
                error={errors.tipo_atencion?.message}
              />
            )}
          />
          <Controller
            name="tipo_diagnostico"
            control={control}
            render={({ field }) => (
              <Select
                label="Tipo de diagnóstico"
                data={TIPO_DIAGNOSTICO_OPTIONS}
                {...contained}
                value={field.value}
                onChange={(v) => field.onChange(v ?? "presuntivo")}
                error={errors.tipo_diagnostico?.message}
              />
            )}
          />
        </Group>

        <Textarea
          label="Motivo de consulta"
          placeholder="¿Por qué acude hoy el paciente?"
          autosize
          minRows={2}
          {...contained}
          {...register("motivo_consulta")}
          error={errors.motivo_consulta?.message}
        />

        <Textarea
          label="Enfermedad actual / Anamnesis (opcional)"
          placeholder="Inicio, duración, características..."
          autosize
          minRows={4}
          {...contained}
          {...register("enfermedad_actual")}
        />

        <Textarea
          label="Examen físico (opcional)"
          placeholder="Hallazgos relevantes del examen físico"
          autosize
          minRows={4}
          {...contained}
          {...register("examen_fisico")}
        />

        <Stack gap="xs">
          <BuscarCie10Input
            value={cie10Principal}
            onChange={setCie10Principal}
          />
          <Text size="xs" c="dimmed">
            Diagnóstico principal (CIE-10)
          </Text>
        </Stack>

        <Stack gap="xs">
          <Text size="sm" fw={500}>
            Diagnósticos secundarios{" "}
            <Text span size="xs" c="dimmed">
              (opcional, máx. 3)
            </Text>
          </Text>
          {cie10Secundarios.length < 3 && (
            <BuscarCie10Input
              value={null}
              onChange={(d) => {
                if (d && !cie10Secundarios.find((s) => s.id === d.id)) {
                  setCie10Secundarios((prev) => [...prev, d]);
                }
              }}
            />
          )}
          {cie10Secundarios.map((d) => (
            <Group key={d.id} gap={6}>
              <Text size="xs" ff="monospace" c="dimmed">
                {d.codigo}
              </Text>
              <Text size="xs">{d.descripcion}</Text>
              <Button
                size="compact-xs"
                variant="subtle"
                color="red"
                onClick={() =>
                  setCie10Secundarios((prev) =>
                    prev.filter((s) => s.id !== d.id),
                  )
                }
              >
                ×
              </Button>
            </Group>
          ))}
        </Stack>

        <Controller
          name="diagnostico_detallado"
          control={control}
          render={({ field }) => (
            <RichTextInput
              label="Diagnóstico detallado"
              required
              value={field.value ?? ""}
              onChange={field.onChange}
              error={errors.diagnostico_detallado?.message}
            />
          )}
        />

        <Controller
          name="plan_tratamiento"
          control={control}
          render={({ field }) => (
            <RichTextInput
              label="Plan de tratamiento"
              description="Opcional"
              value={field.value ?? ""}
              onChange={field.onChange}
            />
          )}
        />

        <Group justify="flex-end" pt="sm">
          <Button
            type="submit"
            color="emerald"
            leftSection={<IconCheck size={14} />}
            loading={registrar.isPending || actualizar.isPending}
          >
            {consultaPrevia ? "Guardar cambios" : "Guardar consulta"}
          </Button>
        </Group>
      </Stack>
    </form>
  );
}
