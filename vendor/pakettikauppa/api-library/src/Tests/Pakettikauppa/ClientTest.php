<?php

namespace Tests\Pakettikauppa;

use Pakettikauppa\Client;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
  /**
   * @return Client
   * @throws \Exception
   */
  public function getClient()
  {
    return new Client([], null);
  }

  public function testThereShouldBeNoLogAdapterByDefault()
  {
    $client = $this->getClient();
    self::assertNull($client->getLogClosure(), 'Default there should be no log adapter set');
  }

  public function provideSetLogAdapterToGiveErrorIfInvalidAdapter()
  {
    return [
      'nameOfExistingFunction' => [
        'error_log',
        \Exception::class,
        "logClosure must be function or null"
      ],
      'callable' => [
        [$this, 'getClient'],
        \Exception::class,
        "logClosure must be function or null"
      ],
      'invalidClosureWithoutParameters' => [
        static function(){},
        \Exception::class,
        "Function should have two parameters: message and level."
      ],
      'invalidClosureWithoutOneRequiredParameter' => [
        static function($message){},
        \Exception::class,
        "Function should have two parameters: message and level."
      ],
      'invalidClosureWithTooManyParameters' => [
        static function($message, $level, $invalidParameter){},
        \Exception::class,
        "Function should have two parameters: message and level."
      ],
    ];
  }

  /**
   * @param $adapter
   * @param $exceptionClass
   * @param $exceptionMessage
   * @throws \Exception
   * @dataProvider provideSetLogAdapterToGiveErrorIfInvalidAdapter
   */
  public function testSetLogAdapterToGiveErrorIfInvalidAdapter($adapter, $exceptionClass, $exceptionMessage)
  {
    $client = $this->getClient();
    $this->expectExceptionMessage($exceptionMessage);
    $this->expectException($exceptionClass);
    $client->setLogClosure($adapter);
  }

  public function testAddingCorrectLogAdapter()
  {
    $client = $this->getClient();
    self::assertSame(
      $client,
      $client->setLogClosure(
        static function($message, $level){}
      )
    );
  }

  public function testLogMessages()
  {
    $capturedMessages = [];
    $client = $this->getClient()->setLogClosure(
      static function($message, $level) use (&$capturedMessages)
      {
        if (!isset($capturedMessages[$level]))
        {
          $capturedMessages[$level] = [];
        }
        $capturedMessages[$level][] = $message;
      }
    );

    $client
      ->log('debug 1')
      ->logError('error 1')
      ->log('debug 3')
      ->log('debug 2')
    ;

    self::assertSame(
      [
        10 => [
          'debug 1',
          'debug 3',
          'debug 2',
        ],
        1 => [
          'error 1',
        ]
      ],
      $capturedMessages
    );
  }

  public function testLogUnauthorizedTokenRequest()
  {
    $capturedMessages = [];
    $client = $this->getClient()->setLogClosure(
      static function($message, $level) use (&$capturedMessages)
      {
        $capturedMessages[] = $message;
      }
    );

    $token = $client
      ->getToken();

    self::assertCount(2, $capturedMessages);
    self::assertStringContainsString('Request', $capturedMessages[0]);
    self::assertStringContainsString('POST', $capturedMessages[0]);
    self::assertStringContainsString('Headers', $capturedMessages[0]);

    self::assertStringContainsString('Response', $capturedMessages[1]);
    self::assertStringContainsString('Data', $capturedMessages[1]);
    self::assertStringContainsString('Unauthorized', $capturedMessages[1]);
  }
}
