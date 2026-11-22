
import { useCallback, useEffect, useState } from 'react'
import api from '../../services/api'

const EMPTY_SUMMARY = {
  total_websites: 0,
  domain: {
    checked: 0,
    not_checked: 0,
    valid: 0,
    expiring_soon: 0,
    expired: 0,
  },
  ssl: {
    checked: 0,
    not_checked: 0,
    valid: 0,
    expiring_soon: 0,
    expired: 0,
    verification_failed: 0,
  },
}

const DOMAIN_STATUSES = [
  ['valid', 'Valid'],
  ['expiring_soon', 'Expiring Soon'],
  ['expired', 'Expired'],
  ['not_found', 'Not Found'],
  ['unavailable', 'Unavailable'],
  ['unsupported', 'Unsupported'],
  ['error', 'Error'],
]

const SSL_STATUSES = [
  ['valid', 'Valid'],
  ['expiring_soon', 'Expiring Soon'],
  ['expired', 'Expired'],
  ['not_yet_valid', 'Not Yet Valid'],
  ['verification_failed', 'Verification Failed'],
  ['unavailable', 'Unavailable'],
  ['unsupported', 'Unsupported'],
  ['error', 'Error'],
]

const STATUS_STYLES = {
  valid: 'border-[#82918B]/50 bg-[#82918B]/10 text-[#50665C]',
  expiring_soon: 'border-[#B69A69]/50 bg-[#B69A69]/10 text-[#816D43]',
  expired: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
  not_found: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
  verification_failed: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
  not_yet_valid: 'border-[#B69A69]/50 bg-[#B69A69]/10 text-[#816D43]',
  unavailable: 'border-[#D8CFBF] bg-[#D8CFBF]/20 text-[#77736C]',
  unsupported: 'border-[#D8CFBF] bg-[#D8CFBF]/20 text-[#77736C]',
  error: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
  unknown: 'border-[#D8CFBF] bg-[#D8CFBF]/20 text-[#77736C]',
  not_checked: 'border-[#D8CFBF] bg-[#D8CFBF]/20 text-[#77736C]',
}

