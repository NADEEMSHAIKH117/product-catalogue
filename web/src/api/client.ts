import type { Category, CategoryProducts, EnquiryInput, FieldErrors, Product } from './types'

// The only place the client knows where the API lives. Relative, so it works
// behind the Vite proxy locally and behind CloudFront path routing in production.
const BASE_URL = '/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: FieldErrors

  constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      ...init,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...init?.headers },
    })
  } catch {
    throw new ApiError(0, 'Could not reach the server. Check your connection and try again.')
  }

  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(
      response.status,
      body?.message ?? 'Something went wrong. Please try again.',
      body?.errors ?? {},
    )
  }

  return body as T
}

type Envelope<T> = { data: T }

export const api = {
  featuredProducts: () => request<Envelope<Product[]>>('/products/featured').then((r) => r.data),

  categories: () => request<Envelope<Category[]>>('/categories').then((r) => r.data),

  categoryProducts: (slug: string) =>
    request<Envelope<CategoryProducts>>(`/categories/${encodeURIComponent(slug)}/products`).then((r) => r.data),

  searchProducts: (term: string) =>
    request<Envelope<Product[]>>(`/products?search=${encodeURIComponent(term)}`).then((r) => r.data),

  product: (id: number) => request<Envelope<Product>>(`/products/${id}`).then((r) => r.data),

  submitEnquiry: (input: EnquiryInput) =>
    request<{ message: string }>('/enquiries', { method: 'POST', body: JSON.stringify(input) }),
}
