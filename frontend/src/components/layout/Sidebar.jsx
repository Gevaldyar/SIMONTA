
import { NavLink } from 'react-router'

import BrandLogo from '../common/BrandLogo'
import InterfaceIcon from '../common/InterfaceIcon'

const navigationGroups = [
  {
    title: 'CONTROL ROOM',
    items: [
      {
        label: 'Overview',
        path: '/',
        icon: 'overview',
      },
      {
        label: 'Data OPD',
        path: '/opds',
        icon: 'building',
      },
      {
        label: 'Website Registry',
        path: '/websites',
        icon: 'globe',
      },
    ],
  },
  {
    title: 'INTELLIGENCE',
    items: [
      {
        label: 'Monitoring',
        path: '/monitoring',
        icon: 'scan',
      },
      {
        label: 'Technology Detection',
        path: '/technologies',
        icon: 'overview',
      },
      {
        label: 'Inspection History',
        path: '/history',
        icon: 'history',
      },
    ],
  },
  {
    title: 'SYSTEM',
    items: [
      {
        label: 'Settings',
        path: '/settings',
        icon: 'settings',
      },
    ],
  },
]

function Sidebar({
  isOpen,
  onNavigate,
  isCollapsed,
  onToggleCollapse,
}) {
  return (
    <aside
      aria-label="Navigasi utama SIMONTA"
      className={`
        fixed inset-y-0 left-0 z-50 flex h-screen w-[270px]
        shrink-0 flex-col overflow-x-hidden overflow-y-auto
        border-r border-[#51483B]
        bg-[#191A20] text-[#EEE9DD]
        transition-[width,transform] duration-300 ease-in-out
        md:sticky md:top-0 md:z-30 md:translate-x-0
        ${isCollapsed ? 'md:w-[88px]' : 'md:w-[270px]'}
        ${isOpen ? 'translate-x-0' : '-translate-x-full'}
      `}
    >
      {/* Brand header */}
      <div
        className={`
          flex min-h-[88px] items-center border-b border-[#51483B]/70
          ${isCollapsed
            ? 'justify-between px-4 md:flex-col md:justify-center md:gap-3 md:px-2 md:py-4'
            : 'justify-between px-6 py-6'}
        `}
      >
        <div className={isCollapsed ? 'md:hidden' : ''}>
          <BrandLogo />
        </div>

        {/* Compact brand mark for minimized sidebar */}
        <div
          className={`
            hidden size-10 shrink-0 place-items-center
            border border-[#B69A69] bg-[#252B37]
            font-serif text-xl text-[#B69A69]
            ${isCollapsed ? 'md:grid' : ''}
          `}
        >
          S
        </div>

        {/* Desktop collapse control */}
        <button
          type="button"
          onClick={onToggleCollapse}
          aria-label={
            isCollapsed ? 'Perbesar sidebar' : 'Minimize sidebar'
          }
          aria-pressed={isCollapsed}
          title={isCollapsed ? 'Expand sidebar' : 'Minimize sidebar'}
          className="
            simonta-icon-action hidden size-8 place-items-center
            border border-[#51483B] text-[#B69A69]
            transition duration-200
            hover:border-[#B69A69] hover:bg-[#963746]/20 hover:text-white
            md:grid
          "
        >
          <svg
            width="17"
            height="17"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            className={`transition-transform duration-300 ${
              isCollapsed ? 'rotate-180' : ''
            }`}
          >
            <path d="m15 18-6-6 6-6" />
          </svg>
        </button>

        {/* Mobile close control */}
        <button
          type="button"
          aria-label="Tutup navigasi"
          onClick={onNavigate}
          className="
            simonta-icon-action grid size-8 place-items-center
            text-[#B69A69] transition hover:bg-white/10 hover:text-white
            md:hidden
          "
        >
          <InterfaceIcon name="close" size={18} />
        </button>
      </div>

      {/* Navigation */}
      <div className="flex-1 px-4 py-7">
        {navigationGroups.map((group) => (
          <div key={group.title} className="mb-8 last:mb-0">

            <p
              className={`
                mb-3 px-3 text-[9px] font-semibold tracking-[0.22em]
                text-[#B69A69]
                ${isCollapsed ? 'md:hidden' : ''}
              `}
            >
              {group.title}
            </p>

            <nav className="space-y-1">
              {group.items.map((item, index) => (
                <NavLink
                  key={item.path}
                  to={item.path}
                  end={item.path === '/'}
                  onClick={onNavigate}
                  title={isCollapsed ? item.label : undefined}
                  className={({ isActive }) => `
                    group relative flex min-h-[46px] items-center gap-3
                    border-l-2 px-3 py-3 text-sm
                    transition-all duration-200
                    ${
                      isActive
                        ? 'border-[#B69A69] bg-[#963746]/25 text-[#EEE9DD]'
                        : 'border-transparent text-[#A9A8A3] hover:border-[#B69A69]/60 hover:bg-white/5 hover:text-white'
                    }
                    ${isCollapsed
                      ? 'md:justify-center md:border-l-0 md:px-2'
                      : ''}
                  `}
                >
                  <InterfaceIcon
                    name={item.icon}
                    size={19}
                    className="shrink-0 transition-transform duration-200 group-hover:scale-110"
                  />

                  <span
                    className={`
                      min-w-0 flex-1 truncate
                      ${isCollapsed ? 'md:hidden' : ''}
                    `}
                  >
                    {item.label}
                  </span>

                  <span
                    className={`
                      shrink-0 font-serif text-xs text-[#B69A69]/60
                      ${isCollapsed ? 'md:hidden' : ''}
                    `}
                  >
                    {String(index + 1).padStart(2, '0')}
                  </span>

                  {/* Tooltip on minimized desktop sidebar */}
                  {isCollapsed && (
                    <span
                      className="
                        pointer-events-none absolute left-full z-50 ml-3
                        hidden whitespace-nowrap border border-[#51483B]
                        bg-[#252B37] px-3 py-2 text-[10px]
                        tracking-wider text-[#EEE9DD] opacity-0 shadow-xl
                        transition duration-200
                        md:block md:group-hover:opacity-100
                      "
                    >
                      {item.label}
                    </span>
                  )}
                </NavLink>
              ))}
            </nav>
          </div>
        ))}
      </div>

      {/* Monitoring protocol */}
      <div className="mt-auto border-t border-[#51483B]/70 p-5">

        <div className={isCollapsed ? 'md:hidden' : ''}>
          <p className="text-[9px] tracking-[0.2em] text-[#B69A69]">
            MONITORING PROTOCOL
          </p>

          <div className="mt-3 border border-[#51483B] bg-[#252B37]/60 p-3">
            <p className="font-serif text-2xl text-[#EEE9DD]">
              01 <span className="text-[#B69A69]">HOUR</span>
            </p>

            <p className="mt-1 text-[10px] tracking-wider text-[#A9A8A3]">
              DEFAULT INSPECTION INTERVAL
            </p>

            <p className="mt-3 border-t border-[#51483B] pt-2 text-[9px] tracking-widest text-[#82918B]">
              SCHEDULER CONFIGURED
            </p>
          </div>

          <p className="mt-5 text-[9px] tracking-[0.15em] text-[#777671]">
            DISKOMINFO KOTA TASIKMALAYA
          </p>
        </div>

        {/* Compact indicator */}
        {isCollapsed && (
          <div className="hidden flex-col items-center gap-3 md:flex">
            <span className="size-2 rounded-full bg-[#82918B] shadow-[0_0_10px_rgba(130,145,139,0.5)]" />

            <span
              className="font-serif text-[9px] tracking-widest text-[#B69A69]"
              title="Default monitoring interval: 60 minutes"
            >
              60M
            </span>
          </div>
        )}
      </div>
    </aside>
  )
}

export default Sidebar
