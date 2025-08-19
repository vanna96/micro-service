<?php
namespace App\Repositories;

use Illuminate\Contracts\Container\Container;
use Rinvex\Repository\Repositories\EloquentRepository;

class RepositoryBase extends EloquentRepository
{
    public function __construct(Container $container)
    {
        $this->setContainer($container);
    }
}