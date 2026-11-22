import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router'

import api from '../../services/api'

const EMPTY_FORM = {
  opd_id: '',
  name: '',
  url: '',
  description: '',
  is_active: true,
  monitoring_enabled: true,
  monitoring_interval_minutes: 10,
}

function Icon({ name, size = 17 }) {
  const paths = {
    globe: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18" />
      </>
    ),
    search: (
      <>
        <circle cx="11" cy="11" r="7" />
        <path d="m16 16 4 4" />
      </>
    ),
    plus: <path d="M12 5v14M5 12h14" />,
    edit: (
      <>
        <path d="m14 5 5 5" />
        <path d="M4 20h4l11-11a2.12 2.12 0 0 0-3-3L5 17l-1 3Z" />
      </>
    ),
    trash: (
      <>
        <path d="M3 6h18M8 6V4h8v2m3 0-1 14H6L5 6" />
        <path d="M10 11v5m4-5v5" />
      </>
    ),
    close: <path d="m18 6-12 12M6 6l12 12" />,
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
      </>
    ),
    arrow: <path d="m7 17 10-10M8 7h9v9" />,
    clock: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 7v5l3 2" />
      </>
    ),
    check: <path d="m5 12 4 4L19 6" />,
    server: (
      <>
        <rect x="3" y="4" width="18" height="7" rx="1" />
        <rect x="3" y="13" width="18" height="7" rx="1" />
        <path d="M7 7.5h.01M7 16.5h.01M11 7.5h6M11 16.5h6" />
      </>
    ),
    chevronLeft: <path d="m15 18-6-6 6-6" />,
    chevronRight: <path d="m9 18 6-6-6-6" />,
    building: (
      <>
        <rect x="4" y="3" width="16" height="18" rx="1" />
        <path d="M9 7h2m2 0h2M9 11h2m2 0h2M9 15h2m2 0h2M10 21v-3h4v3" />
      </>
    ),
  }

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
      {paths[name]}
    </svg>
  )
}

function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return 'Belum dijadwalkan'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) return 'Tanggal tidak valid'

  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

function StatusBadge({ active, label }) {
  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${
        active
          ? 'border-[#82918B]/50 bg-[#82918B]/10 text-[#50665C]'
          : 'border-[#963746]/30 bg-[#963746]/5 text-[#963746]'
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {label || (active ? 'ACTIVE' : 'INACTIVE')}
    </span>
  )
}

function Field({
  label,
  name,
  value,
  onChange,
  error,
  required = false,
  type = 'text',
  placeholder,
}) {
  return (
    <div>
      <label
        htmlFor={`website-${name}`}
        className="mb-2 block text-[9px] font-bold tracking-[0.13em] text-[#665F56]"
      >
        {label}
        {required && <span className="ml-1 text-[#963746]">*</span>}
      </label>

      <input
        id={`website-${name}`}
        name={name}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        required={required}
        maxLength={name === 'url' ? 2048 : undefined}
        className={`
          w-full border bg-[#FAF8F2] px-3 py-3 text-sm text-[#191A20]
          placeholder:text-[#AAA294] outline-none transition
          focus:border-[#B69A69]
          ${error ? 'border-[#963746]' : 'border-[#D8CFBF]'}
        `}
      />

      {error && (
        <p className="mt-1.5 text-[10px] text-[#963746]">
          {error}
        </p>
      )}
    </div>
  )
}

function SelectField({
  label,
  name,
  value,
  onChange,
  error,
  children,
  disabled = false,
}) {
  return (
    <div>
      <label
        htmlFor={`website-${name}`}
        className="mb-2 block text-[9px] font-bold tracking-[0.13em] text-[#665F56]"
      >
        {label}
      </label>

      <select
        id={`website-${name}`}
        name={name}
        value={value}
        onChange={onChange}
        disabled={disabled}
        className={`
          w-full border bg-[#FAF8F2] px-3 py-3 text-sm text-[#191A20]
          outline-none transition focus:border-[#B69A69]
          disabled:cursor-not-allowed disabled:bg-[#EAE4D8] disabled:text-[#817A70]
          ${error ? 'border-[#963746]' : 'border-[#D8CFBF]'}
        `}
      >
        {children}
      </select>

      {error && (
        <p className="mt-1.5 text-[10px] text-[#963746]">
          {error}
        </p>
      )}
    </div>
  )
}

