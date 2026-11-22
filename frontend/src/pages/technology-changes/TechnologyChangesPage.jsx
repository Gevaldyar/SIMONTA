import { useCallback, useEffect, useMemo, useState } from 'react'
import api from '../../services/api'

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    refresh: <><path d="M20 7v5h-5" /><path d="M4 17v-5h5" /><path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" /></>,
    search: <><circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" /></>,
    close: <path d="m18 6-12 12M6 6l12 12" />,
    left: <path d="m15 18-6-6 6-6" />,
    right: <path d="m9 18 6-6-6-6" />,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    plus: <path d="M12 5v14M5 12h14" />,
    minus: <path d="M5 12h14" />,
    version: <><path d="M4 7h16M4 12h10M4 17h13" /><circle cx="18" cy="17" r="2" /></>,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></>,
    eye: <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" /><circle cx="12" cy="12" r="3" /></>,
  }

  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      {paths[name]}
    </svg>
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit', month: 'short', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  }).format(date)
}

function unwrapObject(payload) {
  let value = payload?.data ?? payload
  if (value && !Array.isArray(value) && typeof value === 'object' && value.data && !Array.isArray(value.data)) {
    value = value.data
  }
  return value && typeof value === 'object' ? value : {}
}

function unwrapCollection(payload) {
  const root = payload?.data ?? payload
  if (Array.isArray(root)) return root
  if (Array.isArray(root?.data)) return root.data
  if (Array.isArray(root?.data?.data)) return root.data.data
  return []
}

function getPaginator(payload) {
  // Laravel LengthAwarePaginator biasanya mengirim data + metadata
  // pada object yang sama. Bentuk wrapped response juga tetap didukung.
  const root = payload && typeof payload === 'object' ? payload : {}
  const page = (
    root.data && !Array.isArray(root.data) && Array.isArray(root.data.data)
  ) ? root.data : root

  const meta = page.meta || root.meta || {}
  const items = Array.isArray(page.data)
    ? page.data
    : Array.isArray(page)
      ? page
      : []

  return {
    items,
    currentPage: Number(page.current_page ?? meta.current_page ?? root.current_page ?? 1),
    lastPage: Number(page.last_page ?? meta.last_page ?? root.last_page ?? 1),
    total: Number(page.total ?? meta.total ?? root.total ?? items.length),
  }
}

function changeLabel(type) {
  return ({
    added: 'Technology Added',
    removed: 'Technology Removed',
    version_changed: 'Version Changed',
  })[type] || String(type || 'Unknown').replaceAll('_', ' ')
}

function ChangeBadge({ type }) {
  const styles = {
    added: 'border-[#82918B]/60 bg-[#82918B]/10 text-[#50665C]',
    removed: 'border-[#963746]/40 bg-[#963746]/5 text-[#963746]',
    version_changed: 'border-[#B69A69]/70 bg-[#B69A69]/10 text-[#816D43]',
  }
  const icon = type === 'added' ? 'plus' : type === 'removed' ? 'minus' : 'version'

  return (
    <span className={`inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-[9px] font-bold tracking-wider ${styles[type] || 'border-[#D8CFBF] text-[#665F56]'}`}>
      <Icon name={icon} size={12} />
      {changeLabel(type).toUpperCase()}
    </span>
  )
}

function MetricCard({ index, label, value, description, icon, loading }) {
  return (
    <article className="simonta-tech-metric relative min-h-[145px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]">
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">{index}</span>
      <div className="relative flex items-center justify-between">
        <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">{label}</p>
        <div className="grid size-9 place-items-center border border-[#D8CFBF] text-[#963746]"><Icon name={icon} /></div>
      </div>
      <h2 className="relative mt-5 font-serif text-4xl text-[#191A20]">{loading ? '—' : formatNumber(value)}</h2>
      <p className="relative mt-2 text-[10px] text-[#817A70]">{description}</p>
    </article>
  )
}

