import React, { useState, useEffect, useRef } from "react";
import { fetchPosDisplayPromotions, PromoSlide, normalizeMediaUrl } from "@/lib/pos-api";
import { getEcho } from "@/lib/echo";
import { useTranslation } from "@/lib/i18n/i18n";

export { type PromoSlide };

interface PosDisplayPromotionsProps {
  mode?: "hero" | "compact";
  className?: string;
  token?: string;
  type?: string; // "second_screen" | "mobile" | "web"
}

export const PosDisplayPromotions: React.FC<PosDisplayPromotionsProps> = ({
  mode = "compact",
  className = "",
  token = "",
  type = "second_screen",
}) => {
  const { t } = useTranslation();
  const [slides, setSlides] = useState<PromoSlide[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const [isMuted, setIsMuted] = useState(true);
  const [isPlaying, setIsPlaying] = useState(true);
  const [videoError, setVideoError] = useState(false);

  const videoRef = useRef<HTMLVideoElement>(null);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  // Fetch promotional slides 100% from backend API split by type (second_screen / mobile / web)
  useEffect(() => {
    let isMounted = true;
    setIsLoading(true);
    fetchPosDisplayPromotions(token, type).then((backendSlides) => {
      if (isMounted) {
        setSlides(backendSlides || []);
        setIsLoading(false);
      }
    });

    // Listen to real-time WebSocket update on token channel
    if (token) {
      const echo = getEcho();
      if (echo) {
        const channel = echo.channel(`pos-display.${token}`);
        const handlePromotionSync = (event: any) => {
          if (isMounted && event.action === "promotions_updated" && Array.isArray(event.promotions)) {
            setSlides(event.promotions);
            setCurrentIndex(0);
          }
        };

        channel.listen(".PosDisplaySync", handlePromotionSync);
        return () => {
          isMounted = false;
          // This channel is shared with the cart display. Removing every
          // listener here would stop cart updates when the promotion layout
          // changes between idle and active modes.
          channel.stopListening(".PosDisplaySync", handlePromotionSync);
        };
      }
    }

    return () => {
      isMounted = false;
    };
  }, [token, type]);

  // Video autoplay & synchronization
  const hasSlides = slides.length > 0;
  const currentSlide = hasSlides ? (slides[currentIndex % slides.length] || slides[0]) : null;
  const isVideo = Boolean(currentSlide && currentSlide.type === "video" && currentSlide.mediaUrl && !videoError);

  useEffect(() => {
    const video = videoRef.current;
    if (!video || !isVideo) return;

    video.defaultMuted = true;
    video.muted = isMuted;

    // Trigger playback
    const playPromise = video.play();
    if (playPromise !== undefined) {
      playPromise
        .then(() => {
          setIsPlaying(true);
        })
        .catch((err) => {
          // Autoplay policy retry strictly muted
          video.muted = true;
          setIsMuted(true);
          video.play().then(() => setIsPlaying(true)).catch(() => undefined);
        });
    }
  }, [currentIndex, isVideo, isMuted]);

  // Auto-advance timer (6 seconds per slide, paused on hover or if paused)
  useEffect(() => {
    if (isPaused || slides.length <= 1) return;

    timerRef.current = setInterval(() => {
      setCurrentIndex((prev) => (prev + 1) % slides.length);
    }, 6000);

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [slides.length, isPaused]);

  if (isLoading) {
    return (
      <div
        className={`d-flex align-items-center justify-content-center p-4 rounded-4 ${
          mode === "hero" ? "h-100 min-vh-50" : ""
        } ${className}`}
        style={{
          background: "linear-gradient(135deg, #0f172a 0%, #1e293b 100%)",
          color: "rgba(255, 255, 255, 0.6)",
          minHeight: mode === "hero" ? "480px" : "260px",
        }}
      >
        <div className="spinner-border spinner-border-sm text-primary me-2.5"></div>
        <span className="fs-13 fw-medium">{t("loadingLivePromotions")}</span>
      </div>
    );
  }

  if (!hasSlides || !currentSlide) {
    return null;
  }

  const handlePrev = (e: React.MouseEvent) => {
    e.stopPropagation();
    setCurrentIndex((prev) => (prev - 1 + slides.length) % slides.length);
  };

  const handleNext = (e: React.MouseEvent) => {
    e.stopPropagation();
    setCurrentIndex((prev) => (prev + 1) % slides.length);
  };

  const toggleMute = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (videoRef.current) {
      const nextMuted = !isMuted;
      videoRef.current.muted = nextMuted;
      setIsMuted(nextMuted);
    }
  };

  const togglePlay = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (videoRef.current) {
      if (videoRef.current.paused) {
        videoRef.current.play();
        setIsPlaying(true);
      } else {
        videoRef.current.pause();
        setIsPlaying(false);
      }
    }
  };

  // -------------------------------------------------------------
  // HERO MODE: High-Impact Kiosk Presentation for Idle State
  // -------------------------------------------------------------
  if (mode === "hero") {
    return (
      <div
        className={`pos-promo-hero-card ${className}`}
        onMouseEnter={() => setIsPaused(true)}
        onMouseLeave={() => setIsPaused(false)}
      >
        {/* Background Media: Looping Video or Image or Rich Gradient */}
        {isVideo ? (
          <div className="pos-promo-media-stage">
            <video
              ref={videoRef}
              key={currentSlide.mediaUrl}
              src={normalizeMediaUrl(currentSlide.mediaUrl)}
              autoPlay
              loop
              muted={isMuted}
              playsInline
              preload="auto"
              onError={() => setVideoError(true)}
              className="pos-promo-video-player"
            />
            {/* Subtle bottom gradient scrim so video remains bright & unobstructed */}
            <div className="pos-promo-hero-scrim" />
          </div>
        ) : currentSlide.type === "image" && currentSlide.mediaUrl ? (
          <div
            className="pos-promo-image-stage"
            style={{ backgroundImage: `url(${normalizeMediaUrl(currentSlide.mediaUrl)})` }}
          >
            <div className="pos-promo-hero-scrim" />
          </div>
        ) : (
          <div
            className="pos-promo-gradient-stage"
            style={{ background: currentSlide.gradient }}
          >
            <div className="pos-promo-ambient-glow" />
          </div>
        )}

        {/* Foreground Content */}
        <div className="pos-promo-hero-content">
          {/* Top Bar: Badge, Video Controls & Navigation */}
          <div className="d-flex align-items-center justify-content-between">
            <div className="d-flex align-items-center gap-2">
              <span
                className="badge shadow-sm rounded-pill px-3 py-1.5 fs-12 fw-bold text-uppercase d-inline-flex align-items-center gap-1.5"
                style={{
                  backgroundColor: currentSlide.badgeBg || "rgba(255, 255, 255, 0.95)",
                  color: currentSlide.badgeColor || "#0f172a",
                  letterSpacing: "0.05em",
                }}
              >
                <i className={currentSlide.icon}></i>
                {currentSlide.badge}
              </span>

              {isVideo && (
                <div className="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fs-11 fw-bold d-flex align-items-center gap-1.5">
                  <span className="pos-live-dot"></span>
                  {t("hdVideoReel")}
                </div>
              )}
            </div>

            {/* Controls: Audio mute + Play/Pause + Prev/Next */}
            <div className="d-flex align-items-center gap-2">
              {isVideo && (
                <>
                  <button
                    type="button"
                    className="btn btn-sm btn-dark bg-opacity-60 text-white border-0 rounded-circle d-flex align-items-center justify-content-center p-0 pos-ctrl-btn"
                    onClick={togglePlay}
                    title={isPlaying ? t("pauseVideo") : t("playVideo")}
                  >
                    <i className={isPlaying ? "ri-pause-line fs-18" : "ri-play-line fs-18"}></i>
                  </button>
                  <button
                    type="button"
                    className="btn btn-sm btn-dark bg-opacity-60 text-white border-0 rounded-circle d-flex align-items-center justify-content-center p-0 pos-ctrl-btn"
                    onClick={toggleMute}
                    title={isMuted ? t("unmuteAudio") : t("muteAudio")}
                  >
                    <i className={isMuted ? "ri-volume-mute-line fs-18" : "ri-volume-up-line fs-18"}></i>
                  </button>
                </>
              )}

              <div className="d-flex align-items-center gap-1">
                <button
                  type="button"
                  className="btn btn-sm btn-dark bg-opacity-60 text-white border-0 rounded-circle d-flex align-items-center justify-content-center p-0 pos-ctrl-btn"
                  onClick={handlePrev}
                  title={t("previousPromotion")}
                >
                  <i className="ri-arrow-left-s-line fs-20"></i>
                </button>
                <button
                  type="button"
                  className="btn btn-sm btn-dark bg-opacity-60 text-white border-0 rounded-circle d-flex align-items-center justify-content-center p-0 pos-ctrl-btn"
                  onClick={handleNext}
                  title={t("nextPromotion")}
                >
                  <i className="ri-arrow-right-s-line fs-20"></i>
                </button>
              </div>
            </div>
          </div>

          {/* Headline & Description Overlay */}
          <div className="my-auto py-3">
            {currentSlide.discount && (
              <div className="mb-3">
                <span className="badge bg-warning text-dark px-3 py-1.5 rounded-pill fs-14 fw-bolder font-monospace shadow-sm">
                  ⚡ {currentSlide.discount}
                </span>
              </div>
            )}
            <h1 className="display-5 fw-bolder text-white mb-2 lh-1.2 text-shadow-md">
              {currentSlide.title}
            </h1>
            <p className="fs-18 text-white-75 mb-0 text-shadow-sm" style={{ maxWidth: "650px", lineHeight: "1.5" }}>
              {currentSlide.subtitle}
            </p>
          </div>

          {/* Bottom Interactive Slide Switcher Tabs */}
          <div className="pt-3 border-top border-white border-opacity-15 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-auto">
            <span className="fs-14 text-white-75 fw-medium d-flex align-items-center gap-2">
              <i className="ri-checkbox-circle-fill text-success fs-16"></i>
              {currentSlide.tag || t("availableForAllCustomers")}
            </span>

            {/* Interactive slide tabs */}
            <div className="d-flex align-items-center gap-1.5 flex-wrap">
              {slides.map((s, idx) => (
                <button
                  key={s.id || idx}
                  type="button"
                  className={`btn btn-sm rounded-pill px-3 py-1 fs-12 fw-bold d-flex align-items-center gap-1.5 transition-all ${
                    idx === currentIndex
                      ? "btn-white bg-white text-dark shadow-sm"
                      : "btn-dark bg-opacity-40 text-white-50 border-0 hover-opacity-100"
                  }`}
                  onClick={() => setCurrentIndex(idx)}
                >
                  <i className={s.icon}></i>
                  <span>{s.badge.split("•")[0].trim()}</span>
                </button>
              ))}
            </div>
          </div>
        </div>

        <style jsx>{`
          .pos-promo-hero-card {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 520px;
            border-radius: 28px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.35);
            background-color: #0f172a;
          }

          .pos-promo-media-stage,
          .pos-promo-image-stage,
          .pos-promo-gradient-stage {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
          }

          .pos-promo-video-player {
            width: 100%;
            height: 100%;
            object-fit: cover;
          }

          .pos-promo-image-stage {
            background-size: cover;
            background-position: center;
          }

          /* Light gradient only at the bottom 40% so video remains clear and visible */
          .pos-promo-hero-scrim {
            position: absolute;
            inset: 0;
            background: linear-gradient(
              180deg,
              rgba(15, 23, 42, 0.45) 0%,
              rgba(15, 23, 42, 0.1) 40%,
              rgba(15, 23, 42, 0.75) 80%,
              rgba(15, 23, 42, 0.95) 100%
            );
            z-index: 2;
          }

          .pos-promo-ambient-glow {
            position: absolute;
            top: -20%;
            right: -10%;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.4) 0%, transparent 70%);
            pointer-events: none;
          }

          .pos-promo-hero-content {
            position: relative;
            z-index: 5;
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 36px;
          }

          .pos-live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #ef4444;
            box-shadow: 0 0 8px #ef4444;
            animation: pulse-red 1.5s infinite;
          }

          @keyframes pulse-red {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
          }

          .pos-ctrl-btn {
            width: 38px;
            height: 38px;
            backdrop-filter: blur(10px);
            transition: all 0.2s ease;
          }

          .pos-ctrl-btn:hover {
            background-color: rgba(255, 255, 255, 0.35) !important;
            transform: scale(1.08);
          }

          .text-shadow-md {
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.6);
          }

          .text-shadow-sm {
            text-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
          }
        `}</style>
      </div>
    );
  }

  // -------------------------------------------------------------
  // COMPACT MODE: Sizable 300px Multimedia Card for Active Order
  // -------------------------------------------------------------
  return (
    <div
      className={`pos-promo-card-showcase ${className}`}
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
    >
      {/* Top 16:9 Media Window (Height: 170px) */}
      <div className="pos-promo-media-window">
        {isVideo ? (
          <video
            ref={videoRef}
            key={currentSlide.mediaUrl}
            src={normalizeMediaUrl(currentSlide.mediaUrl)}
            autoPlay
            loop
            muted={isMuted}
            playsInline
            preload="auto"
            onError={() => setVideoError(true)}
            className="pos-promo-video-stream"
          />
        ) : currentSlide.type === "image" && currentSlide.mediaUrl ? (
          <div
            className="pos-promo-banner-stream"
            style={{
              backgroundImage: `url(${normalizeMediaUrl(currentSlide.mediaUrl)})`,
              backgroundSize: "cover",
              backgroundPosition: "center",
            }}
          />
        ) : (
          <div
            className="pos-promo-banner-stream"
            style={{ background: currentSlide.gradient }}
          >
            <div className="pos-promo-stream-icon">
              <i className={currentSlide.icon}></i>
            </div>
          </div>
        )}

        {/* Media Window Overlay Bar */}
        <div className="pos-promo-stream-overlay">
          <div className="d-flex align-items-center justify-content-between p-2.5">
            <span
              className="badge rounded-pill px-2.5 py-1 fs-11 fw-bold text-uppercase d-flex align-items-center gap-1 shadow-sm"
              style={{
                backgroundColor: currentSlide.badgeBg || "rgba(255, 255, 255, 0.95)",
                color: currentSlide.badgeColor || "#0f172a",
              }}
            >
              <i className={currentSlide.icon}></i>
              {currentSlide.badge.split("•")[0].trim()}
            </span>

            <div className="d-flex align-items-center gap-1.5">
              {isVideo && (
                <button
                  type="button"
                  className="btn btn-sm btn-dark bg-opacity-60 text-white border-0 rounded-circle d-flex align-items-center justify-content-center p-0 pos-mini-btn"
                  onClick={toggleMute}
                  title={isMuted ? t("unmuteAudio") : t("muteAudio")}
                >
                  <i className={isMuted ? "ri-volume-mute-line fs-14" : "ri-volume-up-line fs-14"}></i>
                </button>
              )}
              {currentSlide.discount && (
                <span className="badge bg-warning text-dark px-2.5 py-1 rounded-pill fs-11 fw-bolder font-monospace shadow-sm">
                  ⚡ {currentSlide.discount}
                </span>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Bottom Content Body (Height: ~110px) */}
      <div className="p-3 bg-white d-flex flex-column justify-content-between flex-1">
        <div>
          <div className="d-flex align-items-center justify-content-between mb-1">
            <h6 className="fw-bold text-dark mb-0 fs-15 text-truncate" title={currentSlide.title}>
              {currentSlide.title}
            </h6>
            <div className="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
              <button
                type="button"
                className="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center p-0 pos-mini-arrow"
                onClick={handlePrev}
                title={t("previousPromotion")}
              >
                <i className="ri-arrow-left-s-line fs-14 text-dark"></i>
              </button>
              <button
                type="button"
                className="btn btn-sm btn-light border rounded-circle d-flex align-items-center justify-content-center p-0 pos-mini-arrow"
                onClick={handleNext}
                title={t("nextPromotion")}
              >
                <i className="ri-arrow-right-s-line fs-14 text-dark"></i>
              </button>
            </div>
          </div>

          <p className="text-muted fs-12 mb-0 text-truncate-2" style={{ lineHeight: "1.4" }}>
            {currentSlide.subtitle}
          </p>
        </div>

        {/* Footer with Tag & Dots */}
        <div className="d-flex align-items-center justify-content-between pt-2 border-top mt-2">
          <span className="fs-11 text-muted text-truncate fw-medium" style={{ maxWidth: "230px" }}>
            <i className="ri-shield-check-line text-success me-1"></i>
            {currentSlide.tag || t("storePromotion")}
          </span>

          <div className="d-flex align-items-center gap-1">
            {slides.map((s, idx) => (
              <button
                key={s.id || idx}
                type="button"
                className={`pos-compact-dot ${idx === currentIndex ? "active" : ""}`}
                onClick={() => setCurrentIndex(idx)}
                title={`Go to slide ${idx + 1}`}
              />
            ))}
          </div>
        </div>
      </div>

      <style jsx>{`
        .pos-promo-card-showcase {
          width: 100%;
          min-height: 290px;
          border-radius: 16px;
          overflow: hidden;
          background-color: #ffffff;
          border: 1px solid #e2e8f0;
          box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
          display: flex;
          flex-direction: column;
          flex-shrink: 0;
          margin-bottom: 16px;
        }

        .pos-promo-media-window {
          position: relative;
          width: 100%;
          height: 170px;
          background-color: #0f172a;
          overflow: hidden;
          flex-shrink: 0;
        }

        .pos-promo-video-stream {
          width: 100%;
          height: 100%;
          object-fit: cover;
          display: block;
        }

        .pos-promo-banner-stream {
          width: 100%;
          height: 100%;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .pos-promo-stream-icon {
          font-size: 54px;
          color: rgba(255, 255, 255, 0.25);
        }

        .pos-promo-stream-overlay {
          position: absolute;
          inset: 0;
          background: linear-gradient(180deg, rgba(0, 0, 0, 0.45) 0%, transparent 50%, rgba(0, 0, 0, 0.35) 100%);
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          pointer-events: none;
        }

        .pos-promo-stream-overlay > * {
          pointer-events: auto;
        }

        .pos-mini-btn {
          width: 26px;
          height: 26px;
        }

        .pos-mini-arrow {
          width: 24px;
          height: 24px;
        }

        .pos-compact-dot {
          width: 6px;
          height: 6px;
          border-radius: 9999px;
          background-color: #cbd5e1;
          border: none;
          padding: 0;
          cursor: pointer;
          transition: all 0.25s ease;
        }

        .pos-compact-dot.active {
          width: 18px;
          background-color: #4f46e5;
        }

        .text-truncate-2 {
          display: -webkit-box;
          -webkit-line-clamp: 2;
          -webkit-box-orient: vertical;
          overflow: hidden;
        }
      `}</style>
    </div>
  );
};
export default PosDisplayPromotions;
