<?php

/**
 * @file
 * Contains \Drupal\Tests\Component\Utility\TimerUnitTest.
 */

namespace Drupal\Tests\Component\Utility;

use Drupal\Tests\UnitTestCase;
use Drupal\Component\Utility\Timer;

/**
 * Tests the Timer system.
 *
 * @see \Drupal\Component\Utility\Timer
 */
class TimerUnitTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Timer test',
      'description' => 'Test that Timer::read() works both when a timer is running and when a timer is stopped.',
      'group' => 'Bootstrap',
    );
  }

  /**
   * Tests Timer::read() time accumulation accuracy across multiple restarts.
   *
   * @see Drupal\Component\Utility\Timer::read()
   */
  public function testTimer() {
    Timer::start('test');
    usleep(5000);
    $value = Timer::read('test');
    usleep(5000);
    $value2 = Timer::read('test');
    usleep(5000);
    $value3 = Timer::read('test');
    usleep(5000);
    $value4 = Timer::read('test');

    // Although we sleep for 5 milliseconds, we should test that at least 4 ms
    // have past because usleep() is not reliable on Windows. See
    // http://php.net/manual/en/function.usleep.php for more information. The
    // purpose of the test to validate that the Timer class can measure elapsed
    // time not the granularity of usleep() on a particular OS.
    $this->assertGreaterThanOrEqual(4, $value, 'Timer measured at least 4 milliseconds of sleeping while running.');

    $this->assertGreaterThanOrEqual($value + 4, $value2, 'Timer measured at least 8 milliseconds of sleeping while running.');

    $this->assertGreaterThanOrEqual($value2 + 4, $value3, 'Timer measured at least 12 milliseconds of sleeping while running.');

    $this->assertGreaterThanOrEqual($value3 + 4, $value4, 'Timer measured at least 16 milliseconds of sleeping while running.');
  }

}
