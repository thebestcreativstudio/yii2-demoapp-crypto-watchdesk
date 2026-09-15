export function fmtMoney(v, digits = 2) {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '—'
  return '$' + Number(v).toLocaleString(undefined, { maximumFractionDigits: digits })
}

export function fmtAmount(v) {
  if (v === null || v === undefined || Number.isNaN(Number(v))) return '—'
  const n = Number(v)
  if (n >= 1000) return n.toLocaleString(undefined, { maximumFractionDigits: 4 })
  if (n >= 1) return n.toLocaleString(undefined, { maximumFractionDigits: 6 })
  return n.toLocaleString(undefined, { maximumFractionDigits: 10 })
}
