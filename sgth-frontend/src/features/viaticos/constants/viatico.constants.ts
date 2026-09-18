import type { SemanticTone } from "@/config/design.tokens";

export const ZONA_OPTIONS = [
  { value: "dentro_provincia", label: "Dentro de la provincia" },
  { value: "fuera_provincia", label: "Fuera de la provincia" },
  { value: "exterior", label: "Exterior (internacional)" },
];

export const MODALIDAD_OPTIONS = [
  { value: "total", label: "Con anticipo del 70 %" },
  { value: "sin_anticipo", label: "Sin anticipo" },
];

export const TIPO_VIAJE_OPTIONS = [
  { value: "capacitacion", label: "Capacitación" },
  { value: "reunion_oficial", label: "Reunión oficial" },
  { value: "taller_foro_seminario", label: "Taller / Foro / Seminario" },
  { value: "feria_evento_especial", label: "Feria o evento especial" },
  { value: "visita_protocolar", label: "Visita protocolar" },
  { value: "firma_acuerdo", label: "Firma de acuerdo" },
  { value: "visita_tecnica", label: "Visita técnica" },
  { value: "cooperacion_internacional", label: "Cooperación internacional" },
  { value: "asistencia_humanitaria", label: "Asistencia humanitaria" },
];

// Una sola lista para la solicitud, la aprobación y los tramos: antes había
// dos, con países distintos, y la aprobación pedía el país en texto libre.
export const PAISES_OPTIONS = [
  "Alemania", "Argentina", "Australia", "Austria", "Bélgica", "Bolivia",
  "Brasil", "Canadá", "Chile", "China", "Colombia", "Corea del Sur",
  "Costa Rica", "Cuba", "Ecuador", "El Salvador", "España", "Estados Unidos",
  "Francia", "Guatemala", "Honduras", "India", "Israel", "Italia", "Japón",
  "México", "Nicaragua", "Países Bajos", "Panamá", "Paraguay", "Perú",
  "Reino Unido", "República Dominicana", "Suiza", "Uruguay", "Venezuela",
  "Otro",
].map((p) => ({ value: p, label: p }));

export const ZONA_LABELS: Record<string, string> = {
  dentro_provincia: "Dentro de la provincia",
  fuera_provincia: "Fuera de la provincia",
  exterior: "Exterior",
};

export const TONO_VIATICO: Record<string, SemanticTone> = {
  solicitado: "warning",
  aprobado: "info",
  con_anticipo: "info",
  en_comision: "info",
  pendiente_liquidacion: "warning",
  liquidado: "success",
  contabilizado: "neutral",
  cancelado: "danger",
  rechazado: "danger",
};

export const ESTADO_LABELS: Record<string, string> = {
  solicitado: "Solicitado",
  aprobado: "Aprobado",
  con_anticipo: "Con anticipo",
  en_comision: "En comisión",
  pendiente_liquidacion: "Pendiente de liquidación",
  liquidado: "Liquidado",
  contabilizado: "Contabilizado",
  cancelado: "Cancelado",
  rechazado: "Rechazado",
};

export const MODALIDAD_LABELS: Record<string, string> = {
  // El anticipo es siempre el 70 % del monto (Gestión Financiera).
  total: "Con anticipo del 70 %",
  sin_anticipo: "Sin anticipo",
};
