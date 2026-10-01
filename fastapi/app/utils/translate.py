from typing import Any
from googletrans import Translator
from deep_translator import MyMemoryTranslator, GoogleTranslator

_googletrans_client = None

def get_googletrans():
    global _googletrans_client
    if _googletrans_client is None:
        _googletrans_client = Translator()
    return _googletrans_client

def translate_single_line(text: str, target_lang: str = 'km') -> str:
    cleaned = text.strip()
    if not cleaned:
        return text

    target = 'km' if target_lang.lower() in ('kh', 'km') else target_lang.lower()

    # 1. Try googletrans
    try:
        gt = get_googletrans()
        result = gt.translate(cleaned, dest=target)
        if result and hasattr(result, 'text') and result.text:
            return result.text
    except Exception as e:
        print(f"googletrans error for '{cleaned}': {e}")

    # 2. Try MyMemoryTranslator
    try:
        mm_target = 'km-KH' if target in ('km', 'kh') else target
        mm = MyMemoryTranslator(source='en-GB', target=mm_target)
        result = mm.translate(cleaned)
        if result and result.strip() != cleaned:
            return result
    except Exception as e:
        print(f"MyMemory error for '{cleaned}': {e}")

    # 3. Fallback to deep_translator GoogleTranslator
    try:
        d_gt = GoogleTranslator(source='auto', target=target)
        result = d_gt.translate(cleaned)
        if result and result.strip():
            return result
    except Exception as e:
        print(f"GoogleTranslator error for '{cleaned}': {e}")

    return text

def translate_recursive_deep_translator(data: Any, target_lang: str = 'km') -> Any:
    target = 'km' if target_lang.lower() in ('kh', 'km') else target_lang.lower()

    if isinstance(data, dict):
        return {key: translate_recursive_deep_translator(value, target) for key, value in data.items()}
    elif isinstance(data, list):
        return [translate_recursive_deep_translator(item, target) for item in data]
    elif isinstance(data, str):
        lines = data.splitlines()
        translated_lines = []
        for line in lines:
            if line.strip():
                translated_lines.append(translate_single_line(line, target))
            else:
                translated_lines.append('')
        return '\n'.join(translated_lines)
    else:
        return data
