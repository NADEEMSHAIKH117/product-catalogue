import { useEffect, useState } from 'react'

export type AsyncState<T> =
  | { status: 'loading' }
  | { status: 'error'; error: Error }
  | { status: 'success'; data: T }

/**
 * Run an async loader whenever `deps` change. Results from a superseded run
 * (e.g. the user searched again before the first search returned) are ignored.
 */
export function useAsync<T>(load: () => Promise<T>, deps: unknown[]): AsyncState<T> {
  const [state, setState] = useState<AsyncState<T>>({ status: 'loading' })

  useEffect(() => {
    let current = true
    setState({ status: 'loading' })

    load().then(
      (data) => current && setState({ status: 'success', data }),
      (error: Error) => current && setState({ status: 'error', error }),
    )

    return () => {
      current = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- callers pass the loader's inputs as deps
  }, deps)

  return state
}
