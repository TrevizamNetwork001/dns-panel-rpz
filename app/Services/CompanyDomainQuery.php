<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/** Preserve company scope when queries are cloned by the zone generator. */
class CompanyDomainQuery extends Builder
{
    public function __construct(ConnectionInterface $connection, public readonly ?int $empresaId)
    {
        parent::__construct($connection);
    }
}
