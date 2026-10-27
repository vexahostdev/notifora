<?php

use App\Enums\MessageStatus;
use App\Services\Providers\ProviderResult;
use App\Support\Phone;

it('normalizes Indonesian phone numbers to E.164', function (string $in, ?string $out) {
    expect(Phone::normalize($in))->toBe($out);
})->with([
    ['0812-3456-7890', '+6281234567890'],
    ['+62 812 3456 7890', '+6281234567890'],
    ['6281234567890', '+6281234567890'],
    ['81234567890', '+6281234567890'],
    ['abc', null],
    ['', null],
]);

it('masks destinations', function () {
    expect(Phone::mask('+6281234567890'))->toBe('+62812****890');
    expect(Phone::mask('budi@example.com'))->toBe('b***@example.com');
});

it('only allows forward status transitions', function () {
    expect(MessageStatus::QUEUED->canTransitionTo(MessageStatus::SENT))->toBeTrue();
    expect(MessageStatus::DELIVERED->canTransitionTo(MessageStatus::SENT))->toBeFalse();
    expect(MessageStatus::READ->canTransitionTo(MessageStatus::DELIVERED))->toBeFalse();
    expect(MessageStatus::SENT->canTransitionTo(MessageStatus::FAILED))->toBeTrue();
    expect(MessageStatus::DELIVERED->canTransitionTo(MessageStatus::FAILED))->toBeFalse();
    expect(MessageStatus::FAILED->canTransitionTo(MessageStatus::SENT))->toBeFalse();
});

it('redacts secrets from provider traces', function () {
    $r = new ProviderResult(false, rawResponse: ['api_key' => 'x', 'nested' => ['Authorization' => 'Bearer y', 'ok' => 1]]);
    expect($r->redactedTrace()['response'])->toBe(['api_key' => '[redacted]', 'nested' => ['Authorization' => '[redacted]', 'ok' => 1]]);
});
