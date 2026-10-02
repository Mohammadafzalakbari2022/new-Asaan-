<?php

/*
|--------------------------------------------------------------------------
| Storefront Inertia response tests
|--------------------------------------------------------------------------
|
| The browser talks to Inertia. Inertia decides how to read a reply by looking
| at ONE thing: does the reply carry the `X-Inertia` header? If it does not,
| it throws the page away and shows its own error box instead. That is the
| blank screen with pasted output people see on /products.
|
| So the rule this file enforces is simple: if the browser asked for an Inertia
| page, the server answers with an Inertia page. Even when the page fails.
|
| A note on the asset version. `X-Inertia-Version` is the fingerprint of the
| built assets. If the browser's copy is stale the server answers "409, reload
| the whole page", and that reply carries no `X-Inertia` header either -- a
| second way to end up looking at Inertia's error box. These tests therefore
| read the version from the middleware instead of guessing, so they only ever
| fail for the reason they are about.
|
*/

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * The asset version this build advertises.
 *
 * Read from the middleware rather than `Inertia::getVersion()`: the middleware
 * only registers its version resolver *during* a request, so reading it earlier
 * gives an empty string.
 */
function inertiaVersion(): string
{
    return (string) app(HandleInertiaRequests::class)->version(Request::create('/'));
}

/**
 * The headers the browser attaches to every Inertia navigation.
 */
function inertiaHeaders(array $extra = []): array
{
    return array_merge([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'text/html, application/xhtml+xml',
    ], $extra);
}

/**
 * The page object out of an Inertia reply.
 */
function inertiaPage($response): array
{
    return json_decode($response->getContent(), true);
}

/*
|--------------------------------------------------------------------------
| The contract, on a page that works
|--------------------------------------------------------------------------
*/

it('answers an Inertia request to /products with an Inertia response', function () {
    $response = $this->get('/products', inertiaHeaders());

    $response->assertSuccessful();

    // The one header Inertia checks before it will render anything.
    expect($response->headers->get('X-Inertia'))->toBe('true');

    // A page object, not a full HTML document.
    expect($response->headers->get('Content-Type'))->toContain('application/json');

    // "reload the whole page" is only correct for a genuinely stale asset
    // version, which is not what this test is sending.
    expect($response->headers->has('X-Inertia-Location'))->toBeFalse();

    $page = inertiaPage($response);

    expect($page)->toHaveKeys(['component', 'props', 'url', 'version'])
        ->and($page['component'])->toContain('Products/Index')
        ->and($page['url'])->toBe('/products')
        ->and($page['version'])->toBe(inertiaVersion())
        ->and($page['props'])->toHaveKey('products')
        ->and($page['props'])->toHaveKey('filters');
});

it('answers a plain browser visit to /products with a full HTML document', function () {
    $response = $this->get('/products');

    $response->assertSuccessful();

    // No Inertia header, because nobody asked for one.
    expect($response->headers->get('X-Inertia'))->toBeNull();

    $response->assertSee('<div id="app"', false);
});

/*
|--------------------------------------------------------------------------
| The contract, on a page that does not work
|--------------------------------------------------------------------------
|
| This is the /products bug. Anything that goes wrong underneath the
| storefront used to be handed straight back to the browser as a plain HTML
| error page. Inertia cannot read a plain HTML error page, so it discarded the
| visitor's page and showed its own error box over the top -- a blank screen
| with the error pasted into it, on a URL that is otherwise perfectly fine.
|
| The storefront failing and the visitor seeing an error are both correct. The
| visitor seeing a blank screen with raw text is not.
|
*/

/** Break the storefront underneath the page: the products table disappears. */
function breakTheStorefront(): void
{
    Schema::drop('products');
}

