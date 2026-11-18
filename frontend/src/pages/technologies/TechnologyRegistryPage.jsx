import { useCallback, useEffect, useState } from 'react'

import api from '../../services/api'

const CATEGORIES = [
  { value: 'backend_language', label: 'Backend Language' },
  { value: 'backend_framework', label: 'Backend Framework' },
  { value: 'cms', label: 'Content Management System' },
  { value: 'frontend_framework', label: 'Frontend Framework' },
  { value: 'javascript_library', label: 'JavaScript Library' },
  { value: 'css_framework', label: 'CSS Framework' },
  { value: 'web_server', label: 'Web Server' },
  { value: 'cdn_proxy', label: 'CDN / Proxy' },
]

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m16 16 4 4" />
      </>
    ),
    layers: (
      <>
        <path d="m12 3 9 5-9 5-9-5 9-5Z" />
        <path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
      </>
    ),
    code: (
      <>
        <path d="m8 17-5-5 5-5m8 10 5-5-5-5m-3-7-2 24" />
      </>
    ),
    server: (
      <>
        <rect x="3" y="4" width="18" height="7" rx="1" />
        <rect x="3" y="13" width="18" height="7" rx="1" />
        <path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" />
      </>
    ),
    searchX: (
      <>
        <circle cx="10" cy="10" r="7" />
        <path d="m15 15 5 5m-12.5-12.5 5 5m0-5-5 5" />
      </>
    ),
    close: <path d="m18 6-12 12M6 6l12 12" />,
    arrow: <path d="m7 17 10-10M8 7h9v9" />,
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    chevronLeft: <path d="m15 18-6-6 6-6" />,
    chevronRight: <path d="m9 18 6-6-6-6" />,
    check: <path d="m5 12 4 4L19 6" />,
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
      </>
    ),
    eye: (
      <>
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
      </>
    ),
  }

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden="true"
    >
      {paths[name]}
    </svg>
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

function categoryLabel(value) {
  return CATEGORIES.find((item) => item.value === value)?.label
    || value?.replaceAll('_', ' ')
    || 'Uncategorized'
}

function TechnologyStatus({ active }) {
  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${
        active
          ? 'border-[#82918B]/50 bg-[#82918B]/10 text-[#50665C]'
          : 'border-[#963746]/30 bg-[#963746]/5 text-[#963746]'
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {active ? 'ACTIVE' : 'INACTIVE'}
    </span>
  )
}

function ConfidenceBar({ value }) {
  const confidence = Math.max(0, Math.min(100, Number(value || 0)))

  return (
    <div className="min-w-[100px]">
      <div className="mb-1 flex items-center justify-between">
        <span className="text-[8px] tracking-wider text-[#817A70]">
          CONFIDENCE
        </span>

        <span className="text-[10px] font-semibold text-[#963746]">
          {confidence}%
        </span>
      </div>

      <div className="h-1.5 overflow-hidden bg-[#E1D8C9]">
        <div
          className="h-full bg-[#963746] transition-all duration-700"
          style={{ width: `${confidence}%` }}
        />
      </div>
    </div>
  )
}

