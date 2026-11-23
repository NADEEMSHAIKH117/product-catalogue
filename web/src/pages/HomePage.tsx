import { api } from '../api/client'
import { AsyncContent } from '../components/AsyncContent'
import { ProductGrid } from '../components/ProductGrid'
import { useAsync } from '../hooks/useAsync'

export function HomePage() {
  const featured = useAsync(api.featuredProducts, [])

  return (
    <section>
      <h1>Top rated products</h1>
      <AsyncContent state={featured}>
        {(products) => <ProductGrid products={products} label="Top rated products" />}
      </AsyncContent>
    </section>
  )
}
