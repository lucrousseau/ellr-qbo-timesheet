/**
 * @file Tests for timer sync queue serialization.
 */

import { describe, expect, it, vi } from 'vitest'
import { createSyncQueue } from './timerSyncQueue'

describe('createSyncQueue', () => {
  it('runs tasks in order without overlapping', async () => {
    const queue = createSyncQueue()
    const order: string[] = []

    const first = queue.enqueue(async () => {
      await new Promise((resolve) => setTimeout(resolve, 20))
      order.push('first')
    })
    const second = queue.enqueue(async () => {
      order.push('second')
    })

    await Promise.all([first, second])

    expect(order).toEqual(['first', 'second'])
  })

  it('continues the queue after a rejected task', async () => {
    const queue = createSyncQueue()
    const task = vi.fn().mockResolvedValue('ok')

    await queue.enqueue(async () => {
      throw new Error('sync failed')
    }).catch(() => undefined)

    await expect(queue.enqueue(task)).resolves.toBe('ok')
    expect(task).toHaveBeenCalledOnce()
  })
})
