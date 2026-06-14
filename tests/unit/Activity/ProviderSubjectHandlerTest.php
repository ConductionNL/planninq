<?php

/**
 * Unit tests for the planix Activity ProviderSubjectHandler.
 *
 * Verifies that every handled subject (created / status changed / assigned /
 * due date changed / deleted) is rendered with both a parsed subject and a rich
 * subject carrying the actor + task rich parameters, in the language supplied by
 * the IL10N translator — without leaking unsubstituted placeholders.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Activity
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2024 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * @spec openspec/specs/task-collaboration.md
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Activity;

use OCA\Planix\Activity\ProviderSubjectHandler;
use OCP\Activity\IEvent;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProviderSubjectHandler.
 */
class ProviderSubjectHandlerTest extends TestCase
{
    /**
     * The handler under test.
     *
     * @var ProviderSubjectHandler
     */
    private ProviderSubjectHandler $handler;

    /**
     * Set up the handler.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->handler = new ProviderSubjectHandler();
    }//end setUp()

    /**
     * Build a fake IEvent that records the parsed + rich subjects set on it and
     * returns the supplied subject / params.
     *
     * @param string $subject The event subject key.
     * @param array  $params  The subject parameters.
     *
     * @return IEvent The recording event double.
     */
    private function fakeEvent(string $subject, array $params): IEvent
    {
        $event = $this->createMock(IEvent::class);
        $event->method('getSubject')->willReturn($subject);
        $event->method('getSubjectParameters')->willReturn($params);
        $event->method('getObjectId')->willReturn(0);

        $event->method('setParsedSubject')->willReturnCallback(
            function (string $s) use ($event): IEvent {
                $event->parsed = $s;
                return $event;
            }
        );
        $event->method('setRichSubject')->willReturnCallback(
            function (string $s, array $p = []) use ($event): IEvent {
                $event->rich       = $s;
                $event->richParams = $p;
                return $event;
            }
        );

        return $event;
    }//end fakeEvent()

    /**
     * A pass-through IL10N: `t()` substitutes %n$s placeholders so we can assert
     * the rendered string, mirroring the production translator's behaviour.
     *
     * @return IL10N The translator double.
     */
    private function translator(): IL10N
    {
        $l = $this->createMock(IL10N::class);
        $l->method('t')->willReturnCallback(
            function (string $text, $params = []): string {
                if (is_array($params) === false) {
                    $params = [$params];
                }

                foreach ($params as $i => $value) {
                    $text = str_replace('%'.($i + 1).'$s', (string) $value, $text);
                }

                return $text;
            }
        );

        return $l;
    }//end translator()

    /**
     * Created subject renders actor + title and a rich subject.
     *
     * @return void
     */
    public function testTaskCreatedSubject(): void
    {
        $event = $this->fakeEvent('task_created', ['actor' => 'alice', 'title' => 'Write spec', 'objectId' => 'uuid-1']);
        $this->handler->applySubjectText(event: $event, l: $this->translator(), params: $event->getSubjectParameters());

        $this->assertSame('alice created task Write spec', $event->parsed);
        $this->assertSame('{actor} created task {task}', $event->rich);
        $this->assertSame('alice', $event->richParams['actor']['id']);
        $this->assertSame('Write spec', $event->richParams['task']['name']);
        $this->assertSame('uuid-1', $event->richParams['task']['id']);
        $this->assertStringNotContainsString('%', $event->parsed);
    }//end testTaskCreatedSubject()

    /**
     * Status changed subject includes the new status.
     *
     * @return void
     */
    public function testStatusChangedSubject(): void
    {
        $event = $this->fakeEvent('task_status_changed', ['actor' => 'alice', 'title' => 'T', 'status' => 'done']);
        $this->handler->applySubjectText(event: $event, l: $this->translator(), params: $event->getSubjectParameters());

        $this->assertSame('alice changed the status of T to done', $event->parsed);
        $this->assertSame('{actor} changed the status of {task} to done', $event->rich);
        $this->assertStringNotContainsString('%', $event->parsed);
    }//end testStatusChangedSubject()

    /**
     * Assignee subject includes the new assignee.
     *
     * @return void
     */
    public function testAssignedSubject(): void
    {
        $event = $this->fakeEvent('task_assigned_activity', ['actor' => 'alice', 'title' => 'T', 'assignee' => 'bob']);
        $this->handler->applySubjectText(event: $event, l: $this->translator(), params: $event->getSubjectParameters());

        $this->assertSame('alice assigned T to bob', $event->parsed);
        $this->assertStringContainsString('bob', $event->rich);
    }//end testAssignedSubject()

    /**
     * Due date subject includes the new due date.
     *
     * @return void
     */
    public function testDueDateChangedSubject(): void
    {
        $event = $this->fakeEvent('task_due_date_changed', ['actor' => 'alice', 'title' => 'T', 'dueDate' => '2026-07-01']);
        $this->handler->applySubjectText(event: $event, l: $this->translator(), params: $event->getSubjectParameters());

        $this->assertSame('alice changed the due date of T to 2026-07-01', $event->parsed);
        $this->assertStringNotContainsString('%', $event->parsed);
    }//end testDueDateChangedSubject()

    /**
     * Deleted subject renders actor + title.
     *
     * @return void
     */
    public function testDeletedSubject(): void
    {
        $event = $this->fakeEvent('task_deleted', ['actor' => 'alice', 'title' => 'Old task']);
        $this->handler->applySubjectText(event: $event, l: $this->translator(), params: $event->getSubjectParameters());

        $this->assertSame('alice deleted task Old task', $event->parsed);
        $this->assertSame('{actor} deleted task {task}', $event->rich);
    }//end testDeletedSubject()
}//end class
