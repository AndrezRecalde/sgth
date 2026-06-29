export const ENV = {
  API_URL: process.env.NEXT_PUBLIC_API_URL ?? 'http://sgth.test/api/v1',
  APP_NAME: process.env.NEXT_PUBLIC_APP_NAME ?? 'SGTH',
  APP_VERSION: process.env.NEXT_PUBLIC_APP_VERSION ?? '1.0.0',
} as const;
