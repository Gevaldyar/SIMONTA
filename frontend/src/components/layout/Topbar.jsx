
import InterfaceIcon from '../common/InterfaceIcon'

function Topbar({ user, onMenuClick, onLogout, isLoggingOut }) {
  const initials = user?.name
    ?.split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join('') || 'AD'

  return (
    <header className="sticky top-0 z-20 flex min-h-[76px] items-center justify-between border-b border-[#D8CFBF] bg-[#EEE9DD]/95 px-5 backdrop-blur-sm sm:px-8">
      <div className="flex items-center gap-4">
        <button
          type="button"
          onClick={onMenuClick}
          aria-label="Buka navigasi"
          className="text-[#252B37] hover:text-[#963746] md:hidden"
        >
          <InterfaceIcon name="menu" size={23} />
        </button>

        <div>
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            DISKOMINFO / DIGITAL INFRASTRUCTURE
          </p>

          <p className="mt-1 text-xs text-[#77736C]">
            Administrator Console
          </p>
        </div>
      </div>

      <div className="flex items-center gap-3 sm:gap-5">
        <div className="hidden items-center gap-2 border-r border-[#D8CFBF] pr-5 sm:flex">
          <span className="size-2 rounded-full bg-[#82918B]" />
          <span className="text-[10px] font-medium tracking-wider text-[#55584F]">
            ADMIN SESSION
          </span>
        </div>

        <div className="flex items-center gap-2">
          <div className="grid size-9 place-items-center border border-[#B69A69] bg-[#252B37] font-serif text-sm text-[#EEE9DD]">
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
          className="flex items-center gap-2 border border-[#963746]/30 px-3 py-2 text-xs font-semibold text-[#963746] transition hover:bg-[#963746] hover:text-white disabled:cursor-not-allowed disabled:opacity-50"
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
