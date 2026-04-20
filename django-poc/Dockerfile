FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    NPM_BIN_PATH=/usr/bin/npm

WORKDIR /app

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq5 nodejs npm \
 && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt \
 && pip install --no-cache-dir gunicorn

COPY . .

# Build Tailwind CSS (production, minified) then collect statics
RUN python manage.py tailwind install \
 && python manage.py tailwind build \
 && python manage.py collectstatic --noinput || true

EXPOSE 8000
CMD ["gunicorn", "usm_volley.wsgi:application", "--bind", "0.0.0.0:8000"]
