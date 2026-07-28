/**
 * @file Tests for alert banner component.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { expectMessageClasses } from '@ellr/test-utils'
import { Alert } from './Alert'

describe('Alert', () => {
  it('renders error, success, and warning variants', () => {
    const { rerender } = render(<Alert variant="error">Error text</Alert>)
    expectMessageClasses(screen.getByText('Error text'), 'error')

    rerender(<Alert variant="success">Saved</Alert>)
    expectMessageClasses(screen.getByText('Saved'), 'success')

    rerender(
      <Alert variant="warning" className="mt-2">
        Warning
      </Alert>,
    )
    const warning = screen.getByText('Warning')
    expectMessageClasses(warning, 'warning')
    expect(warning.className).toContain('mt-2')
  })
})
