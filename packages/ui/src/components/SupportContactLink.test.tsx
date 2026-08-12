/**
 * @file Tests for the in-app support mailto link.
 */

import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { LocaleProvider } from '../i18n/LocaleProvider'
import { SupportContactLink } from './SupportContactLink'

describe('SupportContactLink', () => {
  it('links to the English support mailbox by default', () => {
    render(
      <LocaleProvider initialLocale="en">
        <SupportContactLink />
      </LocaleProvider>,
    )

    const link = screen.getByRole('link', { name: /contact support/i })
    expect(link).toHaveAttribute(
      'href',
      'mailto:hello@ellr.ca?subject=Ellr%20Timesheet%20support',
    )
    expect(link).toHaveTextContent('hello@ellr.ca')
  })

  it('links to the French support mailbox for fr locale', () => {
    render(
      <LocaleProvider initialLocale="fr">
        <SupportContactLink />
      </LocaleProvider>,
    )

    const link = screen.getByRole('link', { name: /contacter le support/i })
    expect(link).toHaveAttribute(
      'href',
      'mailto:bonjour@ellr.ca?subject=Ellr%20Timesheet%20support',
    )
  })
})
