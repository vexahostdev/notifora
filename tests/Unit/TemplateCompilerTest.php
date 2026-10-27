<?php

use App\Services\TemplateCompiler;

beforeEach(fn () => $this->c = new TemplateCompiler);

it('renders variables and filters', function () {
    $out = $this->c->render(
        'Hi {{ subscriber.first_name|upper }}, total {{ payload.amount|currency:"IDR" }} {{ payload.note|default:"-" }}',
        ['subscriber' => ['first_name' => 'budi'], 'payload' => ['amount' => 450000]],
    );
    expect($out)->toBe('Hi BUDI, total Rp 450.000 -');
});

it('renders missing variables as empty and records a warning', function () {
    $warnings = [];
    $out = $this->c->render('A{{ payload.nope }}B', ['payload' => []], false, $warnings);
    expect($out)->toBe('AB')->and($warnings)->toContain('missing: payload.nope');
});

it('escapes html when requested', function () {
    $out = $this->c->render('{{ payload.name }}', ['payload' => ['name' => '<script>alert(1)</script>']], true);
    expect($out)->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('never executes code and rejects unknown roots and filters', function () {
    expect($this->c->validate('{{ system("ls") }}'))->not->toBeEmpty();
    expect($this->c->validate('{{ env.APP_KEY }}'))->not->toBeEmpty();
    expect($this->c->validate('{{ payload.x|exec }}'))->not->toBeEmpty();
    expect($this->c->validate('@php echo 1; @endphp {{ payload.x }}'))->toBe([]);
    // teks "@php" diperlakukan sebagai literal
    expect($this->c->render('@php echo 1; @endphp', []))->toBe('@php echo 1; @endphp');
});

it('detects unbalanced braces', function () {
    expect($this->c->validate('Halo {{ payload.x '))->not->toBeEmpty();
});

it('formats dates in subscriber timezone', function () {
    $out = $this->c->render('{{ payload.at|date:"H:i" }}', ['payload' => ['at' => '2026-01-01T00:00:00Z'], 'subscriber' => ['timezone' => 'Asia/Jakarta']]);
    expect($out)->toBe('07:00');
});
