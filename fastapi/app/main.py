from fastapi import FastAPI
from fastapi.responses import HTMLResponse 
from app.routes.v1 import mobile, web
from app.middleware.middleware import PlatformMiddleware

app = FastAPI()

# Apply general middleware that can inspect route prefixes
app.add_middleware(PlatformMiddleware)

# Route groups with prefixes
app.include_router(mobile.router, prefix="/api/v1/mobile", tags=["Mobile API"])
app.include_router(web.router, prefix="/api/v1/web", tags=["Web API"])

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
            <h1>Welcome to Our API!</h1>
            <p>We're excited to have you here. Start exploring our services below:</p>
            <div>
                <a href="/docs">Explore Our API Docs</a> 
            </div>
        </div>
    </body>
    </html>
    """