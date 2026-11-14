<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Budget;
use App\Models\BudgetMonth;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Testing\TestResponse;
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

test('renderer failures are logged while falling back to the client shell', function () {
    Log::spy();

    Http::fake(function (Request $request) {
        return Http::failedConnection()($request);
    });

    $this->get('/')
        ->assertOk()
        ->assertSee('<div id="app"></div>', false)
        ->assertDontSee('data-server-rendered', false);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'SSR')
            && $context['component'] === 'welcome'
            && $context['url'] === '/'
            && $context['type'] === 'connection'
            && $context['error'] !== '');
});

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

function ssrBudgetMonth(
    Household $household,
    string $name,
    int $allocated,
    int $spent = 0,
    int $sort = 0,
): BudgetMonth {
    $budget = Budget::factory()->for($household)->create([
        'name' => $name,
        'default_amount' => $allocated,
        'sort' => $sort,
    ]);

    $month = BudgetMonth::factory()->for($budget)->create([
        'period_start' => '2026-10-01',
        'allocated' => $allocated,
    ]);

    if ($spent > 0) {
        Transaction::factory()->create([
            'household_id' => $household->id,
            'budget_month_id' => $month->id,
            'amount' => $spent,
            'occurred_at' => '2026-10-10 12:00:00',
        ]);
    }

    return $month;
}

function ssrFallbackPage(TestResponse $response): array
{
    preg_match(
        '/<script data-page="app" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    expect($matches)->not->toBeEmpty();

    return json_decode($matches[1], true);
}

test('home renders through the renderer with the same props as the fallback shell', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();
    ssrBudgetMonth($household, 'Food', 2_000_000, 1_640_000);
    ssrBudgetMonth($household, 'Transport', 600_000, 710_000, 1);

    $renderedPage = [];

    Http::fake(function (Request $request) use (&$renderedPage) {
        $renderedPage = json_decode($request->body(), true);
        $props = $renderedPage['props'];

        return Http::response([
            'head' => ['<title>Home</title>'],
            'body' => '<div data-server-rendered="true" id="app">'
                .'<h1>'.$props['monthLabel'].'</h1>'
                .'<p>'.$props['budgets'][0]['name'].'</p>'
                .'<p>'.$props['summary']['spent'].'</p>'
                .'</div>',
        ]);
    });

    $rendered = $this->actingAs($owner)->get("/h/{$household->id}")->assertOk();

    $rendered->assertSee('<h1>October</h1>', false)
        ->assertSee('<p>Food</p>', false)
        ->assertSee('<p>2350000</p>', false);

    config(['inertia.ssr.enabled' => false]);

    $fallback = $this->actingAs($owner)->get("/h/{$household->id}")->assertOk();
    $fallbackPage = ssrFallbackPage($fallback);

    expect($renderedPage['component'])->toBe('home')
        ->and($renderedPage['props'])->toEqual($fallbackPage['props'])
        ->and($renderedPage['props']['summary']['spent'])->toBe(2_350_000);
});

test('history renders a collapsed transfer row with the same props as the fallback shell', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();
    $food = ssrBudgetMonth($household, 'Food', 2_000_000);
    $transport = ssrBudgetMonth($household, 'Transport', 600_000, sort: 1);

    $this->actingAs($owner)->post("/h/{$household->id}/transfers", [
        'from_budget_id' => $food->budget_id,
        'to_budget_id' => $transport->budget_id,
        'amount' => 50_000,
    ])->assertRedirect();

    session()->forget(['success', 'error']);

    $renderedPage = [];

    Http::fake(function (Request $request) use (&$renderedPage) {
        $renderedPage = json_decode($request->body(), true);
        $row = $renderedPage['props']['transactions'][0];

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app">'
                .'<p>'.$row['title'].'</p>'
                .'<p>'.$row['budget'].'</p>'
                .'<p>'.$row['amount'].'</p>'
                .'</div>',
        ]);
    });

    $rendered = $this->actingAs($owner)->get("/h/{$household->id}/transactions")->assertOk();

    $rendered->assertSee('<p>Moved Food → Transport</p>', false)
        ->assertSee('<p>Transfer</p>', false)
        ->assertSee('<p>50000</p>', false);

    config(['inertia.ssr.enabled' => false]);

    $fallback = $this->actingAs($owner)->get("/h/{$household->id}/transactions")->assertOk();
    $fallbackPage = ssrFallbackPage($fallback);

    expect($renderedPage['component'])->toBe('transactions/index')
        ->and($renderedPage['props'])->toEqual($fallbackPage['props'])
        ->and($renderedPage['props']['transactions'])->toHaveCount(1)
        ->and($renderedPage['props']['transactions'][0]['title'])->toBe('Moved Food → Transport')
        ->and($renderedPage['props']['transactions'][0]['budget'])->toBe('Transfer')
        ->and($renderedPage['props']['transactions'][0]['signed'])->toBe(0);
});

