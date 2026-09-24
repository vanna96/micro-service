#!/usr/bin/env python3
"""Create local Docker environment files for a fresh checkout.

Existing environment files are never overwritten. Secrets are never printed.
"""

from pathlib import Path
import base64
import json
import secrets
import subprocess
import sys
from urllib.parse import quote

ROOT = Path(__file__).resolve().parent.parent


def read_env(path: Path) -> dict[str, str]:
    values = {}
    for line in path.read_text().splitlines():
        line = line.strip()
        if line and not line.startswith('#') and '=' in line:
            key, value = line.split('=', 1)
            values[key] = value.strip().strip('"').strip("'")
    return values


def write_env(path: Path, values: dict[str, str]) -> None:
    path.write_text(''.join(f'{key}={value}\n' for key, value in values.items()))
    path.chmod(0o600)
    print(f'Created {path.relative_to(ROOT)}')


def existing_mysql_environment() -> dict[str, str] | None:
    result = subprocess.run(
        ['docker', 'container', 'inspect', 'mysql_db', '--format', '{{json .Config.Env}}'],
        capture_output=True, text=True, check=False,
    )
    if result.returncode != 0:
        return None
    return dict(item.split('=', 1) for item in json.loads(result.stdout)
                if '=' in item)


def main() -> None:
    database_path = ROOT / 'database/.env'
    if not database_path.exists():
        previous = existing_mysql_environment()
        if previous and all(previous.get(key) for key in (
            'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD'
        )):
            database = {key: previous[key] for key in (
                'MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD'
            )}
        else:
            volume = subprocess.run(
                ['docker', 'volume', 'inspect', 'database_mysql_data'],
                capture_output=True, check=False,
            )
            if volume.returncode == 0:
                raise RuntimeError(
                    'database/.env is missing, but an existing MySQL volume was found. '
                    'Restore the original database/.env so its credentials still match the data.'
                )
            database = {
                'MYSQL_DATABASE': 'mcservice',
                'MYSQL_USER': 'app',
                'MYSQL_PASSWORD': secrets.token_urlsafe(32),
                'MYSQL_ROOT_PASSWORD': secrets.token_urlsafe(32),
            }
        database.update({
            'DB_CONNECTION': 'mysql',
            'DB_EXPOSE_PORT': '127.0.0.1:3306',
            'RUNNING_PORT': '127.0.0.1:8081',
        })
        write_env(database_path, database)
    database = read_env(database_path)
    required = ('MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD')
    missing = [key for key in required if not database.get(key)]
    if missing:
        raise RuntimeError(f'database/.env is missing required keys: {", ".join(missing)}')

    backend_path = ROOT / 'backend/.env'
    if not backend_path.exists():
        backend = read_env(ROOT / 'backend/.env.example')
        backend.update({
            'APP_ENV': 'production',
            'APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
            'APP_DEBUG': 'false',
            'DEBUGBAR_ENABLED': 'false',
            'TELESCOPE_ENABLED': 'false',
            'APP_URL': 'http://localhost:8080',
            'CENTRAL_PORTAL_HOST': 'localhost',
            'DB_HOST': 'mysql',
            'DB_PORT': '3306',
            'DB_DATABASE': database['MYSQL_DATABASE'],
            'DB_USERNAME': database['MYSQL_USER'],
            'DB_PASSWORD': database['MYSQL_PASSWORD'],
            'NEXT_MODE': 'prod',
            'RUNNING_PORT': '127.0.0.1:8080',
        })
        write_env(backend_path, backend)

    storage_path = ROOT / 'file-storage/.env'
    if not storage_path.exists():
        write_env(storage_path, {
            'MINIO_ROOT_USER': 'microservice',
            'MINIO_ROOT_PASSWORD': secrets.token_urlsafe(32),
            'MINIO_BUCKET': 'uploads',
            'S3_API_PORT': '127.0.0.1:9000',
            'WEB_CONSOLE_PORT': '127.0.0.1:9001',
        })

    fastapi_path = ROOT / 'fastapi/.env'
    if not fastapi_path.exists():
        database_url = (
            'mysql+mysqlconnector://'
            + quote(database['MYSQL_USER'], safe='') + ':'
            + quote(database['MYSQL_PASSWORD'], safe='') + '@mysql:3306/'
            + quote(database['MYSQL_DATABASE'], safe='')
        )
        write_env(fastapi_path, {
            'RUNNING_PORT': '127.0.0.1:8000',
            'DATABASE_URL': database_url,
        })

    ui5_path = ROOT / 'frontend-ui5/.env'
    if not ui5_path.exists():
        write_env(ui5_path, {'RUNNING_PORT': '127.0.0.1:9090'})


if __name__ == '__main__':
    try:
        main()
    except (OSError, RuntimeError, ValueError) as error:
        print(f'Environment setup failed: {error}', file=sys.stderr)
        raise SystemExit(1)