function TechnologyRegistryPage() {
  const [technologies, setTechnologies] = useState([])

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const [error, setError] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  const [categoryFilter, setCategoryFilter] = useState('all')
  const [activeFilter, setActiveFilter] = useState('all')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedTechnology, setSelectedTechnology] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const loadTechnologies = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const params = {
        search: search || undefined,
        per_page: 15,
        page: currentPage,
      }

      if (categoryFilter !== 'all') {
        params.category = categoryFilter
      }

      if (activeFilter !== 'all') {
        params.is_active = activeFilter
      }

      const response = await api.get('/api/technologies', {
        params,
      })

      const result = response.data

      setTechnologies(result.data || [])
      setTotal(result.total || 0)
      setCurrentPage(result.current_page || 1)
      setLastPage(result.last_page || 1)
    } catch (requestError) {
      console.error('Technology registry error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Katalog teknologi tidak dapat dimuat.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [search, categoryFilter, activeFilter, currentPage])

  useEffect(() => {
    loadTechnologies()
  }, [loadTechnologies])

  const activeCount = technologies.filter(
    (item) => item.is_active
  ).length

  const usedCount = technologies.filter(
    (item) => Number(item.websites_count) > 0
  ).length

  const totalWebsiteMatches = technologies.reduce(
    (sum, item) => sum + Number(item.websites_count || 0),
    0
  )

  const handleSearch = (event) => {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchInput.trim())
  }

  const resetFilters = () => {
    setSearch('')
    setSearchInput('')
    setCategoryFilter('all')
    setActiveFilter('all')
    setCurrentPage(1)
  }

  const openDetail = async (technology) => {
    setDetailOpen(true)
    setSelectedTechnology(null)
    setDetailLoading(true)
    setError('')

    try {
      const response = await api.get(
        `/api/technologies/${technology.id}`
      )

      setSelectedTechnology(response.data?.data || null)
    } catch (requestError) {
      console.error('Technology detail error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Detail teknologi tidak dapat dimuat.'
      )

      setDetailOpen(false)
    } finally {
      setDetailLoading(false)
    }
  }

  const closeDetail = () => {
    setDetailOpen(false)
    setSelectedTechnology(null)
  }

  const metrics = [
    {
      index: '01',
      label: 'TOTAL TECHNOLOGIES',
      value: total,
      description: 'Katalog sesuai filter saat ini',
      icon: 'layers',
    },
    {
      index: '02',
      label: 'ACTIVE ON THIS PAGE',
      value: activeCount,
      description: 'Teknologi aktif pada halaman ini',
      icon: 'check',
    },
    {
      index: '03',
      label: 'DETECTED ON THIS PAGE',
      value: usedCount,
      description: 'Teknologi dengan hasil identifikasi',
      icon: 'code',
    },
    {
      index: '04',
      label: 'WEBSITE MATCHES',
      value: totalWebsiteMatches,
      description: 'Pasangan teknologi dan website pada halaman ini',
      icon: 'globe',
    },
  ]

  return (
    <div className="simonta-page-enter space-y-8">

      {/* Page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          05
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / TECHNOLOGY INTELLIGENCE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Technology <span className="italic text-[#963746]">Archive.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Pusat katalog teknologi, aturan identifikasi, dan informasi
                teknologi yang ditemukan pada website OPD.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadTechnologies(true)}
              disabled={refreshing || loading}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto"
            >
              <Icon
                name="refresh"
                size={15}
                className={refreshing ? 'animate-spin' : ''}
              />
              REFRESH CATALOG
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              TECHNOLOGY SIGNATURES
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              FINGERPRINT ANALYSIS
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              REGISTRY API CONNECTED
            </span>
          </div>
        </div>
      </section>

      {/* Error */}
      {error && (
        <div
          role="alert"
          className="flex items-center gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs text-[#963746]"
        >
          <span className="font-serif text-lg">!</span>
          {error}
        </div>
      )}

      {/* Metrics */}
      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map((metric, index) => (
          <article
            key={metric.index}
            className="simonta-card-enter relative min-h-[150px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
            style={{ '--stagger-index': index }}
          >
            <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
              {metric.index}
            </span>

            <div className="relative flex items-center justify-between">
              <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
                {metric.label}
              </p>

              <div className="grid size-9 place-items-center border border-[#D8CFBF] text-[#963746]">
                <Icon name={metric.icon} />
              </div>
            </div>

            <h2 className="relative mt-5 font-serif text-4xl text-[#191A20]">
              {loading ? '—' : formatNumber(metric.value)}
            </h2>

            <p className="relative mt-2 text-[10px] text-[#817A70]">
              {metric.description}
            </p>
          </article>
        ))}
      </section>

      {/* Registry list */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC]">

        <div className="border-b border-[#D8CFBF] p-5">
          <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
              <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
                TECHNOLOGY DATABASE / 01
              </p>

              <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
                Registered Technologies
              </h2>

              <p className="mt-1 text-[10px] text-[#817A70]">
                {total} teknologi terdaftar dalam katalog SIMONTA
              </p>
            </div>

            <form
              onSubmit={handleSearch}
              className="flex min-w-0 sm:w-80"
            >
              <input
                type="search"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search technology or vendor..."
                aria-label="Cari teknologi"
                className="min-w-0 flex-1 border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs outline-none transition focus:border-[#B69A69]"
              />

              <button
                type="submit"
                aria-label="Cari teknologi"
                className="simonta-icon-action grid size-10 shrink-0 place-items-center bg-[#252B37] text-[#EEE9DD] transition hover:bg-[#963746]"
              >
                <Icon name="search" />
              </button>
            </form>
          </div>

          {/* Filters */}
          <div className="mt-5 flex flex-wrap items-end gap-3">
            <div>
              <label
                htmlFor="technology-category"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                CATEGORY
              </label>

              <select
                id="technology-category"
                value={categoryFilter}
                onChange={(event) => {
                  setCategoryFilter(event.target.value)
                  setCurrentPage(1)
                }}
                className="max-w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none transition focus:border-[#B69A69]"
              >
                <option value="all">All categories</option>

                {CATEGORIES.map((category) => (
                  <option key={category.value} value={category.value}>
                    {category.label}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                htmlFor="technology-status"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                STATUS
              </label>

              <select
                id="technology-status"
                value={activeFilter}
                onChange={(event) => {
                  setActiveFilter(event.target.value)
                  setCurrentPage(1)
                }}
                className="border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none transition focus:border-[#B69A69]"
              >
                <option value="all">All statuses</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>

            <button
              type="button"
              onClick={resetFilters}
              className="pb-2.5 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
            >
              RESET FILTERS
            </button>
          </div>
        </div>

        {/* Desktop table */}
        <div className="hidden overflow-x-auto md:block">
          <table className="w-full min-w-[850px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  TECHNOLOGY
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  CATEGORY
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  VENDOR
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  WEBSITES
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  STATUS
                </th>

                <th className="px-5 py-4 text-right text-[9px] font-bold tracking-wider text-[#817A70]">
                  DETAIL
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? (
                [1, 2, 3].map((item) => (
                  <tr key={item}>
                    <td colSpan="6" className="px-5 py-6">
                      <div className="simonta-skeleton h-4 w-2/3" />
                    </td>
                  </tr>
                ))
              ) : technologies.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-5 py-14 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="searchX" size={24} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Technology Found
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      Tidak ada teknologi yang cocok dengan filter.
                    </p>

                    <button
                      type="button"
                      onClick={resetFilters}
                      className="mt-4 text-[9px] font-bold tracking-wider text-[#963746] underline"
                    >
                      CLEAR FILTERS
                    </button>
                  </td>
                </tr>
              ) : (
                technologies.map((technology, index) => (
                  <tr
                    key={technology.id}
                    className="group transition duration-200 hover:bg-[#F1EBDD]"
                    style={{ animationDelay: `${index * 40}ms` }}
                  >
                    <td className="px-5 py-5">
                      <p className="text-sm font-semibold text-[#252B37]">
                        {technology.name}
                      </p>

                      <p className="mt-1 text-[10px] text-[#948B7D]">
                        {technology.slug}
                      </p>
                    </td>

                    <td className="px-5 py-5">
                      <span className="border border-[#D8CFBF] bg-[#FAF8F2] px-2.5 py-1 text-[9px] text-[#665F56]">
                        {categoryLabel(technology.category)}
                      </span>
                    </td>

                    <td className="px-5 py-5 text-xs text-[#665F56]">
                      {technology.vendor || '—'}
                    </td>

                    <td className="px-5 py-5 text-center">
                      <span className="font-serif text-xl text-[#963746]">
                        {formatNumber(technology.websites_count)}
                      </span>
                    </td>

                    <td className="px-5 py-5 text-center">
                      <TechnologyStatus active={technology.is_active} />
                    </td>

                    <td className="px-5 py-5 text-right">
                      <button
                        type="button"
                        onClick={() => openDetail(technology)}
                        aria-label={`Detail ${technology.name}`}
                        title="View technology detail"
                        className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 hover:text-[#963746]"
                      >
                        <Icon name="eye" size={14} />
                        EXPLORE
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Mobile cards */}
        <div className="divide-y divide-[#D8CFBF] md:hidden">
          {loading ? (
            [1, 2, 3].map((item) => (
              <div key={item} className="p-5">
                <div className="simonta-skeleton h-4 w-2/3" />
                <div className="simonta-skeleton mt-3 h-3 w-1/2" />
              </div>
            ))
          ) : (
            technologies.map((technology) => (
              <article key={technology.id} className="space-y-4 p-5">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <h3 className="font-semibold text-sm text-[#252B37]">
                      {technology.name}
                    </h3>

                    <p className="mt-1 text-[10px] text-[#817A70]">
                      {technology.slug}
                    </p>
                  </div>

                  <TechnologyStatus active={technology.is_active} />
                </div>

                <div className="grid grid-cols-2 gap-3 border-y border-[#D8CFBF] py-3">
                  <div>
                    <p className="text-[8px] tracking-wider text-[#817A70]">
                      CATEGORY
                    </p>

                    <p className="mt-1 text-xs text-[#252B37]">
                      {categoryLabel(technology.category)}
                    </p>
                  </div>

                  <div>
                    <p className="text-[8px] tracking-wider text-[#817A70]">
                      WEBSITES
                    </p>

                    <p className="mt-1 font-serif text-xl text-[#963746]">
                      {formatNumber(technology.websites_count)}
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => openDetail(technology)}
                  className="simonta-angular-action inline-flex w-full items-center justify-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-wider text-[#665F56]"
                >
                  <Icon name="eye" size={14} />
                  EXPLORE TECHNOLOGY
                </button>
              </article>
            ))
          )}

          {!loading && technologies.length === 0 && (
            <div className="p-8 text-center text-xs text-[#817A70]">
              Tidak ada teknologi yang ditemukan.
            </div>
          )}
        </div>

        {/* Pagination */}
        {!loading && total > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Showing page {currentPage} of {lastPage} · {total} technologies
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="chevronLeft" />
              </button>

              <span className="min-w-8 text-center font-serif text-sm text-[#252B37]">
                {currentPage}
              </span>

              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(lastPage, page + 1))}
                disabled={currentPage >= lastPage}
                aria-label="Next page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="chevronRight" />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* Technology detail modal */}
      {detailOpen && (
        <div
          className="simonta-backdrop-enter fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#191A20]/65 p-4 backdrop-blur-[2px]"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) closeDetail()
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="technology-detail-title"
            className="simonta-card-enter my-auto w-full max-w-2xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
          >
            <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  TECHNOLOGY INTELLIGENCE
                </p>

                <h2
                  id="technology-detail-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  Technology Detail.
                </h2>

                <p className="mt-2 text-[10px] text-[#817A70]">
                  Catalog information and detection signatures.
                </p>
              </div>

              <button
                type="button"
                onClick={closeDetail}
                aria-label="Close technology detail"
                className="simonta-icon-action grid size-9 shrink-0 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#963746] hover:text-[#963746]"
              >
                <Icon name="close" />
              </button>
            </div>

            {detailLoading || !selectedTechnology ? (
              <div className="space-y-4 p-7">
                <div className="simonta-skeleton h-6 w-1/2" />
                <div className="simonta-skeleton h-4 w-full" />
                <div className="simonta-skeleton h-4 w-2/3" />
              </div>
            ) : (
              <div className="space-y-6 p-5 sm:p-7">

                {/* Technology identity */}
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                  <div>
                    <h3 className="font-serif text-3xl text-[#191A20]">
                      {selectedTechnology.name}
                    </h3>

                    <p className="mt-1 text-xs text-[#817A70]">
                      {selectedTechnology.slug}
                    </p>

                    <div className="mt-3 flex flex-wrap gap-2">
                      <span className="border border-[#D8CFBF] bg-[#F7F4EC] px-2.5 py-1 text-[9px] text-[#665F56]">
                        {categoryLabel(selectedTechnology.category)}
                      </span>

                      <TechnologyStatus active={selectedTechnology.is_active} />
                    </div>
                  </div>

                  <div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4 sm:min-w-[130px]">
                    <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                      WEBSITES DETECTED
                    </p>

                    <p className="mt-1 font-serif text-3xl text-[#963746]">
                      {formatNumber(selectedTechnology.websites_count)}
                    </p>
                  </div>
                </div>

                <div className="grid gap-4 border-y border-[#D8CFBF] py-4 sm:grid-cols-2">
                  <div>
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      VENDOR / DEVELOPER
                    </p>

                    <p className="mt-2 text-sm text-[#252B37]">
                      {selectedTechnology.vendor || 'Not specified'}
                    </p>
                  </div>

                  <div>
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      TECHNOLOGY CATEGORY
                    </p>

                    <p className="mt-2 text-sm text-[#252B37]">
                      {categoryLabel(selectedTechnology.category)}
                    </p>
                  </div>

                  <div className="sm:col-span-2">
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      DESCRIPTION
                    </p>

                    <p className="mt-2 text-xs leading-6 text-[#665F56]">
                      {selectedTechnology.description || 'No description available.'}
                    </p>
                  </div>
                </div>

                {/* Signatures */}
                <div>
                  <div className="flex items-center justify-between gap-3">
                    <div>
                      <h3 className="font-serif text-xl text-[#191A20]">
                        Detection Signatures
                      </h3>

                      <p className="mt-1 text-[10px] text-[#817A70]">
                        Aturan yang digunakan oleh detection engine.
                      </p>
                    </div>

                    <span className="border border-[#D8CFBF] px-2 py-1 text-[9px] tracking-wider text-[#665F56]">
                      {selectedTechnology.signatures?.length || 0} RULES
                    </span>
                  </div>

                  {!selectedTechnology.signatures?.length ? (
                    <div className="mt-4 border border-dashed border-[#D8CFBF] p-5 text-center text-xs text-[#817A70]">
                      Belum ada signature untuk teknologi ini.
                    </div>
                  ) : (
                    <div className="mt-4 space-y-3">
                      {selectedTechnology.signatures.map((signature) => (
                        <article
                          key={signature.id}
                          className="border border-[#D8CFBF] bg-[#F7F4EC] p-4"
                        >
                          <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                              <h4 className="text-xs font-semibold text-[#252B37]">
                                {signature.name}
                              </h4>

                              <div className="mt-2 flex flex-wrap gap-2">
                                <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-bold tracking-wider text-[#665F56]">
                                  {signature.type?.toUpperCase()}
                                </span>

                                <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] text-[#665F56]">
                                  {signature.match_type}
                                </span>

                                <TechnologyStatus active={signature.is_active} />
                              </div>
                            </div>

                            <div className="sm:w-36">
                              <ConfidenceBar value={signature.confidence} />
                            </div>
                          </div>

                          {signature.target && (
                            <div className="mt-3">
                              <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                                TARGET
                              </p>

                              <code className="mt-1 inline-block bg-[#EAE4D8] px-2 py-1 text-[10px] text-[#963746]">
                                {signature.target}
                              </code>
                            </div>
                          )}

                          <div className="mt-3">
                            <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                              MATCH PATTERN
                            </p>

                            <pre className="mt-1 overflow-x-auto whitespace-pre-wrap break-all bg-[#252B37] p-3 text-[10px] leading-5 text-[#EEE9DD]">
                              {signature.pattern}
                            </pre>
                          </div>

                          {signature.version_pattern && (
                            <div className="mt-3">
                              <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                                VERSION PATTERN
                              </p>

                              <pre className="mt-1 overflow-x-auto whitespace-pre-wrap break-all bg-[#EAE4D8] p-3 text-[10px] leading-5 text-[#665F56]">
                                {signature.version_pattern}
                              </pre>
                            </div>
                          )}
                        </article>
                      ))}
                    </div>
                  )}
                </div>

                <div className="border-l-2 border-[#B69A69] bg-[#B69A69]/10 p-4">
                  <p className="text-[9px] font-bold tracking-wider text-[#816D43]">
                    DETECTION NOTE
                  </p>

                  <p className="mt-2 text-[10px] leading-5 text-[#665F56]">
                    Confidence merupakan bobot aturan signature, bukan
                    probabilitas statistik. Hasil identifikasi bergantung
                    pada fingerprint yang terlihat dari website saat diperiksa.
                  </p>
                </div>
              </div>
            )}

            <div className="flex justify-end border-t border-[#D8CFBF] p-5 sm:px-7">
              <button
                type="button"
                onClick={closeDetail}
                className="simonta-angular-action border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10"
              >
                CLOSE DETAIL
              </button>
            </div>
          </section>
        </div>
      )}
    </div>
  )
}

export default TechnologyRegistryPage
