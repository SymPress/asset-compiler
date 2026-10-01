<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Support;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Support\BuildEnvironment;
use Symfony\Component\Process\Process;

final class BuildEnvironmentTest extends TestCase
{
    public function testRealChildDoesNotInheritSecretsOrInjectionControls(): void
    {
        $previous = getenv('COMPOSER_AUTH');
        $agent = getenv('SSH_AUTH_SOCK');
        try {
            putenv('COMPOSER_AUTH=canary-secret');
            putenv('SSH_AUTH_SOCK=/canary-agent');
            $process = new Process([PHP_BINARY, '-r', 'echo json_encode([getenv("COMPOSER_AUTH"),getenv("SSH_AUTH_SOCK"),getenv("BUILD_MARKER")]);'], null, BuildEnvironment::process(['BUILD_MARKER' => 'approved']));
            $process->mustRun();
            self::assertSame('[false,false,"approved"]', $process->getOutput());
        } finally {
            putenv($previous === false ? 'COMPOSER_AUTH' : 'COMPOSER_AUTH=' . $previous);
            putenv($agent === false ? 'SSH_AUTH_SOCK' : 'SSH_AUTH_SOCK=' . $agent);
        }
    }

    public function testPackageCannotReintroduceCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuildEnvironment::configured(['GITHUB_TOKEN' => 'canary']);
    }
}
