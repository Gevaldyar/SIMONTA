import { useCallback, useEffect, useState } from 'react'
import { useNavigate } from 'react-router'

import api from '../../services/api'

const PER_PAGE = 15

const TYPE_OPTIONS = [
  { value: '', label: 'Semua Jenis' },
  { value: 'scan_failed', label: 'Pemindaian Gagal' },
  { value: 'http_non_success', label: 'HTTP Error' },
  { value: 'technology_added', label: 'Teknologi Baru' },
  { value: 'technology_removed', label: 'Teknologi Dihapus' },
  {
    value: 'technology_version_changed',
    label: 'Perubahan Versi Teknologi',
  },
  {
  value: 'security_risk_detected',
  label: 'Risiko Keamanan Website',
},
  {
    value: 'domain_expiry_warning',
    label: 'Peringatan Kedaluwarsa Domain',
  },
  {
    value: 'domain_expired',
    label: 'Domain Kedaluwarsa',
  },
  {
    value: 'ssl_expiry_warning',
    label: 'Peringatan Kedaluwarsa SSL',
  },
  {
    value: 'ssl_expired',
    label: 'Sertifikat SSL Kedaluwarsa',
  },
  {
    value: 'ssl_verification_failed',
    label: 'Verifikasi SSL Gagal',
  },
  {
    value: 'ssl_verification_restored',
    label: 'Verifikasi SSL Pulih',
  },
]

const SEVERITY_STYLES = {
  danger: {
    label: 'DANGER',
    className: 'border-[#963746]/30 bg-[#963746]/10 text-[#963746]',
    dot: 'bg-[#963746]',
  },
  warning: {
    label: 'WARNING',
    className: 'border-[#B68A42]/40 bg-[#B68A42]/10 text-[#816D43]',
    dot: 'bg-[#B68A42]',
  },
  info: {
    label: 'INFO',
    className: 'border-[#526D82]/30 bg-[#526D82]/10 text-[#526D82]',
    dot: 'bg-[#526D82]',
  },
}

