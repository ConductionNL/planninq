<?php

/**
 * Test double for OpenRegister's ObjectService.
 *
 * WHY THIS EXISTS
 * ---------------
 * Planix resolves OpenRegister's `ObjectService` from the container by FQCN
 * string so it carries no compile-time dependency on the openregister package.
 * That means the unit tests cannot `createMock(ObjectService::class)` — the
 * class is not on the classpath — and they used
 *
 *     $this->getMockBuilder(\stdClass::class)
 *         ->addMethods(['setRegister', 'setSchema', 'find', 'saveObject'])
 *         ->getMock()
 *
 * instead. PHPUnit generates those added methods with **no declared
 * parameters**, so every named argument in the production code —
 * `find(id: $projectId)`, `saveObject(object: $body, register: 'planix', …)` —
 * raised `Error: Unknown named parameter $id` / `$object`. That produced 4
 * errors and 1 failure in `ProjectControllerTest` on all six PHPUnit legs.
 *
 * The production calls were correct: OpenRegister really does declare
 * `find(int|string $id, …)` and
 * `saveObject(array|ObjectEntity $object, ?array $extend, …, ?string $uuid, bool $_rbac, …)`.
 * Only the double was wrong, so the double is what changed.
 *
 * The signatures below mirror OpenRegister's, with the concrete OR types
 * (`Register`, `Schema`, `ObjectEntity`) widened to `mixed`/`object` so this
 * file stays loadable without the openregister package present — which is the
 * whole point of the duck-typed container lookup.
 *
 * Keep the PARAMETER NAMES in sync with OpenRegister. They are the contract
 * planix depends on; a rename upstream is exactly the breakage this double now
 * catches instead of hiding.
 *
 * @category Test
 * @package  OCA\Planix\Tests\Unit\Support
 *
 * @author    Conduction Development Team <dev@conductio.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\Planix\Tests\Unit\Support;

/**
 * Signature-faithful stand-in for \OCA\OpenRegister\Service\ObjectService.
 *
 * Every method body is unreachable in tests — PHPUnit replaces them — so they
 * exist only to carry the declared parameter names.
 */
class ObjectServiceDouble
{


    /**
     * Set the current register context.
     *
     * @param mixed $register Register entity, id, uuid or slug.
     *
     * @return static
     */
    public function setRegister(mixed $register): static
    {
        return $this;

    }//end setRegister()


    /**
     * Set the current schema context.
     *
     * @param mixed $schema Schema entity, id, uuid or slug.
     *
     * @return static
     */
    public function setSchema(mixed $schema): static
    {
        return $this;

    }//end setSchema()


    /**
     * Find a single object.
     *
     * @param int|string $id            Object id, uuid or slug.
     * @param array|null $_extend       Properties to extend.
     * @param bool       $files         Whether to include files.
     * @param mixed      $register      Register override.
     * @param mixed      $schema        Schema override.
     * @param bool       $_rbac         Whether to apply RBAC.
     * @param bool       $_multitenancy Whether to apply the organisation filter.
     * @param bool       $_render       Whether to render the object.
     *
     * @return object|null
     */
    public function find(
        int | string $id,
        ?array $_extend=[],
        bool $files=false,
        mixed $register=null,
        mixed $schema=null,
        bool $_rbac=true,
        bool $_multitenancy=true,
        bool $_render=true
    ): ?object {
        return null;

    }//end find()


    /**
     * Persist an object.
     *
     * @param array|object $object        The object data or entity.
     * @param array|null   $extend        Properties to extend on the result.
     * @param mixed        $register      Register id, uuid or slug.
     * @param mixed        $schema        Schema id, uuid or slug.
     * @param string|null  $uuid          Uuid of the object to update.
     * @param bool         $_rbac         Whether to apply RBAC.
     * @param bool         $_multitenancy Whether to apply the organisation filter.
     * @param bool         $silent        Whether to suppress events.
     *
     * @return object|null
     */
    public function saveObject(
        array | object $object,
        ?array $extend=[],
        mixed $register=null,
        mixed $schema=null,
        ?string $uuid=null,
        bool $_rbac=true,
        bool $_multitenancy=true,
        bool $silent=false
    ): ?object {
        return null;

    }//end saveObject()


    /**
     * Search objects by register and schema slug.
     *
     * @param string $registerSlug  The register slug.
     * @param string $schemaSlug    The schema slug.
     * @param array  $filters       Additional filters.
     * @param bool   $_rbac         Whether to apply RBAC.
     * @param bool   $_multitenancy Whether to apply the organisation filter.
     *
     * @return array|int
     */
    public function searchObjectsBySlug(
        string $registerSlug,
        string $schemaSlug,
        array $filters=[],
        bool $_rbac=true,
        bool $_multitenancy=true
    ): array | int {
        return [];

    }//end searchObjectsBySlug()


    /**
     * Search objects.
     *
     * @param array      $query         The search query.
     * @param bool       $_rbac         Whether to apply RBAC.
     * @param bool       $_multitenancy Whether to apply the organisation filter.
     * @param array|null $ids           Optional ids to filter by.
     * @param string|null $uses         Optional usage filter.
     * @param array|null $views         Optional view ids.
     *
     * @return array|int
     */
    public function searchObjects(
        array $query=[],
        bool $_rbac=true,
        bool $_multitenancy=true,
        ?array $ids=null,
        ?string $uses=null,
        ?array $views=null
    ): array | int {
        return [];

    }//end searchObjects()


    /**
     * Delete an object.
     *
     * @param mixed $id            Object id or uuid.
     * @param bool  $_rbac         Whether to apply RBAC.
     * @param bool  $_multitenancy Whether to apply the organisation filter.
     *
     * @return bool
     */
    public function deleteObject(mixed $id, bool $_rbac=true, bool $_multitenancy=true): bool
    {
        return true;

    }//end deleteObject()


}//end class
