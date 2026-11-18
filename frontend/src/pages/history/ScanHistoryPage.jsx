
import { useCallback, useEffect, useMemo, useState } from 'react'

import api from '../../services/api'

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m16 16 4 4" />
      </>
    ),
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    check: <path d="m5 12 4 4L19 6" />,
    alert: (
      <>
        <path d="M12 3 22 20H2L12 3Z" />
        <path d="M12 9v4m0 3h.01" />
      </>
    ),
    eye: (
      <>
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
      </>
    ),
    compare: (
      <>
        <path d="M8 4H4v16h4M16 4h4v16h-4M9 8h6M9 12h6M9 16h6" />
      </>
    ),
    close: <path d="m18 6-12 12M6 6l12 12" />,
    chevronLeft: <path d="m15 18-6-6 6-6" />,
    chevronRight: <path d="m9 18 6-6-6-6" />,
    calendar: (
      <>
        <rect x="3" y="5" width="18" height="16" rx="1" />
        <path d="M16 3v4M8 3v4M3 10h18" />
      </>
    ),
    layers: (
      <>
        <path d="m12 3 9 5-9 5-9-5 9-5Z" />
        <path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
      </>
    ),
    arrow: <path d="m7 17 10-10M8 7h9v9" />,
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

function formatDate(value) {
  if (!value) return 'Belum tersedia'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return 'Tanggal tidak valid'

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function StatusBadge({ status }) {
  const config = {
    queued: {
      label: 'QUEUED',
      style: 'border-[#B69A69]/50 bg-[#B69A69]/10 text-[#816D43]',
    },
    running: {
      label: 'RUNNING',
      style: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    },
    completed: {
      label: 'COMPLETED',
      style: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    },
    failed: {
      label: 'FAILED',
      style: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
    },
  }

  const selected = config[status] || {
    label: String(status || 'UNKNOWN').toUpperCase(),
    style: 'border-[#D8CFBF] text-[#817A70]',
  }

  return (
    <span className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${selected.style}`}>
      <span className="size-1.5 rounded-full bg-current" />
      {selected.label}
    </span>
  )
}

function HistoryStat({ label, value, icon, color, index }) {
  return (
    <article
      className="simonta-card-enter relative min-h-[140px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
      style={{ '--stagger-index': index }}
    >
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
        {String(index + 1).padStart(2, '0')}
      </span>

      <div className="relative flex items-center justify-between">
        <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
          {label}
        </p>

        <div className={`grid size-9 place-items-center border border-[#D8CFBF] ${color}`}>
          <Icon name={icon} />
        </div>
      </div>

      <h3 className="relative mt-5 font-serif text-4xl text-[#191A20]">
        {formatNumber(value)}
      </h3>
    </article>
  )
}

function getTechnologyMap(scan) {
  return new Map(
    (scan?.scan_technologies || []).map((item) => [
      item.technology_id ?? item.technology?.id,
      item,
    ])
  )
}

function ScanHistoryPage() {
  const [scans, setScans] = useState([])
  const [websites, setWebsites] = useState([])
  const [statistics, setStatistics] = useState({})

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  // Filter
  const [statusFilter, setStatusFilter] = useState('all')
  const [websiteFilter, setWebsiteFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  // Scan detail
  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedScan, setSelectedScan] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  // Comparison
  const [compareIds, setCompareIds] = useState([])
  const [compareOpen, setCompareOpen] = useState(false)
  const [compareData, setCompareData] = useState(null)
  const [compareLoading, setCompareLoading] = useState(false)

  const hasActiveScans =
    Number(statistics.queued_scans || 0) > 0 ||
    Number(statistics.running_scans || 0) > 0

  const loadWebsites = useCallback(async () => {
    try {
      const response = await api.get('/api/websites', {
        params: {
          per_page: 100,
          is_active: 1,
        },
      })

      setWebsites(response.data?.data || [])
    } catch (requestError) {
      console.error('History website options error:', requestError)
    }
  }, [])

  const loadHistory = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const params = {
        per_page: 20,
        page: currentPage,
      }

      if (statusFilter !== 'all') {
        params.status = statusFilter
      }

      if (websiteFilter !== 'all') {
        params.website_id = websiteFilter
      }

      if (dateFrom) {
        params.date_from = dateFrom
      }

      if (dateTo) {
        params.date_to = dateTo
      }

      const [historyResponse, summaryResponse] = await Promise.all([
        api.get('/api/scans', { params }),
        api.get('/api/dashboard/summary'),
      ])

      const result = historyResponse.data || {}

      setScans(result.data || [])
      setTotal(result.total || 0)
      setCurrentPage(result.current_page || 1)
      setLastPage(result.last_page || 1)

      setStatistics(
        summaryResponse.data?.data?.statistics || {}
      )
    } catch (requestError) {
      console.error('Scan history error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Riwayat pemeriksaan tidak dapat dimuat.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [
    currentPage,
    statusFilter,
    websiteFilter,
    dateFrom,
    dateTo,
  ])

  useEffect(() => {
    loadWebsites()
  }, [loadWebsites])

  useEffect(() => {
    loadHistory()
  }, [loadHistory])

  useEffect(() => {
    // Reset pilihan compare ketika filter atau halaman berubah.
    setCompareIds([])
  }, [currentPage, statusFilter, websiteFilter, dateFrom, dateTo])

  useEffect(() => {
    const interval = window.setInterval(
      () => loadHistory(true),
      hasActiveScans ? 5000 : 30000
    )

    return () => window.clearInterval(interval)
  }, [loadHistory, hasActiveScans])

  const resetFilters = () => {
    setStatusFilter('all')
    setWebsiteFilter('all')
    setDateFrom('')
    setDateTo('')
    setCurrentPage(1)
    setCompareIds([])
  }

  const handleCompareSelection = (scan) => {
    setNotice('')
    setError('')

    if (scan.status !== 'completed') {
      return
    }

    const alreadySelected = compareIds.includes(scan.id)

    if (alreadySelected) {
      setCompareIds((previous) =>
        previous.filter((id) => id !== scan.id)
      )
      return
    }

    if (compareIds.length === 2) {
      setNotice('Maksimal dua scan dapat dibandingkan. Hapus salah satu pilihan terlebih dahulu.')
      return
    }

    if (compareIds.length === 1) {
      const firstScan = scans.find(
        (item) => item.id === compareIds[0]
      )

      if (
        firstScan
        && firstScan.website_id !== scan.website_id
      ) {
        setNotice('Pilih dua pemeriksaan dari website yang sama agar perubahannya dapat dibandingkan.')
        return
      }
    }

    setCompareIds((previous) => [...previous, scan.id])
  }

  const openDetail = async (scan) => {
    setSelectedScan(null)
    setDetailOpen(true)
    setDetailLoading(true)
    setError('')

    try {
      const response = await api.get(`/api/scans/${scan.id}`)

      setSelectedScan(response.data?.data || null)
    } catch (requestError) {
      console.error('Scan detail error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Detail pemeriksaan tidak dapat dimuat.'
      )

      setDetailOpen(false)
    } finally {
      setDetailLoading(false)
    }
  }

  const compareSelectedScans = async () => {
    if (compareIds.length !== 2) return

    setCompareLoading(true)
    setCompareOpen(true)
    setCompareData(null)
    setError('')

    
try {
  const responses = await Promise.all(
    compareIds.map((id) => api.get(`/api/scans/${id}`))
  )

  // Urutkan berdasarkan waktu pemeriksaan, dari paling lama.
  const chronological = responses
    .map((response) => response.data.data)
    .sort((a, b) => {
      const dateA = new Date(
        a.completed_at || a.started_at || a.created_at
      ).getTime()

      const dateB = new Date(
        b.completed_at || b.started_at || b.created_at
      ).getTime()

      return dateA - dateB
    })

  setCompareData({
    first: chronological[0],
    second: chronological[1],
  })
} catch (requestError) {
  console.error('Compare scans error:', requestError)

  setError(
    requestError.response?.data?.message ||
    'Hasil pemeriksaan tidak dapat dibandingkan.'
  )

  setCompareOpen(false)
} finally {
  setCompareLoading(false)
}

  }

  const closeDetail = () => {
    setDetailOpen(false)
    setSelectedScan(null)
  }

  const closeCompare = () => {
    setCompareOpen(false)
    setCompareData(null)
  }

  const comparisonRows = useMemo(() => {
    if (!compareData) return []

    const firstMap = getTechnologyMap(compareData.first)
    const secondMap = getTechnologyMap(compareData.second)

    const allIds = new Set([
      ...firstMap.keys(),
      ...secondMap.keys(),
    ])

    return Array.from(allIds).map((id) => {
      const first = firstMap.get(id)
      const second = secondMap.get(id)

      const technology = first?.technology || second?.technology

      let change = 'unchanged'

      if (!first && second) {
        change = 'added'
      } else if (first && !second) {
        change = 'removed'
      } else if ((first?.version || '') !== (second?.version || '')) {
        change = 'version_changed'
      }

      return {
        id,
        name: technology?.name || `Technology #${id}`,
        category: technology?.category || 'uncategorized',
        firstVersion: first?.version || 'Not detected',
        secondVersion: second?.version || 'Not detected',
        firstConfidence: first?.confidence ?? null,
        secondConfidence: second?.confidence ?? null,
        change,
      }
    }).sort((a, b) => a.name.localeCompare(b.name))
  }, [compareData])

  const compareSummary = useMemo(() => ({
    added: comparisonRows.filter((item) => item.change === 'added').length,
    removed: comparisonRows.filter((item) => item.change === 'removed').length,
    changed: comparisonRows.filter((item) => item.change === 'version_changed').length,
    unchanged: comparisonRows.filter((item) => item.change === 'unchanged').length,
  }), [comparisonRows])

  const metricCards = [
    {
      label: 'TOTAL SCANS',
      value: total,
      icon: 'layers',
      color: 'text-[#963746]',
    },
    {
      label: 'COMPLETED',
      value: statistics.completed_scans,
      icon: 'check',
      color: 'text-[#82918B]',
    },
    {
      label: 'FAILED',
      value: statistics.failed_scans,
      icon: 'alert',
      color: 'text-[#963746]',
    },
    {
      label: 'IN PROGRESS',
      value:
        Number(statistics.queued_scans || 0) +
        Number(statistics.running_scans || 0),
      icon: 'clock',
      color: 'text-[#B69A69]',
    },
  ]

  return (
    <div className="simonta-page-enter space-y-8">

      {/* Page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          06
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / INSPECTION ARCHIVE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Inspection <span className="italic text-[#963746]">History.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Arsip pemeriksaan website OPD, rekam jejak identifikasi
                teknologi, dan perubahan fingerprint dari waktu ke waktu.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadHistory(true)}
              disabled={refreshing || loading}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto"
            >
              <Icon
                name="refresh"
                className={refreshing ? 'animate-spin' : ''}
              />
              REFRESH HISTORY
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              SCAN ARCHIVE
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              DATE FILTERS
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              COMPARISON ENABLED
            </span>
          </div>
        </div>
      </section>

      {/* Notices */}
      {notice && (
        <div
          role="status"
          className="border border-[#B69A69]/50 bg-[#B69A69]/10 p-4 text-xs leading-5 text-[#816D43]"
        >
          {notice}
        </div>
      )}

      {error && (
        <div
          role="alert"
          className="border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs leading-5 text-[#963746]"
        >
          {error}
        </div>
      )}

      {/* Summary metrics */}
      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {metricCards.map((metric, index) => (
          <HistoryStat
            key={metric.label}
            {...metric}
            index={index}
          />
        ))}
      </section>

      {/* Filters */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="border-b border-[#D8CFBF] p-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            ARCHIVE SEARCH / 01
          </p>

          <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
            Search & Filter Records
          </h2>
        </div>

        <div className="grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-4">

          <div>
            <label
              htmlFor="history-status"
              className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]"
            >
              SCAN STATUS
            </label>

            <select
              id="history-status"
              value={statusFilter}
              onChange={(event) => {
                setStatusFilter(event.target.value)
                setCurrentPage(1)
              }}
              className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
            >
              <option value="all">All statuses</option>
              <option value="queued">Queued</option>
              <option value="running">Running</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
            </select>
          </div>

          <div>
            <label
              htmlFor="history-website"
              className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]"
            >
              WEBSITE
            </label>

            <select
              id="history-website"
              value={websiteFilter}
              onChange={(event) => {
                setWebsiteFilter(event.target.value)
                setCurrentPage(1)
              }}
              className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
            >
              <option value="all">All websites</option>

              {websites.map((website) => (
                <option key={website.id} value={String(website.id)}>
                  {website.name || website.url}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label
              htmlFor="history-date-from"
              className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]"
            >
              DATE FROM
            </label>

            <input
              id="history-date-from"
              type="date"
              value={dateFrom}
              max={dateTo || undefined}
              onChange={(event) => {
                setDateFrom(event.target.value)
                setCurrentPage(1)
              }}
              className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
            />
          </div>

          <div>
            <label
              htmlFor="history-date-to"
              className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]"
            >
              DATE TO
            </label>

            <input
              id="history-date-to"
              type="date"
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(event) => {
                setDateTo(event.target.value)
                setCurrentPage(1)
              }}
              className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
            />
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4">
          <p className="text-[10px] text-[#817A70]">
            {total} records found
          </p>

          <button
            type="button"
            onClick={resetFilters}
            className="text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
          >
            RESET ALL FILTERS
          </button>
        </div>
      </section>

      {/* Compare toolbar */}
      <section className="flex flex-col justify-between gap-4 border border-[#B69A69]/50 bg-[#252B37] p-5 text-[#EEE9DD] sm:flex-row sm:items-center">
        <div>
          <p className="text-[9px] font-bold tracking-[0.18em] text-[#B69A69]">
            TECHNOLOGY CHANGE ANALYSIS
          </p>

          <h2 className="mt-2 font-serif text-xl">
            Compare Inspection Results
          </h2>

          <p className="mt-1 text-[10px] text-[#B8B4AA]">
            Pilih dua scan completed dari website yang sama.
            Pilihan harus berada pada halaman riwayat yang sedang dibuka.
          </p>

          <p className="mt-2 text-[10px] text-[#B69A69]">
            {compareIds.length} / 2 scans selected
          </p>
        </div>

        <button
          type="button"
          onClick={compareSelectedScans}
          disabled={compareIds.length !== 2 || compareLoading}
          className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start bg-[#963746] px-5 py-3 text-[10px] font-bold tracking-wider text-white transition hover:-translate-y-0.5 hover:bg-[#762B37] disabled:cursor-not-allowed disabled:bg-[#55545A] disabled:text-[#B8B4AA] sm:self-auto"
        >
          <Icon name="compare" size={15} />
          {compareLoading ? 'COMPARING...' : 'COMPARE SELECTED'}
        </button>
      </section>

      {/* History table */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="border-b border-[#D8CFBF] p-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            CHRONOLOGICAL RECORDS / 02
          </p>

          <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
            Inspection Records
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            Pemeriksaan terbaru ditampilkan terlebih dahulu.
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[900px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                <th className="w-12 px-4 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  COMPARE
                </th>

                <th className="px-4 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  SCAN / WEBSITE
                </th>

                <th className="px-4 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  DATE
                </th>

                <th className="px-4 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  STATUS
                </th>

                <th className="px-4 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  HTTP
                </th>

                <th className="px-4 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  DURATION
                </th>

                <th className="px-4 py-4 text-center text-[9px] font-bold tracking-wider text-[#817A70]">
                  TECHNOLOGIES
                </th>

                <th className="px-4 py-4 text-right text-[9px] font-bold tracking-wider text-[#817A70]">
                  DETAIL
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? (
                [1, 2, 3].map((item) => (
                  <tr key={item}>
                    <td colSpan="8" className="px-5 py-6">
                      <div className="simonta-skeleton h-4 w-2/3" />
                    </td>
                  </tr>
                ))
              ) : scans.length === 0 ? (
                <tr>
                  <td colSpan="8" className="px-5 py-14 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="search" size={23} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Inspection Records
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      Tidak ada pemeriksaan yang cocok dengan filter.
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
                scans.map((scan) => {
                  const checked = compareIds.includes(scan.id)

                  return (
                    <tr
                      key={scan.id}
                      className={`transition duration-200 hover:bg-[#F1EBDD] ${
                        checked ? 'bg-[#B69A69]/10' : ''
                      }`}
                    >
                      <td className="px-4 py-5 text-center">
                        <input
                          type="checkbox"
                          checked={checked}
                          disabled={scan.status !== 'completed'}
                          onChange={() => handleCompareSelection(scan)}
                          aria-label={`Select scan ${scan.id} for comparison`}
                          title={
                            scan.status !== 'completed'
                              ? 'Hanya scan completed yang dapat dibandingkan'
                              : 'Pilih untuk perbandingan'
                          }
                          className="size-4 accent-[#963746] disabled:cursor-not-allowed disabled:opacity-25"
                        />
                      </td>

                      <td className="max-w-[240px] px-4 py-5">
                        <p className="text-[10px] tracking-wider text-[#963746]">
                          SCAN #{String(scan.id).padStart(5, '0')}
                        </p>

                        <p className="mt-1 truncate text-sm font-medium text-[#252B37]">
                          {scan.website?.name || 'Unnamed Website'}
                        </p>

                        <p className="mt-1 truncate text-[10px] text-[#817A70]">
                          {scan.website?.opd?.name || 'Unknown OPD'}
                        </p>
                      </td>

                      <td className="px-4 py-5">
                        <p className="whitespace-nowrap text-[10px] text-[#665F56]">
                          {formatDate(scan.completed_at || scan.started_at || scan.created_at)}
                        </p>
                      </td>

                      <td className="px-4 py-5">
                        <StatusBadge status={scan.status} />
                      </td>

                      <td className="px-4 py-5 text-center">
                        <span className={`font-serif text-base ${
                          scan.http_status_code >= 400
                            ? 'text-[#963746]'
                            : 'text-[#252B37]'
                        }`}>
                          {scan.http_status_code || '—'}
                        </span>
                      </td>

                      <td className="px-4 py-5 text-center">
                        <span className="text-[10px] text-[#665F56]">
                          {scan.response_time_ms != null
                            ? `${formatNumber(scan.response_time_ms)} ms`
                            : '—'}
                        </span>
                      </td>

                      <td className="px-4 py-5 text-center">
                        <span className="font-serif text-xl text-[#963746]">
                          {formatNumber(scan.scan_technologies_count)}
                        </span>
                      </td>

                      <td className="px-4 py-5 text-right">
                        <button
                          type="button"
                          onClick={() => openDetail(scan)}
                          className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 hover:text-[#963746]"
                        >
                          <Icon name="eye" size={13} />
                          DETAIL
                        </button>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {!loading && total > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Showing page {currentPage} of {lastPage} · {total} records
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] disabled:opacity-35"
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
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] disabled:opacity-35"
              >
                <Icon name="chevronRight" />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* Scan detail modal */}
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
            aria-labelledby="history-detail-title"
            className="simonta-card-enter my-auto w-full max-w-2xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
          >
            <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  INSPECTION RECORD
                </p>

                <h2
                  id="history-detail-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  Scan Detail.
                </h2>
              </div>

              <button
                type="button"
                onClick={closeDetail}
                aria-label="Close detail"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] hover:border-[#963746] hover:text-[#963746]"
              >
                <Icon name="close" />
              </button>
            </div>

            {detailLoading || !selectedScan ? (
              <div className="space-y-4 p-7">
                <div className="simonta-skeleton h-5 w-1/3" />
                <div className="simonta-skeleton h-4 w-full" />
                <div className="simonta-skeleton h-4 w-2/3" />
              </div>
            ) : (
              <div className="space-y-6 p-5 sm:p-7">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="font-serif text-xl text-[#252B37]">
                    SCAN #{String(selectedScan.id).padStart(5, '0')}
                  </p>

                  <StatusBadge status={selectedScan.status} />
                </div>

                <div className="border-y border-[#D8CFBF] py-4">
                  <p className="text-[9px] font-bold tracking-wider text-[#963746]">
                    WEBSITE
                  </p>

                  <p className="mt-2 text-sm font-medium text-[#252B37]">
                    {selectedScan.website?.name || 'Unnamed Website'}
                  </p>

                  <p className="mt-1 break-all text-[10px] text-[#817A70]">
                    {selectedScan.website?.url}
                  </p>

                  <p className="mt-2 text-[10px] text-[#817A70]">
                    {selectedScan.website?.opd?.name || 'Unknown OPD'}
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                  {[
                    ['HTTP STATUS', selectedScan.http_status_code || '—'],
                    ['RESPONSE', selectedScan.response_time_ms != null ? `${formatNumber(selectedScan.response_time_ms)} ms` : '—'],
                    ['TECHNOLOGIES', selectedScan.scan_technologies?.length || 0],
                    ['REDIRECTS', selectedScan.metadata?.redirect_count ?? '—'],
                  ].map(([label, value]) => (
                    <div key={label} className="border border-[#D8CFBF] bg-[#F7F4EC] p-3">
                      <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                        {label}
                      </p>

                      <p className="mt-2 font-serif text-xl text-[#252B37]">
                        {value}
                      </p>
                    </div>
                  ))}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      STARTED AT
                    </p>

                    <p className="mt-1 text-xs text-[#252B37]">
                      {formatDate(selectedScan.started_at)}
                    </p>
                  </div>

                  <div>
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      COMPLETED AT
                    </p>

                    <p className="mt-1 text-xs text-[#252B37]">
                      {formatDate(selectedScan.completed_at)}
                    </p>
                  </div>
                </div>

                {selectedScan.error_message && (
                  <div className="border border-[#963746]/30 bg-[#963746]/5 p-4">
                    <p className="text-[9px] font-bold tracking-wider text-[#963746]">
                      INSPECTION ERROR
                    </p>

                    <p className="mt-2 break-words text-xs leading-5 text-[#963746]">
                      {selectedScan.error_message}
                    </p>
                  </div>
                )}

                <div className="border-t border-[#D8CFBF] pt-5">
                  <h3 className="font-serif text-xl text-[#191A20]">
                    Detected Technologies
                  </h3>

                  {!selectedScan.scan_technologies?.length ? (
                    <p className="mt-4 border border-dashed border-[#D8CFBF] p-5 text-center text-xs text-[#817A70]">
                      Tidak ada teknologi terdeteksi.
                    </p>
                  ) : (
                    <div className="mt-4 space-y-2">
                      {selectedScan.scan_technologies.map((item) => (
                        <div
                          key={item.id}
                          className="flex flex-col justify-between gap-3 border border-[#D8CFBF] bg-[#F7F4EC] p-4 sm:flex-row sm:items-center"
                        >
                          <div>
                            <p className="text-sm font-semibold text-[#252B37]">
                              {item.technology?.name || 'Unknown Technology'}
                            </p>

                            <p className="mt-1 text-[9px] text-[#817A70]">
                              {item.technology?.category || 'Uncategorized'}
                            </p>

                            <p className="mt-1 text-[9px] text-[#963746]">
                              {item.signature?.name || 'Unknown signature'}
                            </p>
                          </div>

                          <div className="flex gap-5">
                            <div>
                              <p className="text-[8px] tracking-wider text-[#817A70]">
                                VERSION
                              </p>

                              <p className="mt-1 text-xs text-[#252B37]">
                                {item.version || 'Unknown'}
                              </p>
                            </div>

                            <div>
                              <p className="text-[8px] tracking-wider text-[#817A70]">
                                CONFIDENCE
                              </p>

                              <p className="mt-1 font-serif text-xl text-[#963746]">
                                {item.confidence}%
                              </p>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {selectedScan.final_url && (
                  <div>
                    <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                      FINAL URL
                    </p>

                    <p className="mt-1 break-all text-xs text-[#963746]">
                      {selectedScan.final_url}
                    </p>
                  </div>
                )}
              </div>
            )}

            <div className="flex justify-end border-t border-[#D8CFBF] p-5 sm:px-7">
              <button
                type="button"
                onClick={closeDetail}
                className="simonta-angular-action border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] hover:bg-[#B69A69]/10"
              >
                CLOSE DETAIL
              </button>
            </div>
          </section>
        </div>
      )}

      {/* Compare modal */}
      {compareOpen && (
        <div
          className="simonta-backdrop-enter fixed inset-0 z-[110] flex items-center justify-center overflow-y-auto bg-[#191A20]/70 p-4 backdrop-blur-[2px]"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) closeCompare()
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="comparison-title"
            className="simonta-card-enter my-auto w-full max-w-5xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.3)]"
          >
            <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  TECHNOLOGY CHANGE ANALYSIS
                </p>

                <h2
                  id="comparison-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  Scan Comparison.
                </h2>

                <p className="mt-2 text-[10px] text-[#817A70]">
                  Perbandingan hasil identifikasi dari dua pemeriksaan.
                </p>
              </div>

              <button
                type="button"
                onClick={closeCompare}
                aria-label="Close comparison"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] hover:border-[#963746] hover:text-[#963746]"
              >
                <Icon name="close" />
              </button>
            </div>

            {compareLoading || !compareData ? (
              <div className="space-y-4 p-7">
                <div className="simonta-skeleton h-6 w-1/2" />
                <div className="simonta-skeleton h-4 w-full" />
                <div className="simonta-skeleton h-4 w-2/3" />
              </div>
            ) : (
              <div className="space-y-6 p-5 sm:p-7">
                <div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4">
                  <p className="text-[9px] font-bold tracking-wider text-[#963746]">
                    WEBSITE
                  </p>

                  <p className="mt-2 text-sm font-semibold text-[#252B37]">
                    {compareData.first.website?.name || compareData.first.website?.url}
                  </p>

                  <p className="mt-1 break-all text-[10px] text-[#817A70]">
                    {compareData.first.website?.url}
                  </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                  {[compareData.first, compareData.second].map((scan, index) => (
                    <div
                      key={scan.id}
                      className={`border p-4 ${
                        index === 0
                          ? 'border-[#B69A69]/60 bg-[#B69A69]/10'
                          : 'border-[#963746]/30 bg-[#963746]/5'
                      }`}
                    >
                      <p className="text-[9px] font-bold tracking-wider text-[#817A70]">
                        {index === 0 ? 'EARLIER SCAN' : 'LATEST SELECTED SCAN'}
                      </p>

                      <p className="mt-2 font-serif text-xl text-[#252B37]">
                        #{String(scan.id).padStart(5, '0')}
                      </p>

                      <p className="mt-1 text-[10px] text-[#665F56]">
                        {formatDate(scan.completed_at || scan.created_at)}
                      </p>

                      <p className="mt-2 text-[10px] text-[#665F56]">
                        HTTP {scan.http_status_code || '—'}
                        {' · '}
                        {scan.response_time_ms != null
                          ? `${formatNumber(scan.response_time_ms)} ms`
                          : '—'}
                      </p>
                    </div>
                  ))}
                </div>

                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  {[
                    ['NEW', compareSummary.added],
                    ['REMOVED', compareSummary.removed],
                    ['VERSION CHANGED', compareSummary.changed],
                    ['UNCHANGED', compareSummary.unchanged],
                  ].map(([label, value]) => (
                    <div key={label} className="border border-[#D8CFBF] bg-[#F7F4EC] p-3">
                      <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                        {label}
                      </p>

                      <p className="mt-2 font-serif text-2xl text-[#252B37]">
                        {value}
                      </p>
                    </div>
                  ))}
                </div>

                <div>
                  <h3 className="font-serif text-xl text-[#191A20]">
                    Technology Changes
                  </h3>

                  <p className="mt-1 text-[10px] text-[#817A70]">
                    Teknologi ditampilkan berdasarkan perbedaan dua hasil scan.
                  </p>
                </div>

                {comparisonRows.length === 0 ? (
                  <div className="border border-dashed border-[#D8CFBF] p-6 text-center text-xs text-[#817A70]">
                    Kedua pemeriksaan tidak memiliki data teknologi untuk dibandingkan.
                  </div>
                ) : (
                  <div className="overflow-x-auto border border-[#D8CFBF]">
                    <table className="w-full min-w-[650px] text-left">
                      <thead className="bg-[#F1EBDD]">
                        <tr>
                          <th className="px-4 py-3 text-[9px] font-bold tracking-wider text-[#817A70]">
                            TECHNOLOGY
                          </th>

                          <th className="px-4 py-3 text-[9px] font-bold tracking-wider text-[#817A70]">
                            EARLIER VERSION
                          </th>

                          <th className="px-4 py-3 text-[9px] font-bold tracking-wider text-[#817A70]">
                            LATEST VERSION
                          </th>

                          <th className="px-4 py-3 text-[9px] font-bold tracking-wider text-[#817A70]">
                            CHANGE
                          </th>
                        </tr>
                      </thead>

                      <tbody className="divide-y divide-[#D8CFBF]">
                        {comparisonRows.map((item) => (
                          <tr key={item.id}>
                            <td className="px-4 py-4">
                              <p className="text-xs font-semibold text-[#252B37]">
                                {item.name}
                              </p>

                              <p className="mt-1 text-[9px] text-[#817A70]">
                                {item.category.replaceAll('_', ' ')}
                              </p>
                            </td>

                            <td className="px-4 py-4 text-xs text-[#665F56]">
                              {item.firstVersion}
                            </td>

                            <td className="px-4 py-4 text-xs text-[#665F56]">
                              {item.secondVersion}
                            </td>

                            <td className="px-4 py-4">
                              <span
                                className={`inline-flex border px-2 py-1 text-[8px] font-bold tracking-wider ${
                                  item.change === 'added'
                                    ? 'border-[#82918B]/50 bg-[#82918B]/10 text-[#50665C]'
                                    : item.change === 'removed'
                                      ? 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]'
                                      : item.change === 'version_changed'
                                        ? 'border-[#B69A69]/50 bg-[#B69A69]/10 text-[#816D43]'
                                        : 'border-[#D8CFBF] text-[#817A70]'
                                }`}
                              >
                                {item.change === 'added'
                                  ? 'NEW'
                                  : item.change === 'removed'
                                    ? 'REMOVED'
                                    : item.change === 'version_changed'
                                      ? 'VERSION CHANGED'
                                      : 'UNCHANGED'}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}

                <p className="border-l-2 border-[#B69A69] bg-[#B69A69]/10 p-4 text-[10px] leading-5 text-[#665F56]">
                  Perubahan ini menunjukkan perbedaan hasil fingerprint yang
                  berhasil dideteksi. Tidak ditemukannya teknologi pada scan
                  terbaru tidak selalu berarti teknologi tersebut benar-benar
                  telah dihapus; website mungkin menyembunyikan fingerprint-nya.
                </p>
              </div>
            )}

            <div className="flex justify-end border-t border-[#D8CFBF] p-5 sm:px-7">
              <button
                type="button"
                onClick={closeCompare}
                className="simonta-angular-action border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] hover:bg-[#B69A69]/10"
              >
                CLOSE COMPARISON
              </button>
            </div>
          </section>
        </div>
      )}
    </div>
  )
}

export default ScanHistoryPage
