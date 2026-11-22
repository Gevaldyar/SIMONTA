
import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import api from '../../services/api'

function BellIcon({ size = 20 }) {
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
      <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
      <path d="M10 21h4" />
    </svg>
  )
}

function SeverityIcon({ severity }) {
  const colors = {
    danger: 'bg-[#963746]',
    warning: 'bg-[#B68A42]',
    info: 'bg-[#526D82]',
  }

  return (
    <span
      className={`mt-1.5 size-2 shrink-0 rounded-full ${
        colors[severity] || colors.info
      }`}
    />
  )
}

function formatDate(value) {
  if (!value) return 'Waktu tidak tersedia'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return 'Waktu tidak tersedia'
  }

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function getNotificationEventType(notification) {
  return notification.data?.type || notification.type || ''
}

function isDomainSslNotification(notification) {
  const type = getNotificationEventType(notification)

  return [
    'domain_expiry_warning',
    'domain_expired',
    'ssl_expiry_warning',
    'ssl_expired',
    'ssl_verification_failed',
    'ssl_verification_restored',
  ].includes(type)
}

function NotificationCenter() {
  const navigate = useNavigate()
  const containerRef = useRef(null)

  const [isOpen, setIsOpen] = useState(false)
  const [notifications, setNotifications] = useState([])
  const [unreadCount, setUnreadCount] = useState(0)

  const [isLoading, setIsLoading] = useState(true)
  const [isMarkingAll, setIsMarkingAll] = useState(false)
  const [markingId, setMarkingId] = useState(null)

  const [error, setError] = useState('')

  const loadNotifications = useCallback(async (silent = false) => {
    if (!silent) setIsLoading(true)

    try {
      const response = await api.get('/api/notifications', {
        params: {
          per_page: 8,
        },
      })

      setNotifications(response.data?.data || [])
      setUnreadCount(Number(response.data?.unread_count || 0))
      setError('')
    } catch (err) {
      console.error('Load notifications error:', err)

      if (!silent || isOpen) {
        setError(
          'Notifikasi gagal dimuat. Periksa koneksi ke server.'
        )
      }
    } finally {
      if (!silent) setIsLoading(false)
    }
  }, [isOpen])

  // Ambil data saat komponen dipasang dan perbarui setiap 30 detik.
  useEffect(() => {
    loadNotifications()

    const intervalId = window.setInterval(() => {
      loadNotifications(true)
    }, 30000)

    return () => window.clearInterval(intervalId)
  }, [loadNotifications])

  // Perbarui data ketika dropdown dibuka.
  useEffect(() => {
    if (isOpen) {
      loadNotifications(true)
    }
  }, [isOpen, loadNotifications])

  // Tutup dropdown ketika pengguna klik di luar panel atau menekan Escape.
  useEffect(() => {
    if (!isOpen) return undefined

    const handleOutsideClick = (event) => {
      if (!containerRef.current?.contains(event.target)) {
        setIsOpen(false)
      }
    }

    const handleEscape = (event) => {
      if (event.key === 'Escape') {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleOutsideClick)
    document.addEventListener('keydown', handleEscape)

    return () => {
      document.removeEventListener('mousedown', handleOutsideClick)
      document.removeEventListener('keydown', handleEscape)
    }
  }, [isOpen])

  const handleMarkAsRead = async (notification) => {
    const isUnread = !notification.read_at

    if (isUnread) {
      setMarkingId(notification.id)
      setError('')

      try {
        const response = await api.patch(
          `/api/notifications/${notification.id}/read`
        )

        setNotifications((previous) =>
          previous.map((item) =>
            item.id === notification.id
              ? {
                  ...item,
                  read_at:
                    response.data?.data?.read_at ||
                    new Date().toISOString(),
                }
              : item
          )
        )

        setUnreadCount(
          Number(response.data?.unread_count ?? 0)
        )
      } catch (err) {
        console.error('Mark notification as read error:', err)

        setError(
          'Notifikasi gagal diperbarui. Silakan coba kembali.'
        )

        return
      } finally {
        setMarkingId(null)
      }
    }

    const destination = notification.data?.url
const eventType = getNotificationEventType(notification)

const isSafeInternalPath =
  typeof destination === 'string' &&
  destination.startsWith('/') &&
  !destination.startsWith('//')

const isDomainSslEvent =
  eventType.startsWith('domain_') ||
  eventType.startsWith('ssl_')

const safeDestination = isSafeInternalPath
  ? destination
  : isDomainSslEvent
    ? '/domain-ssl'
    : '/history'

    setIsOpen(false)
    navigate(safeDestination)
  }

  const handleMarkAllAsRead = async () => {
    if (unreadCount === 0 || isMarkingAll) return

    setIsMarkingAll(true)
    setError('')

    try {
      const response = await api.patch(
        '/api/notifications/read-all'
      )

      const readAt = new Date().toISOString()

      setNotifications((previous) =>
        previous.map((item) => ({
          ...item,
          read_at: item.read_at || readAt,
        }))
      )

      setUnreadCount(
        Number(response.data?.unread_count ?? 0)
      )
    } catch (err) {
      console.error('Mark all notifications error:', err)

      setError(
        'Semua notifikasi gagal diperbarui. Silakan coba kembali.'
      )
    } finally {
      setIsMarkingAll(false)
    }
  }

  return (
    <div ref={containerRef} className="relative">
      {/* Notification trigger */}
      <button
        type="button"
        onClick={() => setIsOpen((previous) => !previous)}
        aria-label={`Notifikasi, ${unreadCount} belum dibaca`}
        aria-expanded={isOpen}
        aria-haspopup="true"
        title="Notifications"
        className="
          simonta-icon-action relative grid size-9
          place-items-center border border-[#D8CFBF]
          text-[#252B37] transition
          hover:border-[#B69A69] hover:bg-white/50
          hover:text-[#963746]
        "
      >
        <BellIcon />

        {unreadCount > 0 && (
          <span
            className="
              absolute -right-1.5 -top-1.5 grid
              min-h-[18px] min-w-[18px] place-items-center
              rounded-full border-2 border-[#EEE9DD]
              bg-[#963746] px-1 text-[9px] font-bold
              leading-none text-white
            "
          >
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Notification dropdown */}
      {isOpen && (
        <div
          role="dialog"
          aria-label="Notification Center"
          className="
            absolute right-0 top-full z-50 mt-3
            w-[min(390px,calc(100vw-2rem))]
            overflow-hidden border border-[#CFC4B2]
            bg-[#F6F2E9] text-[#191A20]
            shadow-[0_18px_55px_rgba(25,26,32,0.18)]
          "
        >
          {/* Header */}
          <div className="flex items-center justify-between border-b border-[#D8CFBF] px-4 py-4">
            <div>
              <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
                SYSTEM ACTIVITY
              </p>

              <h3 className="mt-1 font-serif text-lg text-[#252B37]">
                Notifications
              </h3>

              <p className="mt-0.5 text-[10px] text-[#77736C]">
                {unreadCount} belum dibaca
              </p>
            </div>

            <button
              type="button"
              onClick={handleMarkAllAsRead}
              disabled={unreadCount === 0 || isMarkingAll}
              className="
                border border-[#B69A69] px-2.5 py-2
                text-[9px] font-semibold tracking-wider
                text-[#765E36] transition
                hover:bg-[#EAE1D1]
                disabled:cursor-not-allowed
                disabled:opacity-40
              "
            >
              {isMarkingAll ? 'PROCESSING...' : 'MARK ALL READ'}
            </button>
          </div>

          {/* Error */}
          {error && (
            <div
              role="alert"
              className="border-b border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700"
            >
              {error}
            </div>
          )}

          {/* Notification list */}
          <div className="max-h-[420px] overflow-y-auto">
            {isLoading ? (
              <div className="px-5 py-10 text-center">
                <div className="mx-auto size-5 animate-spin rounded-full border-2 border-[#D8CFBF] border-t-[#963746]" />

                <p className="mt-3 text-xs text-[#77736C]">
                  Loading notifications...
                </p>
              </div>
            ) : notifications.length === 0 ? (
              <div className="px-5 py-12 text-center">
                <div className="mx-auto grid size-11 place-items-center border border-[#D8CFBF] text-[#A49A89]">
                  <BellIcon size={19} />
                </div>

                <p className="mt-4 font-serif text-base text-[#252B37]">
                  All clear
                </p>

                <p className="mt-1 text-xs text-[#77736C]">
                  Belum ada notifikasi aktivitas sistem.
                </p>
              </div>
            ) : (
              notifications.map((notification) => {
  const isUnread = !notification.read_at
  const notificationData = notification.data || {}

  const eventType = getNotificationEventType(notification)

const isSecurityRisk =
  eventType === 'security_risk_detected'

const isDomainSslEvent =
  isDomainSslNotification(notification)

  return (
                  <button
                    key={notification.id}
                    type="button"
                    onClick={() => handleMarkAsRead(notification)}
                    disabled={markingId === notification.id}
                    className={`
                      flex w-full gap-3 border-b border-[#E3DCCF]
                      px-4 py-4 text-left transition
                      hover:bg-[#EDE6D9]
                      disabled:cursor-wait disabled:opacity-60
                      ${
                        isUnread
                          ? 'bg-[#EEE8DA]/70'
                          : 'bg-transparent'
                      }
                    `}
                  >
                    <SeverityIcon
                      severity={notification.severity}
                    />

                    <span className="min-w-0 flex-1">
                      <span className="flex items-start justify-between gap-2">
                        <span className="text-xs font-semibold leading-relaxed text-[#252B37]">
                          {notification.title}
                        </span>

                        {isUnread && (
                          <span className="mt-1 size-1.5 shrink-0 rounded-full bg-[#963746]" />
                        )}
                      </span>

                      <span className="mt-1 block text-[11px] leading-relaxed text-[#68645D]">
                        {notification.message}
                      </span>

                      {isSecurityRisk && (
  <span className="mt-2 flex flex-wrap gap-1.5">
    {notificationData.security_status && (
      <span className="border border-[#963746]/30 bg-[#963746]/5 px-2 py-1 text-[8px] font-bold tracking-wider text-[#963746]">
        {notificationData.security_status === 'high'
          ? 'HIGH RISK'
          : 'NEED REVIEW'}
      </span>
    )}

    {isDomainSslEvent && (
  <span className="mt-2 flex flex-wrap gap-1.5">
    {notificationData.domain && (
      <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
        DOMAIN: {notificationData.domain}
      </span>
    )}

    {notificationData.hostname && (
      <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
        HOST: {notificationData.hostname}
      </span>
    )}

    {notificationData.issuer && (
      <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
        ISSUER: {notificationData.issuer}
      </span>
    )}

    {notificationData.days_remaining !== null &&
      notificationData.days_remaining !== undefined && (
        <span className="border border-[#B68A42]/40 bg-[#B68A42]/5 px-2 py-1 text-[8px] font-semibold text-[#816D43]">
          {Number(notificationData.days_remaining) < 0
            ? `${Math.abs(Number(notificationData.days_remaining))} DAYS OVERDUE`
            : `${notificationData.days_remaining} DAYS REMAINING`}
        </span>
      )}

    {(notificationData.expires_at || notificationData.valid_to) && (
      <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
        EXPIRES: {formatDate(
          notificationData.expires_at || notificationData.valid_to
        )}
      </span>
    )}

    {notificationData.verification && (
      <span className="border border-[#526D82]/30 bg-[#526D82]/5 px-2 py-1 text-[8px] font-semibold text-[#526D82]">
        TLS: {String(notificationData.verification).toUpperCase()}
      </span>
    )}

    {notificationData.threshold_days !== null &&
      notificationData.threshold_days !== undefined && (
        <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
          THRESHOLD: {notificationData.threshold_days} DAYS
        </span>
      )}
  </span>
)}

    {notificationData.risk_score !== null &&
      notificationData.risk_score !== undefined && (
        <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
          SCORE: {notificationData.risk_score}/100
        </span>
      )}

    {notificationData.finding_count !== undefined &&
      notificationData.finding_count !== null && (
        <span className="border border-[#D8CFBF] px-2 py-1 text-[8px] font-semibold text-[#665F56]">
          {notificationData.finding_count} FINDINGS
        </span>
      )}
  </span>
)}

                      <span className="mt-2 block text-[9px] tracking-wide text-[#918879]">
                        {formatDate(notification.created_at)}
                      </span>

                      {markingId === notification.id && (
                        <span className="mt-1 block text-[9px] text-[#963746]">
                          Updating...
                        </span>
                      )}
                    </span>
                  </button>
                )
              })
            )}
          </div>

          {/* Footer */}
          <div className="flex items-center justify-between border-t border-[#D8CFBF] bg-[#EAE3D7] px-4 py-3">
            <span className="text-[9px] tracking-wider text-[#77736C]">
              SIMONTA / ACTIVITY LOG
            </span>

            <button
              type="button"
              onClick={() => {
                setIsOpen(false)
                navigate('/notifications')
              }}
              className="
                text-[9px] font-semibold tracking-wider
                text-[#963746] transition
                hover:text-[#252B37]
              "
            >
              VIEW HISTORY →
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default NotificationCenter
