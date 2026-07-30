/**
 * @file Serializes async timer sync calls to avoid conflicting PUT requests.
 */

/**
 * Creates a FIFO queue for timer sync tasks.
 * @returns Queue with an enqueue helper.
 */
export function createSyncQueue() {
  let tail: Promise<unknown> = Promise.resolve()

  return {
    /**
     * Runs a task after all prior queued tasks settle.
     * @param task Async work to run in order.
     * @returns The task result.
     */
    enqueue<T>(task: () => Promise<T>): Promise<T> {
      const run = tail.then(task, task)
      tail = run.then(
        () => undefined,
        () => undefined,
      )

      return run
    },
  }
}