it('still answers an Inertia request to /products with an Inertia response when the storefront is broken', function () {
    breakTheStorefront();

    $response = $this->get('/products', inertiaHeaders());

    // The page genuinely fails. That is fine and expected.
    $response->assertStatus(500);

    // What matters is that the browser is told, in the browser's own language,
    // that this reply is an Inertia reply.
    expect($response->headers->get('X-Inertia'))->toBe('true');
    expect($response->headers->get('Content-Type'))->toContain('application/json');

    $page = inertiaPage($response);

    expect($page)->toHaveKey('component')
        ->and($page)->toHaveKey('props');
});

it('still answers an Inertia request for a missing product with an Inertia response', function () {
    $response = $this->get('/product/no-such-product', inertiaHeaders());

    $response->assertStatus(404);

    expect($response->headers->get('X-Inertia'))->toBe('true');
    expect($response->headers->get('Content-Type'))->toContain('application/json');
});

it('leaves a plain browser visit to a missing product as a plain HTML error page', function () {
    $response = $this->get('/product/no-such-product');

    $response->assertStatus(404);

    expect($response->headers->get('X-Inertia'))->toBeNull();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
});

/*
|--------------------------------------------------------------------------
| /products must not fail on nonsense in the address bar
|--------------------------------------------------------------------------
|
| The listing used to hand the raw query string straight to the database. On
| the production database that turns `/products?price_min=abc` into a server
| error rather than an ignored filter, and a server error is what put the error
| box on the screen in the first place.
|
| A value the shop cannot use is not worth a broken page. Drop it, keep the
| page, and tell the visitor which value was actually used.
|
*/

it('ignores a page size that is not a number instead of failing the page', function () {
    $response = $this->get('/products?per_page=abc', inertiaHeaders());

    $response->assertSuccessful();
    expect($response->headers->get('X-Inertia'))->toBe('true');
});

it('ignores a price range that is not a number instead of failing the page', function () {
    $response = $this->get('/products?price_min=abc&price_max=zzz', inertiaHeaders());

    $response->assertSuccessful();
    expect($response->headers->get('X-Inertia'))->toBe('true');
});

it('reports the page size it actually used when the requested one is nonsense', function () {
    $default = (int) config('shop.listing.products_per_page');

    $response = $this->get('/products?per_page=abc', inertiaHeaders());

    $response->assertSuccessful();

    expect(inertiaPage($response)['props']['products']['per_page'])->toBe($default);
});

it('reports a number the shop does not offer as the nearest size it does offer', function () {
    $limits = (array) config('shop.listing.available_limits');

    $response = $this->get('/products?per_page=17', inertiaHeaders());

    $response->assertSuccessful();

    expect(inertiaPage($response)['props']['products']['per_page'])->toBeIn($limits);
});

it('keeps a page size the shop does offer', function () {
    $limits = (array) config('shop.listing.available_limits');
    $wanted = (int) $limits[1];

    $response = $this->get('/products?per_page=' . $wanted, inertiaHeaders());

    $response->assertSuccessful();

    expect(inertiaPage($response)['props']['products']['per_page'])->toBe($wanted);
});

/*
|--------------------------------------------------------------------------
| Maintenance mode
|--------------------------------------------------------------------------
|
| Turning the shop off is a normal thing an administrator does while people are
| browsing. It is also the one thing the storefront middleware used to answer
| outside Inertia, on every single storefront page, with a bare HTML card.
|
*/

it('answers an Inertia request in maintenance mode with an Inertia response', function () {
    \Cartxis\Settings\Models\Setting::set('system.maintenance_enabled', true);

    $response = $this->get('/products', inertiaHeaders());

    expect($response->headers->get('X-Inertia'))->toBe('true');

    $page = inertiaPage($response);

    expect($page)->toHaveKey('component')
        ->and($page['props'])->toHaveKey('maintenance');
});

it('answers a plain browser visit in maintenance mode with a readable page', function () {
    \Cartxis\Settings\Models\Setting::set('system.maintenance_enabled', true);

    $response = $this->get('/products');

    expect($response->headers->get('X-Inertia'))->toBeNull();
    expect($response->getStatusCode())->toBe(503);
});
