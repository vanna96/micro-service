<?php
namespace App\Repositories;

use App\Repositories\RepositoryBase;

/** @package App\Repositories */
class UserRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model        = 'App\Models\User';

    public function list()
    {
        $table = $this->getTable();
        $query = $this->select("{$table}.*");
        return $query;
    }

}