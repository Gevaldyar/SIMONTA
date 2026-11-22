
import { useCallback, useEffect, useMemo, useState } from 'react'
import api from '../../services/api'

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    shield: (
      <>
        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
        <path d="m9 12 2 2 4-4" />
      </>
    ),
    alert: (
      <>
        <path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3l-7.9-14.1a2 2 0 0 0-3.4 0Z" />
        <path d="M12 9v4M12 17h.01" />
      </>
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m16 16 4 4" />
      </>
    ),
    down: <path d="m6 9 6 6 6-6" />,
    left: <path d="m15 18-6-6 6-6" />,
    right: <path d="m9 18 6-6-6-6" />,
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

  if (Number.isNaN(date.getTime())) {
    return 'Belum tersedia'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function unwrapCollection(payload) {
  const root = payload?.data ?? payload

  if (Array.isArray(root)) {
    return root
  }

  if (Array.isArray(root?.data)) {
    return root.data
  }

  if (Array.isArray(root?.data?.data)) {
    return root.data.data
  }

  return []
}

const STATUS_CONFIG = {
  strong: {
    label: 'Kuat',
    shortLabel: 'STRONG',
    description: 'Konfigurasi header memenuhi sebagian besar kriteria audit.',
    badge: 'border-[#82918B]/60 bg-[#82918B]/10 text-[#50665C]',
    panel: 'border-[#82918B]/40 bg-[#82918B]/5',
    accent: 'text-[#50665C]',
    bar: '#82918B',
    icon: 'shield',
  },

  moderate: {
    label: 'Cukup',
    shortLabel: 'MODERATE',
    description: 'Konfigurasi header masih memiliki beberapa kekurangan.',
    badge: 'border-[#B69A69]/60 bg-[#B69A69]/10 text-[#816D43]',
    panel: 'border-[#B69A69]/40 bg-[#B69A69]/5',
    accent: 'text-[#816D43]',
    bar: '#B69A69',
    icon: 'alert',
  },

  needs_review: {
    label: 'Perlu Ditinjau',
    shortLabel: 'NEEDS REVIEW',
    description: 'Terdapat konfigurasi atau konflik header yang perlu diperiksa.',
    badge: 'border-[#B69A69]/70 bg-[#B69A69]/15 text-[#816D43]',
    panel: 'border-[#B69A69]/50 bg-[#B69A69]/10',
    accent: 'text-[#816D43]',
    bar: '#B69A69',
    icon: 'alert',
  },

  weak: {
    label: 'Lemah',
    shortLabel: 'WEAK',
    description: 'Banyak kriteria keamanan header belum terpenuhi.',
    badge: 'border-[#963746]/50 bg-[#963746]/10 text-[#963746]',
    panel: 'border-[#963746]/40 bg-[#963746]/5',
    accent: 'text-[#963746]',
    bar: '#963746',
    icon: 'alert',
  },

  unknown: {
    label: 'Belum Dinilai',
    shortLabel: 'UNKNOWN',
    description: 'Informasi hasil audit belum tersedia.',
    badge: 'border-[#D8CFBF] bg-[#EEE9DD] text-[#77736C]',
    panel: 'border-[#D8CFBF] bg-[#F1EBDD]',
    accent: 'text-[#77736C]',
    bar: '#A9A8A3',
    icon: 'clock',
  },
}

const HEADER_LABELS = {
  'content-security-policy': 'Content Security Policy',
  'content-security-policy-report-only': 'CSP Report Only',
  'strict-transport-security': 'HTTP Strict Transport Security',
  'x-content-type-options': 'X-Content-Type-Options',
  'x-frame-options': 'X-Frame-Options',
  'referrer-policy': 'Referrer Policy',
  'permissions-policy': 'Permissions Policy',
  'cross-origin-opener-policy': 'Cross-Origin Opener Policy',
  'cross-origin-embedder-policy': 'Cross-Origin Embedder Policy',
  'cross-origin-resource-policy': 'Cross-Origin Resource Policy',
}

function StatusBadge({ status }) {
  const config = STATUS_CONFIG[status] || STATUS_CONFIG.unknown

  return (
    <span
      className={`inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-[9px] font-bold tracking-wider ${config.badge}`}
    >
      <Icon name={config.icon} size={12} />
      {config.shortLabel}
    </span>
  )
}

function MetricCard({
  index,
  label,
  value,
  description,
  tone = 'default',
  loading,
}) {
  const accent =
    tone === 'danger'
      ? 'text-[#963746]'
      : tone === 'warning'
        ? 'text-[#816D43]'
        : tone === 'success'
          ? 'text-[#50665C]'
          : 'text-[#191A20]'

  return (
    <article className="relative min-h-[138px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]">
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
        {index}
      </span>

      <div className="relative">
        <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
          {label}
        </p>

        <h2 className={`mt-5 font-serif text-4xl ${accent}`}>
          {loading
            ? '—'
            : value === null || value === undefined
              ? '—'
              : formatNumber(value)}
        </h2>

        <p className="mt-2 text-[10px] leading-5 text-[#817A70]">
          {description}
        </p>
      </div>
    </article>
  )
}

function ScoreDisplay({ score, maxScore, status }) {
  const config = STATUS_CONFIG[status] || STATUS_CONFIG.unknown

  const hasScore =
    score !== null &&
    score !== undefined &&
    Number.isFinite(Number(score))

  const maximum = Number(maxScore || 100)

  const percentage = hasScore && maximum > 0
    ? Math.min(100, Math.max(0, (Number(score) / maximum) * 100))
    : 0

  return (
    <div>
      <div className="flex items-end justify-between gap-3">
        <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
          AUDIT SCORE
        </p>

        <p className={`font-serif text-2xl ${config.accent}`}>
          {hasScore ? Number(score) : '—'}
          {hasScore && (
            <span className="ml-1 text-xs">
              / {maximum}
            </span>
          )}
        </p>
      </div>

      <div
        className="mt-2 h-2 overflow-hidden bg-[#E5DED1]"
        role="progressbar"
        aria-label="Audit score"
        aria-valuenow={hasScore ? Number(score) : 0}
        aria-valuemin={0}
        aria-valuemax={maximum}
      >
        <div
          className="h-full transition-all duration-500"
          style={{
            width: `${percentage}%`,
            backgroundColor: config.bar,
          }}
        />
      </div>
    </div>
  )
}

function FindingCard({ finding, index }) {
  const status = finding?.status || 'unknown'

  const statusColors = {
    good: 'text-[#50665C]',
    partial: 'text-[#816D43]',
    conflict: 'text-[#963746]',
    missing: 'text-[#963746]',
    weak: 'text-[#963746]',
    warning: 'text-[#816D43]',
    unknown: 'text-[#77736C]',
    not_applicable: 'text-[#77736C]',
  }

  const observed = Array.isArray(finding?.observed)
    ? finding.observed
    : finding?.observed
      ? [finding.observed]
      : []

  return (
    <article className="border border-[#D8CFBF] bg-[#F7F4EC] p-4">
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-start gap-3">
          <span className="grid size-7 shrink-0 place-items-center border border-[#B69A69]/60 font-serif text-sm text-[#963746]">
            {String(index + 1).padStart(2, '0')}
          </span>

          <div className="min-w-0">
            <h5 className="text-xs font-semibold text-[#252B37]">
              {finding?.label || finding?.key || `Header ${index + 1}`}
            </h5>

            <p
              className={`mt-1 text-[9px] font-bold uppercase tracking-wider ${
                statusColors[status] || statusColors.unknown
              }`}
            >
              {status.replaceAll('_', ' ')}
            </p>
          </div>
        </div>

        <span className="shrink-0 font-serif text-sm text-[#252B37]">
          {finding?.score ?? '—'}
          <span className="text-[10px] text-[#817A70]">
            {' '}/ {finding?.max_score ?? '—'}
          </span>
        </span>
      </div>

      {finding?.summary && (
        <p className="mt-3 text-[11px] leading-5 text-[#77736C]">
          {finding.summary}
        </p>
      )}

      {observed.length > 0 && (
        <div className="mt-3">
          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
            OBSERVED VALUE
          </p>

          <div className="mt-2 space-y-1">
            {observed.map((value, valueIndex) => (
              <pre
                key={`${finding.key || index}-${valueIndex}`}
                className="overflow-x-auto whitespace-pre-wrap break-all border border-[#D8CFBF] bg-[#EEE9DD] p-2 text-[10px] leading-5 text-[#665F56]"
              >
                {String(value)}
              </pre>
            ))}
          </div>
        </div>
      )}

      {finding?.recommendation && (
        <div className="mt-3 border-l-2 border-[#B69A69] bg-[#F1EBDD] p-3">
          <p className="text-[8px] font-bold tracking-wider text-[#665F56]">
            RECOMMENDATION
          </p>

          <p className="mt-1 text-[10px] leading-5 text-[#77736C]">
            {finding.recommendation}
          </p>
        </div>
      )}
    </article>
  )
}

function SecurityHeadersAuditPage() {
  const [audits, setAudits] = useState([])
  const [opds, setOpds] = useState([])
  const [summary, setSummary] = useState(null)

  const [auditStatus, setAuditStatus] = useState('')
  const [opdId, setOpdId] = useState('')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')
  const [optionsError, setOptionsError] = useState('')
  const [expandedId, setExpandedId] = useState(null)

  const queryParams = useMemo(() => {
    const params = {
      per_page: 12,
      page: currentPage,
    }

    if (auditStatus) {
      params.audit_status = auditStatus
    }

    if (opdId) {
      params.opd_id = opdId
    }

    return params
  }, [auditStatus, opdId, currentPage])

  const loadOptions = useCallback(async () => {
    setOptionsError('')

    try {
      const response = await api.get('/api/opds', {
        params: {
          per_page: 100,
        },
      })

      setOpds(unwrapCollection(response.data))
    } catch (requestError) {
      console.error('Security headers OPD options error:', requestError)

      setOptionsError(
        'Daftar OPD gagal dimuat. Hasil audit tetap dapat ditampilkan.',
      )
    }
  }, [])

  const loadAudits = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const response = await api.get('/api/security-headers-audits', {
        params: queryParams,
      })

      const payload = response.data || {}

      setAudits(Array.isArray(payload.data) ? payload.data : [])
      setSummary(payload.summary || null)

      setCurrentPage(Number(payload.meta?.current_page || 1))
      setLastPage(Number(payload.meta?.last_page || 1))
      setTotal(Number(payload.meta?.total || 0))
    } catch (requestError) {
      console.error('Security headers audit error:', requestError)

      setError(
        requestError.response?.data?.message ||
          'Data Security Headers Audit gagal dimuat. Periksa koneksi server.',
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [queryParams])

  useEffect(() => {
    loadOptions()
  }, [loadOptions])

  useEffect(() => {
    loadAudits()
  }, [loadAudits])

  const handleStatusChange = (event) => {
    setAuditStatus(event.target.value)
    setCurrentPage(1)
    setExpandedId(null)
  }

  const handleOpdChange = (event) => {
    setOpdId(event.target.value)
    setCurrentPage(1)
    setExpandedId(null)
  }

  const handleRefresh = () => {
    loadAudits(true)
  }

  const getSummary = (key) => {
    if (!summary) return null
    return summary[key] ?? 0
  }

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
              SIMONTA / SECURITY INTELLIGENCE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Security Headers <span className="italic text-[#963746]">Audit.</span>
              </h1>

              <p className="mt-4 max-w-2xl text-sm leading-7 text-[#77736C]">
                Pemantauan konfigurasi HTTP Security Headers pada website OPD
                untuk mengidentifikasi header yang belum tersedia, lemah,
                atau memiliki konfigurasi yang bertentangan.
              </p>
            </div>

            <button
              type="button"
              onClick={handleRefresh}
              disabled={refreshing || loading}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto"
            >
              <Icon
                name="refresh"
                size={15}
                className={refreshing ? 'animate-spin' : ''}
              />
              {refreshing ? 'REFRESHING...' : 'REFRESH DATA'}
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              HTTP SECURITY HEADERS
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              CONFIGURATION AUDIT
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              AUDIT DATA CONNECTED
            </span>
          </div>
        </div>
      </section>

      {/* Metrics */}
      <section>
        <div className="mb-4 flex items-center justify-between gap-3">
          <div>
            <p className="text-[9px] font-bold tracking-[0.18em] text-[#963746]">
              AUDIT OVERVIEW
            </p>

            <h2 className="mt-1 font-serif text-xl text-[#191A20]">
              Security Posture Summary
            </h2>
          </div>

          <span className="text-[9px] tracking-wider text-[#817A70]">
            LATEST AUDIT PER WEBSITE
          </span>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
          <MetricCard
            index="01"
            label="TOTAL WEBSITES"
            value={getSummary('total_websites')}
            description="Website dengan hasil audit"
            loading={loading}
          />

          <MetricCard
            index="02"
            label="STRONG"
            value={getSummary('strong')}
            description="Konfigurasi relatif kuat"
            tone="success"
            loading={loading}
          />

          <MetricCard
            index="03"
            label="MODERATE"
            value={getSummary('moderate')}
            description="Masih memiliki kekurangan"
            tone="warning"
            loading={loading}
          />

          <MetricCard
            index="04"
            label="NEEDS REVIEW"
            value={getSummary('needs_review')}
            description="Perlu pemeriksaan lebih lanjut"
            tone="warning"
            loading={loading}
          />

          <MetricCard
            index="05"
            label="WEAK"
            value={getSummary('weak')}
            description="Banyak kriteria belum terpenuhi"
            tone="danger"
            loading={loading}
          />
        </div>
      </section>

      {/* Filters */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC] p-5">
        <div className="mb-4 flex items-center gap-2">
          <Icon name="search" size={16} className="text-[#963746]" />

          <h3 className="text-[10px] font-bold tracking-wider text-[#252B37]">
            FILTER AUDIT DATA
          </h3>
        </div>

        <div className="grid gap-4 md:grid-cols-2">
          <label className="block">
            <span className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]">
              AUDIT STATUS
            </span>

            <select
              value={auditStatus}
              onChange={handleStatusChange}
              className="w-full border border-[#D8CFBF] bg-[#EEE9DD] px-3 py-3 text-xs text-[#252B37] outline-none transition focus:border-[#963746]"
            >
              <option value="">Semua Status</option>
              <option value="strong">Strong</option>
              <option value="moderate">Moderate</option>
              <option value="needs_review">Needs Review</option>
              <option value="weak">Weak</option>
              <option value="unknown">Unknown</option>
            </select>
          </label>

          <label className="block">
            <span className="mb-2 block text-[9px] font-bold tracking-wider text-[#817A70]">
              ORGANISASI PERANGKAT DAERAH
            </span>

            <select
              value={opdId}
              onChange={handleOpdChange}
              className="w-full border border-[#D8CFBF] bg-[#EEE9DD] px-3 py-3 text-xs text-[#252B37] outline-none transition focus:border-[#963746]"
            >
              <option value="">Semua OPD</option>

              {opds.map((opd) => (
                <option key={opd.id} value={opd.id}>
                  {opd.name}
                </option>
              ))}
            </select>
          </label>
        </div>

        {optionsError && (
          <p className="mt-3 text-[10px] text-[#963746]">
            {optionsError}
          </p>
        )}
      </section>

      {/* Audit list */}
      <section className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="flex flex-col justify-between gap-3 border-b border-[#D8CFBF] px-5 py-5 sm:flex-row sm:items-center">
          <div>
            <p className="text-[9px] font-bold tracking-[0.18em] text-[#963746]">
              INSPECTION RECORDS
            </p>

            <h2 className="mt-1 font-serif text-xl text-[#191A20]">
              Website Audit Results
            </h2>
          </div>

          <span className="text-[9px] tracking-wider text-[#817A70]">
            {formatNumber(total)} WEBSITE(S)
          </span>
        </div>

        {error && (
          <div
            role="alert"
            className="m-5 border border-[#963746]/40 bg-[#963746]/5 p-4 text-xs leading-6 text-[#963746]"
          >
            {error}
          </div>
        )}

        {loading ? (
          <div className="space-y-3 p-5">
            {[1, 2, 3].map((item) => (
              <div
                key={item}
                className="h-36 animate-pulse border border-[#D8CFBF] bg-[#EEE9DD]"
              />
            ))}
          </div>
        ) : audits.length === 0 ? (
          <div className="px-5 py-16 text-center">
            <div className="mx-auto grid size-14 place-items-center border border-[#D8CFBF] text-[#963746]">
              <Icon name="shield" size={23} />
            </div>

            <h3 className="mt-4 font-serif text-xl text-[#252B37]">
              Belum Ada Hasil Audit
            </h3>

            <p className="mx-auto mt-2 max-w-md text-xs leading-6 text-[#817A70]">
              Belum tersedia hasil HTTP Security Headers Audit untuk filter
              yang dipilih. Jalankan pemeriksaan website melalui Monitoring
              Center terlebih dahulu.
            </p>
          </div>
        ) : (
          <div className="divide-y divide-[#D8CFBF]">
            {audits.map((item) => {
              const audit = item.audit || {}
              const website = item.website || {}
              const opd = item.opd || {}

              const itemId = item.scan_id || website.id
              const isExpanded = expandedId === itemId

              const status =
                STATUS_CONFIG[audit.status]
                  ? audit.status
                  : 'unknown'

              const config = STATUS_CONFIG[status]

              const findings = Array.isArray(audit.findings)
                ? audit.findings
                : []

              const headers = item.security_headers &&
                typeof item.security_headers === 'object'
                ? item.security_headers
                : {}

              return (
                <article
                  key={itemId}
                  className="p-5 transition hover:bg-[#F1EBDD]/70 sm:p-6"
                >
                  <div className="flex flex-col justify-between gap-5 xl:flex-row">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status={status} />

                        <span className="text-[9px] tracking-wider text-[#817A70]">
                          SCAN #{item.scan_id}
                        </span>
                      </div>

                      <h3 className="mt-3 break-words font-serif text-xl text-[#191A20] sm:text-2xl">
                        {website.name || 'Website tidak tersedia'}
                      </h3>

                      <p className="mt-1 text-[10px] text-[#817A70]">
                        {opd.name || 'OPD tidak tersedia'}
                      </p>

                      {website.url && (
                        <a
                          href={website.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="mt-2 inline-block max-w-full break-all text-[10px] text-[#963746] underline decoration-[#B69A69]/60 underline-offset-4 hover:text-[#762B37]"
                        >
                          {website.url}
                        </a>
                      )}

                      <div className="mt-5 grid gap-4 border-y border-[#D8CFBF] py-4 sm:grid-cols-3">
                        <div>
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            FINDINGS
                          </p>

                          <p className="mt-1 font-serif text-2xl text-[#252B37]">
                            {formatNumber(audit.finding_count ?? findings.length)}
                          </p>
                        </div>

                        <div>
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            HEADERS DETECTED
                          </p>

                          <p className="mt-1 font-serif text-2xl text-[#252B37]">
                            {audit.headers_present ?? '—'}
                            <span className="ml-1 text-xs text-[#817A70]">
                              / {audit.headers_checked ?? '—'}
                            </span>
                          </p>
                        </div>

                        <div>
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            AUDIT TIME
                          </p>

                          <p className="mt-2 text-[10px] leading-5 text-[#665F56]">
                            {formatDate(audit.checked_at || item.completed_at)}
                          </p>
                        </div>
                      </div>

                      <div className="mt-4">
                        <ScoreDisplay
                          score={audit.score}
                          maxScore={audit.max_score}
                          status={status}
                        />
                      </div>

                      <div className={`mt-4 border p-3 ${config.panel}`}>
                        <p className="text-[10px] font-semibold text-[#252B37]">
                          {config.label}
                        </p>

                        <p className="mt-1 text-[10px] leading-5 text-[#77736C]">
                          {config.description}
                        </p>
                      </div>
                    </div>

                    <div className="flex shrink-0 items-start xl:items-center">
                      <button
                        type="button"
                        onClick={() => {
                          setExpandedId(isExpanded ? null : itemId)
                        }}
                        aria-expanded={isExpanded}
                        className="inline-flex items-center justify-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10"
                      >
                        <Icon name="search" size={14} />

                        {isExpanded ? 'HIDE DETAILS' : 'VIEW DETAILS'}

                        <Icon
                          name="down"
                          size={13}
                          className={`transition-transform ${
                            isExpanded ? 'rotate-180' : ''
                          }`}
                        />
                      </button>
                    </div>
                  </div>

                  {isExpanded && (
                    <div className="mt-6 border-t border-[#D8CFBF] pt-5">
                      <div className="grid gap-4 sm:grid-cols-3">
                        <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4">
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            SCAN STATUS
                          </p>

                          <p className="mt-2 text-xs font-semibold text-[#252B37]">
                            {item.scan_status || '—'}
                          </p>
                        </div>

                        <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4">
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            HTTP STATUS
                          </p>

                          <p className="mt-2 text-xs font-semibold text-[#252B37]">
                            {item.http_status_code ?? '—'}
                          </p>
                        </div>

                        <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4">
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            APPLICABLE MAX SCORE
                          </p>

                          <p className="mt-2 text-xs font-semibold text-[#252B37]">
                            {audit.applicable_max_score ?? '—'}
                          </p>
                        </div>
                      </div>

                      {/* Individual findings */}
                      <div className="mt-6">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                          <h4 className="font-serif text-xl text-[#191A20]">
                            Audit Findings
                          </h4>

                          <span className="text-[9px] tracking-wider text-[#817A70]">
                            {findings.length} FINDING(S)
                          </span>
                        </div>

                        {findings.length === 0 ? (
                          <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4 text-xs text-[#817A70]">
                            Tidak ada detail temuan yang tersimpan pada audit ini.
                          </div>
                        ) : (
                          <div className="grid gap-3 lg:grid-cols-2">
                            {findings.map((finding, index) => (
                              <FindingCard
                                key={`${itemId}-finding-${index}`}
                                finding={finding}
                                index={index}
                              />
                            ))}
                          </div>
                        )}
                      </div>

                      {/* Raw response headers */}
                      <div className="mt-7">
                        <div className="mb-3">
                          <h4 className="font-serif text-xl text-[#191A20]">
                            Detected HTTP Headers
                          </h4>

                          <p className="mt-1 text-[10px] leading-5 text-[#817A70]">
                            Nilai header yang berhasil diekstrak dari respons HTTP.
                          </p>
                        </div>

                        {Object.keys(headers).length === 0 ? (
                          <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4 text-xs text-[#817A70]">
                            Data header mentah tidak tersedia.
                          </div>
                        ) : (
                          <div className="space-y-2">
                            {Object.entries(headers).map(([key, values]) => {
                              const normalizedValues = Array.isArray(values)
                                ? values
                                : [values]

                              return (
                                <div
                                  key={key}
                                  className="border border-[#D8CFBF] bg-[#F1EBDD] p-3 sm:flex sm:items-start sm:gap-5"
                                >
                                  <div className="mb-2 min-w-0 sm:mb-0 sm:w-1/3">
                                    <p className="text-[10px] font-semibold text-[#252B37]">
                                      {HEADER_LABELS[key] || key}
                                    </p>

                                    <p className="mt-1 break-all text-[9px] text-[#817A70]">
                                      {key}
                                    </p>
                                  </div>

                                  <div className="min-w-0 flex-1 space-y-1">
                                    {normalizedValues.map((value, index) => (
                                      <pre
                                        key={`${key}-${index}`}
                                        className="overflow-x-auto whitespace-pre-wrap break-all bg-[#EEE9DD] p-2 text-[10px] leading-5 text-[#665F56]"
                                      >
                                        {String(value)}
                                      </pre>
                                    ))}
                                  </div>
                                </div>
                              )
                            })}
                          </div>
                        )}
                      </div>

                      <div className="mt-6 border-l-2 border-[#B69A69] bg-[#F1EBDD] p-4">
                        <p className="text-[9px] font-bold tracking-wider text-[#665F56]">
                          AUDIT METHODOLOGY
                        </p>

                        <p className="mt-2 text-[10px] leading-6 text-[#817A70]">
                          Penilaian ini memeriksa enam kelompok HTTP Security
                          Headers menggunakan aturan audit SIMONTA. Skor
                          menggambarkan kelengkapan dan konsistensi konfigurasi
                          header yang diperiksa, bukan tingkat keamanan keseluruhan
                          website maupun sertifikasi keamanan. Rekomendasi perlu
                          dievaluasi oleh pengelola website sebelum diterapkan.
                        </p>
                      </div>
                    </div>
                  )}
                </article>
              )
            })}
          </div>
        )}

        {/* Pagination */}
        {!loading && total > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Showing page {currentPage} of {lastPage}
              {' · '}
              {formatNumber(total)} website(s)
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="left" />
              </button>

              <span className="min-w-8 text-center font-serif text-sm text-[#252B37]">
                {currentPage}
              </span>

              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(lastPage, page + 1))}
                disabled={currentPage >= lastPage}
                aria-label="Next page"
                className="grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="right" />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* Information */}
      <section className="border border-[#B69A69]/50 bg-[#F7F4EC] p-5">
        <div className="flex items-start gap-3">
          <div className="grid size-9 shrink-0 place-items-center border border-[#B69A69]/60 text-[#963746]">
            <Icon name="shield" size={18} />
          </div>

          <div>
            <h3 className="text-[10px] font-bold tracking-wider text-[#252B37]">
              ABOUT HTTP SECURITY HEADERS AUDIT
            </h3>

            <p className="mt-2 text-[10px] leading-6 text-[#77736C]">
              SIMONTA mengevaluasi Content Security Policy, HTTP Strict
              Transport Security, X-Content-Type-Options, Frame Protection,
              Referrer Policy, dan Permissions Policy. Pemeriksaan dilakukan
              berdasarkan header yang diterima dari respons website.
              Hasil audit bersifat informatif dan tidak menggantikan
              pengujian keamanan menyeluruh.
            </p>
          </div>
        </div>
      </section>
    </div>
  )
}

export default SecurityHeadersAuditPage
