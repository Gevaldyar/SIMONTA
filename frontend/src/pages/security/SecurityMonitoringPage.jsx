import { useCallback, useEffect, useMemo, useState } from 'react'
import api from '../../services/api'

function Icon({ name, size = 17, className = '' }) {
  const paths = {
    refresh: <><path d="M20 7v5h-5" /><path d="M4 17v-5h5" /><path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" /></>,
    search: <><circle cx="11" cy="11" r="7" /><path d="m16 16 4 4" /></>,
    shield: <><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" /><path d="m9 12 2 2 4-4" /></>,
    alert: <><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3l-7.9-14.1a2 2 0 0 0-3.4 0Z" /><path d="M12 9v4M12 17h.01" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" /></>,
    down: <path d="m6 9 6 6 6-6" />,
    left: <path d="m15 18-6-6 6-6" />,
    right: <path d="m9 18 6-6-6-6" />,
    close: <path d="m18 6-12 12M6 6l12 12" />,
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
  if (Number.isNaN(date.getTime())) return 'Belum tersedia'

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

  if (Array.isArray(root)) return root
  if (Array.isArray(root?.data)) return root.data
  if (Array.isArray(root?.data?.data)) return root.data.data

  return []
}

function extractPage(payload) {
  const root = payload?.data && !Array.isArray(payload.data)
    ? payload.data
    : payload

  const page = (
    root?.data &&
    !Array.isArray(root.data) &&
    Array.isArray(root.data.data)
  )
    ? root.data
    : root

  const items = Array.isArray(page?.data)
    ? page.data
    : Array.isArray(root?.data)
      ? root.data
      : []

  return {
    items,
    currentPage: Number(
      page?.current_page ?? page?.meta?.current_page ?? root?.current_page ?? 1,
    ),
    lastPage: Number(
      page?.last_page ?? page?.meta?.last_page ?? root?.last_page ?? 1,
    ),
    total: Number(
      page?.total ?? page?.meta?.total ?? root?.total ?? items.length,
    ),
    summary: payload?.summary ?? root?.summary ?? page?.summary ?? null,
  }
}

function getSummaryCount(summary, keys) {
  if (!summary) return null

  for (const key of keys) {
    const value = summary[key] ?? summary.counts?.[key]

    if (value !== undefined && value !== null && Number.isFinite(Number(value))) {
      return Number(value)
    }
  }

  return null
}

const RISK_CONFIG = {
  high: {
    label: 'Risiko Tinggi',
    shortLabel: 'HIGH RISK',
    description: 'Ditemukan indikator yang membutuhkan pemeriksaan segera.',
    icon: 'alert',
    badge: 'border-[#963746]/50 bg-[#963746]/10 text-[#963746]',
    panel: 'border-[#963746]/40 bg-[#963746]/5',
    accent: 'text-[#963746]',
  },
  review: {
    label: 'Perlu Ditinjau',
    shortLabel: 'REVIEW',
    description: 'Terdapat indikator mencurigakan yang perlu diverifikasi.',
    icon: 'alert',
    badge: 'border-[#B69A69]/70 bg-[#B69A69]/15 text-[#816D43]',
    panel: 'border-[#B69A69]/50 bg-[#B69A69]/10',
    accent: 'text-[#816D43]',
  },
  low: {
    label: 'Risiko Rendah',
    shortLabel: 'LOW RISK',
    description: 'Tidak ditemukan indikator risiko utama pada pemeriksaan ini.',
    icon: 'shield',
    badge: 'border-[#82918B]/60 bg-[#82918B]/10 text-[#50665C]',
    panel: 'border-[#82918B]/40 bg-[#82918B]/5',
    accent: 'text-[#50665C]',
  },
  unknown: {
    label: 'Belum Dinilai',
    shortLabel: 'UNKNOWN',
    description: 'Belum tersedia data HTML yang dapat dianalisis.',
    icon: 'clock',
    badge: 'border-[#D8CFBF] bg-[#EEE9DD] text-[#77736C]',
    panel: 'border-[#D8CFBF] bg-[#F1EBDD]',
    accent: 'text-[#77736C]',
  },
}

function RiskBadge({ status }) {
  const config = RISK_CONFIG[status] || RISK_CONFIG.unknown

  return (
    <span className={`inline-flex items-center gap-1.5 border px-2.5 py-1.5 text-[9px] font-bold tracking-wider ${config.badge}`}>
      <Icon name={config.icon} size={12} />
      {config.shortLabel}
    </span>
  )
}

function MetricCard({ index, label, value, description, icon, tone = 'default', loading }) {
  const accent = tone === 'danger'
    ? 'text-[#963746]'
    : tone === 'warning'
      ? 'text-[#816D43]'
      : tone === 'success'
        ? 'text-[#50665C]'
        : 'text-[#191A20]'

  return (
    <article className="relative min-h-[140px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]">
      <span className="absolute -right-1 -top-7 font-serif text-[78px] leading-none text-[#E8E0D2]">
        {index}
      </span>

      <div className="relative flex items-center justify-between gap-3">
        <p className="text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
          {label}
        </p>

        <div className="grid size-9 place-items-center border border-[#D8CFBF] text-[#963746]">
          <Icon name={icon} />
        </div>
      </div>

      <h2 className={`relative mt-5 font-serif text-4xl ${accent}`}>
        {loading ? '—' : value === null ? '—' : formatNumber(value)}
      </h2>

      <p className="relative mt-2 text-[10px] leading-5 text-[#817A70]">
        {description}
      </p>
    </article>
  )
}

function FindingItem({ finding, index }) {
  const isObject = finding && typeof finding === 'object'
  const title = isObject
    ? (
      finding.title ||
      finding.name ||
      finding.label ||
      finding.indicator ||
      finding.type ||
      `Indikator ${index + 1}`
    )
    : String(finding || `Indikator ${index + 1}`)

  const description = isObject
    ? finding.description || finding.message || finding.detail || ''
    : ''

  const evidence = isObject
    ? finding.evidence ?? finding.patterns ?? finding.markers ?? null
    : null

  return (
    <div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4">
      <div className="flex items-start gap-3">
        <span className="grid size-7 shrink-0 place-items-center border border-[#B69A69]/60 font-serif text-sm text-[#963746]">
          {String(index + 1).padStart(2, '0')}
        </span>

        <div className="min-w-0 flex-1">
          <p className="text-xs font-semibold text-[#252B37]">
            {title}
          </p>

          {description && (
            <p className="mt-2 text-[11px] leading-5 text-[#77736C]">
              {description}
            </p>
          )}

          {evidence && (
            <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-all border border-[#D8CFBF] bg-[#EEE9DD] p-3 text-[10px] leading-5 text-[#665F56]">
              {typeof evidence === 'string'
                ? evidence
                : JSON.stringify(evidence, null, 2)}
            </pre>
          )}

          {isObject && !description && !evidence && (
            <pre className="mt-3 overflow-x-auto whitespace-pre-wrap break-all border border-[#D8CFBF] bg-[#EEE9DD] p-3 text-[10px] leading-5 text-[#665F56]">
              {JSON.stringify(finding, null, 2)}
            </pre>
          )}
        </div>
      </div>
    </div>
  )
}

function SecurityMonitoringPage() {
  const [assessments, setAssessments] = useState([])
  const [opds, setOpds] = useState([])
  const [summary, setSummary] = useState(null)

  const [riskStatus, setRiskStatus] = useState('')
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

    if (riskStatus) params.risk_status = riskStatus
    if (opdId) params.opd_id = opdId

    return params
  }, [riskStatus, opdId, currentPage])

  const loadOptions = useCallback(async () => {
    setOptionsError('')

    try {
      const response = await api.get('/api/opds', {
        params: { per_page: 100 },
      })

      setOpds(unwrapCollection(response.data))
    } catch (requestError) {
      console.error('Security assessment OPD options error:', requestError)
      setOptionsError('Daftar OPD gagal dimuat. Data hasil pemeriksaan tetap dapat ditampilkan.')
    }
  }, [])

  const loadAssessments = useCallback(async (silent = false) => {
    if (silent) setRefreshing(true)
    else setLoading(true)

    setError('')

    try {
      const response = await api.get('/api/security-assessments', {
        params: queryParams,
      })

      const result = extractPage(response.data)

      setAssessments(result.items)
      setCurrentPage(result.currentPage)
      setLastPage(result.lastPage)
      setTotal(result.total)
      setSummary(result.summary)
    } catch (requestError) {
      console.error('Security assessment list error:', requestError)

      setAssessments([])
      setTotal(0)
      setSummary(null)

      setError(
        requestError.response?.data?.message ||
        'Data pemeriksaan keamanan tidak dapat dimuat. Periksa koneksi backend dan autentikasi.',
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
    loadAssessments()
  }, [loadAssessments])

  const updateFilter = (key, value) => {
    setCurrentPage(1)

    if (key === 'risk_status') setRiskStatus(value)
    if (key === 'opd_id') setOpdId(value)
  }

  const resetFilters = () => {
    setRiskStatus('')
    setOpdId('')
    setCurrentPage(1)
    setExpandedId(null)
  }

  const metrics = useMemo(() => {
    const countFromPage = (status) => assessments.filter(
      (item) => item.assessment?.status === status,
    ).length

    const totalValue = getSummaryCount(summary, [
      'total_assessments',
      'total',
      'total_websites',
    ])

    const lowValue = getSummaryCount(summary, [
      'low',
      'low_risk',
      'low_count',
      'low_risk_count',
    ])

    const reviewValue = getSummaryCount(summary, [
      'review',
      'review_count',
      'review_risk',
      'review_risk_count',
    ])

    const highValue = getSummaryCount(summary, [
      'high',
      'high_risk',
      'high_count',
      'high_risk_count',
    ])

    const unknownValue = getSummaryCount(summary, [
      'unknown',
      'unknown_count',
    ])

    return [
      {
        index: '01',
        label: 'TOTAL ASSESSMENTS',
        value: totalValue ?? total,
        description: 'Jumlah website pada hasil filter',
        icon: 'globe',
      },
      {
        index: '02',
        label: 'HIGH RISK',
        value: highValue ?? countFromPage('high'),
        description: highValue === null ? 'Jumlah pada halaman ini' : 'Website dengan indikator risiko tinggi',
        icon: 'alert',
        tone: 'danger',
      },
      {
        index: '03',
        label: 'NEED REVIEW',
        value: reviewValue ?? countFromPage('review'),
        description: reviewValue === null ? 'Jumlah pada halaman ini' : 'Website yang memerlukan verifikasi',
        icon: 'search',
        tone: 'warning',
      },
      {
        index: '04',
        label: 'LOW RISK',
        value: lowValue ?? countFromPage('low'),
        description: lowValue === null ? 'Jumlah pada halaman ini' : 'Website dengan risiko rendah',
        icon: 'shield',
        tone: 'success',
      },
    ]
  }, [assessments, summary, total])

  return (
    <div className="simonta-page-enter space-y-8">
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          05
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
                Security <span className="italic text-[#963746]">Monitoring.</span>
              </h1>

              <p className="mt-4 max-w-2xl text-sm leading-7 text-[#77736C]">
                Pemantauan indikator keamanan website OPD berdasarkan analisis
                pasif terhadap konten HTML hasil pemeriksaan website.
              </p>
            </div>

            <button
              type="button"
              onClick={() => loadAssessments(true)}
              disabled={refreshing || loading}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:opacity-50 lg:self-auto"
            >
              <Icon
                name="refresh"
                size={15}
                className={refreshing ? 'animate-spin' : ''}
              />
              REFRESH ASSESSMENTS
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              PASSIVE HTML ANALYSIS
            </span>
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              RISK INDICATORS
            </span>
            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              SECURITY API CONNECTED
            </span>
          </div>
        </div>
      </section>

      {error && (
        <div
          role="alert"
          className="flex items-start gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs leading-6 text-[#963746]"
        >
          <Icon name="alert" size={17} />
          <span>{error}</span>
        </div>
      )}

      {optionsError && (
        <div
          role="status"
          className="border border-[#B69A69]/50 bg-[#B69A69]/10 p-3 text-[10px] leading-5 text-[#665F56]"
        >
          {optionsError}
        </div>
      )}

      <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {metrics.map((metric) => (
          <MetricCard key={metric.index} {...metric} loading={loading} />
        ))}
      </section>

      <section className="border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="border-b border-[#D8CFBF] p-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            SECURITY DATABASE / FILTERS
          </p>

          <div className="mt-2 flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
            <div>
              <h2 className="font-serif text-2xl text-[#191A20]">
                Security Register
              </h2>
              <p className="mt-1 text-[10px] text-[#817A70]">
                Telusuri hasil pemeriksaan berdasarkan tingkat risiko dan OPD.
              </p>
            </div>

            <button
              type="button"
              onClick={resetFilters}
              className="self-start pb-1 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
            >
              RESET FILTERS
            </button>
          </div>

          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <div>
              <label
                htmlFor="security-risk-filter"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                RISK LEVEL
              </label>

              <select
                id="security-risk-filter"
                value={riskStatus}
                onChange={(event) => updateFilter('risk_status', event.target.value)}
                className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
              >
                <option value="">All risk levels</option>
                <option value="high">High risk</option>
                <option value="review">Need review</option>
                <option value="low">Low risk</option>
                <option value="unknown">Unknown</option>
              </select>
            </div>

            <div>
              <label
                htmlFor="security-opd-filter"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                OPD
              </label>

              <select
                id="security-opd-filter"
                value={opdId}
                onChange={(event) => updateFilter('opd_id', event.target.value)}
                className="w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
              >
                <option value="">All OPDs</option>
                {opds.map((opd) => (
                  <option key={opd.id} value={opd.id}>
                    {opd.name || opd.nama || `OPD #${opd.id}`}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </div>

        <div className="divide-y divide-[#D8CFBF]">
          {loading ? (
            [1, 2, 3].map((item) => (
              <div key={item} className="space-y-3 p-5">
                <div className="simonta-skeleton h-4 w-1/3" />
                <div className="simonta-skeleton h-3 w-2/3" />
                <div className="simonta-skeleton h-3 w-1/2" />
              </div>
            ))
          ) : assessments.length === 0 ? (
            <div className="px-5 py-16 text-center">
              <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                <Icon name="shield" size={23} />
              </div>

              <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                No Security Assessments Found
              </h3>

              <p className="mx-auto mt-2 max-w-md text-xs leading-6 text-[#817A70]">
                Belum ada hasil pemeriksaan keamanan yang sesuai dengan filter.
                Hasil akan tersedia setelah website menjalani pemindaian.
              </p>
            </div>
          ) : (
            assessments.map((item) => {
              const assessment = item.assessment || {}
              const website = item.website || {}
              const opd = item.opd || {}
              const status = RISK_CONFIG[assessment.status]
                ? assessment.status
                : 'unknown'

              const config = RISK_CONFIG[status]
              const findings = Array.isArray(assessment.findings)
                ? assessment.findings
                : []

              const itemId = item.scan_id ?? website.id
              const isExpanded = expandedId === itemId

              return (
                <article
                  key={itemId}
                  className="p-5 transition hover:bg-[#F1EBDD]/70 sm:p-6"
                >
                  <div className="flex flex-col justify-between gap-5 xl:flex-row xl:items-start">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <RiskBadge status={status} />

                        <span className="text-[9px] tracking-wider text-[#948B7D]">
                          SCAN #{item.scan_id ?? '—'}
                        </span>
                      </div>

                      <h3 className="mt-4 break-words font-serif text-2xl text-[#191A20]">
                        {website.name || website.url || 'Website tidak tersedia'}
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
                            RISK SCORE
                          </p>
                          <p className={`mt-1 font-serif text-2xl ${config.accent}`}>
                            {assessment.risk_score ?? '—'}
                            {assessment.risk_score !== null && assessment.risk_score !== undefined ? (
                              <span className="ml-1 text-xs">/ 100</span>
                            ) : null}
                          </p>
                        </div>

                        <div>
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            FINDINGS
                          </p>
                          <p className="mt-1 font-serif text-2xl text-[#252B37]">
                            {formatNumber(assessment.finding_count ?? findings.length)}
                          </p>
                        </div>

                        <div>
                          <p className="text-[8px] font-bold tracking-wider text-[#817A70]">
                            ASSESSED AT
                          </p>
                          <p className="mt-2 text-[10px] leading-5 text-[#665F56]">
                            {formatDate(assessment.assessed_at || item.completed_at)}
                          </p>
                        </div>
                      </div>

                      <div className={`mt-4 border p-3 ${config.panel}`}>
                        <p className="text-[10px] font-semibold text-[#252B37]">
                          {config.label}
                        </p>
                        <p className="mt-1 text-[10px] leading-5 text-[#77736C]">
                          {assessment.reason || config.description}
                        </p>
                      </div>
                    </div>

                    <div className="flex shrink-0 flex-row items-center gap-2 xl:flex-col xl:items-stretch">
                      <button
                        type="button"
                        onClick={() => setExpandedId(isExpanded ? null : itemId)}
                        className="simonta-angular-action inline-flex items-center justify-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10"
                      >
                        <Icon name="search" size={14} />
                        {isExpanded ? 'HIDE DETAILS' : 'VIEW DETAILS'}
                        <Icon
                          name="down"
                          size={13}
                          className={isExpanded ? 'rotate-180 transition-transform' : 'transition-transform'}
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
                            HTML ANALYZED
                          </p>
                          <p className="mt-2 text-xs font-semibold text-[#252B37]">
                            {assessment.html_analyzed ? 'Yes' : 'No'}
                          </p>
                        </div>
                      </div>

                      <div className="mt-6">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                          <h4 className="font-serif text-xl text-[#191A20]">
                            Detected Indicators
                          </h4>

                          <span className="text-[9px] tracking-wider text-[#817A70]">
                            {findings.length} FINDING(S)
                          </span>
                        </div>

                        {findings.length === 0 ? (
                          <div className="border border-[#D8CFBF] bg-[#F1EBDD] p-4 text-xs text-[#817A70]">
                            Tidak ada detail indikator yang tersimpan untuk pemeriksaan ini.
                          </div>
                        ) : (
                          <div className="grid gap-3 lg:grid-cols-2">
                            {findings.map((finding, index) => (
                              <FindingItem
                                key={`${itemId}-finding-${index}`}
                                finding={finding}
                                index={index}
                              />
                            ))}
                          </div>
                        )}
                      </div>

                      <div className="mt-5 border-l-2 border-[#B69A69] bg-[#F1EBDD] p-4">
                        <p className="text-[9px] font-bold tracking-wider text-[#665F56]">
                          ASSESSMENT NOTE
                        </p>
                        <p className="mt-2 text-[10px] leading-5 text-[#817A70]">
                          Hasil ini merupakan analisis indikator secara pasif,
                          bukan bukti pasti bahwa website telah diretas.
                          Temuan perlu diverifikasi lebih lanjut oleh administrator
                          atau pengelola website.
                        </p>
                      </div>
                    </div>
                  )}
                </article>
              )
            })
          )}
        </div>

        {!loading && total > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Showing page {currentPage} of {lastPage}
              {' · '}
              {formatNumber(total)} assessment(s)
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
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
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="right" />
              </button>
            </div>
          </div>
        )}
      </section>

      <section className="border border-[#B69A69]/50 bg-[#F7F4EC] p-5">
        <div className="flex items-start gap-3">
          <div className="grid size-9 shrink-0 place-items-center border border-[#B69A69]/60 text-[#963746]">
            <Icon name="shield" size={18} />
          </div>

          <div>
            <h3 className="text-[10px] font-bold tracking-wider text-[#252B37]">
              HOW SECURITY ASSESSMENT WORKS
            </h3>
            <p className="mt-2 text-[10px] leading-6 text-[#77736C]">
              Sistem menganalisis respons HTML dari proses monitoring untuk
              mencari indikator seperti defacement, pola JavaScript mencurigakan,
              iframe eksternal tersembunyi, spam SEO, dan pengalihan lintas domain.
              Pemeriksaan ini bersifat pasif dan berbasis heuristik, sehingga
              tidak dapat menjamin deteksi seluruh jenis kompromi website.
            </p>
          </div>
        </div>
      </section>
    </div>
  )
}

export default SecurityMonitoringPage