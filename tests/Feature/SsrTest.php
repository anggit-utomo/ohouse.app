<?php

use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Inertia\Ssr\SsrErrorType;
use Inertia\Ssr\SsrRenderFailed;

beforeEach(function () {
    config([
        'inertia.ssr.enabled' => true,
        'inertia.ssr.url' => 'http://127.0.0.1:13714',
        'inertia.ssr.ensure_bundle_exists' => false,
    ]);

    // The withoutVite() fake ignores useHotFile(), so swap in a stub that also
    // reports no hot server. The gateway then always uses inertia.ssr.url and
    // the HTTP fakes below intercept every request, even when public/hot
    // exists because a Vite dev server is running.
    $this->swap(Vite::class, new class extends Vite
    {
        public function __invoke($entrypoints, $buildDirectory = null)
        {
            return new HtmlString('');
        }

        public function __call($method, $parameters)
        {
            return '';
        }

        public function __toString()
        {
            return '';
        }

        public function isRunningHot()
        {
            return false;
        }
    });

    Http::preventStrayRequests();
});

afterEach(function () {
    Http::allowStrayRequests();
});

dataset('public pages', [
    'welcome' => ['/', 'welcome'],
    'login' => ['/login', 'auth/login'],
]);

test('public pages use the renderer output when it is available', function (string $url, string $component) {
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => ['<title>Rendered title</title>'],
            'body' => "<div data-server-rendered=\"true\" id=\"app\"><h1>{$component}</h1></div>",
        ]),
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee("<h1>{$component}</h1>", false)
        ->assertSee('<title>Rendered title</title>', false);
})->with('public pages');

test('public pages fall back to the client shell when the renderer returns an error', function (string $url) {
    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response(status: 500),
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertDontSee('data-server-rendered', false);
})->with('public pages');

test('public pages fall back to the client shell when the renderer is stopped', function (string $url) {
    Event::fake([SsrRenderFailed::class]);

    $contactedUrls = [];

    Http::fake(function (Request $request) use (&$contactedUrls) {
        $contactedUrls[] = $request->url();

        return Http::failedConnection()($request);
    });

    $this->get($url)
        ->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertDontSee('data-server-rendered', false);

    expect($contactedUrls)->toBe(['http://127.0.0.1:13714/render']);

    Event::assertDispatched(
        SsrRenderFailed::class,
        fn (SsrRenderFailed $event) => $event->type === SsrErrorType::Connection,
    );
})->with('public pages');

test('the renderer receives the same page payload as the fallback shell', function () {
    $renderedPage = null;

    Http::fake(function (Request $request) use (&$renderedPage) {
        $renderedPage = json_decode($request->body(), true);

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"></div>',
        ]);
    });

    $this->get(route('home'))->assertOk();

    config(['inertia.ssr.enabled' => false]);

    $fallback = $this->get(route('home'))->assertOk();

    preg_match(
        '/<script data-page="app" type="application\/json">(.*?)<\/script>/s',
        $fallback->getContent(),
        $matches,
    );

    $fallbackPage = json_decode($matches[1], true);

    expect($renderedPage)->toBeArray()
        ->and($renderedPage['component'])->toBe('welcome')
        ->and($renderedPage['props'])->toEqual($fallbackPage['props'])
        ->and($renderedPage['url'])->toBe($fallbackPage['url'])
        ->and($renderedPage['version'])->toBe($fallbackPage['version']);
});
