
import { Outlet } from 'react-router'
import BrandLogo from '../components/common/BrandLogo'

function InfrastructureArtwork() {
  return (
    <div
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 overflow-hidden"
    >
      {/* Technical grid */}
      <div
        className="absolute inset-0 opacity-[0.12]"
        style={{
          backgroundImage: `
            linear-gradient(rgba(182,154,105,0.35) 1px, transparent 1px),
            linear-gradient(90deg, rgba(182,154,105,0.35) 1px, transparent 1px)
          `,
          backgroundSize: '58px 58px',
          maskImage: 'linear-gradient(to bottom, black, transparent 90%)',
        }}
      />

      {/* Ambient light */}
      <div className="absolute -right-40 top-1/4 size-[480px] rounded-full bg-[#963746]/15 blur-[110px]" />

      <div className="absolute -bottom-52 -left-32 size-[420px] rounded-full bg-[#B69A69]/10 blur-[100px]" />

      {/* Network infrastructure illustration */}
      <svg
        viewBox="0 0 720 520"
        className="absolute -bottom-8 -right-28 w-[650px] max-w-none opacity-70 sm:-right-16 sm:w-[760px] lg:bottom-0 lg:right-[-90px] lg:w-[850px]"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <defs>
          <linearGradient id="network-line" x1="0" y1="0" x2="720" y2="520">
            <stop stopColor="#B69A69" stopOpacity="0.8" />
            <stop offset="1" stopColor="#963746" stopOpacity="0.15" />
          </linearGradient>

          <radialGradient id="network-node">
            <stop stopColor="#B69A69" />
            <stop offset="1" stopColor="#B69A69" stopOpacity="0" />
          </radialGradient>
        </defs>

        {/* Connection paths */}
        <g stroke="url(#network-line)" strokeWidth="1.2">
          <path d="M86 345L215 278L336 333L455 205L610 250" />
          <path d="M86 345L148 152L315 98L455 205" />
          <path d="M148 152L215 278L315 98" />
          <path d="M215 278L310 430L455 205" />
          <path d="M336 333L485 410L610 250" />
          <path d="M455 205L540 82L610 250" />
          <path d="M315 98L540 82" />
          <path d="M310 430L485 410" />
          <path d="M86 345L310 430" />
        </g>

        {/* Secondary connection paths */}
        <g
          stroke="#B69A69"
          strokeOpacity="0.2"
          strokeWidth="1"
          strokeDasharray="4 7"
        >
          <path d="M148 152L540 82" />
          <path d="M86 345L610 250" />
          <path d="M215 278L610 250" />
          <path d="M315 98L485 410" />
        </g>

        {/* Connection nodes */}
        {[
          [86, 345],
          [148, 152],
          [215, 278],
          [315, 98],
          [336, 333],
          [455, 205],
          [485, 410],
          [540, 82],
          [610, 250],
        ].map(([cx, cy], index) => (
          <g key={`${cx}-${cy}`}>
            <circle
              cx={cx}
              cy={cy}
              r={index === 5 ? 29 : 19}
              fill="url(#network-node)"
              opacity={index === 5 ? 0.35 : 0.16}
            />

            <circle
              cx={cx}
              cy={cy}
              r={index === 5 ? 6 : 4}
              fill={index === 5 ? '#963746' : '#B69A69'}
            />

            <circle
              cx={cx}
              cy={cy}
              r={index === 5 ? 12 : 9}
              stroke="#B69A69"
              strokeOpacity="0.45"
            />
          </g>
        ))}

        {/* Central monitoring target */}
        <circle
          cx="455"
          cy="205"
          r="43"
          stroke="#B69A69"
          strokeOpacity="0.22"
        />

        <circle
          cx="455"
          cy="205"
          r="57"
          stroke="#B69A69"
          strokeOpacity="0.1"
          strokeDasharray="3 7"
        />

        {/* Tiny signal marks */}
        <g
          stroke="#B69A69"
          strokeOpacity="0.55"
          strokeWidth="1"
        >
          <path d="M435 143V126H475V143" />
          <path d="M435 267V284H475V267" />
          <path d="M393 185H376V225H393" />
          <path d="M517 185H534V225H517" />
        </g>
      </svg>
    </div>
  )
}

