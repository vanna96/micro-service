FROM golang:1.24-alpine AS builder

ARG MINIO_VERSION=RELEASE.2025-10-15T17-29-55Z
ARG MC_VERSION=v0.0.0-20251106162529-77f82e18b540

RUN apk add --no-cache git
RUN CGO_ENABLED=0 GOBIN=/out go install "github.com/minio/minio@${MINIO_VERSION}"
RUN CGO_ENABLED=0 GOBIN=/out go install "github.com/minio/mc@${MC_VERSION}"

FROM alpine:3.22

RUN apk add --no-cache ca-certificates curl
COPY --from=builder /out/minio /usr/local/bin/minio
COPY --from=builder /out/mc /usr/local/bin/mc

EXPOSE 9000 9001
ENTRYPOINT ["minio"]