function EvidencePanel({ title, value }) {
  const entries = Array.isArray(value) ? value : value ? [value] : []

  return (
    <div>
      <p className="text-[9px] font-bold tracking-wider text-[#817A70]">{title}</p>
      {entries.length === 0 ? (
        <p className="mt-2 text-xs text-[#948B7D]">Tidak ada evidence yang tersimpan.</p>
      ) : (
        <div className="mt-2 space-y-2">
          {entries.map((entry, index) => (
            <pre key={index} className="overflow-x-auto whitespace-pre-wrap break-all border border-[#D8CFBF] bg-[#252B37] p-3 text-[10px] leading-5 text-[#EEE9DD]">{typeof entry === 'string' ? entry : JSON.stringify(entry, null, 2)}</pre>
          ))}
        </div>
      )}
    </div>
  )
}

function TechnologyChangesPage() {
  const [changes, setChanges] = useState([])
  const [summary, setSummary] = useState({ total_changes: 0, added: 0, removed: 0, version_changed: 0, affected_websites: 0 })
  const [opds, setOpds] = useState([])
  const [websites, setWebsites] = useState([])

  const [filters, setFilters] = useState({ opd_id: '', website_id: '', change_type: '', date_from: '', date_to: '' })

  // Hanya kirim parameter filter yang benar-benar diisi pengguna.
  // Jangan kirim string kosong karena endpoint summary memvalidasinya sebagai input.
  const activeFilterParams = useMemo(() => {
    return Object.fromEntries(
      Object.entries(filters).filter(([, value]) =>
        value !== '' && value !== null && value !== undefined
      )
    )
  }, [filters])

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [optionsError, setOptionsError] = useState('')
  const [summaryError, setSummaryError] = useState('')
  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedChange, setSelectedChange] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const queryParams = useMemo(() => {
    const params = { per_page: 15, page: currentPage }
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '') params[key] = value
    })
    return params
  }, [filters, currentPage])

  const loadOptions = useCallback(async () => {
    setOptionsError('')
    try {
      const [opdResponse, websiteResponse] = await Promise.all([
        api.get('/api/opds', { params: { per_page: 100 } }),
        api.get('/api/websites', { params: { per_page: 100 } }),
      ])
      setOpds(unwrapCollection(opdResponse.data))
      setWebsites(unwrapCollection(websiteResponse.data))
    } catch (requestError) {
      console.error('Technology changes filter options error:', requestError)
      setOptionsError('Daftar OPD atau website gagal dimuat. Riwayat perubahan tetap dapat dilihat, tetapi pilihan filter mungkin tidak lengkap.')
    }
  }, [])

  const loadChanges = useCallback(async (silent = false) => {
    if (silent) setRefreshing(true)
    else setLoading(true)
    setError('')

    const [listResult, summaryResult] = await Promise.allSettled([
      api.get('/api/technology-changes', { params: queryParams }),
      api.get('/api/technology-changes/summary', { params: activeFilterParams }),
    ])

    if (listResult.status === 'fulfilled') {
      const result = getPaginator(listResult.value.data)
      setChanges(result.items)
      setCurrentPage(result.currentPage)
      setLastPage(result.lastPage)
      setTotal(result.total)
    } else {
      console.error('Technology changes list error:', listResult.reason)
      setChanges([])
      setTotal(0)
      setError(listResult.reason.response?.data?.message || 'Riwayat perubahan teknologi tidak dapat dimuat.')
    }

    if (summaryResult.status === 'fulfilled') {
      setSummaryError('')
      const result = unwrapObject(summaryResult.value.data)
      setSummary({
        total_changes: Number(result.total_changes || 0),
        added: Number(result.added || 0),
        removed: Number(result.removed || 0),
        version_changed: Number(result.version_changed || 0),
        affected_websites: Number(result.affected_websites || 0),
      })
    } else {
      console.error('Technology changes summary error:', summaryResult.reason)
      setSummaryError('Ringkasan perubahan tidak dapat dimuat. Angka statistik sementara tidak tersedia.')
      setSummary({ total_changes: 0, added: 0, removed: 0, version_changed: 0, affected_websites: 0 })
    }

    setLoading(false)
    setRefreshing(false)
  }, [queryParams, activeFilterParams])

  useEffect(() => {
    loadOptions()
  }, [loadOptions])

  useEffect(() => {
    loadChanges()
  }, [loadChanges])

  const visibleWebsites = useMemo(() => {
    if (!filters.opd_id) return websites
    return websites.filter((website) => String(website.opd_id ?? website.opd?.id ?? '') === String(filters.opd_id))
  }, [websites, filters.opd_id])

  const updateFilter = (key, value) => {
    setCurrentPage(1)
    setFilters((previous) => ({
      ...previous,
      [key]: value,
      ...(key === 'opd_id' ? { website_id: '' } : {}),
    }))
  }

  const resetFilters = () => {
    setCurrentPage(1)
    setFilters({ opd_id: '', website_id: '', change_type: '', date_from: '', date_to: '' })
  }

  const openDetail = async (change) => {
    setDetailOpen(true)
    setSelectedChange(null)
    setDetailLoading(true)
    try {
      const response = await api.get(`/api/technology-changes/${change.id}`)
      setSelectedChange(response.data?.data || response.data || null)
    } catch (requestError) {
      console.error('Technology change detail error:', requestError)
      setError(requestError.response?.data?.message || 'Detail perubahan teknologi tidak dapat dimuat.')
      setDetailOpen(false)
    } finally {
      setDetailLoading(false)
    }
  }

  const closeDetail = () => {
    setDetailOpen(false)
    setSelectedChange(null)
  }

  const metrics = [
    { index: '01', label: 'TOTAL CHANGES', value: summary.total_changes, description: 'Jumlah perubahan sesuai filter', icon: 'clock' },
    { index: '02', label: 'TECHNOLOGY ADDED', value: summary.added, description: 'Teknologi baru terdeteksi', icon: 'plus' },
    { index: '03', label: 'TECHNOLOGY REMOVED', value: summary.removed, description: 'Teknologi tidak lagi terdeteksi', icon: 'minus' },
    { index: '04', label: 'VERSION CHANGED', value: summary.version_changed, description: 'Perubahan versi yang teridentifikasi', icon: 'version' },
  ]

  return (
    <div className="simonta-page-enter simonta-technology-page simonta-technology-changes space-y-8">
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">06</div>
        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />
            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">SIMONTA / CHANGE INTELLIGENCE</p>
          </div>
          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">Technology <span className="italic text-[#963746]">Changes.</span></h1>
              <p className="mt-4 max-w-2xl text-sm leading-7 text-[#77736C]">Riwayat perubahan teknologi pada website OPD berdasarkan perbandingan hasil pemindaian yang berhasil diselesaikan.</p>
            </div>
            <button type="button" onClick={() => loadChanges(true)} disabled={refreshing || loading} className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto">
              <Icon name="refresh" size={15} className={refreshing ? 'animate-spin' : ''} /> REFRESH CHANGES
            </button>
          </div>
          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">SCAN COMPARISON</span>
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">TECHNOLOGY BASELINE</span>
            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">CHANGE API CONNECTED</span>
          </div>
        </div>
      </section>

      {error && <div role="alert" className="flex items-center gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs text-[#963746]"><span className="font-serif text-lg">!</span>{error}</div>}
      {summaryError && <div role="status" className="border border-[#B69A69]/50 bg-[#B69A69]/10 p-3 text-[10px] leading-5 text-[#665F56]">{summaryError}</div>}
      {optionsError && <div role="status" className="border border-[#B69A69]/50 bg-[#B69A69]/10 p-3 text-[10px] leading-5 text-[#665F56]">{optionsError}</div>}

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map((metric) => <MetricCard key={metric.index} {...metric} loading={loading} />)}
      </section>

      <section className="simonta-tech-mini-metrics grid gap-3 sm:grid-cols-2">
        <article className="flex items-center justify-between gap-4 border border-[#D8CFBF] bg-[#F7F4EC] p-5">
          <div><p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">AFFECTED WEBSITES</p><p className="mt-2 text-[10px] text-[#817A70]">Website dengan catatan perubahan</p></div>
          <p className="font-serif text-3xl text-[#963746]">{loading ? '—' : formatNumber(summary.affected_websites)}</p>
        </article>
        <article className="flex items-center justify-between gap-4 border border-[#D8CFBF] bg-[#F7F4EC] p-5">
          <div><p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">CURRENT RESULT SET</p><p className="mt-2 text-[10px] text-[#817A70]">Total catatan pada hasil filter</p></div>
          <p className="font-serif text-3xl text-[#963746]">{loading ? '—' : formatNumber(total)}</p>
        </article>
      </section>

      <section className="simonta-tech-panel simonta-tech-panel--changes border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="border-b border-[#D8CFBF] p-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">CHANGE DATABASE / FILTERS</p>
          <div className="mt-2 flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
            <div><h2 className="font-serif text-2xl text-[#191A20]">Change Register</h2><p className="mt-1 text-[10px] text-[#817A70]">Telusuri perubahan teknologi berdasarkan OPD, website, tipe, dan tanggal.</p></div>
            <button type="button" onClick={resetFilters} className="self-start pb-1 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]">RESET FILTERS</button>
          </div>

          <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
              <label htmlFor="change-opd" className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">OPD</label>
              <select id="change-opd" value={filters.opd_id} onChange={(event) => updateFilter('opd_id', event.target.value)} className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]">
                <option value="">All OPDs</option>
                {opds.map((opd) => <option key={opd.id} value={opd.id}>{opd.name || opd.nama || `OPD #${opd.id}`}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="change-website" className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">WEBSITE</label>
              <select id="change-website" value={filters.website_id} onChange={(event) => updateFilter('website_id', event.target.value)} className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]">
                <option value="">All websites</option>
                {visibleWebsites.map((website) => <option key={website.id} value={website.id}>{website.name || website.url || `Website #${website.id}`}</option>)}
              </select>
            </div>
            <div>
              <label htmlFor="change-type" className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">CHANGE TYPE</label>
              <select id="change-type" value={filters.change_type} onChange={(event) => updateFilter('change_type', event.target.value)} className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]">
                <option value="">All changes</option><option value="added">Added</option><option value="removed">Removed</option><option value="version_changed">Version changed</option>
              </select>
            </div>
            <div>
              <label htmlFor="change-date-from" className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">DATE FROM</label>
              <input id="change-date-from" type="date" max={filters.date_to || undefined} value={filters.date_from} onChange={(event) => updateFilter('date_from', event.target.value)} className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]" />
            </div>
            <div>
              <label htmlFor="change-date-to" className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">DATE TO</label>
              <input id="change-date-to" type="date" min={filters.date_from || undefined} value={filters.date_to} onChange={(event) => updateFilter('date_to', event.target.value)} className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]" />
            </div>
          </div>
        </div>

        <div className="hidden overflow-x-auto md:block">
          <table className="simonta-tech-table simonta-tech-table--changes w-full min-w-[1000px] border-collapse text-left">
            <thead><tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
              <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">TECHNOLOGY</th>
              <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">CHANGE</th>
              <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">OPD / WEBSITE</th>
              <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">VERSION</th>
              <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">DETECTED AT</th>
              <th className="px-5 py-4 text-right text-[9px] font-bold tracking-wider text-[#817A70]">DETAIL</th>
            </tr></thead>
            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? [1, 2, 3].map((item) => <tr key={item}><td colSpan="6" className="px-5 py-6"><div className="simonta-skeleton h-4 w-2/3" /></td></tr>) : changes.length === 0 ? (
                <tr><td colSpan="6" className="px-5 py-14 text-center"><div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]"><Icon name="search" size={23} /></div><h3 className="mt-4 font-serif text-xl text-[#191A20]">No Changes Found</h3><p className="mt-2 text-xs text-[#817A70]">Belum ada catatan perubahan untuk filter yang dipilih.</p></td></tr>
              ) : changes.map((change) => {
                const website = change.scan?.website
                const opdName = website?.opd?.name || website?.opd?.nama || '—'
                const websiteName = website?.name || website?.url || 'Website tidak tersedia'
                return <tr key={change.id} className="group transition hover:bg-[#F1EBDD]">
                  <td className="px-5 py-5"><p className="text-sm font-semibold text-[#252B37]">{change.technology_name || change.technology?.name || 'Unknown technology'}</p><p className="mt-1 text-[10px] text-[#948B7D]">{change.technology_slug || change.technology?.slug || '—'}</p></td>
                  <td className="px-5 py-5"><ChangeBadge type={change.change_type} /></td>
                  <td className="px-5 py-5"><p className="text-xs font-semibold text-[#252B37]">{opdName}</p><p className="mt-1 max-w-[220px] truncate text-[10px] text-[#817A70]" title={website?.url || websiteName}>{websiteName}</p></td>
                  <td className="px-5 py-5"><p className="text-[10px] text-[#817A70]">{change.previous_version || '—'} <span className="px-1 text-[#B69A69]">→</span> <span className="font-semibold text-[#252B37]">{change.current_version || '—'}</span></p></td>
                  <td className="px-5 py-5 text-xs text-[#665F56]">{formatDate(change.created_at)}</td>
                  <td className="px-5 py-5 text-right"><button type="button" onClick={() => openDetail(change)} className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 hover:text-[#963746]"><Icon name="eye" size={14} /> EXPLORE</button></td>
                </tr>
              })}
            </tbody>
          </table>
        </div>

        <div className="simonta-tech-mobile-list divide-y divide-[#D8CFBF] md:hidden">
          {loading ? [1, 2, 3].map((item) => <div key={item} className="p-5"><div className="simonta-skeleton h-4 w-2/3" /><div className="simonta-skeleton mt-3 h-3 w-1/2" /></div>) : changes.length === 0 ? <div className="p-8 text-center text-xs text-[#817A70]">Belum ada perubahan teknologi yang ditemukan.</div> : changes.map((change) => {
            const website = change.scan?.website
            return <article key={change.id} className="simonta-tech-mobile-card space-y-3 p-5">
              <div className="flex flex-wrap items-start justify-between gap-2"><div><h3 className="text-sm font-semibold text-[#252B37]">{change.technology_name || change.technology?.name || 'Unknown technology'}</h3><p className="mt-1 text-[10px] text-[#817A70]">{website?.opd?.name || '—'} / {website?.name || website?.url || 'Website tidak tersedia'}</p></div><ChangeBadge type={change.change_type} /></div>
              <div className="grid grid-cols-2 gap-3 border-y border-[#D8CFBF] py-3"><div><p className="text-[8px] tracking-wider text-[#817A70]">PREVIOUS VERSION</p><p className="mt-1 text-xs text-[#252B37]">{change.previous_version || '—'}</p></div><div><p className="text-[8px] tracking-wider text-[#817A70]">CURRENT VERSION</p><p className="mt-1 text-xs font-semibold text-[#252B37]">{change.current_version || '—'}</p></div></div>
              <p className="text-[10px] text-[#817A70]">{formatDate(change.created_at)}</p>
              <button type="button" onClick={() => openDetail(change)} className="simonta-angular-action inline-flex w-full items-center justify-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-wider text-[#665F56]"><Icon name="eye" size={14} /> EXPLORE CHANGE</button>
            </article>
          })}
        </div>

        {!loading && total > 0 && <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center"><p className="text-[10px] text-[#817A70]">Showing page {currentPage} of {lastPage} · {formatNumber(total)} changes</p><div className="flex items-center gap-2"><button type="button" onClick={() => setCurrentPage((page) => Math.max(1, page - 1))} disabled={currentPage <= 1} aria-label="Previous page" className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"><Icon name="left" /></button><span className="min-w-8 text-center font-serif text-sm text-[#252B37]">{currentPage}</span><button type="button" onClick={() => setCurrentPage((page) => Math.min(lastPage, page + 1))} disabled={currentPage >= lastPage} aria-label="Next page" className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"><Icon name="right" /></button></div></div>}
      </section>

      {detailOpen && <div className="simonta-backdrop-enter fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#191A20]/65 p-4 backdrop-blur-[2px]" onMouseDown={(event) => { if (event.target === event.currentTarget) closeDetail() }}>
        <section role="dialog" aria-modal="true" aria-labelledby="technology-change-detail-title" className="simonta-card-enter simonta-tech-dialog my-auto max-h-[90vh] w-full max-w-3xl overflow-y-auto border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]">
          <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7"><div><p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">CHANGE INTELLIGENCE / RECORD DETAIL</p><h2 id="technology-change-detail-title" className="mt-2 font-serif text-3xl text-[#191A20]">Technology Change.</h2><p className="mt-2 text-[10px] text-[#817A70]">Snapshot perubahan dari dua hasil pemindaian.</p></div><button type="button" onClick={closeDetail} aria-label="Close change detail" className="simonta-icon-action grid size-9 shrink-0 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#963746] hover:text-[#963746]"><Icon name="close" /></button></div>
          {detailLoading || !selectedChange ? <div className="space-y-4 p-7"><div className="simonta-skeleton h-6 w-1/2" /><div className="simonta-skeleton h-4 w-full" /><div className="simonta-skeleton h-4 w-2/3" /></div> : <div className="space-y-6 p-5 sm:p-7">
            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><h3 className="font-serif text-3xl text-[#191A20]">{selectedChange.technology_name || selectedChange.technology?.name || 'Unknown technology'}</h3><p className="mt-1 text-xs text-[#817A70]">{selectedChange.technology_slug || selectedChange.technology?.slug || '—'}</p><div className="mt-3"><ChangeBadge type={selectedChange.change_type} /></div></div><div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4 sm:min-w-[170px]"><p className="text-[8px] font-bold tracking-wider text-[#817A70]">RECORDED AT</p><p className="mt-2 text-xs text-[#252B37]">{formatDate(selectedChange.created_at)}</p></div></div>
            <div className="simonta-tech-detail-grid grid gap-4 border-y border-[#D8CFBF] py-4 sm:grid-cols-2"><div><p className="text-[9px] font-bold tracking-wider text-[#817A70]">OPD</p><p className="mt-2 text-sm text-[#252B37]">{selectedChange.scan?.website?.opd?.name || '—'}</p></div><div><p className="text-[9px] font-bold tracking-wider text-[#817A70]">WEBSITE</p><p className="mt-2 break-all text-sm text-[#252B37]">{selectedChange.scan?.website?.name || selectedChange.scan?.website?.url || '—'}</p><p className="mt-1 break-all text-[10px] text-[#817A70]">{selectedChange.scan?.website?.url || ''}</p></div><div><p className="text-[9px] font-bold tracking-wider text-[#817A70]">PREVIOUS SCAN ID</p><p className="mt-2 text-sm text-[#252B37]">{selectedChange.previous_scan_id || selectedChange.previous_scan?.id || '—'}</p></div><div><p className="text-[9px] font-bold tracking-wider text-[#817A70]">CURRENT SCAN ID</p><p className="mt-2 text-sm text-[#252B37]">{selectedChange.scan_id || selectedChange.scan?.id || '—'}</p></div></div>
            <div><h3 className="font-serif text-xl text-[#191A20]">Version Comparison</h3><div className="simonta-tech-version-grid mt-3 grid gap-3 sm:grid-cols-2"><div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4"><p className="text-[9px] font-bold tracking-wider text-[#817A70]">PREVIOUS VERSION</p><p className="mt-3 font-serif text-2xl text-[#665F56]">{selectedChange.previous_version || 'Not detected'}</p><p className="mt-3 text-[9px] text-[#817A70]">Confidence: {selectedChange.previous_confidence ?? '—'}{selectedChange.previous_confidence != null ? '%' : ''}</p></div><div className="border border-[#B69A69]/60 bg-[#F7F4EC] p-4"><p className="text-[9px] font-bold tracking-wider text-[#817A70]">CURRENT VERSION</p><p className="mt-3 font-serif text-2xl text-[#963746]">{selectedChange.current_version || 'Not detected'}</p><p className="mt-3 text-[9px] text-[#817A70]">Confidence: {selectedChange.current_confidence ?? '—'}{selectedChange.current_confidence != null ? '%' : ''}</p></div></div></div>
            <div className="grid gap-5 sm:grid-cols-2"><EvidencePanel title="PREVIOUS DETECTION EVIDENCE" value={selectedChange.previous_evidence} /><EvidencePanel title="CURRENT DETECTION EVIDENCE" value={selectedChange.current_evidence} /></div>
          </div>}
          <div className="flex justify-end border-t border-[#D8CFBF] p-5 sm:px-7"><button type="button" onClick={closeDetail} className="simonta-angular-action border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10">CLOSE DETAIL</button></div>
        </section>
      </div>}
    </div>
  )
}

export default TechnologyChangesPage
