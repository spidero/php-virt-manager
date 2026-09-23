<?php

use PHPUnit\Framework\TestCase;

// doc/openapi.yaml must describe exactly the routes of lib/api.php
final class ApiDocTest extends TestCase
{
    // "METHOD /path" pairs from the spec (paths at 2 spaces, methods at 4)
    private function specOperations(): array
    {
        $operations = [];
        $path = null;
        foreach (file(dirname(__DIR__).'/doc/openapi.yaml', FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('#^  (/\S*):$#', $line, $m)) {
                $path = $m[1];
            }
            elseif (preg_match('#^\S#', $line)) {
                $path = null;
            }
            elseif ($path && preg_match('#^    (get|post|put|patch|delete):$#', $line, $m)) {
                $operations[] = strtoupper($m[1]).' '.$path;
            }
        }
        sort($operations);
        return $operations;
    }

    // "METHOD /path" pairs from API_ROUTES, regex groups replaced by {parameter}
    private function codeOperations(): array
    {
        $operations = [];
        foreach (API_ROUTES as $route) {
            $sample = ['([^/]+)' => '{x}', '([a-z]+)' => '{x}', '([0-9]+)' => '{x}'];
            $path = strtr(trim($route[1], '#^$'), $sample);
            $operations[] = $route[0].' '.substr($path, strlen('/v1'));
        }
        sort($operations);
        return $operations;
    }

    public function testSpecMatchesRoutes(): void
    {
        $normalize = fn(array $ops) => array_map(fn($op) => preg_replace('/\{[^}]+\}/', '{x}', $op), $ops);
        $this->assertSame($normalize($this->codeOperations()), $normalize($this->specOperations()));
    }

    public function testEveryOperationHasId(): void
    {
        $yaml = (string)file_get_contents(dirname(__DIR__).'/doc/openapi.yaml');
        $this->assertSame(count($this->specOperations()), substr_count($yaml, 'operationId:'));
    }

    public function testGuideMentionsEveryRoute(): void
    {
        $guide = (string)file_get_contents(dirname(__DIR__).'/doc/api.md');
        foreach ($this->specOperations() as $operation) {
            [$method, $path] = explode(' ', $operation);
            $this->assertStringContainsString($method.' '.$path, $guide, 'doc/api.md lacks '.$operation);
        }
    }
}
