
function HomePage() {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
      <section className="w-full max-w-xl rounded-2xl bg-white p-8 shadow-lg">
        <p className="text-sm font-semibold uppercase tracking-widest text-blue-700">
          SIMONTA
        </p>

        <h1 className="mt-3 text-3xl font-bold text-slate-900">
          Frontend Siap Dikembangkan
        </h1>

        <p className="mt-3 text-slate-600">
          Sistem Monitoring Tautan Website OPD
          Diskominfo Kota Tasikmalaya.
        </p>

        <div className="mt-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-800">
          React, Vite, dan Tailwind CSS berhasil dikonfigurasi.
        </div>
      </section>
    </main>
  )
}

export default HomePage
