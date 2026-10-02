export const ENV = {
  // Relativa a propósito: el API se sirve en el mismo dominio que el frontend
  // (ver `rewrites` en next.config.ts). Una URL absoluta a otro dominio deja
  // fuera la cookie HttpOnly del token, y sin ella no hay sesión.
  API_URL: process.env.NEXT_PUBLIC_API_URL ?? '/api/v1',
  APP_NAME: process.env.NEXT_PUBLIC_APP_NAME ?? 'SGTH',
  APP_VERSION: process.env.NEXT_PUBLIC_APP_VERSION ?? '1.0.0',
} as const;
