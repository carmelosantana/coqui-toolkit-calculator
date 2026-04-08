<?php

declare(strict_types=1);

use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\PHPAgents\Enum\ToolResultStatus;
use CoquiBot\Toolkits\Calculator\CalculatorToolkit;

// ── Toolkit structure ───────────────────────────────────────────────────

test('toolkit implements ToolkitInterface', function () {
    $toolkit = new CalculatorToolkit();

    expect($toolkit)->toBeInstanceOf(ToolkitInterface::class);
});

test('tools returns calculate tool', function () {
    $toolkit = new CalculatorToolkit();
    $tools = $toolkit->tools();

    expect($tools)->toHaveCount(1);
    expect($tools[0]->name())->toBe('calculate');
});

test('guidelines returns non-empty string', function () {
    $toolkit = new CalculatorToolkit();

    expect($toolkit->guidelines())->toBeString()->not->toBeEmpty();
});

test('fromEnv creates instance', function () {
    $toolkit = CalculatorToolkit::fromEnv();

    expect($toolkit)->toBeInstanceOf(CalculatorToolkit::class);
});

// ── Basic arithmetic ────────────────────────────────────────────────────

test('evaluates simple addition', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '2 + 3']);

    expect($result->status)->toBe(ToolResultStatus::Success);
    // Single expression returns just the value
    expect($result->content)->toBe('5');
});

test('evaluates multiplication and division', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '10 * 5']);
    expect($result->content)->toBe('50');

    $result = $tool->execute(['expressions' => '100 / 4']);
    expect($result->content)->toBe('25');
});

test('evaluates exponentiation', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '2 ^ 10']);
    expect($result->content)->toBe('1024');
});

test('evaluates complex expression with parentheses', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '(2 + 3) * (4 - 1)']);
    expect($result->content)->toBe('15');
});

// ── Batch expressions ───────────────────────────────────────────────────

test('evaluates batch expressions separated by semicolons', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '2+3; 10*5; 100/4']);
    $data = json_decode($result->content, true);

    expect($data)->toBeArray()->toHaveCount(3);
    expect($data[0]['result'])->toEqual(5);
    expect($data[1]['result'])->toEqual(50);
    expect($data[2]['result'])->toEqual(25);
});

// ── Variables ───────────────────────────────────────────────────────────

test('supports variable assignment and reuse', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'x = 5; x * 3']);
    $data = json_decode($result->content, true);

    expect($data)->toHaveCount(2);
    expect($data[0]['result'])->toEqual(5);
    expect($data[1]['result'])->toEqual(15);
});

test('variables carry forward across expressions', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'a = 10; b = 20; a + b']);
    $data = json_decode($result->content, true);

    expect($data)->toHaveCount(3);
    expect($data[2]['result'])->toEqual(30);
});

// ── Scientific functions ────────────────────────────────────────────────

test('evaluates trigonometric functions', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'sin(0)']);
    expect((float) $result->content)->toEqualWithDelta(0.0, 0.0001);

    $result = $tool->execute(['expressions' => 'cos(0)']);
    expect((float) $result->content)->toEqualWithDelta(1.0, 0.0001);
});

test('evaluates logarithmic functions', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'log10(1000)']);
    expect((float) $result->content)->toEqualWithDelta(3.0, 0.0001);
});

test('evaluates sqrt', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'sqrt(144)']);
    expect((float) $result->content)->toEqualWithDelta(12.0, 0.0001);
});

test('uses pi and e constants', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'pi']);
    expect((float) $result->content)->toEqualWithDelta(M_PI, 0.0001);

    $result = $tool->execute(['expressions' => 'e']);
    expect((float) $result->content)->toEqualWithDelta(M_E, 0.0001);
});

// ── Error handling ──────────────────────────────────────────────────────

test('returns error for division by zero', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '1/0']);

    // Single expression error returns as ToolResult::error
    expect($result->status)->toBe(ToolResultStatus::Error);
});

test('returns per-expression errors in batch mode', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '2+3; 1/0; 4*5']);
    $data = json_decode($result->content, true);

    expect($data)->toHaveCount(3);
    expect($data[0]['result'])->toEqual(5);
    expect($data[1])->toHaveKey('error');
    expect($data[2]['result'])->toEqual(20);
});

test('returns error for empty input', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '']);
    expect($result->status)->toBe(ToolResultStatus::Error);
});

test('returns error for whitespace-only input', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '   ;  ;  ']);
    expect($result->status)->toBe(ToolResultStatus::Error);
});

test('handles invalid expression gracefully', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'foo bar baz']);
    expect($result->status)->toBe(ToolResultStatus::Error);
});

// ── BCMath precision ────────────────────────────────────────────────────

test('supports bcmath precision mode', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute([
        'expressions' => '1 / 3',
        'precision' => 'bcmath:20',
    ]);

    // BCMath returns string result with fixed decimal places
    expect($result->content)->toBeString();
    expect($result->status)->toBe(ToolResultStatus::Success);
});

// ── Edge cases ──────────────────────────────────────────────────────────

test('handles trailing semicolons', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '2+3;']);
    // Should treat as single expression (trailing semicolons filtered)
    expect($result->content)->toBe('5');
});

test('handles variable with dollar sign prefix', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => '$x = 10; $x * 2']);
    $data = json_decode($result->content, true);

    expect($data)->toHaveCount(2);
    expect($data[0]['result'])->toEqual(10);
    expect($data[1]['result'])->toEqual(20);
});

test('evaluates statistical functions', function () {
    $toolkit = new CalculatorToolkit();
    $tool = $toolkit->tools()[0];

    $result = $tool->execute(['expressions' => 'avg(2, 4, 6)']);
    expect((float) $result->content)->toEqualWithDelta(4.0, 0.0001);

    $result = $tool->execute(['expressions' => 'max(10, 20, 5)']);
    expect((float) $result->content)->toEqualWithDelta(20.0, 0.0001);
});
