import { render } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { vi } from 'vitest'
import type { Category, Product } from '../api/types'
import { App } from '../App'

export const categories: Category[] = [
  { slug: 'electronics', name: 'electronics' },
  { slug: 'jewelery', name: 'jewelery' },
]

export function makeProduct(id: number, title: string, category: Category = categories[0]): Product {
  return {
    id,
    title,
    description: `Description of ${title}`,
    price: 10 + id,
    category,
    image_url: null,
    rating: { average: 4.5, count: 10 },
  }
}

type Handler = (url: URL, init?: RequestInit) => { status?: number; body: unknown }

/**
 * Stub window.fetch with a tiny fake of our backend. Tests exercise the real
 * API client, so a change to the API contract breaks them.
 */
export function fakeApi(handlers: Record<string, Handler>) {
  const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = new URL(String(input), 'http://localhost')
    const key = `${init?.method ?? 'GET'} ${url.pathname}`
    const handler = handlers[key] ?? (key === 'GET /api/v1/categories' ? () => ({ body: { data: categories } }) : undefined)

    if (!handler) {
      return new Response(JSON.stringify({ message: 'Not found.' }), { status: 404 })
    }

    const { status = 200, body } = handler(url, init)
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
  })

  vi.stubGlobal('fetch', fetchMock)
  return fetchMock
}

export function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <App />
    </MemoryRouter>,
  )
}
