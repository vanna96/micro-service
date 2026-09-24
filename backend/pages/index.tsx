import type { GetServerSideProps, InferGetServerSidePropsType } from "next";
import Head from "next/head";
import { FormEvent, useEffect, useState } from "react";
import { ArrowRight, Building2, Eye, EyeOff, LogOut, Settings, Store } from "lucide-react";
import { VPosDashboard } from "@/components/vpos-dashboard";
import {
  centralPortalUrl,
  isCentralHost,
  portalSession,
  requestHost,
  type PortalSession,
  type PortalTenant,
} from "@/lib/next-portal-auth";
import styles from "@/styles/vpos-portal.module.css";
import { useTranslation } from "@/lib/i18n/i18n";

type LoginProps = {
  mode: "login";
};

type ChooseProps = {
  mode: "choose";
  session: PortalSession;
};

type PosProps = {
  mode: "pos";
  session: PortalSession;
  centralUrl: string;
};

type PageProps = LoginProps | ChooseProps | PosProps;

async function csrfToken(): Promise<string> {
  const response = await fetch("/next/auth/csrf", {
    credentials: "same-origin",
    headers: { accept: "application/json" },
  });
  const payload = (await response.json()) as { token?: string };

  if (!response.ok || !payload.token) {
    throw new Error("Unable to start a secure session. Please try again.");
  }

  return payload.token;
}

