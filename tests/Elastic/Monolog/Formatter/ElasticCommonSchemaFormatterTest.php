<?php

declare(strict_types=1);

// Licensed to Elasticsearch B.V under one or more agreements.
// Elasticsearch B.V licenses this file to you under the Apache 2.0 License.
// See the LICENSE file in the project root for more information

namespace Elastic\Tests\Monolog\Formatter;

use DateTimeImmutable;
use Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter;
use Elastic\Tests\BaseTestCase;
use Elastic\Types\{Error, Service, Tracing, User};
use Monolog\Logger;

/**
 * Test: ElasticCommonSchemaFormatter
 *
 * @see    https://www.elastic.co/guide/en/ecs/1.2/ecs-log.html
 * @see    \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter
 *
 * @author Philip Krauss <philip.krauss@elastic.co>
 */
class ElasticCommonSchemaFormatterTest extends BaseTestCase
{
    private const ECS_VERSION = '1.2.0';

    /**
     * @covers \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testFormat()
    {
        $msg = [
            'level'      => Logger::INFO,
            'level_name' => 'INFO',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => [],
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($msg);

        // Must be a string terminated by a new line
        $this->assertIsString($doc);
        $this->assertStringEndsWith("\n", $doc);

        // Comply to the ECS format
        $decoded = json_decode($doc, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('@timestamp', $decoded);
        $this->assertArrayHasKey('log.level', $decoded);
        $this->assertArrayHasKey('log', $decoded);
        $this->assertArrayHasKey('logger', $decoded['log']);
        $this->assertArrayHasKey('message', $decoded);

        // Not other keys are set for the MVP
        $this->assertEquals(['@timestamp', 'log.level', 'message', 'ecs.version', 'log'], array_keys($decoded));
        $this->assertEquals(['logger'], array_keys($decoded['log']));

        // Values correctly propagated
        $this->assertEquals('1970-01-01T00:00:00.000000Z', $decoded['@timestamp']);
        $this->assertEquals($msg['level_name'], $decoded['log.level']);
        $this->assertEquals($msg['message'], $decoded['message']);
        $this->assertEquals(self::ECS_VERSION, $decoded['ecs.version']);
        $this->assertEquals($msg['channel'], $decoded['log']['logger']);
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testContextWithTracing()
    {
        $tracing = new Tracing($this->generateTraceId(), $this->generateTransactionId());
        $msg = [
            'level'      => Logger::NOTICE,
            'level_name' => 'NOTICE',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => ['tracing' => $tracing],
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($msg);

        $decoded = json_decode($doc, true);
        $this->assertArrayHasKey('trace', $decoded);
        $this->assertArrayHasKey('transaction', $decoded);
        $this->assertArrayHasKey('id', $decoded['trace']);
        $this->assertArrayHasKey('id', $decoded['transaction']);

        $this->assertEquals($tracing->toArray()['trace']['id'], $decoded['trace']['id']);
        $this->assertEquals($tracing->toArray()['transaction']['id'], $decoded['transaction']['id']);
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testContextWithService()
    {
        $service = new Service();
        $service->setId(rand(100, 999));
        $service->setName('funky-service-01');

        $msg = [
            'level'      => Logger::NOTICE,
            'level_name' => 'NOTICE',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => ['service' => $service],
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($msg);

        $decoded = json_decode($doc, true);
        $this->assertArrayHasKey('service', $decoded);
        $this->assertArrayHasKey('id', $decoded['service']);
        $this->assertArrayHasKey('name', $decoded['service']);

        $this->assertEquals($service->toArray()['service']['id'], $decoded['service']['id']);
        $this->assertEquals($service->toArray()['service']['name'], $decoded['service']['name']);
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testContextWithUser()
    {
        $user = new User();
        $user->setId(rand(100, 999));
        $user->setHash(md5(uniqid()));

        $msg = [
            'level'      => Logger::NOTICE,
            'level_name' => 'NOTICE',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => ['user' => $user],
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($msg);

        $decoded = json_decode($doc, true);
        $this->assertArrayHasKey('user', $decoded);
        $this->assertArrayHasKey('id', $decoded['user']);
        $this->assertArrayHasKey('hash', $decoded['user']);

        $this->assertEquals($user->toArray()['user']['id'], $decoded['user']['id']);
        $this->assertEquals($user->toArray()['user']['hash'], $decoded['user']['hash']);
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testContextWithError()
    {
        $t = $this->generateException();
        $error = new Error($t);

        $msg = [
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => ['error' => $error],
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($msg);
        $decoded = json_decode($doc, true);

        // ECS Struct ?
        $this->assertArrayHasKey('error', $decoded);
        $this->assertArrayHasKey('type', $decoded['error']);
        $this->assertArrayHasKey('message', $decoded['error']);
        $this->assertArrayHasKey('code', $decoded['error']);
        $this->assertArrayHasKey('stack_trace', $decoded['error']);

        $this->assertArrayHasKey('log', $decoded);
        $this->assertArrayHasKey('origin', $decoded['log']);
        $this->assertArrayHasKey('file', $decoded['log']['origin']);
        $this->assertArrayHasKey('name', $decoded['log']['origin']['file']);
        $this->assertArrayHasKey('line', $decoded['log']['origin']['file']);

        // Ensure Array merging is sound ..
        $this->assertArrayHasKey('log.level', $decoded);
        $this->assertArrayHasKey('logger', $decoded['log']);

        // Values Correct ?
        $this->assertEquals('BaseTestCase.php', basename($decoded['log']['origin']['file']['name']));
        $this->assertEquals(44, $decoded['log']['origin']['file']['line']);

        $this->assertEquals('InvalidArgumentException', $decoded['error']['type']);
        $this->assertEquals($t->getMessage(), $decoded['error']['message']);
        $this->assertEquals($t->getCode(), $decoded['error']['code']);
        $this->assertIsArray($decoded['error']['stack_trace']);
        $this->assertNotEmpty($decoded['error']['stack_trace']);

        // Throwable removed from Context/Labels ?
        $this->assertArrayNotHasKey('labels', $decoded);
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     */
    public function testTags()
    {
        $msg = [
            'level'      => Logger::ERROR,
            'level_name' => 'ERROR',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => [],
            'extra'      => [],
        ];

        $tags = [
            'one',
            'two',
        ];

        $formatter = new ElasticCommonSchemaFormatter($tags);
        $doc = $formatter->format($msg);

        $decoded = json_decode($doc, true);
        $this->assertArrayHasKey('tags', $decoded);
        $this->assertEquals($tags, $decoded['tags']);
    }

