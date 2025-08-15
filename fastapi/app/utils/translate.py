# from googletrans import Translator

# translator = Translator()

# def translate_recursive_googletrans(data, target_lang='km'):
#     if isinstance(data, dict):
#         return {key: translate_recursive_googletrans(value, target_lang) for key, value in data.items()}
#     elif isinstance(data, list):
#         return [translate_recursive_googletrans(item, target_lang) for item in data]
#     elif isinstance(data, str):
#         cleaned_string = data.replace('\n', ' ').replace('\r', '')
#         print(cleaned_string)
#         try:
#             return translator.translate(cleaned_string, dest=target_lang).text
#         except Exception as e:
#             print(f"Error translating string '{cleaned_string}': {e}")
#             return data
#     else:
#         return data

from deep_translator import GoogleTranslator

def translate_recursive_deep_translator(data, target_lang='km'):
    translator = GoogleTranslator(source='auto', target=target_lang)

    def _translate(item):
        if isinstance(item, dict):
            return {key: _translate(value) for key, value in item.items()}
        elif isinstance(item, list):
            return [_translate(value) for value in item]
        elif isinstance(item, str):
            # Split by lines, translate each, then join back
            lines = item.splitlines()
            translated_lines = []
            for line in lines:
                line = line.strip()
                if line:
                    try:
                        translated_lines.append(translator.translate(line))
                    except Exception as e:
                        print(f"Error translating line: '{line}' -> {e}")
                        translated_lines.append(line)
                else:
                    translated_lines.append('')
            return '\n'.join(translated_lines)
        else:
            return item

    return _translate(data)
