<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingStatus;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClassBookingTest extends TestCase
{
    private function makeBooking(
        int $id = 1,
        string $publicId = '01j00000000000000000000000',
        int $companyId = 10,
        int $gymId = 20,
        int $sessionId = 30,
        int $clientId = 40,
        ClassBookingStatus $status = ClassBookingStatus::BOOKED,
        ?string $idempotencyKey = 'key-123',
        ?DateTimeImmutable $bookedAt = null,
        ?DateTimeImmutable $cancelledAt = null,
    ): ClassBooking {
        return new ClassBooking(
            id: $id,
            publicId: $publicId,
            companyId: $companyId,
            gymId: $gymId,
            sessionId: $sessionId,
            clientId: $clientId,
            status: $status,
            idempotencyKey: $idempotencyKey,
            bookedAt: $bookedAt ?? new DateTimeImmutable('2026-11-12 09:00:00+00:00'),
            cancelledAt: $cancelledAt,
        );
    }

    public function test_it_creates_normalizes_key_and_cancels_booking(): void
    {
        $bookedAt = new DateTimeImmutable('2026-11-12 09:00:00+00:00');
        $booking = $this->makeBooking(
            publicId: '  01j00000000000000000000001  ',
            idempotencyKey: '  req-abc  ',
            bookedAt: $bookedAt,
        );

        $this->assertSame(1, $booking->id());
        $this->assertSame('01j00000000000000000000001', $booking->publicId());
        $this->assertSame(10, $booking->companyId());
        $this->assertSame(20, $booking->gymId());
        $this->assertSame(30, $booking->sessionId());
        $this->assertSame(40, $booking->clientId());
        $this->assertSame(ClassBookingStatus::BOOKED, $booking->status());
        $this->assertSame('req-abc', $booking->idempotencyKey());
        $this->assertEquals($bookedAt, $booking->bookedAt());
        $this->assertNull($booking->cancelledAt());
        $this->assertTrue($booking->isBooked());
        $this->assertFalse($booking->isCancelled());

        $blankKeyBooking = $this->makeBooking(idempotencyKey: '   ');
        $this->assertNull($blankKeyBooking->idempotencyKey());

        $cancelledAt = new DateTimeImmutable('2026-11-12 09:30:00+00:00');
        $booking->cancel($cancelledAt);

        $this->assertSame(ClassBookingStatus::CANCELLED, $booking->status());
        $this->assertEquals($cancelledAt, $booking->cancelledAt());
        $this->assertFalse($booking->isBooked());
        $this->assertTrue($booking->isCancelled());
    }

    public function test_it_accepts_pre_cancelled_booking_with_valid_timestamps(): void
    {
        $bookedAt = new DateTimeImmutable('2026-11-12 09:00:00+00:00');
        $booking = $this->makeBooking(
            status: ClassBookingStatus::CANCELLED,
            idempotencyKey: null,
            bookedAt: $bookedAt,
            cancelledAt: $bookedAt,
        );

        $this->assertTrue($booking->isCancelled());
        $this->assertNull($booking->idempotencyKey());
        $this->assertEquals($bookedAt, $booking->cancelledAt());
    }

    public function test_it_rejects_invalid_invariants_and_repeated_or_backdated_cancellation(): void
    {
        $bookedAt = new DateTimeImmutable('2026-11-12 09:00:00+00:00');
        $beforeBooked = new DateTimeImmutable('2026-11-12 08:59:59+00:00');
        $afterBooked = new DateTimeImmutable('2026-11-12 09:10:00+00:00');

        $invalidCases = [
            fn () => $this->makeBooking(id: 0),
            fn () => $this->makeBooking(companyId: 0),
            fn () => $this->makeBooking(gymId: -1),
            fn () => $this->makeBooking(sessionId: 0),
            fn () => $this->makeBooking(clientId: 0),
            fn () => $this->makeBooking(publicId: '   '),
            fn () => $this->makeBooking(idempotencyKey: str_repeat('a', ClassBooking::IDEMPOTENCY_KEY_MAX + 1)),
            fn () => $this->makeBooking(status: ClassBookingStatus::BOOKED, cancelledAt: $afterBooked),
            fn () => $this->makeBooking(status: ClassBookingStatus::CANCELLED, cancelledAt: null),
            fn () => $this->makeBooking(status: ClassBookingStatus::CANCELLED, bookedAt: $bookedAt, cancelledAt: $beforeBooked),
        ];

        foreach ($invalidCases as $index => $factory) {
            try {
                $factory();
                $this->fail("Case {$index} must throw InvalidClassBooking.");
            } catch (InvalidClassBooking) {
                $this->addToAssertionCount(1);
            }
        }

        $booking = $this->makeBooking(bookedAt: $bookedAt);

        try {
            $booking->cancel($beforeBooked);
            $this->fail('Cancelling before bookedAt must throw.');
        } catch (InvalidClassBooking) {
            $this->assertTrue($booking->isBooked());
            $this->assertNull($booking->cancelledAt());
        }

        $booking->cancel($afterBooked);

        try {
            $booking->cancel($afterBooked->modify('+5 minutes'));
            $this->fail('Double cancellation must throw.');
        } catch (InvalidClassBooking) {
            $this->assertTrue($booking->isCancelled());
            $this->assertEquals($afterBooked, $booking->cancelledAt());
        }
    }
}
