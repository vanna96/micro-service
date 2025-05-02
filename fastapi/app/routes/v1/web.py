from fastapi import APIRouter

router = APIRouter()

@router.post("/login")
def web_login():
    return {"message": "Web login success!"}
