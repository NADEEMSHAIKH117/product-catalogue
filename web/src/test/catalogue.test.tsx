import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import { categories, fakeApi, makeProduct, renderAt } from './fakeApi'

const featured = [1, 2, 3, 4, 5].map((id) => makeProduct(id, `Featured ${id}`))

describe('Home page', () => {
  it('shows the five featured products', async () => {
    fakeApi({ 'GET /api/v1/products/featured': () => ({ body: { data: featured } }) })

    renderAt('/')

    const grid = await screen.findByRole('list', { name: 'Top rated products' })
    expect(within(grid).getAllByRole('link')).toHaveLength(5)
    expect(within(grid).getByText('Featured 3')).toBeInTheDocument()
  })

  it('shows the server message when the catalogue is unavailable', async () => {
    fakeApi({
      'GET /api/v1/products/featured': () => ({
        status: 503,
        body: { message: 'The product catalogue is temporarily unavailable. Please try again shortly.' },
      }),
    })

    renderAt('/')

    expect(await screen.findByRole('alert')).toHaveTextContent('temporarily unavailable')
  })
})

describe('Category page', () => {
  it('shows top rated products and the rest of the category from a category link', async () => {
    fakeApi({
      'GET /api/v1/products/featured': () => ({ body: { data: [] } }),
      'GET /api/v1/categories/jewelery/products': () => ({
        body: {
          data: {
            category: categories[1],
            top_rated: [makeProduct(5, 'Gold Ring', categories[1])],
            others: [makeProduct(8, 'Owl Earrings', categories[1])],
          },
        },
      }),
    })
    renderAt('/')

    await userEvent.click(await screen.findByRole('link', { name: 'jewelery' }))

    expect(within(await screen.findByRole('list', { name: 'Top rated in jewelery' })).getByText('Gold Ring')).toBeInTheDocument()
    expect(within(screen.getByRole('list', { name: 'More in jewelery' })).getByText('Owl Earrings')).toBeInTheDocument()
  })
})

describe('Category navigation', () => {
  it('replaces the products when moving from one category to another', async () => {
    const section = (category: (typeof categories)[number], title: string, id: number) => ({
      body: { data: { category, top_rated: [makeProduct(id, title, category)], others: [] } },
    })
    fakeApi({
      'GET /api/v1/categories/electronics/products': () => section(categories[0], 'Fast SSD', 11),
      'GET /api/v1/categories/jewelery/products': () => section(categories[1], 'Gold Ring', 5),
    })
    renderAt('/category/electronics')
    expect(await screen.findByText('Fast SSD')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('link', { name: 'jewelery' }))

    expect(await screen.findByText('Gold Ring')).toBeInTheDocument()
    expect(screen.queryByText('Fast SSD')).not.toBeInTheDocument()
  })
})

describe('Search', () => {
  it('only shows products the API returns for the entered term', async () => {
    const fetchMock = fakeApi({
      'GET /api/v1/products/featured': () => ({ body: { data: featured } }),
      'GET /api/v1/products': (url) => ({
        body: { data: url.searchParams.get('search') === 'ssd' ? [makeProduct(11, 'Fast SSD')] : [] },
      }),
    })
    renderAt('/')
    await screen.findByText('Featured 1')

    await userEvent.type(screen.getByRole('searchbox', { name: 'Search products' }), '  ssd ')
    await userEvent.click(screen.getByRole('button', { name: 'Search' }))

    const results = await screen.findByRole('list', { name: 'Search results' })
    expect(within(results).getAllByRole('link')).toHaveLength(1)
    expect(within(results).getByText('Fast SSD')).toBeInTheDocument()
    expect(screen.queryByText('Featured 1')).not.toBeInTheDocument()
    // The term is trimmed and URL-encoded before it reaches the backend.
    expect(fetchMock).toHaveBeenCalledWith('/api/v1/products?search=ssd', expect.anything())
  })

  it('says so when nothing matches', async () => {
    fakeApi({ 'GET /api/v1/products': () => ({ body: { data: [] } }) })

    renderAt('/search?q=unicorn')

    expect(await screen.findByText('No products match “unicorn”.')).toBeInTheDocument()
  })

  // The box is keyed by the URL term, so back/forward or leaving search resets it.
  it('keeps the search box in step with the URL when the term changes', async () => {
    fakeApi({ 'GET /api/v1/products': () => ({ body: { data: [] } }) })
    renderAt('/search?q=first')
    const box = screen.getByRole('searchbox')

    await userEvent.clear(box)
    await userEvent.type(box, 'second')
    await userEvent.click(screen.getByRole('button', { name: 'Search' }))
    await screen.findByText('No products match “second”.')

    await userEvent.click(screen.getByRole('link', { name: 'Product Catalogue' }))

    expect(screen.getByRole('searchbox')).toHaveValue('')
  })

  it('does not search for a blank term', async () => {
    const fetchMock = fakeApi({ 'GET /api/v1/products/featured': () => ({ body: { data: featured } }) })
    renderAt('/')
    await screen.findByText('Featured 1')

    await userEvent.type(screen.getByRole('searchbox'), '   ')
    await userEvent.click(screen.getByRole('button', { name: 'Search' }))

    expect(fetchMock).not.toHaveBeenCalledWith(expect.stringContaining('search='), expect.anything())
  })
})

describe('Product page', () => {
  it('shows not found for an invalid product id without calling the API', async () => {
    const fetchMock = fakeApi({})

    renderAt('/products/abc')

    expect(screen.getByRole('heading', { name: 'Page not found' })).toBeInTheDocument()
    expect(fetchMock).not.toHaveBeenCalledWith(expect.stringContaining('/products/'), expect.anything())
  })

  it('opens the product details when a product tile is clicked', async () => {
    fakeApi({
      'GET /api/v1/products/featured': () => ({ body: { data: featured } }),
      'GET /api/v1/products/2': () => ({ body: { data: featured[1] } }),
    })
    renderAt('/')

    await userEvent.click(await screen.findByRole('link', { name: /Featured 2/ }))

    expect(await screen.findByRole('heading', { level: 1, name: 'Featured 2' })).toBeInTheDocument()
    expect(screen.getByText('Description of Featured 2')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Enquire' })).toBeInTheDocument()
  })
})
