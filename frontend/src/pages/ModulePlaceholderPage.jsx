
function ModulePlaceholderPage({ section, title, description }) {
  return (
    <section className="relative min-h-[65vh] overflow-hidden border border-[#D8CFBF] bg-[#F7F4EC] p-7 sm:p-10">
      <div className="pointer-events-none absolute -right-4 top-0 font-serif text-[180px] leading-none text-[#E8E0D2]">
        {section}
      </div>

      <div className="relative flex min-h-[55vh] flex-col justify-center">
        <div className="mb-5 flex items-center gap-3">
          <span className="h-px w-9 bg-[#963746]" />

          <p className="text-[10px] font-semibold tracking-[0.2em] text-[#963746]">
            SIMONTA / MODULE {section}
          </p>
        </div>

        <h1 className="max-w-2xl font-serif text-5xl text-[#191A20] sm:text-6xl">
          {title}
          <span className="text-[#963746]">.</span>
        </h1>

        <p className="mt-5 max-w-lg text-sm leading-7 text-[#77736C]">
          {description}
        </p>

        <div className="mt-9 w-fit border border-[#B69A69] bg-[#EEE9DD] px-4 py-3">
          <p className="text-[9px] font-semibold tracking-[0.2em] text-[#963746]">
            MODULE STATUS
          </p>

          <p className="mt-1 text-xs text-[#555047]">
            Under Development
          </p>
        </div>
      </div>
    </section>
  )
}

export default ModulePlaceholderPage
