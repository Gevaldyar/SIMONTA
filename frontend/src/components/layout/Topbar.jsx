import InterfaceIcon from '../common/InterfaceIcon'
import NotificationCenter from './NotificationCenter'
import ThemeToggle from './ThemeToggle'

function Topbar({
  user,
  onMenuClick,
  onToggleCollapse,
  isSidebarCollapsed,
  onLogout,
  isLoggingOut,
  theme = 'light',
  onToggleTheme,
}) {
  const initials = user?.name
    ?.split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('') || 'AD'

  return (
    <header className="simonta-topbar sticky top-0 z-40 flex min-h-[76px] items-center justify-between gap-3 border-b px-4 sm:px-6 xl:px-8">
      {/* Left: navigation controls and system identity */}
      <div className="flex min-w-0 items-center gap-3 sm:gap-4">
        <button
          type="button"
          onClick={onMenuClick}
          aria-label="Buka navigasi utama"
          className="simonta-topbar-control simonta-topbar-menu grid size-10 shrink-0 place-items-center md:hidden"
        >
          <InterfaceIcon name="menu" size={20} />
        </button>

        <button
          type="button"
          onClick={onToggleCollapse}
          aria-label={isSidebarCollapsed ? 'Perbesar sidebar' : 'Minimize sidebar'}
          title={isSidebarCollapsed ? 'Perbesar sidebar' : 'Minimize sidebar'}
          aria-pressed={isSidebarCollapsed}
          className="simonta-topbar-control simonta-topbar-menu hidden size-10 shrink-0 place-items-center md:grid"
        >
          <svg
            width="19"
            height="19"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
          >
            <rect x="3" y="4" width="18" height="16" rx="1.5" />
            <path d="M9 4v16" />
            {isSidebarCollapsed
              ? <path d="m13 9 3 3-3 3" />
              : <path d="m16 9-3 3 3 3" />}
          </svg>
        </button>

        <span className="simonta-topbar-divider hidden h-9 w-px sm:block" />

        <div className="min-w-0">
          <div className="flex min-w-0 items-center gap-2">
            <span className="simonta-topbar-kicker">DISKOMINFO</span>
            <span className="simonta-topbar-slash">/</span>
            <span className="simonta-topbar-kicker simonta-topbar-kicker-muted truncate">
              DIGITAL INFRASTRUCTURE
            </span>
          </div>
          <p className="simonta-topbar-subtitle mt-1 truncate">
            Administrator Console
          </p>
        </div>
      </div>

      {/* Right: session, preferences, notifications and account */}
      <div className="flex shrink-0 items-center gap-2 sm:gap-3 lg:gap-4">
        <div className="simonta-topbar-session hidden items-center gap-2 xl:flex">
          <span className="simonta-session-indicator" />
          <span>ADMIN SESSION</span>
          <span className="simonta-session-state">ACTIVE</span>
        </div>

        <span className="simonta-topbar-divider hidden h-8 w-px xl:block" />

        <div className="flex items-center gap-2">
          <ThemeToggle theme={theme} onToggle={onToggleTheme} />
          <NotificationCenter />
        </div>

        <span className="simonta-topbar-divider hidden h-8 w-px sm:block" />

        <div className="simonta-topbar-profile flex min-w-0 items-center gap-2 sm:gap-3">
          <div className="simonta-topbar-avatar grid size-10 shrink-0 place-items-center">
            {initials}
          </div>

          <div className="hidden min-w-0 max-w-[168px] lg:block">
            <p className="simonta-topbar-user truncate">
              {user?.name || 'Administrator SIMONTA'}
            </p>
            <p className="simonta-topbar-email truncate">
              {user?.email || 'admin@simonta.local'}
            </p>
          </div>
        </div>

        <button
          type="button"
          onClick={onLogout}
          disabled={isLoggingOut}
          title={isLoggingOut ? 'Sedang keluar...' : 'Logout'}
          aria-label={isLoggingOut ? 'Sedang keluar' : 'Logout'}
          className="simonta-topbar-logout inline-flex h-10 shrink-0 items-center justify-center gap-2 px-3 sm:px-4"
        >
          <InterfaceIcon name="logout" size={16} />
          <span className="hidden text-xs font-semibold sm:inline">
            {isLoggingOut ? 'Signing out...' : 'Logout'}
          </span>
        </button>
      </div>
    </header>
  )
}

export default Topbar
