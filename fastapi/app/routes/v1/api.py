from fastapi import APIRouter
from pydantic import BaseModel
from typing import Any
from utils.translate import translate_recursive_deep_translator

router = APIRouter()

class NestedJSON(BaseModel):
    data: Any
    lng: str='en'

@router.post("/translate")
def translate_json(json_input: NestedJSON): 
    translated = translate_recursive_deep_translator(json_input.data, target_lang=json_input.lng)
    return translated
