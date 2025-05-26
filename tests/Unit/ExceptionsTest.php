<?php

declare(strict_types=1);

namespace Wio\WioPayments\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wio\WioPayments\Exceptions\InvalidCredentialsException;
use Wio\WioPayments\Exceptions\InvalidCurrencyException;
use Wio\WioPayments\Exceptions\PaymentFailedException;
use Wio\WioPayments\Exceptions\WioPaymentsException;

class ExceptionsTest extends TestCase
{
    /** @test */
    public function invalid_credentials_exception_extends_base_exception(): void
    {
        $exception = new InvalidCredentialsException('Invalid credentials');

        $this->assertInstanceOf(WioPaymentsException::class, $exception);
        $this->assertEquals('Invalid credentials', $exception->getMessage());
    }

    /** @test */
    public function invalid_currency_exception_extends_base_exception(): void
    {
        $exception = new InvalidCurrencyException('Invalid currency');

        $this->assertInstanceOf(WioPaymentsException::class, $exception);
        $this->assertEquals('Invalid currency', $exception->getMessage());
    }

    /** @test */
    public function payment_failed_exception_extends_base_exception(): void
    {
        $exception = new PaymentFailedException('Payment failed');

        $this->assertInstanceOf(WioPaymentsException::class, $exception);
        $this->assertEquals('Payment failed', $exception->getMessage());
    }

    /** @test */
    public function base_exception_extends_standard_exception(): void
    {
        $exception = new WioPaymentsException('Base exception');

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertEquals('Base exception', $exception->getMessage());
    }

    /** @test */
    public function exceptions_support_previous_exception(): void
    {
        $previousException = new \Exception('Previous error');
        $exception = new PaymentFailedException('Payment failed', 0, $previousException);

        $this->assertSame($previousException, $exception->getPrevious());
    }

    /** @test */
    public function exceptions_support_error_codes(): void
    {
        $exception = new PaymentFailedException('Payment failed', 1001);

        $this->assertEquals(1001, $exception->getCode());
    }
}