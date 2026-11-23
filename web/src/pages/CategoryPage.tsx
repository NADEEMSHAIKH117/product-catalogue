import { useParams } from 'react-router-dom'
import { api } from '../api/client'
import { AsyncContent } from '../components/AsyncContent'
import { ProductGrid } from '../components/ProductGrid'
import { useAsync } from '../hooks/useAsync'

export function CategoryPage() {
  const { slug = '' } = useParams()
  const state = useAsync(() => api.categoryProducts(slug), [slug])

  return (
    <AsyncContent state={state}>
      {({ category, top_rated, others }) => (
        <>
          <h1 className="capitalize">{category.name}</h1>
          <section>
            <h2>Top rated</h2>
            <ProductGrid products={top_rated} label={`Top rated in ${category.name}`} />
          </section>
          {others.length > 0 && (
            <section>
              <h2>More in {category.name}</h2>
              <ProductGrid products={others} label={`More in ${category.name}`} />
            </section>
          )}
        </>
      )}
    </AsyncContent>
  )
}
