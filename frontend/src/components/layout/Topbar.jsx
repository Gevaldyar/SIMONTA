
import InterfaceIcon from '../common/InterfaceIcon'

function Topbar({
  user,
  onMenuClick,
  onToggleCollapse,
  isSidebarCollapsed,
  onLogout,
  isLoggingOut,
}) {
  const initials = user?.name
    ?.split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('') || 'AD'

  return (
    <header className="
      sticky top-0 z-20 flex min-h-[76px] items-center
      justify-between border-b border-[#D8CFBF]
      bg-[#EEE9DD]/95 px-5 backdrop-blur-xl
      sm:px-8
    ">
      <div className="flex min-w-0 items-center gap-4">

        {/* Mobile menu */}
        <button
          type="button"
          onClick={onMenuClick}
          aria-label="Buka navigasi"
          className="
            simonta-icon-action grid size-9 shrink-0 place-items-center
            border border-[#D8CFBF] text-[#252B37]
            transition hover:border-[#B69A69] hover:text-[#963746]
            md:hidden
          "
        >
          <InterfaceIcon name="menu" size={20} />
        </button>

        {/* Desktop sidebar toggle */}
        <button
          type="button"
          onClick={onToggleCollapse}
          aria-label={
            isSidebarCollapsed ? 'Expand sidebar' : 'Minimize sidebar'
          }
          title={
            isSidebarCollapsed ? 'Expand sidebar' : 'Minimize sidebar'
          }
          aria-pressed={isSidebarCollapsed}
          className="
            simonta-icon-action hidden size-9 shrink-0 place-items-center
            border border-[#D8CFBF] text-[#252B37]
            transition duration-200 hover:border-[#B69A69]
            hover:bg-white/50 hover:text-[#963746]
            md:grid
          "
        >
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
          >
            <rect x="3" y="4" width="18" height="16" rx="1" />
            <path d="M9 4v16" />
            {isSidebarCollapsed
              ? <path d="m13 9 3 3-3 3" />
              : <path d="m16 9-3 3 3 3" />
            }
          </svg>
        </button>

        <div className="min-w-0">
          <p className="truncate text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            DISKOMINFO / DIGITAL INFRASTRUCTURE
          </p>

          <p className="mt-1 text-xs text-[#77736C]">
            Administrator Console
          </p>
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-3 sm:gap-5">

        <div className="hidden items-center gap-2 border-r border-[#D8CFBF] pr-5 sm:flex">
          <span className="size-2 rounded-full bg-[#82918B] shadow-[0_0_8px_rgba(130,145,139,0.45)]" />

          <span className="text-[9px] font-medium tracking-wider text-[#55584F]">
            ADMIN SESSION
          </span>
        </div>

        <div className="flex items-center gap-2">
          <div className="
            grid size-9 place-items-center border border-[#B69A69]
            bg-[#252B37] font-serif text-sm text-[#EEE9DD]
          ">
            {initials}
          </div>

          <div className="hidden max-w-[150px] sm:block">
            <p className="truncate text-xs font-semibold text-[#191A20]">
              {user?.name || 'Administrator'}
            </p>

            <p className="truncate text-[10px] text-[#77736C]">
              {user?.email}
            </p>
          </div>
        </div>

        <button
          type="button"
          onClick={onLogout}
          disabled={isLoggingOut}
          title="Logout"
          aria-label="Logout"
          className="
            simonta-angular-action flex items-center gap-2
            bg-[#252B37] px-3 py-2 text-xs font-semibold
            text-[#EEE9DD] transition duration-200
            hover:-translate-y-0.5 hover:bg-[#963746]
            disabled:cursor-not-allowed disabled:opacity-50
          "
        >
          <InterfaceIcon name="logout" size={16} />

          <span className="hidden sm:inline">
            {isLoggingOut ? 'Exiting...' : 'Logout'}
          </span>
        </button>
      </div>
    </header>
  )
}

export default Topbar