    private static function isPrefixOf(string $prefix, string $text, bool $isCaseSensitive = true): bool
    {
        $prefixLen = strlen($prefix);
        if ($prefixLen === 0) {
            return true;
        }

        return substr_compare(
            $text /* <- haystack */,
            $prefix /* <- needle */,
            0 /* <- offset */,
            $prefixLen /* <- length */,
            !$isCaseSensitive /* <- case_insensitivity */
        ) === 0;
    }

    /**
     * @depends testFormat
     *
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::__construct
     * @covers  \Elastic\Monolog\Formatter\ElasticCommonSchemaFormatter::format
     */
    public function testSanitizeOfLabelKeys()
    {
        $inLabels = [
            'sim ple' => 'sim_ple',
            ' lpad'   => 'lpad',
            'rpad '   => 'rpad',
            'foo.bar' => 'foo_bar',
            'a.b.c'   => 'a_b_c',
            '.hello'  => '_hello',
            'lorem.'  => 'lorem_',
            'st*ar'   => 'st_ar',
            'sla\sh'  => 'sla_sh',
            'a.b*c\d' => 'a_b_c_d',
        ];

        $inContext = ['labels' => $inLabels];
        foreach ($inLabels as $key => $val) {
            $inContext['top_level_' . $key] = $key;
        }

        $inRecord = [
            'level'      => Logger::NOTICE,
            'level_name' => 'NOTICE',
            'channel'    => 'ecs',
            'datetime'   => new DateTimeImmutable("@0"),
            'message'    => md5(uniqid()),
            'context'    => $inContext,
            'extra'      => [],
        ];

        $formatter = new ElasticCommonSchemaFormatter();
        $doc = $formatter->format($inRecord);
        $decoded = json_decode($doc, true);

        $this->assertArrayHasKey('labels', $decoded);
        $outLabels = $decoded['labels'];
        $this->assertCount(count($inLabels), $outLabels);
        foreach ($inLabels as $keyPrevious => $keySanitized) {
            $this->assertArrayNotHasKey($keyPrevious, $outLabels, $keyPrevious);
            $this->assertArrayHasKey($keySanitized, $outLabels, $keySanitized);
        }

        $topLevelFoundCount = 0;
        foreach ($inContext as $key => $val) {
            if (!self::isPrefixOf('top_level_', $key)) {
                continue;
            }

            $this->assertSame('top_level_' . $val, $key);
            ++$topLevelFoundCount;
        }
        $this->assertSame(count($inLabels), $topLevelFoundCount);
    }
}
