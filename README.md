# Coqui Calculator Toolkit

Math expression evaluator toolkit for [Coqui](https://github.com/AgentCoqui/coqui). Provides a token-efficient `calculate` tool that evaluates arithmetic, scientific, and algebraic expressions — powered by [nxp/math-executor](https://github.com/NeonXP/MathExecutor) with 60+ built-in functions.

## Requirements

- PHP 8.4+

## Installation

```bash
composer require coquibot/coqui-toolkit-calculator
```

When installed alongside Coqui, the toolkit is **auto-discovered** via Composer's `extra.php-agents.toolkits` — no manual registration needed.

## Tools Provided

### `calculate`

Evaluate one or more math expressions in a single call.

| Parameter     | Type   | Required | Description                                                                 |
|---------------|--------|----------|-----------------------------------------------------------------------------|
| `expressions` | string | Yes      | Semicolon-delimited math expressions (e.g. `2+3; sin(pi/4); x=5; x^2`)    |
| `precision`   | string | No       | Decimal precision. Default: 10. Use `bcmath:N` for arbitrary precision.     |

**Features:**
- Batch evaluation — semicolon-delimited expressions evaluated in sequence
- Variable support — assign variables that carry forward within a call (`x=5; 2*x+3`)
- 60+ built-in functions — trig, logarithmic, rounding, statistics, base conversion
- Per-expression error handling — a bad expression doesn't kill the batch
- BCMath mode — arbitrary precision via `precision: "bcmath:20"`
- No credentials required — pure PHP computation

### Supported Functions

| Category       | Functions                                                            |
|----------------|----------------------------------------------------------------------|
| Arithmetic     | `+`, `-`, `*`, `/`, `%`, `^`                                        |
| Trigonometric  | sin, cos, tan, asin, acos, atan, atan2                               |
| Hyperbolic     | sinh, cosh, tanh                                                     |
| Logarithmic    | log (natural), log10, log1p, exp, expm1                              |
| Rounding       | ceil, floor, round, abs                                              |
| Roots/Power    | sqrt, pow, hypot                                                     |
| Statistics     | min, max, avg, median                                                |
| Conversion     | deg2rad, rad2deg, bindec, decbin, hexdec, dechex, octdec, decoct     |
| Other          | fmod, intdiv, pi, if(cond, true, false)                              |
| Constants      | `pi` (3.14159...), `e` (2.71828...)                                  |

### Output Format

**Single expression** — returns the raw result value:
```
5
```

**Batch expressions** — returns a JSON array:
```json
[{"expr":"2+3","result":5},{"expr":"sin(pi/4)","result":0.7071067812},{"expr":"x=10","result":10},{"expr":"x^2","result":100}]
```

**Errors** — per-expression error fields without stopping the batch:
```json
[{"expr":"2+3","result":5},{"expr":"1/0","error":"Division by zero"},{"expr":"4*5","result":20}]
```

## Standalone Usage

```php
<?php

declare(strict_types=1);

use CoquiBot\Toolkits\Calculator\CalculatorToolkit;

require __DIR__ . '/vendor/autoload.php';

$toolkit = CalculatorToolkit::fromEnv();
$tool = $toolkit->tools()[0];

// Single expression
$result = $tool->execute(['expressions' => '2 + 3']);
echo $result->content; // 5

// Batch with variables
$result = $tool->execute(['expressions' => 'x=10; y=20; sqrt(x^2 + y^2)']);
echo $result->content;
// [{"expr":"x=10","result":10},{"expr":"y=20","result":20},{"expr":"sqrt(x^2 + y^2)","result":22.360679775}]

// BCMath precision
$result = $tool->execute([
    'expressions' => '1/3',
    'precision' => 'bcmath:30',
]);
echo $result->content; // 0.333333333333333333333333333333
```

## Development

```bash
git clone https://github.com/AgentCoqui/coqui-toolkit-calculator.git
cd coqui-toolkit-calculator
composer install
```

### Run tests

```bash
./vendor/bin/pest
```

### Static analysis

```bash
./vendor/bin/phpstan analyse
```

## License

MIT
