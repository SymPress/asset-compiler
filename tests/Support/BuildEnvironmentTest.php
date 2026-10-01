<?php

declare(strict_types=1);

namespace SymPress\AssetCompiler\Tests\Support;

use PHPUnit\Framework\TestCase;
use SymPress\AssetCompiler\Support\BuildEnvironment;
use SymPress\AssetCompiler\Support\CompilationGuard;
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
            $process = new Process([PHP_BINARY, '-r', 'echo json_encode([getenv("COMPOSER_AUTH"),getenv("SSH_AUTH_SOCK"),getenv("BUILD_MARKER"),getenv("SYMPRESS_ASSET_COMPILER_ACTIVE")]);'], null, BuildEnvironment::process(['BUILD_MARKER' => 'approved']));
            $process->mustRun();
            self::assertSame('[false,false,"approved","1"]', $process->getOutput());
        } finally {
            putenv($previous === false ? 'COMPOSER_AUTH' : 'COMPOSER_AUTH=' . $previous);
            putenv($agent === false ? 'SSH_AUTH_SOCK' : 'SSH_AUTH_SOCK=' . $agent);
        }
    }

    public function testPackageCannotReplaceOrRemoveTheCompilationMarker(): void
    {
        self::assertSame('1', BuildEnvironment::process([CompilationGuard::ENVIRONMENT_VARIABLE => false])[CompilationGuard::ENVIRONMENT_VARIABLE]);
        $this->expectException(\InvalidArgumentException::class);
        BuildEnvironment::configured([CompilationGuard::ENVIRONMENT_VARIABLE => '']);
    }

    public function testPackageCannotReintroduceCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        BuildEnvironment::configured(['GITHUB_TOKEN' => 'canary']);
    }
}
