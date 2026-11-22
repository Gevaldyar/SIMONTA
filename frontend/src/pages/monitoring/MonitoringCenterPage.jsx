import { useCallback, useEffect, useMemo, useState } from 'react'

import api from '../../services/api'

const INITIAL_STATS = {
  queued_scans: 0,
  running_scans: 0,
  completed_scans: 0,
  failed_scans: 0,
}

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    scan: (
      <>
        <path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3" />
        <path d="M7 12h10M12 7v10" />
      </>
    ),
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m16 16 4 4" />
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
    server: (
      <>
        <rect x="3" y="4" width="18" height="7" rx="1" />
        <rect x="3" y="13" width="18" height="7" rx="1" />
        <path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" />
      </>
    ),
    arrow: <path d="m7 17 10-10M8 7h9v9" />,
    eye: (
      <>
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
      </>
    ),
    searchX: (
      <>
        <circle cx="10" cy="10" r="7" />
        <path d="m15 15 5 5M7.5 7.5l5 5m0-5-5 5" />
      </>
    ),
    chevronLeft: <path d="m15 18-6-6 6-6" />,
    chevronRight: <path d="m9 18 6-6-6-6" />,
    close: <path d="m18 6-12 12M6 6l12 12" />,
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
      </>
    ),
    layers: (
      <>
        <path d="m12 3 9 5-9 5-9-5 9-5Z" />
        <path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
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
    style: 'border-[#D8CFBF] text-[#77736C]',
  }

  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${selected.style}`}
    >
      {status === 'running' ? (
        <span className="size-1.5 animate-pulse rounded-full bg-current" />
      ) : (
        <span className="size-1.5 rounded-full bg-current" />
      )}

      {selected.label}
    </span>
  )
}

function FlagBadge({ enabled, onLabel = 'ON', offLabel = 'OFF' }) {
  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${
        enabled
          ? 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]'
          : 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]'
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {enabled ? onLabel : offLabel}
    </span>
  )
}

function StatCard({ label, value, description, icon, color, index }) {
  return (
    <article
      className="simonta-card-enter simonta-monitoring-stat relative min-h-[145px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
      style={{ '--stagger-index': index }}
    >
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
        {String(index + 1).padStart(2, '0')}
      </span>

      <div className="relative flex items-center justify-between">
        <p className="text-[9px] font-bold tracking-[0.16em] text-[#77736C]">
          {label}
        </p>

        <div className={`grid size-9 place-items-center border border-[#D8CFBF] ${color}`}>
          <Icon name={icon} />
        </div>
      </div>

      <h3 className="relative mt-5 font-serif text-4xl text-[#191A20]">
        {formatNumber(value)}
      </h3>

      <p className="relative mt-1 text-[10px] text-[#817A70]">
        {description}
      </p>
    </article>
  )
}

