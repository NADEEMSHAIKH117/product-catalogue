import { useState, type FormEvent } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'

export function SearchBar() {
  const [params] = useSearchParams()
  const [term, setTerm] = useState(params.get('q') ?? '')
  const navigate = useNavigate()

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const trimmed = term.trim()
    if (trimmed === '') return
    // The term lives in the URL so results can be shared and the back button works.
    navigate(`/search?q=${encodeURIComponent(trimmed)}`)
  }

  return (
    <form role="search" className="search-bar" onSubmit={handleSubmit}>
      <label htmlFor="search-term" className="visually-hidden">Search products</label>
      <input
        id="search-term"
        type="search"
        placeholder="Search products"
        value={term}
        maxLength={100}
        onChange={(event) => setTerm(event.target.value)}
      />
      <button type="submit">Search</button>
    </form>
  )
}
