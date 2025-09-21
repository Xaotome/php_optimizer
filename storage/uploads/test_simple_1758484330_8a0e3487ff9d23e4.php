<?php

declare(strict_types=1);

namespace Test;

class SimpleTest
{
    public function testMethod($data)
    {
        // Test str_contains
        if (strpos($data, 'test') !== false) {
            return true;
        }

        // Test switch -> match
        switch ($data) {
            case 'a':
                return 1;
            case 'b':
                return 2;
            default:
                return 0;
        }
    }
}