function VLineArtBackground() {
  return (
    <div className={styles.vLineBackground} aria-hidden="true">
      <svg
        className={styles.vLineSvg}
        viewBox="0 0 1440 900"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        preserveAspectRatio="xMidYMin slice"
      >
        <defs>
          {/* Luminous Champagne Rose Gold Ribbon Gradients */}
          <linearGradient id="ribbonFront" x1="10%" y1="15%" x2="90%" y2="85%">
            <stop offset="0%" stopColor="#fff8f5" />
            <stop offset="25%" stopColor="#fce5df" />
            <stop offset="50%" stopColor="#f2c7bd" />
            <stop offset="75%" stopColor="#e8aca0" />
            <stop offset="100%" stopColor="#fce4de" />
          </linearGradient>

          <linearGradient id="ribbonBack" x1="85%" y1="20%" x2="15%" y2="80%">
            <stop offset="0%" stopColor="#ebd0c7" stopOpacity="0.6" />
            <stop offset="50%" stopColor="#df998d" stopOpacity="0.4" />
            <stop offset="100%" stopColor="#f3cbc2" stopOpacity="0.6" />
          </linearGradient>

          <linearGradient id="geomDraftGrad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#f7dbd3" stopOpacity="0.35" />
            <stop offset="50%" stopColor="#e5a89d" stopOpacity="0.22" />
            <stop offset="100%" stopColor="#f7dbd3" stopOpacity="0.35" />
          </linearGradient>

          <radialGradient id="apexCelestialGlow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stopColor="#f8d5cb" stopOpacity="0.16" />
            <stop offset="40%" stopColor="#f2c2b7" stopOpacity="0.07" />
            <stop offset="75%" stopColor="#ecd2cb" stopOpacity="0.015" />
            <stop offset="100%" stopColor="#ecd2cb" stopOpacity="0" />
          </radialGradient>

          {/* Atelier Micro Paper-Tooth Displacement */}
          <filter id="atelierHandTooth" x="-10%" y="-10%" width="120%" height="120%">
            <feTurbulence type="fractalNoise" baseFrequency="0.042" numOctaves="3" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="2.0" xChannelSelector="R" yChannelSelector="G" />
          </filter>

          {/* Specular Glint Filter */}
          <filter id="starGlint" x="-50%" y="-50%" width="200%" height="200%">
            <feGaussianBlur stdDeviation="2" result="blur" />
            <feMerge>
              <feMergeNode in="blur" />
              <feMergeNode in="SourceGraphic" />
            </feMerge>
          </filter>
        </defs>

        {/* Ambient Celestial Halo Behind the Apex */}
        <circle cx="720" cy="600" r="480" fill="url(#apexCelestialGlow)" />

        {/* --- ATELIER SACRED GEOMETRY UNDERLAY --- */}
        {/* Outer Harmonic Orbital Ring */}
        <circle
          cx="720"
          cy="600"
          r="440"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.9"
          strokeDasharray="2 12"
          vectorEffect="non-scaling-stroke"
        />

        {/* Golden Ratio Inner Harmony Ring */}
        <circle
          cx="720"
          cy="600"
          r="272"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.8"
          strokeDasharray="4 8"
          vectorEffect="non-scaling-stroke"
        />

        {/* Dynamic Tangent Draft Rays */}
        <line
          x1="220"
          y1="100"
          x2="1220"
          y2="100"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.75"
          strokeDasharray="6 14"
          vectorEffect="non-scaling-stroke"
        />
        <line
          x1="360"
          y1="240"
          x2="1080"
          y2="240"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.7"
          strokeDasharray="4 10"
          vectorEffect="non-scaling-stroke"
        />

        {/* 45-Degree Golden Section Axis Lines */}
        <line
          x1="240"
          y1="120"
          x2="720"
          y2="600"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.65"
          strokeDasharray="3 7"
          vectorEffect="non-scaling-stroke"
        />
        <line
          x1="1200"
          y1="120"
          x2="720"
          y2="600"
          stroke="url(#geomDraftGrad)"
          strokeWidth="0.65"
          strokeDasharray="3 7"
          vectorEffect="non-scaling-stroke"
        />

        {/* Subtle Atelier Monospace Notations */}
        <g opacity="0.32" fill="#dca094" fontFamily="monospace" fontSize="8" letterSpacing="0.25em">
          <text x="720" y="650" textAnchor="middle">· V-POS ATELIER · GEOMETRY Φ</text>
          <text x="360" y="232" textAnchor="start">R 272 · 45°</text>
          <text x="1080" y="232" textAnchor="end">Φ 1.618033</text>
        </g>

        {/* --- MÖBIUS CONTINUOUS CALLIGRAPHIC RIBBON V --- */}
        {/* Ribbon Reverse Shading (Gives 3D Over-Under Illusion) */}
        <path
          d="M 685,585
             C 660,610 680,660 720,665
             C 755,670 780,630 755,595
             C 730,565 690,580 720,620
             C 745,655 775,615 805,570"
          stroke="url(#ribbonBack)"
          strokeWidth="3.5"
          strokeLinecap="round"
          filter="url(#atelierHandTooth)"
          vectorEffect="non-scaling-stroke"
        />

        {/* Expressive Flowing Silk Ribbon (Variable Width Downstroke) */}
        <path
          d="M 140,75
             C 230,55 330,85 415,175
             C 500,265 575,410 650,520
             C 680,565 708,615 725,640
             C 742,615 770,565 800,520
             C 875,410 950,265 1035,175
             C 1120,85 1220,55 1310,75
             C 1313,76 1315,79 1312,82
             C 1222,65 1125,95 1042,183
             C 958,272 882,416 808,525
             C 778,570 750,620 730,642
             C 715,620 688,570 658,525
             C 584,416 508,272 424,183
             C 341,95 244,65 142,82
             Z"
          fill="url(#ribbonFront)"
          opacity="0.38"
          filter="url(#atelierHandTooth)"
        />

        {/* Primary Master-Stroke Calligraphic Continuous Flow with Infinity Twist */}
        <path
          d="M 130,85
             C 210,65  320,95  405,185
             C 490,275 565,420 640,530
             C 675,580 702,625 720,642
             C 740,660 765,650 755,618
             C 740,575 695,575 690,615
             C 685,655 725,670 748,642
             C 768,618 795,575 830,525
             C 905,415 980,270 1065,180
             C 1150,90 1250,60 1320,80
             C 1335,85 1345,100 1335,115
             C 1320,130 1295,115 1310,95"
          fill="none"
          stroke="url(#ribbonFront)"
          strokeWidth="2.0"
          strokeLinecap="round"
          strokeLinejoin="round"
          filter="url(#atelierHandTooth)"
          opacity="0.75"
          vectorEffect="non-scaling-stroke"
        />

        {/* Secondary Floating Whisper Sketch-Line (Quill Pen Echo) */}
        <path
          d="M 165,105
             C 245,85  350,120 430,205
             C 510,290 580,430 655,538
             C 685,582 710,622 725,635
             C 740,622 765,582 795,538
             C 870,430 940,290 1020,205
             C 1100,120 1205,85 1285,105"
          fill="none"
          stroke="url(#ribbonFront)"
          strokeWidth="0.9"
          strokeDasharray="220 8 140 6"
          strokeLinecap="round"
          filter="url(#atelierHandTooth)"
          opacity="0.4"
          vectorEffect="non-scaling-stroke"
        />

        {/* --- CELESTIAL STIPPLING & KINTSUGI GOLD NODES --- */}
        {/* Nucleus Apex Pearl & Orbit Rings */}
        <circle cx="720" cy="620" r="9" stroke="url(#ribbonFront)" strokeWidth="0.8" opacity="0.5" />
        <circle cx="720" cy="620" r="3.5" fill="#ffffff" stroke="url(#ribbonFront)" strokeWidth="1.5" />

        {/* Star Glint Crosshairs at Harmonic Tangents */}
        <g filter="url(#starGlint)" opacity="0.6">
          {/* Left Tangent Star */}
          <circle cx="410" cy="188" r="2" fill="#fff5f2" />
          <line x1="404" y1="188" x2="416" y2="188" stroke="#e8aca0" strokeWidth="0.8" />
          <line x1="410" y1="182" x2="410" y2="194" stroke="#e8aca0" strokeWidth="0.8" />

          {/* Right Tangent Star */}
          <circle cx="1040" cy="188" r="2" fill="#fff5f2" />
          <line x1="1034" y1="188" x2="1046" y2="188" stroke="#e8aca0" strokeWidth="0.8" />
          <line x1="1040" y1="182" x2="1040" y2="194" stroke="#e8aca0" strokeWidth="0.8" />
        </g>

        {/* Micro Gold-Leaf Dust Particles */}
        <circle cx="280" cy="120" r="1.2" fill="#f5cec5" opacity="0.6" />
        <circle cx="530" cy="340" r="1.5" fill="#f5cec5" opacity="0.7" />
        <circle cx="620" cy="480" r="1.2" fill="#f5cec5" opacity="0.5" />
        <circle cx="820" cy="480" r="1.2" fill="#f5cec5" opacity="0.5" />
        <circle cx="910" cy="340" r="1.5" fill="#f5cec5" opacity="0.7" />
        <circle cx="1160" cy="120" r="1.2" fill="#f5cec5" opacity="0.6" />
      </svg>
    </div>
  );
}

