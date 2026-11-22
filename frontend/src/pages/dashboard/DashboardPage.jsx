
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'

import api from '../../services/api'

const INITIAL_DATA = {
  statistics: {},
  latestScan: null,
  distribution: [],
  recentScans: [],

  websiteHealth: {
    summary: {},
    websites: [],
  },

  securityAssessment: {
    summary: {},
  },
}

const CATEGORY_LABELS = {
  backend_language: 'Backend Language',
  backend_framework: 'Backend Framework',
  cms: 'Content Management System',
  frontend_framework: 'Frontend Framework',
  javascript_library: 'JavaScript Library',
  css_framework: 'CSS Framework',
  web_server: 'Web Server',
  cdn_proxy: 'CDN / Proxy',
  analytics: 'Analytics',
}

function Icon({ name, size = 17, className = '' }) {
  const common = {
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.7,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
  }

  const paths = {
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.64 9a7 7 0 0 1 11.5-2L20 12M4 12l2.86 5a7 7 0 0 0 11.5-2" />
      </>
    ),
    arrow: <path d="M7 17 17 7M8 7h9v9" />,
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
      </>
    ),
    activity: (
      <>
        <path d="M3 12h4l3-8 4 16 3-8h4" />
      </>
    ),
    layers: (
      <>
        <path d="m12 3 9 5-9 5-9-5 9-5Z" />
        <path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
      </>
    ),
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    database: (
      <>
        <ellipse cx="12" cy="5" rx="8" ry="3" />
        <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3" />
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
  }

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      className={className}
      aria-hidden="true"
      {...common}
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
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function formatPercent(value) {
  if (value === null || value === undefined) return '—'

  return `${Number(value).toFixed(2)}%`
}

function formatResponseTime(value) {
  if (value === null || value === undefined) return '—'

  return `${formatNumber(value)} ms`
}

const HEALTH_STATUS = {
  online: {
    label: 'ONLINE',
    className: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    dot: 'bg-[#82918B]',
  },
  degraded: {
    label: 'DEGRADED',
    className: 'border-[#B69A69]/60 bg-[#B69A69]/15 text-[#816D43]',
    dot: 'bg-[#B69A69]',
  },
  down: {
    label: 'DOWN',
    className: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
    dot: 'bg-[#963746]',
  },
  checking: {
    label: 'CHECKING',
    className: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    dot: 'bg-[#82918B]',
  },
  unknown: {
    label: 'UNKNOWN',
    className: 'border-[#D8CFBF] bg-[#D8CFBF]/20 text-[#77736C]',
    dot: 'bg-[#948B7D]',
  },
}

function HealthBadge({ status }) {
  const config = HEALTH_STATUS[status] || HEALTH_STATUS.unknown

  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1.5 text-[9px] font-bold tracking-wider ${config.className}`}
    >
      <span className={`size-1.5 rounded-full ${config.dot}`} />
      {config.label}
    </span>
  )
}

function MetricPanel({
  index,
  label,
  value,
  description,
  icon,
  delay,
}) {
  return (
    <article
      className="simonta-card-enter group relative min-h-[174px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69] hover:shadow-[0_12px_28px_rgba(25,26,32,0.07)]"
      style={{ '--stagger-index': delay }}
    >
      <span className="pointer-events-none absolute -right-2 -top-8 select-none font-serif text-[105px] leading-none text-[#E8E0D2] transition duration-500 group-hover:text-[#DCD0BB]">
        {index}
      </span>

      <div className="relative flex items-center justify-between">
        <p className="text-[9px] font-bold tracking-[0.17em] text-[#77736C]">
          {label}
        </p>

        <div className="grid size-9 place-items-center border border-[#D8CFBF] text-[#963746] transition duration-300 group-hover:border-[#B69A69] group-hover:bg-[#963746] group-hover:text-[#EEE9DD]">
          <Icon name={icon} size={17} />
        </div>
      </div>

      <div className="relative mt-5">
        <h3 className="font-serif text-4xl tracking-tight text-[#191A20] sm:text-[42px]">
          {formatNumber(value)}
        </h3>

        <p className="mt-2 text-[10px] leading-5 text-[#8B8479]">
          {description}
        </p>
      </div>

      <span className="absolute bottom-0 left-0 h-[2px] w-0 bg-[#963746] transition-all duration-500 group-hover:w-full" />
    </article>
  )
}

function MetricSkeleton() {
  return (
    <div className="min-h-[174px] border border-[#D8CFBF] bg-[#F7F4EC] p-5">
      <div className="simonta-skeleton h-3 w-28" />
      <div className="simonta-skeleton mt-7 h-10 w-24" />
      <div className="simonta-skeleton mt-4 h-3 w-36" />
    </div>
  )
}

function StatusBadge({ status }) {
  const styles = {
    queued: 'border-[#B69A69]/50 bg-[#B69A69]/10 text-[#816D43]',
    running: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    completed: 'border-[#82918B]/60 bg-[#82918B]/15 text-[#50665C]',
    failed: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
  }

  const labels = {
    queued: 'QUEUED',
    running: 'RUNNING',
    completed: 'COMPLETED',
    failed: 'FAILED',
  }

  return (
    <span
      className={`inline-flex items-center gap-1.5 border px-2 py-1 text-[8px] font-bold tracking-[0.12em] ${
        styles[status] || 'border-[#D8CFBF] text-[#77736C]'
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {labels[status] || String(status || 'UNKNOWN').toUpperCase()}
    </span>
  )
}

