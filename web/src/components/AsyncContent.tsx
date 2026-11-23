import type { ReactNode } from 'react'
import type { AsyncState } from '../hooks/useAsync'

/** Renders the loading and error states consistently; children render the data. */
export function AsyncContent<T>({ state, children }: { state: AsyncState<T>; children: (data: T) => ReactNode }) {
  if (state.status === 'loading') {
    return <p className="status" role="status">Loading…</p>
  }

  if (state.status === 'error') {
    return <p className="status status--error" role="alert">{state.error.message}</p>
  }

  return <>{children(state.data)}</>
}
