import { useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { AsyncContent } from '../components/AsyncContent'
import { ProductGrid } from '../components/ProductGrid'
import { useAsync } from '../hooks/useAsync'

export function SearchPage() {
  const [params] = useSearchParams()
  const term = (params.get('q') ?? '').trim()

  if (term === '') {
    return <p className="status">Enter a search term to find products.</p>
  }

  return <SearchResults term={term} />
}

function SearchResults({ term }: { term: string }) {
  const results = useAsync(() => api.searchProducts(term), [term])

  return (
    <section>
      <h1>Results for “{term}”</h1>
      <AsyncContent state={results}>
        {(products) =>
          products.length === 0 ? (
            <p className="status">No products match “{term}”.</p>
          ) : (
            <ProductGrid products={products} label="Search results" />
          )
        }
      </AsyncContent>
    </section>
  )
}
