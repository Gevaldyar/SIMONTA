import { useEffect, useState } from "react";
import api from "../../services/api";
import "./ReportsPage.css";

const initialFilters = {
  opd_id: "",
  website_id: "",
  status: "",
  date_from: "",
  date_to: "",
};

function extractRows(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.data?.data)) return payload.data.data;
  return [];
}

function getApiMessage(error, fallback) {
  let data = error?.response?.data;

  if (data instanceof Blob) {
    return data.text().then((text) => {
      try {
        const parsed = JSON.parse(text);
        const firstError = Object.values(parsed.errors ?? {}).flat()[0];

        return firstError ?? parsed.message ?? fallback;
      } catch {
        return fallback;
      }
    });
  }

  const firstError = Object.values(data?.errors ?? {}).flat()[0];

  return Promise.resolve(
    firstError ?? data?.message ?? fallback
  );
}

function ReportIcon({ type }) {
  const paths = {
    document: (
      <>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6" />
        <path d="M8 13h8M8 17h8" />
      </>
    ),
    download: (
      <>
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
        <path d="m7 10 5 5 5-5" />
        <path d="M12 15V3" />
      </>
    ),
    filter: (
      <>
        <path d="M4 5h16M7 12h10M10 19h4" />
      </>
    ),
    info: (
      <>
        <circle cx="12" cy="12" r="9" />
        <path d="M12 16v-4" />
        <path d="M12 8h.01" />
      </>
    ),
  };

  return (
    <svg
      viewBox="0 0 24 24"
      width="21"
      height="21"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.7"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      {paths[type]}
    </svg>
  );
}

