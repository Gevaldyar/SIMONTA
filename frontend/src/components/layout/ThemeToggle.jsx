function SunIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
    </svg>
  )
}

function MoonIcon() {
  return (
    <svg
      width="18"
      height="18"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M20.8 14.2A8.5 8.5 0 0 1 9.8 3.2a8.7 8.7 0 1 0 11 11Z" />
    </svg>
  )
}

function ThemeToggle({ theme = 'light', onToggle }) {
  const isDark = theme === 'dark'
  const label = isDark ? 'Aktifkan light mode' : 'Aktifkan dark mode'

  return (
    <button
      type="button"
      onClick={onToggle}
      aria-label={label}
      aria-pressed={isDark}
      title={label}
      className="simonta-theme-toggle grid size-9 shrink-0 place-items-center rounded-lg border transition duration-200"
    >
      {isDark ? <SunIcon /> : <MoonIcon />}
    </button>
  )
}

export default ThemeToggle
