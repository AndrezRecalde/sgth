import api from '@/lib/axios'
import type { LoginRequest, LoginResponse, ApiResponse } from '@/types/api'
import type { CambiarPasswordPayload } from '../schemas/cambiarPassword.schema'

export const authService = {
  login: (data: LoginRequest) =>
    api.post<ApiResponse<LoginResponse>>('/auth/login', data).then(r => r.data.datos),
    
  logout: () =>
    api.post<ApiResponse<void>>('/auth/logout').then(r => r.data.datos),

  cambiarPassword: (data: CambiarPasswordPayload) =>
    api.post<ApiResponse<void>>('/auth/cambiar-contrasena', data).then(r => r.data.datos),
}
