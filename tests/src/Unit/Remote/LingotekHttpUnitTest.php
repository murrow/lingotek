<?php

namespace Drupal\Tests\lingotek\Unit\Remote;

use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\lingotek\Remote\LingotekHttp;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversDefaultClass \Drupal\lingotek\Remote\LingotekHttp
 * @group lingotek
 * @preserveGlobalState disabled
 * @requires PHPUnit > 8
 * We depend on MockBuilder::addMethods() which was introduced in PHPUnit 8.
 */
class LingotekHttpUnitTest extends UnitTestCase {


  /**
   * @var \Drupal\lingotek\Remote\LingotekHttp
   */
  protected $lingotekHttp;

  /**
   * The HTTP client to interact with the Lingotek service.
   *
   * @var \GuzzleHttp\ClientInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $httpClient;

  /**
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $config;

  /**
   * @var \Drupal\Core\Config\Config|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $accountConfig;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->httpClient = $this
      ->getMockBuilder(ClientInterface::class)
      ->addMethods(['get', 'post', 'patch', 'delete'])
      ->getMockForAbstractClass();

    $this->config = $this->createMock(Config::class);
    $this->accountConfig = $this->createMock(Config::class);

    $this->accountConfig->expects($this->any())
      ->method('get')
      ->will($this->returnValueMap([['host', 'http://example.com'], ['access_token', 'the_token']]));

    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->configFactory->expects($this->any())
      ->method('get')
      ->withConsecutive(['lingotek.settings'], ['lingotek.account'])
      ->willReturnOnConsecutiveCalls($this->config, $this->accountConfig);

    $this->lingotekHttp = new LingotekHttp($this->httpClient, $this->configFactory);
  }

  /**
   * @covers ::get
   */
  public function testGet() {
    $response = $this->createMock(ResponseInterface::class);
    $this->httpClient->expects($this->at(0))
      ->method('get')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
        ],
        'query' => [
          'one' => 'one_value',
        ],
      ])
      ->will($this->returnValue($response));

    $args = ['one' => 'one_value'];
    $this->lingotekHttp->get('/test', $args);
  }

  /**
   * @covers ::post
   */
  public function testPost() {
    $response = $this->createMock(ResponseInterface::class);
    $this->httpClient->expects($this->at(0))
      ->method('post')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
        ],
        'multipart' => [
          [
            'name' => 'one',
            'contents' => 'one_value',
          ],
          [
            'name' => 'fruits',
            'contents' => 'pear',
          ],
          [
            'name' => 'fruits',
            'contents' => 'lemon',
          ],
        ],
      ])
      ->will($this->returnValue($response));

    $this->httpClient->expects($this->at(1))
      ->method('post')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
        ],
        'form_params' => [
          'one' => 'one_value',
          'fruits' => ['pear', 'lemon'],
        ],
      ])
      ->will($this->returnValue($response));

    $args = ['one' => 'one_value', 'fruits' => ['pear', 'lemon']];
    $this->lingotekHttp->post('/test', $args, TRUE);
    $this->lingotekHttp->post('/test', $args, FALSE);
  }

  /**
   * @covers ::patch
   */
  public function testPatch() {
    $response = $this->createMock(ResponseInterface::class);
    $this->httpClient->expects($this->at(0))
      ->method('patch')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
          'X-HTTP-Method-Override' => 'PATCH',
        ],
        'multipart' => [
          [
            'name' => 'one',
            'contents' => 'one_value',
          ],
          [
            'name' => 'fruits',
            'contents' => 'pear',
          ],
          [
            'name' => 'fruits',
            'contents' => 'lemon',
          ],
        ],
      ])
      ->will($this->returnValue($response));

    $this->httpClient->expects($this->at(1))
      ->method('patch')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
          'X-HTTP-Method-Override' => 'PATCH',
        ],
        'form_params' => [
          'one' => 'one_value',
          'fruits' => ['pear', 'lemon'],
        ],
      ])
      ->will($this->returnValue($response));

    $args = ['one' => 'one_value', 'fruits' => ['pear', 'lemon']];
    $this->lingotekHttp->patch('/test', $args, TRUE);
    $this->lingotekHttp->patch('/test', $args, FALSE);
  }

  /**
   * @covers ::delete
   */
  public function testDelete() {
    $response = $this->createMock(ResponseInterface::class);
    $this->httpClient->expects($this->at(0))
      ->method('delete')
      ->with('http://example.com/test', [
        'headers' => [
          'Accept' => '*/*',
          'Authorization' => 'bearer the_token',
          'X-HTTP-Method-Override' => 'DELETE',
        ],
        'query' => [
          'one' => 'one_value',
        ],
      ])
      ->will($this->returnValue($response));

    $args = ['one' => 'one_value'];
    $this->lingotekHttp->delete('/test', $args);
  }

  /**
   * @covers ::getCurrentToken
   */
  public function testGetCurrentToken() {
    $value = $this->lingotekHttp->getCurrentToken();
    $this->assertEquals('the_token', $value);
  }

}
