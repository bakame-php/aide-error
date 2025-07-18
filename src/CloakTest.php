<?php

declare(strict_types=1);

namespace Bakame\Aide\Error;

use ErrorException;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

use const E_ALL;
use const E_DEPRECATED;
use const E_NOTICE;
use const E_STRICT;
use const E_WARNING;

final class CloakTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cloak::silentOnError();
    }

    #[Test]
    public function it_returns_information_about_its_error_reporting_level(): void
    {
        error_reporting(-1);
        $lambda = Cloak::warning(touch(...));
        $res = $lambda('/foo');

        self::assertFalse($res);
        self::assertTrue($lambda->reportingLevel()->contains(E_WARNING));
        self::assertFalse($lambda->reportingLevel()->contains(E_NOTICE));
        self::assertCount(1, $lambda->errors());
        self::assertFalse($lambda->errors()->isEmpty());
    }

    #[Test]
    public function it_will_include_nothing_in_case_of_success(): void
    {
        $lambda = Cloak::userWarning(strtoupper(...));
        $res = $lambda('foo');

        self::assertSame('FOO', $res);
        self::assertTrue($lambda->errors()->isEmpty());
        self::assertFalse($lambda->errors()->isNotEmpty());
    }

    public function testGetErrorReporting(): void
    {
        $lambda = Cloak::deprecated(strtoupper(...));

        self::assertTrue($lambda->reportingLevel()->contains(E_DEPRECATED));
    }

    public function testCapturesTriggeredError(): void
    {
        error_reporting(-1);

        $lambda = Cloak::all(trigger_error(...));
        $lambda('foo');

        self::assertSame('foo', $lambda->errors()->last()?->getMessage());
    }

    public function testCapturesSilencedError(): void
    {
        $lambda = Cloak::warning(fn (string $x) => @trigger_error($x));
        $lambda('foo');

        self::assertTrue($lambda->errors()->isEmpty());
    }

    public function testErrorTransformedIntoARuntimeException(): void
    {
        error_reporting(-1);
        $this->expectException(ErrorException::class);

        Cloak::throwOnError();
        $touch = Cloak::warning(touch(...));
        $touch('/foo');
    }

    public function testErrorTransformedIntoAnErrorException(): void
    {
        error_reporting(-1);
        Cloak::throwOnError();
        $this->expectException(ErrorException::class);

        $touch = Cloak::all(touch(...));
        $touch('/foo');
    }

    public function testSpecificBehaviourOverrideGeneralErrorSetting(): void
    {
        $this->expectNotToPerformAssertions();

        Cloak::throwOnError();
        $touch = Cloak::warning(touch(...), Cloak::SILENT);
        $touch('/foo');
    }

    public function testCaptureNothingThrowNoException(): void
    {
        Cloak::throwOnError();
        $strtoupper = Cloak::userDeprecated(strtoupper(...));

        self::assertSame('FOO', $strtoupper('foo'));
    }

    #[Test]
    public function it_can_detect_the_level_to_include(): void
    {
        $touch = new Cloak(
            touch(...),
            Cloak::THROW,
            E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED
        );

        $errorLevel = $touch->reportingLevel();

        self::assertFalse($errorLevel->contains(E_NOTICE));
        self::assertTrue($touch->errorsAreThrown());
        self::assertFalse($touch->errorsAreSilenced());
    }

    #[Test]
    public function it_can_collection_all_errors_if_errors_are_silenced(): void
    {
        error_reporting(-1);
        $callback = function (string $path): array|false {
            touch($path);

            return file($path);
        };

        $lambda = Cloak::warning($callback);
        $res = $lambda('/foobar');
        $errors = $lambda->errors();
        self::assertFalse($res);
        self::assertCount(2, $errors);
        self::assertTrue($errors->isNotEmpty());
        self::assertFalse($errors->isEmpty());
        self::assertCount(2, [...$errors]);

        self::assertStringContainsString('touch(): Unable to create file /foobar because', $errors->first()?->getMessage() ?? '');
        self::assertSame('file(/foobar): Failed to open stream: No such file or directory', $errors->last()?->getMessage() ?? '');
    }

    #[Test]
    public function it_throws_with_the_first_error_if_errors_are_thrown(): void
    {
        error_reporting(-1);
        $callback = function (string $path): array|false {
            touch($path);

            return file($path);
        };

        try {
            $lambda = Cloak::warning($callback, Cloak::THROW);
            $lambda('/foobar');
            self::fail(ErrorException::class.' was not thrown');
        } catch (ErrorException $cloakedErrors) {
            self::assertStringContainsString('touch(): Unable to create file /foobar because', $cloakedErrors->getMessage());
        }
    }

    #[Test]
    public function it_does_not_interfer_with_exception(): void
    {
        $this->expectException(Exception::class);

        $lambda = Cloak::userNotice(fn () => throw new Exception());
        $lambda();
    }

    #[Test]
    public function it_does_use_the_current_error_reporting_level(): void
    {
        $lambda = Cloak::env(fn () => true, Cloak::SILENT);
        $lambda();
        self::assertSame($lambda->reportingLevel()->value(), ReportingLevel::fromEnv()->value());
    }

    #[Test]
    public function it_will_fail_instantiation_with_wrong_settings(): void
    {
        $this->expectException(ValueError::class);

        new Cloak(fn () => true, -1, E_WARNING);
    }
}
