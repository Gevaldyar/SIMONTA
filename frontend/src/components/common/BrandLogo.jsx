
function BrandLogo({ compact = false }) {
  return (
    <div className="flex items-center gap-3">
      <div className="relative grid size-11 shrink-0 place-items-center border border-[#B69A69]/70 bg-[#252B37]">
        <span className="font-serif text-2xl italic text-[#EEE9DD]">
          S
        </span>

        <span className="absolute -bottom-1 -right-1 size-2.5 bg-[#963746]" />
      </div>

      {!compact && (
        <div>
          <h1 className="font-serif text-xl font-bold tracking-[0.12em] text-[#EEE9DD]">
            SIMONTA
          </h1>

          <p className="mt-0.5 text-[9px] tracking-[0.19em] text-[#B69A69]">
            THE DIGITAL OBSERVATORY
          </p>
        </div>
      )}
    </div>
  )
}

export default BrandLogo
