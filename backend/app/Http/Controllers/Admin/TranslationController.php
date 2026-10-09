<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TranslationController extends Controller
{
    protected string $khJsonPath;
    protected string $kmJsonPath;

    public function __construct()
    {
        $this->khJsonPath = base_path('lang/kh.json');
        $this->kmJsonPath = base_path('lang/km.json');
    }

    public function index(Request $request): View
    {
        $translations = $this->loadTranslations();
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $filtered = [];
            foreach ($translations as $key => $val) {
                if (stripos($key, $search) !== false || stripos($val, $search) !== false) {
                    $filtered[$key] = $val;
                }
            }
            $translations = $filtered;
        }

        // Test microservice health
        $serviceUrl = env('TRANSLATION_SERVICE_URL', 'http://fastapi-app:8000/api/translate');
        $serviceOnline = false;
        try {
            $checkUrl = str_replace('/api/translate', '/docs', $serviceUrl);
            $res = Http::timeout(1)->get($checkUrl);
            $serviceOnline = $res->successful();
        } catch (\Throwable) {
            $serviceOnline = false;
        }

        return view('admin.translations.index', [
            'activeLocale'    => app()->getLocale(),
            'translations'    => $translations,
            'totalCount'      => count($this->loadTranslations()),
            'filteredCount'   => count($translations),
            'search'          => $search,
            'serviceOnline'   => $serviceOnline,
            'serviceUrl'      => $serviceUrl,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key'   => ['required', 'string', 'max:1000'],
            'value' => ['required', 'string', 'max:2000'],
        ]);

        $translations = $this->loadTranslations();
        $translations[trim($validated['key'])] = trim($validated['value']);

        $this->saveTranslations($translations);
        $this->clearTranslationCache();

        return redirect()
            ->route('admin.translations.index')
            ->with('status', __('Translation saved successfully.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:1000'],
        ]);

        $translations = $this->loadTranslations();
        $key = $validated['key'];

        if (!array_key_exists($key, $translations)) {
            throw ValidationException::withMessages([
                'key' => __('Translation entry not found.'),
            ]);
        }

        unset($translations[$key]);
        $this->saveTranslations($translations);
        $this->clearTranslationCache();

        return redirect()
            ->route('admin.translations.index')
            ->with('status', __('Translation deleted successfully.'));
    }

    public function testTranslate(Request $request): JsonResponse
    {
        $text = trim((string) $request->input('text', ''));
        $target = normalize_locale_code($request->input('lng', 'kh'));

        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => __('Please enter text to translate.'),
            ], 422);
        }

        $translated = translate($text, $target);

        return response()->json([
            'success'    => true,
            'original'   => $text,
            'translated' => $translated,
            'locale'     => $target,
        ]);
    }

    public function clearCache(): RedirectResponse
    {
        $this->clearTranslationCache();

        return redirect()
            ->route('admin.translations.index')
            ->with('status', __('Translation cache cleared.'));
    }

    protected function loadTranslations(): array
    {
        if (file_exists($this->khJsonPath)) {
            $json = json_decode(file_get_contents($this->khJsonPath), true);
            if (is_array($json)) {
                return $json;
            }
        }
        return [];
    }

    protected function clearTranslationCache(): void
    {
        Cache::store('translations')->flush();
    }

    protected function saveTranslations(array $data): void
    {
        ksort($data);
        try {
            $encoded = json_encode((object) $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (!is_dir(dirname($this->khJsonPath))) {
                if (!mkdir(dirname($this->khJsonPath), 0775, true)) {
                    throw new \RuntimeException('Unable to create the translation directory.');
                }
            }

            // Check both locales before writing so a permissions error on km
            // does not leave kh updated while the alias remains stale.
            foreach ([$this->khJsonPath, $this->kmJsonPath] as $path) {
                if (!is_writable(file_exists($path) ? $path : dirname($path))) {
                    throw new \RuntimeException('Translation file is not writable: ' . $path);
                }
            }

            foreach ([$this->khJsonPath, $this->kmJsonPath] as $path) {
                if (file_put_contents($path, $encoded, LOCK_EX) === false) {
                    throw new \RuntimeException('Unable to write translation file: ' . $path);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to save translations: ' . $e->getMessage());

            throw ValidationException::withMessages([
                'value' => __('Unable to save translations. Please try again or contact your administrator.'),
            ]);
        }
    }
}