function formatDate(value) {
  if (!value) return 'Waktu tidak tersedia'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Waktu tidak tersedia'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function formatType(type) {
  return (
    TYPE_OPTIONS.find((item) => item.value === type)?.label ||
    'Aktivitas Sistem'
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

function getDestination(notification) {
  const destination = notification.data?.url
  const eventType = notification.data?.type || notification.type || ''

  if (
    typeof destination === 'string' &&
    destination.startsWith('/') &&
    !destination.startsWith('//')
  ) {
    return destination
  }

  if (
    eventType.startsWith('domain_') ||
    eventType.startsWith('ssl_')
  ) {
    return '/domain-ssl'
  }

  if (eventType.startsWith('technology_')) {
    return '/technology-changes'
  }

  return '/history'
}

function NotificationHistoryPage() {
  const navigate = useNavigate()

  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)

  const [page, setPage] = useState(1)
  const [meta, setMeta] = useState({
    current_page: 1,
    last_page: 1,
    per_page: PER_PAGE,
    total: 0,
  })

  const [readFilter, setReadFilter] = useState('all')
  const [typeFilter, setTypeFilter] = useState('')

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)
  const [markingAll, setMarkingAll] = useState(false)
  const [markingId, setMarkingId] = useState(null)

  const [error, setError] = useState('')

  const loadNotifications = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const response = await api.get('/api/notifications', {
        params: {
          per_page: PER_PAGE,
          page,
          unread: readFilter === 'unread' ? 1 : undefined,
          type: typeFilter || undefined,
        },
      })

      const responseData = response.data || {}

      setNotifications(
        Array.isArray(responseData.data)
          ? responseData.data
          : []
      )

      setUnreadCount(
        Number(responseData.unread_count || 0)
      )

      setMeta({
        current_page: Number(
          responseData.meta?.current_page || 1
        ),
        last_page: Number(
          responseData.meta?.last_page || 1
        ),
        per_page: Number(
          responseData.meta?.per_page || PER_PAGE
        ),
        total: Number(
          responseData.meta?.total || 0
        ),
      })
    } catch (requestError) {
      console.error(
        'Notification history error:',
        requestError
      )

      setError(
        requestError.response?.data?.message ||
        'Riwayat notifikasi gagal dimuat. Periksa koneksi server.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [page, readFilter, typeFilter])

  useEffect(() => {
    loadNotifications()
  }, [loadNotifications])

  const handleReadFilter = (value) => {
    setReadFilter(value)
    setPage(1)
  }

  const handleTypeFilter = (value) => {
    setTypeFilter(value)
    setPage(1)
  }

  const handleMarkAsRead = async (notification) => {
    if (notification.read_at || markingId) return

    setMarkingId(notification.id)
    setError('')

    try {
      const response = await api.patch(
        `/api/notifications/${notification.id}/read`
      )

      const updatedNotification = response.data?.data

      setNotifications((previous) =>
        previous.map((item) =>
          item.id === notification.id
            ? {
                ...item,
                ...(updatedNotification || {}),
                read_at:
                  updatedNotification?.read_at ||
                  new Date().toISOString(),
              }
            : item
        )
      )

      setUnreadCount(
        Number(response.data?.unread_count ?? 0)
      )
    } catch (requestError) {
      console.error(
        'Mark notification as read error:',
        requestError
      )

      setError(
        'Notifikasi gagal diperbarui. Silakan coba kembali.'
      )
    } finally {
      setMarkingId(null)
    }
  }

  const handleOpenNotification = async (notification) => {
    if (!notification.read_at) {
      await handleMarkAsRead(notification)
    }

    navigate(getDestination(notification))
  }

  const handleMarkAllAsRead = async () => {
    if (unreadCount === 0 || markingAll) return

    setMarkingAll(true)
    setError('')

    try {
      await api.patch('/api/notifications/read-all')

      setUnreadCount(0)

      if (readFilter === 'unread') {
        setNotifications([])
      } else {
        const readAt = new Date().toISOString()

        setNotifications((previous) =>
          previous.map((item) => ({
            ...item,
            read_at: item.read_at || readAt,
          }))
        )
      }

      await loadNotifications(true)
    } catch (requestError) {
      console.error(
        'Mark all notifications error:',
        requestError
      )

      setError(
        'Semua notifikasi gagal diperbarui. Silakan coba kembali.'
      )
    } finally {
      setMarkingAll(false)
    }
  }

  const handlePreviousPage = () => {
    setPage((current) => Math.max(1, current - 1))
  }

  const handleNextPage = () => {
    setPage((current) =>
      Math.min(meta.last_page, current + 1)
    )
  }

  const firstItem = meta.total === 0
    ? 0
    : (meta.current_page - 1) * meta.per_page + 1

  const lastItem = Math.min(
    meta.current_page * meta.per_page,
    meta.total
  )

  return (
    <div className="simonta-page-enter space-y-8">

      {/* Page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-3 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1] sm:text-[180px]">
          05
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SYSTEM ACTIVITY / ARCHIVE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-4xl font-normal tracking-tight text-[#191A20] sm:text-5xl">
                Notification <span className="italic text-[#963746]">History.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Arsip pemberitahuan aktivitas, perubahan teknologi,
                dan kondisi website yang dipantau oleh SIMONTA.
              </p>
            </div>

            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => loadNotifications(true)}
                disabled={refreshing || loading}
                className="simonta-angular-action inline-flex items-center gap-2 border border-[#B69A69] px-4 py-3 text-[9px] font-bold tracking-[0.12em] text-[#665F56] transition hover:bg-[#B69A69]/10 disabled:opacity-50"
              >
                <span className={refreshing ? 'animate-spin' : ''}>
                  ↻
                </span>
                {refreshing ? 'UPDATING...' : 'REFRESH'}
              </button>

              <button
                type="button"
                onClick={handleMarkAllAsRead}
                disabled={unreadCount === 0 || markingAll}
                className="simonta-angular-action inline-flex items-center gap-2 bg-[#963746] px-4 py-3 text-[9px] font-bold tracking-[0.12em] text-[#EEE9DD] transition hover:bg-[#762B37] disabled:cursor-not-allowed disabled:opacity-40"
              >
                {markingAll ? 'PROCESSING...' : 'MARK ALL READ'}
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Summary */}
      <section className="grid gap-px border border-[#D8CFBF] bg-[#D8CFBF] sm:grid-cols-2">
        <div className="bg-[#F7F4EC] p-5">
          <p className="text-[9px] font-bold tracking-[0.16em] text-[#817A70]">
            TOTAL NOTIFICATIONS
          </p>

          <h2 className="mt-3 font-serif text-3xl text-[#191A20]">
            {formatNumber(meta.total)}
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            Total berdasarkan filter aktif
          </p>
        </div>

        <div className="bg-[#F7F4EC] p-5">
          <p className="text-[9px] font-bold tracking-[0.16em] text-[#817A70]">
            UNREAD NOTIFICATIONS
          </p>

          <h2 className="mt-3 font-serif text-3xl text-[#963746]">
            {formatNumber(unreadCount)}
          </h2>

          <p className="mt-1 text-[10px] text-[#817A70]">
            Pemberitahuan yang belum dibaca
          </p>
        </div>
      </section>

      {/* Filters */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC] p-4 sm:p-5">
        <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
          <div>
            <p className="text-[9px] font-semibold tracking-[0.18em] text-[#963746]">
              ARCHIVE FILTERS
            </p>

            <h2 className="mt-1 font-serif text-xl text-[#191A20]">
              Browse Notifications
            </h2>

            <div className="mt-4 inline-flex border border-[#D8CFBF]">
              <button
                type="button"
                onClick={() => handleReadFilter('all')}
                className={`px-4 py-2.5 text-[9px] font-bold tracking-wider transition ${
                  readFilter === 'all'
                    ? 'bg-[#252B37] text-[#EEE9DD]'
                    : 'text-[#665F56] hover:bg-[#EEE8DA]'
                }`}
              >
                ALL
              </button>

              <button
                type="button"
                onClick={() => handleReadFilter('unread')}
                className={`border-l border-[#D8CFBF] px-4 py-2.5 text-[9px] font-bold tracking-wider transition ${
                  readFilter === 'unread'
                    ? 'bg-[#252B37] text-[#EEE9DD]'
                    : 'text-[#665F56] hover:bg-[#EEE8DA]'
                }`}
              >
                UNREAD
              </button>
            </div>
          </div>

          <div className="w-full lg:max-w-xs">
            <label
              htmlFor="notification-type-filter"
              className="mb-2 block text-[9px] font-bold tracking-[0.14em] text-[#817A70]"
            >
              NOTIFICATION TYPE
            </label>

            <select
              id="notification-type-filter"
              value={typeFilter}
              onChange={(event) => handleTypeFilter(event.target.value)}
              className="w-full border border-[#D8CFBF] bg-[#EEE9DD] px-3 py-3 text-xs text-[#252B37] outline-none transition focus:border-[#963746]"
            >
              {TYPE_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* Error */}
      {error && (
        <div
          role="alert"
          className="flex flex-col justify-between gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 sm:flex-row sm:items-center"
        >
          <p className="text-xs leading-5 text-[#963746]">
            {error}
          </p>

          <button
            type="button"
            onClick={() => loadNotifications()}
            className="text-[9px] font-bold tracking-wider text-[#963746] underline"
          >
            TRY AGAIN
          </button>
        </div>
      )}

      {/* Notification records */}
      <section className="overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC]">
        <div className="flex items-center justify-between border-b border-[#D8CFBF] px-5 py-4">
          <div>
            <p className="text-[9px] font-semibold tracking-[0.18em] text-[#963746]">
              CHRONOLOGICAL RECORDS
            </p>

            <h2 className="mt-1 font-serif text-xl text-[#191A20]">
              Notification Records
            </h2>
          </div>

          <span className="text-[9px] tracking-wider text-[#817A70]">
            {formatNumber(meta.total)} RECORDS
          </span>
        </div>

        {loading ? (
          <div className="space-y-4 p-5">
            {[1, 2, 3, 4].map((item) => (
              <div
                key={item}
                className="border-b border-[#D8CFBF] pb-5"
              >
                <div className="simonta-skeleton h-3 w-24" />
                <div className="simonta-skeleton mt-3 h-4 w-2/3" />
                <div className="simonta-skeleton mt-3 h-3 w-1/2" />
              </div>
            ))}
          </div>
        ) : notifications.length === 0 ? (
          <div className="flex min-h-[280px] flex-col items-center justify-center px-5 text-center">
            <div className="grid size-14 place-items-center border border-[#D8CFBF] font-serif text-2xl text-[#B69A69]">
              N
            </div>

            <h3 className="mt-5 font-serif text-xl text-[#191A20]">
              No Notifications Found
            </h3>

            <p className="mt-2 max-w-sm text-xs leading-6 text-[#817A70]">
              Tidak ada notifikasi yang sesuai dengan filter yang dipilih.
            </p>

            {(readFilter === 'unread' || typeFilter) && (
              <button
                type="button"
                onClick={() => {
                  setReadFilter('all')
                  setTypeFilter('')
                  setPage(1)
                }}
                className="mt-5 border-b border-[#B69A69] pb-1 text-[9px] font-bold tracking-wider text-[#963746]"
              >
                CLEAR FILTERS
              </button>
            )}
          </div>
        ) : (
          <div className="divide-y divide-[#D8CFBF]">
            {notifications.map((notification) => {
              const severity =
                SEVERITY_STYLES[notification.severity] ||
                SEVERITY_STYLES.info

              const isUnread = !notification.read_at
              const securityStatus = notification.data?.security_status

              const notificationData = notification.data || {}

const eventType = notificationData.type || notification.type || ''

const isDomainSslEvent = [
  'domain_expiry_warning',
  'domain_expired',
  'ssl_expiry_warning',
  'ssl_expired',
  'ssl_verification_failed',
  'ssl_verification_restored',
].includes(eventType)

const securityStatusLabel = {
  high: 'HIGH RISK',
  review: 'NEED REVIEW',
}

              return (
                <article
                  key={notification.id}
                  className={`group p-5 transition duration-200 hover:bg-[#F1EBDD] ${
                    isUnread ? 'bg-[#EEE8DA]/50' : ''
                  }`}
                >
                  <div className="flex flex-col justify-between gap-4 sm:flex-row">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <span
                          className={`inline-flex items-center gap-1.5 border px-2 py-1 text-[8px] font-bold tracking-wider ${severity.className}`}
                        >
                          <span className={`size-1.5 rounded-full ${severity.dot}`} />
                          {severity.label}
                        </span>

                        <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] tracking-wider text-[#817A70]">
                          {formatType(
  notification.data?.type || notification.type
)}
                        </span>

                        {isUnread ? (
                          <span className="text-[8px] font-bold tracking-wider text-[#963746]">
                            UNREAD
                          </span>
                        ) : (
                          <span className="text-[8px] tracking-wider text-[#948B7D]">
                            READ
                          </span>
                        )}
                      </div>

                      <h3 className="mt-3 font-medium text-sm text-[#252B37]">
                        {notification.title || 'Notifikasi'}
                      </h3>

                      <p className="mt-1 max-w-3xl text-xs leading-6 text-[#68645D]">
                        {notification.message}
                      </p>

                      <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-[9px] text-[#918879]">
  <span>
    {formatDate(notification.created_at)}
  </span>

  {notification.data?.website_name && (
    <span>
      WEBSITE: {notification.data.website_name}
    </span>
  )}

  {notification.data?.opd_name && (
    <span>
      OPD: {notification.data.opd_name}
    </span>
  )}

  {notification.data?.http_status_code && (
    <span>
      HTTP {notification.data.http_status_code}
    </span>
  )}

  {notification.data?.technology_name && (
    <span>
      TECHNOLOGY: {notification.data.technology_name}
    </span>
  )}

  {securityStatus && (
    <span className="border border-[#963746]/30 bg-[#963746]/5 px-2 py-1 font-bold tracking-wider text-[#963746]">
      {securityStatusLabel[securityStatus] || securityStatus.toUpperCase()}
    </span>
  )}

  {notification.data?.risk_score !== null &&
    notification.data?.risk_score !== undefined && (
      <span className="font-semibold text-[#963746]">
        RISK SCORE: {notification.data.risk_score}/100
      </span>
    )}

  {notification.data?.finding_count !== undefined &&
    notification.data?.finding_count !== null && (
      <span>
        FINDINGS: {formatNumber(notification.data.finding_count)}
      </span>
    )}

      {isDomainSslEvent && (
    <>
      {notificationData.domain && (
        <span>
          DOMAIN: {notificationData.domain}
        </span>
      )}

      {notificationData.hostname && (
        <span>
          HOST: {notificationData.hostname}
        </span>
      )}

      {notificationData.issuer && (
        <span>
          ISSUER: {notificationData.issuer}
        </span>
      )}

      {notificationData.days_remaining !== null &&
        notificationData.days_remaining !== undefined && (
          <span className="font-semibold text-[#816D43]">
            {Number(notificationData.days_remaining) < 0
              ? `${Math.abs(Number(notificationData.days_remaining))} DAYS OVERDUE`
              : `${notificationData.days_remaining} DAYS REMAINING`}
          </span>
        )}

      {(notificationData.expires_at || notificationData.valid_to) && (
        <span>
          EXPIRES: {formatDate(
            notificationData.expires_at || notificationData.valid_to
          )}
        </span>
      )}

      {notificationData.verification && (
        <span>
          TLS: {String(notificationData.verification).toUpperCase()}
        </span>
      )}

      {notificationData.threshold_days !== null &&
        notificationData.threshold_days !== undefined && (
          <span>
            THRESHOLD: {notificationData.threshold_days} DAYS
          </span>
        )}
    </>
  )}
</div>
                    </div>

                    <div className="flex shrink-0 items-center gap-2 sm:flex-col sm:items-end sm:justify-center">
                      {isUnread && (
                        <button
                          type="button"
                          onClick={() => handleMarkAsRead(notification)}
                          disabled={markingId === notification.id}
                          className="border border-[#D8CFBF] px-3 py-2 text-[8px] font-bold tracking-wider text-[#665F56] transition hover:border-[#B69A69] hover:bg-white disabled:opacity-50"
                        >
                          {markingId === notification.id
                            ? 'UPDATING...'
                            : 'MARK AS READ'}
                        </button>
                      )}

                      <button
                        type="button"
                        onClick={() => handleOpenNotification(notification)}
                        className="border-b border-[#B69A69] px-1 py-2 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
                      >
                        OPEN EVENT ↗
                      </button>
                    </div>
                  </div>
                </article>
              )
            })}
          </div>
        )}

        {/* Pagination */}
        {!loading && meta.total > 0 && (
          <div className="flex flex-col justify-between gap-4 border-t border-[#D8CFBF] bg-[#EEE9DD] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[9px] tracking-wider text-[#817A70]">
              SHOWING {formatNumber(firstItem)}–{formatNumber(lastItem)}
              {' '}OF {formatNumber(meta.total)} NOTIFICATIONS
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={handlePreviousPage}
                disabled={page <= 1}
                className="border border-[#D0C4B2] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                PREVIOUS
              </button>

              <span className="min-w-20 text-center text-[10px] text-[#665F56]">
                PAGE {meta.current_page} / {meta.last_page}
              </span>

              <button
                type="button"
                onClick={handleNextPage}
                disabled={page >= meta.last_page}
                className="border border-[#D0C4B2] px-3 py-2 text-[9px] font-bold tracking-wider text-[#665F56] transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40"
              >
                NEXT
              </button>
            </div>
          </div>
        )}
      </section>

      <p className="text-right text-[9px] tracking-wider text-[#948B7D]">
        SIMONTA / NOTIFICATION ARCHIVE
      </p>
    </div>
  )
}

export default NotificationHistoryPage