function Icon({ name, size = 17 }) {
  const paths = {
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
      </>
    ),
    shield: (
      <>
        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
        <path d="m9 12 2 2 4-4" />
      </>
    ),
    refresh: (
      <>
        <path d="M20 7v5h-5M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    history: (
      <>
        <path d="M3 12a9 9 0 1 0 2.6-6.4L3 8" />
        <path d="M3 3v5h5M12 7v5l3 2" />
      </>
    ),
    arrow: <path d="m9 18 6-6-6-6" />,
    close: <path d="m18 6-12 12M6 6l12 12" />,
    calendar: (
      <>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M16 3v4M8 3v4M3 10h18" />
      </>
    ),
    alert: (
      <>
        <path d="M12 3 22 20H2L12 3Z" />
        <path d="M12 9v4m0 3h.01" />
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

function formatStatus(status) {
  if (!status) return 'NOT CHECKED'

  return String(status)
    .replaceAll('_', ' ')
    .toUpperCase()
}

function formatDaysRemaining(days) {
  if (days === null || days === undefined) {
    return 'Tidak tersedia'
  }

  const value = Number(days)

  if (!Number.isFinite(value)) return 'Tidak tersedia'
  if (value < 0) return `Terlambat ${Math.abs(value)} hari`
  if (value === 0) return 'Berakhir hari ini'

  return `${formatNumber(value)} hari lagi`
}

function StatusBadge({ status }) {
  const key = status || 'not_checked'

  return (
    <span
      className={`inline-flex items-center gap-2 whitespace-nowrap border px-2.5 py-1.5 text-[8px] font-bold tracking-wider ${
        STATUS_STYLES[key] || STATUS_STYLES.unknown
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {formatStatus(status)}
    </span>
  )
}

function StatCard({ label, value, description, icon, index }) {
  return (
    <article
      className="simonta-card-enter relative min-h-[155px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
      style={{ '--stagger-index': index }}
    >
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
        {String(index + 1).padStart(2, '0')}
      </span>

      <div className="relative flex items-center justify-between">
        <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
          {label}
        </p>

        <div className="grid size-9 place-items-center border border-[#D8CFBF] text-[#963746]">
          <Icon name={icon} size={17} />
        </div>
      </div>

      <h3 className="relative mt-5 font-serif text-4xl text-[#191A20]">
        {formatNumber(value)}
      </h3>

      <p className="relative mt-2 text-[10px] text-[#8B8479]">
        {description}
      </p>
    </article>
  )
}

function SelectFilter({ label, value, onChange, children }) {
  return (
    <div>
      <label className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]">
        {label}
      </label>

      <select
        value={value}
        onChange={onChange}
        className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none transition focus:border-[#B69A69]"
      >
        {children}
      </select>
    </div>
  )
}

function LoadingRows() {
  return (
    <div className="space-y-4 p-5">
      {[1, 2, 3].map((item) => (
        <div
          key={item}
          className="grid gap-4 border-b border-[#D8CFBF] pb-4 md:grid-cols-4"
        >
          <div className="simonta-skeleton h-4 w-3/4" />
          <div className="simonta-skeleton h-4 w-2/3" />
          <div className="simonta-skeleton h-4 w-1/2" />
          <div className="simonta-skeleton h-4 w-2/3" />
        </div>
      ))}
    </div>
  )
}

function Pagination({ currentPage, lastPage, onChange }) {
  if (lastPage <= 1) return null

  return (
    <div className="flex items-center justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4">
      <p className="text-[10px] text-[#817A70]">
        Page {currentPage} of {lastPage}
      </p>

      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={() => onChange(Math.max(1, currentPage - 1))}
          disabled={currentPage <= 1}
          className="border border-[#D8CFBF] px-3 py-2 text-xs text-[#665F56] disabled:opacity-30"
        >
          Previous
        </button>

        <button
          type="button"
          onClick={() => onChange(Math.min(lastPage, currentPage + 1))}
          disabled={currentPage >= lastPage}
          className="border border-[#D8CFBF] px-3 py-2 text-xs text-[#665F56] disabled:opacity-30"
        >
          Next
        </button>
      </div>
    </div>
  )
}

function HistorySection({ title, type, paginator, loading, onPageChange }) {
  const records = paginator?.data || []

  return (
    <section className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
      <div className="border-b border-[#D8CFBF] p-4">
        <p className="text-[9px] font-bold tracking-[0.16em] text-[#963746]">
          {type === 'domain' ? 'DOMAIN REGISTRATION' : 'SSL CERTIFICATE'}
        </p>
        <h3 className="mt-2 font-serif text-xl text-[#191A20]">{title}</h3>
      </div>

      {loading ? (
        <LoadingRows />
      ) : records.length === 0 ? (
        <div className="p-7 text-center text-xs text-[#817A70]">
          Belum ada riwayat pemeriksaan.
        </div>
      ) : (
        <div className="divide-y divide-[#D8CFBF]">
          {records.map((check) => (
            <article key={check.id} className="space-y-3 p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <StatusBadge status={check.status} />
                <span className="text-[9px] text-[#817A70]">
                  {formatDate(check.checked_at)}
                </span>
              </div>

              {type === 'domain' ? (
                <>
                  <div>
                    <p className="break-all text-xs font-semibold text-[#252B37]">
                      {check.domain || 'Domain tidak tersedia'}
                    </p>
                    <p className="mt-1 text-[10px] text-[#817A70]">
                      Registrar: {check.registrar || 'Tidak tersedia'}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <p className="text-[8px] tracking-wider text-[#817A70]">
                        EXPIRES AT
                      </p>
                      <p className="mt-1 text-[10px] text-[#252B37]">
                        {formatDate(check.expires_at)}
                      </p>
                    </div>

                    <div>
                      <p className="text-[8px] tracking-wider text-[#817A70]">
                        REMAINING
                      </p>
                      <p className="mt-1 text-[10px] text-[#963746]">
                        {formatDaysRemaining(check.days_remaining)}
                      </p>
                    </div>
                  </div>

                  {Array.isArray(check.nameservers) &&
                    check.nameservers.length > 0 && (
                      <div>
                        <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                          NAMESERVERS
                        </p>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                          {check.nameservers.map((server) => (
                            <span
                              key={server}
                              className="border border-[#D8CFBF] px-2 py-1 text-[9px] text-[#665F56]"
                            >
                              {server}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                </>
              ) : (
                <>
                  <div>
                    <p className="break-all text-xs font-semibold text-[#252B37]">
                      {check.hostname || 'Hostname tidak tersedia'}
                    </p>
                    <p className="mt-1 text-[10px] text-[#817A70]">
                      Issuer: {check.issuer || 'Tidak tersedia'}
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <p className="text-[8px] tracking-wider text-[#817A70]">
                        VALID UNTIL
                      </p>
                      <p className="mt-1 text-[10px] text-[#252B37]">
                        {formatDate(check.valid_to)}
                      </p>
                    </div>

                    <div>
                      <p className="text-[8px] tracking-wider text-[#817A70]">
                        REMAINING
                      </p>
                      <p className="mt-1 text-[10px] text-[#963746]">
                        {formatDaysRemaining(check.days_remaining)}
                      </p>
                    </div>
                  </div>

                  <p className="break-all text-[9px] text-[#817A70]">
                    Fingerprint: {check.fingerprint_sha256 || 'Tidak tersedia'}
                  </p>
                </>
              )}
            </article>
          ))}
        </div>
      )}

      {!loading && (
        <Pagination
          currentPage={paginator?.current_page || 1}
          lastPage={paginator?.last_page || 1}
          onChange={onPageChange}
        />
      )}
    </section>
  )
}

function HistoryModal({
  website,
  history,
  loading,
  error,
  onClose,
  onPageChange,
}) {
  if (!website) return null

  return (
    <div
      className="simonta-backdrop-enter fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#191A20]/70 p-3 backdrop-blur-[2px] sm:p-5"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) onClose()
      }}
    >
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="domain-history-title"
        className="simonta-card-enter my-auto w-full max-w-6xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
      >
        <div className="flex items-start justify-between gap-4 border-b border-[#D8CFBF] p-5 sm:p-7">
          <div className="min-w-0">
            <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
              DOMAIN & SSL INSPECTION RECORD
            </p>

            <h2
              id="domain-history-title"
              className="mt-2 font-serif text-2xl text-[#191A20] sm:text-3xl"
            >
              Inspection History.
            </h2>

            <p className="mt-2 truncate text-xs font-medium text-[#252B37]">
              {website.name || website.url}
            </p>

            <p className="mt-1 break-all text-[10px] text-[#817A70]">
              {website.url}
            </p>

            <p className="mt-1 text-[10px] text-[#817A70]">
              {website.opd?.name || 'OPD tidak tersedia'}
            </p>
          </div>

          <button
            type="button"
            onClick={onClose}
            aria-label="Tutup riwayat"
            className="grid size-9 shrink-0 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#963746] hover:text-[#963746]"
          >
            <Icon name="close" />
          </button>
        </div>

        {error && (
          <div className="m-5 border border-[#963746]/30 bg-[#963746]/5 p-3 text-xs text-[#963746]">
            {error}
          </div>
        )}

        <div className="grid gap-4 p-4 sm:p-6 lg:grid-cols-2">
          <HistorySection
            title="Domain Registration History"
            type="domain"
            paginator={history?.domain_checks}
            loading={loading}
            onPageChange={(page) => onPageChange('domain', page)}
          />

          <HistorySection
            title="SSL Certificate History"
            type="ssl"
            paginator={history?.ssl_checks}
            loading={loading}
            onPageChange={(page) => onPageChange('ssl', page)}
          />
        </div>

        <div className="flex justify-end border-t border-[#D8CFBF] p-5">
          <button
            type="button"
            onClick={onClose}
            className="border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10"
          >
            CLOSE HISTORY
          </button>
        </div>
      </section>
    </div>
  )
}

function DomainSslMonitoringPage() {
  const [records, setRecords] = useState([])
  const [summary, setSummary] = useState(EMPTY_SUMMARY)
  const [opds, setOpds] = useState([])

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const [domainStatus, setDomainStatus] = useState('all')
  const [sslStatus, setSslStatus] = useState('all')
  const [opdId, setOpdId] = useState('all')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [selectedWebsite, setSelectedWebsite] = useState(null)
  const [history, setHistory] = useState(null)
  const [historyLoading, setHistoryLoading] = useState(false)
  const [historyError, setHistoryError] = useState('')
  const [domainHistoryPage, setDomainHistoryPage] = useState(1)
  const [sslHistoryPage, setSslHistoryPage] = useState(1)

  const loadData = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')
    setSuccess('')

    try {
      const params = {
        per_page: 15,
        page: currentPage,
      }

      if (domainStatus !== 'all') {
        params.domain_status = domainStatus
      }

      if (sslStatus !== 'all') {
        params.ssl_status = sslStatus
      }

      if (opdId !== 'all') {
        params.opd_id = opdId
      }

      const [monitoringResponse, opdResponse] = await Promise.all([
        api.get('/api/domain-ssl-checks', { params }),
        api.get('/api/opds', {
          params: {
            per_page: 100,
          },
        }),
      ])

      const result = monitoringResponse.data || {}

      setRecords(Array.isArray(result.data) ? result.data : [])
      setSummary(result.summary || EMPTY_SUMMARY)
      setCurrentPage(result.meta?.current_page || 1)
      setLastPage(result.meta?.last_page || 1)
      setTotal(result.meta?.total || 0)
      setOpds(opdResponse.data?.data || [])
    } catch (requestError) {
      console.error('Domain SSL API error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Data Domain & SSL tidak dapat dimuat. Periksa koneksi backend SIMONTA.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [currentPage, domainStatus, sslStatus, opdId])

  useEffect(() => {
    loadData()
  }, [loadData])

  const loadHistory = async (
    website,
    domainPage = 1,
    sslPage = 1
  ) => {
    if (!website) return

    setHistoryLoading(true)
    setHistoryError('')

    try {
      const response = await api.get(
        `/api/websites/${website.id}/domain-ssl-history`,
        {
          params: {
            per_page: 5,
            domain_page: domainPage,
            ssl_page: sslPage,
          },
        }
      )

      setHistory(response.data)
      setDomainHistoryPage(domainPage)
      setSslHistoryPage(sslPage)
    } catch (requestError) {
      console.error('Domain SSL history error:', requestError)

      setHistoryError(
        requestError.response?.data?.message ||
        'Riwayat pemeriksaan tidak dapat dimuat.'
      )
    } finally {
      setHistoryLoading(false)
    }
  }

  const openHistory = async (item) => {
    const website = {
      ...item.website,
      opd: item.opd,
    }

    setSelectedWebsite(website)
    setHistory(null)
    setHistoryError('')
    setDomainHistoryPage(1)
    setSslHistoryPage(1)

    await loadHistory(website, 1, 1)
  }

  const closeHistory = () => {
    setSelectedWebsite(null)
    setHistory(null)
    setHistoryError('')
  }

  const changeHistoryPage = (type, page) => {
    if (type === 'domain') {
      loadHistory(selectedWebsite, page, sslHistoryPage)
    } else {
      loadHistory(selectedWebsite, domainHistoryPage, page)
    }
  }

  const resetFilters = () => {
    setDomainStatus('all')
    setSslStatus('all')
    setOpdId('all')
    setCurrentPage(1)
  }

  const domainStats = summary.domain || EMPTY_SUMMARY.domain
  const sslStats = summary.ssl || EMPTY_SUMMARY.ssl

  const statCards = [
    {
      label: 'REGISTERED WEBSITES',
      value: summary.total_websites,
      description: 'Website dalam hasil filter',
      icon: 'globe',
    },
    {
      label: 'DOMAIN EXPIRING',
      value: domainStats.expiring_soon,
      description: 'Domain mendekati kedaluwarsa',
      icon: 'calendar',
    },
    {
      label: 'DOMAIN EXPIRED',
      value: domainStats.expired,
      description: 'Domain yang kedaluwarsa',
      icon: 'alert',
    },
    {
      label: 'SSL EXPIRING',
      value: sslStats.expiring_soon,
      description: 'Sertifikat mendekati kedaluwarsa',
      icon: 'shield',
    },
    {
      label: 'SSL EXPIRED',
      value: sslStats.expired,
      description: 'Sertifikat yang kedaluwarsa',
      icon: 'alert',
    },
  ]

  return (
    <div className="simonta-page-enter space-y-8">
      {/* Page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[145px] leading-none text-[#E7DFD1] sm:text-[170px]">
          02
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />
            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / DOMAIN INTELLIGENCE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Domain & SSL{' '}
                <span className="italic text-[#963746]">Observatory.</span>
              </h1>

              <p className="mt-4 max-w-2xl text-sm leading-7 text-[#77736C]">
                Pusat pemantauan status registrasi domain dan validitas
                sertifikat SSL pada website organisasi perangkat daerah.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadData(true)}
              disabled={loading || refreshing}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:cursor-not-allowed disabled:opacity-50 lg:self-auto"
            >
              <span className={refreshing ? 'animate-spin' : ''}>
                <Icon name="refresh" size={15} />
              </span>
              {refreshing ? 'UPDATING' : 'REFRESH DATA'}
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              DOMAIN REGISTRATION
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              SSL CERTIFICATE
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              RDAP & TLS MONITORING
            </span>
          </div>
        </div>
      </section>

      {/* Alerts */}
      {success && (
        <div
          role="status"
          className="border border-[#82918B]/50 bg-[#82918B]/10 p-4 text-xs text-[#50665C]"
        >
          {success}
        </div>
      )}

      {error && (
        <div
          role="alert"
          className="flex flex-col justify-between gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 sm:flex-row sm:items-center"
        >
          <p className="text-xs leading-5 text-[#963746]">{error}</p>

          <button
            type="button"
            onClick={() => loadData()}
            className="shrink-0 text-[9px] font-bold tracking-wider text-[#963746] underline"
          >
            TRY AGAIN
          </button>
        </div>
      )}

      {/* Summary */}
      <section>
        <div className="mb-5">
          <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
            MONITORING SUMMARY
          </p>
          <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
            Domain & Certificate Overview
          </h2>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          {loading && !records.length
            ? [1, 2, 3, 4, 5].map((item) => (
                <div
                  key={item}
                  className="min-h-[155px] border border-[#D8CFBF] bg-[#F7F4EC] p-5"
                >
                  <div className="simonta-skeleton h-3 w-28" />
                  <div className="simonta-skeleton mt-7 h-10 w-20" />
                  <div className="simonta-skeleton mt-4 h-3 w-32" />
                </div>
              ))
            : statCards.map((card, index) => (
                <StatCard
                  key={card.label}
                  {...card}
                  index={index}
                />
              ))}
        </div>
      </section>

      {/* Domain and SSL detailed counters */}
      <section className="grid gap-5 lg:grid-cols-2">
        <article className="border border-[#D8CFBF] bg-[#F7F4EC]">
          <div className="flex items-center justify-between border-b border-[#D8CFBF] p-5">
            <div className="flex items-center gap-3">
              <div className="grid size-10 place-items-center border border-[#D8CFBF] text-[#963746]">
                <Icon name="globe" size={20} />
              </div>
              <div>
                <p className="text-[9px] font-bold tracking-wider text-[#963746]">
                  DOMAIN REGISTRATION
                </p>
                <h3 className="mt-1 font-serif text-xl text-[#191A20]">
                  Domain Status
                </h3>
              </div>
            </div>

            <span className="font-serif text-2xl text-[#963746]">
              {formatNumber(domainStats.checked)}
            </span>
          </div>

          <div className="grid grid-cols-2 gap-px bg-[#D8CFBF] sm:grid-cols-4">
            {[
              ['VALID', domainStats.valid],
              ['EXPIRING', domainStats.expiring_soon],
              ['EXPIRED', domainStats.expired],
              ['NOT CHECKED', domainStats.not_checked],
            ].map(([label, value]) => (
              <div key={label} className="bg-[#F7F4EC] p-4">
                <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                  {label}
                </p>
                <p className="mt-3 font-serif text-2xl text-[#252B37]">
                  {loading ? '—' : formatNumber(value)}
                </p>
              </div>
            ))}
          </div>
        </article>

        <article className="border border-[#D8CFBF] bg-[#F7F4EC]">
          <div className="flex items-center justify-between border-b border-[#D8CFBF] p-5">
            <div className="flex items-center gap-3">
              <div className="grid size-10 place-items-center border border-[#D8CFBF] text-[#963746]">
                <Icon name="shield" size={20} />
              </div>
              <div>
                <p className="text-[9px] font-bold tracking-wider text-[#963746]">
                  SSL CERTIFICATE
                </p>
                <h3 className="mt-1 font-serif text-xl text-[#191A20]">
                  Certificate Status
                </h3>
              </div>
            </div>

            <span className="font-serif text-2xl text-[#963746]">
              {formatNumber(sslStats.checked)}
            </span>
          </div>

          <div className="grid grid-cols-2 gap-px bg-[#D8CFBF] sm:grid-cols-3">
            {[
              ['VALID', sslStats.valid],
              ['EXPIRING', sslStats.expiring_soon],
              ['EXPIRED', sslStats.expired],
              ['FAILED', sslStats.verification_failed],
              ['NOT CHECKED', sslStats.not_checked],
            ].map(([label, value]) => (
              <div key={label} className="bg-[#F7F4EC] p-4">
                <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                  {label}
                </p>
                <p className="mt-3 font-serif text-2xl text-[#252B37]">
                  {loading ? '—' : formatNumber(value)}
                </p>
              </div>
            ))}
          </div>
        </article>
      </section>

      {/* Registry */}
      <section className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="border-b border-[#D8CFBF] p-5 sm:p-6">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            DOMAIN DATABASE / 01
          </p>

          <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
            Registered Domain & SSL
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            {formatNumber(total)} website sesuai dengan filter pemeriksaan.
          </p>

          <div className="mt-5 grid gap-3 sm:grid-cols-3">
            <SelectFilter
              label="DOMAIN STATUS"
              value={domainStatus}
              onChange={(event) => {
                setDomainStatus(event.target.value)
                setCurrentPage(1)
              }}
            >
              <option value="all">All domain statuses</option>
              {DOMAIN_STATUSES.map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </SelectFilter>

            <SelectFilter
              label="SSL STATUS"
              value={sslStatus}
              onChange={(event) => {
                setSslStatus(event.target.value)
                setCurrentPage(1)
              }}
            >
              <option value="all">All SSL statuses</option>
              {SSL_STATUSES.map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </SelectFilter>

            <SelectFilter
              label="ORGANIZATION"
              value={opdId}
              onChange={(event) => {
                setOpdId(event.target.value)
                setCurrentPage(1)
              }}
            >
              <option value="all">All organizations</option>
              {opds.map((opd) => (
                <option key={opd.id} value={String(opd.id)}>
                  {opd.name}
                </option>
              ))}
            </SelectFilter>
          </div>

          <div className="mt-4 flex justify-end">
            <button
              type="button"
              onClick={resetFilters}
              className="text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
            >
              RESET ALL FILTERS
            </button>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[1100px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                {[
                  'WEBSITE / OPD',
                  'DOMAIN STATUS',
                  'DOMAIN EXPIRATION',
                  'SSL STATUS',
                  'SSL EXPIRATION',
                  'HISTORY',
                ].map((heading) => (
                  <th
                    key={heading}
                    className="px-4 py-4 text-[8px] font-bold tracking-[0.13em] text-[#817A70]"
                  >
                    {heading}
                  </th>
                ))}
              </tr>
            </thead>

            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? (
                <tr>
                  <td colSpan="6">
                    <LoadingRows />
                  </td>
                </tr>
              ) : records.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-5 py-16 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="globe" size={24} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Monitoring Records
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      Belum ada website yang sesuai dengan filter atau pemeriksaan belum tersedia.
                    </p>
                  </td>
                </tr>
              ) : (
                records.map((item) => {
                  const website = item.website
                  const domain = item.domain_check
                  const ssl = item.ssl_check

                  return (
                    <tr
                      key={website.id}
                      className="transition duration-200 hover:bg-[#F1EBDD]"
                    >
                      <td className="max-w-[250px] px-4 py-5">
                        <p className="text-xs font-semibold text-[#252B37]">
                          {website.name || 'Unnamed Website'}
                        </p>

                        <a
                          href={website.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="mt-1 block truncate text-[9px] text-[#963746] hover:underline"
                        >
                          {website.url}
                        </a>

                        <p className="mt-2 text-[9px] text-[#817A70]">
                          {item.opd?.name || 'OPD tidak tersedia'}
                        </p>
                      </td>

                      <td className="px-4 py-5">
                        <StatusBadge status={domain?.status} />
                        {domain && (
                          <p className="mt-2 text-[9px] text-[#817A70]">
                            {formatDate(domain.checked_at)}
                          </p>
                        )}
                      </td>

                      <td className="px-4 py-5">
                        <p className="text-[10px] text-[#252B37]">
                          {formatDate(domain?.expires_at)}
                        </p>
                        <p className="mt-1 text-[9px] text-[#963746]">
                          {formatDaysRemaining(domain?.days_remaining)}
                        </p>
                      </td>

                      <td className="px-4 py-5">
                        <StatusBadge status={ssl?.status} />
                        {ssl && (
                          <p className="mt-2 text-[9px] text-[#817A70]">
                            {formatDate(ssl.checked_at)}
                          </p>
                        )}
                      </td>

                      <td className="px-4 py-5">
                        <p className="text-[10px] text-[#252B37]">
                          {formatDate(ssl?.valid_to)}
                        </p>
                        <p className="mt-1 text-[9px] text-[#963746]">
                          {formatDaysRemaining(ssl?.days_remaining)}
                        </p>
                      </td>

                      <td className="px-4 py-5">
                        <button
                          type="button"
                          onClick={() => openHistory(item)}
                          className="inline-flex items-center gap-2 border border-[#B69A69] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 hover:text-[#963746]"
                        >
                          <Icon name="history" size={13} />
                          HISTORY
                        </button>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>

        {!loading && total > 0 && (
          <div className="flex items-center justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4">
            <p className="text-[10px] text-[#817A70]">
              Page {currentPage} of {lastPage} · {total} websites
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                className="border border-[#D8CFBF] px-3 py-2 text-xs text-[#665F56] disabled:opacity-30"
              >
                Previous
              </button>

              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(lastPage, page + 1))}
                disabled={currentPage >= lastPage}
                className="border border-[#D8CFBF] px-3 py-2 text-xs text-[#665F56] disabled:opacity-30"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>
      {/* Monitoring information */}
      <section className="border-l-2 border-[#B69A69] bg-[#F1EBDD] p-5">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 text-[#963746]">
            <Icon name="alert" size={18} />
          </span>

          <div>
            <h3 className="text-[10px] font-bold tracking-wider text-[#252B37]">
              MONITORING INFORMATION
            </h3>

            <p className="mt-2 text-[10px] leading-6 text-[#77736C]">
              Status yang ditampilkan berasal dari hasil pemeriksaan domain
              melalui RDAP dan validasi sertifikat SSL melalui TLS. Website yang
              belum memiliki hasil pemeriksaan akan ditandai sebagai NOT CHECKED.
              Informasi ini mengikuti waktu pemeriksaan terakhir yang tersimpan
              di SIMONTA.
            </p>
          </div>
        </div>
      </section>

      {/* Domain & SSL History Modal */}
      <HistoryModal
        website={selectedWebsite}
        history={history}
        loading={historyLoading}
        error={historyError}
        onClose={closeHistory}
        onPageChange={changeHistoryPage}
      />

    </div>
  )
}

export default DomainSslMonitoringPage
