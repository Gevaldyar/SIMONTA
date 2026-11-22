function SidebarNavigationIcon({ label, size = 19, className = '' }) {
  const common = {
    width: size,
    height: size,
    viewBox: '0 0 24 24',
    fill: 'none',
    stroke: 'currentColor',
    strokeWidth: 1.7,
    strokeLinecap: 'round',
    strokeLinejoin: 'round',
    className,
    'aria-hidden': true,
    focusable: 'false',
  }

  switch (label) {
    case 'Overview':
      return (
        <svg {...common}>
          <rect x="3.5" y="3.5" width="7" height="7" rx="1" />
          <rect x="13.5" y="3.5" width="7" height="7" rx="1" />
          <rect x="3.5" y="13.5" width="7" height="7" rx="1" />
          <rect x="13.5" y="13.5" width="7" height="7" rx="1" />
        </svg>
      )

    case 'Data OPD':
      return (
        <svg {...common}>
          <path d="M3 21h18M5 21V5.5L12 3l7 2.5V21" />
          <path d="M9 8h.01M15 8h.01M9 12h.01M15 12h.01M10 21v-4h4v4" />
          <path d="M2.5 8.5 5 7.6M21.5 8.5 19 7.6" />
        </svg>
      )

    case 'Website Registry':
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" />
        </svg>
      )

    case 'Monitoring':
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <circle cx="12" cy="12" r="4.5" />
          <path d="M12 3v4.5M21 12h-4.5M12 21v-4.5M3 12h4.5" />
          <path d="m12 12 5.7-5.7" />
        </svg>
      )

    case 'Domain & SSL Monitoring':
      return (
        <svg {...common}>
          <circle cx="10.5" cy="10.5" r="7.5" />
          <path d="M3 10.5h15M10.5 3a12 12 0 0 1 0 15M10.5 3a12 12 0 0 0 0 15" />
          <rect x="14.5" y="14" width="7" height="6.5" rx="1.2" />
          <path d="M16.2 14v-1.3a1.8 1.8 0 0 1 3.6 0V14" />
        </svg>
      )

    case 'Technology Detection':
      return (
        <svg {...common}>
          <rect x="7" y="7" width="10" height="10" rx="1.5" />
          <path d="M9.5 3.5v3M14.5 3.5v3M9.5 17v3.5M14.5 17v3.5M3.5 9.5h3M3.5 14.5h3M17 9.5h3.5M17 14.5h3.5" />
          <path d="M10 10h4v4h-4z" />
        </svg>
      )

    case 'Technology Changes':
      return (
        <svg {...common}>
          <path d="M4 7h15l-3-3M20 17H5l3 3" />
          <path d="M16 4v3M8 17v3" />
          <circle cx="4" cy="7" r="1.2" />
          <circle cx="20" cy="17" r="1.2" />
        </svg>
      )

    case 'Security Monitoring':
      return (
        <svg {...common}>
          <path d="M12 2.8 20 6v5.1c0 5.1-3.3 8.3-8 10.1-4.7-1.8-8-5-8-10.1V6l8-3.2Z" />
          <path d="M5.8 12h3l1.5-2.5 2.2 5 1.6-2.5h4.1" />
        </svg>
      )

    case 'Security Headers Audit':
      return (
        <svg {...common}>
          <path d="M6 3.5h8l4 4V13" />
          <path d="M14 3.5v4h4M6 3.5v17h6" />
          <path d="M9 11h6M9 14h4" />
          <path d="m15 17 2 2 4-4" />
        </svg>
      )

    case 'Inspection History':
      return (
        <svg {...common}>
          <path d="M3.5 11a8.5 8.5 0 1 1 2.4 6" />
          <path d="M3.5 5.5V11h5.5M12 7v5l3.2 2" />
        </svg>
      )

    case 'Reports':
      return (
        <svg {...common}>
          <path d="M6 3.5h8l4 4v13H6z" />
          <path d="M14 3.5v4h4M9 17v-3M12 17v-5M15 17v-7" />
        </svg>
      )

    case 'Settings':
      return (
        <svg {...common}>
          <path d="M4 7h16M4 17h16" />
          <circle cx="9" cy="7" r="2" fill="#142033" />
          <circle cx="15" cy="17" r="2" fill="#142033" />
          <path d="M9 5v4M15 15v4" />
        </svg>
      )

    default:
      return (
        <svg {...common}>
          <circle cx="12" cy="12" r="9" />
          <path d="M8 12h8M12 8v8" />
        </svg>
      )
  }
}

export default SidebarNavigationIcon
