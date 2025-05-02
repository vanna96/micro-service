from fastapi import Request
from starlette.middleware.base import BaseHTTPMiddleware

class PlatformMiddleware(BaseHTTPMiddleware):
    async def dispatch(self, request: Request, call_next):
        if "/api/v1/mobile" in request.url.path:
            print("Mobile middleware triggered")
        elif "/api/v1/web" in request.url.path:
            print("Web middleware triggered")
        return await call_next(request)