test('report renders through the renderer with the same props as the fallback shell', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();
    ssrBudgetMonth($household, 'Food', 2_000_000, 1_640_000);
    ssrBudgetMonth($household, 'Transport', 600_000, 710_000, 1);

    $renderedPage = [];

    Http::fake(function (Request $request) use (&$renderedPage) {
        $renderedPage = json_decode($request->body(), true);
        $props = $renderedPage['props'];

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app">'
                .'<p>Total spent</p>'
                .'<p>'.$props['spent'].'</p>'
                .'<p>'.$props['budgets'][0]['name'].'</p>'
                .'</div>',
        ]);
    });

    $rendered = $this->actingAs($owner)->get("/h/{$household->id}/report")->assertOk();

    $rendered->assertSee('<p>Total spent</p>', false)
        ->assertSee('<p>2350000</p>', false)
        ->assertSee('<p>Food</p>', false);

    config(['inertia.ssr.enabled' => false]);

    $fallback = $this->actingAs($owner)->get("/h/{$household->id}/report")->assertOk();
    $fallbackPage = ssrFallbackPage($fallback);

    expect($renderedPage['component'])->toBe('report/index')
        ->and($renderedPage['props'])->toEqual($fallbackPage['props'])
        ->and($renderedPage['props']['spent'])->toBe(2_350_000)
        ->and($renderedPage['props']['budgets'][0]['name'])->toBe('Food');
});

test('the appearance preference renders its theme class in the first HTML', function () {
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();

    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"></div>',
        ]),
    ]);

    $dark = $this->actingAs($owner)
        ->withUnencryptedCookie('appearance', 'dark')
        ->get("/h/{$household->id}")
        ->assertOk()
        ->assertSee('data-server-rendered', false);

    $light = $this->actingAs($owner)
        ->withUnencryptedCookie('appearance', 'light')
        ->get("/h/{$household->id}")
        ->assertOk()
        ->assertSee('data-server-rendered', false);

    expect($dark->getContent())->toMatch('/<html[^>]*class="dark"/')
        ->and($light->getContent())->not->toMatch('/<html[^>]*class="dark"/');
});

test('an outsider gets not found without rendering household content', function () {
    $household = Household::factory()->create();

    Http::fake();

    $this->actingAs(User::factory()->create())
        ->get("/h/{$household->id}")
        ->assertNotFound()
        ->assertDontSee('data-server-rendered', false)
        ->assertDontSee('recentTransactions', false)
        ->assertDontSee('monthLabel', false);

    Http::assertNothingSent();
});

test('viewer household pages render with read-only props', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $household = Household::factory()->create();
    $viewer = User::factory()->create();
    $viewer->forceFill(['current_household_id' => $household->id])->save();
    $household->members()->attach($viewer->id, ['role' => 'viewer']);
    ssrBudgetMonth($household, 'Food', 2_000_000, 1_640_000);

    $pages = [];

    Http::fake(function (Request $request) use (&$pages) {
        $page = json_decode($request->body(), true);
        $pages[] = $page;

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"><p>'
                .($page['props']['canEdit'] ? 'editable' : 'read-only')
                .'</p></div>',
        ]);
    });

    $home = $this->actingAs($viewer)->get("/h/{$household->id}")->assertOk();
    $history = $this->actingAs($viewer)->get("/h/{$household->id}/transactions")->assertOk();

    $home->assertSee('<p>read-only</p>', false);
    $history->assertSee('<p>read-only</p>', false);

    expect($pages)->toHaveCount(2)
        ->and($pages[0]['component'])->toBe('home')
        ->and($pages[0]['props']['canEdit'])->toBeFalse()
        ->and($pages[0]['props']['banner'])->toBeNull()
        ->and($pages[1]['component'])->toBe('transactions/index')
        ->and($pages[1]['props']['canEdit'])->toBeFalse();
});