function ScanActivity({ scans }) {
  if (!scans.length) {
    return (
      <div className="flex min-h-[250px] flex-col items-center justify-center px-5 text-center">
        <div className="grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
          <Icon name="activity" size={23} />
        </div>

        <h3 className="mt-5 font-serif text-xl text-[#191A20]">
          Awaiting First Inspection
        </h3>

        <p className="mt-2 max-w-xs text-xs leading-6 text-[#817A70]">
          Belum ada riwayat pemeriksaan yang dapat ditampilkan.
          Aktivitas akan muncul setelah website mulai dipantau.
        </p>

        <Link
          to="/websites"
          className="simonta-angular-action mt-5 inline-flex items-center gap-2 bg-[#963746] px-5 py-3 text-[10px] font-bold tracking-[0.12em] text-[#EEE9DD] transition hover:-translate-y-0.5 hover:bg-[#762B37]"
        >
          OPEN WEBSITE REGISTRY
          <Icon name="arrow" size={14} />
        </Link>
      </div>
    )
  }

  return (
    <div className="divide-y divide-[#D8CFBF]">
      {scans.map((scan, index) => {
        const technologies = scan.scan_technologies || []
        const date = scan.completed_at || scan.started_at || scan.created_at

        return (
          <Link
            key={scan.id}
            to="/history"
            className="group grid gap-3 px-5 py-5 transition duration-300 hover:bg-[#F1EBDD] sm:grid-cols-[1fr_auto] sm:items-center"
            style={{ animationDelay: `${index * 70}ms` }}
          >
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <StatusBadge status={scan.status} />

                <span className="text-[9px] tracking-wider text-[#948B7D]">
                  SCAN #{String(scan.id).padStart(5, '0')}
                </span>
              </div>

              <h3 className="mt-2 truncate font-medium text-sm text-[#252B37] group-hover:text-[#963746]">
                {scan.website?.name || scan.website?.url || 'Website tidak diketahui'}
              </h3>

              <p className="mt-1 truncate text-[10px] text-[#817A70]">
                {scan.website?.opd?.name || 'OPD tidak diketahui'}
              </p>

              {technologies.length > 0 && (
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {technologies.slice(0, 3).map((item) => (
                    <span
                      key={`${scan.id}-${item.id}`}
                      className="border border-[#D8CFBF] px-2 py-1 text-[9px] text-[#665F56]"
                    >
                      {item.technology?.name || 'Technology'}
                    </span>
                  ))}

                  {technologies.length > 3 && (
                    <span className="px-1 py-1 text-[9px] text-[#817A70]">
                      +{technologies.length - 3} more
                    </span>
                  )}
                </div>
              )}
            </div>

            <div className="flex items-center justify-between gap-4 sm:flex-col sm:items-end sm:gap-2">
              <span className="text-[10px] text-[#817A70]">
                {formatDate(date)}
              </span>

              <div className="flex items-center gap-3">
                <span className="text-[9px] text-[#948B7D]">
                  {scan.http_status_code
                    ? `HTTP ${scan.http_status_code}`
                    : 'HTTP —'}
                </span>

                <span className="text-[9px] text-[#948B7D]">
                  {scan.response_time_ms != null
                    ? `${formatNumber(scan.response_time_ms)} ms`
                    : '—'}
                </span>

                <Icon
                  name="arrow"
                  size={14}
                  className="text-[#B69A69] transition-transform group-hover:translate-x-1"
                />
              </div>
            </div>
          </Link>
        )
      })}
    </div>
  )
}

