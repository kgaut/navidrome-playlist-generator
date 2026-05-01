<?php

namespace App\Tests\Service;

use App\Service\StatsService;
use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase
{
    public function testPeriodsContainsExpectedKeys(): void
    {
        $keys = array_keys(StatsService::periods());

        $this->assertContains(StatsService::PERIOD_LAST_7D, $keys);
        $this->assertContains(StatsService::PERIOD_LAST_30D, $keys);
        $this->assertContains(StatsService::PERIOD_LAST_MONTH, $keys);
        $this->assertContains(StatsService::PERIOD_LAST_YEAR, $keys);
        $this->assertContains(StatsService::PERIOD_ALL_TIME, $keys);
    }

    public function testPeriodsLabelsAreNonEmpty(): void
    {
        foreach (StatsService::periods() as $key => $label) {
            $this->assertNotSame('', $key);
            $this->assertNotSame('', $label);
        }
    }
}
