<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TranslationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationControllerTest extends TestCase
{
    private string $directory;
    private TranslationController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/translations-test-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        file_put_contents($this->directory . '/kh.json', '{"Zebra":"Original"}');

        $this->controller = new class ($this->directory) extends TranslationController {
            public function __construct(string $directory)
            {
                $this->khJsonPath = $directory . '/kh.json';
                $this->kmJsonPath = $directory . '/km.json';
            }
        };
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') as $path) {
            unlink($path);
        }
        rmdir($this->directory);

        parent::tearDown();
    }

    public function test_successful_save_updates_both_locales_and_clears_cache(): void
    {
        $this->expectsTranslationCacheFlush();

        $response = $this->controller->update($this->translationRequest());

        $kh = file_get_contents($this->directory . '/kh.json');
        $this->assertSame($kh, file_get_contents($this->directory . '/km.json'));
        $this->assertSame(['Apple' => 'ផ្លែប៉ោម', 'Zebra' => 'Original'], json_decode($kh, true));
        $this->assertStringContainsString('ផ្លែប៉ោម', $kh);
        $this->assertSame(route('admin.translations.index'), $response->getTargetUrl());
        $this->assertSame(__('Translation saved successfully.'), session('status'));
    }

    public function test_delete_form_removes_only_the_requested_key_from_both_locales(): void
    {
        $key = 'messages.done / "សួស្តី" & <example>';
        file_put_contents($this->directory . '/kh.json', json_encode([
            $key => 'លុប',
            'Zebra' => 'Original',
        ]));
        $this->app->instance(TranslationController::class, $this->controller);
        $this->expectsTranslationCacheFlush();

        $this->withoutMiddleware()->post(route('admin.translations.destroy'), [
            '_method' => 'DELETE',
            'key' => $key,
        ])->assertRedirect(route('admin.translations.index'));

        $kh = file_get_contents($this->directory . '/kh.json');
        $this->assertSame($kh, file_get_contents($this->directory . '/km.json'));
        $this->assertSame(['Zebra' => 'Original'], json_decode($kh, true));
        $this->assertSame(__('Translation deleted successfully.'), session('status'));
        $route = app('router')->getRoutes()->getByName('admin.translations.destroy');
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin.central', $route->gatherMiddleware());
    }

    public function test_deleting_the_last_entry_leaves_empty_json_objects(): void
    {
        $this->expectsTranslationCacheFlush();

        $this->controller->destroy(Request::create('/admin/translations', 'DELETE', ['key' => 'Zebra']));

        $this->assertSame('{}', file_get_contents($this->directory . '/kh.json'));
        $this->assertSame('{}', file_get_contents($this->directory . '/km.json'));
    }

    #[DataProvider('invalidDeleteKeys')]
    public function test_invalid_delete_preserves_dictionary_and_cache(mixed $key): void
    {
        $original = file_get_contents($this->directory . '/kh.json');
        file_put_contents($this->directory . '/km.json', $original);
        Cache::shouldReceive('store')->never();

        try {
            $this->controller->destroy(Request::create('/admin/translations', 'DELETE', ['key' => $key]));
            $this->fail('An invalid delete must not return a success response.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('key', $exception->errors());
            $this->assertSame($original, file_get_contents($this->directory . '/kh.json'));
            $this->assertSame($original, file_get_contents($this->directory . '/km.json'));
            $this->assertNull(session('status'));
        }
    }

    public static function invalidDeleteKeys(): array
    {
        return [[null], [''], [['Zebra']], [str_repeat('x', 1001)], ['Missing']];
    }

    #[DataProvider('dictionaryPaths')]
    public function test_permission_failure_preserves_both_locales_and_does_not_clear_cache(string $dictionary, string $action): void
    {
        $original = file_get_contents($this->directory . '/kh.json');
        file_put_contents($this->directory . '/km.json', $original);
        $path = $this->directory . '/' . $dictionary;
        chmod($path, 0444);
        clearstatcache(true, $path);

        if (is_writable($path)) {
            $this->markTestSkipped('Run as an unprivileged user to test filesystem permissions.');
        }

        Cache::shouldReceive('store')->never();
        Log::shouldReceive('error')->once()->with('Failed to save translations: Translation file is not writable: ' . $path);

        try {
            $request = $action === 'destroy'
                ? Request::create('/admin/translations', 'DELETE', ['key' => 'Zebra'])
                : $this->translationRequest();
            $this->controller->{$action}($request);
            $this->fail('A failed save must not return a success response.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('value', $exception->errors());
            $this->assertSame($original, file_get_contents($this->directory . '/kh.json'));
            $this->assertSame($original, file_get_contents($this->directory . '/km.json'));
            $this->assertNull(session('status'));
        }
    }

    public static function dictionaryPaths(): array
    {
        return [
            ['kh.json', 'update'],
            ['km.json', 'update'],
            ['kh.json', 'destroy'],
            ['km.json', 'destroy'],
        ];
    }

    private function translationRequest(): Request
    {
        return Request::create('/admin/translations/update', 'POST', [
            'key' => 'Apple',
            'value' => 'ផ្លែប៉ោម',
        ]);
    }

    private function expectsTranslationCacheFlush(): void
    {
        $store = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $store->shouldReceive('flush')->once()->andReturnTrue();
        Cache::shouldReceive('store')->once()->with('translations')->andReturn($store);
    }
}