function TechnologyDistribution({ distribution }) {
  const maxValue = Math.max(
    1,
    ...distribution.map((item) =>
      Number(item.technology_website_matches || 0)
    )
  )

  if (!distribution.length) {
    return (
      <div className="flex min-h-[250px] flex-col items-center justify-center text-center">
        <div className="font-serif text-6xl italic text-[#B69A69]/50">
          00
        </div>

        <h3 className="mt-3 font-serif text-xl text-[#191A20]">
          No Detection Records
        </h3>

        <p className="mt-2 max-w-xs text-xs leading-6 text-[#77736C]">
          Katalog teknologi telah tersedia, tetapi belum ada
          hasil identifikasi yang dapat ditampilkan.
        </p>

        <Link
          to="/technologies"
          className="mt-5 inline-flex items-center gap-2 border-b border-[#B69A69] pb-1 text-[9px] font-bold tracking-[0.15em] text-[#963746] transition hover:text-[#762B37]"
        >
          EXPLORE TECHNOLOGY CATALOG
          <Icon name="arrow" size={13} />
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-5 px-5 py-6">
      {distribution.map((item, index) => {
        const count = Number(item.technology_website_matches || 0)
        const percentage = count > 0
          ? Math.max(4, (count / maxValue) * 100)
          : 0

        return (
          <div
            key={item.category}
            className="simonta-card-enter"
            style={{ '--stagger-index': index }}
          >
            <div className="mb-2 flex items-center justify-between gap-3">
              <div className="min-w-0">
                <p className="truncate text-xs font-semibold text-[#252B37]">
                  {CATEGORY_LABELS[item.category] || item.category}
                </p>

                <p className="mt-1 text-[10px] text-[#817A70]">
                  {formatNumber(item.technologies_count)} technologies
                </p>
              </div>

              <span className="font-serif text-xl text-[#963746]">
                {formatNumber(count)}
              </span>
            </div>

            <div className="h-2 overflow-hidden border border-[#D8E0EA] bg-[#EEF2F6]">
              <div
                className="h-full bg-[#963746] transition-all duration-1000 ease-out"
                style={{ width: `${percentage}%` }}
              />
            </div>
          </div>
        )
      })}

      <p className="border-t border-[#D8E0EA] pt-4 text-[10px] leading-5 text-[#77736C]">
        Jumlah merupakan pasangan teknologi dan website berdasarkan
        hasil pemeriksaan completed terbaru per website.
      </p>
    </div>
  )
}

function WebsiteHealthSection({ health, loading }) {
  const summary = health.summary || {}
  const websites = health.websites || []

  const statusCards = [
    {
      label: 'ONLINE',
      value: summary.online,
      color: 'text-[#50665C]',
      dot: 'bg-[#82918B]',
    },
    {
      label: 'DEGRADED',
      value: summary.degraded,
      color: 'text-[#816D43]',
      dot: 'bg-[#B69A69]',
    },
    {
      label: 'DOWN',
      value: summary.down,
      color: 'text-[#963746]',
      dot: 'bg-[#963746]',
    },
    {
      label: 'CHECKING',
      value: summary.checking,
      color: 'text-[#50665C]',
      dot: 'bg-[#82918B]',
    },
    {
      label: 'UNKNOWN',
      value: summary.unknown,
      color: 'text-[#77736C]',
      dot: 'bg-[#948B7D]',
    },
  ]

  return (
    <section className="space-y-5">
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
          <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
            INFRASTRUCTURE STATUS / 04
          </p>

          <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
            Website Health Overview
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            Kondisi kesehatan website OPD berdasarkan hasil pemeriksaan terakhir.
          </p>
        </div>

        <p className="text-[9px] tracking-widest text-[#8B8479]">
          {formatNumber(summary.total_websites)} REGISTERED WEBSITES
        </p>
      </div>

      {/* Health status counters */}
      <div className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-[#CBD5E1] bg-[#CBD5E1] sm:grid-cols-3 lg:grid-cols-5">
        {statusCards.map((item) => (
          <div
            key={item.label}
            className="bg-[#F7F4EC] px-4 py-5 transition hover:bg-white"
          >
            <div className="flex items-center gap-2">
              <span className={`size-2 rounded-full ${item.dot}`} />

              <p className="text-[9px] font-bold tracking-[0.14em] text-[#817A70]">
                {item.label}
              </p>
            </div>

            <p className={`mt-3 font-serif text-3xl ${item.color}`}>
              {loading && summary[item.label.toLowerCase()] === undefined
                ? '—'
                : formatNumber(item.value)}
            </p>
          </div>
        ))}
      </div>

      {/* Performance metrics */}
      <div className="grid gap-3 sm:grid-cols-3">
        <article className="rounded-lg border border-[#D6DEE8] bg-white p-5">
          <p className="text-[9px] font-bold tracking-[0.15em] text-[#526176]">
            UPTIME / LAST 24 HOURS
          </p>

          <h3 className="mt-3 font-serif text-3xl text-[#191A20]">
            {formatPercent(summary.uptime_24h_percent)}
          </h3>

          <p className="mt-2 text-[10px] text-[#817A70]">
            {formatNumber(summary.successful_checks_24h)} dari{' '}
            {formatNumber(summary.checks_24h)} pemeriksaan berhasil
          </p>
        </article>

        <article className="rounded-lg border border-[#D6DEE8] bg-white p-5">
          <p className="text-[9px] font-bold tracking-[0.15em] text-[#526176]">
            UPTIME / LAST 7 DAYS
          </p>

          <h3 className="mt-3 font-serif text-3xl text-[#191A20]">
            {formatPercent(summary.uptime_7d_percent)}
          </h3>

          <p className="mt-2 text-[10px] text-[#817A70]">
            {formatNumber(summary.successful_checks_7d)} dari{' '}
            {formatNumber(summary.checks_7d)} pemeriksaan berhasil
          </p>
        </article>

        <article className="rounded-lg border border-[#D6DEE8] bg-white p-5">
          <p className="text-[9px] font-bold tracking-[0.15em] text-[#526176]">
            AVG. RESPONSE TIME / 7 DAYS
          </p>

          <h3 className="mt-3 font-serif text-3xl text-[#191A20]">
            {formatResponseTime(summary.average_response_time_7d_ms)}
          </h3>

          <p className="mt-2 text-[10px] text-[#817A70]">
            Rata-rata waktu respons pemeriksaan berhasil
          </p>
        </article>
      </div>

      {/* Website health registry */}
      <article className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="flex flex-col justify-between gap-3 border-b border-[#D8CFBF] p-5 sm:flex-row sm:items-center">
          <div>
            <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
              WEBSITE DIAGNOSTICS
            </p>

            <h3 className="mt-2 font-serif text-xl text-[#191A20]">
              Website Health Registry
            </h3>
          </div>

          <Link
            to="/websites"
            className="inline-flex items-center gap-1 border-b border-[#B69A69] pb-1 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
          >
            MANAGE WEBSITES
            <Icon name="arrow" size={13} />
          </Link>
        </div>

        {websites.length === 0 ? (
          <div className="flex min-h-[180px] flex-col items-center justify-center px-5 text-center">
            <Icon name="globe" size={25} className="text-[#B69A69]" />

            <h4 className="mt-4 font-serif text-lg text-[#191A20]">
              No Website Data
            </h4>

            <p className="mt-2 max-w-sm text-xs leading-6 text-[#817A70]">
              Belum ada website aktif yang dapat ditampilkan dalam pemantauan kesehatan.
            </p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[980px] border-collapse text-left">
              <thead>
                <tr className="border-b border-[#C9D4E2] bg-[#EEF2F7]">
                  {[
                    'WEBSITE',
                    'HEALTH STATUS',
                    'HTTP',
                    'RESPONSE',
                    'UPTIME 24H',
                    'UPTIME 7D',
                    'MONITORING',
                  ].map((heading) => (
                    <th
                      key={heading}
                      className="border-r border-[#CBD5E1] px-4 py-3.5 text-[9px] font-bold tracking-[0.12em] text-[#526176] last:border-r-0"
                    >
                      {heading}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody className="divide-y divide-[#D6DEE8]">
                {websites.map((website) => (
                  <tr
                    key={website.id}
                    className="transition odd:bg-white even:bg-[#F8FAFC] hover:bg-[#F1F5F9]"
                  >
                    <td className="max-w-[260px] border-r border-[#D6DEE8] px-4 py-4 align-middle last:border-r-0">
                      <p className="truncate text-xs font-semibold text-[#252B37]">
                        {website.name}
                      </p>

                      <p className="mt-1 truncate text-[9px] text-[#817A70]">
                        {website.opd?.name || 'OPD tidak diketahui'}
                      </p>

                      <p className="mt-1 truncate text-[9px] text-[#A0988B]">
                        {website.url}
                      </p>
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 align-middle last:border-r-0">
                      <HealthBadge status={website.health_status} />
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 text-[11px] text-[#526176] align-middle last:border-r-0">
                      {website.last_scan?.http_status_code
                        ? `HTTP ${website.last_scan.http_status_code}`
                        : '—'}
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 text-[11px] text-[#526176] align-middle last:border-r-0">
                      {formatResponseTime(
                        website.last_scan?.response_time_ms
                      )}
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 text-[11px] font-semibold text-[#50665C] align-middle last:border-r-0">
                      {formatPercent(website.uptime_24h_percent)}
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 text-[11px] font-semibold text-[#50665C] align-middle last:border-r-0">
                      {formatPercent(website.uptime_7d_percent)}
                    </td>

                    <td className="border-r border-[#D6DEE8] px-4 py-4 align-middle last:border-r-0">
                      <span
                        className={`text-[9px] font-bold tracking-wider ${
                          website.monitoring_enabled
                            ? 'text-[#50665C]'
                            : 'text-[#963746]'
                        }`}
                      >
                        {website.monitoring_enabled ? 'ENABLED' : 'DISABLED'}
                      </span>

                      <p className="mt-1 text-[9px] text-[#817A70]">
                        {website.monitoring_interval_minutes
                          ? `Every ${website.monitoring_interval_minutes} min`
                          : 'No interval'}
                      </p>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        <div className="border-t border-[#D8CFBF] px-5 py-3">
          <p className="text-[9px] leading-5 text-[#817A70]">
            Uptime dihitung berdasarkan persentase pemeriksaan terminal yang berhasil
            (HTTP 200–399) dibandingkan seluruh pemeriksaan terminal pada periode terkait.
            Nilai ini merupakan metrik berbasis pemeriksaan, bukan pengukuran uptime kontinu.
          </p>
        </div>
      </article>
    </section>
  )
}

function SecurityOverviewSection({ assessment, loading }) {
  const summary = assessment?.summary || {}

  const hasSummary = summary.total_websites !== undefined

  const metrics = [
    {
      index: '01',
      label: 'HIGH RISK',
      value: summary.high,
      description: 'Indikator risiko tinggi',
      color: 'text-[#963746]',
      dot: 'bg-[#963746]',
      border: 'border-[#963746]/30',
    },
    {
      index: '02',
      label: 'NEED REVIEW',
      value: summary.review,
      description: 'Memerlukan verifikasi',
      color: 'text-[#816D43]',
      dot: 'bg-[#B69A69]',
      border: 'border-[#B69A69]/40',
    },
    {
      index: '03',
      label: 'LOW RISK',
      value: summary.low,
      description: 'Risiko rendah terdeteksi',
      color: 'text-[#50665C]',
      dot: 'bg-[#82918B]',
      border: 'border-[#82918B]/40',
    },
    {
      index: '04',
      label: 'UNKNOWN',
      value: summary.unknown,
      description: 'Belum dapat dianalisis',
      color: 'text-[#77736C]',
      dot: 'bg-[#948B7D]',
      border: 'border-[#D8CFBF]',
    },
  ]

  return (
    <section className="space-y-5">
      {/* Section heading */}
      <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
          <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
            SECURITY INTELLIGENCE / 05
          </p>

          <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
            Security Overview
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            Ringkasan indikator keamanan berdasarkan hasil assessment
            terbaru setiap website.
          </p>
        </div>

        <Link
          to="/security"
          className="inline-flex items-center gap-2 border-b border-[#B69A69] pb-1 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
        >
          OPEN SECURITY MONITORING
          <span aria-hidden="true">↗</span>
        </Link>
      </div>

      {/* Total assessments */}
      <article className="flex flex-col justify-between gap-4 rounded-xl border border-[#D6DEE8] bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:px-6">
        <div>
          <p className="text-[9px] font-bold tracking-[0.16em] text-[#963746]">
            TOTAL SECURITY ASSESSMENTS
          </p>

          <p className="mt-2 font-serif text-4xl text-[#191A20]">
            {loading || !hasSummary
              ? '—'
              : formatNumber(summary.total_websites)}
          </p>

          <p className="mt-1 text-[11px] text-[#77736C]">
            Website dengan hasil pemeriksaan keamanan terbaru
          </p>
        </div>

        <div className="flex items-center gap-3 border-t border-[#D6DEE8] pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
          <span className="grid size-11 place-items-center border border-[#D6DEE8] bg-[#F8FAFC] text-[#963746]">
            <span className="text-xl">◈</span>
          </span>

          <div>
            <p className="text-[9px] font-bold tracking-wider text-[#963746]">
              ANALYSIS ENGINE
            </p>
            <p className="mt-1 text-xs font-semibold text-[#252B37]">
              Passive HTML Assessment
            </p>
          </div>
        </div>
      </article>

      {/* Risk counters */}
      <div className="grid grid-cols-2 gap-px border border-[#D8CFBF] bg-[#D8CFBF] lg:grid-cols-4">
        {metrics.map((item) => (
          <article
            key={item.index}
            className="bg-[#F7F4EC] px-4 py-5 transition duration-300 hover:bg-white sm:px-5"
          >
            <div className="flex items-center justify-between gap-2">
              <div className="flex min-w-0 items-center gap-2">
                <span className={`size-2 shrink-0 rounded-full ${item.dot}`} />

                <p className="truncate text-[9px] font-bold tracking-[0.13em] text-[#817A70]">
                  {item.label}
                </p>
              </div>

              <span className="font-serif text-xs text-[#C8BDAA]">
                {item.index}
              </span>
            </div>

            <p className={`mt-4 font-serif text-3xl ${item.color}`}>
              {loading || !hasSummary
                ? '—'
                : formatNumber(item.value ?? 0)}
            </p>

            <p className="mt-2 text-[9px] text-[#817A70]">
              {item.description}
            </p>
          </article>
        ))}
      </div>

      {!loading && !hasSummary && (
        <p className="border border-[#B69A69]/40 bg-[#B69A69]/10 p-3 text-[10px] text-[#665F56]">
          Ringkasan keamanan belum tersedia. Periksa koneksi Security Assessment API.
        </p>
      )}

      {/* Assessment information */}
      <div className="flex flex-col justify-between gap-3 border-l-2 border-[#B69A69] bg-[#F1EBDD] p-4 sm:flex-row sm:items-center">
        <p className="max-w-2xl text-[10px] leading-5 text-[#77736C]">
          Hasil pemeriksaan merupakan analisis indikator berbasis heuristik.
          Status risiko bukan jaminan bahwa website sepenuhnya bebas dari ancaman.
        </p>

        <Link
          to="/security"
          className="shrink-0 text-[9px] font-bold tracking-wider text-[#963746] hover:text-[#762B37]"
        >
          VIEW SECURITY REGISTER →
        </Link>
      </div>
    </section>
  )
}

function DashboardPage() {
  const [data, setData] = useState(INITIAL_DATA)
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [error, setError] = useState('')

  const loadDashboard = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const [
        summaryResponse,
        distributionResponse,
        recentResponse,
        healthResponse,
        securityResponse,
      ] = await Promise.all([
        api.get('/api/dashboard/summary'),
        api.get('/api/dashboard/technology-distribution'),
        api.get('/api/dashboard/recent-scans'),
        api.get('/api/dashboard/website-health'),

        // Security API dibuat independen agar kegagalannya
        // tidak mengganggu seluruh dashboard.
        api.get('/api/security-assessments', {
          params: { per_page: 1 },
        }).catch((securityError) => {
          console.error('Dashboard security assessment error:', securityError)
          return null
        }),
      ])

      const summary = summaryResponse.data?.data || {}
      const websiteHealth = healthResponse.data?.data || {}

      const securitySummary = securityResponse?.data?.summary || {}

      setData({
        statistics: summary.statistics || {},

        latestScan: summary.latest_successful_scan_at || null,

        distribution: distributionResponse.data?.data || [],

        recentScans: recentResponse.data?.data || [],

        websiteHealth: {
          summary: websiteHealth.summary || {},
          websites: Array.isArray(websiteHealth.websites)
            ? websiteHealth.websites
            : [],
        },

        securityAssessment: {
          summary: securitySummary,
        },
      })
    } catch (requestError) {
      console.error('Dashboard API error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Data dashboard tidak dapat dimuat. Periksa koneksi server SIMONTA.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [])

  useEffect(() => {
    loadDashboard()

    const interval = window.setInterval(() => {
      loadDashboard(true)
    }, 30000)

    return () => window.clearInterval(interval)
  }, [loadDashboard])

  const stats = data.statistics

  const metrics = [
    {
      index: '01',
      label: 'REGISTERED OPDS',
      value: stats.total_opds,
      description: `${formatNumber(stats.active_opds)} OPD berstatus aktif`,
      icon: 'layers',
    },
    {
      index: '02',
      label: 'REGISTERED WEBSITES',
      value: stats.total_websites,
      description: `${formatNumber(stats.active_websites)} website aktif`,
      icon: 'globe',
    },
    {
      index: '03',
      label: 'MONITORING ENABLED',
      value: stats.monitored_websites,
      description: 'Website dengan monitoring aktif',
      icon: 'activity',
    },
    {
      index: '04',
      label: 'TECHNOLOGY CATALOG',
      value: stats.total_technologies,
      description: `${formatNumber(stats.total_signatures)} aturan identifikasi aktif`,
      icon: 'database',
    },
  ]

  const scanCounters = [
    {
      label: 'QUEUED',
      value: stats.queued_scans,
      icon: 'clock',
      color: 'text-[#B69A69]',
    },
    {
      label: 'RUNNING',
      value: stats.running_scans,
      icon: 'activity',
      color: 'text-[#82918B]',
    },
    {
      label: 'COMPLETED',
      value: stats.completed_scans,
      icon: 'check',
      color: 'text-[#82918B]',
    },
    {
      label: 'FAILED',
      value: stats.failed_scans,
      icon: 'alert',
      color: 'text-[#963746]',
    },
  ]

  return (
    <div className="simonta-page-enter space-y-9">

      {/* Editorial page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-8">
        <div className="pointer-events-none absolute -right-3 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1] sm:text-[180px]">
          01
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / CONTROL ROOM
            </p>
          </div>

          <div className="flex flex-col justify-between gap-6 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Digital <span className="italic text-[#963746]">Observatory.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Ruang kendali untuk mengawasi, memeriksa, dan memahami
                infrastruktur digital website OPD Kota Tasikmalaya.
              </p>
            </div>

            <div className="flex flex-wrap items-center gap-3">
              <button
                type="button"
                onClick={() => loadDashboard(true)}
                disabled={refreshing || loading}
                className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-[0.12em] text-[#665F56] transition hover:-translate-y-0.5 hover:bg-[#B69A69]/10 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Icon
                  name="refresh"
                  size={14}
                  className={refreshing ? 'animate-spin' : ''}
                />
                {refreshing ? 'UPDATING' : 'REFRESH DATA'}
              </button>

              <Link
                to="/websites"
                className="simonta-angular-action inline-flex items-center gap-2 bg-[#963746] px-5 py-3 text-[9px] font-bold tracking-[0.12em] text-[#EEE9DD] transition hover:-translate-y-0.5 hover:bg-[#762B37]"
              >
                WEBSITE REGISTRY
                <Icon name="arrow" size={14} />
              </Link>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              PUBLIC WEBSITES
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              ADMINISTRATOR ACCESS
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              LIVE DATA / 30 SEC REFRESH
            </span>
          </div>
        </div>
      </section>

      {/* Error notification */}
      {error && (
        <div
          role="alert"
          className="flex flex-col justify-between gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 sm:flex-row sm:items-center"
        >
          <div className="flex items-center gap-3">
            <Icon name="alert" size={19} className="shrink-0 text-[#963746]" />
            <p className="text-xs leading-5 text-[#963746]">{error}</p>
          </div>

          <button
            type="button"
            onClick={() => loadDashboard()}
            className="simonta-angular-action self-start border border-[#963746] px-4 py-2 text-[9px] font-bold tracking-wider text-[#963746] transition hover:bg-[#963746] hover:text-white sm:self-auto"
          >
            TRY AGAIN
          </button>
        </div>
      )}

      {/* Summary metrics */}
      <section>
        <div className="mb-5 flex items-end justify-between gap-3">
          <div>
            <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
              SYSTEM OVERVIEW
            </p>

            <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
              Monitoring Summary
            </h2>
          </div>

          <p className="hidden text-[9px] tracking-widest text-[#8B8479] sm:block">
            {loading ? 'LOADING DATABASE' : 'DATABASE CONNECTED'}
          </p>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {loading && !Object.keys(stats).length
            ? [1, 2, 3, 4].map((item) => <MetricSkeleton key={item} />)
            : metrics.map((metric, index) => (
              <MetricPanel
                key={metric.index}
                {...metric}
                delay={index}
              />
            ))}
        </div>
      </section>

      {/* Scan status counters */}
      <section className="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-[#CBD5E1] bg-[#CBD5E1] md:grid-cols-4">
        {scanCounters.map((counter, index) => (
          <div
            key={counter.label}
            className="bg-[#F7F4EC] px-4 py-5 transition duration-300 hover:bg-white sm:px-5"
          >
            <div className="flex items-center gap-2">
              <Icon
                name={counter.icon}
                size={14}
                className={counter.color}
              />

              <p className="text-[9px] font-bold tracking-[0.16em] text-[#817A70]">
                {counter.label}
              </p>
            </div>

            <p className="mt-3 font-serif text-3xl text-[#191A20]">
              {formatNumber(stats[
                ['queued_scans', 'running_scans', 'completed_scans', 'failed_scans'][index]
              ])}
            </p>
          </div>
        ))}
      </section>

            {/* Website Health Monitoring */}
      <WebsiteHealthSection
        health={data.websiteHealth}
        loading={loading}
      />

      {/* Security Overview */}
<SecurityOverviewSection
  assessment={data.securityAssessment}
  loading={loading}
/>

      {/* Activity and technology distribution */}
      <section className="grid gap-5 xl:grid-cols-[1.5fr_0.85fr]">

        <article className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
          <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5">
            <div>
              <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
                INSPECTION LOG / 01
              </p>

              <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
                Monitoring Activity
              </h2>

              <p className="mt-1 text-[10px] text-[#817A70]">
                Sepuluh pemeriksaan terakhir
              </p>
            </div>

            <Link
              to="/history"
              className="inline-flex items-center gap-1 border-b border-[#B69A69] pb-1 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
            >
              VIEW ALL
              <Icon name="arrow" size={13} />
            </Link>
          </div>

          {loading && !data.recentScans.length ? (
            <div className="space-y-4 p-5">
              {[1, 2, 3].map((item) => (
                <div key={item} className="border-b border-[#D8CFBF] pb-4">
                  <div className="simonta-skeleton h-3 w-20" />
                  <div className="simonta-skeleton mt-3 h-4 w-2/3" />
                  <div className="simonta-skeleton mt-3 h-3 w-1/3" />
                </div>
              ))}
            </div>
          ) : (
            <ScanActivity scans={data.recentScans} />
          )}
        </article>

        <article className="overflow-hidden rounded-xl border border-[#D6DEE8] bg-white text-[#252B37] shadow-sm">
          <div className="border-b border-[#D6DEE8] bg-[#F8FAFC] p-5 sm:p-6">
            <p className="text-[9px] font-bold tracking-[0.18em] text-[#963746]">
              TECHNOLOGY ARCHIVE / 02
            </p>

            <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
              Technology Intelligence
            </h2>

            <p className="mt-1 text-xs leading-5 text-[#77736C]">
              Distribusi berdasarkan kategori teknologi
            </p>
          </div>

          {loading && !data.distribution.length ? (
            <div className="space-y-7 p-5">
              {[1, 2, 3, 4].map((item) => (
                <div key={item}>
                  <div className="simonta-skeleton h-3 w-32" />
                  <div className="simonta-skeleton mt-3 h-1 w-full" />
                </div>
              ))}
            </div>
          ) : (
            <TechnologyDistribution
              distribution={data.distribution}
            />
          )}
        </article>
      </section>

      {/* Monitoring protocol */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC] p-5 sm:p-7">
        <div className="mb-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            OPERATING PARAMETERS / 03
          </p>

          <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
            Monitoring Protocol
          </h2>
        </div>

        <div className="grid gap-5 border-t border-[#D8CFBF] pt-5 sm:grid-cols-3">
          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              TARGET SCOPE
            </p>

            <p className="mt-2 font-medium text-[#252B37]">
              Public Websites
            </p>
          </div>

          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              DEFAULT INTERVAL
            </p>

            <p className="mt-2 font-medium text-[#252B37]">
              10 Minutes
            </p>

            <p className="mt-1 text-[9px] text-[#817A70]">
              Default for new websites; configurable individually.
            </p>
          </div>

          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              LATEST SUCCESSFUL SCAN
            </p>

            <p className="mt-2 text-sm font-medium text-[#963746]">
              {formatDate(data.latestScan)}
            </p>
          </div>
        </div>

        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-[#D8CFBF] pt-4">
          <div className="flex items-center gap-2">
            <span className="size-2 rounded-full bg-[#82918B]" />
            <p className="text-[9px] tracking-widest text-[#50665C]">
              BACKGROUND MONITORING ARCHITECTURE CONFIGURED
            </p>
          </div>

          <Link
            to="/websites"
            className="inline-flex items-center gap-2 text-[9px] font-bold tracking-wider text-[#963746] transition hover:gap-3"
          >
            CONFIGURE WEBSITES
            <Icon name="arrow" size={13} />
          </Link>
        </div>
      </section>

      <p className="text-right text-[9px] tracking-wider text-[#948B7D]">
        DATA AUTOMATICALLY REFRESHED EVERY 30 SECONDS
      </p>

    </div>
  )
}

export default DashboardPage
