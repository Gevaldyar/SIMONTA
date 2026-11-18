
import { useCallback, useEffect, useState } from 'react'

import api from '../../services/api'

const EMPTY_FORM = {
  name: '',
  code: '',
  email: '',
  phone: '',
  address: '',
  description: '',
  is_active: true,
}

function Icon({ name, size = 17 }) {
  const paths = {
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
    building: (
      <>
        <rect x="4" y="3" width="16" height="18" rx="1" />
        <path d="M9 7h2m2 0h2M9 11h2m2 0h2M9 15h2m2 0h2M10 21v-3h4v3" />
      </>
    ),
    arrow: <path d="m9 18 6-6-6-6" />,
    chevronLeft: <path d="m15 18-6-6 6-6" />,
    chevronRight: <path d="m9 18 6-6-6-6" />,
    check: <path d="m5 12 4 4L19 6" />,
    refresh: (
      <>
        <path d="M20 7v5h-5" />
        <path d="M4 17v-5h5" />
        <path d="M5.6 9a7 7 0 0 1 11.5-2L20 12M4 12l2.5 5.5a7 7 0 0 0 11.6-2" />
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

function StatusBadge({ active }) {
  return (
    <span
      className={`inline-flex items-center gap-2 border px-2.5 py-1 text-[9px] font-bold tracking-wider ${
        active
          ? 'border-[#82918B]/50 bg-[#82918B]/10 text-[#50665C]'
          : 'border-[#963746]/30 bg-[#963746]/5 text-[#963746]'
      }`}
    >
      <span className="size-1.5 rounded-full bg-current" />
      {active ? 'ACTIVE' : 'INACTIVE'}
    </span>
  )
}

function FormField({
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
        htmlFor={`opd-${name}`}
        className="mb-2 block text-[9px] font-bold tracking-[0.13em] text-[#665F56]"
      >
        {label}
        {required && <span className="ml-1 text-[#963746]">*</span>}
      </label>

      <input
        id={`opd-${name}`}
        name={name}
        type={type}
        value={value}
        onChange={onChange}
        placeholder={placeholder}
        required={required}
        maxLength={name === 'code' ? 50 : undefined}
        className={`
          w-full border bg-[#FAF8F2] px-3 py-3 text-sm text-[#191A20]
          placeholder:text-[#AAA294] transition
          focus:border-[#B69A69] focus:outline-none
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

function OpdManagementPage() {
  const [opds, setOpds] = useState([])
  const [loading, setLoading] = useState(true)
  const [refreshing, setRefreshing] = useState(false)

  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  const [currentPage, setCurrentPage] = useState(1)
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)

  const [modalOpen, setModalOpen] = useState(false)
  const [editingOpd, setEditingOpd] = useState(null)

  const [form, setForm] = useState(EMPTY_FORM)
  const [validationErrors, setValidationErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const [deleteLoading, setDeleteLoading] = useState(null)

  const loadOpds = useCallback(async (silent = false) => {
    if (silent) {
      setRefreshing(true)
    } else {
      setLoading(true)
    }

    setError('')

    try {
      const response = await api.get('/api/opds', {
        params: {
          search: search || undefined,
          per_page: 15,
          page: currentPage,
        },
      })

      const result = response.data

      setOpds(result.data || [])
      setCurrentPage(result.current_page || 1)
      setLastPage(result.last_page || 1)
      setTotal(result.total || 0)
    } catch (requestError) {
      console.error('OPD API error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'Data OPD tidak dapat dimuat. Periksa koneksi server.'
      )
    } finally {
      setLoading(false)
      setRefreshing(false)
    }
  }, [search, currentPage])

  useEffect(() => {
    loadOpds()
  }, [loadOpds])

  const activeCount = opds.filter((item) => item.is_active).length
  const inactiveCount = opds.length - activeCount

  const openCreateModal = () => {
    setEditingOpd(null)
    setForm(EMPTY_FORM)
    setValidationErrors({})
    setError('')
    setSuccess('')
    setModalOpen(true)
  }

  const openEditModal = (opd) => {
    setEditingOpd(opd)

    setForm({
      name: opd.name || '',
      code: opd.code || '',
      email: opd.email || '',
      phone: opd.phone || '',
      address: opd.address || '',
      description: opd.description || '',
      is_active: Boolean(opd.is_active),
    })

    setValidationErrors({})
    setError('')
    setSuccess('')
    setModalOpen(true)
  }

  const closeModal = () => {
    if (saving) return

    setModalOpen(false)
    setEditingOpd(null)
    setValidationErrors({})
  }

  const handleChange = (event) => {
    const { name, value, type, checked } = event.target

    setForm((previous) => ({
      ...previous,
      [name]: type === 'checkbox' ? checked : value,
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
    setValidationErrors({})
    setError('')

    const payload = {
      name: form.name.trim(),
      code: form.code.trim() || null,
      email: form.email.trim() || null,
      phone: form.phone.trim() || null,
      address: form.address.trim() || null,
      description: form.description.trim() || null,
      is_active: form.is_active,
    }

    try {
      if (editingOpd) {
        await api.patch(`/api/opds/${editingOpd.id}`, payload)

        setSuccess('Data OPD berhasil diperbarui.')
      } else {
        await api.post('/api/opds', payload)

        setCurrentPage(1)
        setSearch('')
        setSearchInput('')

        setSuccess('OPD baru berhasil ditambahkan.')
      }

      setModalOpen(false)
      setEditingOpd(null)

      await loadOpds(true)
    } catch (requestError) {
      console.error('OPD save error:', requestError)

      const responseData = requestError.response?.data

      if (responseData?.errors) {
        setValidationErrors(responseData.errors)
      } else {
        setError(
          responseData?.message ||
          'Data OPD gagal disimpan. Silakan coba kembali.'
        )
      }
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (opd) => {
    const confirmed = window.confirm(
      `Hapus OPD "${opd.name}"?\n\nData akan dihapus secara administratif. OPD yang masih memiliki website tidak dapat dihapus.`
    )

    if (!confirmed) return

    setDeleteLoading(opd.id)
    setError('')
    setSuccess('')

    try {
      await api.delete(`/api/opds/${opd.id}`)

      setSuccess(`OPD "${opd.name}" berhasil dihapus.`)

      // Jika halaman sekarang menjadi kosong, kembali ke halaman sebelumnya.
      if (opds.length === 1 && currentPage > 1) {
        setCurrentPage((page) => page - 1)
      } else {
        await loadOpds(true)
      }
    } catch (requestError) {
      console.error('OPD delete error:', requestError)

      setError(
        requestError.response?.data?.message ||
        'OPD gagal dihapus. Silakan coba kembali.'
      )
    } finally {
      setDeleteLoading(null)
    }
  }

  const statCards = [
    {
      label: 'TOTAL OPDS',
      value: total,
      description: 'Organisasi terdaftar',
      index: '01',
    },
    {
      label: 'ACTIVE ON THIS PAGE',
      value: activeCount,
      description: 'OPD aktif pada halaman ini',
      index: '02',
    },
    {
      label: 'INACTIVE ON THIS PAGE',
      value: inactiveCount,
      description: 'OPD nonaktif pada halaman ini',
      index: '03',
    },
  ]

  return (
    <div className="simonta-page-enter space-y-8">

      {/* Page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-7">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[150px] leading-none text-[#E7DFD1]">
          02
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / ORGANIZATION REGISTRY
            </p>
          </div>

          <div className="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
              <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
                OPD <span className="italic text-[#963746]">Registry.</span>
              </h1>

              <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
                Pusat pengelolaan organisasi perangkat daerah Kota
                Tasikmalaya dan keterkaitannya dengan website publik.
              </p>
            </div>

            <button
              type="button"
              onClick={openCreateModal}
              className="simonta-angular-action inline-flex items-center justify-center gap-2 self-start bg-[#963746] px-5 py-3 text-[10px] font-bold tracking-[0.13em] text-[#EEE9DD] transition duration-300 hover:-translate-y-0.5 hover:bg-[#762B37] lg:self-auto"
            >
              <Icon name="plus" size={15} />
              REGISTER NEW OPD
            </button>
          </div>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              ORGANIZATION DATA
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              WEBSITE RELATIONSHIP
            </span>

            <span className="border border-[#82918B]/60 bg-[#82918B]/10 px-3 py-1.5 text-[9px] tracking-widest text-[#50665C]">
              API CONNECTED
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
      <section className="grid gap-3 sm:grid-cols-3">
        {statCards.map((card, index) => (
          <article
            key={card.index}
            className="simonta-card-enter relative overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]"
            style={{ '--stagger-index': index }}
          >
            <span className="absolute -right-1 -top-6 font-serif text-[75px] leading-none text-[#E8E0D2]">
              {card.index}
            </span>

            <p className="relative text-[9px] font-bold tracking-[0.15em] text-[#77736C]">
              {card.label}
            </p>

            <h2 className="relative mt-5 font-serif text-4xl text-[#191A20]">
              {loading ? '—' : card.value}
            </h2>

            <p className="relative mt-2 text-[10px] text-[#8B8479]">
              {card.description}
            </p>
          </article>
        ))}
      </section>

      {/* Registry table */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC]">

        <div className="flex flex-col justify-between gap-4 border-b border-[#D8CFBF] p-5 lg:flex-row lg:items-center">
          <div>
            <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
              ORGANIZATION DATABASE / 01
            </p>

            <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
              Registered Organizations
            </h2>

            <p className="mt-1 text-[10px] text-[#817A70]">
              {total} organisasi tercatat dalam database SIMONTA
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <form
              onSubmit={handleSearch}
              className="flex min-w-0 flex-1 sm:flex-initial"
            >
              <input
                type="search"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                placeholder="Search organization..."
                aria-label="Cari OPD"
                className="min-w-0 w-full border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-2.5 text-xs outline-none transition focus:border-[#B69A69] sm:w-56"
              />

              <button
                type="submit"
                title="Search"
                aria-label="Cari"
                className="simonta-icon-action grid size-10 shrink-0 place-items-center bg-[#252B37] text-[#EEE9DD] transition hover:bg-[#963746]"
              >
                <Icon name="search" />
              </button>
            </form>

            <button
              type="button"
              onClick={() => loadOpds(true)}
              disabled={refreshing || loading}
              title="Refresh data"
              aria-label="Refresh data OPD"
              className="simonta-icon-action grid size-10 shrink-0 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] hover:text-[#963746] disabled:opacity-50"
            >
              <Icon
                name="refresh"
                size={16}
              />
            </button>
          </div>
        </div>

        {/* Desktop table */}
        <div className="hidden overflow-x-auto md:block">
          <table className="w-full min-w-[850px] border-collapse text-left">
            <thead>
              <tr className="border-b border-[#D8CFBF] bg-[#F1EBDD]">
                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  ORGANIZATION
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  IDENTIFIER
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  CONTACT
                </th>

                <th className="px-5 py-4 text-center text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  WEBSITES
                </th>

                <th className="px-5 py-4 text-[9px] font-bold tracking-[0.15em] text-[#817A70]">
                  STATUS
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
                    <td colSpan="6" className="px-5 py-6">
                      <div className="simonta-skeleton h-4 w-2/3" />
                    </td>
                  </tr>
                ))
              ) : opds.length === 0 ? (
                <tr>
                  <td colSpan="6" className="px-5 py-14 text-center">
                    <div className="mx-auto grid size-14 place-items-center border border-[#B69A69]/60 text-[#963746]">
                      <Icon name="building" size={24} />
                    </div>

                    <h3 className="mt-4 font-serif text-xl text-[#191A20]">
                      No Organization Found
                    </h3>

                    <p className="mt-2 text-xs text-[#817A70]">
                      {search
                        ? 'Tidak ditemukan OPD dengan kata kunci tersebut.'
                        : 'Belum ada data OPD. Silakan daftarkan organisasi pertama.'}
                    </p>

                    {!search && (
                      <button
                        type="button"
                        onClick={openCreateModal}
                        className="simonta-angular-action mt-5 bg-[#963746] px-5 py-3 text-[9px] font-bold tracking-wider text-white transition hover:bg-[#762B37]"
                      >
                        REGISTER FIRST OPD
                      </button>
                    )}
                  </td>
                </tr>
              ) : (
                opds.map((opd, index) => (
                  <tr
                    key={opd.id}
                    className="group transition duration-200 hover:bg-[#F1EBDD]"
                    style={{ animationDelay: `${index * 45}ms` }}
                  >
                    <td className="max-w-[300px] px-5 py-5">
                      <p className="font-medium text-sm text-[#252B37]">
                        {opd.name}
                      </p>

                      <p className="mt-1 text-[10px] text-[#948B7D]">
                        {opd.email || 'No email registered'}
                      </p>
                    </td>

                    <td className="px-5 py-5">
                      <span className="border border-[#D8CFBF] px-2.5 py-1 text-[10px] tracking-wider text-[#665F56]">
                        {opd.code || '—'}
                      </span>
                    </td>

                    <td className="px-5 py-5">
                      <p className="text-xs text-[#665F56]">
                        {opd.phone || '—'}
                      </p>
                    </td>

                    <td className="px-5 py-5 text-center">
                      <span className="font-serif text-xl text-[#963746]">
                        {opd.websites_count ?? 0}
                      </span>
                    </td>

                    <td className="px-5 py-5">
                      <StatusBadge active={opd.is_active} />
                    </td>

                    <td className="px-5 py-5">
                      <div className="flex justify-end gap-2">
                        <button
                          type="button"
                          onClick={() => openEditModal(opd)}
                          title="Edit OPD"
                          aria-label={`Edit ${opd.name}`}
                          className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] hover:bg-[#B69A69]/10 hover:text-[#963746]"
                        >
                          <Icon name="edit" size={15} />
                        </button>

                        <button
                          type="button"
                          onClick={() => handleDelete(opd)}
                          disabled={deleteLoading === opd.id}
                          title="Delete OPD"
                          aria-label={`Delete ${opd.name}`}
                          className="simonta-icon-action grid size-9 place-items-center border border-[#963746]/25 text-[#963746] transition hover:bg-[#963746] hover:text-white disabled:opacity-50"
                        >
                          {deleteLoading === opd.id
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

        {/* Mobile organization cards */}
        <div className="divide-y divide-[#D8CFBF] md:hidden">
          {loading ? (
            [1, 2, 3].map((item) => (
              <div key={item} className="p-5">
                <div className="simonta-skeleton h-4 w-2/3" />
                <div className="simonta-skeleton mt-3 h-3 w-1/3" />
              </div>
            ))
          ) : opds.length === 0 ? (
            <div className="p-8 text-center">
              <h3 className="font-serif text-xl">No Organization Found</h3>
              <p className="mt-2 text-xs text-[#817A70]">
                Belum ada data OPD yang dapat ditampilkan.
              </p>
            </div>
          ) : (
            opds.map((opd) => (
              <article key={opd.id} className="space-y-4 p-5">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <h3 className="font-medium text-sm text-[#252B37]">
                      {opd.name}
                    </h3>

                    <p className="mt-1 text-[10px] text-[#817A70]">
                      {opd.code || 'No identifier'}
                    </p>
                  </div>

                  <StatusBadge active={opd.is_active} />
                </div>

                <div className="flex items-center justify-between border-t border-[#D8CFBF] pt-3">
                  <div>
                    <p className="text-[9px] tracking-wider text-[#817A70]">
                      REGISTERED WEBSITES
                    </p>

                    <p className="mt-1 font-serif text-xl text-[#963746]">
                      {opd.websites_count ?? 0}
                    </p>
                  </div>

                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => openEditModal(opd)}
                      aria-label={`Edit ${opd.name}`}
                      className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56]"
                    >
                      <Icon name="edit" />
                    </button>

                    <button
                      type="button"
                      onClick={() => handleDelete(opd)}
                      aria-label={`Delete ${opd.name}`}
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
              Showing page {currentPage} of {lastPage} · {total} organizations
            </p>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage <= 1}
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:cursor-not-allowed disabled:opacity-35"
                aria-label="Previous page"
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
                className="simonta-icon-action grid size-9 place-items-center border border-[#D8CFBF] text-[#665F56] transition hover:border-[#B69A69] disabled:cursor-not-allowed disabled:opacity-35"
                aria-label="Next page"
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
            aria-labelledby="opd-modal-title"
            className="simonta-card-enter my-auto w-full max-w-2xl border border-[#B69A69]/50 bg-[#EEE9DD] shadow-[0_25px_80px_rgba(0,0,0,0.25)]"
          >
            <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5 sm:p-7">
              <div>
                <p className="text-[9px] font-bold tracking-[0.2em] text-[#963746]">
                  ORGANIZATION REGISTRY
                </p>

                <h2
                  id="opd-modal-title"
                  className="mt-2 font-serif text-3xl text-[#191A20]"
                >
                  {editingOpd ? 'Edit Organization.' : 'New Organization.'}
                </h2>

                <p className="mt-2 text-xs text-[#817A70]">
                  {editingOpd
                    ? 'Perbarui informasi organisasi perangkat daerah.'
                    : 'Tambahkan organisasi perangkat daerah ke SIMONTA.'}
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
              <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-7">

                <div className="sm:col-span-2">
                  <FormField
                    label="ORGANIZATION NAME"
                    name="name"
                    value={form.name}
                    onChange={handleChange}
                    error={validationErrors.name?.[0]}
                    required
                    placeholder="Contoh: Dinas Komunikasi dan Informatika"
                  />
                </div>

                <FormField
                  label="ORGANIZATION CODE"
                  name="code"
                  value={form.code}
                  onChange={handleChange}
                  error={validationErrors.code?.[0]}
                  placeholder="Contoh: DISKOMINFO"
                />

                <FormField
                  label="EMAIL ADDRESS"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={handleChange}
                  error={validationErrors.email?.[0]}
                  placeholder="opd@example.go.id"
                />

                <FormField
                  label="PHONE NUMBER"
                  name="phone"
                  value={form.phone}
                  onChange={handleChange}
                  error={validationErrors.phone?.[0]}
                  placeholder="Nomor telepon OPD"
                />

                <FormField
                  label="ADDRESS"
                  name="address"
                  value={form.address}
                  onChange={handleChange}
                  error={validationErrors.address?.[0]}
                  placeholder="Alamat kantor OPD"
                />

                <div className="sm:col-span-2">
                  <label
                    htmlFor="opd-description"
                    className="mb-2 block text-[9px] font-bold tracking-[0.13em] text-[#665F56]"
                  >
                    DESCRIPTION
                  </label>

                  <textarea
                    id="opd-description"
                    name="description"
                    value={form.description}
                    onChange={handleChange}
                    rows={3}
                    placeholder="Deskripsi singkat organisasi..."
                    className="w-full resize-y border border-[#D8CFBF] bg-[#FAF8F2] px-3 py-3 text-sm text-[#191A20] outline-none transition placeholder:text-[#AAA294] focus:border-[#B69A69]"
                  />

                  {validationErrors.description?.[0] && (
                    <p className="mt-1.5 text-[10px] text-[#963746]">
                      {validationErrors.description[0]}
                    </p>
                  )}
                </div>

                <label className="sm:col-span-2 flex cursor-pointer items-center justify-between gap-4 border border-[#D8CFBF] bg-[#F7F4EC] p-4 transition hover:border-[#B69A69]">
                  <div>
                    <p className="text-xs font-semibold text-[#252B37]">
                      Organization Status
                    </p>

                    <p className="mt-1 text-[10px] text-[#817A70]">
                      Aktifkan organisasi agar dapat dikelola di SIMONTA.
                    </p>
                  </div>

                  <input
                    type="checkbox"
                    name="is_active"
                    checked={form.is_active}
                    onChange={handleChange}
                    className="size-4 shrink-0 accent-[#963746]"
                  />
                </label>
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
                  disabled={saving}
                  className="simonta-angular-action inline-flex items-center justify-center gap-2 bg-[#963746] px-6 py-3 text-[10px] font-bold tracking-wider text-[#EEE9DD] transition hover:-translate-y-0.5 hover:bg-[#762B37] disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {saving && (
                    <span className="size-3 animate-spin border border-white border-t-transparent" />
                  )}

                  {saving
                    ? 'SAVING...'
                    : editingOpd
                      ? 'SAVE CHANGES'
                      : 'REGISTER OPD'}
                </button>
              </div>
            </form>
          </section>
        </div>
      )}
    </div>
  )
}

export default OpdManagementPage