function LoginPage() {
  const { t, locale, setLocale } = useTranslation();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [remember, setRemember] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      const token = await csrfToken();
      const response = await fetch("/next/auth/login", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          "x-csrf-token": token,
        },
        body: JSON.stringify({ username, password, remember }),
      });
      const payload = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
      };

      if (!response.ok) {
        const validationMessage = payload.errors
          ? Object.values(payload.errors).flat().find(Boolean)
          : undefined;
        throw new Error(validationMessage || payload.message || t("invalidCredentials"));
      }

      window.location.reload();
    } catch (loginError) {
      setError(loginError instanceof Error ? loginError.message : t("signInFailed"));
      setSubmitting(false);
    }
  }

  return (
    <>
      <Head>
        <title>{t("signIn")} | V-POS</title>
        <meta name="description" content="Sign in to V-POS" />
        <link rel="icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="icon" href="/branding/v-pos-mark-32.png" type="image/png" sizes="32x32" />
        <link rel="shortcut icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
      </Head>
      <main className={styles.page}>
        <VLineArtBackground />
        <div className={styles.loginShell}>
          <section className={styles.panel} aria-labelledby="login-heading">
            <div className={styles.brandHeader}>
              <img className={styles.logo} src="/branding/v-pos-logo.svg" alt="V-POS" />
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <button
                  type="button"
                  style={{
                    background: "rgba(255, 255, 255, 0.9)",
                    border: "1px solid #e2e8f0",
                    borderRadius: "9999px",
                    padding: "4px 10px",
                    fontSize: "12px",
                    fontWeight: 600,
                    cursor: "pointer",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "4px",
                    color: "#334155",
                  }}
                  onClick={() => setLocale(locale === "en" ? "km" : "en")}
                  title={t("language")}
                >
                  🌐 <span>{locale === "en" ? "KM" : "EN"}</span>
                </button>
                <span className={styles.proBadge}>POS PRO</span>
              </div>
            </div>

            <div className={styles.panelHeader}>
              <h1 className={styles.title} id="login-heading">{t("welcomeBack")}</h1>
              <p className={styles.subtitle}>{t("signInSubtitle")}</p>
            </div>

            <form onSubmit={submit}>
              {error ? <div className={styles.error} role="alert">{error}</div> : null}

              <div className={styles.field}>
                <label className={styles.label} htmlFor="username">{t("usernameLabel")}</label>
                <input
                  autoComplete="username"
                  autoFocus
                  className={styles.input}
                  id="username"
                  name="username"
                  onChange={(event) => setUsername(event.target.value)}
                  placeholder="admin@example.local"
                  required
                  value={username}
                />
              </div>

              <div className={styles.field}>
                <label className={styles.label} htmlFor="password">{t("password")}</label>
                <div className={styles.inputWrap}>
                  <input
                    autoComplete="current-password"
                    className={`${styles.input} ${styles.inputWithReveal}`}
                    id="password"
                    name="password"
                    onChange={(event) => setPassword(event.target.value)}
                    placeholder="••••••••••••"
                    required
                    type={showPassword ? "text" : "password"}
                    value={password}
                  />
                  <button
                    aria-label={showPassword ? t("hidePassword") : t("showPassword")}
                    className={styles.reveal}
                    onClick={() => setShowPassword((visible) => !visible)}
                    type="button"
                  >
                    {showPassword ? <EyeOff aria-hidden="true" size={17} /> : <Eye aria-hidden="true" size={17} />}
                  </button>
                </div>
              </div>

              <label className={styles.remember}>
                <input checked={remember} onChange={(event) => setRemember(event.target.checked)} type="checkbox" />
                <span>{t("keepMeSignedIn")}</span>
              </label>

              <button className={styles.primaryButton} disabled={submitting} type="submit">
                {submitting ? t("signingIn") : t("signIn")}
              </button>
            </form>
          </section>
        </div>
      </main>
    </>
  );
}

