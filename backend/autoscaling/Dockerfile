FROM docker:29-cli
RUN apk add --no-cache python3
WORKDIR /app
COPY autoscale.py /app/autoscale.py
ENV PYTHONUNBUFFERED=1 PYTHONDONTWRITEBYTECODE=1
CMD ["python3", "/app/autoscale.py"]
