import { ApiError, apiFetch } from './api'

export type User = {
  id: number
  name: string
  email: string
  qbo_employee_ref?: string | null
  qbo_employee_name?: string | null
}

export async function login(email: string, password: string): Promise<User> {
  const response = await apiFetch<{ user: User }>('/login', {
    method: 'POST',
    body: JSON.stringify({ email, password }),
  })

  return response.user
}

export async function logout(): Promise<void> {
  await apiFetch('/logout', { method: 'POST' })
}

export async function fetchCurrentUser(): Promise<User | null> {
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

export async function updateQboEmployee(
  qboEmployeeRef: string,
  qboEmployeeName?: string,
): Promise<User> {
  const response = await apiFetch<{ user: User }>('/user/qbo-employee', {
    method: 'PATCH',
    body: JSON.stringify({
      qbo_employee_ref: qboEmployeeRef,
      qbo_employee_name: qboEmployeeName ?? null,
    }),
  })

  return response.user
}
