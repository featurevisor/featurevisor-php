<?php

namespace Featurevisor\Tests;

use PHPUnit\Framework\TestCase;

use Featurevisor\Emitter;

class EmitterTest extends TestCase {
    public function testAddListenerForEvent() {
        $emitter = new Emitter();
        $handledDetails = [];
        $handleDetails = function($details) use (&$handledDetails) {
            $handledDetails[] = $details;
        };

        $unsubscribe = $emitter->on('datafile_set', $handleDetails);

        self::assertContains($handleDetails, $emitter->listeners['datafile_set']);
        self::assertArrayNotHasKey('datafile_changed', $emitter->listeners);
        self::assertArrayNotHasKey('context_set', $emitter->listeners);
        self::assertCount(1, $emitter->listeners['datafile_set']);

        // trigger already subscribed event
        $emitter->trigger('datafile_set', ['key' => 'value']);
        self::assertCount(1, $handledDetails);
        self::assertEquals(['key' => 'value'], $handledDetails[0]);

        // trigger unsubscribed event
        $emitter->trigger('sticky_set', ['key' => 'value2']);
        self::assertCount(1, $handledDetails);

        // unsubscribe
        $unsubscribe();
        self::assertCount(0, $emitter->listeners['datafile_set']);

        // clear all
        $emitter->clearAll();
        self::assertEquals([], $emitter->listeners);
    }
}
