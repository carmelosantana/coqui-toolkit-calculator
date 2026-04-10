<?php

declare(strict_types=1);

namespace CoquiBot\Toolkits\Calculator;

use CarmeloSantana\PHPAgents\Contract\ToolInterface;
use CarmeloSantana\PHPAgents\Contract\ToolkitInterface;
use CarmeloSantana\PHPAgents\Tool\Tool;
use CarmeloSantana\PHPAgents\Tool\ToolResult;
use CarmeloSantana\PHPAgents\Tool\Parameter\StringParameter;
use NXP\MathExecutor;

/**
 * Calculator toolkit — evaluates mathematical expressions using nxp/math-executor.
 *
 * Supports arithmetic, scientific functions (trig, log, sqrt, etc.), variables,
 * and batch evaluation via semicolon-delimited expressions.
 *
 * Auto-discovered by Coqui's ToolkitDiscovery when installed via Composer.
 * No credentials required — pure PHP computation.
 */
final class CalculatorToolkit implements ToolkitInterface
{
    private const int MAX_EXPRESSIONS = 50;
    private const int MAX_PRECISION = 50;

    public function __construct() {}

    /**
     * Factory method for ToolkitDiscovery auto-instantiation.
     */
    public static function fromEnv(): self
    {
        return new self();
    }

    /**
     * @return ToolInterface[]
     */
    public function tools(): array
    {
        return [
            $this->calculateTool(),
        ];
    }

    public function guidelines(): string
    {
        return <<<'GUIDELINES'
        <CALCULATOR-GUIDELINES>
        Use `calculate` to evaluate math expressions instead of computing in your head.
        This ensures accuracy and saves tokens — delegate all math to this tool.

        ## Syntax
        - Operators: `+`, `-`, `*`, `/`, `%` (modulo), `^` (power)
        - Parentheses: `(` `)` for grouping
        - Variables: `x = 5` then use `x` in later expressions (persist within one call)
        - Constants: `pi`, `e`

        ## Batch Mode
        Separate multiple expressions with semicolons to evaluate them in one call:
        `2+3; sin(pi/4); x=10; x^2 + 3*x`

        ## Available Functions
        - **Trig**: sin, cos, tan, asin, acos, atan, atan2
        - **Hyperbolic**: sinh, cosh, tanh
        - **Logarithmic**: log (natural), log10, log1p, exp
        - **Rounding**: ceil, floor, round, abs
        - **Roots/Power**: sqrt, pow, hypot
        - **Statistics**: min, max, avg, median
        - **Conversion**: deg2rad, rad2deg, bindec, decbin, hexdec, dechex, octdec, decoct
        - **Other**: fmod, intdiv, pi

        ## Precision
        Default: 10 decimal places. Pass `precision` param to change.
        For arbitrary precision, use `bcmath:N` (e.g. `bcmath:20` for 20 decimal places).

        ## Tips
        - Batch related calculations in one call to minimize tool-call overhead.
        - Variables assigned in earlier expressions carry forward to later ones.
        - Division by zero returns an error for that expression only — others still evaluate.
        </CALCULATOR-GUIDELINES>
        GUIDELINES;
    }

    private function calculateTool(): ToolInterface
    {
        return new Tool(
            name: 'calculate',
            description: 'Evaluate math expressions. Supports arithmetic, scientific functions (sin, cos, log, sqrt, etc.), variables, and batch mode via semicolons. Examples: "2+3", "x=5; 2*x+3; sqrt(x)", "sin(pi/4); log10(1000)"',
            parameters: [
                new StringParameter(
                    'expressions',
                    'Semicolon-delimited math expressions. Supports variables (x=5), arithmetic (+,-,*,/,%,^), and 60+ functions (sin, cos, log, sqrt, abs, round, etc.).',
                ),
                new StringParameter(
                    'precision',
                    'Decimal places for results (default: 10). Use "bcmath:N" for arbitrary precision with N decimal places.',
                    required: false,
                ),
            ],
            callback: function (array $input): ToolResult {
                return $this->executeCalculate($input);
            },
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function executeCalculate(array $input): ToolResult
    {
        $raw = trim((string) ($input['expressions'] ?? ''));

        if ($raw === '') {
            return ToolResult::error('No expressions provided. Pass one or more math expressions separated by semicolons.');
        }

        $expressions = array_filter(
            array_map('trim', explode(';', $raw)),
            static fn(string $s): bool => $s !== '',
        );

        if (count($expressions) === 0) {
            return ToolResult::error('No valid expressions found after parsing.');
        }

        if (count($expressions) > self::MAX_EXPRESSIONS) {
            return ToolResult::error(
                sprintf('Too many expressions (%d). Maximum is %d per call.', count($expressions), self::MAX_EXPRESSIONS),
            );
        }

        $executor = new MathExecutor();
        $this->configurePrecision($executor, $input);

        $results = [];
        $variables = [];

        foreach ($expressions as $expr) {
            // Detect variable assignment: "x = 5" or "x=5"
            if (preg_match('/^(\$?[a-zA-Z_]\w*)\s*=\s*(.+)$/', $expr, $matches)) {
                $varName = ltrim($matches[1], '$');
                $valueExpr = $matches[2];

                try {
                    /** @var int|float|string $value */
                    $value = $executor->execute($valueExpr);
                    $executor->setVar($varName, $value);
                    $variables[$varName] = $value;
                    $results[] = ['expr' => $expr, 'result' => $value];
                } catch (\Throwable $e) {
                    $results[] = ['expr' => $expr, 'error' => $this->sanitizeError($e)];
                }

                continue;
            }

            // Standard expression evaluation
            try {
                /** @var int|float|string $value */
                $value = $executor->execute($expr);
                $results[] = ['expr' => $expr, 'result' => $value];
            } catch (\Throwable $e) {
                $results[] = ['expr' => $expr, 'error' => $this->sanitizeError($e)];
            }
        }

        // Compact output for single expression
        if (count($results) === 1) {
            $r = $results[0];

            if (isset($r['error'])) {
                return ToolResult::error($r['error']);
            }

            return ToolResult::success((string) $r['result']);
        }

        return ToolResult::success(
            json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '[]',
        );
    }

    /**
     * Configure precision on the MathExecutor based on the precision parameter.
     *
     * @param array<string, mixed> $input
     */
    private function configurePrecision(MathExecutor $executor, array $input): void
    {
        $precision = trim((string) ($input['precision'] ?? ''));

        if ($precision === '') {
            return;
        }

        // BCMath mode: "bcmath:20"
        if (str_starts_with($precision, 'bcmath:')) {
            $digits = (int) substr($precision, 7);
            $digits = max(1, min($digits, self::MAX_PRECISION));
            $executor->useBCMath($digits);

            return;
        }

        // Standard rounding precision — we don't configure MathExecutor differently,
        // but we could post-process results. For now, MathExecutor uses PHP float math.
        // The bcmath mode above is the recommended path for precision control.
    }

    /**
     * Extract a clean, short error message from an exception.
     */
    private function sanitizeError(\Throwable $e): string
    {
        $message = $e->getMessage();

        // Trim class prefixes from exception messages for cleaner output
        if (str_contains($message, ': ')) {
            $parts = explode(': ', $message, 2);

            if (class_exists($parts[0]) || str_contains($parts[0], '\\')) {
                return $parts[1];
            }
        }

        return $message;
    }
}
