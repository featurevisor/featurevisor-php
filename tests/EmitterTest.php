<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../src/Emitter.php';

use Featurevisor\Emitter;

class EmitterTest extends TestCase {
    public function testAddListenerForEvent() {
        $emitter = new Emitter();
        $handledDetails = [];
        $handleDetails = function($details) use (&$handledDetails) {
            $handledDetails[] = $details;
        };

        $unsubscribe = $emitter->on('datafile_set', $handleDetails);

        $this->assertContains($handleDetails, $emitter->listeners['datafile_set']);
        $this->assertArrayNotHasKey('datafile_changed', $emitter->listeners);
        $this->assertArrayNotHasKey('context_set', $emitter->listeners);
        $this->assertCount(1, $emitter->listeners['datafile_set']);

        // trigger already subscribed event
        $emitter->trigger('datafile_set', ['key' => 'value']);
        $this->assertCount(1, $handledDetails);
        $this->assertEquals(['key' => 'value'], $handledDetails[0]);

        // trigger unsubscribed event
        $emitter->trigger('sticky_set', ['key' => 'value2']);
        $this->assertCount(1, $handledDetails);

        // unsubscribe
        $unsubscribe();
        $this->assertCount(0, $emitter->listeners['datafile_set']);

        // clear all
        $emitter->clearAll();
        $this->assertEquals([], $emitter->listeners);
    }
}