function TenantChooser({ session }: { session: PortalSession }) {
  const { t, locale, setLocale } = useTranslation();
  const [opening, setOpening] = useState("");
  const [error, setError] = useState("");
  const [signingOut, setSigningOut] = useState(false);

  async function openTenant(tenantId: string, domain: string) {
    const key = `${tenantId}:${domain}`;
    setOpening(key);
    setError("");

    try {
      const token = await csrfToken();
      const response = await fetch("/next/auth/tenant-handoff", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          "x-csrf-token": token,
        },
        body: JSON.stringify({ tenant_id: tenantId, domain }),
      });
      const payload = (await response.json().catch(() => ({}))) as { message?: string; url?: string };

      if (!response.ok || !payload.url) {
        throw new Error(payload.message || t("unableToOpenTenant"));
      }

      window.location.assign(payload.url);
    } catch (handoffError) {
      setError(handoffError instanceof Error ? handoffError.message : t("unableToOpenTenant"));
      setOpening("");
    }
  }

  async function signOut() {
    setSigningOut(true);
    try {
      const token = await csrfToken();
      await fetch("/next/auth/logout", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          accept: "application/json",
          "content-type": "application/json",
          "x-csrf-token": token,
        },
      });
    } finally {
      window.location.reload();
    }
  }

  const userIdentifier = session.user.email || session.user.username;

  return (
    <>
      <Head>
        <title>{t("selectAStore")} | V-POS</title>
        <meta name="description" content="Select a store for V-POS" />
        <link rel="icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="icon" href="/branding/v-pos-mark-32.png" type="image/png" sizes="32x32" />
        <link rel="shortcut icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
      </Head>
      <main className={styles.page}>
        <VLineArtBackground />
        <div className={styles.chooserShell}>
          <header className={styles.header}>
            <div className={styles.headerBrand}>
              <img className={styles.logo} src="/branding/v-pos-logo.svg" alt="V-POS" />
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <button
                  type="button"
                  style={{
                    background: "rgba(255, 255, 255, 0.9)",
                    border: "1px solid #e2e8f0",
                    borderRadius: "9999px",
                    padding: "4px 10px",
                    fontSize: "12px",
                    fontWeight: 600,
                    cursor: "pointer",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: "4px",
                    color: "#334155",
                  }}
                  onClick={() => setLocale(locale === "en" ? "km" : "en")}
                  title={t("language")}
                >
                  🌐 <span>{locale === "en" ? "KM" : "EN"}</span>
                </button>
                <span className={styles.proBadge}>POS PRO</span>
              </div>
            </div>

            <div className={styles.headerActions}>
              <a className={styles.adminLink} href="/admin/dashboard">
                <Settings aria-hidden="true" size={14} />
                <span>{t("administration")}</span>
              </a>

              <button className={styles.signout} disabled={signingOut} onClick={signOut} type="button">
                <LogOut aria-hidden="true" size={14} />
                <span>{signingOut ? t("signingOut") : t("signOut")}</span>
              </button>
            </div>
          </header>

          <div className={styles.chooserIntro}>
            <h1 className={styles.chooserTitle}>{t("selectAStore")}</h1>
            <p className={styles.chooserSubtitle}>{t("chooseStoreSubtitle")}</p>
          </div>

          {error ? <div className={styles.error} role="alert">{error}</div> : null}

          {session.tenants.length > 0 ? (
            <div className={styles.storeGrid}>
              {session.tenants.map((tenant) => {
                const primaryDomain = tenant.domains[0] || `${tenant.alias || tenant.id}.localhost`;
                const key = `${tenant.id}:${primaryDomain}`;
                const isOpening = opening === key;

                return (
                  <div
                    className={styles.storeCard}
                    key={tenant.id}
                    onClick={() => openTenant(tenant.id, primaryDomain)}
                    role="button"
                    tabIndex={0}
                  >
                    {/* Media wrap matching POS product card */}
                    <div className={styles.storeMediaWrap}>
                      <div className={styles.storeMediaIcon}>
                        <Store aria-hidden="true" size={32} />
                      </div>

                      {/* Top-left badge (like UOM: kg) */}
                      <span className={styles.storeTopBadge}>
                        {t("storeHash")}{tenant.alias || tenant.id}
                      </span>

                      {/* Bottom-left badge (like 15% OFF green badge) */}
                      <span className={styles.storeBottomBadge}>
                        <span className={styles.badgePulseDot} />
                        <span>{t("liveTerminal")}</span>
                      </span>
                    </div>

                    {/* Body with SKU and Title */}
                    <div className={styles.storeBody}>
                      <span className={styles.storeSku}>{tenant.id}</span>
                      <h2 className={styles.storeTitle} title={tenant.name}>
                        {tenant.name}
                      </h2>
                    </div>

                    {/* Footer with divider line, domain, and Open button */}
                    <div className={styles.storeFooter}>
                      <span className={styles.storeDomainText}>{primaryDomain}</span>
                      <button
                        className={styles.storeOpenPill}
                        disabled={opening !== ""}
                        onClick={(e) => {
                          e.stopPropagation();
                          openTenant(tenant.id, primaryDomain);
                        }}
                        type="button"
                      >
                        {isOpening ? (
                          <span className={styles.openingBadge}>{t("opening")}</span>
                        ) : (
                          <>
                            <span>{t("open")}</span>
                            <ArrowRight aria-hidden="true" size={13} />
                          </>
                        )}
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          ) : (
            <div className={styles.panel} style={{ textAlign: "center", padding: "40px 20px" }}>
              <Building2 aria-hidden="true" size={28} style={{ color: "#a1a1aa", margin: "0 auto 12px" }} />
              <div style={{ fontWeight: 600, color: "#18181b" }}>{t("noAssignedStores")}</div>
              <div style={{ fontSize: 13, color: "#71717a", marginTop: 4 }}>
                {t("noStoresDescription")}
              </div>
            </div>
          )}

          <div className={styles.accountFooter}>
            {t("signedInAs")} {userIdentifier}
          </div>
        </div>
      </main>
    </>
  );
}

function PosPage({ centralUrl }: { centralUrl: string }) {
  return (
    <>
      <Head>
        <title>V-POS | Point of Sale</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
        <meta name="description" content="V-POS modern point-of-sale system" />
        <link rel="icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="icon" href="/branding/v-pos-mark-32.png" type="image/png" sizes="32x32" />
        <link rel="shortcut icon" href="/branding/v-pos-mark.svg" type="image/svg+xml" />
        <link rel="apple-touch-icon" href="/branding/v-pos-mark.png" />
      </Head>
      <VPosDashboard centralUrl={centralUrl} />
    </>
  );
}

export default function Home(props: InferGetServerSidePropsType<typeof getServerSideProps>) {
  if (props.mode === "pos") return <PosPage centralUrl={props.centralUrl} />;
  if (props.mode === "choose") return <TenantChooser session={props.session} />;
  return <LoginPage />;
}

export const getServerSideProps: GetServerSideProps<PageProps> = async ({ req }) => {
  const host = requestHost(req.headers);
  const central = isCentralHost(host);
  const session = await portalSession(req.headers);

  if (central) {
    return session
      ? { props: { mode: "choose", session } }
      : { props: { mode: "login" } };
  }

  if (!session?.tenant) {
    return {
      redirect: {
        destination: centralPortalUrl(req.headers),
        permanent: false,
      },
    };
  }

  return {
    props: {
      mode: "pos",
      session,
      centralUrl: centralPortalUrl(req.headers),
    },
  };
};
