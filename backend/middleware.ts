import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

// Common SQL Injection Signatures
const SQLI_PATTERNS = [
  /union(\s+all)?\s+select/i,
  /select\s+.*?\s+from\s+/i,
  /insert\s+into\s+/i,
  /drop\s+(table|database|view)/i,
  /delete\s+from\s+/i,
  /\bor\b\s+['"]?\d+['"]?\s*=\s*['"]?\d+['"]?/i,
  /'\s+or\s+'1'='1/i,
  /--|\/\*|#\s/,
  /information_schema/i,
];

// Common XSS Signatures
const XSS_PATTERNS = [
  /<script\b[^>]*>(.*?)<\/script>/i,
  /javascript\s*:/i,
  /onerror\s*=/i,
  /onload\s*=/i,
  /onclick\s*=/i,
  /<iframe\b[^>]*>/i,
  /document\.cookie/i,
];

// Common Path Traversal / Probe Signatures
const TRAVERSAL_PATTERNS = [
  /\.\.[\/\\]\.\.[\/\\]/,
  /\/etc\/passwd/i,
  /\.env/i,
  /wp-admin/i,
  /phpmyadmin/i,
];

// In-memory cache for IP verification to avoid hitting the backend on every sub-asset
interface FirewallCheckResult {
  blocked: boolean;
  reason?: string;
  country?: string;
}

function escapeHtml(value: string): string {
  return value.replace(/[&<>"']/g, (character) => {
    const entities: Record<string, string> = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };

    return entities[character];
  });
}

