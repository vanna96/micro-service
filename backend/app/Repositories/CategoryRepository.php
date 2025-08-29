<?php
namespace App\Repositories;

use App\Repositories\RepositoryBase;

/** @package App\Repositories */
class CategoryRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model        = 'App\Models\Category';

    public function list()
    {
        $table = $this->getTable();
        $query = $this->select("{$table}.*")
                    ->with(['galleries', 'parent']);
        return $query;
    }

}