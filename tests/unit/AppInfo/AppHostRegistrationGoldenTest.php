<?php

/**
 * Golden test for the AppHost plumbing registration.
 *
 * @category Tests
 * @package  OCA\Planninq\Tests\Unit\AppInfo
 *
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\Planninq\Tests\Unit\AppInfo;

use OCA\Planninq\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use PHPUnit\Framework\TestCase;

/**
 * Pins every call Application::registerAppHost() makes on the registration
 * context, in order, and the code and captured values of every factory it
 * registers.
 *
 * Calls made from inside OpenRegister (the store controller helper) are left
 * out, so the recording is the same with OpenRegister installed or absent.
 * Set PLANNINQ_GOLDEN_RECORD=1 to rewrite the fixture.
 *
 * @covers \OCA\Planninq\AppInfo\AppHostRegistration
 */
class AppHostRegistrationGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/apphost-registration.golden.json';

	/**
	 * The recorded calls equal the fixture.
	 *
	 * @return void
	 */
	public function testRegisterAppHostMatchesTheGoldenRecording(): void {
		$calls   = [];
		$context = $this->createMock(originalClassName: IRegistrationContext::class);
		foreach (get_class_methods(IRegistrationContext::class) as $name) {
			$context->method($name)->willReturnCallback(
				static function (mixed ...$args) use (&$calls, $name): void {
					if (self::callerIsPlanninq() === true) {
						$calls[] = ['method' => $name, 'args' => array_map(self::describe(...), $args)];
					}
				}
			);
		}

		$app = (new \ReflectionClass(Application::class))->newInstanceWithoutConstructor();
		(new \ReflectionMethod(Application::class, 'registerAppHost'))->invoke($app, $context);

		$json = json_encode($calls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
		if (getenv('PLANNINQ_GOLDEN_RECORD') === '1') {
			file_put_contents(self::FIXTURE, $json);
		}

		self::assertNotSame(expected: [], actual: $calls);
		self::assertSame(expected: (string)file_get_contents(self::FIXTURE), actual: $json);
	}//end testRegisterAppHostMatchesTheGoldenRecording()

	/**
	 * Whether the nearest app frame on the stack belongs to planninq.
	 *
	 * @return bool
	 */
	private static function callerIsPlanninq(): bool {
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
			$class = $frame['class'] ?? '';
			if (str_starts_with($class, 'OCA\\') === true && str_starts_with($class, 'OCA\\Planninq\\Tests\\') === false) {
				return str_starts_with($class, 'OCA\\Planninq\\');
			}
		}

		return false;
	}//end callerIsPlanninq()

	/**
	 * A JSON-safe description of one call argument; a closure is described by
	 * its parameters, return type, captured values and source text.
	 *
	 * @param mixed $value The argument.
	 *
	 * @return mixed
	 */
	private static function describe(mixed $value): mixed {
		if ($value instanceof \Closure === false) {
			return $value;
		}

		$function = new \ReflectionFunction($value);
		$lines    = file((string)$function->getFileName());
		$source   = array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1);

		return [
			'closure'  => array_map(static fn (\ReflectionParameter $p): string => (string)$p->getType() . ' $' . $p->getName(), $function->getParameters()),
			'returns'  => (string)$function->getReturnType(),
			'captured' => $function->getStaticVariables(),
			'source'   => array_values(array_map('trim', $source)),
		];
	}//end describe()
}//end class
