<?php
declare(strict_types=1);

/*
 +------------------------------------------------------------------------+
 | Phosphorum                                                             |
 +------------------------------------------------------------------------+
 | Copyright (c) 2013-present Phalcon Team and contributors               |
 +------------------------------------------------------------------------+
 | This source file is subject to the New BSD License that is bundled     |
 | with this package in the file LICENSE.txt.                             |
 |                                                                        |
 | If you did not receive a copy of the license and are unable to         |
 | obtain it through the world-wide-web, please send an email             |
 | to license@phalconphp.com so we can send you a copy immediately.       |
 +------------------------------------------------------------------------+
*/

namespace Phosphorum\Domain\Services;

use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;
use Phalcon\Mvc\Model\Manager;
use Phalcon\Mvc\Model\Query\Builder;
use Phalcon\Mvc\Model\Query\BuilderInterface;
use Phalcon\Platform\Domain\AbstractService;
use Phalcon\Platform\Traits\InjectionAwareTrait;
use Phosphorum\Domain\Entities\PostEntity;
use Phosphorum\Domain\Entities\PostRepliesEntity;
use Phosphorum\Domain\Repositories\PostRepository;
use Phalcon\Mvc\Model\Resultset\Complex;

/**
 * Phosphorum\Domain\Services\PostService
 *
 * @method PostRepository getRepository()
 *
 * @package Phosphorum\Domain\Services
 */
class PostService extends AbstractService implements InjectionAwareInterface
{
    use InjectionAwareTrait {
        InjectionAwareTrait::__construct as protected __DiInject;
    }

    /**
     * PostService constructor.
     *
     * @param PostRepository   $repository
     * @param DiInterface|null $container
     */
    public function __construct(PostRepository $repository, DiInterface $container = null)
    {
        $this->__DiInject($container);

        parent::__construct($repository);
    }

    /**
     * Get most popular posts.
     *
     * @param  int      $postsPerPage
     * @param  int|null $offset
     *
     * @return Complex
     */
    public function getPopularPosts(int $postsPerPage = 40, ?int $offset = null): Complex
    {
        $itemBuilder = $this->createItemBuilder($postsPerPage);
        $itemBuilder->orderBy('p.sticked DESC, p.modifiedAt DESC');

        $this
            ->withReplies($itemBuilder)
            ->withoutTrash($itemBuilder)
            ->applyOffset($itemBuilder, $offset);

        return $itemBuilder->getQuery()->execute();
    }

    protected function withReplies(BuilderInterface $postBuilder): self
    {
        $postBuilder
            ->leftJoin(PostRepliesEntity::class, 'p.id = rp.postId', 'rp')
            ->groupBy('p.id')
            ->columns([
                'p.*',
                'COUNT(rp.postId) AS count_replies',
                'IFNULL(MAX(rp.modifiedAt), MAX(rp.createdAt)) AS reply_time'
            ]);

        return $this;
    }

    protected function withoutTrash(BuilderInterface $postBuilder): self
    {
        $postBuilder->andWhere('p.deleted = 0');

        return $this;
    }

    /**
     * @param Builder $postBuilder
     * @param int|null $offset
     *
     * @return PostService
     */
    protected function applyOffset(Builder $postBuilder, ?int $offset = null): self
    {
        if ($offset > 0) {
            $postBuilder->offset($offset);
        }

        return $this;
    }

    /**
     * Prepares the item builder to be executed in each list of posts.
     *
     * The returned builder will be used as base in the search, tagged list and index lists.
     *
     * @param  int  $postsPerPage
     * @param  bool $joinReply
     *
     * @return BuilderInterface|Builder
     */
    protected function createItemBuilder(int $postsPerPage = 40, bool $joinReply = false): BuilderInterface
    {
        $itemBuilder = $this->createBuilder($joinReply);

        return $itemBuilder
            ->columns(['p.*'])
            ->limit($postsPerPage);
    }

    /**
     * Prepares the total builder to be executed in each list of posts.
     *
     * The returned builder will be used as base in the search, tagged list and index lists.
     *
     * @param  bool $joinReply
     *
     * @return BuilderInterface|Builder
     */
    protected function createTotalBuilder(bool $joinReply = false): BuilderInterface
    {
        $totalBuilder = $this->createBuilder($joinReply);

        return $totalBuilder
            ->columns('COUNT(*) AS count');
    }

    /**
     * Create internal query builder.
     *
     * @see PostService::createItemBuilder
     * @see PostService::createTotalBuilder
     *
     * @param  bool $joinReply
     *
     * @return BuilderInterface|Builder
     */
    protected function createBuilder(bool $joinReply = false): BuilderInterface
    {
        /** @var Manager $modelsManager */
        $modelsManager = $this->getDI()->getShared('modelsManager');

        $itemBuilder = $modelsManager
            ->createBuilder()
            ->from(['p' => PostEntity::class])
            ->orderBy('p.sticked DESC, p.createdAt DESC');

        if ($joinReply == true) {
            $itemBuilder
                ->groupBy('p.id')
                ->join(PostRepliesEntity::class, 'r.postId = p.id', 'r');
        }

        return $itemBuilder;
    }
}
