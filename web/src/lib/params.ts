/** Parse a route param as a positive integer product ID, or null if it isn't one. */
export function parseProductId(raw: string | undefined): number | null {
  if (!raw || !/^[1-9][0-9]{0,8}$/.test(raw)) return null
  return Number(raw)
}
