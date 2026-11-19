
const transitionStyles = `
  .simonta-transition-overlay {
    animation: simontaLocalExit 3.2s cubic-bezier(0.77, 0, 0.18, 1) forwards;
  }

  .simonta-transition-grid {
    position: absolute;
    inset: 0;
    z-index: 0;
    opacity: 0.4;
    pointer-events: none;
    background-image:
      linear-gradient(rgba(182,154,105,.13) 1px, transparent 1px),
      linear-gradient(90deg, rgba(182,154,105,.13) 1px, transparent 1px);
    background-size: 58px 58px;
    mask-image: linear-gradient(to bottom, black, transparent 95%);
    animation: simontaGridMove 3s linear infinite;
  }

  .simonta-transition-glow {
    position: absolute;
    z-index: 0;
    width: min(75vw, 650px);
    aspect-ratio: 1;
    border-radius: 50%;
    pointer-events: none;
    background: radial-gradient(
      circle,
      rgba(150,55,70,.27),
      rgba(182,154,105,.08) 40%,
      transparent 70%
    );
    animation: simontaGlow 2.8s ease-in-out both;
  }

  .simonta-transition-art {
    position: absolute;
    z-index: 1;
    width: min(950px, 95vw);
    max-height: 90vh;
    opacity: 0;
    pointer-events: none;
    animation: simontaNetwork 2.2s ease-out .1s forwards;
  }

  .simonta-transition-connections {
    fill: none;
    stroke: #b69a69;
    stroke-width: 1.5;
    stroke-dasharray: 8 8;
    stroke-dashoffset: 1500;
    animation: simontaDraw 1.8s ease-out .1s forwards;
  }

  .simonta-transition-secondary {
    fill: none;
    stroke: #963746;
    stroke-width: 1.2;
    stroke-dasharray: 3 9;
    opacity: 0;
    animation: simontaSecondary .8s ease-out .65s forwards;
  }

  .simonta-transition-node-glow {
    fill: rgba(182,154,105,.2);
    transform-box: fill-box;
    transform-origin: center;
    animation: simontaPulse 1.2s ease-in-out infinite alternate;
  }

  .simonta-transition-node {
    fill: #b69a69;
    stroke: #eee9dd;
    stroke-width: .8;
    stroke-opacity: .7;
  }

  .simonta-transition-target {
    fill: none;
    stroke: #963746;
    stroke-width: 1.5;
    transform-box: fill-box;
    transform-origin: center;
    animation: simontaTarget 1.8s ease-out infinite;
  }

  .simonta-transition-target-outer {
    fill: none;
    stroke: #b69a69;
    stroke-width: 1;
    stroke-dasharray: 3 8;
    transform-box: fill-box;
    transform-origin: center;
    animation: simontaRotate 9s linear infinite;
  }

  .simonta-transition-crosshair {
    fill: none;
    stroke: #b69a69;
    stroke-width: 1.2;
  }

  .simonta-transition-content {
    position: relative;
    z-index: 2;
    width: min(420px, calc(100% - 40px));
    text-align: center;
    animation: simontaContent .9s cubic-bezier(.22,1,.36,1) both;
  }

  .simonta-transition-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    animation: simontaBrand .8s ease-out .1s both;
  }

  .simonta-transition-mark {
    position: relative;
    display: grid;
    flex-shrink: 0;
    width: 46px;
    height: 46px;
    place-items: center;
    border: 1px solid rgba(182,154,105,.75);
    background: #252b37;
  }

  .simonta-transition-mark span {
    color: #eee9dd;
    font-family: Georgia, serif;
    font-size: 27px;
    font-style: italic;
  }

  .simonta-transition-mark i {
    position: absolute;
    right: -4px;
    bottom: -4px;
    width: 11px;
    height: 11px;
    background: #963746;
  }

  .simonta-transition-status {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 9px 12px;
    border: 1px solid rgba(182,154,105,.3);
    color: #c7b38b;
    font-size: 9px;
    letter-spacing: .17em;
    animation: simontaBrand .8s .35s both;
  }

  .simonta-transition-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #82918b;
    box-shadow: 0 0 12px rgba(130,145,139,.8);
    animation: simontaPulse .8s ease-in-out infinite alternate;
  }

  .simonta-transition-progress {
    height: 2px;
    overflow: hidden;
    background: rgba(182,154,105,.2);
  }

  .simonta-transition-progress-fill {
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #963746, #b69a69);
    transform: scaleX(0);
    transform-origin: left;
    animation: simontaProgress 2.3s cubic-bezier(.55,0,.2,1) .2s forwards;
  }

  .simonta-transition-footer {
    position: absolute;
    z-index: 2;
    bottom: 28px;
    left: 0;
    right: 0;
    text-align: center;
    color: #77736c;
    font-size: 8px;
    letter-spacing: .2em;
  }

  @keyframes simontaLocalExit {
    0%, 78% {
      opacity: 1;
      clip-path: inset(0 0 0 0);
    }
    100% {
      opacity: 0;
      clip-path: inset(0 0 100% 0);
      visibility: hidden;
    }
  }

  @keyframes simontaGridMove {
    from { background-position: 0 0; }
    to { background-position: 58px 58px; }
  }

  @keyframes simontaGlow {
    0% { opacity: 0; transform: scale(.7); }
    50% { opacity: 1; transform: scale(1.05); }
    100% { opacity: .65; transform: scale(1); }
  }

  @keyframes simontaNetwork {
    0% { opacity: 0; transform: scale(.78) translateY(25px); }
    45% { opacity: .4; }
    100% { opacity: .52; transform: scale(1) translateY(0); }
  }

  @keyframes simontaDraw {
    to { stroke-dashoffset: 0; }
  }

  @keyframes simontaSecondary {
    to { opacity: .65; }
  }

  @keyframes simontaPulse {
    from { opacity: .3; transform: scale(.8); }
    to { opacity: 1; transform: scale(1.2); }
  }

  @keyframes simontaTarget {
    0% { opacity: .8; transform: scale(.65); }
    100% { opacity: 0; transform: scale(1.5); }
  }

  @keyframes simontaRotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
  }

  @keyframes simontaContent {
    from {
      opacity: 0;
      transform: translateY(22px);
      filter: blur(7px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
      filter: blur(0);
    }
  }

  @keyframes simontaBrand {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
  }

  @keyframes simontaProgress {
    to { transform: scaleX(1); }
  }

  @media (prefers-reduced-motion: reduce) {
    .simonta-transition-grid,
    .simonta-transition-glow,
    .simonta-transition-art,
    .simonta-transition-connections,
    .simonta-transition-secondary,
    .simonta-transition-node-glow,
    .simonta-transition-target,
    .simonta-transition-target-outer,
    .simonta-transition-content,
    .simonta-transition-brand,
    .simonta-transition-status,
    .simonta-transition-progress-fill {
      animation-duration: 0.5s;
      animation-iteration-count: 1;
    }

    .simonta-transition-overlay {
      animation-duration: 1s;
    }
  }
`

