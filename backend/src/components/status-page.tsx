"use client";

import Link from "next/link";
import styles from "@/styles/not-found.module.css";

type StatusPageProps = { statusCode: number; onRetry?: () => void };

const messages: Record<
  number,
  { title: string; description: string; footer: string }
> = {
  403: {
    title: "Access Denied",
    description:
      "You do not have permission to view this page or this request was blocked by the security firewall.",
    footer:
      "Return to the main dashboard or contact an administrator if you need access.",
  },
  404: {
    title: "Page Not Found",
    description:
      "The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.",
    footer:
      "Double check the URL or return to the main dashboard navigation.",
  },
  429: {
    title: "Too Many Requests",
    description:
      "You have made too many requests in a short time. Please wait a moment and try again.",
    footer: "This limit helps keep the platform available for everyone.",
  },
  500: {
    title: "Internal Server Error",
    description:
      "An unexpected error occurred while processing your request. Please try again shortly.",
    footer: "If the problem continues, contact support.",
  },
  502: {
    title: "Bad Gateway",
    description:
      "The service is having trouble connecting to the server. Please try again shortly.",
    footer: "If the problem continues, contact support.",
  },
  503: {
    title: "Service Temporarily Unavailable",
    description:
      "Our systems are currently undergoing scheduled maintenance or experiencing high demand. We are working hard to restore full service shortly.",
    footer:
      "Automated health monitoring is active. Please try refreshing in a moment.",
  },
};

export default function StatusPage({ statusCode, onRetry }: StatusPageProps) {
  const message = messages[statusCode] ?? {
    title: "Request Error",
    description: "We could not complete your request. Please try again.",
    footer: "If the problem continues, contact support.",
  };
  const retry = statusCode >= 500 || statusCode === 429;

  const handleGoBack = () => {
    if (typeof window !== "undefined") {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.location.href = "/";
      }
    }
  };

  return (
    <>
      <link rel="stylesheet" href="/fonts/error-fonts/fonts.css" />
      <main className={styles.page}>
        <div className={styles.container}>
          <h1 className={styles.code}>Error {statusCode}</h1>
        <h2 className={styles.subtitle}>{message.title}</h2>
        <p className={styles.description}>{message.description}</p>
        <div className={styles.actions}>
          {retry ? (
            <>
              <button
                type="button"
                className={styles.button}
                onClick={onRetry ?? (() => window.location.reload())}
              >
                {statusCode === 503 ? "Refresh Page" : "Try Again"}
              </button>
              <Link href="/" className={styles.button}>
                Home
              </Link>
            </>
          ) : (
            <>
              <Link href="/" className={styles.button}>
                Home
              </Link>
              <button
                type="button"
                className={styles.button}
                onClick={handleGoBack}
              >
                Go Back
              </button>
            </>
          )}
        </div>
        {message.footer ? <p className={styles.footer}>{message.footer}</p> : null}
      </div>
    </main>
    </>
  );
}
