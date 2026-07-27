import { ApiError, apiFetch, ensureCsrfCookie } from './api'

export type User = {
  id: number
  name: string
  email: string
}

export async function login(email: string, password: string): Promise<User> {
  await ensureCsrfCookie()

  const response = await apiFetch<{ user: User }>('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })

  return response.user
}

export async function logout(): Promise<void> {
  await ensureCsrfCookie()
  await apiFetch('/logout', { method: 'POST' })
}

export async function fetchCurrentUser(): Promise<User | null> {
  await ensureCsrfCookie()

  try {
    const response = await apiFetch<{ user: User }>('/user')
    return response.user
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null
    }
    throw error
  }
}