function AuthLayout() {
  return (
    <main className="min-h-screen bg-[#EEE9DD] lg:grid lg:grid-cols-[1.08fr_0.92fr]">

      {/* Identity panel */}
      <section
        className="
          relative isolate flex min-h-[390px] flex-col
          justify-between overflow-hidden bg-[#191A20]
          px-6 py-7 text-[#F5F1E8]
          sm:min-h-[430px] sm:px-10 sm:py-9
          lg:min-h-screen lg:px-14 lg:py-12
          xl:px-[8%]
        "
      >
        <InfrastructureArtwork />

        {/* Brand */}
        <div className="relative z-10 [&_*]:!text-[#F5F1E8] [&_span]:!text-[#D7B978]">
          <BrandLogo />
        </div>

        {/* Main message */}
        <div className="relative z-10 my-14 max-w-[570px] lg:my-20">
          <div className="mb-7 flex items-center gap-3">
            <span className="h-px w-9 bg-[#963746]" />

            <span className="text-[9px] font-semibold tracking-[0.25em] !text-[#D7B978]">
              DIGITAL INFRASTRUCTURE / TASIKMALAYA
            </span>
          </div>

          <h1
            className="
              font-serif text-[clamp(2.8rem,5vw,5.2rem)]
              leading-[1.04] tracking-[-0.045em]
              !text-[#F5F1E8]
            "
            style={{ color: '#F5F1E8' }}
          >
            See the
            <br />

            <span
              className="italic !text-[#D7B978]"
              style={{ color: '#D7B978' }}
            >
              infrastructure.
            </span>

            <br />
            Understand
            <br />
            the network.
          </h1>

          <p
            className="
              mt-7 max-w-[390px] text-xs leading-7
              !text-[#D5D0C6] sm:text-sm
            "
            style={{ color: '#D5D0C6' }}
          >
            Platform monitoring infrastruktur digital untuk
            mengawasi, mengidentifikasi, dan memahami teknologi
            website OPD Kota Tasikmalaya.
          </p>
        </div>

        {/* Bottom information */}
        <div
          className="
            relative z-10 flex flex-wrap items-end
            justify-between gap-5 border-t border-[#51483B]/80
            pt-5
          "
        >
          <div>
            <p className="text-[8px] tracking-[0.22em] text-[#D7B978]">
              SYSTEM CLASSIFICATION
            </p>

            <p className="mt-1 text-[10px] tracking-[0.12em] text-[#E0DBD2]">
              ADMINISTRATIVE / INTERNAL ACCESS
            </p>
          </div>

          <div className="text-right">
            <p className="font-serif text-2xl italic text-[#D7B978]">
              01—04
            </p>

            <p className="mt-1 text-[8px] tracking-[0.18em] text-[#C5BFB4]">
              DIGITAL OBSERVATORY
            </p>
          </div>
        </div>
      </section>

      {/* Login panel */}
      <section
        className="
          flex min-h-[600px] flex-col justify-between
          bg-[#EEE9DD] px-6 py-8
          sm:px-10 sm:py-10
          lg:min-h-screen lg:px-12 lg:py-12
          xl:px-[12%]
        "
      >
        {/* Top label */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="size-1.5 rounded-full bg-[#82918B]" />

            <span className="text-[9px] font-semibold tracking-[0.18em] text-[#68645D]">
              SYSTEM OPERATIONAL
            </span>
          </div>

          <span className="hidden text-[9px] tracking-[0.15em] text-[#918879] sm:block">
            SECURE ACCESS / 01
          </span>
        </div>

        {/* Login content */}
        <div className="mx-auto w-full max-w-[430px] py-14 lg:py-16">
          <Outlet />
        </div>

        {/* Footer */}
        <footer
          className="
            flex flex-col justify-between gap-2
            border-t border-[#D8CFBF] pt-5
            text-[9px] tracking-[0.12em] text-[#817A70]
            sm:flex-row sm:items-center
          "
        >
          <span>
            © {new Date().getFullYear()} SIMONTA
          </span>

          <span>
            DISKOMINFO KOTA TASIKMALAYA
          </span>
        </footer>
      </section>
    </main>
  )
}

export default AuthLayout
