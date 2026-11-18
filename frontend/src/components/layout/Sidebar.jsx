
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

function Sidebar({ isOpen, onNavigate }) {
  return (
    <aside
      className={`
        fixed inset-y-0 left-0 z-50 flex h-screen w-[270px]
        shrink-0 flex-col overflow-y-auto
        border-r border-[#51483B]
        bg-[#191A20] text-[#EEE9DD]
        transition-transform duration-300
        md:sticky md:top-0 md:translate-x-0
        ${isOpen ? 'translate-x-0' : '-translate-x-full'}
      `}
    >
      <div className="flex items-center justify-between border-b border-[#51483B]/70 px-6 py-7">
        <BrandLogo />

        <button
          type="button"
          aria-label="Tutup navigasi"
          onClick={onNavigate}
          className="text-[#B69A69] hover:text-white md:hidden"
        >
          <InterfaceIcon name="close" />
        </button>
      </div>

      <div className="px-4 py-7">
        {navigationGroups.map((group) => (
          <div key={group.title} className="mb-8 last:mb-0">
            <p className="mb-3 px-3 text-[9px] font-semibold tracking-[0.22em] text-[#B69A69]">
              {group.title}
            </p>

            <nav className="space-y-1">
              {group.items.map((item, index) => (
                <NavLink
                  key={item.path}
                  to={item.path}
                  end={item.path === '/'}
                  onClick={onNavigate}
                  className={({ isActive }) => `
                    group flex items-center gap-3 border-l-2 px-3 py-3
                    text-sm transition-all duration-200
                    ${
                      isActive
                        ? 'border-[#B69A69] bg-[#963746]/25 text-[#EEE9DD]'
                        : 'border-transparent text-[#A9A8A3] hover:border-[#B69A69]/60 hover:bg-white/5 hover:text-white'
                    }
                  `}
                >
                  <InterfaceIcon
                    name={item.icon}
                    size={18}
                    className="shrink-0"
                  />

                  <span className="flex-1">{item.label}</span>

                  <span className="font-serif text-xs text-[#B69A69]/60">
                    {String(index + 1).padStart(2, '0')}
                  </span>
                </NavLink>
              ))}
            </nav>
          </div>
        ))}
      </div>

      <div className="mt-auto border-t border-[#51483B]/70 p-5">
        <p className="text-[9px] tracking-[0.2em] text-[#B69A69]">
          MONITORING PROTOCOL
        </p>

        <div className="mt-3 border border-[#51483B] bg-[#252B37]/60 p-3">
          <p className="font-serif text-2xl text-[#EEE9DD]">
            01 <span className="text-[#B69A69]">HOUR</span>
          </p>

          <p className="mt-1 text-[10px] tracking-wider text-[#A9A8A3]">
            PLANNED INSPECTION INTERVAL
          </p>

          <p className="mt-3 border-t border-[#51483B] pt-2 text-[9px] tracking-widest text-[#B69A69]">
            ENGINE IN DEVELOPMENT
          </p>
        </div>

        <p className="mt-5 text-[9px] tracking-[0.15em] text-[#777671]">
          DISKOMINFO KOTA TASIKMALAYA
        </p>
      </div>
    </aside>
  )
}

export default Sidebar
