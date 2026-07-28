/**
 * @file Vitest setup: jest-dom matchers for component tests.
 */

import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterEach, vi } from 'vitest'

/**
 * Minimal ResizeObserver stub for Headless UI in jsdom.
 */
class ResizeObserverMock {
  observe() {}
  unobserve() {}
  disconnect() {}
}

vi.stubGlobal('ResizeObserver', ResizeObserverMock)

afterEach(() => {
  cleanup()
})
