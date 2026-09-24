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
const ipCache = new Map<string, { blocked: boolean; expires: number }>();

async function isIpBlocked(ip: string): Promise<boolean> {
  if (!ip || ip === "127.0.0.1" || ip === "::1") {
    return false;
  }

  const now = Date.now();
  const cached = ipCache.get(ip);
  if (cached && cached.expires > now) {
    return cached.blocked;
  }

  try {
    const backendUrl = process.env.LARAVEL_API_URL || "http://nginx/v1/api";
    const res = await fetch(`${backendUrl}/security/verify-ip?ip=${encodeURIComponent(ip)}`, {
      cache: "no-store",
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(1000), // 1s fast timeout
    });

    if (res.ok) {
      const data = await res.json();
      const blocked = Boolean(data.blocked);
      ipCache.set(ip, { blocked, expires: now + 15_000 }); // Cache for 15s
      return blocked;
    }
  } catch (err) {
    // If backend check fails or times out, failsafe allow to preserve site uptime
  }

  return false;
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

  // Determine client IP
  const forwarded = request.headers.get("x-forwarded-for");
  const clientIp = forwarded ? forwarded.split(",")[0].trim() : request.headers.get("x-real-ip") || "127.0.0.1";

  // 1. Check IP Blacklist
  if (await isIpBlocked(clientIp)) {
    return new NextResponse(
      `<!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>403 Forbidden - Security Firewall</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
          .card { background: #1e293b; border: 1px solid #ef4444; border-radius: 12px; max-width: 460px; width: 100%; padding: 32px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
          h1 { color: #ef4444; font-size: 22px; margin: 0 0 12px 0; }
          p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0; }
          .badge { display: inline-block; background: #334155; color: #f87171; font-family: monospace; padding: 4px 10px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
          .footer { font-size: 12px; color: #64748b; border-top: 1px solid #334155; padding-top: 16px; }
        </style>
      </head>
      <body>
        <div class="card">
          <h1>Access Blocked</h1>
          <p>Your IP address has been flagged and blocked by the V-POS Application Firewall.</p>
          <div class="badge">IP: ${clientIp}</div>
          <div class="footer">V-POS Central Security & Threat Mitigation Engine</div>
        </div>
      </body>
      </html>`,
      {
        status: 403,
        headers: {
          "Content-Type": "text/html; charset=utf-8",
          "X-Security-Firewall": "Blocked",
        },
      }
    );
  }

  // 2. WAF URL & Query Inspection (SQLi, XSS, Traversal)
  const fullUri = decodeURIComponent(pathname + search);

  const isSqli = SQLI_PATTERNS.some((pattern) => pattern.test(fullUri));
  const isXss = XSS_PATTERNS.some((pattern) => pattern.test(fullUri));
  const isTraversal = TRAVERSAL_PATTERNS.some((pattern) => pattern.test(fullUri));

  if (isSqli || isXss || isTraversal) {
    const threatName = isSqli ? "SQL Injection" : isXss ? "Cross-Site Scripting" : "Path Traversal Probe";

    return new NextResponse(
      `<!DOCTYPE html>
      <html lang="en">
      <head>
        <meta charset="utf-8">
        <title>403 Forbidden - Security Firewall</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
          .card { background: #1e293b; border: 1px solid #ef4444; border-radius: 12px; max-width: 480px; width: 100%; padding: 32px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
          h1 { color: #ef4444; font-size: 22px; margin: 0 0 12px 0; }
          p { color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0; }
          .badge { display: inline-block; background: #334155; color: #f87171; font-family: monospace; padding: 4px 10px; border-radius: 6px; font-size: 13px; margin-bottom: 20px; }
          .footer { font-size: 12px; color: #64748b; border-top: 1px solid #334155; padding-top: 16px; }
        </style>
      </head>
      <body>
        <div class="card">
          <h1>Request Blocked</h1>
          <p>Malicious request payload blocked by Web Application Firewall.</p>
          <div class="badge">Threat: ${threatName}</div>
          <div class="footer">V-POS Central Security & Threat Mitigation Engine</div>
        </div>
      </body>
      </html>`,
      {
        status: 403,
        headers: {
          "Content-Type": "text/html; charset=utf-8",
          "X-Security-Firewall": "Blocked",
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
