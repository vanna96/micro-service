# Micro Service

## Run in WSL Ubuntu

Install Docker Desktop with WSL integration enabled for Ubuntu, plus `git` and `python3` in Ubuntu. Then run:

```bash
git clone https://github.com/vanna96/micro-service.git
cd micro-service
bash run.sh
```

The first run creates local `.env` files, builds and starts the Docker services, installs Laravel Composer packages, runs central database migrations, and checks the Laravel API. Later runs keep the existing `.env` files and Docker data volumes. The first run may take several minutes while images and packages download.

From Windows, run `run.bat`, `run.cmd`, or `run.ps1` from the checkout. These launchers all invoke the same `run.sh` inside WSL Ubuntu. If your WSL distribution has a different name, set `MICRO_SERVICE_WSL_DISTRO` before launching (for example, `Ubuntu-24.04`).

Local URLs:

| Service | URL |
| --- | --- |
| Laravel + Next.js | http://127.0.0.1:8080 |
| phpMyAdmin | http://127.0.0.1:8081 |
| FastAPI docs | http://127.0.0.1:8000/docs |
| UI5 development server | http://127.0.0.1:9090 |
| MinIO console | http://127.0.0.1:9001 |

The ports bind to localhost for a safe first run. UI5 is a development server. To publish the main app, configure its domain, HTTPS reverse proxy, and router forwarding separately. Do not forward MySQL, phpMyAdmin, MinIO, FastAPI, or UI5 ports without deliberate access controls.

### Existing installations

`database/.env` is now local and untracked so database passwords are not distributed in the repository. Before pulling this change into an older checkout, save a copy of its `database/.env`. If the file disappears during the update, restore it before running `run.sh` so its credentials still match the existing MySQL volume. The bootstrap script can recover credentials from an existing `mysql_db` container, but cannot recover them from a volume alone.

Existing installations should rotate the database credentials previously committed to the repository before exposing the service publicly.
