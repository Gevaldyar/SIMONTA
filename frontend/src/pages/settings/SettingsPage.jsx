import { useEffect, useState } from "react";
import api from "../../services/api";
import "./SettingsPage.css";

const initialProfile = {
  name: "",
  email: "",
};

const initialPassword = {
  current_password: "",
  password: "",
  password_confirmation: "",
};

function getUserFromResponse(responseData) {
  let user =
    responseData?.data ??
    responseData?.user ??
    responseData;

  if (user?.user) {
    user = user.user;
  }

  return user ?? {};
}

function getErrors(error) {
  return error?.response?.data?.errors ?? {};
}

function getErrorMessage(error, fallback) {
  const errors = getErrors(error);
  const firstFieldError = Object.values(errors).flat()[0];

  return (
    firstFieldError ??
    error?.response?.data?.message ??
    fallback
  );
}

function formatDate(date) {
  if (!date) return "-";

  const parsedDate = new Date(date);

  if (Number.isNaN(parsedDate.getTime())) {
    return "-";
  }

  return new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(parsedDate);
}

function FieldError({ message }) {
  if (!message) return null;

  return <p className="settings-field-error">{message}</p>;
}

function SettingsIcon({ type }) {
  const icons = {
    user: (
      <>
        <circle cx="12" cy="8" r="4" />
        <path d="M5 21v-2a7 7 0 0 1 14 0v2" />
      </>
    ),
    shield: (
      <>
        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z" />
        <path d="m9 12 2 2 4-4" />
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
      {icons[type]}
    </svg>
  );
}

export default function SettingsPage() {
  const [profile, setProfile] = useState(initialProfile);
  const [profileForm, setProfileForm] = useState(initialProfile);

  const [passwordForm, setPasswordForm] = useState(initialPassword);

  const [loading, setLoading] = useState(true);
  const [savingProfile, setSavingProfile] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);

  const [pageError, setPageError] = useState("");
  const [profileMessage, setProfileMessage] = useState("");
  const [passwordMessage, setPasswordMessage] = useState("");

  const [profileErrors, setProfileErrors] = useState({});
  const [passwordErrors, setPasswordErrors] = useState({});

  useEffect(() => {
    let cancelled = false;

    async function fetchProfile() {
      try {
        setLoading(true);
        setPageError("");

        const response = await api.get("/api/settings/profile");
        const user = getUserFromResponse(response.data);

        if (cancelled) return;

        const userProfile = {
          id: user.id,
          name: user.name ?? "",
          email: user.email ?? "",
          created_at: user.created_at ?? null,
        };

        setProfile(userProfile);
        setProfileForm({
          name: userProfile.name,
          email: userProfile.email,
        });
      } catch (error) {
        if (cancelled) return;

        setPageError(
          getErrorMessage(
            error,
            "Gagal mengambil data profil. Silakan muat ulang halaman."
          )
        );
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    fetchProfile();

    return () => {
      cancelled = true;
    };
  }, []);

  function handleProfileChange(event) {
    const { name, value } = event.target;

    setProfileForm((current) => ({
      ...current,
      [name]: value,
    }));

    setProfileErrors((current) => ({
      ...current,
      [name]: undefined,
    }));

    setProfileMessage("");
  }

  function handlePasswordChange(event) {
    const { name, value } = event.target;

    setPasswordForm((current) => ({
      ...current,
      [name]: value,
    }));

    setPasswordErrors((current) => ({
      ...current,
      [name]: undefined,
    }));

    setPasswordMessage("");
  }

  async function handleProfileSubmit(event) {
    event.preventDefault();

    setSavingProfile(true);
    setProfileMessage("");
    setProfileErrors({});

    try {
      const response = await api.patch("/api/settings/profile", {
        name: profileForm.name,
        email: profileForm.email,
      });

      const updatedUser = getUserFromResponse(response.data);

      const nextProfile = {
        ...profile,
        ...updatedUser,
        name: updatedUser.name ?? profileForm.name,
        email: updatedUser.email ?? profileForm.email,
      };

      setProfile(nextProfile);
      setProfileForm({
        name: nextProfile.name,
        email: nextProfile.email,
      });

      setProfileMessage(
        response.data?.message ?? "Profil berhasil diperbarui."
      );
    } catch (error) {
      setProfileErrors(getErrors(error));

      setProfileMessage(
        getErrorMessage(
          error,
          "Gagal memperbarui profil. Silakan coba kembali."
        )
      );
    } finally {
      setSavingProfile(false);
    }
  }

  async function handlePasswordSubmit(event) {
    event.preventDefault();

    setSavingPassword(true);
    setPasswordMessage("");
    setPasswordErrors({});

    try {
      const response = await api.put("/api/settings/password", {
        current_password: passwordForm.current_password,
        password: passwordForm.password,
        password_confirmation: passwordForm.password_confirmation,
      });

      setPasswordForm(initialPassword);

      setPasswordMessage(
        response.data?.message ?? "Password berhasil diperbarui."
      );
    } catch (error) {
      setPasswordErrors(getErrors(error));

      setPasswordMessage(
        getErrorMessage(
          error,
          "Gagal memperbarui password. Periksa kembali data yang dimasukkan."
        )
      );
    } finally {
      setSavingPassword(false);
    }
  }

  if (loading) {
    return (
      <main className="settings-page">
        <div className="settings-heading">
          <span className="settings-eyebrow">PREFERENCES / ACCOUNT</span>
          <h1>Pengaturan</h1>
          <p>Kelola informasi akun dan keamanan akses SIMONTA.</p>
        </div>

        <div className="settings-loading">
          <div className="settings-loading-line" />
          <div className="settings-loading-line short" />
          <div className="settings-loading-card" />
        </div>
      </main>
    );
  }

  return (
    <main className="settings-page">
      <header className="settings-heading">
        <span className="settings-eyebrow">PREFERENCES / ACCOUNT</span>

        <div className="settings-title-row">
          <div>
            <h1>Pengaturan</h1>
            <p>
              Kelola informasi akun dan keamanan akses SIMONTA.
            </p>
          </div>

          <div className="settings-heading-mark">
            <SettingsIcon type="user" />
            <span>ACCOUNT</span>
          </div>
        </div>
      </header>

      {pageError && (
        <div className="settings-alert settings-alert-error" role="alert">
          {pageError}
        </div>
      )}

      <section className="settings-overview">
        <div className="settings-overview-icon">
          <SettingsIcon type="user" />
        </div>

        <div className="settings-overview-info">
          <span className="settings-overview-label">ADMINISTRATOR</span>
          <h2>{profile.name || "Admin SIMONTA"}</h2>
          <p>{profile.email || "-"}</p>
        </div>

        <div className="settings-overview-meta">
          <span>ID AKUN</span>
          <strong>#{profile.id ?? "-"}</strong>
          <span className="settings-account-date">
            Bergabung {formatDate(profile.created_at)}
          </span>
        </div>
      </section>

      <div className="settings-section-heading">
        <span>01</span>
        <div>
          <h2>Informasi Profil</h2>
          <p>Perbarui identitas akun administrator.</p>
        </div>
      </div>

      <section className="settings-card">
        <div className="settings-card-heading">
          <div className="settings-card-icon">
            <SettingsIcon type="user" />
          </div>

          <div>
            <h3>Data Akun</h3>
            <p>Informasi ini digunakan untuk identitas akun SIMONTA.</p>
          </div>
        </div>

        <form onSubmit={handleProfileSubmit}>
          <div className="settings-form-grid">
            <div className="settings-field">
              <label htmlFor="profile-name">Nama Administrator</label>
              <input
                id="profile-name"
                type="text"
                name="name"
                value={profileForm.name}
                onChange={handleProfileChange}
                placeholder="Masukkan nama"
                required
                autoComplete="name"
              />
              <FieldError message={profileErrors.name?.[0]} />
            </div>

            <div className="settings-field">
              <label htmlFor="profile-email">Alamat Email</label>
              <input
                id="profile-email"
                type="email"
                name="email"
                value={profileForm.email}
                onChange={handleProfileChange}
                placeholder="Masukkan email"
                required
                autoComplete="email"
              />
              <FieldError message={profileErrors.email?.[0]} />
            </div>
          </div>

          {profileMessage && (
            <div
              className={`settings-form-message ${
                profileErrors.name || profileErrors.email
                  ? "is-error"
                  : "is-success"
              }`}
              role="status"
            >
              {profileMessage}
            </div>
          )}

          <div className="settings-form-actions">
            <button
              type="submit"
              className="settings-button settings-button-primary"
              disabled={savingProfile}
            >
              {savingProfile ? "Menyimpan..." : "Simpan Perubahan"}
              <span aria-hidden="true">↗</span>
            </button>
          </div>
        </form>
      </section>

      <div className="settings-section-heading settings-section-spaced">
        <span>02</span>
        <div>
          <h2>Keamanan Akun</h2>
          <p>Pastikan hanya Anda yang memiliki akses ke akun administrator.</p>
        </div>
      </div>

      <section className="settings-card">
        <div className="settings-card-heading">
          <div className="settings-card-icon">
            <SettingsIcon type="shield" />
          </div>

          <div>
            <h3>Ubah Password</h3>
            <p>
              Gunakan password baru dengan minimal 12 karakter.
            </p>
          </div>
        </div>

        <form onSubmit={handlePasswordSubmit}>
          <div className="settings-password-fields">
            <div className="settings-field">
              <label htmlFor="current-password">Password Saat Ini</label>
              <input
                id="current-password"
                type="password"
                name="current_password"
                value={passwordForm.current_password}
                onChange={handlePasswordChange}
                placeholder="Masukkan password saat ini"
                required
                autoComplete="current-password"
              />
              <FieldError message={passwordErrors.current_password?.[0]} />
            </div>

            <div className="settings-form-grid">
              <div className="settings-field">
                <label htmlFor="new-password">Password Baru</label>
                <input
                  id="new-password"
                  type="password"
                  name="password"
                  value={passwordForm.password}
                  onChange={handlePasswordChange}
                  placeholder="Minimal 12 karakter"
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
                <FieldError message={passwordErrors.password?.[0]} />
              </div>

              <div className="settings-field">
                <label htmlFor="confirm-password">
                  Konfirmasi Password Baru
                </label>
                <input
                  id="confirm-password"
                  type="password"
                  name="password_confirmation"
                  value={passwordForm.password_confirmation}
                  onChange={handlePasswordChange}
                  placeholder="Ulangi password baru"
                  minLength={12}
                  required
                  autoComplete="new-password"
                />
              </div>
            </div>
          </div>

          {passwordMessage && (
            <div
              className={`settings-form-message ${
                passwordErrors.current_password || passwordErrors.password
                  ? "is-error"
                  : "is-success"
              }`}
              role="status"
            >
              {passwordMessage}
            </div>
          )}

          <div className="settings-form-actions">
            <button
              type="submit"
              className="settings-button settings-button-dark"
              disabled={savingPassword}
            >
              {savingPassword ? "Memperbarui..." : "Perbarui Password"}
              <span aria-hidden="true">↗</span>
            </button>
          </div>
        </form>
      </section>

      <section className="settings-information">
        <div className="settings-information-icon">
          <SettingsIcon type="info" />
        </div>

        <div>
          <h3>Informasi Pengaturan SIMONTA</h3>
          <p>
            Interval monitoring diatur secara individual pada setiap website.
            Untuk mengubah interval pemeriksaan otomatis, buka halaman
            <strong> Website Registry</strong>.
          </p>
        </div>
      </section>
    </main>
  );
}