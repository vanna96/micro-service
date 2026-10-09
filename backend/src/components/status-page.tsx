"use client";

import Link from "next/link";
import { ArrowLeft, CircleAlert, Compass, Home, RotateCcw, ServerCrash, ShieldAlert, Timer, Wrench } from "lucide-react";
import styles from "@/styles/not-found.module.css";

type StatusPageProps = { statusCode: number; onRetry?: () => void };
type Tone = "sky" | "purple" | "red" | "amber";

const messages: Record<number, { title: string; description: string; footer: string; tone: Tone }> = {
  403: {
    title: "Access Denied",
    description: "You do not have permission to view this page or this request was blocked by the security firewall.",
    footer: "Return to the main dashboard or contact an administrator if you need access.",
    tone: "red",
  },
  404: {
    title: "Page Not Found",
    description: "The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.",
    footer: "Double check the URL or return to the main dashboard navigation.",
    tone: "sky",
  },
  429: {
    title: "Too Many Requests",
    description: "You have made too many requests in a short time. Please wait a moment and try again.",
    footer: "This limit helps keep the platform available for everyone.",
    tone: "amber",
  },
  500: {
    title: "Internal Server Error",
    description: "An unexpected error occurred while processing your request. Please try again shortly.",
    footer: "If the problem continues, contact support.",
    tone: "purple",
  },
  502: {
    title: "Bad Gateway",
    description: "The service is having trouble connecting to the server. Please try again shortly.",
    footer: "If the problem continues, contact support.",
    tone: "purple",
  },
  503: {
    title: "Service Temporarily Unavailable",
    description: "Our systems are currently undergoing scheduled maintenance or experiencing high demand. We are working hard to restore full service shortly.",
    footer: "Automated health monitoring is active. Please try refreshing in a moment.",
    tone: "amber",
  },
};

function StatusIcon({ statusCode }: { statusCode: number }) {
  const props = { size: 42, strokeWidth: 2, "aria-hidden": true as const };
  if (statusCode === 503) return <Wrench {...props} />;
  if (statusCode === 404) return <Compass {...props} />;
  if (statusCode === 403) return <ShieldAlert {...props} />;
  if (statusCode === 429) return <Timer {...props} />;
  if (statusCode >= 500) return <ServerCrash {...props} />;
  return <CircleAlert {...props} />;
}

export default function StatusPage({ statusCode, onRetry }: StatusPageProps) {
  const message = messages[statusCode] ?? {
    title: "Request Error",
    description: "We could not complete your request. Please try again.",
    footer: "If the problem continues, contact support.",
    tone: "red" as Tone,
  };
  const retry = statusCode >= 500 || statusCode === 429;

  return (
    <main className={styles.page} data-tone={message.tone}>
      <section className={styles.card} aria-labelledby="error-title">
        <div className={styles.icon}><StatusIcon statusCode={statusCode} /></div>
        <div className={styles.badge}>
          <span className={styles.pulseDot} aria-hidden="true" />
          {statusCode === 503 ? "Status 503 • Maintenance" : `Error ${statusCode}`}
        </div>
        <h1 id="error-title">{message.title}</h1>
        <p className={styles.description}>{message.description}</p>
        <div className={styles.actions}>
          {retry ? (
            <>
              <button type="button" className={`${styles.button} ${styles.primary}`} onClick={onRetry ?? (() => window.location.reload())}>
                <RotateCcw size={16} aria-hidden="true" /> {statusCode === 503 ? "Refresh Page" : "Try Again"}
              </button>
              <Link href="/" className={`${styles.button} ${styles.secondary}`}><Home size={16} aria-hidden="true" /> Home</Link>
            </>
          ) : (
            <>
              <Link href="/" className={`${styles.button} ${styles.primary}`}><Home size={16} aria-hidden="true" /> Home</Link>
              <button type="button" className={`${styles.button} ${styles.secondary}`} onClick={() => window.history.back()}>
                <ArrowLeft size={16} aria-hidden="true" /> Go Back
              </button>
            </>
          )}
        </div>
        <p className={styles.footer}>{message.footer}</p>
      </section>
    </main>
  );
}
