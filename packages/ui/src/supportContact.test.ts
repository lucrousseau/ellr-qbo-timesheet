/**
 * @file Tests for support mailbox helpers.
 */

import { describe, expect, it } from 'vitest'
import {
  SUPPORT_EMAIL_EN,
  SUPPORT_EMAIL_FR,
  supportEmailForLocale,
  supportMailtoHref,
} from './supportContact'

describe('supportContact', () => {
  it('resolves locale-specific support emails', () => {
    expect(supportEmailForLocale('en')).toBe(SUPPORT_EMAIL_EN)
    expect(supportEmailForLocale('fr')).toBe(SUPPORT_EMAIL_FR)
  })

  it('builds a mailto href with a default subject', () => {
    expect(supportMailtoHref('en')).toBe(
      'mailto:hello@ellr.ca?subject=Ellr%20Timesheet%20support',
    )
  })
})