function WebsiteManagementPage() {
  const [websites, setWebsites] = useState([])
  const [opds, setOpds] = useState([])

  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  const [activeFilter, setActiveFilter] = useState('all')
  const [monitoringFilter, setMonitoringFilter] = useState('all')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingWebsite, setEditingWebsite] = useState(null)

  const [form, setForm] = useState(EMPTY_FORM)
  const [validationErrors, setValidationErrors] = useState({})

  const [saving, setSaving] = useState(false)
  const [deleteLoading, setDeleteLoading] = useState(null)

  // Mengambil daftar OPD aktif untuk pilihan saat menambahkan website.
  const loadOpds = useCallback(async () => {
    try {
      const response = await api.get('/api/opds', {
        params: {
          is_active: 1,
          per_page: 100,
        },
      })

      setOpds(response.data.data || [])
    } catch (requestError) {
      console.error('OPD options error:', requestError)

      setError(
        'Daftar OPD tidak dapat dimuat. Periksa koneksi server.'
      )
    }
  }, [])

  const loadWebsites = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const params = {
        search: search || undefined,
        per_page: 15,
        page: currentPage,
      }

      if (activeFilter !== 'all') {
        params.is_active = activeFilter
      }

      if (monitoringFilter !== 'all') {
        params.monitoring_enabled = monitoringFilter
      }

      const response = await api.get('/api/websites', { params })

      const result = response.data

      setWebsites(result.data || [])
      setCurrentPage(result.current_page || 1)
      setLastPage(result.last_page || 1)
      setTotal(result.total || 0)
    } catch (requestError) {
      console.error('Website API error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Data website tidak dapat dimuat. Periksa koneksi server.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [search, activeFilter, monitoringFilter, currentPage])

  useEffect(() => {
    loadOpds()
  }, [loadOpds])

  useEffect(() => {
    loadWebsites()
  }, [loadWebsites])

  const activeCount = websites.filter((item) => item.is_active).length

  const monitoringCount = websites.filter(
    (item) => item.monitoring_enabled
  ).length

  const openCreateModal = () => {
    setEditingWebsite(null)

    setForm({
      ...EMPTY_FORM,
      opd_id: opds[0]?.id ? String(opds[0].id) : '',
    })

    setValidationErrors({})
    setError('')
    setSuccess('')
    setModalOpen(true)
  }

  const openEditModal = (website) => {
    setEditingWebsite(website)

    setForm({
      opd_id: String(website.opd_id),
      name: website.name || '',
      url: website.url || '',
      description: website.description || '',
      is_active: Boolean(website.is_active),
      monitoring_enabled: Boolean(website.monitoring_enabled),
      monitoring_interval_minutes: Number(
        website.monitoring_interval_minutes || 10
      ),
    })

    setValidationErrors({})
    setError('')
    setSuccess('')
    setModalOpen(true)
  }

  const closeModal = () => {
    if (saving) return

    setModalOpen(false)
    setEditingWebsite(null)
    setValidationErrors({})
  }

  const handleChange = (event) => {
    const { name, value, type, checked } = event.target

    setForm((previous) => ({
      ...previous,
      [name]: type === 'checkbox'
        ? checked
        : name === 'monitoring_interval_minutes'
          ? value
          : value,
    }))

    setValidationErrors((previous) => ({
      ...previous,
      [name]: undefined,
    }))
  }

  const handleSearch = (event) => {
    event.preventDefault()

    setCurrentPage(1)
    setSearch(searchInput.trim())
  }

  const handleSubmit = async (event) => {
    event.preventDefault()

    setSaving(true)
    setError('')
    setValidationErrors({})

    const payload = {
      name: form.name.trim() || null,
      url: form.url.trim(),
      description: form.description.trim() || null,
      is_active: form.is_active,
      monitoring_enabled: form.monitoring_enabled,
      monitoring_interval_minutes: Number(
        form.monitoring_interval_minutes
      ),
    }

    try {
      if (editingWebsite) {
        await api.patch(
          `/api/websites/${editingWebsite.id}`,
          payload
        )

        setSuccess('Informasi website berhasil diperbarui.')
      } else {
        if (!form.opd_id) {
          setValidationErrors({
            opd_id: ['Pilih OPD pemilik website.'],
          })
          return
        }

        await api.post(
          `/api/opds/${form.opd_id}/websites`,
          payload
        )

        setCurrentPage(1)
        setSearch('')
        setSearchInput('')
        setActiveFilter('all')
        setMonitoringFilter('all')

        setSuccess('Website berhasil didaftarkan.')
      }

      setModalOpen(false)
      setEditingWebsite(null)

      await loadWebsites(true)
    } catch (requestError) {
      console.error('Website save error:', requestError)

      const responseData = requestError.response?.data

      if (responseData?.errors) {
        setValidationErrors(responseData.errors)
      } else {
        setError(
          responseData?.message ||
          'Data website gagal disimpan. Silakan coba kembali.'
        )
      }
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (website) => {
    const confirmed = window.confirm(
      `Hapus website "${website.name || website.url}"?\n\nRiwayat scan akan tetap tersimpan.`
    )

    if (!confirmed) return

    setDeleteLoading(website.id)
    setError('')
    setSuccess('')

    try {
      await api.delete(`/api/websites/${website.id}`)

      setSuccess('Website berhasil dihapus.')

      if (websites.length === 1 && currentPage > 1) {
        setCurrentPage((page) => page - 1)
      } else {
        await loadWebsites(true)
      }
    } catch (requestError) {
      console.error('Website delete error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Website gagal dihapus. Silakan coba kembali.'
      )
    } finally {
      setDeleteLoading(null)
    }
  }

  const resetFilters = () => {
    setSearch('')
    setSearchInput('')
    setActiveFilter('all')
    setMonitoringFilter('all')
    setCurrentPage(1)
  }

  const statCards = [
    {
      index: '01',
      label: 'TOTAL WEBSITES',
      value: total,
      description: 'Website dalam database',
    },
    {
      index: '02',
      label: 'ACTIVE ON THIS PAGE',
      value: activeCount,
      description: 'Website aktif di halaman ini',
    },
    {
      index: '03',
      label: 'MONITORING ENABLED',
      value: monitoringCount,
      description: 'Monitoring aktif di halaman ini',
    },
  ]

  return (
    <div className="simonta-page-enter simonta-registry-page simonta-website-page space-y-8">

      {/* Page heading */}
      <section className="simonta-registry-hero relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          03
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / WEBSITE INFRASTRUCTURE
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                Website <span className="italic text-[#963746]">Registry.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Pusat pengelolaan alamat website publik, organisasi pemilik,
                serta parameter pemantauan otomatis SIMONTA.
              </p>
            </div>

            <button
              type="button"
              onClick={openCreateModal}
              disabled={opds.length === 0}
              title={opds.length === 0 ? 'Tambahkan OPD aktif terlebih dahulu' : ''}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start bg-[#963746] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#EEE9DD] transition duration-300 hover:-translate-y-0.5 hover:bg-[#762B37] disabled:cursor-not-allowed disabled:opacity-40 lg:self-auto"
            >
              <Icon name="plus" size={15} />
              REGISTER WEBSITE
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              PUBLIC WEBSITE DATABASE
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              OPD RELATIONSHIP
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              MONITORING CONFIGURED
            </span>
          </div>
        </div>
      </section>

      {/* Notifications */}
      {success && (
        <div
          role="status"
          className="flex items-center gap-3 border border-[#82918B]/50 bg-[#82918B]/10 p-4 text-xs text-[#50665C]"
        >
          <Icon name="check" />
          {success}
        </div>
      )}

      {error && (
        <div
          role="alert"
          className="flex items-center gap-3 border border-[#963746]/30 bg-[#963746]/5 p-4 text-xs text-[#963746]"
        >
          <span className="font-serif text-lg">!</span>
          {error}
        </div>
      )}

      {/* Summary */}
      <section className="simonta-registry-stats grid gap-3 sm:grid-cols-3">
        {statCards.map((card, index) => (
          <article
            key={card.index}
            className="simonta-card-enter simonta-registry-stat relative overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
            style={{ '--stagger-index': index }}
          >
            <span className="absolute -right-1 -top-6 font-serif text-[75px] leading-none text-[#E8E0D2]">
              {card.index}
            </span>

            <p className="relative text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
              {card.label}
            </p>

            <h2 className="relative mt-5 font-serif text-4xl text-[#191A20]">
              {loading ? '—' : formatNumber(card.value)}
            </h2>

            <p className="relative mt-2 text-[10px] text-[#8B8479]">
              {card.description}
            </p>
          </article>
        ))}
      </section>

      {/* Registry */}
      <section className="simonta-registry-panel border border-[#D8CFBF] bg-[#F7F4EC]">

        <div className="simonta-registry-toolbar border-b border-[#D8CFBF] p-5">
          <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
              <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
                WEBSITE DATABASE / 01
              </p>

              <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
                Registered Websites
              </h2>

              <p className="mt-1 text-[10px] text-[#817A70]">
                {total} website tercatat dalam database SIMONTA
              </p>
            </div>

            <form
              onSubmit={handleSearch}
              className="flex min-w-0 sm:w-72"
            >
              <input
                type="search"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search name or URL..."
                aria-label="Cari website"
                className="min-w-0 flex-1 border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs outline-none transition focus:border-[#B69A69]"
              />

              <button
                type="submit"
                title="Search"
                aria-label="Cari website"
                className="simonta-icon-action grid size-10 shrink-0 place-items-center bg-[#252B37] text-[#EEE9DD] transition hover:bg-[#963746]"
              >
                <Icon name="search" />
              </button>
            </form>
          </div>

          {/* Filters */}
          <div className="simonta-registry-filter-row mt-5 flex flex-wrap items-end gap-3">
            <div>
              <label
                htmlFor="website-active-filter"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                WEBSITE STATUS
              </label>

              <select
                id="website-active-filter"
                value={activeFilter}
                onChange={(event) => {
                  setActiveFilter(event.target.value)
                  setCurrentPage(1)
                }}
                className="border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
              >
                <option value="all">All websites</option>
                <option value="1">Active only</option>
                <option value="0">Inactive only</option>
              </select>
            </div>

            <div>
              <label
                htmlFor="website-monitor-filter"
                className="mb-1.5 block text-[9px] font-bold tracking-wider text-[#817A70]"
              >
                MONITORING
              </label>

              <select
                id="website-monitor-filter"
                value={monitoringFilter}
                onChange={(event) => {
                  setMonitoringFilter(event.target.value)
                  setCurrentPage(1)
                }}
                className="border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs text-[#252B37] outline-none focus:border-[#B69A69]"
              >
                <option value="all">All settings</option>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
            </div>

            <button
              type="button"
              onClick={resetFilters}
              className="pb-2.5 text-[9px] font-bold tracking-wider text-[#963746] transition hover:text-[#762B37]"
            >
              RESET FILTERS
            </button>

            <button
              type="button"
              onClick={() => loadWebsites(true)}
              disabled={refreshing || loading}
              title="Refresh data"
              aria-label="Refresh data website"
              className="simonta-icon-action ml-auto grid size-10 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] hover:text-[#963746] disabled:opacity-50"
            >
              <Icon name="refresh" size={16} />
            </button>
          </div>
        </div>

        {/* Desktop table */}
        <div className="simonta-registry-table-wrap hidden overflow-x-auto md:block">
          <table className="simonta-registry-table w-full min-w-[1050px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  WEBSITE
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  ORGANIZATION
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  STATUS
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  MONITORING
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  INTERVAL
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  NEXT SCAN
                </th>

                <th className="px-5 py-4 text-right text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  ACTION
                </th>
              </tr>
            </thead>

            <tbody className="divide-y divide-[#E1D8C9]">
              {loading ? (
                [1, 2, 3].map((item) => (
                  <tr key={item}>
                    <td colSpan="7" className="px-5 py-6">
                      <div className="simonta-skeleton h-4 w-2/3" />
                    </td>
                  </tr>
                ))
              ) : websites.length === 0 ? (
                <tr>
                  <td colSpan="7" className="px-5 py-14 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="globe" size={24} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Website Found
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      {search || activeFilter !== 'all' || monitoringFilter !== 'all'
                        ? 'Tidak ditemukan website yang sesuai dengan filter.'
                        : 'Belum ada website yang terdaftar di SIMONTA.'}
                    </p>

                    {search === '' &&
                      activeFilter === 'all' &&
                      monitoringFilter === 'all' && (
                        <button
                          type="button"
                          onClick={openCreateModal}
                          disabled={opds.length === 0}
                          className="simonta-angular-action mt-5 bg-[#963746] px-5 py-3 text-[9px] font-bold tracking-wider text-white transition hover:bg-[#762B37] disabled:opacity-40"
                        >
                          REGISTER FIRST WEBSITE
                        </button>
                      )}
                  </td>
                </tr>
              ) : (
                websites.map((website) => (
                  <tr
                    key={website.id}
                    className="group transition duration-200 hover:bg-[#F1EBDD]"
                  >
                    <td className="max-w-[250px] px-5 py-5">
                      <p className="truncate font-medium text-sm text-[#252B37]">
                        {website.name || 'Unnamed Website'}
                      </p>

                      <a
                        href={website.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-1 inline-flex max-w-full items-center gap-1 truncate text-[10px] text-[#963746] hover:underline"
                      >
                        <span className="truncate">{website.url}</span>
                        <Icon name="arrow" size={11} />
                      </a>
                    </td>

                    <td className="max-w-[180px] px-5 py-5">
                      <p className="truncate text-xs text-[#665F56]">
                        {website.opd?.name || 'Unknown OPD'}
                      </p>

                      <p className="mt-1 text-[9px] text-[#948B7D]">
                        {formatNumber(website.scans_count)} inspections
                      </p>
                    </td>

                    <td className="px-5 py-5 text-center">
                      <StatusBadge
                        active={website.is_active}
                        label={website.is_active ? 'ACTIVE' : 'INACTIVE'}
                      />
                    </td>

                    <td className="px-5 py-5 text-center">
                      <StatusBadge
                        active={website.monitoring_enabled}
                        label={website.monitoring_enabled ? 'ON' : 'OFF'}
                      />
                    </td>

                    <td className="px-5 py-5 text-center">
                      <span className="font-serif text-lg text-[#252B37]">
                        {website.monitoring_interval_minutes} min
                      </span>
                    </td>

                    <td className="px-5 py-5">
                      <p className="text-[10px] text-[#665F56]">
                        {website.monitoring_enabled
                          ? formatDate(website.next_scan_at)
                          : 'Monitoring off'}
                      </p>
                    </td>

                    <td className="px-5 py-5">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => openEditModal(website)}
                          title="Edit website"
                          aria-label={`Edit ${website.name || website.url}`}
                          className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] hover:bg-[#B69A69]/10 hover:text-[#963746]"
                        >
                          <Icon name="edit" size={15} />
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDelete(website)}
                          disabled={deleteLoading === website.id}
                          title="Delete website"
                          aria-label={`Delete ${website.name || website.url}`}
                          className="simonta-icon-action grid size-9 place-items-center border border-[#963746]/25 text-[#963746] transition hover:bg-[#963746] hover:text-white disabled:opacity-50"
                        >
                          {deleteLoading === website.id
                            ? <span className="size-3 animate-spin border border-current border-t-transparent" />
                            : <Icon name="trash" size={15} />
                          }
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Mobile cards */}
        <div className="simonta-registry-mobile-list divide-y divide-[#D8CFBF] md:hidden">
          {loading ? (
            [1, 2, 3].map((item) => (
              <div key={item} className="p-5">
                <div className="simonta-skeleton h-4 w-2/3" />
                <div className="simonta-skeleton mt-3 h-3 w-1/2" />
              </div>
            ))
          ) : websites.length === 0 ? (
            <div className="p-8 text-center">
              <h3 className="font-serif text-xl">No Website Found</h3>
              <p className="mt-2 text-xs text-[#817A70]">
                Tidak ada website yang sesuai dengan pencarian.
              </p>
            </div>
          ) : (
            websites.map((website) => (
              <article key={website.id} className="space-y-4 p-5">
                <div>
                  <h3 className="font-medium text-sm text-[#252B37]">
                    {website.name || 'Unnamed Website'}
                  </h3>

                  <a
                    href={website.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-1 block break-all text-[10px] text-[#963746]"
                  >
                    {website.url}
                  </a>

                  <p className="mt-2 text-[10px] text-[#817A70]">
                    {website.opd?.name || 'Unknown OPD'}
                  </p>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge active={website.is_active} />

                  <StatusBadge
                    active={website.monitoring_enabled}
                    label={website.monitoring_enabled ? 'MONITORING ON' : 'MONITORING OFF'}
                  />
                </div>

                <div className="flex items-center justify-between border-t border-[#D8CFBF] pt-3">
                  <div>
                    <p className="text-[9px] tracking-wider text-[#817A70]">
                      MONITORING INTERVAL
                    </p>

                    <p className="mt-1 font-serif text-xl text-[#252B37]">
                      {website.monitoring_interval_minutes} minutes
                    </p>
                  </div>

                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => openEditModal(website)}
                      aria-label="Edit website"
                      className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56]"
                    >
                      <Icon name="edit" />
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDelete(website)}
                      aria-label="Delete website"
                      className="simonta-icon-action grid size-9 place-items-center border border-[#963746]/30 text-[#963746]"
                    >
                      <Icon name="trash" />
                    </button>
                  </div>
                </div>
              </article>
            ))
          )}
        </div>

        {/* Pagination */}
        {!loading && total > 0 && (
          <div className="flex flex-col justify-between gap-3 border-t border-[#D8CFBF] px-5 py-4 sm:flex-row sm:items-center">
            <p className="text-[10px] text-[#817A70]">
              Showing page {currentPage} of {lastPage} · {total} websites
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                aria-label="Previous page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="chevronLeft" />
              </button>

              <span className="min-w-8 text-center font-serif text-sm text-[#252B37]">
                {currentPage}
              </span>

              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(lastPage, page + 1))}
                disabled={currentPage >= lastPage}
                aria-label="Next page"
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:opacity-35"
              >
                <Icon name="chevronRight" />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* Create / Edit modal */}
      {modalOpen && (
        <div
          className="simonta-backdrop-enter fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#191A20]/65 p-4 backdrop-blur-[2px]"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) closeModal()
          }}
        >
          <section
            role="dialog"
            aria-modal="true"
            aria-labelledby="website-modal-title"
            className="simonta-card-enter simonta-registry-modal my-auto w-full max-w-2xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
          >
            <div className="simonta-registry-modal-header flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  WEBSITE INFRASTRUCTURE
                </p>

                <h2
                  id="website-modal-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  {editingWebsite ? 'Edit Website.' : 'Register Website.'}
                </h2>

                <p className="mt-2 text-xs text-[#817A70]">
                  {editingWebsite
                    ? 'Perbarui informasi dan parameter monitoring website.'
                    : 'Daftarkan alamat website publik milik OPD.'}
                </p>
              </div>

              <button
                type="button"
                onClick={closeModal}
                disabled={saving}
                aria-label="Tutup form"
                className="simonta-icon-action grid size-9 shrink-0 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#963746] hover:text-[#963746]"
              >
                <Icon name="close" />
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              <div className="simonta-registry-form-grid grid gap-5 p-5 sm:grid-cols-2 sm:p-7">

                <div className="sm:col-span-2">
                  <SelectField
                    label="OWNER ORGANIZATION"
                    name="opd_id"
                    value={form.opd_id}
                    onChange={handleChange}
                    error={validationErrors.opd_id?.[0]}
                    disabled={Boolean(editingWebsite)}
                  >
                    <option value="">Select OPD...</option>

                    {opds.map((opd) => (
                      <option key={opd.id} value={String(opd.id)}>
                        {opd.name}
                      </option>
                    ))}
                  </SelectField>

                  {editingWebsite && (
                    <p className="mt-1.5 text-[10px] text-[#817A70]">
                      OPD pemilik tidak dapat diubah dari form ini.
                    </p>
                  )}
                </div>

                <div className="sm:col-span-2">
                  <Field
                    label="WEBSITE NAME"
                    name="name"
                    value={form.name}
                    onChange={handleChange}
                    error={validationErrors.name?.[0]}
                    placeholder="Contoh: Portal Resmi Diskominfo"
                  />
                </div>

                <div className="sm:col-span-2">
                  <Field
                    label="WEBSITE URL"
                    name="url"
                    type="url"
                    value={form.url}
                    onChange={handleChange}
                    error={validationErrors.url?.[0]}
                    required
                    placeholder="https://example.go.id"
                  />

                  <p className="mt-1.5 text-[10px] leading-5 text-[#817A70]">
                    Masukkan alamat publik yang dapat diakses melalui HTTP atau HTTPS.
                    Sistem akan memvalidasi keamanan URL saat pemeriksaan dijalankan.
                  </p>
                </div>

                <div className="sm:col-span-2">
                  <label
                    htmlFor="website-description"
                    className="mb-2 block text-[9px] font-bold tracking-[0.13em] text-[#665F56]"
                  >
                    DESCRIPTION
                  </label>

                  <textarea
                    id="website-description"
                    name="description"
                    value={form.description}
                    onChange={handleChange}
                    rows={3}
                    placeholder="Keterangan singkat website..."
                    className="w-full resize-y border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-sm text-[#191A20] outline-none transition placeholder:text-[#AAA294] focus:border-[#B69A69]"
                  />

                  {validationErrors.description?.[0] && (
                    <p className="mt-1.5 text-[10px] text-[#963746]">
                      {validationErrors.description[0]}
                    </p>
                  )}
                </div>

                <div className="sm:col-span-2">
                  <div className="border border-[#D8CFBF] bg-[#F7F4EC] p-4">
                    <div className="flex items-center justify-between gap-4">
                      <div>
                        <p className="text-xs font-semibold text-[#252B37]">
                          Website Status
                        </p>

                        <p className="mt-1 text-[10px] text-[#817A70]">
                          Website aktif dapat dimasukkan ke proses pemeriksaan.
                        </p>
                      </div>

                      <input
                        type="checkbox"
                        name="is_active"
                        checked={form.is_active}
                        onChange={handleChange}
                        className="size-4 shrink-0 accent-[#963746]"
                      />
                    </div>

                    <div className="mt-4 border-t border-[#D8CFBF] pt-4">
                      <div className="flex items-center justify-between gap-4">
                        <div>
                          <p className="text-xs font-semibold text-[#252B37]">
                            Automatic Monitoring
                          </p>

                          <p className="mt-1 text-[10px] text-[#817A70]">
                            Periksa website secara berkala melalui Scheduler.
                          </p>
                        </div>

                        <input
                          type="checkbox"
                          name="monitoring_enabled"
                          checked={form.monitoring_enabled}
                          onChange={handleChange}
                          className="size-4 shrink-0 accent-[#963746]"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                <div className="sm:col-span-2">
                  <SelectField
                    label="MONITORING INTERVAL"
                    name="monitoring_interval_minutes"
                    value={String(form.monitoring_interval_minutes)}
                    onChange={handleChange}
                    error={validationErrors.monitoring_interval_minutes?.[0]}
                    disabled={!form.monitoring_enabled}
                  >
                    <option value="1">Every 1 minute</option>
                    <option value="5">Every 5 minutes</option>
                    <option value="10">Every 10 minutes</option>
                    <option value="30">Every 30 minutes</option>
                  </SelectField>

                  <p className="mt-1.5 text-[10px] text-[#817A70]">
                    Pilih interval pemeriksaan otomatis: 1, 5, 10, atau 30 menit.
                    Jika monitoring dinonaktifkan, Scheduler akan melewati website ini.
                  </p>
                </div>
              </div>

              <div className="flex flex-col-reverse justify-end gap-3 border-t border-[#D8CFBF] p-5 sm:flex-row sm:p-7">
                <button
                  type="button"
                  onClick={closeModal}
                  disabled={saving}
                  className="simonta-angular-action border border-[#B69A69] px-5 py-3 text-[10px] font-bold tracking-wider text-[#665F56] transition hover:bg-[#B69A69]/10 disabled:opacity-50"
                >
                  CANCEL
                </button>

                <button
                  type="submit"
                  disabled={saving || (!editingWebsite && opds.length === 0)}
                  className="simonta-angular-action inline-flex items-center justify-center gap-2 bg-[#963746] px-6 py-3 text-[10px] font-bold tracking-wider text-[#EEE9DD] transition hover:-translate-y-0.5 hover:bg-[#762B37] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {saving && (
                    <span className="size-3 animate-spin border border-white border-t-transparent" />
                  )}

                  {saving
                    ? 'SAVING...'
                    : editingWebsite
                      ? 'SAVE CHANGES'
                      : 'REGISTER WEBSITE'}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </div>
  )
}

export default WebsiteManagementPage
