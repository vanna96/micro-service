<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        Cache::flush();

        return redirect()
            ->route('admin.translations.index')
            ->with('status', __('Translation saved successfully.'));
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
        Cache::flush();

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

    protected function saveTranslations(array $data): void
    {
        ksort($data);
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            if (!is_dir(dirname($this->khJsonPath))) {
                @mkdir(dirname($this->khJsonPath), 0775, true);
            }
            file_put_contents($this->khJsonPath, $encoded);
            if (file_exists(dirname($this->kmJsonPath))) {
                file_put_contents($this->kmJsonPath, $encoded);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to save translations: ' . $e->getMessage());
        }
    }
}