function renderForbiddenPage({
  title,
  heading,
  message,
  footer,
}: {
  title: string;
  heading: string;
  message: string;
  footer: string;
}): string {
  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${escapeHtml(title)}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Space+Mono:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
  <style>
    :root { --text-muted: #555555; }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Share Tech Mono', 'Space Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
    html { background-color: #ffffff !important; color: #000000 !important; }
    body { background-color: #ffffff !important; color: #000000 !important; min-height: 100vh; min-height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 24px; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
    .container { max-width: 520px; width: 100%; text-align: left; animation: fadeIn 0.2s ease-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
    h1 { font-size: clamp(2.4rem, 6vw, 3.4rem); font-weight: 400; letter-spacing: -0.01em; line-height: 1.1; margin-bottom: 8px; color: #000000; }
    h2 { font-size: clamp(1.2rem, 3vw, 1.45rem); font-weight: 400; letter-spacing: -0.01em; line-height: 1.3; margin-bottom: 20px; color: #000000; }
    .description { font-size: clamp(0.95rem, 2.2vw, 1.05rem); line-height: 1.6; margin-bottom: 28px; color: #000000; }
    .actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; padding: 9px 22px; font-size: 15px; color: #000000; background: transparent; border: 1px solid #000000; border-radius: 4px; text-decoration: none; cursor: pointer; transition: all 0.15s ease; outline: none; font-family: inherit; }
    .btn:hover { background-color: #000000; color: #ffffff; }
    .btn:active { transform: scale(0.98); }
    .footer { font-size: 13px; line-height: 1.5; color: var(--text-muted); }
  </style>
</head>
<body>
  <main class="container">
    <h1>Error 403</h1>
    <h2>${escapeHtml(heading)}</h2>
    <p class="description">${escapeHtml(message)}</p>
    <div class="actions">
      <a href="/" class="btn">Home</a>
      <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/'" class="btn">Go Back</button>
    </div>
    <p class="footer">${escapeHtml(footer)}</p>
  </main>
</body>
</html>`;
}

async function isClientBlocked(ip: string, countryHeader?: string | null): Promise<FirewallCheckResult> {
  try {
    const backendUrl = process.env.LARAVEL_API_URL || "http://nginx/v1/api";
    const headers: Record<string, string> = { Accept: "application/json" };
    if (countryHeader) {
      headers["CF-IPCountry"] = countryHeader;
      headers["X-Country-Code"] = countryHeader;
    }

    const res = await fetch(`${backendUrl}/security/verify-ip?ip=${encodeURIComponent(ip)}`, {
      cache: "no-store",
      headers,
      signal: AbortSignal.timeout(1500),
    });

    if (res.ok) {
      const data = await res.json();
      const result: FirewallCheckResult = {
        blocked: Boolean(data.blocked),
        reason: data.reason,
        country: data.country_name || data.country_code,
      };
      return result;
    }
  } catch {
    // If backend check fails or times out, failsafe allow to preserve site uptime
  }

  return { blocked: false };
}

export async function middleware(request: NextRequest) {
  const { pathname, search } = request.nextUrl;

  // Skip static assets and internal next files
  if (
    pathname.startsWith("/_next") ||
    pathname.startsWith("/assets") ||
    pathname.startsWith("/branding") ||
    pathname.startsWith("/favicon") ||
    pathname.startsWith("/videos") ||
    pathname.includes(".")
  ) {
    return NextResponse.next();
  }

  // Determine client IP & country headers
  const forwarded = request.headers.get("x-forwarded-for");
  const clientIp = forwarded ? forwarded.split(",")[0].trim() : request.headers.get("x-real-ip") || request.headers.get("cf-connecting-ip") || "127.0.0.1";
  const clientCountry = request.headers.get("cf-ipcountry") || request.headers.get("x-country-code") || "";

  // 1. Check IP & Country Firewall
  const check = await isClientBlocked(clientIp, clientCountry);
  if (check.blocked) {
    const isCountryBlocked = check.reason === "country_access_blocked";
    const title = isCountryBlocked ? "403 - Country Access Restricted" : "403 - Access Denied by Security Firewall";
    const heading = isCountryBlocked ? "Access restricted in your region" : "Access Denied by Security Firewall";
    const message = isCountryBlocked
      ? `Access from your country (${check.country || "your location"}) is not permitted by the security policy.`
      : "Your IP address has been blocked by the security firewall.";
    const refCode = isCountryBlocked ? "SEC-GEO-RESTRICTED" : "SEC-IP-BLACKLIST";

    return new NextResponse(
      renderForbiddenPage({
        title,
        heading,
        message,
        footer: `Reference ID: ${refCode}`,
      }),
      {
        status: 403,
        headers: {
          "Content-Type": "text/html; charset=utf-8",
          "X-Security-Firewall": isCountryBlocked ? "Country-Blocked" : "IP-Blocked",
          "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
          Pragma: "no-cache",
          Expires: "0",
        },
      }
    );
  }

  // 2. WAF URL & Query Inspection (SQLi, XSS, Traversal)
  let fullUri = (pathname + search).replace(/\+/g, " ");
  try {
    fullUri = decodeURIComponent(fullUri);
  } catch {
    // Keep the raw URI when malformed percent encoding cannot be decoded.
  }

  const isSqli = SQLI_PATTERNS.some((pattern) => pattern.test(fullUri));
  const isXss = XSS_PATTERNS.some((pattern) => pattern.test(fullUri));
  const isTraversal = TRAVERSAL_PATTERNS.some((pattern) => pattern.test(fullUri));

  if (isSqli || isXss || isTraversal) {
    const threatName = isSqli ? "SQL Injection" : isXss ? "Cross-Site Scripting" : "Path Traversal Probe";

    return new NextResponse(
      renderForbiddenPage({
        title: "403 Forbidden - Security Firewall",
        heading: "Request Blocked",
        message: "Malicious request payload blocked by Web Application Firewall.",
        footer: `Threat: ${threatName}`,
      }),
      {
        status: 403,
        headers: {
          "Content-Type": "text/html; charset=utf-8",
          "X-Security-Firewall": "Blocked",
          "Cache-Control": "no-store, no-cache, must-revalidate, max-age=0",
          Pragma: "no-cache",
          Expires: "0",
        },
      }
    );
  }

  // 3. Attach Security Headers to All Clean Responses
  const response = NextResponse.next();
  response.headers.set("X-Security-Firewall", "Active");
  response.headers.set("X-Frame-Options", "SAMEORIGIN");
  response.headers.set("X-Content-Type-Options", "nosniff");
  response.headers.set("Referrer-Policy", "strict-origin-when-cross-origin");

  return response;
}

export const config = {
  matcher: ["/((?!api|_next/static|_next/image|favicon.ico).*)"],
};
