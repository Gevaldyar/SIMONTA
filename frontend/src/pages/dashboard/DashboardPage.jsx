
function MetricPanel({ index, label, description }) {
  return (
    <article className="relative min-h-[155px] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-5 transition duration-300 hover:-translate-y-1 hover:border-[#B69A69]">
      <span className="absolute -right-2 -top-7 font-serif text-[90px] leading-none text-[#E8E0D2]">
        {index}
      </span>

      <div className="relative">
        <p className="text-[10px] font-semibold tracking-[0.16em] text-[#77736C]">
          {label}
        </p>

        <h3 className="mt-5 font-serif text-4xl text-[#191A20]">
          —
        </h3>

        <p className="mt-2 text-[10px] text-[#8B8479]">
          {description}
        </p>
      </div>
    </article>
  )
}

function DashboardPage() {
  const metrics = [
    {
      index: '01',
      label: 'REGISTERED WEBSITES',
      description: 'Data website belum tersedia',
    },
    {
      index: '02',
      label: 'WEBSITES ONLINE',
      description: 'Menunggu hasil pemeriksaan',
    },
    {
      index: '03',
      label: 'WEBSITES OFFLINE',
      description: 'Menunggu hasil pemeriksaan',
    },
    {
      index: '04',
      label: 'DETECTED TECHNOLOGIES',
      description: 'Mesin identifikasi belum aktif',
    },
  ]

  return (
    <div className="space-y-8">

      {/* Editorial page heading */}
      <section className="relative overflow-hidden border-b border-[#CFC4B2] pb-8">
        <div className="pointer-events-none absolute -right-2 -top-12 select-none font-serif text-[180px] leading-none text-[#E7DFD1]">
          01
        </div>

        <div className="relative">
          <div className="mb-5 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
              SIMONTA / CONTROL ROOM
            </p>
          </div>

          <h1 className="font-serif text-5xl font-normal tracking-tight text-[#191A20] sm:text-6xl">
            Digital <span className="italic text-[#963746]">Observatory.</span>
          </h1>

          <p className="mt-4 max-w-xl text-sm leading-7 text-[#77736C]">
            Ruang kendali untuk mengawasi, memeriksa, dan memahami
            infrastruktur digital website OPD Kota Tasikmalaya.
          </p>

          <div className="mt-6 flex flex-wrap gap-2">
            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              PUBLIC WEBSITES
            </span>

            <span className="border border-[#D0C4B2] px-3 py-1.5 text-[9px] tracking-widest text-[#665F56]">
              ADMINISTRATOR ACCESS
            </span>
          </div>
        </div>
      </section>

      {/* Summary metrics */}
      <section>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <p className="text-[10px] font-semibold tracking-[0.18em] text-[#963746]">
              SYSTEM OVERVIEW
            </p>

            <h2 className="mt-1 font-serif text-2xl text-[#191A20]">
              Monitoring Summary
            </h2>
          </div>

          <span className="hidden text-[9px] tracking-widest text-[#8B8479] sm:block">
            DATA AWAITING INITIALIZATION
          </span>
        </div>

        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          {metrics.map((metric) => (
            <MetricPanel key={metric.index} {...metric} />
          ))}
        </div>
      </section>

      {/* Monitoring and technology panels */}
      <section className="grid gap-5 xl:grid-cols-[1.5fr_0.85fr]">

        <article className="border border-[#D8CFBF] bg-[#F7F4EC]">
          <div className="flex items-start justify-between border-b border-[#D8CFBF] p-5">
            <div>
              <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
                INSPECTION LOG / 01
              </p>

              <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
                Monitoring Activity
              </h2>
            </div>

            <span className="border border-[#B69A69]/60 px-2 py-1 text-[9px] tracking-widest text-[#81704C]">
              NOT ACTIVE
            </span>
          </div>

          <div className="flex min-h-[260px] flex-col items-center justify-center px-6 py-10 text-center">
            <div className="relative mb-6 grid size-20 place-items-center border border-[#B69A69]">
              <div className="absolute inset-2 border border-[#B69A69]/40" />

              <span className="font-serif text-3xl italic text-[#963746]">
                01
              </span>
            </div>

            <h3 className="font-serif text-xl text-[#191A20]">
              Awaiting First Inspection
            </h3>

            <p className="mt-3 max-w-sm text-xs leading-6 text-[#817A70]">
              Belum ada siklus monitoring yang dijalankan.
              Data aktivitas akan ditampilkan setelah modul monitoring
              berhasil diimplementasikan.
            </p>
          </div>
        </article>

        <article className="border border-[#3A3940] bg-[#252B37] text-[#EEE9DD]">
          <div className="border-b border-[#55515A] p-5">
            <p className="text-[9px] font-semibold tracking-[0.2em] text-[#B69A69]">
              TECHNOLOGY ARCHIVE / 02
            </p>

            <h2 className="mt-2 font-serif text-2xl">
              Technology Registry
            </h2>
          </div>

          <div className="flex min-h-[260px] flex-col justify-center p-6">
            <div className="font-serif text-6xl italic text-[#B69A69]/50">
              ∅
            </div>

            <h3 className="mt-3 font-serif text-xl">
              No Records Yet
            </h3>

            <p className="mt-3 text-xs leading-6 text-[#B8B4AA]">
              Belum ada teknologi yang teridentifikasi.
              Hasil deteksi akan muncul setelah technology detection
              engine dikembangkan.
            </p>

            <div className="mt-6 border-t border-[#55515A] pt-4">
              <p className="text-[9px] tracking-[0.18em] text-[#B69A69]">
                DETECTION ENGINE
              </p>

              <p className="mt-2 text-xs text-[#B8B4AA]">
                Development in progress
              </p>
            </div>
          </div>
        </article>

      </section>

      {/* Monitoring configuration */}
      <section className="border border-[#D8CFBF] bg-[#F7F4EC] p-5 sm:p-7">
        <div className="mb-5">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            OPERATING PARAMETERS / 03
          </p>

          <h2 className="mt-2 font-serif text-2xl text-[#191A20]">
            Monitoring Protocol
          </h2>
        </div>

        <div className="grid gap-5 border-t border-[#D8CFBF] pt-5 sm:grid-cols-3">
          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              TARGET SCOPE
            </p>

            <p className="mt-2 font-medium text-[#252B37]">
              Public Websites
            </p>
          </div>

          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              PLANNED INTERVAL
            </p>

            <p className="mt-2 font-medium text-[#252B37]">
              Every 60 Minutes
            </p>
          </div>

          <div>
            <p className="text-[9px] tracking-widest text-[#817A70]">
              EXECUTION ENGINE
            </p>

            <p className="mt-2 font-medium text-[#963746]">
              Not Implemented
            </p>
          </div>
        </div>
      </section>

    </div>
  )
}

export default DashboardPage