function SystemEntryTransition({ onFinish }) {
  return (
    <div
      className="simonta-transition-overlay"
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 99999,
        display: 'grid',
        placeItems: 'center',
        overflow: 'hidden',
        isolation: 'isolate',
        backgroundColor: '#191A20',
        color: '#EEE9DD',
        pointerEvents: 'auto',
      }}
      role="status"
      aria-label="SIMONTA system initialization"
      onAnimationEnd={(event) => {
        if (event.target === event.currentTarget) {
          onFinish?.()
        }
      }}
    >
      <style>{transitionStyles}</style>

      <div className="simonta-transition-grid" />
      <div className="simonta-transition-glow" />

      <svg
        className="simonta-transition-art"
        viewBox="0 0 900 580"
        fill="none"
        aria-hidden="true"
      >
        <g className="simonta-transition-connections">
          <path d="M100 340L245 230L390 330L540 170L720 260" />
          <path d="M245 230L310 90L540 170" />
          <path d="M100 340L310 470L390 330" />
          <path d="M390 330L550 470L720 260" />
          <path d="M540 170L650 70L720 260" />
          <path d="M310 90L650 70" />
          <path d="M310 470L550 470" />
          <path d="M245 230L550 470" />
          <path d="M310 90L720 260" />
        </g>

        <g className="simonta-transition-secondary">
          <path d="M100 340L720 260" />
          <path d="M245 230L720 260" />
          <path d="M310 90L550 470" />
          <path d="M100 340L540 170" />
        </g>

        {[
          [100, 340],
          [245, 230],
          [310, 90],
          [390, 330],
          [540, 170],
          [550, 470],
          [650, 70],
          [720, 260],
        ].map(([cx, cy], index) => (
          <g key={`${cx}-${cy}`}>
            <circle
              className="simonta-transition-node-glow"
              cx={cx}
              cy={cy}
              r={index === 4 ? 28 : 17}
            />
            <circle
              className="simonta-transition-node"
              cx={cx}
              cy={cy}
              r={index === 4 ? 6 : 4}
            />
          </g>
        ))}

        <circle
          className="simonta-transition-target"
          cx="540"
          cy="170"
          r="48"
        />

        <circle
          className="simonta-transition-target-outer"
          cx="540"
          cy="170"
          r="68"
        />

        <path
          className="simonta-transition-crosshair"
          d="M520 103V82H560V103M520 237V258H560V237M473 150H452V190H473M607 150H628V190H607"
        />
      </svg>

      <div className="simonta-transition-content">
        <div className="simonta-transition-brand">
          <div className="simonta-transition-mark">
            <span>S</span>
            <i />
          </div>

          <div className="text-left">
            <p className="font-serif text-2xl font-bold tracking-[0.12em] text-[#EEE9DD]">
              SIMONTA
            </p>
            <p className="mt-1 text-[8px] tracking-[0.24em] text-[#B69A69]">
              THE DIGITAL OBSERVATORY
            </p>
          </div>
        </div>

        <div className="mt-14 text-center">
          <div className="simonta-transition-status">
            <span className="simonta-transition-status-dot" />
            SECURE SESSION ESTABLISHED
          </div>

          <h1 className="mt-7 font-serif text-4xl tracking-tight text-[#EEE9DD] sm:text-5xl">
            System
            <span className="block italic text-[#B69A69]">
              initialized.
            </span>
          </h1>

          <p className="mt-5 text-[10px] tracking-[0.22em] text-[#AAA69D]">
            PREPARING DIGITAL INFRASTRUCTURE
          </p>
        </div>

        <div className="mx-auto mt-12 w-[220px] max-w-full">
          <div className="simonta-transition-progress">
            <span className="simonta-transition-progress-fill" />
          </div>

          <div className="mt-3 flex justify-between text-[8px] tracking-[0.16em] text-[#918879]">
            <span>INITIALIZING SYSTEM</span>
            <span>SIMONTA / 01</span>
          </div>
        </div>
      </div>

      <div className="simonta-transition-footer">
        DISKOMINFO KOTA TASIKMALAYA
      </div>
    </div>
  )
}

export default SystemEntryTransition
