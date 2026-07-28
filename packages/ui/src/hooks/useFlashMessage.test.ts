/**
 * @file Tests for flash message state helpers.
 */

import { act } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { renderHookWithLocale } from '../test/renderWithLocale'
import { useFlashMessage } from './useFlashMessage'

describe('useFlashMessage', () => {
  it('stores and clears success, error, and warning messages', () => {
    const { result } = renderHookWithLocale(() => useFlashMessage())

    act(() => {
      result.current.showSuccess('Saved')
    })
    expect(result.current.message).toEqual({ text: 'Saved', type: 'success' })

    act(() => {
      result.current.showError('Failed')
    })
    expect(result.current.message).toEqual({ text: 'Failed', type: 'error' })

    act(() => {
      result.current.showWarning('Check inbox')
    })
    expect(result.current.message).toEqual({ text: 'Check inbox', type: 'warning' })

    act(() => {
      result.current.clearMessage()
    })
    expect(result.current.message).toBeNull()
  })
})