test('closed period pages pass their locked state to the renderer', function () {
    $this->travelTo('2026-10-15 09:00:00');
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();
    $food = ssrBudgetMonth($household, 'Food', 2_000_000);

    BudgetMonth::factory()->for($food->budget)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'allocated' => 1_500_000,
        'closed_at' => now(),
    ]);

    $pages = [];

    Http::fake(function (Request $request) use (&$pages) {
        $page = json_decode($request->body(), true);
        $pages[] = $page;
        $label = $page['props']['monthLabel']
            ?? (collect($page['props']['months'])
                ->firstWhere('value', $page['props']['month'])['label'] ?? '');

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app">'
                .'<p>'.($page['props']['closed'] ? 'closed' : 'open').'</p>'
                .'<p>'.$label.'</p>'
                .'</div>',
        ]);
    });

    $history = $this->actingAs($owner)
        ->get("/h/{$household->id}/transactions?month=2026-09")
        ->assertOk()
        ->assertSee('<p>closed</p>', false)
        ->assertSee('September', false);

    $budget = $this->actingAs($owner)
        ->get("/h/{$household->id}/budgets/{$food->budget_id}?month=2026-09")
        ->assertOk()
        ->assertSee('<p>closed</p>', false)
        ->assertSee('September', false);

    expect($pages)->toHaveCount(2)
        ->and($pages[0]['component'])->toBe('transactions/index')
        ->and($pages[0]['props']['closed'])->toBeTrue()
        ->and($pages[1]['component'])->toBe('budgets/show')
        ->and($pages[1]['props']['closed'])->toBeTrue()
        ->and($pages[1]['props']['monthLabel'])->toContain('September');
});

test('the log reports when the renderer served the page', function () {
    Log::spy();

    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"></div>',
        ]),
    ]);

    $this->get('/')->assertOk();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context) => $message === 'Inertia page render outcome.'
            && $context === [
                'mode' => 'rendered',
                'component' => 'welcome',
                'url' => '/',
            ],
    );
});

test('the log reports when a failed renderer served the fallback', function () {
    Log::spy();

    Http::fake(fn (Request $request) => Http::failedConnection()($request));

    $this->get('/')->assertOk();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context) => $message === 'Inertia page render outcome.'
            && $context === [
                'mode' => 'fallback',
                'component' => 'welcome',
                'url' => '/',
                'reason' => 'renderer-unavailable',
            ],
    );
});

test('the log reports when rendering is switched off', function () {
    config(['inertia.ssr.enabled' => false]);

    Log::spy();
    Http::fake();

    $this->get('/')->assertOk();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message, array $context) => $message === 'Inertia page render outcome.'
            && $context['mode'] === 'fallback'
            && $context['reason'] === 'disabled',
    );

    Http::assertNothingSent();
});

test('the log only reports full page renders', function () {
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();

    $version = app(HandleInertiaRequests::class)
        ->version(request());

    Log::spy();

    Http::fake([
        'http://127.0.0.1:13714/render' => Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"></div>',
        ]),
    ]);

    $this->actingAs($owner)
        ->get("/h/{$household->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ])
        ->assertOk();

    $this->get('/manifest.webmanifest')->assertOk();

    Log::shouldNotHaveReceived('info');
});

test('a settings page renders through the renderer without per-page wiring', function () {
    $household = Household::factory()->create();
    $owner = $household->owner;
    $owner->forceFill(['current_household_id' => $household->id])->save();

    $renderedPage = [];

    Http::fake(function (Request $request) use (&$renderedPage) {
        $renderedPage = json_decode($request->body(), true);

        return Http::response([
            'head' => [],
            'body' => '<div data-server-rendered="true" id="app"><p>'
                .$renderedPage['component']
                .'</p></div>',
        ]);
    });

    $this->actingAs($owner)
        ->get("/h/{$household->id}/settings")
        ->assertOk()
        ->assertSee('<p>settings/household</p>', false);

    expect($renderedPage['component'])->toBe('settings/household');
});