function MonitoringCenterPage() {
  const [websites, setWebsites] = useState([])
  const [scans, setScans] = useState([])
  const [stats, setStats] = useState(INITIAL_STATS)

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [websiteStatus, setWebsiteStatus] = useState('1')

  const [scanStatus, setScanStatus] = useState('all')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [totalWebsites, setTotalWebsites] = useState(0)

  const [scanningId, setScanningId] = useState(null)

  const [detailOpen, setDetailOpen] = useState(false)
  const [selectedScan, setSelectedScan] = useState(null)
  const [detailLoading, setDetailLoading] = useState(false)

  const hasActiveScans = stats.queued_scans > 0 || stats.running_scans > 0

  const pendingWebsiteIds = useMemo(() => {
    return new Set(
      scans
        .filter((scan) => ['queued', 'running'].includes(scan.status))
        .map((scan) => scan.website_id)
    )
  }, [scans])

  const loadMonitoringData = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const websiteParams = {
        per_page: 15,
        page: currentPage,
        search: search || undefined,
      }

      if (websiteStatus !== 'all') {
        websiteParams.is_active = websiteStatus
      }

      const scanParams = {
        per_page: 15,
      }

      if (scanStatus !== 'all') {
        scanParams.status = scanStatus
      }

      const [summaryResponse, websiteResponse, scanResponse] =
        await Promise.all([
          api.get('/api/dashboard/summary'),
          api.get('/api/websites', { params: websiteParams }),
          api.get('/api/scans', { params: scanParams }),
        ])

      const summary = summaryResponse.data?.data || {}
      const websiteResult = websiteResponse.data || {}
      const scanResult = scanResponse.data || {}

      setStats(summary.statistics || INITIAL_STATS)
      setWebsites(websiteResult.data || [])
      setTotalWebsites(websiteResult.total || 0)
      setLastPage(websiteResult.last_page || 1)
      setScans(scanResult.data || [])
    } catch (requestError) {
      console.error('Monitoring API error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Informasi monitoring gagal dimuat. Periksa koneksi backend.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [currentPage, search, websiteStatus, scanStatus])

  useEffect(() => {
    loadMonitoringData()
  }, [loadMonitoringData])

  // Polling lebih sering jika masih ada scan queued atau running.
  useEffect(() => {
    const intervalDuration = hasActiveScans ? 5000 : 30000

    const interval = window.setInterval(() => {
      loadMonitoringData(true)
    }, intervalDuration)

    return () => window.clearInterval(interval)
  }, [loadMonitoringData, hasActiveScans])

  const handleSearch = (event) => {
    event.preventDefault()
    setCurrentPage(1)
    setSearch(searchInput.trim())
  }

  const handleManualScan = async (website) => {
    setScanningId(website.id)
    setError('')
    setNotice('')

    try {
      const response = await api.post(
        `/api/websites/${website.id}/scans`
      )

      const newScan = response.data?.data

      setNotice(
        response.data?.message ||
        `Pemeriksaan ${website.name || website.url} berhasil dimasukkan ke antrean.`
      )

      if (newScan) {
        setScans((previous) => [
          newScan,
          ...previous.filter((item) => item.id !== newScan.id),
        ].slice(0, 15))
      }

      await loadMonitoringData(true)
    } catch (requestError) {
      console.error('Manual scan error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Pemeriksaan gagal dimasukkan ke antrean.'
      )

      if (requestError.response?.status === 409) {
        await loadMonitoringData(true)
      }
    } finally {
      setScanningId(null)
    }
  }

  const openScanDetail = async (scan) => {
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

  const closeDetail = () => {
    setDetailOpen(false)
    setSelectedScan(null)
  }

  const statCards = [
    {
      label: 'QUEUED SCANS',
      value: stats.queued_scans,
      description: 'Menunggu diproses Queue Worker',
      icon: 'clock',
      color: 'text-[#B69A69]',
    },
    {
      label: 'RUNNING SCANS',
      value: stats.running_scans,
      description: 'Pemeriksaan sedang berlangsung',
      icon: 'scan',
      color: 'text-[#82918B]',
    },
    {
      label: 'COMPLETED SCANS',
      value: stats.completed_scans,
      description: 'Pemeriksaan selesai',
      icon: 'check',
      color: 'text-[#82918B]',
    },
    {
      label: 'FAILED SCANS',
      value: stats.failed_scans,
      description: 'Pemeriksaan mengalami kegagalan',
      icon: 'alert',
      color: 'text-[#963746]',
    },
  ]

  return (
    <div className="simonta-page-enter simonta-monitoring-page space-y-8">

      {/* Page heading */}
      <section className="simonta-monitoring-hero relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          01
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / INTELLIGENCE CONTROL
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Monitoring <span className="italic text-[#963746]">Center.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Pusat kendali pemeriksaan website OPD, pemantauan
                antrean, dan pengawasan aktivitas identifikasi teknologi.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadMonitoringData(true)}
              disabled={refreshing || loading}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto"
            >
              <Icon
                name="refresh"
                size={15}
                className={refreshing ? 'animate-spin' : ''}
              />
              REFRESH MONITORING
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              MANUAL INSPECTION
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              AUTOMATIC MONITORING
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              AUTO REFRESH ACTIVE
            </span>
          </div>
        </div>
      </section>

      {/* Notifications */}
      {notice && (
        <div
          role="status"
          className="flex items-center gap-3 border border-[#82918B]/50 bg-[#82918B]/10 p-4 text-xs text-[#50665C]"
        >
          <Icon name="check" />
          {notice}
        </div>
      )}

      {error && (
        <div
          role="alert"
          className="flex items-center justify-between gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs text-[#963746]"
        >
          <span>{error}</span>
          <button
            type="button"
            onClick={() => loadMonitoringData(true)}
            className="shrink-0 font-bold underline"
          >
            RETRY
          </button>
        </div>
      )}

      {/* Scan summary */}
      <section className="simonta-monitoring-summary">
        <div className="simonta-monitoring-section-heading mb-4">
          <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
            MONITORING PERFORMANCE
          </p>

          <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
            Inspection Status
          </h2>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {statCards.map((card, index) => (
            <StatCard
              key={card.label}
              {...card}
              index={index}
            />
          ))}
        </div>
      </section>

      {/* Website inspection control */}
      <section className="simonta-monitoring-panel simonta-monitoring-inspection-panel border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="simonta-monitoring-panel-header border-b border-[#D8CFBF] p-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            INSPECTION CONTROL / 01
          </p>

          <div className="mt-2 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
            <div>
              <h2 className="font-serif text-2xl text-[#191A20]">
                Website Inspection
              </h2>

              <p className="mt-1 text-[10px] text-[#817A70]">
                Jalankan pemeriksaan manual pada website yang sudah terdaftar.
              </p>
            </div>

            <form
              onSubmit={handleSearch}
              className="flex min-w-0 sm:w-72"
            >
              <input
                type="search"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search website..."
                aria-label="Cari website"
                className="min-w-0 flex-1 border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs outline-none focus:border-[#B69A69]"
              />

              <button
                type="submit"
                aria-label="Cari website"
                className="simonta-icon-action grid size-10 shrink-0 place-items-center bg-[#252B37] text-white transition hover:bg-[#963746]"
              >
                <Icon name="search" />
              </button>
            </form>
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              <label
                htmlFor="monitor-website-status"
                className="text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                STATUS
              </label>

              <select
                id="monitor-website-status"
                value={websiteStatus}
                onChange={(event) => {
                  setWebsiteStatus(event.target.value)
                  setCurrentPage(1)
                }}
                className="border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
              >
                <option value="1">Active websites</option>
                <option value="all">All websites</option>
                <option value="0">Inactive websites</option>
              </select>
            </div>

            <p className="text-[10px] text-[#817A70]">
              {totalWebsites} websites registered
            </p>
          </div>
        </div>

        <div className="simonta-monitoring-table-wrap hidden overflow-x-auto md:block">
          <table className="simonta-monitoring-table w-full min-w-[900px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  WEBSITE
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  OPD
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  MONITORING
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-wider text-[#817A70]">
                  INTERVAL
                </th>

                <th className="px-5 py-4 text-right text-[9px] font-bold tracking-wider text-[#817A70]">
                  INSPECTION
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? (
                [1, 2, 3].map((item) => (
                  <tr key={item}>
                    <td colSpan="5" className="px-5 py-6">
                      <div className="simonta-skeleton h-4 w-2/3" />
                    </td>
                  </tr>
                ))
              ) : websites.length === 0 ? (
                <tr>
                  <td colSpan="5" className="px-5 py-14 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="globe" size={24} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Website Available
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      Tidak ada website yang sesuai dengan filter.
                    </p>
                  </td>
                </tr>
              ) : (
                websites.map((website) => {
                  const pending = pendingWebsiteIds.has(website.id)

                  const disabled =
                    !website.is_active ||
                    pending ||
                    scanningId === website.id

                  return (
                    <tr
                      key={website.id}
                      className="transition duration-200 hover:bg-[#F1EBDD]"
                    >
                      <td className="max-w-[270px] px-5 py-5">
                        <p className="truncate text-sm font-medium text-[#252B37]">
                          {website.name || 'Unnamed Website'}
                        </p>

                        <p className="mt-1 truncate text-[10px] text-[#963746]">
                          {website.url}
                        </p>
                      </td>

                      <td className="px-5 py-5">
                        <p className="text-xs text-[#665F56]">
                          {website.opd?.name || 'Unknown OPD'}
                        </p>
                      </td>

                      <td className="px-5 py-5">
                        <FlagBadge
  enabled={website.monitoring_enabled}
  onLabel="ON"
  offLabel="OFF"
/>
                        <p className="mt-1 text-[9px] text-[#817A70]">
                          {website.monitoring_enabled ? 'Automatic ON' : 'Automatic OFF'}
                        </p>
                      </td>

                      <td className="px-5 py-5">
                        <p className="font-serif text-lg text-[#252B37]">
                          {website.monitoring_interval_minutes} {Number(website.monitoring_interval_minutes) === 1 ? 'minute' : 'minutes'}
                        </p>
                      </td>

                      <td className="px-5 py-5 text-right">
                        <button
                          type="button"
                          onClick={() => handleManualScan(website)}
                          disabled={disabled}
                          className="simonta-angular-action inline-flex items-center justify-center gap-2 bg-[#963746] px-4 py-2.5 text-[9px] font-bold tracking-wider text-[#EEE9DD] transition hover:-translate-y-0.5 hover:bg-[#762B37] disabled:cursor-not-allowed disabled:bg-[#A9A39A] disabled:opacity-60"
                        >
                          {scanningId === website.id ? (
                            <span className="size-3 animate-spin border border-white border-t-transparent" />
                          ) : (
                            <Icon name="scan" size={14} />
                          )}

                          {pending
                            ? 'IN QUEUE'
                            : scanningId === website.id
                              ? 'SENDING...'
                              : 'RUN SCAN'}
                        </button>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Mobile cards */}
        <div className="simonta-monitoring-mobile-list divide-y divide-[#D8CFBF] md:hidden">
          {loading ? (
            [1, 2, 3].map((item) => (
              <div key={item} className="p-5">
                <div className="simonta-skeleton h-4 w-2/3" />
                <div className="simonta-skeleton mt-3 h-3 w-1/2" />
              </div>
            ))
          ) : (
            websites.map((website) => {
              const pending = pendingWebsiteIds.has(website.id)

              const disabled =
                !website.is_active ||
                pending ||
                scanningId === website.id

              return (
                <article key={website.id} className="space-y-4 p-5">
                  <div>
                    <h3 className="font-medium text-sm text-[#252B37]">
                      {website.name || 'Unnamed Website'}
                    </h3>

                    <p className="mt-1 break-all text-[10px] text-[#963746]">
                      {website.url}
                    </p>

                    <p className="mt-2 text-[10px] text-[#817A70]">
                      {website.opd?.name || 'Unknown OPD'}
                    </p>
                  </div>

                  <div className="flex items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                      <FlagBadge
  enabled={website.is_active}
  onLabel="ACTIVE"
  offLabel="INACTIVE"
/>

                      {pending && (
                        <StatusBadge status="queued" />
                      )}
                    </div>

                    <p className="text-[10px] text-[#817A70]">
                      {website.monitoring_interval_minutes} {Number(website.monitoring_interval_minutes) === 1 ? 'minute' : 'minutes'} interval
                    </p>
                  </div>

                  <button
                    type="button"
                    onClick={() => handleManualScan(website)}
                    disabled={disabled}
                    className="simonta-angular-action inline-flex w-full items-center justify-center gap-2 bg-[#963746] px-4 py-3 text-[10px] font-bold tracking-wider text-white disabled:bg-[#A9A39A]"
                  >
                    <Icon name="scan" size={15} />
                    {pending ? 'SCAN IN QUEUE' : 'RUN MANUAL SCAN'}
                  </button>
                </article>
              )
            })
          )}

          {!loading && websites.length === 0 && (
            <div className="p-8 text-center text-xs text-[#817A70]">
              Tidak ada website yang dapat ditampilkan.
            </div>
          )}
        </div>

        {/* Pagination */}
        {!loading && totalWebsites > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Page {currentPage} of {lastPage} · {totalWebsites} websites
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

              <span className="min-w-8 text-center font-serif text-sm">
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

      {/* Recent scan history */}
      <section className="simonta-monitoring-panel simonta-monitoring-history-panel border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="simonta-monitoring-panel-header flex flex-col justify-between gap-3 border-b border-[#D8CFBF] p-5 sm:flex-row sm:items-center">
          <div>
            <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
              INSPECTION LOG / 02
            </p>

            <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
              Recent Scan Activity
            </h2>
          </div>

          <div className="flex items-center gap-2">
            <label
              htmlFor="monitor-scan-status"
              className="text-[9px] font-bold tracking-wider text-[#817A70]"
            >
              STATUS
            </label>

            <select
              id="monitor-scan-status"
              value={scanStatus}
              onChange={(event) => setScanStatus(event.target.value)}
              className="border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
            >
              <option value="all">All scans</option>
              <option value="queued">Queued</option>
              <option value="running">Running</option>
              <option value="completed">Completed</option>
              <option value="failed">Failed</option>
            </select>
          </div>
        </div>

        {loading && !scans.length ? (
          <div className="space-y-4 p-5">
            {[1, 2, 3].map((item) => (
              <div key={item} className="border-b border-[#D8CFBF] pb-4">
                <div className="simonta-skeleton h-3 w-20" />
                <div className="simonta-skeleton mt-3 h-4 w-2/3" />
              </div>
            ))}
          </div>
        ) : scans.length === 0 ? (
          <div className="flex min-h-[220px] flex-col items-center justify-center p-6 text-center">
            <div className="grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
              <Icon name="clock" size={23} />
            </div>

            <h3 className="mt-4 font-serif text-xl text-[#191A20]">
              No Inspection Records
            </h3>

            <p className="mt-2 max-w-sm text-xs leading-6 text-[#817A70]">
              Belum ada riwayat pemeriksaan untuk filter yang dipilih.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-[#D8CFBF]">
            {scans.map((scan, index) => (
              <div
                key={scan.id}
                className="simonta-monitoring-scan-row group flex flex-col justify-between gap-4 px-5 py-4 transition duration-200 hover:bg-[#F1EBDD] sm:flex-row sm:items-center"
                style={{ animationDelay: `${index * 40}ms` }}
              >
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={scan.status} />

                    <span className="text-[9px] tracking-wider text-[#948B7D]">
                      SCAN #{String(scan.id).padStart(5, '0')}
                    </span>
                  </div>

                  <p className="mt-2 truncate text-sm font-medium text-[#252B37]">
                    {scan.website?.name || scan.website?.url || 'Website tidak diketahui'}
                  </p>

                  <p className="mt-1 text-[10px] text-[#817A70]">
                    {scan.website?.opd?.name || 'OPD tidak diketahui'}
                  </p>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4 sm:justify-end">
                  <div className="text-[10px] text-[#817A70]">
                    {formatDate(
                      scan.completed_at ||
                      scan.started_at ||
                      scan.created_at
                    )}
                  </div>

                  <div className="simonta-monitoring-scan-actions flex items-center gap-3">
                    <span className="text-[9px] text-[#665F56]">
                      {scan.http_status_code
                        ? `HTTP ${scan.http_status_code}`
                        : 'HTTP —'}
                    </span>

                    <span className="text-[9px] text-[#665F56]">
                      {scan.response_time_ms != null
                        ? `${formatNumber(scan.response_time_ms)} ms`
                        : '—'}
                    </span>

                    <button
                      type="button"
                      onClick={() => openScanDetail(scan)}
                      title="View scan detail"
                      aria-label={`Detail scan ${scan.id}`}
                      className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 hover:text-[#963746]"
                    >
                      <Icon name="eye" size={13} />
                      DETAIL
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Auto refresh note */}
      <div className="simonta-monitoring-refresh-note flex flex-wrap items-center justify-between gap-3 border-t border-[#D8CFBF] pt-4">
        <div className="flex items-center gap-2">
          <span className="size-2 rounded-full bg-[#82918B]" />
          <p className="text-[9px] tracking-wider text-[#50665C]">
            AUTO REFRESH ENABLED
          </p>
        </div>

        <p className="text-[9px] text-[#817A70]">
          {hasActiveScans
            ? 'Updating every 5 seconds while scans are active.'
            : 'Updating every 30 seconds.'}
        </p>
      </div>

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
            aria-labelledby="scan-detail-title"
            className="simonta-card-enter simonta-monitoring-detail-modal my-auto w-full max-w-3xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
          >
            <div className="simonta-monitoring-detail-header flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  INSPECTION RECORD
                </p>

                <h2
                  id="scan-detail-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  Scan Detail.
                </h2>

                <p className="mt-2 text-[10px] text-[#817A70]">
                  Detailed result and technology fingerprint.
                </p>
              </div>

              <button
                type="button"
                onClick={closeDetail}
                aria-label="Close scan detail"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#963746] hover:text-[#963746]"
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

                <div className="simonta-monitoring-detail-website border-y border-[#D8CFBF] py-4">
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

                <div className="simonta-monitoring-detail-metrics grid grid-cols-2 gap-3 sm:grid-cols-4">
                  {[
                    {
                      label: 'HTTP STATUS',
                      value: selectedScan.http_status_code || '—',
                    },
                    {
                      label: 'RESPONSE',
                      value: selectedScan.response_time_ms != null
                        ? `${formatNumber(selectedScan.response_time_ms)} ms`
                        : '—',
                    },
                    {
                      label: 'DETECTED',
                      value: selectedScan.scan_technologies?.length || 0,
                    },
                    {
                      label: 'REDIRECTS',
                      value: selectedScan.metadata?.redirect_count ?? '—',
                    },
                  ].map((item) => (
                    <div
                      key={item.label}
                      className="simonta-monitoring-detail-metric border border-[#D8CFBF] bg-[#F7F4EC] p-3"
                    >
                      <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                        {item.label}
                      </p>

                      <p className="mt-2 font-serif text-xl text-[#252B37]">
                        {item.value}
                      </p>
                    </div>
                  ))}
                </div>

                <div className="simonta-monitoring-detail-timestamps grid gap-3 sm:grid-cols-2">
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

                <div className="simonta-monitoring-detail-technologies border-t border-[#D8CFBF] pt-5">
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="font-serif text-xl text-[#191A20]">
                      Detected Technologies
                    </h3>

                    <span className="text-[9px] tracking-wider text-[#817A70]">
                      {selectedScan.scan_technologies?.length || 0} RECORDS
                    </span>
                  </div>

                  {!selectedScan.scan_technologies?.length ? (
                    <p className="mt-4 border border-dashed border-[#D8CFBF] p-5 text-center text-xs text-[#817A70]">
                      Tidak ada teknologi yang teridentifikasi pada pemeriksaan ini.
                    </p>
                  ) : (
                    <div className="mt-4 space-y-2">
                      {selectedScan.scan_technologies.map((item) => (
                        <div
                          key={item.id}
                          className="simonta-monitoring-technology-row flex flex-col justify-between gap-2 border border-[#D8CFBF] bg-[#F7F4EC] p-4 sm:flex-row sm:items-center"
                        >
                          <div>
                            <p className="text-sm font-semibold text-[#252B37]">
                              {item.technology?.name || 'Unknown Technology'}
                            </p>

                            <p className="mt-1 text-[9px] text-[#817A70]">
                              {item.technology?.category || 'Uncategorized'}
                            </p>

                            {item.signature && (
                              <p className="mt-1 text-[9px] text-[#963746]">
                                {item.signature.name}
                              </p>
                            )}
                          </div>

                          <div className="flex items-center gap-4">
                            <div>
                              <p className="text-[8px] tracking-wider text-[#817A70]">
                                VERSION
                              </p>

                              <p className="mt-1 text-xs font-medium text-[#252B37]">
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

                {selectedScan.metadata?.headers && (
                  <details className="simonta-monitoring-response-details border border-[#D8CFBF]">
                    <summary className="cursor-pointer px-4 py-3 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#F7F4EC]">
                      VIEW RESPONSE METADATA
                    </summary>

                    <pre className="overflow-x-auto border-t border-[#D8CFBF] bg-[#F7F4EC] p-4 text-[10px] leading-5 text-[#665F56]">
                      {JSON.stringify(selectedScan.metadata, null, 2)}
                    </pre>
                  </details>
                )}
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

export default MonitoringCenterPage
