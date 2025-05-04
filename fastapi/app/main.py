from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError 
from fastapi.responses import HTMLResponse 
from routes.v1 import mobile, web
from middleware.middleware import PlatformMiddleware 
from dotenv import load_dotenv
import os
from fastapi.responses import JSONResponse
from fastapi.staticfiles import StaticFiles

# Load environment variables from .env
# load_dotenv()

# Now you can access environment variables
# database_url = os.getenv("DATABASE_URL")

# config.set_main_option('sqlalchemy.url', database_url)

app = FastAPI() 

# # Mount the directory to serve static files
app.mount("/assets", StaticFiles(directory="static/assets"), name="asset")

async def validation_exception_handler(request: Request, exc: RequestValidationError):
    error_response = {"errors": []}
    for error in exc.errors():
        error_response["errors"].append({
            "key": error["loc"][-1],  # e.g. "name"
            "msg": error["msg"],      # e.g. "Field required"
            # "input": error.get("input", None)  # optional, includes invalid input if needed
        })
    return JSONResponse(status_code=400, content=error_response)

# Apply general middleware that can inspect route prefixes
app.add_exception_handler(RequestValidationError, validation_exception_handler)
app.add_middleware(PlatformMiddleware)

# Route groups with prefixes
app.include_router(mobile.router, prefix="/api/v1/mobile", tags=["Mobile API"])
app.include_router(web.router, prefix="/api/v1/admin")

@app.get("/", tags=["Welcome"], response_class=HTMLResponse)
async def welcome():
    return """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to Our API</title>
        <style>
            body {
                font-family: 'Arial', sans-serif;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #6e7f80, #f4f4f9);
                color: #fff;
                text-align: center;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                overflow: hidden;
            }
            .welcome-container {
                background-color: rgba(0, 0, 0, 0.5);
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
                text-align: center;
                animation: fadeIn 2s ease-out;
            }
            h1 {
                font-size: 3em;
                margin-bottom: 20px;
                letter-spacing: 2px;
            }
            p {
                font-size: 1.2em;
                margin-bottom: 20px;
            }
            a {
                color: #ff6f61;
                text-decoration: none;
                font-weight: bold;
                padding: 8px 20px;
                background-color: #fff;
                color: #333;
                border-radius: 5px;
                transition: background-color 0.3s;
            }
            a:hover {
                background-color: #ff6f61;
                color: #fff;
            }
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(50px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>
    </head>
    <body>
        <div class="welcome-container">
            <image src="https://localhost:8000/asset/images/categories/32d441e1652f442f9475e9ee29b75454.png"/>
            <h1>Welcome to Our API!</h1>
            <p>We're excited to have you here. Start exploring our services below:</p>
            <div>
                <a href="/docs">Explore Our API Docs</a> 
            </div>
        </div>
    </body>
    </html>
    """