/**
 * @file Tests for PasswordPolicyRequirements rendering.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { PasswordPolicyRequirements } from './PasswordPolicyRequirements'

describe('PasswordPolicyRequirements', () => {
  it('renders localized password requirement hints', () => {
    render(<PasswordPolicyRequirements />, { wrapper: createLocaleWrapper('en') })

    expect(screen.getByText(/password requirements/i)).toBeInTheDocument()
    expect(screen.getByRole('list')).toBeInTheDocument()
  })
})
