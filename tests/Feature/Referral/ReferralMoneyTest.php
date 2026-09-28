<?php

use Cartxis\Core\Models\Currency;
use Cartxis\Referral\Services\ReferralCodeService;
use Cartxis\Referral\Services\ReferralLinkService;
use Cartxis\Referral\Support\Money;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Money formatting
|--------------------------------------------------------------------------
|
| The referral copy puts a real amount into a sentence, so it has to know the
| store's currency. These tests exist because that copy used to read
| config('currency.symbol', 'AFN'), and there is no config/currency.php in this
| project. The fallback therefore always won, and a shop priced in anything
| other than AFN was told its rewards were in AFN.
|
*/

function moneyTestCurrency(string $code, string $symbol, string $position = 'before', int $decimals = 2): Currency
{
    return Currency::create([
        'code' => $code,
        'name' => $code.' test currency',
        'symbol' => $symbol,
        'symbol_position' => $position,
        'decimal_places' => $decimals,
        'exchange_rate' => 1,
        'is_default' => true,
        'is_active' => true,
    ]);
}

it('reads the symbol from the store currency rather than a config fallback', function () {
    moneyTestCurrency('USD', '$');

    expect(Money::symbol())->toBe('$')
        ->and(Money::code())->toBe('USD');
});

it('formats an amount using the store currency and its own decimal places', function () {
    moneyTestCurrency('USD', '$');

    // 2 decimal places is what this currency is configured for, so the store's
    // own money is shown the same way here as everywhere else.
    expect(Money::inSentence(500))->toBe('$500.00')
        ->and(Money::inSentence(12.5))->toBe('$12.50');
});

it('respects a currency that puts the symbol after the amount', function () {
    moneyTestCurrency('EUR', '€', 'after');

    expect(Money::inSentence(500))->toBe('500.00€');
});

it('respects a currency with no decimal places', function () {
    moneyTestCurrency('JPY', '¥', 'before', 0);

    expect(Money::inSentence(500))->toBe('¥500');
});

it('never invents a currency when the store has not set one up', function () {
    // No Currency row at all, which is the state during install.
    expect(Currency::count())->toBe(0)
        ->and(Money::symbol())->toBe('')
        ->and(Money::code())->toBe('')
        ->and(Money::inSentence(500))->toBe('500');
});

/*
|--------------------------------------------------------------------------
| The share message
|--------------------------------------------------------------------------
*/

it('quotes the reward in the store currency, not a hardcoded one', function () {
    configureReferral(['reward_amount' => 10, 'threshold_amount' => 500]);
    moneyTestCurrency('GBP', '£');

    $user = referralUser('Referrer');
    linkReferral($user, referralUser('Friend'));

    $response = $this->actingAs($user)->get('/account/referrals')->assertSuccessful();

    $shareText = $response->viewData('page')['props']['referral']['share_text'];

    expect($shareText)->toContain('£10.00')
        ->and($shareText)->toContain('£500.00')
        ->and($shareText)->not->toContain('AFN');
});

it('does not promise the invited person a reward, because only the referrer earns', function () {
    configureReferral();
    moneyTestCurrency('GBP', '£');

    $user = referralUser('Referrer');
    linkReferral($user, referralUser('Friend'));

    $response = $this->actingAs($user)->get('/account/referrals')->assertSuccessful();

    $shareText = mb_strtolower($response->viewData('page')['props']['referral']['share_text']);

    expect($shareText)->toContain('i earn')
        ->and($shareText)->not->toContain('we will both get')
        ->and($shareText)->not->toContain('both get');
});

/*
|--------------------------------------------------------------------------
| Refusals leave a trace
|--------------------------------------------------------------------------
|
| The refusal log used to sit behind `if (false)`, so the guard worked but left
| an operator with no way to find out why invitations were being dropped. These
| tests attach a real Monolog test handler rather than mocking the Log facade,
| because mocking it also intercepts the framework's own logging.
|
*/

/**
 * Collect everything written to the 'stack' log channel while the callback runs.
 *
 * @return array<int, array{level: string, message: string, context: array}>
 */
function captureRefusalLog(callable $callback): array
{
    $records = [];

    $handler = new \Monolog\Handler\TestHandler();
    Log::channel()->getLogger()->pushHandler($handler);

    try {
        $callback();
    } finally {
        Log::channel()->getLogger()->popHandler();
    }

    foreach ($handler->getRecords() as $record) {
        $records[] = [
            'level' => $record['level_name'],
            'message' => $record['message'],
            'context' => $record['context'],
        ];
    }

    return $records;
}

it('records why a referral was refused', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $code = app(ReferralCodeService::class)->forUser($referrer);

    // Self-referral: the owner of the code is the one using it. This is the
    // refusal people hit most, and the one the dead `if (false)` block was
    // supposed to be reporting.
    $records = captureRefusalLog(function () use ($referrer, $code) {
        app(ReferralLinkService::class)->linkFor($referrer, $code->code);
    });

    $refusals = array_values(array_filter(
        $records,
        fn (array $record) => $record['message'] === 'Referral link refused'
    ));

    expect($refusals)->toHaveCount(1)
        ->and($refusals[0]['context']['user_id'])->toBe($referrer->id)
        ->and($refusals[0]['context']['referrer_id'])->toBe($referrer->id)
        ->and($refusals[0]['context']['code'])->toBe($code->code)
        ->and($refusals[0]['context']['reason'])->toBe(ReferralLinkService::REASON_SELF);
});

it('records the refusal when the invited shopper tries to refer themselves again', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    app(ReferralLinkService::class)->linkToCode(
        $shopper,
        \Cartxis\Referral\Models\ReferralCode::where('user_id', $referrer->id)->firstOrFail()
    );

    // The shopper now has a code of their own, and tries to use it on an
    // account that is already linked. That is a second, different refusal.
    $shopperCode = app(ReferralCodeService::class)->forUser($shopper);

    $records = captureRefusalLog(function () use ($shopper, $shopperCode) {
        app(ReferralLinkService::class)->linkFor($shopper, $shopperCode->code);
    });

    $reasons = array_column(array_filter(
        $records,
        fn (array $record) => $record['message'] === 'Referral link refused'
    ), 'context');

    // The self-referral rule is checked first, so that is the reason reported.
    expect($reasons)->toHaveCount(1)
        ->and($reasons[0]['reason'])->toBe(ReferralLinkService::REASON_SELF);
});

it('stays quiet when the link succeeds, so the log is not filled with noise', function () {
    configureReferral();

    $referrer = referralUser('Referrer');
    $shopper = referralUser('Shopper');
    $code = app(ReferralCodeService::class)->forUser($referrer);

    $referral = null;
    $records = captureRefusalLog(function () use ($shopper, $code, &$referral) {
        $referral = app(ReferralLinkService::class)->linkFor($shopper, $code->code);
    });

    $refusals = array_filter(
        $records,
        fn (array $record) => $record['message'] === 'Referral link refused'
    );

    expect($referral)->not->toBeNull()
        ->and($refusals)->toBeEmpty();
});

it('stays quiet when there is no code to act on', function () {
    configureReferral();

    $user = referralUser('Lonely');

    $records = captureRefusalLog(function () use ($user) {
        expect(app(ReferralLinkService::class)->linkFor($user, null))->toBeNull();
    });

    $refusals = array_filter(
        $records,
        fn (array $record) => $record['message'] === 'Referral link refused'
    );

    expect($refusals)->toBeEmpty();
});
