from fastapi import APIRouter
from pydantic import BaseModel
from typing import Any
from utils.translate import translate_recursive_deep_translator
from routes.v1.vision import router as vision_router

router = APIRouter()
router.include_router(vision_router)

class NestedJSON(BaseModel):
    data: Any
    lng: str = 'en'

@router.post("/translate")
def translate_json(json_input: NestedJSON):
    target = json_input.lng.lower() if json_input.lng else 'en'
    if target in ('kh', 'km'):
        target = 'km'
    translated = translate_recursive_deep_translator(json_input.data, target_lang=target)
    return translated
