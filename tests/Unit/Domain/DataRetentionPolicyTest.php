<?php

namespace Tests\Unit\Domain;

use App\Domain\DataRetentionPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DataRetentionPolicyTest extends TestCase
{
    public function testAcceptsSafeRetentionRange(): void
    {
        self::assertSame(30, DataRetentionPolicy::days(30));
        self::assertSame(365, DataRetentionPolicy::days(365));
        self::assertSame(3650, DataRetentionPolicy::days(3650));
    }

    public function testRejectsUnsafeRetentionRange(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataRetentionPolicy::days(29);
    }

    public function testAcceptsBoundedBatchSize(): void
    {
        self::assertSame(1, DataRetentionPolicy::batchSize(1));
        self::assertSame(500, DataRetentionPolicy::batchSize(500));
        self::assertSame(5000, DataRetentionPolicy::batchSize(5000));
    }

    public function testRejectsOversizedBatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DataRetentionPolicy::batchSize(5001);
    }
}