export default function ReportsPage() {
  const [opds, setOpds] = useState([]);
  const [websites, setWebsites] = useState([]);

  const [filters, setFilters] = useState(initialFilters);

  const [loadingOpds, setLoadingOpds] = useState(true);
  const [loadingWebsites, setLoadingWebsites] = useState(false);
  const [exporting, setExporting] = useState(false);

  const [error, setError] = useState("");
  const [message, setMessage] = useState("");

  // Mengambil daftar OPD.
  useEffect(() => {
    let cancelled = false;

    async function fetchOpds() {
      try {
        setLoadingOpds(true);

        const response = await api.get("/api/opds", {
          params: {
            per_page: 100,
          },
        });

        if (!cancelled) {
          setOpds(extractRows(response.data));
        }
      } catch (err) {
        if (!cancelled) {
          const message = await getApiMessage(
            err,
            "Gagal mengambil daftar OPD."
          );
          setError(message);
        }
      } finally {
        if (!cancelled) {
          setLoadingOpds(false);
        }
      }
    }

    fetchOpds();

    return () => {
      cancelled = true;
    };
  }, []);

  // Mengambil daftar website berdasarkan OPD yang dipilih.
  useEffect(() => {
    let cancelled = false;

    async function fetchWebsites() {
      if (!filters.opd_id) {
        setWebsites([]);
        return;
      }

      try {
        setLoadingWebsites(true);
        setWebsites([]);
        setError("");

        const response = await api.get(
          `/api/opds/${filters.opd_id}/websites`,
          {
            params: {
              per_page: 100,
            },
          }
        );

        if (!cancelled) {
          setWebsites(extractRows(response.data));
        }
      } catch (err) {
        if (!cancelled) {
          const message = await getApiMessage(
            err,
            "Gagal mengambil daftar website OPD."
          );
          setError(message);
        }
      } finally {
        if (!cancelled) {
          setLoadingWebsites(false);
        }
      }
    }

    fetchWebsites();

    return () => {
      cancelled = true;
    };
  }, [filters.opd_id]);

  function handleFilterChange(event) {
    const { name, value } = event.target;

    setFilters((current) => ({
      ...current,
      [name]: value,
      ...(name === "opd_id" ? { website_id: "" } : {}),
    }));

    setError("");
    setMessage("");
  }

  function handleReset() {
    setFilters(initialFilters);
    setWebsites([]);
    setError("");
    setMessage("");
  }

  async function handleExport(event) {
    event.preventDefault();

    setError("");
    setMessage("");

    if (
      filters.date_from &&
      filters.date_to &&
      filters.date_to < filters.date_from
    ) {
      setError(
        "Tanggal akhir tidak boleh lebih awal dari tanggal awal."
      );
      return;
    }

    const params = {};

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== "") {
        params[key] = value;
      }
    });

    try {
      setExporting(true);

      const response = await api.get(
        "/api/reports/scans/export",
        {
          params,
          responseType: "blob",
        }
      );

      const blob = new Blob(
        [response.data],
        { type: "text/csv;charset=utf-8;" }
      );

      const downloadUrl = window.URL.createObjectURL(blob);
      const link = document.createElement("a");

      const date = new Date().toISOString().slice(0, 10);
      link.href = downloadUrl;
      link.download = `laporan-monitoring-simonta-${date}.csv`;

      document.body.appendChild(link);
      link.click();
      link.remove();

      window.URL.revokeObjectURL(downloadUrl);

      setMessage(
        "Laporan berhasil dibuat dan diunduh."
      );
    } catch (err) {
      const message = await getApiMessage(
        err,
        "Gagal mengunduh laporan. Periksa koneksi dan filter."
      );

      setError(message);
    } finally {
      setExporting(false);
    }
  }

  return (
    <main className="reports-page">
      <header className="reports-heading">
        <span className="reports-eyebrow">
          SIMONTA / REPORT CENTER
        </span>

        <div className="reports-heading-row">
          <div>
            <h1>Laporan Monitoring</h1>
            <p>
              Rekapitulasi hasil pemeriksaan website OPD dan teknologi
              yang teridentifikasi.
            </p>
          </div>

          <div className="reports-heading-icon">
            <ReportIcon type="document" />
            <span>REPORTS</span>
          </div>
        </div>
      </header>

      <section className="reports-intro">
        <div className="reports-intro-symbol">
          <ReportIcon type="document" />
        </div>

        <div>
          <span className="reports-intro-label">
            MONITORING DATA EXPORT
          </span>
          <h2>Dokumentasi Hasil Pemeriksaan</h2>
          <p>
            Pilih kriteria laporan untuk mengunduh data pemeriksaan
            website. Data mencakup informasi OPD, website, status
            pemeriksaan, respons HTTP, dan teknologi yang terdeteksi.
          </p>
        </div>

        <div className="reports-intro-number">
          <span>FORMAT</span>
          <strong>CSV</strong>
        </div>
      </section>

      <div className="reports-section-heading">
        <span>01</span>
        <div>
          <h2>Filter Laporan</h2>
          <p>Tentukan data yang ingin dimasukkan ke dalam laporan.</p>
        </div>
      </div>

      <section className="reports-filter-card">
        <div className="reports-card-heading">
          <div className="reports-card-icon">
            <ReportIcon type="filter" />
          </div>

          <div>
            <h3>Kriteria Pemeriksaan</h3>
            <p>
              Filter bersifat opsional. Kosongkan filter untuk
              menyertakan seluruh data.
            </p>
          </div>
        </div>

        <form onSubmit={handleExport}>
          <div className="reports-filter-grid">
            <div className="reports-field">
              <label htmlFor="report-opd">Organisasi Perangkat Daerah</label>

              <select
                id="report-opd"
                name="opd_id"
                value={filters.opd_id}
                onChange={handleFilterChange}
                disabled={loadingOpds}
              >
                <option value="">
                  {loadingOpds ? "Memuat OPD..." : "Semua OPD"}
                </option>

                {opds.map((opd) => (
                  <option key={opd.id} value={opd.id}>
                    {opd.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="reports-field">
              <label htmlFor="report-website">Website</label>

              <select
                id="report-website"
                name="website_id"
                value={filters.website_id}
                onChange={handleFilterChange}
                disabled={!filters.opd_id || loadingWebsites}
              >
                <option value="">
                  {!filters.opd_id
                    ? "Pilih OPD terlebih dahulu"
                    : loadingWebsites
                      ? "Memuat website..."
                      : "Semua Website OPD"}
                </option>

                {websites.map((website) => (
                  <option key={website.id} value={website.id}>
                    {website.name || website.url}
                  </option>
                ))}
              </select>
            </div>

            <div className="reports-field">
              <label htmlFor="report-status">Status Pemeriksaan</label>

              <select
                id="report-status"
                name="status"
                value={filters.status}
                onChange={handleFilterChange}
              >
                <option value="">Semua Status</option>
                <option value="completed">Selesai</option>
                <option value="failed">Gagal</option>
                <option value="queued">Dalam Antrean</option>
                <option value="running">Sedang Berjalan</option>
              </select>
            </div>

            <div className="reports-field">
              <label htmlFor="report-date-from">Tanggal Mulai</label>

              <input
                id="report-date-from"
                type="date"
                name="date_from"
                value={filters.date_from}
                onChange={handleFilterChange}
              />
            </div>

            <div className="reports-field">
              <label htmlFor="report-date-to">Tanggal Akhir</label>

              <input
                id="report-date-to"
                type="date"
                name="date_to"
                value={filters.date_to}
                min={filters.date_from || undefined}
                onChange={handleFilterChange}
              />
            </div>
          </div>

          {error && (
            <div className="reports-message reports-message-error" role="alert">
              {error}
            </div>
          )}

          {message && (
            <div className="reports-message reports-message-success" role="status">
              {message}
            </div>
          )}

          <div className="reports-form-footer">
            <button
              type="button"
              className="reports-button reports-button-reset"
              onClick={handleReset}
              disabled={exporting}
            >
              Reset Filter
            </button>

            <button
              type="submit"
              className="reports-button reports-button-export"
              disabled={exporting || loadingOpds}
            >
              <ReportIcon type="download" />
              {exporting ? "Menyiapkan Laporan..." : "Unduh Laporan CSV"}
            </button>
          </div>
        </form>
      </section>

      <section className="reports-note">
        <div className="reports-note-icon">
          <ReportIcon type="info" />
        </div>

        <div>
          <h3>Informasi Ekspor</h3>
          <p>
            File CSV berisi data riwayat pemeriksaan sesuai filter yang
            dipilih. File dapat dibuka menggunakan Microsoft Excel atau
            aplikasi spreadsheet lainnya. Waktu pemeriksaan menggunakan
            waktu penyelesaian, atau waktu pembuatan untuk pemeriksaan
            yang belum selesai.
          </p>
        </div>
      </section>
    </main>
  );
}