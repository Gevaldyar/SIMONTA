
function BrandLogo({ compact = false }) {
  return (
    <div className="flex items-center gap-3">
      {/* Logo symbol */}
      <div className="
        relative grid size-11 shrink-0 place-items-center
        border border-[#D7B978]/80
        bg-[#252B37]
      ">
        <span className="
          font-serif text-2xl italic font-semibold
          !text-[#F5F1E8]
        ">
          S
        </span>

        <span className="
          absolute -bottom-1 -right-1
          size-2.5 bg-[#963746]
        " />
      </div>

      {/* Brand identity */}
      {!compact && (
        <div className="flex flex-col">
          <h1 className="
            font-serif text-xl font-bold
            tracking-[0.12em]
            !text-[#F5F1E8]
            drop-shadow-[0_1px_3px_rgba(0,0,0,0.35)]
          ">
            SIMONTA
          </h1>

          <p className="
            mt-0.5 text-[9px] font-semibold
            tracking-[0.19em]
            !text-[#D7B978]
          ">
            THE DIGITAL OBSERVATORY
          </p>
        </div>
      )}
    </div>
  )
}

export default BrandLogo
