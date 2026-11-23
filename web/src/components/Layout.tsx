import { Link, NavLink, Outlet, useSearchParams } from 'react-router-dom'
import { api } from '../api/client'
import { useAsync } from '../hooks/useAsync'
import { SearchBar } from './SearchBar'

export function Layout() {
  const categories = useAsync(api.categories, [])
  const [params] = useSearchParams()

  return (
    <>
      <header className="site-header">
        <div className="container site-header__inner">
          <Link to="/" className="brand">Product Catalogue</Link>
          {/* Keyed by the URL term so the box resets on back/forward navigation. */}
          <SearchBar key={params.get('q') ?? ''} />
        </div>
        <nav className="container category-nav" aria-label="Categories">
          {categories.status === 'success' &&
            categories.data.map((category) => (
              <NavLink key={category.slug} to={`/category/${category.slug}`}>
                {category.name}
              </NavLink>
            ))}
        </nav>
      </header>
      <main className="container">
        <Outlet />
      </main>
    </>
  )
}
