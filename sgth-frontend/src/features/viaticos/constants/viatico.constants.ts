export const ZONA_OPTIONS = [
  { value: "dentro_provincia", label: "Dentro de la provincia" },
  { value: "fuera_provincia", label: "Fuera de la provincia" },
  { value: "exterior", label: "Exterior (internacional)" },
];

export const MODALIDAD_OPTIONS = [
  { value: "total", label: "Anticipo (70% del monto calculado)" },
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

export const PAISES_OPTIONS = [
  "Colombia",
  "Perú",
  "Bolivia",
  "Chile",
  "Argentina",
  "Brasil",
  "Venezuela",
  "México",
  "España",
  "Estados Unidos",
  "Canadá",
  "Francia",
  "Alemania",
  "Italia",
  "China",
  "Japón",
  "Otro",
].map((p) => ({ value: p, label: p }));

export const ZONA_LABELS: Record<string, string> = {
  dentro_provincia: "Dentro de la provincia",
  fuera_provincia: "Fuera de la provincia",
  exterior: "Exterior",
};

export const ESTADO_COLORS: Record<string, string> = {
  solicitado: "orange",
  aprobado: "blue",
  con_anticipo: "cyan",
  en_comision: "violet",
  pendiente_liquidacion: "yellow",
  liquidado: "emerald",
  contabilizado: "gray",
  cancelado: "red",
  rechazado: "orange",
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

export const PASO_STEPPER: Record<string, number> = {
  solicitado: 0,
  aprobado: 1,
  con_anticipo: 2,
  en_comision: 3,
  pendiente_liquidacion: 4,
  liquidado: 5,
  contabilizado: 6,
  cancelado: 0,
  rechazado: 0,
};

export const MODALIDAD_LABELS: Record<string, string> = {
  total: "Anticipo total",
  sin_anticipo: "Sin anticipo",
};
