/**
 * @file Tests for EllrLogoMark brand chrome.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { createLocaleWrapper } from '../test/renderWithLocale'
import { EllrLogoMark } from './EllrLogoMark'

describe('EllrLogoMark', () => {
  it('renders the wordmark and optional tagline', () => {
    const wrapper = createLocaleWrapper()

    render(<EllrLogoMark showTagline />, { wrapper })

    expect(screen.getByText('ellr')).toBeInTheDocument()
    expect(screen.getByText('Technology + accounting expertise')).toBeInTheDocument()
  })

  it('hides the visible wordmark when showWordmark is false', () => {
    const wrapper = createLocaleWrapper()

    render(<EllrLogoMark showWordmark={false} />, { wrapper })

    expect(screen.getByText('ellr')).toHaveClass('sr-only')
  })

  it('renders the french tagline', () => {
    const wrapper = createLocaleWrapper('fr')

    render(<EllrLogoMark showTagline />, { wrapper })

    expect(screen.getByText('Technologie + expertise comptable')).toBeInTheDocument()
  })
})
